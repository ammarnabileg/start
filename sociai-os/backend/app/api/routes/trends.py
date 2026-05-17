"""
Trend intelligence routes for SociAI OS.

Endpoints:
  GET  /trends                         – Get current trending topics
  GET  /trends/platform/{platform}     – Platform-specific trending topics
  GET  /trends/hashtags                – Trending hashtags
  POST /trends/generate                – Generate content from a trend
  GET  /trends/opportunities           – AI-scored trend opportunities
  POST /trends/opportunities/{id}/act  – Act on a trend opportunity
  GET  /trends/calendar                – Upcoming events / viral dates
  GET  /trends/competitor-trends       – What competitors are trending on
  GET  /trends/keywords                – Keyword trend tracking
  POST /trends/keywords                – Add keywords to track
  DELETE /trends/keywords/{id}         – Remove a tracked keyword
  GET  /trends/industry                – Industry-specific trend report
  GET  /trends/viral-patterns          – Recurring viral content patterns
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

from app.api.deps import get_current_active_user, get_db, get_redis, require_role

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class TrendingTopic(BaseModel):
    id: str
    topic: str
    platform: str
    rank: int
    volume: Optional[int]
    volume_change_pct: Optional[float]
    sentiment_score: Optional[float]
    virality_score: float
    related_hashtags: List[str]
    related_keywords: List[str]
    geographic_focus: Optional[str]
    started_trending_at: Optional[datetime]
    peak_time: Optional[datetime]
    category: Optional[str]
    is_opportunity: bool
    opportunity_score: Optional[float]


class HashtagTrend(BaseModel):
    hashtag: str
    platform: str
    post_volume: int
    volume_change_pct: float
    avg_engagement_rate: float
    top_post_preview: Optional[str]
    sentiment_score: Optional[float]
    difficulty_score: float   # 0-100, higher = harder to rank
    opportunity_score: float
    related_hashtags: List[str]
    peak_hour: Optional[int]
    recommended: bool


class GenerateFromTrendRequest(BaseModel):
    trend_id: Optional[str] = None
    topic: str
    platforms: List[str] = Field(..., min_length=1)
    angle: Optional[str] = Field(
        default=None,
        description="Content angle: informative|opinion|humor|behind_scenes|tutorial|challenge",
    )
    tone: Optional[str] = None
    content_pillar_id: Optional[str] = None
    save_as_draft: bool = True
    variations: int = Field(default=2, ge=1, le=5)


class TrendOpportunityResponse(BaseModel):
    id: str
    trend_topic: str
    platform: str
    opportunity_score: float  # 0-100
    urgency: str   # "immediate" | "24h" | "this_week"
    reason: str
    recommended_content_type: str
    suggested_angles: List[str]
    suggested_hashtags: List[str]
    estimated_reach: Optional[int]
    competitor_activity_level: str   # "none" | "low" | "moderate" | "high"
    expires_at: Optional[datetime]
    created_at: datetime


class ActOnOpportunityRequest(BaseModel):
    action: str = Field(..., description="generate_content|add_to_queue|dismiss|snooze")
    snooze_hours: Optional[int] = None
    generation_options: Optional[Dict[str, Any]] = None


class TrackedKeyword(BaseModel):
    id: str
    keyword: str
    platforms: List[str]
    alert_on_spike: bool
    spike_threshold_pct: float
    is_active: bool
    current_volume: Optional[int]
    volume_7d_avg: Optional[int]
    trend_direction: Optional[str]
    created_at: datetime


class AddKeywordRequest(BaseModel):
    keyword: str = Field(..., min_length=2, max_length=128)
    platforms: Optional[List[str]] = None
    alert_on_spike: bool = True
    spike_threshold_pct: float = Field(default=50.0, ge=10.0, le=500.0)


class ViralCalendarEvent(BaseModel):
    id: str
    event_name: str
    event_date: datetime
    event_type: str   # holiday|awareness_day|industry_event|cultural_moment|seasonal
    platforms: List[str]
    virality_score: float
    lead_time_days: int   # recommended days in advance to post
    content_suggestions: List[str]
    relevant_hashtags: List[str]
    is_global: bool
    regions: Optional[List[str]]


class ViralPattern(BaseModel):
    pattern_id: str
    pattern_name: str
    description: str
    example_content_types: List[str]
    best_platforms: List[str]
    best_time_of_day: Optional[str]
    best_day_of_week: Optional[str]
    avg_virality_score: float
    recurrence: str   # "seasonal" | "weekly" | "monthly" | "event-driven"
    current_opportunity: bool
    next_window: Optional[datetime]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.get(
    "",
    response_model=List[TrendingTopic],
    summary="Get current trending topics across all connected platforms",
)
async def get_trending_topics(
    limit: int = Query(default=20, ge=5, le=100),
    category: Optional[str] = Query(default=None, description="Filter by category: news|entertainment|sports|tech|lifestyle|business"),
    min_virality: float = Query(default=0.0, ge=0.0, le=1.0),
    opportunities_only: bool = Query(default=False),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    # Check Redis cache (trends are expensive to compute)
    import json
    cache_key = f"trends:all:{current_user.id}:{limit}:{category or 'all'}"
    cached = await redis.get(cache_key)
    if cached:
        trends = json.loads(cached)
        if opportunities_only:
            trends = [t for t in trends if t.get("is_opportunity")]
        return trends

    from app.models.analytics import TrendOpportunity
    result = await db.execute(
        select(TrendOpportunity)
        .where(TrendOpportunity.user_id == current_user.id)
        .order_by(TrendOpportunity.virality_score.desc())
        .limit(limit)
    )
    trends_db = result.scalars().all()
    trends = [
        TrendingTopic(
            id=str(t.id),
            topic=getattr(t, "topic", ""),
            platform=getattr(t, "platform", ""),
            rank=getattr(t, "rank", 0) or 0,
            volume=getattr(t, "volume", None),
            volume_change_pct=getattr(t, "volume_change_pct", None),
            sentiment_score=getattr(t, "sentiment_score", None),
            virality_score=getattr(t, "virality_score", 0.0) or 0.0,
            related_hashtags=getattr(t, "related_hashtags", None) or [],
            related_keywords=getattr(t, "related_keywords", None) or [],
            geographic_focus=getattr(t, "geographic_focus", None),
            started_trending_at=getattr(t, "started_trending_at", None),
            peak_time=getattr(t, "peak_time", None),
            category=getattr(t, "category", None),
            is_opportunity=getattr(t, "is_opportunity", False),
            opportunity_score=getattr(t, "opportunity_score", None),
        )
        for t in trends_db
    ]
    # Cache for 15 minutes
    await redis.setex(cache_key, 900, json.dumps([t.model_dump(mode="json") for t in trends]))
    return trends


@router.get(
    "/platform/{platform}",
    response_model=List[TrendingTopic],
    summary="Platform-specific trending topics",
)
async def get_platform_trends(
    platform: str,
    limit: int = Query(default=20, ge=5, le=100),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.analytics import TrendOpportunity
    result = await db.execute(
        select(TrendOpportunity)
        .where(
            TrendOpportunity.user_id == current_user.id,
            TrendOpportunity.platform == platform,
        )
        .order_by(TrendOpportunity.virality_score.desc())
        .limit(limit)
    )
    trends_db = result.scalars().all()
    return [
        TrendingTopic(
            id=str(t.id),
            topic=getattr(t, "topic", ""),
            platform=platform,
            rank=getattr(t, "rank", 0) or 0,
            volume=getattr(t, "volume", None),
            volume_change_pct=None,
            sentiment_score=None,
            virality_score=getattr(t, "virality_score", 0.0) or 0.0,
            related_hashtags=getattr(t, "related_hashtags", None) or [],
            related_keywords=[],
            geographic_focus=None,
            started_trending_at=None,
            peak_time=None,
            category=None,
            is_opportunity=False,
            opportunity_score=None,
        )
        for t in trends_db
    ]


@router.get(
    "/hashtags",
    response_model=List[HashtagTrend],
    summary="Trending hashtags across platforms",
)
async def get_trending_hashtags(
    platform: Optional[str] = Query(default=None),
    category: Optional[str] = Query(default=None),
    limit: int = Query(default=30, ge=5, le=100),
    recommended_only: bool = Query(default=False),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    import json
    cache_key = f"trending_hashtags:{current_user.id}:{platform or 'all'}:{limit}"
    cached = await redis.get(cache_key)
    if cached:
        return json.loads(cached)
    # In production: fetch from platform APIs and ML trend model
    return []


@router.post(
    "/generate",
    summary="Generate content inspired by a trending topic",
    status_code=status.HTTP_202_ACCEPTED,
)
async def generate_from_trend(
    payload: GenerateFromTrendRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    result = await svc.generate_content(
        user_id=str(current_user.id),
        topic=payload.topic,
        content_type="post",
        platforms=payload.platforms,
        tone=payload.tone,
        content_pillar_id=payload.content_pillar_id,
        include_hashtags=True,
        include_emojis=True,
        include_cta=True,
        variations_count=payload.variations,
    )

    saved_ids = []
    if payload.save_as_draft:
        for variation in result.get("variations", []):
            post = await svc.create_post(
                user_id=str(current_user.id),
                content=variation.get("content", ""),
                platform_account_ids=[],   # User will assign
                hashtags=variation.get("hashtags", []),
            )
            saved_ids.append(str(post.id))

    return {
        "generated_content": result,
        "saved_draft_ids": saved_ids,
        "trend_topic": payload.topic,
        "platforms": payload.platforms,
    }


@router.get(
    "/opportunities",
    response_model=List[TrendOpportunityResponse],
    summary="AI-scored trend opportunities for your brand",
)
async def get_trend_opportunities(
    urgency: Optional[str] = Query(default=None, description="immediate|24h|this_week"),
    platform: Optional[str] = Query(default=None),
    min_score: float = Query(default=60.0, ge=0.0, le=100.0),
    limit: int = Query(default=15, ge=5, le=50),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.analytics import TrendOpportunity
    from sqlalchemy import and_

    filters = [
        TrendOpportunity.user_id == current_user.id,
        TrendOpportunity.is_opportunity == True,
    ]
    if platform:
        filters.append(TrendOpportunity.platform == platform)

    result = await db.execute(
        select(TrendOpportunity)
        .where(and_(*filters))
        .order_by(TrendOpportunity.opportunity_score.desc())
        .limit(limit)
    )
    opps = result.scalars().all()
    return [
        TrendOpportunityResponse(
            id=str(o.id),
            trend_topic=getattr(o, "topic", ""),
            platform=getattr(o, "platform", ""),
            opportunity_score=getattr(o, "opportunity_score", 0.0) or 0.0,
            urgency=getattr(o, "urgency", "this_week"),
            reason=getattr(o, "reason", ""),
            recommended_content_type=getattr(o, "recommended_content_type", "post"),
            suggested_angles=getattr(o, "suggested_angles", None) or [],
            suggested_hashtags=getattr(o, "related_hashtags", None) or [],
            estimated_reach=getattr(o, "estimated_reach", None),
            competitor_activity_level=getattr(o, "competitor_activity_level", "low"),
            expires_at=getattr(o, "expires_at", None),
            created_at=o.created_at,
        )
        for o in opps
    ]


@router.post(
    "/opportunities/{opportunity_id}/act",
    summary="Act on a trend opportunity",
)
async def act_on_opportunity(
    opportunity_id: UUID,
    payload: ActOnOpportunityRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.analytics import TrendOpportunity

    result = await db.execute(
        select(TrendOpportunity).where(
            TrendOpportunity.id == opportunity_id,
            TrendOpportunity.user_id == current_user.id,
        )
    )
    opp = result.scalar_one_or_none()
    if not opp:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Opportunity not found")

    if payload.action == "dismiss":
        opp.is_opportunity = False
        db.add(opp)
        await db.flush()
        return {"action": "dismissed", "opportunity_id": str(opportunity_id)}

    if payload.action == "generate_content":
        from app.services.content_service import ContentService
        svc = ContentService(db, redis)
        gen_options = payload.generation_options or {}
        result = await svc.generate_content(
            user_id=str(current_user.id),
            topic=getattr(opp, "topic", ""),
            platforms=gen_options.get("platforms", ["twitter"]),
            content_type=gen_options.get("content_type", "post"),
            variations_count=gen_options.get("variations", 2),
        )
        return {"action": "content_generated", "generated": result}

    return {"action": payload.action, "opportunity_id": str(opportunity_id), "status": "queued"}


@router.get(
    "/calendar",
    response_model=List[ViralCalendarEvent],
    summary="Upcoming viral dates and events for content planning",
)
async def get_viral_calendar(
    days_ahead: int = Query(default=90, ge=7, le=365),
    category: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    import json
    cache_key = f"viral_calendar:{days_ahead}:{category or 'all'}"
    cached = await redis.get(cache_key)
    if cached:
        return json.loads(cached)
    # In production: loaded from a curated events database + AI enrichment
    return []


@router.get(
    "/competitor-trends",
    summary="Trending topics your competitors are posting about",
)
async def get_competitor_trends(
    limit: int = Query(default=20, ge=5, le=100),
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.analytics_service import AnalyticsService
    svc = AnalyticsService(db, redis)
    return await svc.get_competitor_trends(
        user_id=str(current_user.id),
        limit=limit,
    )


@router.get(
    "/keywords",
    response_model=List[TrackedKeyword],
    summary="List tracked keywords with current trend data",
)
async def list_tracked_keywords(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    return []


@router.post(
    "/keywords",
    response_model=TrackedKeyword,
    status_code=status.HTTP_201_CREATED,
    summary="Add a keyword to track",
)
async def add_tracked_keyword(
    payload: AddKeywordRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    now = datetime.now(timezone.utc)
    return TrackedKeyword(
        id="stub-keyword-id",
        keyword=payload.keyword,
        platforms=payload.platforms or ["twitter", "linkedin"],
        alert_on_spike=payload.alert_on_spike,
        spike_threshold_pct=payload.spike_threshold_pct,
        is_active=True,
        current_volume=None,
        volume_7d_avg=None,
        trend_direction=None,
        created_at=now,
    )


@router.delete(
    "/keywords/{keyword_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Remove a tracked keyword",
)
async def delete_tracked_keyword(
    keyword_id: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    pass  # In production: delete from DB


@router.get(
    "/industry",
    summary="Industry-specific trend report",
)
async def get_industry_trends(
    industry: Optional[str] = Query(default=None, description="e.g. saas, ecommerce, healthcare, fintech"),
    limit: int = Query(default=15),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    return {"industry": industry or "general", "trends": [], "generated_at": datetime.now(timezone.utc).isoformat()}


@router.get(
    "/viral-patterns",
    response_model=List[ViralPattern],
    summary="Recurring viral content patterns and timing opportunities",
)
async def get_viral_patterns(
    platform: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    # In production: ML model predictions based on historical data
    return []
