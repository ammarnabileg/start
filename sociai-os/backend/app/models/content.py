"""
app/models/content.py
─────────────────────
ContentPiece, Post, Caption, Campaign, ContentPillar, Schedule, PostAnalytics models.
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
from sqlalchemy.dialects.postgresql import ARRAY, JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.core.database import Base

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.platform import PlatformAccount
    from app.models.strategy import ContentPlan
    from app.models.analytics import AnalyticsReport


# ─── Enums ────────────────────────────────────────────────────────────────────

class ContentType(str, enum.Enum):
    TEXT = "text"
    IMAGE = "image"
    VIDEO = "video"
    CAROUSEL = "carousel"
    STORY = "story"
    REEL = "reel"
    SHORT = "short"        # YouTube Shorts / TikTok
    THREAD = "thread"
    NEWSLETTER = "newsletter"
    PODCAST_CLIP = "podcast_clip"
    LIVE = "live"


class ContentStatus(str, enum.Enum):
    DRAFT = "draft"
    REVIEW = "review"
    APPROVED = "approved"
    SCHEDULED = "scheduled"
    PUBLISHING = "publishing"
    PUBLISHED = "published"
    FAILED = "failed"
    ARCHIVED = "archived"


class PostStatus(str, enum.Enum):
    QUEUED = "queued"
    PUBLISHING = "publishing"
    PUBLISHED = "published"
    FAILED = "failed"
    CANCELLED = "cancelled"
    DELETED_FROM_PLATFORM = "deleted_from_platform"


class CampaignStatus(str, enum.Enum):
    DRAFT = "draft"
    ACTIVE = "active"
    PAUSED = "paused"
    COMPLETED = "completed"
    ARCHIVED = "archived"


class ScheduleFrequency(str, enum.Enum):
    ONCE = "once"
    DAILY = "daily"
    WEEKLY = "weekly"
    BIWEEKLY = "biweekly"
    MONTHLY = "monthly"
    CUSTOM = "custom"  # cron expression


class AIGenerationModel(str, enum.Enum):
    GPT4O = "gpt-4o"
    GPT4O_MINI = "gpt-4o-mini"
    CLAUDE_SONNET = "claude-sonnet-4-6"
    CLAUDE_HAIKU = "claude-haiku-3-5"
    STABLE_DIFFUSION = "stable-diffusion-xl"
    DALL_E_3 = "dall-e-3"


# ─── Content Pillar Model ─────────────────────────────────────────────────────

class ContentPillar(Base):
    """
    High-level content themes/pillars for a user's content strategy.
    Example: 'Educational', 'Behind-the-Scenes', 'Promotional', 'UGC'
    """
    __tablename__ = "content_pillars"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(150), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    color_hex: Mapped[Optional[str]] = mapped_column(String(7), nullable=True)  # e.g. "#4F46E5"
    icon: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)
    target_percentage: Mapped[float] = mapped_column(Float, default=0.0, nullable=False)  # % of content mix
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    sort_order: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    content_pieces: Mapped[List["ContentPiece"]] = relationship(
        "ContentPiece", back_populates="pillar"
    )

    __table_args__ = (
        UniqueConstraint("user_id", "name", name="uq_content_pillar_user_name"),
    )


# ─── Campaign Model ───────────────────────────────────────────────────────────

class Campaign(Base):
    __tablename__ = "campaigns"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    status: Mapped[CampaignStatus] = mapped_column(
        Enum(CampaignStatus), default=CampaignStatus.DRAFT, nullable=False, index=True
    )
    objective: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    budget: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    currency: Mapped[str] = mapped_column(String(3), default="USD", nullable=False)
    start_date: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    end_date: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    target_platforms: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    target_audience: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    kpis: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    tags: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    ai_brief: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    cover_image_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    owner: Mapped["User"] = relationship("User", back_populates="campaigns")
    content_pieces: Mapped[List["ContentPiece"]] = relationship(
        "ContentPiece", back_populates="campaign"
    )

    __table_args__ = (
        Index("ix_campaigns_user_status", "user_id", "status"),
        Index("ix_campaigns_dates", "start_date", "end_date"),
    )


# ─── Content Piece Model ──────────────────────────────────────────────────────

class ContentPiece(Base):
    """
    The core content entity. A content piece can have multiple Posts
    (one per platform) and multiple AI-generated Captions.
    """
    __tablename__ = "content_pieces"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    campaign_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("campaigns.id", ondelete="SET NULL"), nullable=True, index=True
    )
    pillar_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("content_pillars.id", ondelete="SET NULL"), nullable=True, index=True
    )

    title: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    body: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    content_type: Mapped[ContentType] = mapped_column(
        Enum(ContentType), default=ContentType.TEXT, nullable=False, index=True
    )
    status: Mapped[ContentStatus] = mapped_column(
        Enum(ContentStatus), default=ContentStatus.DRAFT, nullable=False, index=True
    )

    # AI generation provenance
    ai_generated: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    ai_model_used: Mapped[Optional[AIGenerationModel]] = mapped_column(
        Enum(AIGenerationModel), nullable=True
    )
    ai_prompt: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    ai_generation_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Media
    media_urls: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    thumbnail_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    media_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Metadata
    hashtags: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    mentions: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    keywords: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    tags: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    language: Mapped[str] = mapped_column(String(10), default="en", nullable=False)
    tone: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)

    # Approval workflow
    approved_by_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    approved_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    rejection_reason: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Scoring
    viral_score: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    engagement_prediction: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    creator: Mapped["User"] = relationship("User", foreign_keys=[user_id], back_populates="content_pieces")
    campaign: Mapped[Optional["Campaign"]] = relationship("Campaign", back_populates="content_pieces")
    pillar: Mapped[Optional["ContentPillar"]] = relationship("ContentPillar", back_populates="content_pieces")
    captions: Mapped[List["Caption"]] = relationship(
        "Caption", back_populates="content_piece", cascade="all, delete-orphan"
    )
    posts: Mapped[List["Post"]] = relationship(
        "Post", back_populates="content_piece", cascade="all, delete-orphan"
    )
    schedules: Mapped[List["Schedule"]] = relationship(
        "Schedule", back_populates="content_piece", cascade="all, delete-orphan"
    )

    __table_args__ = (
        Index("ix_content_pieces_user_status", "user_id", "status"),
        Index("ix_content_pieces_type", "content_type"),
        Index("ix_content_pieces_deleted", "deleted_at"),
    )


# ─── Caption Model ────────────────────────────────────────────────────────────

class Caption(Base):
    """
    AI-generated (or human-written) caption variants for a ContentPiece.
    Multiple captions can exist per content piece per platform.
    """
    __tablename__ = "captions"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    content_piece_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("content_pieces.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    platform: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)  # NULL = universal
    text: Mapped[str] = mapped_column(Text, nullable=False)
    char_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    hashtags: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    call_to_action: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    tone: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    language: Mapped[str] = mapped_column(String(10), default="en", nullable=False)
    ai_generated: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    ai_model_used: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    ai_prompt: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    is_selected: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    quality_score: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    version: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    content_piece: Mapped["ContentPiece"] = relationship("ContentPiece", back_populates="captions")

    __table_args__ = (
        Index("ix_captions_content_piece_platform", "content_piece_id", "platform"),
    )


# ─── Post Model ───────────────────────────────────────────────────────────────

class Post(Base):
    """
    A published (or scheduled) unit of content on a specific platform account.
    Linked 1:1 to a platform publishing event.
    """
    __tablename__ = "posts"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    content_piece_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("content_pieces.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    platform_account_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    caption_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("captions.id", ondelete="SET NULL"), nullable=True
    )
    schedule_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("schedules.id", ondelete="SET NULL"), nullable=True
    )

    status: Mapped[PostStatus] = mapped_column(
        Enum(PostStatus), default=PostStatus.QUEUED, nullable=False, index=True
    )

    # Platform-native identifiers (populated after successful publish)
    platform_post_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    platform_post_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)

    # Publishing details
    scheduled_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    published_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    failed_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    failure_reason: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    retry_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

    # Platform-specific post options (e.g., first_comment, tagged_users, location)
    publish_options: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    platform_response: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Celery task tracking
    celery_task_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    content_piece: Mapped["ContentPiece"] = relationship("ContentPiece", back_populates="posts")
    platform_account: Mapped["PlatformAccount"] = relationship(
        "PlatformAccount", back_populates="posts"
    )
    caption: Mapped[Optional["Caption"]] = relationship("Caption")
    schedule: Mapped[Optional["Schedule"]] = relationship("Schedule", back_populates="posts")
    analytics: Mapped[List["PostAnalytics"]] = relationship(
        "PostAnalytics", back_populates="post", cascade="all, delete-orphan"
    )

    __table_args__ = (
        Index("ix_posts_scheduled_at", "scheduled_at"),
        Index("ix_posts_published_at", "published_at"),
        Index("ix_posts_status_scheduled", "status", "scheduled_at"),
    )


# ─── Schedule Model ───────────────────────────────────────────────────────────

class Schedule(Base):
    """
    Publishing schedule for content pieces. Supports one-time and recurring schedules.
    """
    __tablename__ = "schedules"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    content_piece_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("content_pieces.id", ondelete="CASCADE"),
        nullable=True,
        index=True,
    )
    name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    frequency: Mapped[ScheduleFrequency] = mapped_column(
        Enum(ScheduleFrequency), default=ScheduleFrequency.ONCE, nullable=False
    )
    cron_expression: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    timezone: Mapped[str] = mapped_column(String(50), default="UTC", nullable=False)
    scheduled_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False, index=True)
    next_run_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    last_run_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    is_ai_optimized: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    # Which platform accounts to publish to
    target_platform_account_ids: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    run_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    max_runs: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)  # NULL = unlimited
    celery_periodic_task_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    content_piece: Mapped[Optional["ContentPiece"]] = relationship(
        "ContentPiece", back_populates="schedules"
    )
    posts: Mapped[List["Post"]] = relationship("Post", back_populates="schedule")

    __table_args__ = (
        Index("ix_schedules_active_next_run", "is_active", "next_run_at"),
    )


# ─── Post Analytics Model ─────────────────────────────────────────────────────

class PostAnalytics(Base):
    """
    Performance metrics for a published post, snapshotted at regular intervals.
    """
    __tablename__ = "post_analytics"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    post_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("posts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    # Core engagement metrics
    impressions: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    reach: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    likes: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    comments: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    shares: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    saves: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    clicks: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    profile_visits: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    new_followers: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)

    # Calculated metrics
    engagement_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    click_through_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    virality_coefficient: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Video-specific
    video_views: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    video_watch_time_seconds: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    video_completion_rate: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Platform-native extras
    platform_extras: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Snapshot metadata
    snapshot_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False, index=True
    )
    hours_since_publish: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)

    # Relationships
    post: Mapped["Post"] = relationship("Post", back_populates="analytics")

    __table_args__ = (
        Index("ix_post_analytics_post_snapshot", "post_id", "snapshot_at"),
    )
