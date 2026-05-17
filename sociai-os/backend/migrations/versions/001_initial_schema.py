"""Initial schema - complete SociAI-OS database

Revision ID: 001
Revises:
Create Date: 2026-05-17 00:00:00.000000

"""
from typing import Sequence, Union
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

revision: str = "001"
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # ─── Users & Auth ──────────────────────────────────────────────────────────
    op.create_table(
        "users",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("email", sa.String(255), nullable=False, unique=True),
        sa.Column("username", sa.String(100), nullable=False, unique=True),
        sa.Column("full_name", sa.String(255), nullable=False),
        sa.Column("hashed_password", sa.String(255), nullable=False),
        sa.Column("avatar_url", sa.String(500)),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("is_verified", sa.Boolean, nullable=False, default=False),
        sa.Column("is_superuser", sa.Boolean, nullable=False, default=False),
        sa.Column("preferred_language", sa.String(10), default="en"),
        sa.Column("timezone", sa.String(50), default="UTC"),
        sa.Column("last_login_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), onupdate=sa.func.now()),
        sa.Column("deleted_at", sa.DateTime(timezone=True)),
    )

    op.create_table(
        "two_factor_auth",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id", ondelete="CASCADE"), nullable=False, unique=True),
        sa.Column("secret", sa.String(32), nullable=False),
        sa.Column("is_enabled", sa.Boolean, nullable=False, default=False),
        sa.Column("backup_codes", postgresql.JSONB, default=list),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    op.create_table(
        "user_sessions",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id", ondelete="CASCADE"), nullable=False),
        sa.Column("refresh_token_hash", sa.String(255), nullable=False),
        sa.Column("device_info", postgresql.JSONB, default=dict),
        sa.Column("ip_address", sa.String(50)),
        sa.Column("user_agent", sa.Text),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("last_used_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    op.create_table(
        "login_history",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id", ondelete="CASCADE"), nullable=False),
        sa.Column("ip_address", sa.String(50)),
        sa.Column("user_agent", sa.Text),
        sa.Column("location", postgresql.JSONB, default=dict),
        sa.Column("success", sa.Boolean, nullable=False),
        sa.Column("failure_reason", sa.String(255)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Brands & Teams ────────────────────────────────────────────────────────
    op.create_table(
        "brands",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("owner_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id"), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("slug", sa.String(100), nullable=False, unique=True),
        sa.Column("description", sa.Text),
        sa.Column("logo_url", sa.String(500)),
        sa.Column("website", sa.String(500)),
        sa.Column("industry", sa.String(100)),
        sa.Column("brand_colors", postgresql.JSONB, default=dict),
        sa.Column("typography", postgresql.JSONB, default=dict),
        sa.Column("settings", postgresql.JSONB, default=dict),
        sa.Column("subscription_tier", sa.String(50), default="starter"),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("deleted_at", sa.DateTime(timezone=True)),
    )

    op.create_table(
        "team_members",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id", ondelete="CASCADE"), nullable=False),
        sa.Column("role", sa.String(50), nullable=False, default="editor"),
        sa.Column("permissions", postgresql.JSONB, default=list),
        sa.Column("invited_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("invite_accepted_at", sa.DateTime(timezone=True)),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.UniqueConstraint("brand_id", "user_id", name="uq_team_member"),
    )

    # ─── Platform Accounts ─────────────────────────────────────────────────────
    op.create_table(
        "platform_accounts",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("platform", sa.String(50), nullable=False),
        sa.Column("account_id", sa.String(255), nullable=False),
        sa.Column("account_name", sa.String(255)),
        sa.Column("account_username", sa.String(255)),
        sa.Column("account_url", sa.String(500)),
        sa.Column("avatar_url", sa.String(500)),
        sa.Column("follower_count", sa.Integer, default=0),
        sa.Column("encrypted_credentials", sa.Text),
        sa.Column("access_token_encrypted", sa.Text),
        sa.Column("refresh_token_encrypted", sa.Text),
        sa.Column("token_expires_at", sa.DateTime(timezone=True)),
        sa.Column("scopes", postgresql.ARRAY(sa.String), default=list),
        sa.Column("extra_data", postgresql.JSONB, default=dict),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("last_synced_at", sa.DateTime(timezone=True)),
        sa.Column("connection_health", sa.String(20), default="unknown"),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Strategy & Brand Guidelines ──────────────────────────────────────────
    op.create_table(
        "marketing_strategies",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("version", sa.Integer, nullable=False, default=1),
        sa.Column("raw_document_url", sa.String(500)),
        sa.Column("extracted_data", postgresql.JSONB, default=dict),
        sa.Column("brand_tone", sa.String(100)),
        sa.Column("content_pillars", postgresql.JSONB, default=list),
        sa.Column("target_audience", postgresql.JSONB, default=dict),
        sa.Column("business_goals", postgresql.JSONB, default=list),
        sa.Column("competitors", postgresql.JSONB, default=list),
        sa.Column("monthly_objectives", postgresql.JSONB, default=list),
        sa.Column("campaign_timelines", postgresql.JSONB, default=dict),
        sa.Column("ai_summary", sa.Text),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("processed_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    op.create_table(
        "uploaded_documents",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("strategy_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("marketing_strategies.id")),
        sa.Column("document_type", sa.String(50), nullable=False),
        sa.Column("filename", sa.String(255), nullable=False),
        sa.Column("file_url", sa.String(500), nullable=False),
        sa.Column("file_size", sa.Integer),
        sa.Column("mime_type", sa.String(100)),
        sa.Column("extracted_text", sa.Text),
        sa.Column("processing_status", sa.String(50), default="pending"),
        sa.Column("uploaded_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Content & Posts ──────────────────────────────────────────────────────
    op.create_table(
        "campaigns",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("description", sa.Text),
        sa.Column("goal", sa.String(255)),
        sa.Column("target_platforms", postgresql.ARRAY(sa.String), default=list),
        sa.Column("budget", sa.Numeric(12, 2)),
        sa.Column("status", sa.String(50), default="draft"),
        sa.Column("start_date", sa.Date),
        sa.Column("end_date", sa.Date),
        sa.Column("target_reach", sa.Integer),
        sa.Column("target_conversions", sa.Integer),
        sa.Column("performance_data", postgresql.JSONB, default=dict),
        sa.Column("ai_brief", sa.Text),
        sa.Column("created_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("deleted_at", sa.DateTime(timezone=True)),
    )

    op.create_table(
        "content_pieces",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("campaign_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("campaigns.id")),
        sa.Column("title", sa.String(500)),
        sa.Column("content_type", sa.String(50), nullable=False),
        sa.Column("topic", sa.String(500)),
        sa.Column("content_pillar", sa.String(255)),
        sa.Column("writing_style", sa.String(50)),
        sa.Column("language", sa.String(20), default="english"),
        sa.Column("body_text", sa.Text),
        sa.Column("hook", sa.Text),
        sa.Column("cta", sa.Text),
        sa.Column("hashtags", postgresql.ARRAY(sa.String), default=list),
        sa.Column("mentions", postgresql.ARRAY(sa.String), default=list),
        sa.Column("media_urls", postgresql.JSONB, default=list),
        sa.Column("platform_variants", postgresql.JSONB, default=dict),
        sa.Column("ai_score", sa.Numeric(5, 2)),
        sa.Column("viral_prediction_score", sa.Numeric(5, 2)),
        sa.Column("status", sa.String(50), default="draft"),
        sa.Column("approval_status", sa.String(50), default="pending"),
        sa.Column("approved_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("approved_at", sa.DateTime(timezone=True)),
        sa.Column("rejection_reason", sa.Text),
        sa.Column("created_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("ai_generated", sa.Boolean, default=True),
        sa.Column("generation_metadata", postgresql.JSONB, default=dict),
        sa.Column("tags", postgresql.ARRAY(sa.String), default=list),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("deleted_at", sa.DateTime(timezone=True)),
    )

    op.create_table(
        "scheduled_posts",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("content_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("content_pieces.id", ondelete="CASCADE"), nullable=False),
        sa.Column("platform_account_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("platform_accounts.id"), nullable=False),
        sa.Column("platform", sa.String(50), nullable=False),
        sa.Column("platform_post_id", sa.String(255)),
        sa.Column("scheduled_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("published_at", sa.DateTime(timezone=True)),
        sa.Column("status", sa.String(50), default="scheduled"),
        sa.Column("error_message", sa.Text),
        sa.Column("retry_count", sa.Integer, default=0),
        sa.Column("platform_url", sa.String(500)),
        sa.Column("ab_test_group", sa.String(10)),
        sa.Column("is_recycled", sa.Boolean, default=False),
        sa.Column("celery_task_id", sa.String(255)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Analytics ────────────────────────────────────────────────────────────
    op.create_table(
        "post_analytics",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("scheduled_post_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("scheduled_posts.id"), nullable=False),
        sa.Column("platform", sa.String(50), nullable=False),
        sa.Column("impressions", sa.BigInteger, default=0),
        sa.Column("reach", sa.BigInteger, default=0),
        sa.Column("likes", sa.Integer, default=0),
        sa.Column("comments", sa.Integer, default=0),
        sa.Column("shares", sa.Integer, default=0),
        sa.Column("saves", sa.Integer, default=0),
        sa.Column("clicks", sa.Integer, default=0),
        sa.Column("video_views", sa.Integer, default=0),
        sa.Column("watch_time_seconds", sa.Integer, default=0),
        sa.Column("retention_rate", sa.Numeric(5, 2)),
        sa.Column("engagement_rate", sa.Numeric(7, 4)),
        sa.Column("ctr", sa.Numeric(7, 4)),
        sa.Column("conversions", sa.Integer, default=0),
        sa.Column("viral_score", sa.Numeric(5, 2)),
        sa.Column("sentiment_positive", sa.Numeric(5, 2)),
        sa.Column("sentiment_negative", sa.Numeric(5, 2)),
        sa.Column("sentiment_neutral", sa.Numeric(5, 2)),
        sa.Column("raw_data", postgresql.JSONB, default=dict),
        sa.Column("recorded_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("period", sa.String(20)),
    )

    op.create_table(
        "brand_analytics_snapshots",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("platform", sa.String(50)),
        sa.Column("date", sa.Date, nullable=False),
        sa.Column("total_followers", sa.Integer, default=0),
        sa.Column("new_followers", sa.Integer, default=0),
        sa.Column("total_reach", sa.BigInteger, default=0),
        sa.Column("total_impressions", sa.BigInteger, default=0),
        sa.Column("avg_engagement_rate", sa.Numeric(7, 4)),
        sa.Column("total_posts", sa.Integer, default=0),
        sa.Column("top_content_ids", postgresql.JSONB, default=list),
        sa.Column("ai_insights", sa.Text),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── AI Agents ────────────────────────────────────────────────────────────
    op.create_table(
        "agent_tasks",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("agent_type", sa.String(50), nullable=False),
        sa.Column("task_name", sa.String(255), nullable=False),
        sa.Column("input_data", postgresql.JSONB, default=dict),
        sa.Column("output_data", postgresql.JSONB, default=dict),
        sa.Column("status", sa.String(50), default="pending"),
        sa.Column("progress", sa.Integer, default=0),
        sa.Column("error_message", sa.Text),
        sa.Column("celery_task_id", sa.String(255)),
        sa.Column("tokens_used", sa.Integer, default=0),
        sa.Column("api_cost_usd", sa.Numeric(10, 6), default=0),
        sa.Column("duration_seconds", sa.Numeric(10, 2)),
        sa.Column("triggered_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("workflow_id", postgresql.UUID(as_uuid=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("completed_at", sa.DateTime(timezone=True)),
    )

    op.create_table(
        "agent_workflows",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("description", sa.Text),
        sa.Column("workflow_definition", postgresql.JSONB, nullable=False),
        sa.Column("trigger_type", sa.String(50)),
        sa.Column("trigger_config", postgresql.JSONB, default=dict),
        sa.Column("is_active", sa.Boolean, nullable=False, default=True),
        sa.Column("run_count", sa.Integer, default=0),
        sa.Column("last_run_at", sa.DateTime(timezone=True)),
        sa.Column("created_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Community Management ─────────────────────────────────────────────────
    op.create_table(
        "community_interactions",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("platform", sa.String(50), nullable=False),
        sa.Column("interaction_type", sa.String(50), nullable=False),
        sa.Column("platform_comment_id", sa.String(255)),
        sa.Column("author_id", sa.String(255)),
        sa.Column("author_name", sa.String(255)),
        sa.Column("message_text", sa.Text),
        sa.Column("sentiment", sa.String(20)),
        sa.Column("sentiment_score", sa.Numeric(5, 4)),
        sa.Column("is_spam", sa.Boolean, default=False),
        sa.Column("is_lead", sa.Boolean, default=False),
        sa.Column("needs_escalation", sa.Boolean, default=False),
        sa.Column("ai_suggested_reply", sa.Text),
        sa.Column("actual_reply", sa.Text),
        sa.Column("replied_at", sa.DateTime(timezone=True)),
        sa.Column("replied_by", sa.String(50), default="ai"),
        sa.Column("status", sa.String(50), default="pending"),
        sa.Column("detected_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Trend Hunter ────────────────────────────────────────────────────────
    op.create_table(
        "trend_opportunities",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("platform", sa.String(50), nullable=False),
        sa.Column("trend_type", sa.String(50)),
        sa.Column("trend_name", sa.String(500), nullable=False),
        sa.Column("description", sa.Text),
        sa.Column("relevance_score", sa.Numeric(5, 2)),
        sa.Column("virality_score", sa.Numeric(5, 2)),
        sa.Column("volume_estimate", sa.Integer),
        sa.Column("hashtags", postgresql.ARRAY(sa.String), default=list),
        sa.Column("example_content", postgresql.JSONB, default=list),
        sa.Column("ai_content_suggestion", sa.Text),
        sa.Column("industries", postgresql.ARRAY(sa.String), default=list),
        sa.Column("expires_at", sa.DateTime(timezone=True)),
        sa.Column("detected_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Brand Assets ────────────────────────────────────────────────────────
    op.create_table(
        "brand_assets",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id", ondelete="CASCADE"), nullable=False),
        sa.Column("asset_type", sa.String(50), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("file_url", sa.String(500), nullable=False),
        sa.Column("file_size", sa.Integer),
        sa.Column("mime_type", sa.String(100)),
        sa.Column("dimensions", postgresql.JSONB),
        sa.Column("tags", postgresql.ARRAY(sa.String), default=list),
        sa.Column("ai_description", sa.Text),
        sa.Column("usage_count", sa.Integer, default=0),
        sa.Column("uploaded_by", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id")),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # ─── Notifications ───────────────────────────────────────────────────────
    op.create_table(
        "notifications",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("uuid_generate_v4()")),
        sa.Column("user_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("users.id", ondelete="CASCADE"), nullable=False),
        sa.Column("brand_id", postgresql.UUID(as_uuid=True), sa.ForeignKey("brands.id")),
        sa.Column("type", sa.String(50), nullable=False),
        sa.Column("title", sa.String(255), nullable=False),
        sa.Column("message", sa.Text),
        sa.Column("data", postgresql.JSONB, default=dict),
        sa.Column("is_read", sa.Boolean, nullable=False, default=False),
        sa.Column("action_url", sa.String(500)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("read_at", sa.DateTime(timezone=True)),
    )

    # ─── Indexes ─────────────────────────────────────────────────────────────
    op.create_index("ix_users_email", "users", ["email"])
    op.create_index("ix_users_username", "users", ["username"])
    op.create_index("ix_brands_owner_id", "brands", ["owner_id"])
    op.create_index("ix_platform_accounts_brand_id", "platform_accounts", ["brand_id"])
    op.create_index("ix_content_pieces_brand_id", "content_pieces", ["brand_id"])
    op.create_index("ix_content_pieces_status", "content_pieces", ["status"])
    op.create_index("ix_scheduled_posts_scheduled_at", "scheduled_posts", ["scheduled_at"])
    op.create_index("ix_scheduled_posts_status", "scheduled_posts", ["status"])
    op.create_index("ix_agent_tasks_brand_id", "agent_tasks", ["brand_id"])
    op.create_index("ix_agent_tasks_status", "agent_tasks", ["status"])
    op.create_index("ix_notifications_user_id", "notifications", ["user_id"])
    op.create_index("ix_notifications_is_read", "notifications", ["is_read"])
    op.create_index("ix_trend_opportunities_platform", "trend_opportunities", ["platform"])
    op.create_index("ix_community_interactions_brand_id", "community_interactions", ["brand_id"])


def downgrade() -> None:
    tables = [
        "notifications", "brand_assets", "trend_opportunities",
        "community_interactions", "agent_workflows", "agent_tasks",
        "brand_analytics_snapshots", "post_analytics", "scheduled_posts",
        "content_pieces", "campaigns", "uploaded_documents",
        "marketing_strategies", "platform_accounts", "team_members",
        "brands", "login_history", "user_sessions", "two_factor_auth", "users",
    ]
    for table in tables:
        op.drop_table(table)
