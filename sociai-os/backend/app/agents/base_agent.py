"""
Base agent class providing shared infrastructure for all AI agents:
memory, queuing, caching, retry logic, cost tracking, and structured logging.
"""

import asyncio
import json
import logging
import time
import uuid
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from typing import Any, Dict, List, Optional, Tuple

import redis.asyncio as aioredis
from tenacity import (
    AsyncRetrying,
    RetryError,
    retry_if_exception_type,
    stop_after_attempt,
    wait_exponential,
)

logger = logging.getLogger(__name__)


@dataclass
class AgentMessage:
    sender: str
    recipient: str
    content: Any
    message_id: str = field(default_factory=lambda: str(uuid.uuid4()))
    timestamp: datetime = field(default_factory=datetime.utcnow)
    metadata: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "sender": self.sender,
            "recipient": self.recipient,
            "content": self.content,
            "message_id": self.message_id,
            "timestamp": self.timestamp.isoformat(),
            "metadata": self.metadata,
        }


@dataclass
class AgentResult:
    success: bool
    data: Any
    agent_name: str
    task: str
    duration_ms: float
    cost_usd: float = 0.0
    tokens_used: int = 0
    error: Optional[str] = None
    metadata: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "success": self.success,
            "data": self.data,
            "agent_name": self.agent_name,
            "task": self.task,
            "duration_ms": self.duration_ms,
            "cost_usd": self.cost_usd,
            "tokens_used": self.tokens_used,
            "error": self.error,
            "metadata": self.metadata,
        }


class CostTracker:
    # Pricing per 1k tokens (input/output) as of 2025
    MODEL_PRICING: Dict[str, Tuple[float, float]] = {
        "claude-sonnet-4-6": (0.003, 0.015),
        "claude-opus-4-5": (0.015, 0.075),
        "claude-haiku-3-5": (0.00025, 0.00125),
        "gpt-4o": (0.005, 0.015),
        "gpt-4o-mini": (0.00015, 0.0006),
        "dall-e-3": (0.04, 0.0),   # per image
        "stability-ai": (0.002, 0.0),  # per step
    }

    def __init__(self, redis_client: aioredis.Redis, brand_id: str):
        self.redis = redis_client
        self.brand_id = brand_id
        self._session_cost: float = 0.0
        self._session_tokens: int = 0

    async def record(
        self,
        model: str,
        input_tokens: int,
        output_tokens: int,
        agent_name: str,
        task: str,
    ) -> float:
        pricing = self.MODEL_PRICING.get(model, (0.001, 0.002))
        cost = (input_tokens / 1000 * pricing[0]) + (output_tokens / 1000 * pricing[1])
        total_tokens = input_tokens + output_tokens

        self._session_cost += cost
        self._session_tokens += total_tokens

        record = {
            "brand_id": self.brand_id,
            "agent": agent_name,
            "task": task,
            "model": model,
            "input_tokens": input_tokens,
            "output_tokens": output_tokens,
            "cost_usd": cost,
            "timestamp": datetime.utcnow().isoformat(),
        }

        key = f"cost:{self.brand_id}:{datetime.utcnow().strftime('%Y-%m')}"
        await self.redis.lpush(key, json.dumps(record))
        await self.redis.expire(key, 90 * 86400)  # keep 90 days
        await self.redis.incrbyfloat(f"cost_total:{self.brand_id}", cost)

        logger.info(
            "cost_recorded",
            extra={
                "brand_id": self.brand_id,
                "model": model,
                "cost_usd": round(cost, 6),
                "tokens": total_tokens,
                "agent": agent_name,
            },
        )
        return cost

    async def get_monthly_cost(self) -> float:
        key = f"cost_total:{self.brand_id}"
        val = await self.redis.get(key)
        return float(val) if val else 0.0


