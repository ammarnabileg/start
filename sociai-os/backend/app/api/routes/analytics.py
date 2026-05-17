"""
Analytics routes for SociAI OS.

Endpoints:
  GET /analytics/dashboard            – Aggregated KPI dashboard
  GET /analytics/platforms            – Per-platform breakdown
  GET /analytics/platforms/{platform} – Single-platform deep-dive
  GET /analytics/viral-scores         – Top performing content by viral score
  GET /analytics/sentiment            – Sentiment overview + trend
  GET /analytics/sentiment/alerts     – Active sentiment alerts
  GET /analytics/competitor           – Competitor benchmarking
  GET /analytics/competitor/{id}      – Single-competitor analysis
  GET /analytics/growth               – Follower / engagement growth tracking
  GET /analytics/growth/predictions   – AI-powered growth predictions
  GET /analytics/top-content          – Top-performing content
  GET /analytics/audience             – Audience demographics & behaviour
  GET /analytics/hashtags             – Hashtag performance
  GET /analytics/reports              – List generated reports
  POST /analytics/reports             – Generate and export a report
  GET /analytics/reports/{id}         – Download a specific report
"""
from __future__ import annotations

import logging
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from pydantic import BaseModel, Field
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import get_current_active_user, get_db, get_redis, require_role
from app.services.analytics_service import AnalyticsService

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class DateRangeParams:
    def __init__(
        self,
        days: int = Query(default=30, ge=1, le=365, description="Look-back window in days"),
        from_date: Optional[datetime] = Query(default=None),
        to_date: Optional[datetime] = Query(default=None),
    ):
        if from_date and to_date:
            self.start = from_date
            self.end = to_date
        else:
            self.end = datetime.now(timezone.utc)
            self.start = self.end - timedelta(days=days)
        self.days = days


class DashboardMetricsResponse(BaseModel):
    period_start: datetime
    period_end: datetime
    total_followers: int
    follower_growth: int
    follower_growth_pct: float
    total_impressions: int
    total_reach: int
    total_engagements: int
    avg_engagement_rate: float
    total_posts_published: int
    posts_scheduled: int
    posts_in_draft: int
    top_platform: Optional[str]
    viral_posts_count: int
    avg_viral_score: float
    sentiment_score: Optional[float]
    platform_summary: List[Dict[str, Any]]
    recent_alerts: List[Dict[str, Any]]
    growth_chart: List[Dict[str, Any]]


class PlatformBreakdownResponse(BaseModel):
    platform: str
    account_id: str
    account_name: Optional[str]
    followers: int
    follower_delta: int
    impressions: int
    reach: int
    engagements: int
    engagement_rate: float
    posts_count: int
    avg_likes: float
    avg_comments: float
    avg_shares: float
    top_post: Optional[Dict[str, Any]]
    growth_trend: str   # "up" | "down" | "flat"
    growth_pct: float


class ViralScoreResponse(BaseModel):
    post_id: str
    title: Optional[str]
    content_preview: str
    platform: str
    viral_score: float
    virality_factors: Dict[str, float]
    impressions: int
    engagements: int
    shares: int
    published_at: Optional[datetime]
    reach_velocity: float


class SentimentResponse(BaseModel):
    overall_score: float    # -1.0 to 1.0
    overall_label: str      # "very_positive" | "positive" | "neutral" | "negative" | "very_negative"
    positive_pct: float
    neutral_pct: float
    negative_pct: float
    total_mentions: int
    total_comments_analyzed: int
    platform_sentiment: Dict[str, float]
    trend: List[Dict[str, Any]]   # daily scores
    top_positive_topics: List[str]
    top_negative_topics: List[str]
    emotion_breakdown: Dict[str, float]


class SentimentAlertResponse(BaseModel):
    id: str
    alert_type: str   # "negative_spike" | "brand_mention" | "crisis"
    severity: str     # "low" | "medium" | "high" | "critical"
    platform: str
    message: str
    triggered_at: datetime
    resolved: bool
    resolved_at: Optional[datetime]
    post_ids: List[str]


class CompetitorAnalysisResponse(BaseModel):
    competitor_id: str
    competitor_name: str
    platform: str
    their_followers: int
    our_followers: int
    follower_gap: int
    their_engagement_rate: float
    our_engagement_rate: float
    their_post_frequency: float
    our_post_frequency: float
    their_top_content_types: List[str]
    our_top_content_types: List[str]
    top_competitor_hashtags: List[str]
    their_avg_viral_score: Optional[float]
    competitive_score: float   # 0-100, higher = we're winning
    recommendations: List[str]
    last_analyzed_at: datetime


class GrowthDataResponse(BaseModel):
    platform: str
    account_id: str
    data_points: List[Dict[str, Any]]  # [{date, followers, engagements, ...}]
    followers_start: int
    followers_end: int
    net_growth: int
    growth_rate_pct: float
    best_day: Optional[Dict[str, Any]]
    worst_day: Optional[Dict[str, Any]]
    avg_daily_growth: float


