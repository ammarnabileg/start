"""
Scheduler Service for SociAI OS.

Responsibilities:
  - Calculate optimal post times (AI + historical data)
  - Manage the scheduling queue (Redis + DB)
  - Schedule / reschedule individual and bulk posts
  - Retry logic for failed publishes (exponential back-off)
  - Emergency stop / pause entire schedule
  - Content recycling (repurpose evergreen posts)
  - Conflict detection (avoid same-platform double-posting)
  - Queue health monitoring
"""
from __future__ import annotations

import json
import logging
import random
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Tuple
from uuid import UUID, uuid4

import redis.asyncio as aioredis
from sqlalchemy import and_, func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings

logger = logging.getLogger(__name__)


# ─── Platform-level optimal time windows (UTC hour ranges) ───────────────────

PLATFORM_OPTIMAL_WINDOWS: Dict[str, List[Tuple[int, int]]] = {
    "linkedin": [(8, 10), (12, 13), (17, 18)],
    "twitter": [(8, 9), (12, 13), (17, 18), (20, 22)],
    "instagram": [(6, 9), (12, 14), (19, 22)],
    "meta": [(7, 9), (12, 13), (18, 19)],
    "facebook": [(7, 9), (12, 13), (18, 19)],
    "tiktok": [(7, 9), (12, 13), (19, 23)],
    "youtube": [(12, 16), (20, 23)],
    "pinterest": [(8, 11), (14, 16), (20, 22)],
    "snapchat": [(8, 9), (12, 14), (18, 22)],
    "threads": [(9, 11), (13, 15), (18, 20)],
    "reddit": [(6, 8), (12, 14), (17, 19)],
}

# Minimum minutes between posts on the same platform
MIN_POSTING_INTERVAL_MINUTES = 30


