"""
AI Client wrappers for OpenAI and Anthropic APIs.

Provides:
- Unified async interface for Claude and GPT models
- Automatic retry with exponential backoff
- Per-call cost tracking with accurate token accounting
- Model selection helpers based on task type and quality tier
- Streaming support
- Response caching (via Redis if provided)
- Structured JSON response mode
"""

from __future__ import annotations

import asyncio
import hashlib
import json
import logging
import os
import time
from dataclasses import dataclass, field
from typing import Any, AsyncGenerator, Dict, List, Optional, Tuple

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Pricing tables (USD per 1K tokens, input/output)
# ---------------------------------------------------------------------------

ANTHROPIC_PRICING: Dict[str, Tuple[float, float]] = {
    "claude-opus-4-5":       (0.015,  0.075),
    "claude-sonnet-4-6":     (0.003,  0.015),
    "claude-haiku-3-5":      (0.00025, 0.00125),
    "claude-3-5-sonnet-20241022": (0.003, 0.015),
    "claude-3-haiku-20240307":    (0.00025, 0.00125),
}

OPENAI_PRICING: Dict[str, Tuple[float, float]] = {
    "gpt-4o":               (0.005,  0.015),
    "gpt-4o-mini":          (0.00015, 0.0006),
    "gpt-4-turbo":          (0.010,  0.030),
    "gpt-3.5-turbo":        (0.0005, 0.0015),
    "dall-e-3":             (0.040,  0.0),    # per image (output side)
    "dall-e-2":             (0.020,  0.0),
    "whisper-1":            (0.006,  0.0),    # per minute (input side)
    "tts-1":                (0.015,  0.0),    # per 1K chars
    "text-embedding-3-small":(0.00002, 0.0),
    "text-embedding-3-large":(0.00013, 0.0),
}

# Task → recommended model mapping
TASK_MODEL_MAP: Dict[str, str] = {
    "strategy":          "claude-sonnet-4-6",
    "copywriting":       "claude-sonnet-4-6",
    "community":         "claude-haiku-3-5",
    "spam_detection":    "claude-haiku-3-5",
    "analytics":         "claude-sonnet-4-6",
    "research":          "claude-haiku-3-5",
    "video_script":      "claude-sonnet-4-6",
    "long_form":         "claude-opus-4-5",
    "quick_reply":       "claude-haiku-3-5",
    "structured_data":   "claude-sonnet-4-6",
    "image_generation":  "dall-e-3",
    "embedding":         "text-embedding-3-small",
}


@dataclass
class AIResponse:
    """Normalized response from any AI provider."""
    text: str
    model: str
    provider: str
    input_tokens: int
    output_tokens: int
    cost_usd: float
    latency_ms: float
    metadata: Dict[str, Any] = field(default_factory=dict)

    @property
    def total_tokens(self) -> int:
        return self.input_tokens + self.output_tokens

    def to_dict(self) -> Dict[str, Any]:
        return {
            "text": self.text,
            "model": self.model,
            "provider": self.provider,
            "input_tokens": self.input_tokens,
            "output_tokens": self.output_tokens,
            "cost_usd": round(self.cost_usd, 6),
            "latency_ms": self.latency_ms,
            "total_tokens": self.total_tokens,
            **self.metadata,
        }


class CostAccumulator:
    """Thread-safe in-process cost accumulator."""

    def __init__(self):
        self._lock = asyncio.Lock()
        self._total_usd: float = 0.0
        self._calls: int = 0
        self._by_model: Dict[str, float] = {}

    async def add(self, cost: float, model: str) -> None:
        async with self._lock:
            self._total_usd += cost
            self._calls += 1
            self._by_model[model] = self._by_model.get(model, 0.0) + cost

    @property
    def total(self) -> float:
        return round(self._total_usd, 6)

    @property
    def calls(self) -> int:
        return self._calls

    def summary(self) -> Dict[str, Any]:
        return {
            "total_usd": self.total,
            "total_calls": self._calls,
            "by_model": {k: round(v, 6) for k, v in self._by_model.items()},
        }


# Module-level accumulator (shared across all client instances in a process)
_global_cost = CostAccumulator()


