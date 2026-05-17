"""
WebSocket routes for SociAI OS.

Provides real-time bi-directional communication for:
  - Dashboard metric updates
  - Agent task progress and reasoning logs
  - Notification delivery
  - Content approval notifications
  - Sentiment alerts
  - Publishing status updates

Endpoint:
  WS /ws/connect    – Authenticated WebSocket connection
  WS /ws/agent/{task_id}  – Stream agent progress for a specific task
"""
from __future__ import annotations

import asyncio
import json
import logging
import time
from datetime import datetime, timezone
from typing import Any, Dict, Optional, Set
from uuid import UUID

import redis.asyncio as aioredis
from fastapi import APIRouter, Depends, HTTPException, Query, WebSocket, WebSocketDisconnect, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.api.deps import get_redis
from app.core.config import settings
from app.core.security import decode_access_token

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Connection Manager ───────────────────────────────────────────────────────

class ConnectionManager:
    """
    Manages active WebSocket connections with per-user grouping.
    Supports broadcasting to individual users or all users, and pub/sub
    via Redis for multi-process deployments.
    """

    def __init__(self):
        # user_id → set of WebSocket connections
        self._user_connections: Dict[str, Set[WebSocket]] = {}
        self._connection_metadata: Dict[WebSocket, Dict[str, Any]] = {}
        self._lock = asyncio.Lock()

    async def connect(self, websocket: WebSocket, user_id: str, metadata: Dict[str, Any] = None):
        await websocket.accept()
        async with self._lock:
            self._user_connections.setdefault(user_id, set()).add(websocket)
            self._connection_metadata[websocket] = {
                "user_id": user_id,
                "connected_at": datetime.now(timezone.utc).isoformat(),
                **(metadata or {}),
            }
        logger.info("WS connected: user=%s total_connections=%d", user_id, self.connection_count)

    async def disconnect(self, websocket: WebSocket, user_id: str):
        async with self._lock:
            conns = self._user_connections.get(user_id, set())
            conns.discard(websocket)
            if not conns:
                self._user_connections.pop(user_id, None)
            self._connection_metadata.pop(websocket, None)
        logger.info("WS disconnected: user=%s remaining=%d", user_id, self.connection_count)

    async def send_to_user(self, user_id: str, message: Dict[str, Any]) -> int:
        """Send a JSON message to all connections for a specific user. Returns send count."""
        conns = self._user_connections.get(user_id, set())
        if not conns:
            return 0
        payload = json.dumps(message, default=str)
        sent = 0
        dead: Set[WebSocket] = set()
        for ws in list(conns):
            try:
                await ws.send_text(payload)
                sent += 1
            except Exception:
                dead.add(ws)
        # Clean up dead connections
        async with self._lock:
            for ws in dead:
                conns.discard(ws)
                self._connection_metadata.pop(ws, None)
        return sent

    async def broadcast(self, message: Dict[str, Any]) -> int:
        """Send a message to every connected user."""
        total = 0
        for user_id in list(self._user_connections.keys()):
            total += await self.send_to_user(user_id, message)
        return total

    @property
    def connection_count(self) -> int:
        return sum(len(v) for v in self._user_connections.values())

    @property
    def connected_users(self) -> Set[str]:
        return set(self._user_connections.keys())

    def get_user_connection_count(self, user_id: str) -> int:
        return len(self._user_connections.get(user_id, set()))


# Global connection manager (single-process; Redis pub/sub extends this for multi-process)
manager = ConnectionManager()


# ─── Authentication Helper ────────────────────────────────────────────────────

async def _authenticate_websocket(websocket: WebSocket, token: Optional[str]) -> Optional[str]:
    """
    Decode JWT from query param, return user_id or None if invalid.
    Closes WebSocket with 4001 code on auth failure.
    """
    if not token:
        await websocket.close(code=4001, reason="Authentication required")
        return None
    try:
        from jose import JWTError
        payload = decode_access_token(token)
        user_id = payload.get("sub")
        if not user_id:
            raise JWTError("No subject")
        return user_id
    except Exception as exc:
        logger.warning("WS auth failed: %s", exc)
        await websocket.close(code=4001, reason="Invalid token")
        return None


