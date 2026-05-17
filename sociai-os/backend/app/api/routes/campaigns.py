"""
Campaign management routes for SociAI OS.

Endpoints:
  POST   /campaigns                      – Create campaign
  GET    /campaigns                      – List campaigns
  GET    /campaigns/{id}                 – Get campaign detail
  PATCH  /campaigns/{id}                 – Update campaign
  DELETE /campaigns/{id}                 – Delete campaign
  POST   /campaigns/{id}/launch          – Launch campaign
  POST   /campaigns/{id}/pause           – Pause campaign
  POST   /campaigns/{id}/complete        – Mark campaign complete
  GET    /campaigns/{id}/timeline        – Get campaign timeline/milestones
  POST   /campaigns/{id}/milestones      – Add a milestone
  PATCH  /campaigns/{id}/milestones/{mid} – Update a milestone
  DELETE /campaigns/{id}/milestones/{mid} – Delete a milestone
  GET    /campaigns/{id}/performance     – Campaign performance metrics
  GET    /campaigns/{id}/posts           – List posts under this campaign
  POST   /campaigns/{id}/posts/add       – Assign posts to campaign
  GET    /campaigns/{id}/budget          – Budget tracking
  PATCH  /campaigns/{id}/budget          – Update budget allocation
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

class CampaignCreate(BaseModel):
    name: str = Field(..., max_length=256)
    description: Optional[str] = None
    campaign_type: str = Field(
        default="awareness",
        description="awareness|engagement|lead_gen|conversion|retention|product_launch|brand_awareness|event",
    )
    start_date: datetime
    end_date: datetime
    platforms: List[str] = Field(default_factory=list)
    target_audience: Optional[str] = None
    goals: Optional[List[Dict[str, Any]]] = None
    budget_total: Optional[float] = None
    budget_currency: str = "USD"
    hashtags: Optional[List[str]] = None
    content_pillar_ids: Optional[List[str]] = None
    target_impressions: Optional[int] = None
    target_engagements: Optional[int] = None
    target_followers_gained: Optional[int] = None
    color_code: Optional[str] = None   # UI hex color
    cover_image_url: Optional[str] = None
    collaborators: Optional[List[str]] = None  # user IDs


class CampaignUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    campaign_type: Optional[str] = None
    start_date: Optional[datetime] = None
    end_date: Optional[datetime] = None
    platforms: Optional[List[str]] = None
    target_audience: Optional[str] = None
    goals: Optional[List[Dict[str, Any]]] = None
    budget_total: Optional[float] = None
    hashtags: Optional[List[str]] = None
    content_pillar_ids: Optional[List[str]] = None
    target_impressions: Optional[int] = None
    target_engagements: Optional[int] = None
    target_followers_gained: Optional[int] = None
    color_code: Optional[str] = None
    cover_image_url: Optional[str] = None


class MilestoneCreate(BaseModel):
    title: str = Field(..., max_length=256)
    description: Optional[str] = None
    due_date: datetime
    milestone_type: str = Field(
        default="checkpoint",
        description="launch|checkpoint|review|publish|deadline|custom",
    )
    assigned_to: Optional[List[str]] = None  # user IDs
    task_ids: Optional[List[str]] = None     # linked agent tasks


class MilestoneUpdate(BaseModel):
    title: Optional[str] = None
    description: Optional[str] = None
    due_date: Optional[datetime] = None
    milestone_type: Optional[str] = None
    status: Optional[str] = None
    assigned_to: Optional[List[str]] = None
    completion_notes: Optional[str] = None


class BudgetUpdate(BaseModel):
    budget_total: float
    budget_currency: str = "USD"
    allocations: Optional[Dict[str, float]] = Field(
        default=None,
        description="Platform-level budget allocations e.g. {'twitter': 500, 'linkedin': 1000}",
    )
    notes: Optional[str] = None


class AddPostsRequest(BaseModel):
    post_ids: List[str] = Field(..., min_length=1)


class CampaignResponse(BaseModel):
    id: str
    name: str
    description: Optional[str]
    status: str   # draft|active|paused|completed|cancelled
    campaign_type: str
    start_date: datetime
    end_date: datetime
    platforms: List[str]
    target_audience: Optional[str]
    goals: List[Dict[str, Any]]
    budget_total: Optional[float]
    budget_spent: Optional[float]
    budget_currency: str
    hashtags: List[str]
    content_pillar_ids: List[str]
    target_impressions: Optional[int]
    target_engagements: Optional[int]
    target_followers_gained: Optional[int]
    color_code: Optional[str]
    cover_image_url: Optional[str]
    posts_count: int
    milestones_count: int
    created_at: datetime
    updated_at: datetime


class MilestoneResponse(BaseModel):
    id: str
    campaign_id: str
    title: str
    description: Optional[str]
    due_date: datetime
    milestone_type: str
    status: str   # pending|in_progress|completed|overdue
    assigned_to: List[str]
    completed_at: Optional[datetime]
    completion_notes: Optional[str]
    created_at: datetime


class CampaignPerformanceResponse(BaseModel):
    campaign_id: str
    campaign_name: str
    status: str
    days_elapsed: int
    days_remaining: int
    posts_published: int
    posts_scheduled: int
    total_impressions: int
    total_reach: int
    total_engagements: int
    avg_engagement_rate: float
    followers_gained: int
    budget_spent: Optional[float]
    budget_remaining: Optional[float]
    roi: Optional[float]
    target_completion: Dict[str, float]   # {"impressions": 75.5, "engagements": 42.0}
    platform_breakdown: List[Dict[str, Any]]
    top_performing_posts: List[Dict[str, Any]]
    milestones_completed: int
    milestones_total: int
    health_score: float   # 0-100


class BudgetResponse(BaseModel):
    campaign_id: str
    budget_total: Optional[float]
    budget_spent: float
    budget_remaining: Optional[float]
    budget_currency: str
    spend_pct: Optional[float]
    allocations: Dict[str, float]
    spend_by_platform: Dict[str, float]
    daily_burn_rate: Optional[float]
    projected_spend_at_end: Optional[float]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.post(
    "",
    response_model=CampaignResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new campaign",
)
async def create_campaign(
    payload: CampaignCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    if payload.start_date >= payload.end_date:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="start_date must be before end_date",
        )
    from app.models.content import Campaign
    campaign = Campaign(
        user_id=current_user.id,
        name=payload.name,
        description=payload.description,
        campaign_type=payload.campaign_type,
        status="draft",
        start_date=payload.start_date,
        end_date=payload.end_date,
        platforms=payload.platforms,
        target_audience=payload.target_audience,
        goals=payload.goals or [],
        budget_total=payload.budget_total,
        budget_currency=payload.budget_currency,
        hashtags=payload.hashtags or [],
        content_pillar_ids=payload.content_pillar_ids or [],
        target_impressions=payload.target_impressions,
        target_engagements=payload.target_engagements,
        target_followers_gained=payload.target_followers_gained,
        color_code=payload.color_code,
        cover_image_url=payload.cover_image_url,
    )
    db.add(campaign)
    await db.flush()
    return _campaign_to_response(campaign)


@router.get(
    "",
    summary="List campaigns",
)
async def list_campaigns(
    status_filter: Optional[str] = Query(default=None, alias="status"),
    campaign_type: Optional[str] = Query(default=None),
    search: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import Campaign
    from sqlalchemy import and_

    filters = [Campaign.user_id == current_user.id]
    if status_filter:
        filters.append(Campaign.status == status_filter)
    if campaign_type:
        filters.append(Campaign.campaign_type == campaign_type)
    if search:
        filters.append(Campaign.name.ilike(f"%{search}%"))

    result = await db.execute(
        select(Campaign)
        .where(and_(*filters))
        .order_by(Campaign.start_date.desc())
        .offset(pagination.offset)
        .limit(pagination.limit)
    )
    campaigns = result.scalars().all()
    return {
        "items": [_campaign_to_response(c) for c in campaigns],
        "page": pagination.page,
        "page_size": pagination.page_size,
    }


@router.get(
    "/{campaign_id}",
    response_model=CampaignResponse,
    summary="Get a campaign by ID",
)
async def get_campaign(
    campaign_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    return _campaign_to_response(campaign)


@router.patch(
    "/{campaign_id}",
    response_model=CampaignResponse,
    summary="Update a campaign",
)
async def update_campaign(
    campaign_id: UUID,
    payload: CampaignUpdate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    for field, value in payload.model_dump(exclude_none=True).items():
        setattr(campaign, field, value)
    campaign.updated_at = datetime.now(timezone.utc)
    db.add(campaign)
    await db.flush()
    return _campaign_to_response(campaign)


@router.delete(
    "/{campaign_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a campaign",
)
async def delete_campaign(
    campaign_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    if getattr(campaign, "status", "") == "active":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete an active campaign. Pause it first.",
        )
    await db.delete(campaign)
    await db.flush()


@router.post("/{campaign_id}/launch", response_model=CampaignResponse, summary="Launch a campaign")
async def launch_campaign(
    campaign_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    if getattr(campaign, "status", "") not in ("draft", "paused"):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Campaign cannot be launched from its current status")
    campaign.status = "active"
    campaign.updated_at = datetime.now(timezone.utc)
    db.add(campaign)
    await db.flush()
    return _campaign_to_response(campaign)


@router.post("/{campaign_id}/pause", response_model=CampaignResponse, summary="Pause an active campaign")
async def pause_campaign(
    campaign_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    if getattr(campaign, "status", "") != "active":
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Only active campaigns can be paused")
    campaign.status = "paused"
    campaign.updated_at = datetime.now(timezone.utc)
    db.add(campaign)
    await db.flush()
    return _campaign_to_response(campaign)


@router.post("/{campaign_id}/complete", response_model=CampaignResponse, summary="Mark a campaign as complete")
async def complete_campaign(
    campaign_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    campaign.status = "completed"
    campaign.updated_at = datetime.now(timezone.utc)
    db.add(campaign)
    await db.flush()
    return _campaign_to_response(campaign)


# ── Milestones ────────────────────────────────────────────────────────────────

@router.get(
    "/{campaign_id}/timeline",
    response_model=List[MilestoneResponse],
    summary="Get campaign timeline milestones",
)
async def get_timeline(
    campaign_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    await _get_campaign_or_404(db, campaign_id, current_user.id)
    from app.models.content import Schedule as Milestone  # reuse Schedule or a Milestone model

    # In production, query a dedicated CampaignMilestone table
    return []


@router.post(
    "/{campaign_id}/milestones",
    response_model=MilestoneResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Add a milestone to a campaign",
)
async def add_milestone(
    campaign_id: UUID,
    payload: MilestoneCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    await _get_campaign_or_404(db, campaign_id, current_user.id)
    # Stub: insert into CampaignMilestone table
    now = datetime.now(timezone.utc)
    return MilestoneResponse(
        id=str(UUID(int=0)),  # Would be real DB ID
        campaign_id=str(campaign_id),
        title=payload.title,
        description=payload.description,
        due_date=payload.due_date,
        milestone_type=payload.milestone_type,
        status="pending",
        assigned_to=payload.assigned_to or [],
        completed_at=None,
        completion_notes=None,
        created_at=now,
    )


# ── Performance ───────────────────────────────────────────────────────────────

@router.get(
    "/{campaign_id}/performance",
    response_model=CampaignPerformanceResponse,
    summary="Get campaign performance metrics",
)
async def get_campaign_performance(
    campaign_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    from app.services.analytics_service import AnalyticsService
    svc = AnalyticsService(db, redis)
    return await svc.get_campaign_performance(
        campaign_id=str(campaign_id),
        user_id=str(current_user.id),
    )


# ── Posts ─────────────────────────────────────────────────────────────────────

@router.get("/{campaign_id}/posts", summary="List posts under this campaign")
async def list_campaign_posts(
    campaign_id: UUID,
    status_filter: Optional[str] = Query(default=None, alias="status"),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    await _get_campaign_or_404(db, campaign_id, current_user.id)
    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    posts, total = await svc.list_posts(
        user_id=str(current_user.id),
        campaign_id=str(campaign_id),
        status=status_filter,
        offset=pagination.offset,
        limit=pagination.limit,
    )
    return {"items": posts, "total": total}


@router.post("/{campaign_id}/posts/add", summary="Assign existing posts to a campaign")
async def add_posts_to_campaign(
    campaign_id: UUID,
    payload: AddPostsRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    await _get_campaign_or_404(db, campaign_id, current_user.id)
    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    updated = []
    for post_id in payload.post_ids:
        post = await svc.update_post(
            post_id=post_id,
            user_id=str(current_user.id),
            updates={"campaign_id": str(campaign_id)},
        )
        if post:
            updated.append(post_id)
    return {"assigned_post_ids": updated, "count": len(updated)}


# ── Budget ────────────────────────────────────────────────────────────────────

@router.get("/{campaign_id}/budget", response_model=BudgetResponse, summary="Get campaign budget tracking")
async def get_budget(
    campaign_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    budget_total = getattr(campaign, "budget_total", None)
    budget_spent = getattr(campaign, "budget_spent", 0.0) or 0.0
    return BudgetResponse(
        campaign_id=str(campaign_id),
        budget_total=budget_total,
        budget_spent=budget_spent,
        budget_remaining=(budget_total - budget_spent) if budget_total else None,
        budget_currency=getattr(campaign, "budget_currency", "USD"),
        spend_pct=round(budget_spent / budget_total * 100, 2) if budget_total else None,
        allocations=getattr(campaign, "budget_allocations", None) or {},
        spend_by_platform=getattr(campaign, "spend_by_platform", None) or {},
        daily_burn_rate=None,
        projected_spend_at_end=None,
    )


@router.patch("/{campaign_id}/budget", response_model=BudgetResponse, summary="Update campaign budget")
async def update_budget(
    campaign_id: UUID,
    payload: BudgetUpdate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    campaign = await _get_campaign_or_404(db, campaign_id, current_user.id)
    campaign.budget_total = payload.budget_total
    campaign.budget_currency = payload.budget_currency
    if payload.allocations:
        campaign.budget_allocations = payload.allocations
    campaign.updated_at = datetime.now(timezone.utc)
    db.add(campaign)
    await db.flush()
    return BudgetResponse(
        campaign_id=str(campaign_id),
        budget_total=payload.budget_total,
        budget_spent=getattr(campaign, "budget_spent", 0.0) or 0.0,
        budget_remaining=payload.budget_total - (getattr(campaign, "budget_spent", 0.0) or 0.0),
        budget_currency=payload.budget_currency,
        spend_pct=None,
        allocations=payload.allocations or {},
        spend_by_platform={},
        daily_burn_rate=None,
        projected_spend_at_end=None,
    )


# ─── Helpers ──────────────────────────────────────────────────────────────────

async def _get_campaign_or_404(db: AsyncSession, campaign_id: UUID, user_id):
    from app.models.content import Campaign
    result = await db.execute(
        select(Campaign).where(
            Campaign.id == campaign_id,
            Campaign.user_id == user_id,
        )
    )
    campaign = result.scalar_one_or_none()
    if not campaign:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Campaign not found")
    return campaign


def _campaign_to_response(c) -> CampaignResponse:
    return CampaignResponse(
        id=str(c.id),
        name=c.name,
        description=getattr(c, "description", None),
        status=getattr(c, "status", "draft"),
        campaign_type=getattr(c, "campaign_type", "awareness"),
        start_date=c.start_date,
        end_date=c.end_date,
        platforms=getattr(c, "platforms", None) or [],
        target_audience=getattr(c, "target_audience", None),
        goals=getattr(c, "goals", None) or [],
        budget_total=getattr(c, "budget_total", None),
        budget_spent=getattr(c, "budget_spent", 0.0) or 0.0,
        budget_currency=getattr(c, "budget_currency", "USD"),
        hashtags=getattr(c, "hashtags", None) or [],
        content_pillar_ids=getattr(c, "content_pillar_ids", None) or [],
        target_impressions=getattr(c, "target_impressions", None),
        target_engagements=getattr(c, "target_engagements", None),
        target_followers_gained=getattr(c, "target_followers_gained", None),
        color_code=getattr(c, "color_code", None),
        cover_image_url=getattr(c, "cover_image_url", None),
        posts_count=getattr(c, "posts_count", 0) or 0,
        milestones_count=getattr(c, "milestones_count", 0) or 0,
        created_at=c.created_at,
        updated_at=getattr(c, "updated_at", c.created_at),
    )
