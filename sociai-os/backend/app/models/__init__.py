"""
app/models/__init__.py
──────────────────────
Re-exports all ORM models so that:
  - Alembic autogenerate can discover them (import app.models before running migrations)
  - Application code can do `from app.models import User, Post, ...`
"""
from app.models.user import (
    User,
    Role,
    Permission,
    TeamMember,
    UserSession,
    LoginHistory,
    TwoFactorAuth,
    UserStatus,
    SubscriptionTier,
    TeamRole,
    LoginMethod,
)

from app.models.platform import (
    PlatformAccount,
    PlatformCredential,
    OAuthToken,
    SocialAccount,
    PlatformType,
    AccountType,
    ConnectionStatus,
    OAuthGrantType,
)

from app.models.content import (
    ContentPiece,
    Post,
    Caption,
    Campaign,
    ContentPillar,
    Schedule,
    PostAnalytics,
    ContentType,
    ContentStatus,
    PostStatus,
    CampaignStatus,
    ScheduleFrequency,
    AIGenerationModel,
)

from app.models.strategy import (
    MarketingStrategy,
    BrandGuideline,
    ContentPlan,
    TargetAudience,
    BusinessGoal,
    CompetitorRef,
    StrategyStatus,
    GoalType,
    GoalStatus,
    ContentPlanStatus,
)

from app.models.analytics import (
    AnalyticsReport,
    MetricSnapshot,
    ViralScore,
    SentimentAnalysis,
    TrendOpportunity,
    ReportType,
    ReportStatus,
    MetricGranularity,
    SentimentLabel,
    TrendCategory,
    TrendStatus,
)

from app.models.agent import (
    AgentTask,
    AgentWorkflow,
    AgentResult,
    WorkflowStep,
    AgentType,
    TaskStatus,
    WorkflowStatus,
    StepStatus,
    StepType,
    LLMProvider,
)

__all__ = [
    # User domain
    "User",
    "Role",
    "Permission",
    "TeamMember",
    "UserSession",
    "LoginHistory",
    "TwoFactorAuth",
    "UserStatus",
    "SubscriptionTier",
    "TeamRole",
    "LoginMethod",
    # Platform domain
    "PlatformAccount",
    "PlatformCredential",
    "OAuthToken",
    "SocialAccount",
    "PlatformType",
    "AccountType",
    "ConnectionStatus",
    "OAuthGrantType",
    # Content domain
    "ContentPiece",
    "Post",
    "Caption",
    "Campaign",
    "ContentPillar",
    "Schedule",
    "PostAnalytics",
    "ContentType",
    "ContentStatus",
    "PostStatus",
    "CampaignStatus",
    "ScheduleFrequency",
    "AIGenerationModel",
    # Strategy domain
    "MarketingStrategy",
    "BrandGuideline",
    "ContentPlan",
    "TargetAudience",
    "BusinessGoal",
    "CompetitorRef",
    "StrategyStatus",
    "GoalType",
    "GoalStatus",
    "ContentPlanStatus",
    # Analytics domain
    "AnalyticsReport",
    "MetricSnapshot",
    "ViralScore",
    "SentimentAnalysis",
    "TrendOpportunity",
    "ReportType",
    "ReportStatus",
    "MetricGranularity",
    "SentimentLabel",
    "TrendCategory",
    "TrendStatus",
    # Agent domain
    "AgentTask",
    "AgentWorkflow",
    "AgentResult",
    "WorkflowStep",
    "AgentType",
    "TaskStatus",
    "WorkflowStatus",
    "StepStatus",
    "StepType",
    "LLMProvider",
]
