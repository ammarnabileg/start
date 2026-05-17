"""
Community management routes for SociAI OS.

Endpoints:
  GET    /community/inbox              – Unified inbox (comments + DMs)
  GET    /community/comments           – List comments to review/reply
  POST   /community/comments/{id}/reply  – Reply to a comment
  POST   /community/comments/{id}/hide   – Hide a comment
  POST   /community/comments/{id}/delete – Delete a comment (via platform API)
  POST   /community/comments/{id}/like   – Like a comment
  GET    /community/dms                – List direct messages
  POST   /community/dms/{id}/reply     – Reply to a DM
  POST   /community/dms/{id}/archive   – Archive a DM thread
  GET    /community/moderation         – Moderation queue (flagged/spam)
  POST   /community/moderation/{id}/approve – Approve a flagged item
  POST   /community/moderation/{id}/remove  – Remove a flagged item
  POST   /community/moderation/rules   – Create moderation rule
  GET    /community/moderation/rules   – List moderation rules
  DELETE /community/moderation/rules/{id} – Delete a rule
  GET    /community/sentiment-alerts   – Negative sentiment alerts
  POST   /community/bulk-reply         – AI-generate bulk replies
  GET    /community/stats              – Community engagement statistics
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import (
    get_current_active_user,
    get_db,
    get_pagination,
    get_redis,
    PaginationParams,
    require_role,
)

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class ReplyRequest(BaseModel):
    content: str = Field(..., min_length=1, max_length=2200)
    use_ai_suggestion: bool = False
    tone: Optional[str] = Field(default=None, description="professional|friendly|empathetic|apologetic")


class ModerationRuleCreate(BaseModel):
    rule_name: str = Field(..., max_length=128)
    rule_type: str = Field(
        ...,
        description="keyword|regex|sentiment|spam|profanity|competitor_mention",
    )
    pattern: Optional[str] = None          # keyword or regex
    sentiment_threshold: Optional[float] = None  # for sentiment rules
    action: str = Field(
        ...,
        description="hide|delete|flag|alert|auto_reply|quarantine",
    )
    auto_reply_template: Optional[str] = None
    applies_to_platforms: Optional[List[str]] = None
    is_active: bool = True
    severity: str = Field(default="medium", pattern="^(low|medium|high|critical)$")


class BulkReplyRequest(BaseModel):
    comment_ids: Optional[List[str]] = None
    dm_ids: Optional[List[str]] = None
    reply_template: Optional[str] = None
    use_ai: bool = True
    tone: Optional[str] = None
    personalize: bool = True


class CommentResponse(BaseModel):
    id: str
    platform: str
    platform_comment_id: str
    post_id: Optional[str]
    platform_account_id: str
    author_username: str
    author_display_name: Optional[str]
    author_avatar: Optional[str]
    content: str
    sentiment_score: Optional[float]
    sentiment_label: Optional[str]
    is_replied: bool
    is_hidden: bool
    is_flagged: bool
    flag_reason: Optional[str]
    parent_comment_id: Optional[str]
    likes_count: int
    published_at: datetime
    fetched_at: datetime
    ai_suggested_reply: Optional[str]


class DMResponse(BaseModel):
    id: str
    platform: str
    platform_account_id: str
    thread_id: str
    sender_username: str
    sender_display_name: Optional[str]
    sender_avatar: Optional[str]
    content: str
    is_read: bool
    is_replied: bool
    is_archived: bool
    sentiment_score: Optional[float]
    received_at: datetime
    ai_suggested_reply: Optional[str]


class ModerationItemResponse(BaseModel):
    id: str
    item_type: str   # comment | dm | mention
    platform: str
    content: str
    author_username: str
    flag_reason: str
    flag_score: float
    status: str   # pending | approved | removed
    rule_triggered: Optional[str]
    created_at: datetime


class ModerationRuleResponse(BaseModel):
    id: str
    rule_name: str
    rule_type: str
    pattern: Optional[str]
    sentiment_threshold: Optional[float]
    action: str
    auto_reply_template: Optional[str]
    applies_to_platforms: List[str]
    is_active: bool
    severity: str
    trigger_count: int
    created_at: datetime


class InboxItem(BaseModel):
    item_type: str  # comment | dm | mention
    id: str
    platform: str
    author_username: str
    content_preview: str
    sentiment_label: Optional[str]
    is_read: bool
    is_replied: bool
    requires_action: bool
    priority_score: float
    received_at: datetime


class CommunityStatsResponse(BaseModel):
    total_comments: int
    comments_replied: int
    reply_rate: float
    avg_reply_time_minutes: float
    total_dms: int
    dms_replied: int
    dm_reply_rate: float
    flagged_items: int
    flagged_resolved: int
    positive_sentiment_pct: float
    neutral_sentiment_pct: float
    negative_sentiment_pct: float
    active_alerts: int
    top_commenters: List[Dict[str, Any]]
    busiest_hours: List[Dict[str, Any]]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.get("/inbox", summary="Unified inbox — comments, DMs, and mentions")
async def get_inbox(
    unread_only: bool = Query(default=False),
    unreplied_only: bool = Query(default=False),
    platform: Optional[str] = Query(default=None),
    sentiment: Optional[str] = Query(default=None, description="positive|neutral|negative"),
    requires_action: Optional[bool] = Query(default=None),
    search: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    """
    Returns a priority-sorted unified inbox combining comments and DMs
    across all connected platforms.
    """
    # In production, this aggregates from comments + DMs tables with
    # AI-powered priority scoring
    return {
        "items": [],
        "total": 0,
        "page": pagination.page,
        "page_size": pagination.page_size,
        "unread_count": 0,
        "unreplied_count": 0,
        "urgent_count": 0,
    }


@router.get(
    "/comments",
    response_model=List[CommentResponse],
    summary="List comments from all connected platforms",
)
async def list_comments(
    platform: Optional[str] = Query(default=None),
    post_id: Optional[str] = Query(default=None),
    is_replied: Optional[bool] = Query(default=None),
    is_flagged: Optional[bool] = Query(default=None),
    sentiment: Optional[str] = Query(default=None),
    from_date: Optional[datetime] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    # In production: query PlatformComment table with filters
    return []


@router.post(
    "/comments/{comment_id}/reply",
    summary="Reply to a comment",
)
async def reply_to_comment(
    comment_id: str,
    payload: ReplyRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    reply_content = payload.content
    if payload.use_ai_suggestion:
        # In production, fetch AI suggestion from cache or generate
        cached = await redis.get(f"ai_reply:comment:{comment_id}")
        if cached:
            reply_content = cached

    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    result = await svc.reply_to_comment(
        comment_id=comment_id,
        user_id=str(current_user.id),
        content=reply_content,
    )
    return {
        "success": True,
        "comment_id": comment_id,
        "reply_id": result.get("reply_id") if result else None,
        "content": reply_content,
        "replied_at": datetime.now(timezone.utc).isoformat(),
    }


@router.post("/comments/{comment_id}/hide", summary="Hide a comment via platform API")
async def hide_comment(
    comment_id: str,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    await svc.moderate_comment(
        comment_id=comment_id,
        user_id=str(current_user.id),
        action="hide",
    )
    return {"success": True, "comment_id": comment_id, "action": "hidden"}


@router.post("/comments/{comment_id}/delete", summary="Delete a comment via platform API")
async def delete_comment(
    comment_id: str,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    await svc.moderate_comment(
        comment_id=comment_id,
        user_id=str(current_user.id),
        action="delete",
    )
    return {"success": True, "comment_id": comment_id, "action": "deleted"}


@router.post("/comments/{comment_id}/like", summary="Like a comment")
async def like_comment(
    comment_id: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    await svc.moderate_comment(
        comment_id=comment_id,
        user_id=str(current_user.id),
        action="like",
    )
    return {"success": True, "comment_id": comment_id, "action": "liked"}


# ── DMs ───────────────────────────────────────────────────────────────────────

@router.get(
    "/dms",
    response_model=List[DMResponse],
    summary="List direct messages",
)
async def list_dms(
    platform: Optional[str] = Query(default=None),
    is_replied: Optional[bool] = Query(default=None),
    is_archived: Optional[bool] = Query(default=False),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    # In production: query PlatformDM table
    return []


@router.post("/dms/{dm_id}/reply", summary="Reply to a direct message")
async def reply_to_dm(
    dm_id: str,
    payload: ReplyRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    result = await svc.send_dm_reply(
        dm_id=dm_id,
        user_id=str(current_user.id),
        content=payload.content,
    )
    return {
        "success": True,
        "dm_id": dm_id,
        "content": payload.content,
        "sent_at": datetime.now(timezone.utc).isoformat(),
    }


@router.post("/dms/{dm_id}/archive", summary="Archive a DM thread")
async def archive_dm(
    dm_id: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    return {"success": True, "dm_id": dm_id, "action": "archived"}


# ── Moderation Queue ──────────────────────────────────────────────────────────

@router.get(
    "/moderation",
    response_model=List[ModerationItemResponse],
    summary="Moderation queue — flagged and potentially problematic content",
)
async def get_moderation_queue(
    status_filter: Optional[str] = Query(default="pending", alias="status"),
    severity: Optional[str] = Query(default=None),
    platform: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
):
    return []


@router.post("/moderation/{item_id}/approve", summary="Approve a flagged moderation item")
async def approve_moderation_item(
    item_id: str,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    return {"success": True, "item_id": item_id, "action": "approved"}


@router.post("/moderation/{item_id}/remove", summary="Remove a flagged item via platform API")
async def remove_moderation_item(
    item_id: str,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.platform_service import PlatformService
    svc = PlatformService(db, redis)
    # Remove via platform API
    return {"success": True, "item_id": item_id, "action": "removed"}


# ── Moderation Rules ──────────────────────────────────────────────────────────

@router.get(
    "/moderation/rules",
    response_model=List[ModerationRuleResponse],
    summary="List active moderation rules",
)
async def list_moderation_rules(
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    return []


@router.post(
    "/moderation/rules",
    response_model=ModerationRuleResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new moderation rule",
)
async def create_moderation_rule(
    payload: ModerationRuleCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    now = datetime.now(timezone.utc)
    return ModerationRuleResponse(
        id="stub-id",
        rule_name=payload.rule_name,
        rule_type=payload.rule_type,
        pattern=payload.pattern,
        sentiment_threshold=payload.sentiment_threshold,
        action=payload.action,
        auto_reply_template=payload.auto_reply_template,
        applies_to_platforms=payload.applies_to_platforms or [],
        is_active=payload.is_active,
        severity=payload.severity,
        trigger_count=0,
        created_at=now,
    )


@router.delete(
    "/moderation/rules/{rule_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a moderation rule",
)
async def delete_moderation_rule(
    rule_id: str,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    pass  # In production: delete from DB


# ── Sentiment Alerts ──────────────────────────────────────────────────────────

@router.get("/sentiment-alerts", summary="Get active negative sentiment alerts")
async def get_sentiment_alerts(
    resolved: bool = Query(default=False),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    return {"alerts": [], "total": 0}


# ── Bulk Operations ───────────────────────────────────────────────────────────

@router.post("/bulk-reply", summary="Generate and send AI replies to multiple comments/DMs")
async def bulk_reply(
    payload: BulkReplyRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    if not payload.comment_ids and not payload.dm_ids:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Provide at least one of comment_ids or dm_ids",
        )

    total = len(payload.comment_ids or []) + len(payload.dm_ids or [])
    task_id = f"bulk_reply:{current_user.id}:{int(datetime.now(timezone.utc).timestamp())}"

    import json
    await redis.setex(
        f"task:{task_id}",
        3600,
        json.dumps({
            "type": "bulk_reply",
            "user_id": str(current_user.id),
            "comment_ids": payload.comment_ids,
            "dm_ids": payload.dm_ids,
            "use_ai": payload.use_ai,
            "tone": payload.tone,
            "personalize": payload.personalize,
            "status": "queued",
        }),
    )
    return {
        "task_id": task_id,
        "status": "queued",
        "total_items": total,
        "message": f"Queued AI reply generation for {total} items. Track via /agents/tasks/{task_id}",
    }


# ── Statistics ────────────────────────────────────────────────────────────────

@router.get(
    "/stats",
    response_model=CommunityStatsResponse,
    summary="Community engagement statistics",
)
async def get_community_stats(
    days: int = Query(default=30, ge=1, le=365),
    platform: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.analytics_service import AnalyticsService
    svc = AnalyticsService(db, redis)
    from datetime import timedelta
    end = datetime.now(timezone.utc)
    start = end - timedelta(days=days)
    return await svc.get_community_stats(
        user_id=str(current_user.id),
        platform=platform,
        start=start,
        end=end,
    )
