"""
app/models/analytics.py
───────────────────────
AnalyticsReport, MetricSnapshot, ViralScore, SentimentAnalysis, TrendOpportunity models.
"""
from __future__ import annotations

import enum
import uuid
from datetime import datetime
from typing import TYPE_CHECKING, List, Optional

from sqlalchemy import (
    BigInteger,
    Boolean,
    DateTime,
    Enum,
    Float,
    ForeignKey,
    Index,
    Integer,
    String,
    Text,
    UniqueConstraint,
    func,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.core.database import Base

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.platform import PlatformAccount
    from app.models.content import ContentPiece, Post


# ─── Enums ────────────────────────────────────────────────────────────────────

class ReportType(str, enum.Enum):
    OVERVIEW = "overview"
    PLATFORM_SPECIFIC = "platform_specific"
    CAMPAIGN = "campaign"
    COMPETITOR = "competitor"
    TREND = "trend"
    CUSTOM = "custom"
    AI_INSIGHTS = "ai_insights"


class ReportStatus(str, enum.Enum):
    PENDING = "pending"
    GENERATING = "generating"
    READY = "ready"
    FAILED = "failed"
    EXPIRED = "expired"


class MetricGranularity(str, enum.Enum):
    HOURLY = "hourly"
    DAILY = "daily"
    WEEKLY = "weekly"
    MONTHLY = "monthly"


class SentimentLabel(str, enum.Enum):
    VERY_POSITIVE = "very_positive"
    POSITIVE = "positive"
    NEUTRAL = "neutral"
    NEGATIVE = "negative"
    VERY_NEGATIVE = "very_negative"
    MIXED = "mixed"


class TrendCategory(str, enum.Enum):
    HASHTAG = "hashtag"
    TOPIC = "topic"
    AUDIO = "audio"        # TikTok/Reels audio trends
    FORMAT = "format"      # content format trends
    CHALLENGE = "challenge"
    MEME = "meme"
    NEWS = "news"
    SEASONAL = "seasonal"


class TrendStatus(str, enum.Enum):
    EMERGING = "emerging"
    RISING = "rising"
    PEAK = "peak"
    DECLINING = "declining"
    EXPIRED = "expired"


# ─── Analytics Report Model ───────────────────────────────────────────────────

class AnalyticsReport(Base):
    """
    Generated analytics reports (overview, campaign-level, AI-insight reports, etc.)
    """
    __tablename__ = "analytics_reports"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    report_type: Mapped[ReportType] = mapped_column(
        Enum(ReportType), nullable=False, index=True
    )
    status: Mapped[ReportStatus] = mapped_column(
        Enum(ReportStatus), default=ReportStatus.PENDING, nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Report scope
    date_range_start: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    date_range_end: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    platforms: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    campaign_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), nullable=True  # denormalized, no FK to avoid cross-model complexity
    )

    # Report content (summary stats + AI insights)
    summary_metrics: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    platform_breakdown: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    top_posts: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    audience_insights: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    ai_insights: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    recommendations: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    trend_analysis: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Generation metadata
    generated_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    generation_duration_ms: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)
    ai_model_used: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    celery_task_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    error_message: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Export
    export_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    expires_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    __table_args__ = (
        Index("ix_analytics_reports_user_type", "user_id", "report_type"),
        Index("ix_analytics_reports_status", "status"),
    )


# ─── Metric Snapshot Model ────────────────────────────────────────────────────

class MetricSnapshot(Base):
    """
    Time-series snapshots of account-level metrics per platform.
    Used for charting follower growth, engagement trends, etc.
    """
    __tablename__ = "metric_snapshots"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    platform_account_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    granularity: Mapped[MetricGranularity] = mapped_column(
        Enum(MetricGranularity), nullable=False, index=True
    )
    period_start: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    period_end: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)

    # Account-level aggregate metrics
    followers: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    followers_gained: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    followers_lost: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    following: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_posts: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    posts_published: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

    # Aggregate engagement
    total_impressions: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_reach: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_likes: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_comments: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_shares: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_saves: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_clicks: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    total_video_views: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)

    # Calculated rates
    avg_engagement_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    avg_reach_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Audience demographics snapshot (if available via API)
    audience_demographics: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    audience_geography: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    audience_devices: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Platform-specific extras
    platform_extras: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    snapshot_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    # Relationships
    platform_account: Mapped["PlatformAccount"] = relationship(
        "PlatformAccount", back_populates="metric_snapshots"
    )

    __table_args__ = (
        UniqueConstraint(
            "platform_account_id", "granularity", "period_start",
            name="uq_metric_snapshot_account_granularity_period"
        ),
        Index("ix_metric_snapshots_account_period", "platform_account_id", "period_start"),
        Index("ix_metric_snapshots_user_period", "user_id", "period_start"),
    )