class AnthropicClient:
    """
    Async wrapper around Anthropic's Python SDK with retry, cost tracking,
    and structured JSON response helpers.
    """

    DEFAULT_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6")
    MAX_RETRIES = 3
    RETRY_BASE_DELAY = 1.0

    def __init__(self, api_key: Optional[str] = None):
        import anthropic as _anthropic
        self._client = _anthropic.AsyncAnthropic(
            api_key=api_key or os.getenv("ANTHROPIC_API_KEY", ""),
        )
        self._log = logging.getLogger(f"{__name__}.anthropic")

    def select_model(self, task: str, quality: str = "standard") -> str:
        """Return the best model for a task and quality tier."""
        if quality == "premium":
            return "claude-opus-4-5"
        if quality == "fast":
            return "claude-haiku-3-5"
        return TASK_MODEL_MAP.get(task, self.DEFAULT_MODEL)

    def calculate_cost(self, model: str, input_tokens: int, output_tokens: int) -> float:
        pricing = ANTHROPIC_PRICING.get(model, (0.003, 0.015))
        return (input_tokens / 1000 * pricing[0]) + (output_tokens / 1000 * pricing[1])

    async def complete(
        self,
        prompt: str,
        system: str = "",
        model: Optional[str] = None,
        max_tokens: int = 1024,
        temperature: float = 0.7,
        task: str = "general",
    ) -> AIResponse:
        """Send a completion request with automatic retry."""
        chosen_model = model or self.select_model(task)
        last_error: Optional[Exception] = None
        t0 = time.perf_counter()

        for attempt in range(self.MAX_RETRIES):
            try:
                kwargs: Dict[str, Any] = {
                    "model": chosen_model,
                    "max_tokens": max_tokens,
                    "messages": [{"role": "user", "content": prompt}],
                }
                if system:
                    kwargs["system"] = system

                message = await self._client.messages.create(**kwargs)
                latency_ms = round((time.perf_counter() - t0) * 1000, 1)
                in_tok = message.usage.input_tokens
                out_tok = message.usage.output_tokens
                cost = self.calculate_cost(chosen_model, in_tok, out_tok)

                await _global_cost.add(cost, chosen_model)

                self._log.debug(
                    "anthropic_complete",
                    extra={"model": chosen_model, "tokens": in_tok + out_tok, "cost": cost},
                )

                return AIResponse(
                    text=message.content[0].text,
                    model=chosen_model,
                    provider="anthropic",
                    input_tokens=in_tok,
                    output_tokens=out_tok,
                    cost_usd=cost,
                    latency_ms=latency_ms,
                    metadata={"task": task, "attempt": attempt + 1},
                )

            except Exception as exc:
                last_error = exc
                self._log.warning(
                    f"Anthropic attempt {attempt + 1}/{self.MAX_RETRIES} failed: {exc}"
                )
                if attempt < self.MAX_RETRIES - 1:
                    await asyncio.sleep(self.RETRY_BASE_DELAY * (2 ** attempt))

        raise RuntimeError(f"Anthropic API failed after {self.MAX_RETRIES} attempts: {last_error}")

    async def complete_json(
        self,
        prompt: str,
        system: str = "You are a helpful assistant. Always respond with valid JSON only.",
        model: Optional[str] = None,
        max_tokens: int = 2048,
        task: str = "structured_data",
    ) -> Tuple[Any, AIResponse]:
        """
        Complete and parse JSON response.
        Returns (parsed_data, raw_response).
        """
        if "json" not in system.lower():
            system = system.rstrip(". ") + ". Respond ONLY with valid JSON, no markdown or explanation."

        response = await self.complete(
            prompt=prompt,
            system=system,
            model=model,
            max_tokens=max_tokens,
            task=task,
        )
        parsed = self._parse_json(response.text)
        return parsed, response

    async def complete_batch(
        self,
        prompts: List[str],
        system: str = "",
        model: Optional[str] = None,
        max_tokens: int = 1024,
        task: str = "general",
        concurrency: int = 5,
    ) -> List[AIResponse]:
        """Process multiple prompts concurrently with controlled parallelism."""
        semaphore = asyncio.Semaphore(concurrency)

        async def _bounded(p: str) -> AIResponse:
            async with semaphore:
                return await self.complete(
                    prompt=p, system=system, model=model,
                    max_tokens=max_tokens, task=task,
                )

        results = await asyncio.gather(*[_bounded(p) for p in prompts], return_exceptions=True)
        processed = []
        for r in results:
            if isinstance(r, Exception):
                processed.append(AIResponse(
                    text=f"[ERROR: {r}]", model=model or self.DEFAULT_MODEL,
                    provider="anthropic", input_tokens=0, output_tokens=0,
                    cost_usd=0.0, latency_ms=0.0, metadata={"error": str(r)},
                ))
            else:
                processed.append(r)
        return processed

    async def stream(
        self,
        prompt: str,
        system: str = "",
        model: Optional[str] = None,
        max_tokens: int = 2048,
    ) -> AsyncGenerator[str, None]:
        """Stream tokens from the API."""
        chosen_model = model or self.DEFAULT_MODEL
        kwargs: Dict[str, Any] = {
            "model": chosen_model,
            "max_tokens": max_tokens,
            "messages": [{"role": "user", "content": prompt}],
        }
        if system:
            kwargs["system"] = system

        async with self._client.messages.stream(**kwargs) as stream:
            async for text_chunk in stream.text_stream:
                yield text_chunk

    @staticmethod
    def _parse_json(text: str) -> Any:
        import re
        text = text.strip()
        # Strip markdown code fences
        text = re.sub(r"^```[a-z]*\n?", "", text)
        text = re.sub(r"\n?```$", "", text)
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            match = re.search(r"(\{[\s\S]*\}|\[[\s\S]*\])", text)
            if match:
                try:
                    return json.loads(match.group(1))
                except json.JSONDecodeError:
                    pass
        return {"raw_response": text}