# ─── Message Handlers ─────────────────────────────────────────────────────────

async def _handle_client_message(
    websocket: WebSocket,
    user_id: str,
    message: Dict[str, Any],
    redis: aioredis.Redis,
):
    """Process incoming messages from the client."""
    msg_type = message.get("type", "")

    if msg_type == "ping":
        await websocket.send_text(json.dumps({"type": "pong", "ts": time.time()}))

    elif msg_type == "subscribe":
        # Subscribe to specific event channels
        channels = message.get("channels", [])
        valid_channels = {"notifications", "agent_progress", "analytics", "publishing", "community"}
        for ch in channels:
            if ch in valid_channels:
                # Acknowledge subscription
                await websocket.send_text(json.dumps({
                    "type": "subscribed",
                    "channel": ch,
                    "ts": time.time(),
                }))

    elif msg_type == "unsubscribe":
        channels = message.get("channels", [])
        for ch in channels:
            await websocket.send_text(json.dumps({
                "type": "unsubscribed",
                "channel": ch,
                "ts": time.time(),
            }))

    elif msg_type == "request_dashboard_update":
        # Trigger a fresh analytics snapshot
        await redis.publish(f"dashboard_refresh:{user_id}", "1")
        await websocket.send_text(json.dumps({
            "type": "dashboard_refresh_queued",
            "ts": time.time(),
        }))

    else:
        await websocket.send_text(json.dumps({
            "type": "error",
            "code": "UNKNOWN_MESSAGE_TYPE",
            "message": f"Unknown message type: {msg_type}",
        }))


async def _redis_pubsub_listener(
    user_id: str,
    websocket: WebSocket,
    redis: aioredis.Redis,
    stop_event: asyncio.Event,
):
    """
    Listen to Redis pub/sub channels for this user and forward messages
    to the WebSocket. Runs as a background coroutine.
    """
    channels = [
        f"user:{user_id}:notifications",
        f"user:{user_id}:agent_progress",
        f"user:{user_id}:publishing_status",
        f"user:{user_id}:analytics_update",
        f"user:{user_id}:sentiment_alert",
        f"user:{user_id}:approval_request",
    ]
    pubsub = redis.pubsub()
    try:
        await pubsub.subscribe(*channels)
        while not stop_event.is_set():
            message = await pubsub.get_message(ignore_subscribe_messages=True, timeout=1.0)
            if message and message.get("type") == "message":
                data = message.get("data", "")
                try:
                    payload = json.loads(data)
                    await websocket.send_text(json.dumps(payload, default=str))
                except json.JSONDecodeError:
                    await websocket.send_text(json.dumps({
                        "type": "raw_event",
                        "data": data,
                    }))
    except asyncio.CancelledError:
        pass
    finally:
        await pubsub.unsubscribe(*channels)
        await pubsub.close()


# ─── Main WebSocket Endpoint ──────────────────────────────────────────────────

