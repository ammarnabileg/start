"""
app/core/redis_client.py
────────────────────────
Redis client with a connection pool, pub/sub helpers, and caching utilities.
Uses the redis-py async client (redis>=4.2).
"""
from __future__ import annotations

import asyncio
import contextlib
import json
from typing import Any, AsyncGenerator, Callable, Optional

import redis.asyncio as aioredis
from redis.asyncio import ConnectionPool, Redis
from redis.asyncio.client import PubSub

import structlog

from app.core.config import settings

logger = structlog.get_logger(__name__)

# ─── Connection Pool ─────────────────────────────────────────────────────────

_pool: Optional[ConnectionPool] = None


def _build_pool() -> ConnectionPool:
    return ConnectionPool.from_url(
        settings.REDIS_URL,
        max_connections=settings.REDIS_MAX_CONNECTIONS,
        socket_timeout=settings.REDIS_SOCKET_TIMEOUT,
        socket_connect_timeout=settings.REDIS_SOCKET_CONNECT_TIMEOUT,
        retry_on_timeout=settings.REDIS_RETRY_ON_TIMEOUT,
        decode_responses=settings.REDIS_DECODE_RESPONSES,
        health_check_interval=30,
    )


def get_pool() -> ConnectionPool:
    global _pool
    if _pool is None:
        _pool = _build_pool()
    return _pool


def get_redis() -> Redis:
    """Return a Redis client sharing the global connection pool."""
    return Redis(connection_pool=get_pool())


# Singleton client – used as a FastAPI dependency
redis_client: Redis = get_redis()


# ─── FastAPI Dependency ───────────────────────────────────────────────────────

async def get_redis_client() -> AsyncGenerator[Redis, None]:
    """
    FastAPI dependency that yields the shared Redis client.

    Usage::

        @router.get("/ping")
        async def ping(r: Redis = Depends(get_redis_client)):
            return await r.ping()
    """
    yield redis_client


# ─── Lifecycle ───────────────────────────────────────────────────────────────

async def close_redis() -> None:
    """Close all pool connections cleanly on application shutdown."""
    global _pool
    if _pool is not None:
        await _pool.aclose()
        _pool = None
        logger.info("redis.pool_closed")


async def check_connection() -> bool:
    """Verify Redis is reachable. Returns True on success."""
    try:
        return await redis_client.ping()
    except Exception as exc:
        logger.error("redis.connection_failed", error=str(exc))
        return False


# ─── Key Namespacing ─────────────────────────────────────────────────────────

APP_PREFIX = "sociai"


def make_key(*parts: str) -> str:
    """Build a namespaced Redis key: sociai:<part1>:<part2>:..."""
    return f"{APP_PREFIX}:" + ":".join(parts)


def user_key(user_id: str, *parts: str) -> str:
    return make_key("user", user_id, *parts)


def session_key(session_id: str) -> str:
    return make_key("session", session_id)


def rate_limit_key(identifier: str, endpoint: str) -> str:
    return make_key("ratelimit", endpoint, identifier)


def cache_key(*parts: str) -> str:
    return make_key("cache", *parts)


def pubsub_channel(channel: str) -> str:
    return make_key("channel", channel)


# ─── JSON Cache Helpers ───────────────────────────────────────────────────────

async def cache_set(
    key: str,
    value: Any,
    ttl_seconds: int = 300,
    *,
    client: Optional[Redis] = None,
) -> None:
    """Serialize value to JSON and store it with a TTL."""
    r = client or redis_client
    await r.setex(key, ttl_seconds, json.dumps(value, default=str))


async def cache_get(
    key: str,
    *,
    client: Optional[Redis] = None,
) -> Optional[Any]:
    """Retrieve and deserialize a cached JSON value. Returns None on miss."""
    r = client or redis_client
    raw = await r.get(key)
    if raw is None:
        return None
    return json.loads(raw)


async def cache_delete(key: str, *, client: Optional[Redis] = None) -> int:
    """Delete a cache key. Returns the number of keys deleted."""
    r = client or redis_client
    return await r.delete(key)


async def cache_get_or_set(
    key: str,
    factory: Callable[[], Any],
    ttl_seconds: int = 300,
    *,
    client: Optional[Redis] = None,
) -> Any:
    """
    Return the cached value for *key*, or call *factory()*, store its result,
    and return it.  *factory* may be a coroutine function.
    """
    r = client or redis_client
    cached = await cache_get(key, client=r)
    if cached is not None:
        return cached

    value = await factory() if asyncio.iscoroutinefunction(factory) else factory()
    await cache_set(key, value, ttl_seconds, client=r)
    return value


async def invalidate_prefix(prefix: str, *, client: Optional[Redis] = None) -> int:
    """Delete all keys matching a prefix pattern. Returns number of deleted keys."""
    r = client or redis_client
    pattern = f"{prefix}*"
    keys = [key async for key in r.scan_iter(match=pattern, count=100)]
    if keys:
        return await r.delete(*keys)
    return 0


# ─── Counter / Atomic Helpers ─────────────────────────────────────────────────

