"""
Platform management routes for SociAI OS.

Endpoints:
  POST   /platforms/connect
  GET    /platforms/accounts
  GET    /platforms/accounts/{account_id}
  DELETE /platforms/accounts/{account_id}
  POST   /platforms/accounts/{account_id}/refresh-oauth
  POST   /platforms/accounts/{account_id}/test-connection
  GET    /platforms/accounts/{account_id}/metrics
  GET    /platforms/supported
  GET    /platforms/accounts/{account_id}/pages          (Meta / LinkedIn pages)
  POST   /platforms/accounts/{account_id}/select-page
  GET    /platforms/webhooks
  POST   /platforms/webhooks
  DELETE /platforms/webhooks/{webhook_id}
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Query, status
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import (
    get_current_active_user,
    get_db,
    get_redis,
    require_role,
)
from app.services.platform_service import PlatformService

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class ConnectPlatformRequest(BaseModel):
    platform: str = Field(..., description="Platform slug: linkedin, meta, tiktok, twitter, youtube, snapchat, pinterest, whatsapp, telegram, threads, reddit")
    access_token: Optional[str] = Field(default=None, description="Manual OAuth token (if not using redirect flow)")
    refresh_token: Optional[str] = None
    expires_at: Optional[datetime] = None
    page_id: Optional[str] = Field(default=None, description="Specific page/account ID to connect (for Meta, LinkedIn)")
    extra_credentials: Optional[Dict[str, str]] = Field(default=None, description="Platform-specific extra fields")


class SelectPageRequest(BaseModel):
    page_id: str
    page_name: str
    page_type: str = "page"   # page | profile | business


class WebhookCreateRequest(BaseModel):
    platform: str
    platform_account_id: UUID
    event_types: List[str] = Field(..., min_length=1)
    callback_url: Optional[str] = None  # Defaults to system webhook endpoint


class PlatformAccountResponse(BaseModel):
    id: str
    platform: str
    platform_user_id: str
    platform_username: Optional[str]
    platform_display_name: Optional[str]
    avatar_url: Optional[str]
    is_active: bool
    is_verified: bool
    token_expires_at: Optional[datetime]
    followers_count: Optional[int]
    connected_at: datetime
    last_synced_at: Optional[datetime]
    permissions: List[str]
    selected_page_id: Optional[str]
    selected_page_name: Optional[str]


class PlatformMetricsResponse(BaseModel):
    platform: str
    account_id: str
    period_start: datetime
    period_end: datetime
    followers: int
    following: Optional[int]
    posts_count: int
    total_impressions: int
    total_reach: int
    total_engagements: int
    engagement_rate: float
    avg_likes: float
    avg_comments: float
    avg_shares: float
    profile_views: Optional[int]
    link_clicks: Optional[int]
    top_posts: List[Dict[str, Any]]
    audience_demographics: Optional[Dict[str, Any]]
    growth_rate: float


class TestConnectionResponse(BaseModel):
    success: bool
    platform: str
    account_id: str
    username: Optional[str]
    permissions_granted: List[str]
    permissions_missing: List[str]
    message: str


class WebhookResponse(BaseModel):
    id: str
    platform: str
    platform_account_id: str
    event_types: List[str]
    callback_url: str
    is_active: bool
    created_at: datetime


# ─── Routes ───────────────────────────────────────────────────────────────────

SUPPORTED_PLATFORMS = [
    "linkedin", "meta", "facebook", "instagram", "tiktok",
    "twitter", "youtube", "snapchat", "pinterest",
    "whatsapp", "telegram", "threads", "reddit",
]


@router.get("/supported", summary="List all supported social platforms")
async def list_supported_platforms():
    return {
        "platforms": [
            {
                "slug": "linkedin",
                "name": "LinkedIn",
                "type": "professional",
                "supports_oauth": True,
                "scopes": ["r_liteprofile", "r_emailaddress", "w_member_social"],
                "post_types": ["text", "image", "video", "article", "carousel"],
                "max_caption_length": 3000,
            },
            {
                "slug": "meta",
                "name": "Meta (Facebook + Instagram)",
                "type": "social",
                "supports_oauth": True,
                "scopes": ["pages_manage_posts", "instagram_content_publish"],
                "post_types": ["text", "image", "video", "reel", "story", "carousel"],
                "max_caption_length": 2200,
            },
            {
                "slug": "tiktok",
                "name": "TikTok",
                "type": "video",
                "supports_oauth": True,
                "scopes": ["video.upload", "user.info.basic"],
                "post_types": ["video"],
                "max_caption_length": 2200,
            },
            {
                "slug": "twitter",
                "name": "Twitter / X",
                "type": "microblog",
                "supports_oauth": True,
                "scopes": ["tweet.read", "tweet.write", "users.read"],
                "post_types": ["text", "image", "video", "poll", "thread"],
                "max_caption_length": 280,
            },
            {
                "slug": "youtube",
                "name": "YouTube",
                "type": "video",
                "supports_oauth": True,
                "scopes": ["youtube.upload", "youtube.readonly"],
                "post_types": ["video", "short", "community_post"],
                "max_caption_length": 5000,
            },
            {
                "slug": "pinterest",
                "name": "Pinterest",
                "type": "visual",
                "supports_oauth": True,
                "scopes": ["pins:read", "pins:write", "boards:read", "boards:write"],
                "post_types": ["pin", "idea_pin", "carousel"],
                "max_caption_length": 500,
            },
            {
                "slug": "snapchat",
                "name": "Snapchat",
                "type": "ephemeral",
                "supports_oauth": True,
                "scopes": ["snapchat-marketing-api"],
                "post_types": ["snap", "story", "spotlight"],
                "max_caption_length": 250,
            },
        ]
    }


@router.post(
    "/connect",
    response_model=PlatformAccountResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Connect a social media platform account",
)
async def connect_platform(
    payload: ConnectPlatformRequest,
    background_tasks: BackgroundTasks,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    if payload.platform not in SUPPORTED_PLATFORMS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported platform '{payload.platform}'. Supported: {SUPPORTED_PLATFORMS}",
        )
    svc = PlatformService(db, redis)
    account = await svc.connect_platform(
        user_id=str(current_user.id),
        platform=payload.platform,
        access_token=payload.access_token,
        refresh_token=payload.refresh_token,
        expires_at=payload.expires_at,
        page_id=payload.page_id,
        extra_credentials=payload.extra_credentials,
    )
    # Kick off initial profile sync in background
    background_tasks.add_task(svc.sync_account_profile, str(account.id))
    return _account_to_response(account)


@router.get(
    "/accounts",
    response_model=List[PlatformAccountResponse],
    summary="List all connected platform accounts",
)
async def list_accounts(
    platform: Optional[str] = Query(default=None, description="Filter by platform slug"),
    active_only: bool = Query(default=True),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    accounts = await svc.list_accounts(
        user_id=str(current_user.id),
        platform=platform,
        active_only=active_only,
    )
    return [_account_to_response(a) for a in accounts]


@router.get(
    "/accounts/{account_id}",
    response_model=PlatformAccountResponse,
    summary="Get a specific connected platform account",
)
async def get_account(
    account_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    account = await svc.get_account(str(account_id), str(current_user.id))
    if not account:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Platform account not found")
    return _account_to_response(account)


@router.delete(
    "/accounts/{account_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Disconnect and remove a platform account",
)
async def disconnect_account(
    account_id: UUID,
    revoke_oauth: bool = Query(default=True, description="Also revoke OAuth token with the platform"),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    deleted = await svc.disconnect_account(
        account_id=str(account_id),
        user_id=str(current_user.id),
        revoke_oauth=revoke_oauth,
    )
    if not deleted:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Platform account not found")


@router.post(
    "/accounts/{account_id}/refresh-oauth",
    response_model=PlatformAccountResponse,
    summary="Refresh OAuth tokens for a platform account",
)
async def refresh_oauth_tokens(
    account_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    account = await svc.refresh_oauth_tokens(str(account_id), str(current_user.id))
    if not account:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Platform account not found")
    return _account_to_response(account)


@router.post(
    "/accounts/{account_id}/test-connection",
    response_model=TestConnectionResponse,
    summary="Test connectivity and token validity for a platform account",
)
async def test_connection(
    account_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    result = await svc.test_connection(str(account_id), str(current_user.id))
    if result is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Platform account not found")
    return result


@router.get(
    "/accounts/{account_id}/metrics",
    response_model=PlatformMetricsResponse,
    summary="Fetch platform-native metrics for an account",
)
async def get_platform_metrics(
    account_id: UUID,
    days: int = Query(default=30, ge=1, le=365),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    metrics = await svc.get_account_metrics(
        account_id=str(account_id),
        user_id=str(current_user.id),
        days=days,
    )
    if metrics is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Account or metrics not found")
    return metrics


@router.get(
    "/accounts/{account_id}/pages",
    summary="List pages / business profiles available for this account (Meta, LinkedIn)",
)
async def list_pages(
    account_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    pages = await svc.list_pages(str(account_id), str(current_user.id))
    return {"pages": pages}


@router.post(
    "/accounts/{account_id}/select-page",
    response_model=PlatformAccountResponse,
    summary="Set the active page/profile for a Meta or LinkedIn account",
)
async def select_page(
    account_id: UUID,
    payload: SelectPageRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    account = await svc.select_page(
        account_id=str(account_id),
        user_id=str(current_user.id),
        page_id=payload.page_id,
        page_name=payload.page_name,
    )
    return _account_to_response(account)


@router.get(
    "/webhooks",
    response_model=List[WebhookResponse],
    summary="List registered platform webhooks",
)
async def list_webhooks(
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.platform import PlatformWebhook
    result = await db.execute(
        select(PlatformWebhook)
        .where(PlatformWebhook.user_id == current_user.id)
        .order_by(PlatformWebhook.created_at.desc())
    )
    webhooks = result.scalars().all()
    return [
        WebhookResponse(
            id=str(w.id),
            platform=w.platform,
            platform_account_id=str(w.platform_account_id),
            event_types=w.event_types or [],
            callback_url=w.callback_url or "",
            is_active=w.is_active,
            created_at=w.created_at,
        )
        for w in webhooks
    ]


@router.post(
    "/webhooks",
    response_model=WebhookResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Register a webhook for platform events",
)
async def create_webhook(
    payload: WebhookCreateRequest,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = PlatformService(db, redis)
    webhook = await svc.register_webhook(
        user_id=str(current_user.id),
        platform=payload.platform,
        platform_account_id=str(payload.platform_account_id),
        event_types=payload.event_types,
        callback_url=payload.callback_url,
    )
    return WebhookResponse(
        id=str(webhook.id),
        platform=webhook.platform,
        platform_account_id=str(webhook.platform_account_id),
        event_types=webhook.event_types or [],
        callback_url=webhook.callback_url or "",
        is_active=webhook.is_active,
        created_at=webhook.created_at,
    )


@router.delete(
    "/webhooks/{webhook_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a registered webhook",
)
async def delete_webhook(
    webhook_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.platform import PlatformWebhook
    result = await db.execute(
        select(PlatformWebhook).where(
            PlatformWebhook.id == webhook_id,
            PlatformWebhook.user_id == current_user.id,
        )
    )
    webhook = result.scalar_one_or_none()
    if not webhook:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Webhook not found")
    await db.delete(webhook)
    await db.flush()


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _account_to_response(account) -> PlatformAccountResponse:
    return PlatformAccountResponse(
        id=str(account.id),
        platform=account.platform,
        platform_user_id=account.platform_user_id,
        platform_username=getattr(account, "platform_username", None),
        platform_display_name=getattr(account, "platform_display_name", None),
        avatar_url=getattr(account, "avatar_url", None),
        is_active=account.is_active,
        is_verified=getattr(account, "is_verified", False),
        token_expires_at=getattr(account, "token_expires_at", None),
        followers_count=getattr(account, "followers_count", None),
        connected_at=account.created_at,
        last_synced_at=getattr(account, "last_synced_at", None),
        permissions=getattr(account, "granted_scopes", None) or [],
        selected_page_id=getattr(account, "selected_page_id", None),
        selected_page_name=getattr(account, "selected_page_name", None),
    )