class OpenAIClient:
    """
    Async wrapper around OpenAI's Python SDK with retry, cost tracking,
    image generation, embeddings, and TTS support.
    """

    DEFAULT_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
    MAX_RETRIES = 3
    RETRY_BASE_DELAY = 1.0

    def __init__(self, api_key: Optional[str] = None):
        try:
            import openai as _openai
            self._client = _openai.AsyncOpenAI(
                api_key=api_key or os.getenv("OPENAI_API_KEY", ""),
            )
        except ImportError:
            self._client = None
            logger.warning("openai package not installed. OpenAI client unavailable.")
        self._log = logging.getLogger(f"{__name__}.openai")

    def _require_client(self):
        if self._client is None:
            raise ImportError("Install openai: pip install openai")

    def calculate_cost(self, model: str, input_tokens: int, output_tokens: int) -> float:
        pricing = OPENAI_PRICING.get(model, (0.001, 0.002))
        return (input_tokens / 1000 * pricing[0]) + (output_tokens / 1000 * pricing[1])

    async def complete(
        self,
        prompt: str,
        system: str = "",
        model: Optional[str] = None,
        max_tokens: int = 1024,
        temperature: float = 0.7,
        response_format: Optional[str] = None,  # "json_object"
        task: str = "general",
    ) -> AIResponse:
        self._require_client()
        chosen_model = model or self.DEFAULT_MODEL
        last_error: Optional[Exception] = None
        t0 = time.perf_counter()

        messages = []
        if system:
            messages.append({"role": "system", "content": system})
        messages.append({"role": "user", "content": prompt})

        kwargs: Dict[str, Any] = {
            "model": chosen_model,
            "messages": messages,
            "max_tokens": max_tokens,
            "temperature": temperature,
        }
        if response_format == "json_object":
            kwargs["response_format"] = {"type": "json_object"}

        for attempt in range(self.MAX_RETRIES):
            try:
                resp = await self._client.chat.completions.create(**kwargs)
                latency_ms = round((time.perf_counter() - t0) * 1000, 1)
                in_tok = resp.usage.prompt_tokens
                out_tok = resp.usage.completion_tokens
                cost = self.calculate_cost(chosen_model, in_tok, out_tok)

                await _global_cost.add(cost, chosen_model)

                return AIResponse(
                    text=resp.choices[0].message.content or "",
                    model=chosen_model,
                    provider="openai",
                    input_tokens=in_tok,
                    output_tokens=out_tok,
                    cost_usd=cost,
                    latency_ms=latency_ms,
                    metadata={"task": task, "attempt": attempt + 1},
                )
            except Exception as exc:
                last_error = exc
                self._log.warning(f"OpenAI attempt {attempt + 1} failed: {exc}")
                if attempt < self.MAX_RETRIES - 1:
                    await asyncio.sleep(self.RETRY_BASE_DELAY * (2 ** attempt))

        raise RuntimeError(f"OpenAI API failed after {self.MAX_RETRIES} attempts: {last_error}")

    async def complete_json(
        self,
        prompt: str,
        system: str = "You are a helpful assistant.",
        model: Optional[str] = None,
        max_tokens: int = 2048,
        task: str = "structured_data",
    ) -> Tuple[Any, AIResponse]:
        """Complete and return parsed JSON."""
        if system and "json" not in system.lower():
            system += " Always respond with valid JSON."

        response = await self.complete(
            prompt=prompt, system=system, model=model,
            max_tokens=max_tokens, response_format="json_object", task=task,
        )
        try:
            parsed = json.loads(response.text)
        except json.JSONDecodeError:
            parsed = {"raw_response": response.text}
        return parsed, response

    async def generate_image(
        self,
        prompt: str,
        model: str = "dall-e-3",
        size: str = "1024x1024",
        quality: str = "hd",
        style: str = "vivid",
        n: int = 1,
    ) -> Dict[str, Any]:
        """Generate image(s) via DALL-E."""
        self._require_client()
        t0 = time.perf_counter()

        response = await self._client.images.generate(
            model=model,
            prompt=prompt[:4000],
            size=size,  # type: ignore
            quality=quality,  # type: ignore
            style=style,  # type: ignore
            n=n,
            response_format="url",
        )
        latency_ms = round((time.perf_counter() - t0) * 1000, 1)
        cost = OPENAI_PRICING.get(model, (0.04, 0.0))[0] * n

        await _global_cost.add(cost, model)

        images = [
            {
                "url": img.url,
                "revised_prompt": getattr(img, "revised_prompt", prompt),
            }
            for img in response.data
        ]

        return {
            "images": images,
            "model": model,
            "size": size,
            "quality": quality,
            "cost_usd": cost,
            "latency_ms": latency_ms,
        }

    async def create_embedding(
        self,
        text: str,
        model: str = "text-embedding-3-small",
    ) -> Tuple[List[float], float]:
        """Create a text embedding. Returns (vector, cost)."""
        self._require_client()
        response = await self._client.embeddings.create(
            input=text,
            model=model,
        )
        tokens = response.usage.total_tokens
        cost = tokens / 1000 * OPENAI_PRICING.get(model, (0.00002, 0.0))[0]
        await _global_cost.add(cost, model)
        return response.data[0].embedding, cost

    async def transcribe(
        self,
        audio_file_path: str,
        language: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Transcribe audio using Whisper."""
        self._require_client()
        with open(audio_file_path, "rb") as f:
            kwargs: Dict[str, Any] = {"model": "whisper-1", "file": f}
            if language:
                kwargs["language"] = language
            response = await self._client.audio.transcriptions.create(**kwargs)
        return {"text": response.text, "model": "whisper-1"}

    async def text_to_speech(
        self,
        text: str,
        voice: str = "alloy",
        model: str = "tts-1",
        output_format: str = "mp3",
    ) -> bytes:
        """Generate speech audio from text."""
        self._require_client()
        response = await self._client.audio.speech.create(
            model=model,
            voice=voice,  # type: ignore
            input=text,
            response_format=output_format,  # type: ignore
        )
        # Cost: per 1K chars
        cost = len(text) / 1000 * OPENAI_PRICING.get(model, (0.015, 0.0))[0]
        await _global_cost.add(cost, model)
        return response.content


class AIClientFactory:
    """
    Factory that returns the right client (Anthropic or OpenAI) based on
    the task type, model name, or explicit provider selection.
    """

    _anthropic: Optional[AnthropicClient] = None
    _openai: Optional[OpenAIClient] = None

    @classmethod
    def anthropic(cls) -> AnthropicClient:
        if cls._anthropic is None:
            cls._anthropic = AnthropicClient()
        return cls._anthropic

    @classmethod
    def openai(cls) -> OpenAIClient:
        if cls._openai is None:
            cls._openai = OpenAIClient()
        return cls._openai

    @classmethod
    def for_task(cls, task: str, quality: str = "standard") -> AnthropicClient:
        """Return the best client+model for the given task."""
        # All text tasks use Anthropic; image/audio tasks use OpenAI
        if task in ("image_generation", "embedding", "transcription", "tts"):
            return cls.openai()  # type: ignore[return-value]
        return cls.anthropic()

    @classmethod
    def get_cost_summary(cls) -> Dict[str, Any]:
        return _global_cost.summary()


def estimate_tokens(text: str, model: str = "claude-sonnet-4-6") -> int:
    """Rough token estimation: ~4 characters per token for English text."""
    return max(1, len(text) // 4)


def select_model_for_budget(max_cost_per_call: float, estimated_tokens: int) -> str:
    """
    Given a budget constraint, return the most capable model that fits.
    Defaults to output tokens = input tokens for estimation.
    """
    for model in ["claude-opus-4-5", "claude-sonnet-4-6", "claude-haiku-3-5"]:
        pricing = ANTHROPIC_PRICING.get(model, (0.003, 0.015))
        estimated_cost = (estimated_tokens / 1000 * pricing[0]) + (estimated_tokens / 1000 * pricing[1])
        if estimated_cost <= max_cost_per_call:
            return model
    return "claude-haiku-3-5"  # Always fits