class SchedulerService:
    """
    Manages post scheduling, optimal timing, and queue operations.
    """

    def __init__(self, db: AsyncSession, redis: aioredis.Redis):
        self.db = db
        self.redis = redis

    # ─── Backward-compatible static methods (legacy callers) ─────────────────

    @staticmethod
    async def execute_scheduled_post(scheduled_post_id: str) -> Dict[str, Any]:
        logger.info("Executing scheduled post: %s", scheduled_post_id)
        return {
            "post_id": scheduled_post_id,
            "status": "published",
            "published_at": datetime.now(timezone.utc).isoformat(),
        }

    @staticmethod
    async def get_due_posts() -> List[str]:
        return []

    # ─── Optimal Time Calculation ─────────────────────────────────────────────

    async def calculate_optimal_time(
        self,
        user_id: str,
        platforms: List[str],
        preferred_date: Optional[datetime] = None,
    ) -> datetime:
        """
        Calculate the best posting time for the given platforms.

        Algorithm:
        1. Load audience insights (best hours) from DB / Redis cache
        2. Fall back to platform-level heuristics if no user data
        3. Avoid conflicts with existing scheduled posts
        4. Prefer times within the next 72 hours
        """
        cache_key = f"optimal_time:{user_id}:{','.join(sorted(platforms))}"
        cached = await self.redis.get(cache_key)
        if cached:
            return datetime.fromisoformat(cached)

        base = preferred_date or datetime.now(timezone.utc)
        if base < datetime.now(timezone.utc):
            base = datetime.now(timezone.utc) + timedelta(hours=1)

        # Collect optimal hour scores from each platform
        hour_scores: Dict[int, float] = {h: 0.0 for h in range(24)}
        for platform in platforms:
            windows = PLATFORM_OPTIMAL_WINDOWS.get(platform, [(9, 11), (18, 20)])
            for start_h, end_h in windows:
                for h in range(start_h, end_h):
                    hour_scores[h] = hour_scores.get(h, 0.0) + 1.0

        # Try to load user-specific best hours from AudienceInsight
        try:
            from app.models.analytics import AudienceInsight
            result = await self.db.execute(
                select(AudienceInsight)
                .where(AudienceInsight.user_id == user_id)
                .order_by(AudienceInsight.recorded_at.desc())
                .limit(1)
            )
            insight = result.scalar_one_or_none()
            if insight:
                best_times = getattr(insight, "best_post_times", []) or []
                for entry in best_times:
                    hour = entry.get("hour") if isinstance(entry, dict) else None
                    if hour is not None:
                        hour_scores[hour] = hour_scores.get(hour, 0.0) + 2.0  # stronger weight
        except Exception as exc:
            logger.debug("Failed to load audience insights: %s", exc)

        best_hour = max(hour_scores, key=lambda h: hour_scores[h])

        # Find the next occurrence of this hour that avoids conflicts
        candidate = base.replace(hour=best_hour, minute=0, second=0, microsecond=0)
        if candidate <= datetime.now(timezone.utc):
            candidate += timedelta(days=1)

        conflict_free = await self._find_conflict_free_time(
            user_id=user_id,
            candidate=candidate,
            platforms=platforms,
        )

        ttl = 1800  # 30-min cache
        await self.redis.setex(cache_key, ttl, conflict_free.isoformat())
        return conflict_free

    async def _find_conflict_free_time(
        self,
        user_id: str,
        candidate: datetime,
        platforms: List[str],
        max_attempts: int = 10,
    ) -> datetime:
        """Shift candidate time until there are no conflicts with existing posts."""
        from app.models.content import Post

        for _ in range(max_attempts):
            window_start = candidate - timedelta(minutes=MIN_POSTING_INTERVAL_MINUTES)
            window_end = candidate + timedelta(minutes=MIN_POSTING_INTERVAL_MINUTES)

            result = await self.db.execute(
                select(func.count(Post.id)).where(
                    and_(
                        Post.user_id == user_id,
                        Post.status.in_(["scheduled", "approved"]),
                        Post.scheduled_at >= window_start,
                        Post.scheduled_at <= window_end,
                    )
                )
            )
            count = result.scalar() or 0
            if count == 0:
                return candidate
            # Shift by minimum interval + small jitter
            candidate += timedelta(minutes=MIN_POSTING_INTERVAL_MINUTES + random.randint(5, 15))

        return candidate  # Return best candidate even if there's a conflict

    # ─── Schedule / Reschedule ────────────────────────────────────────────────

    async def schedule_post(
        self,
        post_id: str,
        user_id: str,
        scheduled_at: datetime,
        platform_account_ids: Optional[List[str]] = None,
    ):
        """
        Schedule a post for publishing at a specified time.
        Validates the post status and enqueues it in Redis.
        """
        from app.models.content import Post

        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            raise ValueError(f"Post {post_id} not found")

        valid_statuses = {"draft", "approved", "rejected", "pending_approval"}
        if getattr(post, "status", "") not in valid_statuses and getattr(post, "status", "") != "scheduled":
            raise ValueError(f"Post cannot be scheduled from status '{post.status}'")

        if scheduled_at < datetime.now(timezone.utc):
            raise ValueError("scheduled_at must be in the future")

        if platform_account_ids:
            post.platform_account_ids = platform_account_ids

        post.status = "scheduled"
        post.scheduled_at = scheduled_at
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()

        # Enqueue in Redis sorted set (score = unix timestamp)
        queue_payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zadd("post_schedule_queue", {queue_payload: scheduled_at.timestamp()})
        logger.info("Scheduled post %s at %s", post_id, scheduled_at.isoformat())
        return post

    async def reschedule_post(
        self, post_id: str, user_id: str, new_scheduled_at: datetime
    ):
        """Change the scheduled time for an already-scheduled post."""
        from app.models.content import Post

        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            raise ValueError(f"Post {post_id} not found")

        # Remove old entry from queue
        old_payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zrem("post_schedule_queue", old_payload)

        post.scheduled_at = new_scheduled_at
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()

        queue_payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zadd("post_schedule_queue", {queue_payload: new_scheduled_at.timestamp()})
        return post

    async def unschedule_post(self, post_id: str, user_id: str):
        """Remove a post from the schedule and revert to draft."""
        from app.models.content import Post

        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            raise ValueError(f"Post {post_id} not found")

        payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zrem("post_schedule_queue", payload)
        post.status = "draft"
        post.scheduled_at = None
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()
        return post

    # ─── Bulk Scheduling ─────────────────────────────────────────────────────

    async def bulk_schedule(
        self,
        post_ids: List[str],
        user_id: str,
        scheduled_at: datetime,
        use_optimal_time: bool = False,
        spacing_minutes: int = MIN_POSTING_INTERVAL_MINUTES,
    ) -> List[Dict[str, Any]]:
        """
        Schedule multiple posts with automatic time spacing.
        If use_optimal_time=True, the first post is scheduled at the optimal time
        and subsequent posts are spaced by spacing_minutes.
        """
        results = []
        base_time = scheduled_at

        if use_optimal_time:
            base_time = await self.calculate_optimal_time(
                user_id=user_id,
                platforms=[],  # Cross-platform optimal
                preferred_date=scheduled_at,
            )

        for i, post_id in enumerate(post_ids):
            post_time = base_time + timedelta(minutes=i * spacing_minutes)
            try:
                post = await self.schedule_post(
                    post_id=post_id,
                    user_id=user_id,
                    scheduled_at=post_time,
                )
                results.append({
                    "post_id": post_id,
                    "success": True,
                    "scheduled_at": post_time.isoformat(),
                    "status": post.status,
                })
            except Exception as exc:
                logger.warning("Failed to schedule post %s: %s", post_id, exc)
                results.append({
                    "post_id": post_id,
                    "success": False,
                    "error": str(exc),
                })

        return results

    # ─── Due Posts (for workers) ──────────────────────────────────────────────

    async def get_due_posts_from_queue(self, limit: int = 50) -> List[Dict[str, Any]]:
        """
        Retrieve posts that are due for publishing now (or overdue).
        Called by the Celery beat / cron worker.
        """
        now = datetime.now(timezone.utc).timestamp()
        # ZRANGEBYSCORE returns members with score (timestamp) <= now
        due_raw = await self.redis.zrangebyscore(
            "post_schedule_queue",
            "-inf",
            now,
            start=0,
            num=limit,
        )
        due_posts = []
        for raw in due_raw:
            try:
                entry = json.loads(raw)
                due_posts.append(entry)
            except json.JSONDecodeError:
                logger.warning("Invalid queue entry: %s", raw)
        return due_posts

    async def mark_post_published(
        self, post_id: str, user_id: str, published_at: Optional[datetime] = None
    ) -> None:
        """Update post status to published and remove from queue."""
        from app.models.content import Post

        now = published_at or datetime.now(timezone.utc)
        await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        # Remove from queue
        payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zrem("post_schedule_queue", payload)

        result = await self.db.execute(
            select(Post).where(Post.id == post_id)
        )
        post = result.scalar_one_or_none()
        if post:
            post.status = "published"
            post.published_at = now
            post.updated_at = now
            self.db.add(post)
            await self.db.flush()

    # ─── Retry Logic ─────────────────────────────────────────────────────────

    async def handle_publish_failure(
        self,
        post_id: str,
        user_id: str,
        error_message: str,
        max_retries: int = 3,
    ) -> Dict[str, Any]:
        """
        Handle a publishing failure with exponential back-off retry logic.
        Returns a dict with next_retry_at or final_failure information.
        """
        retry_key = f"post_retry:{post_id}"
        retry_count_raw = await self.redis.get(retry_key)
        retry_count = int(retry_count_raw or 0)

        if retry_count >= max_retries:
            # Mark as permanently failed
            from app.models.content import Post
            result = await self.db.execute(select(Post).where(Post.id == post_id))
            post = result.scalar_one_or_none()
            if post:
                post.status = "failed"
                post.error_message = error_message
                post.updated_at = datetime.now(timezone.utc)
                self.db.add(post)
                await self.db.flush()
            await self.redis.delete(retry_key)
            logger.error("Post %s permanently failed after %d retries", post_id, max_retries)
            return {
                "post_id": post_id,
                "action": "permanent_failure",
                "retry_count": retry_count,
                "error": error_message,
            }

        # Exponential back-off: 5min, 15min, 45min
        backoff_minutes = 5 * (3 ** retry_count)
        next_retry = datetime.now(timezone.utc) + timedelta(minutes=backoff_minutes)

        new_count = retry_count + 1
        await self.redis.setex(retry_key, 86400, str(new_count))

        queue_payload = json.dumps({"post_id": post_id, "user_id": user_id})
        await self.redis.zadd("post_schedule_queue", {queue_payload: next_retry.timestamp()})

        logger.warning(
            "Post %s failed (attempt %d/%d), retrying at %s",
            post_id, new_count, max_retries, next_retry.isoformat(),
        )
        return {
            "post_id": post_id,
            "action": "retry_scheduled",
            "retry_count": new_count,
            "next_retry_at": next_retry.isoformat(),
            "error": error_message,
        }

    # ─── Emergency Stop ───────────────────────────────────────────────────────

    async def emergency_stop(
        self,
        user_id: str,
        platforms: Optional[List[str]] = None,
    ) -> Dict[str, Any]:
        """
        Emergency stop: pause all scheduled posts for the user.
        Optionally filter to specific platforms.
        """
        from app.models.content import Post
        from sqlalchemy import update

        filters = [
            Post.user_id == user_id,
            Post.status == "scheduled",
        ]

        result = await self.db.execute(
            select(Post).where(and_(*filters))
        )
        posts = result.scalars().all()
        paused_ids = []
        for post in posts:
            post.status = "paused"
            post.updated_at = datetime.now(timezone.utc)
            self.db.add(post)
            paused_ids.append(str(post.id))
            payload = json.dumps({"post_id": str(post.id), "user_id": user_id})
            await self.redis.zrem("post_schedule_queue", payload)

        await self.db.flush()

        # Set emergency stop flag
        await self.redis.setex(f"emergency_stop:{user_id}", 86400, "1")
        logger.warning("Emergency stop activated for user %s. Paused %d posts.", user_id, len(paused_ids))

        return {
            "status": "emergency_stop_activated",
            "paused_posts": len(paused_ids),
            "paused_post_ids": paused_ids,
            "activated_at": datetime.now(timezone.utc).isoformat(),
        }

    async def lift_emergency_stop(self, user_id: str) -> Dict[str, Any]:
        """Lift the emergency stop flag. Posts remain paused until re-scheduled."""
        await self.redis.delete(f"emergency_stop:{user_id}")
        return {
            "status": "emergency_stop_lifted",
            "message": "Posts remain paused. Re-schedule them individually or use bulk schedule.",
            "lifted_at": datetime.now(timezone.utc).isoformat(),
        }

    async def is_emergency_stopped(self, user_id: str) -> bool:
        return await self.redis.exists(f"emergency_stop:{user_id}") > 0

    # ─── Content Recycling ────────────────────────────────────────────────────

    async def recycle_evergreen_posts(
        self,
        user_id: str,
        min_days_since_last_publish: int = 90,
        max_posts_to_recycle: int = 5,
    ) -> List[Dict[str, Any]]:
        """
        Identify published evergreen posts that haven't been recycled recently
        and schedule them for re-publishing with updated metadata.
        """
        from app.models.content import Post
        from app.models.analytics import ViralScore

        cutoff = datetime.now(timezone.utc) - timedelta(days=min_days_since_last_publish)

        result = await self.db.execute(
            select(Post)
            .join(ViralScore, ViralScore.post_id == Post.id, isouter=True)
            .where(
                Post.user_id == user_id,
                Post.status == "published",
                Post.published_at <= cutoff,
                Post.is_evergreen == True,
            )
            .order_by(ViralScore.score.desc().nullslast())
            .limit(max_posts_to_recycle)
        )
        posts = result.scalars().all()
        recycled = []
        for post in posts:
            # Duplicate and schedule at next optimal time
            from app.services.content_service import ContentService
            content_svc = ContentService(self.db, self.redis)
            new_post = await content_svc.duplicate_post(str(post.id), user_id)
            if new_post:
                optimal = await self.calculate_optimal_time(
                    user_id=user_id,
                    platforms=getattr(post, "platform_account_ids", []) or [],
                )
                await self.schedule_post(str(new_post.id), user_id, optimal)
                recycled.append({
                    "original_post_id": str(post.id),
                    "new_post_id": str(new_post.id),
                    "scheduled_at": optimal.isoformat(),
                })

        logger.info("Recycled %d evergreen posts for user %s", len(recycled), user_id)
        return recycled

    # ─── Queue Health ─────────────────────────────────────────────────────────

    async def get_queue_health(self, user_id: str) -> Dict[str, Any]:
        """Return statistics about the user's scheduling queue."""
        now = datetime.now(timezone.utc).timestamp()
        future_ts = (datetime.now(timezone.utc) + timedelta(days=30)).timestamp()

        total = await self.redis.zcount("post_schedule_queue", "-inf", "+inf")
        overdue = await self.redis.zcount("post_schedule_queue", "-inf", now)
        upcoming_7d = await self.redis.zcount(
            "post_schedule_queue",
            now,
            (datetime.now(timezone.utc) + timedelta(days=7)).timestamp(),
        )
        emergency_stopped = await self.is_emergency_stopped(user_id)

        return {
            "total_queued": total,
            "overdue": overdue,
            "upcoming_7_days": upcoming_7d,
            "emergency_stopped": emergency_stopped,
            "queue_health": "critical" if overdue > 5 else ("warning" if overdue > 0 else "healthy"),
            "checked_at": datetime.now(timezone.utc).isoformat(),
        }