async def increment_counter(
    key: str,
    amount: int = 1,
    ttl_seconds: Optional[int] = None,
    *,
    client: Optional[Redis] = None,
) -> int:
    """Atomically increment a counter and optionally set/refresh its TTL."""
    r = client or redis_client
    value = await r.incrby(key, amount)
    if ttl_seconds:
        await r.expire(key, ttl_seconds)
    return value


async def sliding_window_rate_limit(
    key: str,
    limit: int,
    window_seconds: int,
    *,
    client: Optional[Redis] = None,
) -> tuple[int, int, bool]:
    """
    Sliding-window rate limiter.

    Returns:
        (current_count, remaining, is_limited)
    """
    import time
    import secrets as _secrets

    r = client or redis_client
    now = int(time.time() * 1000)  # millisecond precision
    window_start = now - (window_seconds * 1000)

    async with r.pipeline() as pipe:
        pipe.zremrangebyscore(key, "-inf", window_start)
        pipe.zadd(key, {f"{now}:{_secrets.token_hex(4)}": now})
        pipe.zcard(key)
        pipe.pexpire(key, window_seconds * 1000)
        results = await pipe.execute()

    count: int = results[2]
    remaining = max(0, limit - count)
    is_limited = count > limit
    return count, remaining, is_limited


# ─── Pub/Sub Helpers ─────────────────────────────────────────────────────────

@contextlib.asynccontextmanager
async def get_pubsub() -> AsyncGenerator[PubSub, None]:
    """
    Context manager that yields a PubSub object backed by a dedicated connection.

    Usage::

        async with get_pubsub() as ps:
            await ps.subscribe("my-channel")
            async for message in ps.listen():
                if message["type"] == "message":
                    print(message["data"])
    """
    # PubSub needs its own connection; don't use the shared pool client
    dedicated_client: Redis = Redis.from_url(
        settings.REDIS_URL,
        decode_responses=settings.REDIS_DECODE_RESPONSES,
        socket_timeout=None,  # blocking listen
    )
    ps = dedicated_client.pubsub(ignore_subscribe_messages=True)
    try:
        yield ps
    finally:
        await ps.unsubscribe()
        await ps.aclose()
        await dedicated_client.aclose()


async def publish(channel: str, message: Any, *, client: Optional[Redis] = None) -> int:
    """Publish a JSON-serialized message to a channel."""
    r = client or redis_client
    payload = json.dumps(message, default=str)
    return await r.publish(pubsub_channel(channel), payload)


async def listen_channel(
    channel: str,
    handler: Callable[[Any], Any],
    *,
    stop_event: Optional[asyncio.Event] = None,
) -> None:
    """
    Subscribe to a channel and call *handler* for each incoming message.
    Stops when *stop_event* is set (or the task is cancelled).

    *handler* may be a coroutine function.
    """
    full_channel = pubsub_channel(channel)
    async with get_pubsub() as ps:
        await ps.subscribe(full_channel)
        logger.info("redis.pubsub_subscribed", channel=full_channel)
        async for raw in ps.listen():
            if stop_event and stop_event.is_set():
                break
            if raw["type"] != "message":
                continue
            try:
                data = json.loads(raw["data"])
                if asyncio.iscoroutinefunction(handler):
                    await handler(data)
                else:
                    handler(data)
            except Exception as exc:
                logger.error(
                    "redis.pubsub_handler_error",
                    channel=full_channel,
                    error=str(exc),
                    exc_info=True,
                )


# ─── Distributed Lock ─────────────────────────────────────────────────────────

@contextlib.asynccontextmanager
async def acquire_lock(
    lock_name: str,
    timeout_seconds: int = 30,
    *,
    client: Optional[Redis] = None,
) -> AsyncGenerator[bool, None]:
    """
    Simple distributed lock using SET NX EX.

    Usage::

        async with acquire_lock("my-lock") as acquired:
            if acquired:
                do_work()
    """
    import secrets as _secrets

    r = client or redis_client
    key = make_key("lock", lock_name)
    token = _secrets.token_hex(16)
    acquired = await r.set(key, token, nx=True, ex=timeout_seconds)
    try:
        yield bool(acquired)
    finally:
        if acquired:
            # Only release if we still own the lock (Lua script for atomicity)
            lua_script = """
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
            """
            await r.eval(lua_script, 1, key, token)


# ─── WebSocket Session Registry ──────────────────────────────────────────────

WS_SESSION_TTL = 3600  # 1 hour


async def register_ws_session(
    user_id: str,
    session_id: str,
    connection_id: str,
    *,
    client: Optional[Redis] = None,
) -> None:
    """Register an active WebSocket connection for a user."""
    r = client or redis_client
    key = user_key(user_id, "ws_connections")
    await r.hset(key, connection_id, session_id)
    await r.expire(key, WS_SESSION_TTL)


async def unregister_ws_session(
    user_id: str,
    connection_id: str,
    *,
    client: Optional[Redis] = None,
) -> None:
    """Remove a WebSocket connection from the registry."""
    r = client or redis_client
    key = user_key(user_id, "ws_connections")
    await r.hdel(key, connection_id)


async def get_user_ws_connections(
    user_id: str,
    *,
    client: Optional[Redis] = None,
) -> dict[str, str]:
    """Return all active WS connection_id → session_id mappings for a user."""
    r = client or redis_client
    key = user_key(user_id, "ws_connections")
    return await r.hgetall(key)
