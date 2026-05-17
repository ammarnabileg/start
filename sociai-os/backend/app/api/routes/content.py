"""
Content management routes for SociAI OS.

Endpoints:
  POST   /content/generate              – AI-generate content for platforms
  GET    /content/posts                 – List posts (filterable)
  POST   /content/posts                 – Create a post manually
  GET    /content/posts/{id}            – Get post detail
  PATCH  /content/posts/{id}            – Update post
  DELETE /content/posts/{id}            – Delete post
  POST   /content/posts/{id}/schedule   – Schedule a post
  POST   /content/posts/{id}/approve    – Approve a post
  POST   /content/posts/{id}/reject     – Reject a post
  POST   /content/posts/{id}/publish    – Publish immediately
  POST   /content/posts/{id}/duplicate  – Duplicate a post
  GET    /content/calendar              – Content calendar view
  GET    /content/calendar/export       – Export calendar (iCal / JSON)
  POST   /content/bulk/schedule         – Bulk schedule posts
  POST   /content/bulk/approve          – Bulk approve posts
  DELETE /content/bulk                  – Bulk delete posts
  GET    /content/posts/{id}/analytics  – Post-level analytics
  POST   /content/ab-test               – Set up A/B test
  GET    /content/media                 – List media assets
  POST   /content/media/upload          – Upload media asset
  DELETE /content/media/{id}            – Delete media asset
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import (
    APIRouter, BackgroundTasks, Depends, File, Form, HTTPException,
    Query, UploadFile, status,
)
from pydantic import BaseModel, Field
from sqlalchemy import select, update
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
from app.services.content_service import ContentService

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class GenerateContentRequest(BaseModel):
    topic: str = Field(..., min_length=3, max_length=1000)
    content_type: str = Field(default="post", description="post|carousel|thread|story|reel|video_script")
    platforms: List[str] = Field(..., min_length=1, description="Target platform slugs")
    tone: Optional[str] = Field(default=None, description="Override tone: professional|casual|humorous|inspirational")
    content_pillar_id: Optional[str] = None
    include_hashtags: bool = True
    include_emojis: bool = True
    include_cta: bool = True
    target_audience: Optional[str] = None
    max_length: Optional[int] = None
    reference_urls: Optional[List[str]] = None
    style_examples: Optional[List[str]] = None
    language: str = "en"
    variations_count: int = Field(default=1, ge=1, le=5)


class PostCreate(BaseModel):
    title: Optional[str] = Field(default=None, max_length=256)
    content: str = Field(..., min_length=1)
    platform_account_ids: List[str] = Field(..., min_length=1)
    scheduled_at: Optional[datetime] = None
    content_pillar_id: Optional[str] = None
    campaign_id: Optional[str] = None
    media_asset_ids: Optional[List[str]] = None
    hashtags: Optional[List[str]] = None
    mention_handles: Optional[List[str]] = None
    location: Optional[str] = None
    link_url: Optional[str] = None
    platform_specific: Optional[Dict[str, Any]] = Field(
        default=None,
        description="Per-platform overrides e.g. {'twitter': {'thread_tweets': ['...']}}"
    )
    labels: Optional[List[str]] = None
    requires_approval: bool = False


class PostUpdate(BaseModel):
    title: Optional[str] = None
    content: Optional[str] = None
    scheduled_at: Optional[datetime] = None
    hashtags: Optional[List[str]] = None
    mention_handles: Optional[List[str]] = None
    media_asset_ids: Optional[List[str]] = None
    link_url: Optional[str] = None
    platform_specific: Optional[Dict[str, Any]] = None
    labels: Optional[List[str]] = None


class SchedulePostRequest(BaseModel):
    scheduled_at: datetime
    platform_account_ids: Optional[List[str]] = None  # override which accounts to post to
    use_optimal_time: bool = Field(
        default=False,
        description="Ignore scheduled_at and use AI-calculated optimal time",
    )


class ApproveRejectRequest(BaseModel):
    comment: Optional[str] = None


class BulkScheduleRequest(BaseModel):
    post_ids: List[str] = Field(..., min_length=1)
    scheduled_at: datetime
    use_optimal_time: bool = False


class BulkApproveRequest(BaseModel):
    post_ids: List[str] = Field(..., min_length=1)
    comment: Optional[str] = None


class BulkDeleteRequest(BaseModel):
    post_ids: List[str] = Field(..., min_length=1)


class ABTestSetupRequest(BaseModel):
    post_id_a: str
    post_id_b: str
    test_name: str
    metric: str = Field(default="engagement_rate", description="engagement_rate|clicks|reach|impressions")
    duration_hours: int = Field(default=24, ge=1, le=168)
    split_percentage: float = Field(default=50.0, ge=10.0, le=90.0)


class PostResponse(BaseModel):
    id: str
    title: Optional[str]
    content: str
    status: str
    platform_accounts: List[Dict[str, Any]]
    scheduled_at: Optional[datetime]
    published_at: Optional[datetime]
    content_pillar_id: Optional[str]
    campaign_id: Optional[str]
    hashtags: List[str]
    labels: List[str]
    media_assets: List[Dict[str, Any]]
    requires_approval: bool
    approved_by: Optional[str]
    rejected_reason: Optional[str]
    created_at: datetime
    updated_at: datetime
    engagement_preview: Optional[Dict[str, Any]]


class CalendarEntry(BaseModel):
    date: str
    posts: List[PostResponse]
    total_posts: int
    platforms: List[str]


class MediaAssetResponse(BaseModel):
    id: str
    filename: str
    url: str
    thumbnail_url: Optional[str]
    file_type: str
    file_size_bytes: int
    width: Optional[int]
    height: Optional[int]
    duration_seconds: Optional[float]
    alt_text: Optional[str]
    created_at: datetime


class GeneratedContentResponse(BaseModel):
    variations: List[Dict[str, Any]]
    platform_adaptations: Dict[str, Any]
    hashtag_suggestions: List[str]
    content_score: Optional[float]
    readability_score: Optional[float]
    estimated_reach: Optional[Dict[str, int]]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.post(
    "/generate",
    response_model=GeneratedContentResponse,
    summary="AI-generate content for one or more platforms",
)
async def generate_content(
    payload: GenerateContentRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    result = await svc.generate_content(
        user_id=str(current_user.id),
        topic=payload.topic,
        content_type=payload.content_type,
        platforms=payload.platforms,
        tone=payload.tone,
        content_pillar_id=payload.content_pillar_id,
        include_hashtags=payload.include_hashtags,
        include_emojis=payload.include_emojis,
        include_cta=payload.include_cta,
        target_audience=payload.target_audience,
        max_length=payload.max_length,
        reference_urls=payload.reference_urls,
        style_examples=payload.style_examples,
        language=payload.language,
        variations_count=payload.variations_count,
    )
    return result


@router.get(
    "/posts",
    response_model=Dict[str, Any],
    summary="List posts with filtering and pagination",
)
async def list_posts(
    status_filter: Optional[str] = Query(default=None, alias="status"),
    platform: Optional[str] = Query(default=None),
    campaign_id: Optional[str] = Query(default=None),
    content_pillar_id: Optional[str] = Query(default=None),
    from_date: Optional[datetime] = Query(default=None),
    to_date: Optional[datetime] = Query(default=None),
    requires_approval: Optional[bool] = Query(default=None),
    search: Optional[str] = Query(default=None, max_length=256),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    posts, total = await svc.list_posts(
        user_id=str(current_user.id),
        status=status_filter,
        platform=platform,
        campaign_id=campaign_id,
        content_pillar_id=content_pillar_id,
        from_date=from_date,
        to_date=to_date,
        requires_approval=requires_approval,
        search=search,
        offset=pagination.offset,
        limit=pagination.limit,
    )
    return {
        "items": [_post_to_response(p) for p in posts],
        "total": total,
        "page": pagination.page,
        "page_size": pagination.page_size,
        "pages": -(-total // pagination.page_size),
    }


@router.post(
    "/posts",
    response_model=PostResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new post",
)
async def create_post(
    payload: PostCreate,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.create_post(
        user_id=str(current_user.id),
        **payload.model_dump(),
    )
    return _post_to_response(post)


@router.get(
    "/posts/{post_id}",
    response_model=PostResponse,
    summary="Get a specific post by ID",
)
async def get_post(
    post_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.get_post(str(post_id), str(current_user.id))
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return _post_to_response(post)


@router.patch(
    "/posts/{post_id}",
    response_model=PostResponse,
    summary="Update a post (draft or scheduled)",
)
async def update_post(
    post_id: UUID,
    payload: PostUpdate,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.update_post(
        post_id=str(post_id),
        user_id=str(current_user.id),
        updates=payload.model_dump(exclude_none=True),
    )
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return _post_to_response(post)


@router.delete(
    "/posts/{post_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a post",
)
async def delete_post(
    post_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    deleted = await svc.delete_post(str(post_id), str(current_user.id))
    if not deleted:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")


@router.post(
    "/posts/{post_id}/schedule",
    response_model=PostResponse,
    summary="Schedule a post for publishing",
)
async def schedule_post(
    post_id: UUID,
    payload: SchedulePostRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.scheduler_service import SchedulerService
    svc = ContentService(db, redis)
    scheduler = SchedulerService(db, redis)

    post = await svc.get_post(str(post_id), str(current_user.id))
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")

    if post.status == "published":
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Post already published")

    scheduled_time = payload.scheduled_at
    if payload.use_optimal_time:
        platform_slugs = [pa.get("platform") for pa in (getattr(post, "platform_accounts", None) or [])]
        scheduled_time = await scheduler.calculate_optimal_time(
            user_id=str(current_user.id),
            platforms=platform_slugs,
        )

    post = await scheduler.schedule_post(
        post_id=str(post_id),
        user_id=str(current_user.id),
        scheduled_at=scheduled_time,
        platform_account_ids=payload.platform_account_ids,
    )
    return _post_to_response(post)


@router.post(
    "/posts/{post_id}/approve",
    response_model=PostResponse,
    summary="Approve a post awaiting review",
)
async def approve_post(
    post_id: UUID,
    payload: ApproveRejectRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.approve_post(
        post_id=str(post_id),
        approver_id=str(current_user.id),
        comment=payload.comment,
    )
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return _post_to_response(post)


@router.post(
    "/posts/{post_id}/reject",
    response_model=PostResponse,
    summary="Reject a post and return to draft",
)
async def reject_post(
    post_id: UUID,
    payload: ApproveRejectRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.reject_post(
        post_id=str(post_id),
        rejector_id=str(current_user.id),
        reason=payload.comment,
    )
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return _post_to_response(post)


@router.post(
    "/posts/{post_id}/publish",
    response_model=PostResponse,
    summary="Publish a post immediately (bypasses schedule)",
)
async def publish_now(
    post_id: UUID,
    background_tasks: BackgroundTasks,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    post = await svc.get_post(str(post_id), str(current_user.id))
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    if getattr(post, "status", None) == "published":
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Post already published")

    from app.services.platform_service import PlatformService
    platform_svc = PlatformService(db, redis)
    background_tasks.add_task(
        platform_svc.publish_post,
        post_id=str(post_id),
        user_id=str(current_user.id),
    )
    # Mark as publishing
    post = await svc.update_post(str(post_id), str(current_user.id), {"status": "publishing"})
    return _post_to_response(post)


@router.post(
    "/posts/{post_id}/duplicate",
    response_model=PostResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Duplicate a post as a new draft",
)
async def duplicate_post(
    post_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    new_post = await svc.duplicate_post(str(post_id), str(current_user.id))
    if not new_post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return _post_to_response(new_post)


# ── Calendar ─────────────────────────────────────────────────────────────────

@router.get(
    "/calendar",
    summary="Get content calendar view",
)
async def get_calendar(
    year: int = Query(..., ge=2020, le=2035),
    month: int = Query(..., ge=1, le=12),
    platform: Optional[str] = Query(default=None),
    campaign_id: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    calendar_data = await svc.get_calendar(
        user_id=str(current_user.id),
        year=year,
        month=month,
        platform=platform,
        campaign_id=campaign_id,
    )
    return {"year": year, "month": month, "days": calendar_data}


@router.get(
    "/calendar/export",
    summary="Export content calendar as iCal or JSON",
)
async def export_calendar(
    format: str = Query(default="json", pattern="^(json|ical)$"),
    year: int = Query(...),
    month: int = Query(..., ge=1, le=12),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from fastapi.responses import Response
    svc = ContentService(db, redis)
    if format == "ical":
        ical_data = await svc.export_calendar_ical(
            user_id=str(current_user.id), year=year, month=month
        )
        return Response(
            content=ical_data,
            media_type="text/calendar",
            headers={"Content-Disposition": f"attachment; filename=content-calendar-{year}-{month:02d}.ics"},
        )
    calendar_data = await svc.get_calendar(
        user_id=str(current_user.id), year=year, month=month
    )
    return {"year": year, "month": month, "days": calendar_data}


# ── Bulk Operations ───────────────────────────────────────────────────────────

@router.post("/bulk/schedule", summary="Schedule multiple posts at once")
async def bulk_schedule(
    payload: BulkScheduleRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.scheduler_service import SchedulerService
    scheduler = SchedulerService(db, redis)
    results = await scheduler.bulk_schedule(
        post_ids=payload.post_ids,
        user_id=str(current_user.id),
        scheduled_at=payload.scheduled_at,
        use_optimal_time=payload.use_optimal_time,
    )
    return {"results": results, "total_scheduled": sum(1 for r in results if r.get("success"))}


@router.post("/bulk/approve", summary="Approve multiple posts at once")
async def bulk_approve(
    payload: BulkApproveRequest,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    results = []
    for post_id in payload.post_ids:
        try:
            post = await svc.approve_post(
                post_id=post_id,
                approver_id=str(current_user.id),
                comment=payload.comment,
            )
            results.append({"post_id": post_id, "success": True, "status": post.status if post else "unknown"})
        except Exception as exc:
            results.append({"post_id": post_id, "success": False, "error": str(exc)})
    return {"results": results, "total_approved": sum(1 for r in results if r.get("success"))}


@router.delete("/bulk", summary="Delete multiple posts at once")
async def bulk_delete(
    payload: BulkDeleteRequest,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    deleted_ids = []
    for post_id in payload.post_ids:
        success = await svc.delete_post(post_id, str(current_user.id))
        if success:
            deleted_ids.append(post_id)
    return {"deleted": deleted_ids, "count": len(deleted_ids)}


# ── Post Analytics ────────────────────────────────────────────────────────────

@router.get("/posts/{post_id}/analytics", summary="Get analytics for a specific post")
async def get_post_analytics(
    post_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.content import PostAnalytics
    result = await db.execute(
        select(PostAnalytics)
        .where(PostAnalytics.post_id == post_id)
        .order_by(PostAnalytics.recorded_at.desc())
        .limit(1)
    )
    analytics = result.scalar_one_or_none()
    if not analytics:
        return {
            "post_id": str(post_id),
            "impressions": 0,
            "reach": 0,
            "likes": 0,
            "comments": 0,
            "shares": 0,
            "clicks": 0,
            "saves": 0,
            "engagement_rate": 0.0,
            "platform_breakdown": {},
        }
    return {
        "post_id": str(post_id),
        "impressions": getattr(analytics, "impressions", 0),
        "reach": getattr(analytics, "reach", 0),
        "likes": getattr(analytics, "likes", 0),
        "comments": getattr(analytics, "comments", 0),
        "shares": getattr(analytics, "shares", 0),
        "clicks": getattr(analytics, "clicks", 0),
        "saves": getattr(analytics, "saves", 0),
        "engagement_rate": getattr(analytics, "engagement_rate", 0.0),
        "platform_breakdown": getattr(analytics, "platform_breakdown", {}),
        "recorded_at": analytics.recorded_at.isoformat() if analytics.recorded_at else None,
    }


# ── A/B Testing ───────────────────────────────────────────────────────────────

@router.post("/ab-test", status_code=status.HTTP_201_CREATED, summary="Set up an A/B content test")
async def setup_ab_test(
    payload: ABTestSetupRequest,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = ContentService(db, redis)
    test = await svc.setup_ab_test(
        user_id=str(current_user.id),
        post_id_a=payload.post_id_a,
        post_id_b=payload.post_id_b,
        test_name=payload.test_name,
        metric=payload.metric,
        duration_hours=payload.duration_hours,
        split_percentage=payload.split_percentage,
    )
    return test


# ── Media Assets ──────────────────────────────────────────────────────────────

@router.get("/media", response_model=List[MediaAssetResponse], summary="List media assets")
async def list_media_assets(
    file_type: Optional[str] = Query(default=None, description="image|video|audio|document"),
    search: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import MediaAsset
    from sqlalchemy import and_, or_, func

    filters = [MediaAsset.user_id == current_user.id]
    if file_type:
        filters.append(MediaAsset.file_type == file_type)
    if search:
        filters.append(MediaAsset.filename.ilike(f"%{search}%"))

    result = await db.execute(
        select(MediaAsset)
        .where(and_(*filters))
        .order_by(MediaAsset.created_at.desc())
        .offset(pagination.offset)
        .limit(pagination.limit)
    )
    assets = result.scalars().all()
    return [
        MediaAssetResponse(
            id=str(a.id),
            filename=a.filename,
            url=getattr(a, "url", ""),
            thumbnail_url=getattr(a, "thumbnail_url", None),
            file_type=getattr(a, "file_type", "image"),
            file_size_bytes=getattr(a, "file_size_bytes", 0),
            width=getattr(a, "width", None),
            height=getattr(a, "height", None),
            duration_seconds=getattr(a, "duration_seconds", None),
            alt_text=getattr(a, "alt_text", None),
            created_at=a.created_at,
        )
        for a in assets
    ]


@router.post(
    "/media/upload",
    response_model=MediaAssetResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Upload a media asset",
)
async def upload_media(
    file: UploadFile = File(...),
    alt_text: Optional[str] = Form(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    allowed_types = {
        "image/jpeg", "image/png", "image/gif", "image/webp",
        "video/mp4", "video/quicktime", "video/webm",
        "audio/mpeg", "audio/wav",
    }
    if file.content_type not in allowed_types:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported media type: {file.content_type}",
        )

    max_size = 500 * 1024 * 1024  # 500MB
    content = await file.read()
    if len(content) > max_size:
        raise HTTPException(
            status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
            detail="File exceeds maximum size of 500MB",
        )

    svc = ContentService(db, redis)
    asset = await svc.upload_media(
        user_id=str(current_user.id),
        filename=file.filename,
        content=content,
        content_type=file.content_type,
        alt_text=alt_text,
    )
    return MediaAssetResponse(
        id=str(asset.id),
        filename=asset.filename,
        url=getattr(asset, "url", ""),
        thumbnail_url=getattr(asset, "thumbnail_url", None),
        file_type=getattr(asset, "file_type", "image"),
        file_size_bytes=len(content),
        width=getattr(asset, "width", None),
        height=getattr(asset, "height", None),
        duration_seconds=getattr(asset, "duration_seconds", None),
        alt_text=alt_text,
        created_at=asset.created_at,
    )


@router.delete("/media/{asset_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Delete a media asset")
async def delete_media_asset(
    asset_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.content import MediaAsset
    result = await db.execute(
        select(MediaAsset).where(
            MediaAsset.id == asset_id,
            MediaAsset.user_id == current_user.id,
        )
    )
    asset = result.scalar_one_or_none()
    if not asset:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Media asset not found")
    svc = ContentService(db, redis)
    await svc.delete_media_asset(str(asset_id), str(current_user.id))


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _post_to_response(post) -> PostResponse:
    return PostResponse(
        id=str(post.id),
        title=getattr(post, "title", None),
        content=getattr(post, "content", ""),
        status=getattr(post, "status", "draft"),
        platform_accounts=getattr(post, "platform_accounts", None) or [],
        scheduled_at=getattr(post, "scheduled_at", None),
        published_at=getattr(post, "published_at", None),
        content_pillar_id=str(post.content_pillar_id) if getattr(post, "content_pillar_id", None) else None,
        campaign_id=str(post.campaign_id) if getattr(post, "campaign_id", None) else None,
        hashtags=getattr(post, "hashtags", None) or [],
        labels=getattr(post, "labels", None) or [],
        media_assets=getattr(post, "media_assets", None) or [],
        requires_approval=getattr(post, "requires_approval", False),
        approved_by=str(post.approved_by) if getattr(post, "approved_by", None) else None,
        rejected_reason=getattr(post, "rejected_reason", None),
        created_at=post.created_at,
        updated_at=post.updated_at,
        engagement_preview=getattr(post, "engagement_preview", None),
    )