class BaseAgent(ABC):
    """
    Abstract base for all SociAI-OS agents.

    Every concrete agent must implement execute(). The base class wires up:
    - Redis-backed short-term memory (TTL-managed)
    - Result cache keyed by task hash (avoids duplicate LLM calls)
    - Structured JSON logging
    - Exponential-backoff retry with configurable attempts
    - Per-brand cost tracking
    - Simple agent-to-agent messaging via Redis pub/sub channels
    """

    CACHE_TTL: int = 3600          # 1 hour default result cache
    MEMORY_TTL: int = 86400 * 7    # 7-day agent memory
    MAX_RETRIES: int = 3

    def __init__(
        self,
        agent_name: str,
        brand_id: str,
        redis_url: str = "redis://localhost:6379/0",
    ):
        self.agent_name = agent_name
        self.brand_id = brand_id
        self._redis_url = redis_url
        self._redis: Optional[aioredis.Redis] = None
        self._cost_tracker: Optional[CostTracker] = None
        self._log = logging.getLogger(f"agent.{agent_name}")

    # ------------------------------------------------------------------
    # Lifecycle
    # ------------------------------------------------------------------

    async def _get_redis(self) -> aioredis.Redis:
        if self._redis is None:
            self._redis = await aioredis.from_url(
                self._redis_url, encoding="utf-8", decode_responses=True
            )
        return self._redis

    async def _get_cost_tracker(self) -> CostTracker:
        if self._cost_tracker is None:
            redis = await self._get_redis()
            self._cost_tracker = CostTracker(redis, self.brand_id)
        return self._cost_tracker

    async def close(self) -> None:
        if self._redis:
            await self._redis.close()

    # ------------------------------------------------------------------
    # Abstract interface
    # ------------------------------------------------------------------

    @abstractmethod
    async def execute(self, task: str, **kwargs) -> AgentResult:
        """Run the agent's primary task. Must be implemented by subclasses."""

    # ------------------------------------------------------------------
    # Memory
    # ------------------------------------------------------------------

    async def remember(self, key: str, value: Any, ttl: Optional[int] = None) -> None:
        redis = await self._get_redis()
        full_key = f"memory:{self.agent_name}:{self.brand_id}:{key}"
        serialized = json.dumps(value, default=str)
        await redis.set(full_key, serialized, ex=ttl or self.MEMORY_TTL)

    async def recall(self, key: str) -> Optional[Any]:
        redis = await self._get_redis()
        full_key = f"memory:{self.agent_name}:{self.brand_id}:{key}"
        raw = await redis.get(full_key)
        return json.loads(raw) if raw else None

    async def forget(self, key: str) -> None:
        redis = await self._get_redis()
        full_key = f"memory:{self.agent_name}:{self.brand_id}:{key}"
        await redis.delete(full_key)

    async def recall_all(self, pattern: str = "*") -> Dict[str, Any]:
        redis = await self._get_redis()
        prefix = f"memory:{self.agent_name}:{self.brand_id}:"
        keys = await redis.keys(f"{prefix}{pattern}")
        result = {}
        for key in keys:
            raw = await redis.get(key)
            if raw:
                short_key = key[len(prefix):]
                result[short_key] = json.loads(raw)
        return result

    # ------------------------------------------------------------------
    # Result cache
    # ------------------------------------------------------------------

    def _cache_key(self, task: str, kwargs: Dict[str, Any]) -> str:
        import hashlib
        payload = json.dumps({"task": task, **kwargs}, sort_keys=True, default=str)
        digest = hashlib.sha256(payload.encode()).hexdigest()[:16]
        return f"cache:{self.agent_name}:{self.brand_id}:{digest}"

    async def get_cached(self, task: str, **kwargs) -> Optional[AgentResult]:
        redis = await self._get_redis()
        key = self._cache_key(task, kwargs)
        raw = await redis.get(key)
        if raw:
            self._log.debug("cache_hit", extra={"key": key})
            data = json.loads(raw)
            result = AgentResult(**data)
            result.metadata["from_cache"] = True
            return result
        return None

    async def cache_result(self, task: str, result: AgentResult, ttl: Optional[int] = None, **kwargs) -> None:
        redis = await self._get_redis()
        key = self._cache_key(task, kwargs)
        await redis.set(key, json.dumps(result.to_dict(), default=str), ex=ttl or self.CACHE_TTL)

    # ------------------------------------------------------------------
    # Retry wrapper
    # ------------------------------------------------------------------

    async def run_with_retry(self, coro, max_attempts: Optional[int] = None):
        attempts = max_attempts or self.MAX_RETRIES
        try:
            async for attempt in AsyncRetrying(
                stop=stop_after_attempt(attempts),
                wait=wait_exponential(multiplier=1, min=2, max=30),
                retry=retry_if_exception_type((ConnectionError, TimeoutError, OSError)),
                reraise=True,
            ):
                with attempt:
                    return await coro
        except RetryError as exc:
            self._log.error("retry_exhausted", extra={"error": str(exc)})
            raise

    # ------------------------------------------------------------------
    # Agent-to-agent communication
    # ------------------------------------------------------------------

    async def communicate_with(
        self,
        other_agent: "BaseAgent",
        message_content: Any,
        metadata: Optional[Dict[str, Any]] = None,
    ) -> AgentMessage:
        msg = AgentMessage(
            sender=self.agent_name,
            recipient=other_agent.agent_name,
            content=message_content,
            metadata=metadata or {},
        )
        redis = await self._get_redis()
        channel = f"agent_channel:{self.brand_id}:{other_agent.agent_name}"
        await redis.lpush(channel, json.dumps(msg.to_dict(), default=str))
        await redis.expire(channel, 3600)
        self._log.info(
            "message_sent",
            extra={
                "to": other_agent.agent_name,
                "message_id": msg.message_id,
            },
        )
        return msg

    async def receive_messages(self, max_count: int = 10) -> List[AgentMessage]:
        redis = await self._get_redis()
        channel = f"agent_channel:{self.brand_id}:{self.agent_name}"
        messages = []
        for _ in range(max_count):
            raw = await redis.rpop(channel)
            if not raw:
                break
            data = json.loads(raw)
            data["timestamp"] = datetime.fromisoformat(data["timestamp"])
            messages.append(AgentMessage(**data))
        return messages

    # ------------------------------------------------------------------
    # Shared context (blackboard pattern)
    # ------------------------------------------------------------------

    async def set_shared_context(self, workflow_id: str, key: str, value: Any) -> None:
        redis = await self._get_redis()
        ctx_key = f"workflow:{self.brand_id}:{workflow_id}:{key}"
        await redis.set(ctx_key, json.dumps(value, default=str), ex=86400)

    async def get_shared_context(self, workflow_id: str, key: str) -> Optional[Any]:
        redis = await self._get_redis()
        ctx_key = f"workflow:{self.brand_id}:{workflow_id}:{key}"
        raw = await redis.get(ctx_key)
        return json.loads(raw) if raw else None

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    def _start_timer(self) -> float:
        return time.perf_counter()

    def _elapsed_ms(self, start: float) -> float:
        return round((time.perf_counter() - start) * 1000, 2)

    def _make_result(
        self,
        task: str,
        data: Any,
        start: float,
        cost: float = 0.0,
        tokens: int = 0,
        error: Optional[str] = None,
        **metadata,
    ) -> AgentResult:
        return AgentResult(
            success=error is None,
            data=data,
            agent_name=self.agent_name,
            task=task,
            duration_ms=self._elapsed_ms(start),
            cost_usd=cost,
            tokens_used=tokens,
            error=error,
            metadata=metadata,
        )

    def _log_execution(self, result: AgentResult) -> None:
        level = logging.INFO if result.success else logging.ERROR
        self._log.log(
            level,
            "task_completed" if result.success else "task_failed",
            extra={
                "agent": self.agent_name,
                "brand_id": self.brand_id,
                "task": result.task,
                "duration_ms": result.duration_ms,
                "cost_usd": result.cost_usd,
                "tokens": result.tokens_used,
                "error": result.error,
            },
        )

    async def track_cost(
        self,
        model: str,
        input_tokens: int,
        output_tokens: int,
        task: str,
    ) -> float:
        tracker = await self._get_cost_tracker()
        return await tracker.record(model, input_tokens, output_tokens, self.agent_name, task)