@router.websocket("/connect")
async def websocket_endpoint(
    websocket: WebSocket,
    token: Optional[str] = Query(default=None, description="JWT access token"),
    redis: aioredis.Redis = Depends(get_redis),
):
    """
    Primary WebSocket endpoint for real-time dashboard updates.

    Authentication: Pass JWT access token as ?token= query parameter.

    Client → Server message types:
      - ping               → pong
      - subscribe          → subscribed (per channel)
      - unsubscribe        → unsubscribed
      - request_dashboard_update

    Server → Client event types:
      - connection_established
      - heartbeat
      - notification
      - agent_progress
      - agent_completed
      - publishing_status
      - analytics_update
      - sentiment_alert
      - approval_request
      - dashboard_metrics
      - pong
    """
    user_id = await _authenticate_websocket(websocket, token)
    if not user_id:
        return

    # Enforce per-user connection limit
    if manager.get_user_connection_count(user_id) >= settings.WS_MAX_CONNECTIONS_PER_USER:
        await websocket.close(code=4008, reason="Too many connections")
        return

    await manager.connect(websocket, user_id, metadata={"token_preview": (token or "")[:8]})

    stop_event = asyncio.Event()
    pubsub_task = asyncio.create_task(
        _redis_pubsub_listener(user_id, websocket, redis, stop_event)
    )

    try:
        # Send welcome message
        await websocket.send_text(json.dumps({
            "type": "connection_established",
            "user_id": user_id,
            "server_time": datetime.now(timezone.utc).isoformat(),
            "heartbeat_interval_seconds": settings.WS_HEARTBEAT_INTERVAL,
            "subscribed_channels": [
                f"user:{user_id}:notifications",
                f"user:{user_id}:agent_progress",
                f"user:{user_id}:publishing_status",
                f"user:{user_id}:analytics_update",
                f"user:{user_id}:sentiment_alert",
                f"user:{user_id}:approval_request",
            ],
        }))

        heartbeat_task = asyncio.create_task(_heartbeat_loop(websocket, user_id))

        try:
            while True:
                raw = await websocket.receive_text()
                try:
                    message = json.loads(raw)
                except json.JSONDecodeError:
                    await websocket.send_text(json.dumps({
                        "type": "error",
                        "code": "INVALID_JSON",
                        "message": "Message must be valid JSON",
                    }))
                    continue
                await _handle_client_message(websocket, user_id, message, redis)

        except WebSocketDisconnect:
            logger.info("WS client disconnected: user=%s", user_id)
        finally:
            heartbeat_task.cancel()

    finally:
        stop_event.set()
        pubsub_task.cancel()
        try:
            await pubsub_task
        except asyncio.CancelledError:
            pass
        await manager.disconnect(websocket, user_id)


async def _heartbeat_loop(websocket: WebSocket, user_id: str):
    """Send periodic heartbeat pings to keep the connection alive."""
    try:
        while True:
            await asyncio.sleep(settings.WS_HEARTBEAT_INTERVAL)
            await websocket.send_text(json.dumps({
                "type": "heartbeat",
                "ts": time.time(),
                "server_time": datetime.now(timezone.utc).isoformat(),
                "active_users": len(manager.connected_users),
            }))
    except (WebSocketDisconnect, asyncio.CancelledError):
        pass
    except Exception as exc:
        logger.debug("Heartbeat error for user %s: %s", user_id, exc)


# ─── Agent Progress Stream ────────────────────────────────────────────────────

@router.websocket("/agent/{task_id}")
async def agent_progress_websocket(
    websocket: WebSocket,
    task_id: str,
    token: Optional[str] = Query(default=None),
    redis: aioredis.Redis = Depends(get_redis),
):
    """
    WebSocket endpoint for streaming real-time progress of a specific agent task.
    Closes automatically when task reaches a terminal state.
    """
    user_id = await _authenticate_websocket(websocket, token)
    if not user_id:
        return

    # Verify task ownership
    task_data = await redis.get(f"agent_task:{task_id}")
    if not task_data:
        await websocket.close(code=4004, reason="Task not found")
        return

    task = json.loads(task_data)
    if task.get("user_id") != user_id:
        await websocket.close(code=4003, reason="Not authorised")
        return

    await websocket.accept()
    logger.info("WS agent stream opened: user=%s task=%s", user_id, task_id)

    pubsub = redis.pubsub()
    channel = f"agent_progress:{task_id}"
    await pubsub.subscribe(channel)

    try:
        # Send current task state
        await websocket.send_text(json.dumps({
            "type": "task_state",
            "task_id": task_id,
            "status": task.get("status", "queued"),
            "progress_pct": task.get("progress_pct", 0),
            "created_at": task.get("created_at"),
        }))

        TERMINAL_STATES = {"completed", "failed", "cancelled"}

        while True:
            # Check if task is in terminal state
            fresh = await redis.get(f"agent_task:{task_id}")
            if fresh:
                fresh_task = json.loads(fresh)
                if fresh_task.get("status") in TERMINAL_STATES:
                    await websocket.send_text(json.dumps({
                        "type": "task_terminal",
                        "task_id": task_id,
                        "status": fresh_task["status"],
                        "result": fresh_task.get("result"),
                        "error_message": fresh_task.get("error_message"),
                        "completed_at": fresh_task.get("completed_at"),
                        "tokens_used": fresh_task.get("tokens_used"),
                        "cost_usd": fresh_task.get("cost_usd"),
                    }))
                    break

            # Check for pub/sub messages
            message = await pubsub.get_message(ignore_subscribe_messages=True, timeout=2.0)
            if message and message.get("type") == "message":
                data = message.get("data", "")
                try:
                    payload = json.loads(data)
                    await websocket.send_text(json.dumps(payload, default=str))
                    # Exit on terminal state received via pub/sub
                    if payload.get("status") in TERMINAL_STATES:
                        break
                except json.JSONDecodeError:
                    pass

    except WebSocketDisconnect:
        logger.info("WS agent stream disconnected: user=%s task=%s", user_id, task_id)
    except asyncio.CancelledError:
        pass
    finally:
        await pubsub.unsubscribe(channel)
        await pubsub.close()


