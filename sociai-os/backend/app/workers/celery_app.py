"""Celery application with Redis broker and periodic task scheduling."""
from celery import Celery
from celery.schedules import crontab
from app.core.config import settings

celery_app = Celery(
    "sociai_os",
    broker=settings.CELERY_BROKER_URL,
    backend=settings.CELERY_RESULT_BACKEND,
    include=["app.workers.tasks"],
)

celery_app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="UTC",
    enable_utc=True,
    task_track_started=True,
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    task_routes={
        "app.workers.tasks.publish_post": {"queue": "publishing"},
        "app.workers.tasks.run_analytics_snapshot": {"queue": "analytics"},
        "app.workers.tasks.scan_trends": {"queue": "default"},
        "app.workers.tasks.process_community_batch": {"queue": "default"},
        "app.workers.tasks.generate_content_batch": {"queue": "high_priority"},
    },
    beat_schedule={
        "hourly-trend-scan": {
            "task": "app.workers.tasks.scan_trends",
            "schedule": crontab(minute=0),
            "args": (["tiktok", "instagram", "linkedin"],),
        },
        "daily-analytics-snapshot": {
            "task": "app.workers.tasks.run_analytics_snapshot",
            "schedule": crontab(hour=0, minute=30),
        },
        "check-scheduled-posts": {
            "task": "app.workers.tasks.check_and_publish_scheduled_posts",
            "schedule": 60.0,  # Every minute
        },
        "community-management-sweep": {
            "task": "app.workers.tasks.community_management_sweep",
            "schedule": crontab(minute="*/15"),
        },
        "viral-prediction-refresh": {
            "task": "app.workers.tasks.refresh_viral_predictions",
            "schedule": crontab(hour="*/6"),
        },
    },
)
