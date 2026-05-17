"""PublishingAgent – schedules, publishes, A/B tests, and recycles social content."""
from __future__ import annotations

import logging
from datetime import datetime, timedelta
from typing import Any, Optional
from zoneinfo import ZoneInfo

from app.agents.base_agent import BaseAgent, AgentResult

logger = logging.getLogger(__name__)

# Best posting times per platform (UTC hour, platform)
OPTIMAL_HOURS: dict[str, list[int]] = {
    "linkedin":  [8, 9, 12, 17, 18],
    "instagram": [8, 11, 14, 17, 20],
    "facebook":  [9, 13, 15, 19],
    "tiktok":    [6, 10, 14, 19, 21],
    "twitter":   [8, 10, 12, 15, 18],
    "youtube":   [14, 15, 16, 20],
    "snapchat":  [8, 10, 20, 22],
    "threads":   [9, 12, 18, 21],
    "pinterest": [8, 11, 20, 21],
    "whatsapp":  [8, 12, 18, 20],
    "telegram":  [9, 12, 18, 21],
}

class PublishingAgent(BaseAgent):
    agent_type = "publishing"

    async def execute(self, task: str, **kwargs) -> AgentResult:
        start = self._start_timer()
        try:
            result = await getattr(self, task)(**kwargs)
            return self._make_result(True, result, task, self._elapsed_ms(start))
        except Exception as e:
            logger.exception(f"PublishingAgent.{task} failed")
            return self._make_result(False, None, task, self._elapsed_ms(start), error=str(e))

    async def optimize_posting_time(
        self,
        platform: str,
        audience_timezone: str = "UTC",
        target_date: Optional[datetime] = None,
    ) -> dict[str, Any]:
        tz = ZoneInfo(audience_timezone)
        base = target_date or datetime.now(tz).replace(tzinfo=None)
        optimal = OPTIMAL_HOURS.get(platform.lower(), [12, 18])
        candidates = []
        for h in optimal:
            candidate = base.replace(hour=h, minute=0, second=0, microsecond=0)
            if candidate < datetime.now():
                candidate += timedelta(days=1)
            candidates.append(candidate)
        best = min(candidates, key=lambda t: abs(t.hour - 12))
        return {
            "platform": platform,
            "recommended_time": best.isoformat(),
            "all_options": [c.isoformat() for c in candidates],
            "audience_timezone": audience_timezone,
        }

    async def schedule_post(
        self,
        content_id: str,
        platform_account_ids: list[str],
        scheduled_at: datetime,
        ab_test: bool = False,
        content_b_id: Optional[str] = None,
    ) -> dict[str, Any]:
        schedules = []
        for account_id in platform_account_ids:
            entry = {
                "content_id": content_id,
                "platform_account_id": account_id,
                "scheduled_at": scheduled_at.isoformat(),
                "status": "scheduled",
                "ab_test": ab_test,
            }
            if ab_test and content_b_id:
                entry["ab_test_group"] = "A"
                entry["content_b_id"] = content_b_id
            schedules.append(entry)
        await self.remember(f"schedule:{content_id}", schedules, ttl=86400 * 7)
        return {"scheduled": len(schedules), "entries": schedules}

    async def cross_post(
        self,
        content: dict[str, Any],
        source_platform: str,
        target_platforms: list[str],
        adapt_per_platform: bool = True,
    ) -> dict[str, Any]:
        adaptations: dict[str, Any] = {}
        for platform in target_platforms:
            if adapt_per_platform:
                adapted = await self._adapt_content_for_platform(content, platform)
            else:
                adapted = content.copy()
            adaptations[platform] = adapted
        return {"source": source_platform, "cross_posted_to": adaptations}

    async def recycle_top_performing(
        self,
        brand_id: str,
        threshold_viral_score: float = 7.0,
        lookback_days: int = 90,
    ) -> dict[str, Any]:
        cutoff = datetime.utcnow() - timedelta(days=lookback_days)
        recycled = await self.recall(f"top_posts:{brand_id}") or []
        eligible = [p for p in recycled if p.get("viral_score", 0) >= threshold_viral_score]
        return {
            "eligible_count": len(eligible),
            "threshold": threshold_viral_score,
            "lookback_days": lookback_days,
            "posts": eligible[:10],
        }

    async def emergency_stop(self, brand_id: str, reason: str = "manual") -> dict[str, Any]:
        await self.remember(f"emergency_stop:{brand_id}", {"active": True, "reason": reason, "at": datetime.utcnow().isoformat()}, ttl=3600)
        logger.critical(f"EMERGENCY STOP activated for brand {brand_id}: {reason}")
        return {"status": "stopped", "brand_id": brand_id, "reason": reason}

    async def release_emergency_stop(self, brand_id: str) -> dict[str, Any]:
        await self.forget(f"emergency_stop:{brand_id}")
        return {"status": "released", "brand_id": brand_id}

    async def ab_test_result(self, post_a_id: str, post_b_id: str) -> dict[str, Any]:
        a_metrics = await self.recall(f"metrics:{post_a_id}") or {}
        b_metrics = await self.recall(f"metrics:{post_b_id}") or {}
        winner = "A" if a_metrics.get("engagement_rate", 0) >= b_metrics.get("engagement_rate", 0) else "B"
        return {"winner": winner, "a_metrics": a_metrics, "b_metrics": b_metrics}

    async def _adapt_content_for_platform(self, content: dict, platform: str) -> dict:
        limits = {"twitter": 280, "linkedin": 3000, "instagram": 2200, "tiktok": 2200, "threads": 500}
        char_limit = limits.get(platform, 2000)
        adapted = content.copy()
        if len(adapted.get("body_text", "")) > char_limit:
            adapted["body_text"] = adapted["body_text"][:char_limit - 3] + "..."
        adapted["platform"] = platform
        return adapted