# ─── Utility: push notification from server-side code ─────────────────────────

async def push_notification(
    redis: aioredis.Redis,
    user_id: str,
    notification_type: str,
    title: str,
    message: str,
    metadata: Optional[Dict[str, Any]] = None,
    link: Optional[str] = None,
):
    """
    Utility function to publish a notification to a user's WebSocket channel.
    Call this from services, background tasks, or Celery workers.
    """
    payload = {
        "type": "notification",
        "notification_type": notification_type,
        "title": title,
        "message": message,
        "link": link,
        "metadata": metadata or {},
        "ts": datetime.now(timezone.utc).isoformat(),
    }
    await redis.publish(
        f"user:{user_id}:notifications",
        json.dumps(payload, default=str),
    )


async def push_agent_progress(
    redis: aioredis.Redis,
    task_id: str,
    status: str,
    progress_pct: float,
    message: Optional[str] = None,
    result: Optional[Dict[str, Any]] = None,
    reasoning_step: Optional[str] = None,
):
    """
    Utility to broadcast agent task progress updates.
    Called by AI agent workers during task execution.
    """
    payload = {
        "type": "agent_progress",
        "task_id": task_id,
        "status": status,
        "progress_pct": progress_pct,
        "message": message,
        "result": result,
        "reasoning_step": reasoning_step,
        "ts": datetime.now(timezone.utc).isoformat(),
    }
    await redis.publish(f"agent_progress:{task_id}", json.dumps(payload, default=str))


async def push_publishing_status(
    redis: aioredis.Redis,
    user_id: str,
    post_id: str,
    platform: str,
    status: str,
    error_message: Optional[str] = None,
    published_url: Optional[str] = None,
):
    """Notify the dashboard of post publishing status changes."""
    payload = {
        "type": "publishing_status",
        "post_id": post_id,
        "platform": platform,
        "status": status,
        "error_message": error_message,
        "published_url": published_url,
        "ts": datetime.now(timezone.utc).isoformat(),
    }
    await redis.publish(
        f"user:{user_id}:publishing_status",
        json.dumps(payload, default=str),
    )


async def push_analytics_update(
    redis: aioredis.Redis,
    user_id: str,
    metric_type: str,
    data: Dict[str, Any],
):
    """Push live analytics updates to the dashboard."""
    payload = {
        "type": "analytics_update",
        "metric_type": metric_type,
        "data": data,
        "ts": datetime.now(timezone.utc).isoformat(),
    }
    await redis.publish(
        f"user:{user_id}:analytics_update",
        json.dumps(payload, default=str),
    )


async def push_sentiment_alert(
    redis: aioredis.Redis,
    user_id: str,
    severity: str,
    platform: str,
    message: str,
    post_ids: Optional[list] = None,
):
    """Push urgent sentiment/crisis alerts."""
    payload = {
        "type": "sentiment_alert",
        "severity": severity,
        "platform": platform,
        "message": message,
        "post_ids": post_ids or [],
        "ts": datetime.now(timezone.utc).isoformat(),
    }
    await redis.publish(
        f"user:{user_id}:sentiment_alert",
        json.dumps(payload, default=str),
    )