class GrowthPredictionResponse(BaseModel):
    platform: str
    account_id: str
    current_followers: int
    predicted_30d: int
    predicted_90d: int
    predicted_6m: int
    predicted_1y: int
    confidence: float
    growth_model: str
    assumptions: List[str]
    predicted_milestones: List[Dict[str, Any]]


class ReportCreateRequest(BaseModel):
    report_type: str = Field(..., description="summary|platform|competitor|growth|content|custom")
    title: str = Field(..., max_length=256)
    platforms: Optional[List[str]] = None
    from_date: datetime
    to_date: datetime
    include_sections: Optional[List[str]] = None
    format: str = Field(default="pdf", pattern="^(pdf|csv|xlsx|json)$")
    email_recipients: Optional[List[str]] = None


class ReportResponse(BaseModel):
    id: str
    title: str
    report_type: str
    format: str
    status: str
    download_url: Optional[str]
    file_size_bytes: Optional[int]
    created_at: datetime
    generated_at: Optional[datetime]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.get(
    "/dashboard",
    response_model=DashboardMetricsResponse,
    summary="Get aggregated KPI dashboard metrics",
)
async def get_dashboard(
    date_range: DateRangeParams = Depends(),
    platform: Optional[str] = Query(default=None, description="Filter to a specific platform"),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_dashboard_metrics(
        user_id=str(current_user.id),
        start=date_range.start,
        end=date_range.end,
        platform=platform,
    )


@router.get(
    "/platforms",
    response_model=List[PlatformBreakdownResponse],
    summary="Per-platform performance breakdown",
)
async def get_platform_breakdown(
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_platform_breakdown(
        user_id=str(current_user.id),
        start=date_range.start,
        end=date_range.end,
    )


@router.get(
    "/platforms/{platform}",
    summary="Deep-dive analytics for a single platform",
)
async def get_single_platform_analytics(
    platform: str,
    account_id: Optional[str] = Query(default=None),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_platform_deep_dive(
        user_id=str(current_user.id),
        platform=platform,
        account_id=account_id,
        start=date_range.start,
        end=date_range.end,
    )


@router.get(
    "/viral-scores",
    response_model=List[ViralScoreResponse],
    summary="Top content ranked by viral score",
)
async def get_viral_scores(
    limit: int = Query(default=20, ge=1, le=100),
    platform: Optional[str] = Query(default=None),
    min_score: float = Query(default=0.0, ge=0.0, le=1.0),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_viral_scores(
        user_id=str(current_user.id),
        start=date_range.start,
        end=date_range.end,
        platform=platform,
        min_score=min_score,
        limit=limit,
    )


@router.get(
    "/sentiment",
    response_model=SentimentResponse,
    summary="Brand sentiment analysis overview",
)
async def get_sentiment(
    date_range: DateRangeParams = Depends(),
    platform: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_sentiment_overview(
        user_id=str(current_user.id),
        start=date_range.start,
        end=date_range.end,
        platform=platform,
    )


@router.get(
    "/sentiment/alerts",
    response_model=List[SentimentAlertResponse],
    summary="Active sentiment alerts",
)
async def get_sentiment_alerts(
    resolved: bool = Query(default=False),
    severity: Optional[str] = Query(default=None, pattern="^(low|medium|high|critical)$"),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.analytics import SentimentAnalysis
    from sqlalchemy import select

    # In a real implementation, there'd be a SentimentAlert model;
    # here we stub with the SentimentAnalysis model
    result = await db.execute(
        select(SentimentAnalysis)
        .where(SentimentAnalysis.user_id == current_user.id)
        .order_by(SentimentAnalysis.created_at.desc())
        .limit(50)
    )
    rows = result.scalars().all()
    alerts = []
    for row in rows:
        score = getattr(row, "score", 0.0) or 0.0
        if score < -0.3:  # Simulate negative spike alert
            alerts.append(
                SentimentAlertResponse(
                    id=str(row.id),
                    alert_type="negative_spike",
                    severity="high" if score < -0.6 else "medium",
                    platform=getattr(row, "platform", "unknown"),
                    message=f"Negative sentiment spike detected (score: {score:.2f})",
                    triggered_at=row.created_at,
                    resolved=False,
                    resolved_at=None,
                    post_ids=[],
                )
            )
    return alerts


@router.get(
    "/competitor",
    response_model=List[CompetitorAnalysisResponse],
    summary="Competitive benchmarking across all tracked competitors",
)
async def get_competitor_analysis(
    platform: Optional[str] = Query(default=None),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_competitor_analysis(
        user_id=str(current_user.id),
        platform=platform,
        start=date_range.start,
        end=date_range.end,
    )


@router.get(
    "/competitor/{competitor_id}",
    response_model=CompetitorAnalysisResponse,
    summary="Deep-dive on a single competitor",
)
async def get_single_competitor(
    competitor_id: UUID,
    date_range: DateRangeParams = Depends(),
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    result = await svc.get_competitor_detail(
        user_id=str(current_user.id),
        competitor_id=str(competitor_id),
        start=date_range.start,
        end=date_range.end,
    )
    if not result:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Competitor not found")
    return result


@router.get(
    "/growth",
    response_model=List[GrowthDataResponse],
    summary="Follower and engagement growth tracking",
)
async def get_growth_data(
    platform: Optional[str] = Query(default=None),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_growth_data(
        user_id=str(current_user.id),
        platform=platform,
        start=date_range.start,
        end=date_range.end,
    )


@router.get(
    "/growth/predictions",
    response_model=List[GrowthPredictionResponse],
    summary="AI-powered growth predictions",
)
async def get_growth_predictions(
    platform: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_growth_predictions(
        user_id=str(current_user.id),
        platform=platform,
    )


@router.get(
    "/top-content",
    summary="Top-performing content across all platforms",
)
async def get_top_content(
    metric: str = Query(default="engagement_rate", description="engagement_rate|impressions|shares|viral_score"),
    limit: int = Query(default=10, ge=1, le=50),
    platform: Optional[str] = Query(default=None),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_top_content(
        user_id=str(current_user.id),
        metric=metric,
        limit=limit,
        platform=platform,
        start=date_range.start,
        end=date_range.end,
    )


@router.get(
    "/audience",
    summary="Audience demographics and behaviour insights",
)
async def get_audience_insights(
    platform: Optional[str] = Query(default=None),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_audience_insights(
        user_id=str(current_user.id),
        platform=platform,
    )


@router.get(
    "/hashtags",
    summary="Hashtag performance analysis",
)
async def get_hashtag_performance(
    limit: int = Query(default=30, ge=5, le=100),
    platform: Optional[str] = Query(default=None),
    date_range: DateRangeParams = Depends(),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    return await svc.get_hashtag_performance(
        user_id=str(current_user.id),
        platform=platform,
        start=date_range.start,
        end=date_range.end,
        limit=limit,
    )


@router.get(
    "/reports",
    response_model=List[ReportResponse],
    summary="List all generated analytics reports",
)
async def list_reports(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.analytics import AnalyticsReport
    from sqlalchemy import select

    result = await db.execute(
        select(AnalyticsReport)
        .where(AnalyticsReport.user_id == current_user.id)
        .order_by(AnalyticsReport.created_at.desc())
        .limit(50)
    )
    reports = result.scalars().all()
    return [
        ReportResponse(
            id=str(r.id),
            title=getattr(r, "title", "Report"),
            report_type=getattr(r, "report_type", "summary"),
            format=getattr(r, "format", "pdf"),
            status=getattr(r, "status", "completed"),
            download_url=getattr(r, "download_url", None),
            file_size_bytes=getattr(r, "file_size_bytes", None),
            created_at=r.created_at,
            generated_at=getattr(r, "generated_at", None),
        )
        for r in reports
    ]


@router.post(
    "/reports",
    response_model=ReportResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Generate and optionally export an analytics report",
)
async def create_report(
    payload: ReportCreateRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AnalyticsService(db, redis)
    report = await svc.generate_report(
        user_id=str(current_user.id),
        report_type=payload.report_type,
        title=payload.title,
        platforms=payload.platforms,
        from_date=payload.from_date,
        to_date=payload.to_date,
        include_sections=payload.include_sections,
        format=payload.format,
        email_recipients=payload.email_recipients,
    )
    return ReportResponse(
        id=str(report.id),
        title=report.title,
        report_type=getattr(report, "report_type", payload.report_type),
        format=getattr(report, "format", payload.format),
        status=getattr(report, "status", "generating"),
        download_url=None,
        file_size_bytes=None,
        created_at=report.created_at,
        generated_at=None,
    )


@router.get(
    "/reports/{report_id}",
    response_model=ReportResponse,
    summary="Get details and download link for a specific report",
)
async def get_report(
    report_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.analytics import AnalyticsReport
    from sqlalchemy import select

    result = await db.execute(
        select(AnalyticsReport).where(
            AnalyticsReport.id == report_id,
            AnalyticsReport.user_id == current_user.id,
        )
    )
    report = result.scalar_one_or_none()
    if not report:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Report not found")
    return ReportResponse(
        id=str(report.id),
        title=getattr(report, "title", "Report"),
        report_type=getattr(report, "report_type", "summary"),
        format=getattr(report, "format", "pdf"),
        status=getattr(report, "status", "completed"),
        download_url=getattr(report, "download_url", None),
        file_size_bytes=getattr(report, "file_size_bytes", None),
        created_at=report.created_at,
        generated_at=getattr(report, "generated_at", None),
    )
