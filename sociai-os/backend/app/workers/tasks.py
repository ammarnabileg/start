"""Celery tasks for async background processing."""
from __future__ import annotations
import asyncio
import logging
from datetime import datetime
from typing import Any, Optional
from celery import shared_task
from app.workers.celery_app import celery_app

logger = logging.getLogger(__name__)


def run_async(coro):
    """Run async coroutine in Celery task context."""
    loop = asyncio.new_event_loop()
    try:
        return loop.run_until_complete(coro)
    finally:
        loop.close()


@celery_app.task(bind=True, max_retries=3, default_retry_delay=60, queue="publishing")
def publish_post(self, scheduled_post_id: str):
    """Publish a scheduled post to its target platform."""
    from app.services.scheduler_service import SchedulerService
    try:
        result = run_async(SchedulerService.execute_scheduled_post(scheduled_post_id))
        logger.info(f"Post {scheduled_post_id} published successfully: {result}")
        return result
    except Exception as exc:
        logger.exception(f"Failed to publish post {scheduled_post_id}")
        raise self.retry(exc=exc, countdown=2 ** self.request.retries * 60)


@celery_app.task(bind=True, queue="publishing")
def check_and_publish_scheduled_posts(self):
    """Check for due scheduled posts and dispatch publish tasks."""
    from app.services.scheduler_service import SchedulerService
    try:
        due_posts = run_async(SchedulerService.get_due_posts())
        for post_id in due_posts:
            publish_post.apply_async(args=[post_id], queue="publishing")
        logger.info(f"Dispatched {len(due_posts)} scheduled posts")
        return {"dispatched": len(due_posts)}
    except Exception as exc:
        logger.exception("Failed to check scheduled posts")
        raise


@celery_app.task(bind=True, queue="analytics")
def run_analytics_snapshot(self, brand_id: Optional[str] = None):
    """Generate daily analytics snapshots for all active brands."""
    from app.services.analytics_service import AnalyticsService
    try:
        result = run_async(AnalyticsService.create_daily_snapshots(brand_id=brand_id))
        return result
    except Exception as exc:
        logger.exception("Analytics snapshot failed")
        raise self.retry(exc=exc, countdown=300)


@celery_app.task(bind=True, queue="default")
def scan_trends(self, platforms: list[str]):
    """Scan trends across platforms and store opportunities."""
    try:
        from app.agents.research_agent import ResearchAgent
        agent = ResearchAgent(brand_id="global", redis_url=__import__("app.core.config", fromlist=["settings"]).settings.REDIS_URL)
        result = run_async(agent.scan_all_trends(niche="general", platforms=platforms))
        logger.info(f"Trend scan completed: {len(platforms)} platforms")
        return result
    except Exception as exc:
        logger.exception("Trend scan failed")
        raise self.retry(exc=exc, countdown=300)


@celery_app.task(bind=True, queue="default")
def process_community_batch(self, brand_id: str, interaction_ids: list[str]):
    """Process a batch of community interactions through CommunityAgent."""
    from app.services.community_service import CommunityService
    try:
        result = run_async(CommunityService.process_batch(brand_id, interaction_ids))
        return result
    except Exception as exc:
        logger.exception(f"Community batch failed for brand {brand_id}")
        raise self.retry(exc=exc, countdown=60)


@celery_app.task(bind=True, queue="default")
def community_management_sweep(self):
    """Periodic sweep of pending community interactions."""
    from app.services.community_service import CommunityService
    try:
        result = run_async(CommunityService.sweep_pending_interactions())
        return result
    except Exception as exc:
        logger.exception("Community sweep failed")


@celery_app.task(bind=True, queue="high_priority")
def generate_content_batch(self, brand_id: str, briefs: list[dict]):
    """Generate a batch of content pieces via AI agents."""
    from app.agents.orchestrator import AgentOrchestrator
    from app.core.config import settings
    try:
        orchestrator = AgentOrchestrator(brand_id=brand_id, redis_url=settings.REDIS_URL)
        results = []
        for brief in briefs:
            result = run_async(orchestrator.run_content_creation_workflow(brief))
            results.append(result)
        return {"generated": len(results), "results": results}
    except Exception as exc:
        logger.exception(f"Content batch generation failed for brand {brand_id}")
        raise self.retry(exc=exc, countdown=120)


@celery_app.task(bind=True, queue="analytics")
def refresh_viral_predictions(self):
    """Recalculate viral predictions for recently published content."""
    from app.services.analytics_service import AnalyticsService
    try:
        result = run_async(AnalyticsService.refresh_viral_predictions())
        return result
    except Exception as exc:
        logger.exception("Viral prediction refresh failed")


@celery_app.task(bind=True, queue="high_priority")
def run_strategy_workflow(self, brand_id: str, document_path: str):
    """Run the full strategy analysis workflow for a brand."""
    from app.agents.orchestrator import AgentOrchestrator
    from app.core.config import settings
    try:
        orchestrator = AgentOrchestrator(brand_id=brand_id, redis_url=settings.REDIS_URL)
        result = run_async(orchestrator.run_full_strategy_workflow(document_path))
        return result
    except Exception as exc:
        logger.exception(f"Strategy workflow failed for brand {brand_id}")
        raise self.retry(exc=exc, countdown=120)