# ─── Viral Score Model ────────────────────────────────────────────────────────

class ViralScore(Base):
    """
    AI-computed virality potential score for a content piece before publishing.
    Updated when the content is edited or when new trend data becomes available.
    """
    __tablename__ = "viral_scores"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    content_piece_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("content_pieces.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Scores (0.0–100.0)
    overall_score: Mapped[float] = mapped_column(Float, nullable=False)
    hook_strength: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    emotional_resonance: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    shareability: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    trend_alignment: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    hashtag_effectiveness: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    timing_score: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    audience_fit: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    originality: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Platform-specific scores
    platform_scores: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # AI rationale
    rationale: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    improvement_suggestions: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    predicted_metrics: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Model used
    ai_model_used: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    model_version: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)
    scoring_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    version: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    __table_args__ = (
        Index("ix_viral_scores_content_piece", "content_piece_id"),
        Index("ix_viral_scores_overall", "overall_score"),
    )


# ─── Sentiment Analysis Model ─────────────────────────────────────────────────

class SentimentAnalysis(Base):
    """
    NLP sentiment analysis of comments, mentions, or any free-form text
    associated with posts or brand monitoring.
    """
    __tablename__ = "sentiment_analyses"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    # Polymorphic source reference (nullable: can analyze any text)
    post_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("posts.id", ondelete="CASCADE"), nullable=True, index=True
    )
    platform_account_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=True,
        index=True,
    )

    source_type: Mapped[str] = mapped_column(
        String(50), nullable=False
    )  # "comment", "mention", "review", "dm", "post_caption", "brand_monitoring"
    source_text: Mapped[str] = mapped_column(Text, nullable=False)
    source_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    author_platform_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)

    # Analysis results
    sentiment: Mapped[SentimentLabel] = mapped_column(
        Enum(SentimentLabel), nullable=False, index=True
    )
    sentiment_score: Mapped[float] = mapped_column(Float, nullable=False)  # -1.0 to 1.0
    confidence: Mapped[float] = mapped_column(Float, nullable=False)  # 0.0 to 1.0
    magnitude: Mapped[Optional[float]] = mapped_column(Float, nullable=True)  # intensity

    # Fine-grained emotion detection
    emotions: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    # e.g. {"joy": 0.8, "anger": 0.1, "sadness": 0.05, "fear": 0.02, "surprise": 0.03}

    # Entity / keyword extraction
    entities: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    keywords: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    topics: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)

    # Intent detection
    intent: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    # e.g. "purchase_intent", "complaint", "praise", "question", "neutral"

    # Model metadata
    ai_model_used: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    processing_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    analyzed_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    __table_args__ = (
        Index("ix_sentiment_analyses_user_sentiment", "user_id", "sentiment"),
        Index("ix_sentiment_analyses_analyzed_at", "analyzed_at"),
        Index("ix_sentiment_analyses_post", "post_id"),
    )


# ─── Trend Opportunity Model ──────────────────────────────────────────────────

class TrendOpportunity(Base):
    """
    Trending topics, hashtags, audio, and content formats discovered by AI agents.
    Surfaces actionable trend opportunities for the user.
    """
    __tablename__ = "trend_opportunities"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Trend identification
    trend_name: Mapped[str] = mapped_column(String(500), nullable=False)
    category: Mapped[TrendCategory] = mapped_column(
        Enum(TrendCategory), nullable=False, index=True
    )
    status: Mapped[TrendStatus] = mapped_column(
        Enum(TrendStatus), default=TrendStatus.EMERGING, nullable=False, index=True
    )
    platforms: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)

    # Trend metrics
    volume: Mapped[Optional[int]] = mapped_column(BigInteger, nullable=True)  # total mentions/uses
    velocity: Mapped[Optional[float]] = mapped_column(Float, nullable=True)  # growth rate %/hour
    peak_volume: Mapped[Optional[int]] = mapped_column(BigInteger, nullable=True)
    engagement_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    reach_estimate: Mapped[Optional[int]] = mapped_column(BigInteger, nullable=True)
    relevance_score: Mapped[float] = mapped_column(Float, nullable=False)  # 0–100, fit to user's niche

    # Content opportunity
    opportunity_window_hours: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)
    opportunity_expires_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    content_ideas: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    sample_successful_posts: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    ai_analysis: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    action_taken: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)

    # Discovery metadata
    discovered_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    source: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    # e.g., "twitter_trending", "tiktok_discover", "google_trends", "reddit"
    raw_data: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    __table_args__ = (
        Index("ix_trend_opportunities_user_status", "user_id", "status"),
        Index("ix_trend_opportunities_category_status", "category", "status"),
        Index("ix_trend_opportunities_relevance", "relevance_score"),
        Index("ix_trend_opportunities_expires", "opportunity_expires_at"),
    )
