"""
app/models/agent.py
───────────────────
AgentTask, AgentWorkflow, AgentResult, WorkflowStep models.

The AI agent layer orchestrates multi-step workflows (e.g., "Generate + Schedule
30 days of content for LinkedIn") using LLM chains and tool calls.
"""
from __future__ import annotations

import enum
import uuid
from datetime import datetime
from typing import TYPE_CHECKING, List, Optional

from sqlalchemy import (
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


# ─── Enums ────────────────────────────────────────────────────────────────────

class AgentType(str, enum.Enum):
    STRATEGY_GENERATOR = "strategy_generator"
    CONTENT_CREATOR = "content_creator"
    SCHEDULER = "scheduler"
    ANALYTICS_ANALYST = "analytics_analyst"
    TREND_SCOUT = "trend_scout"
    COMMUNITY_MANAGER = "community_manager"
    COMPETITOR_ANALYST = "competitor_analyst"
    CAPTION_WRITER = "caption_writer"
    HASHTAG_OPTIMIZER = "hashtag_optimizer"
    IMAGE_PROMPTER = "image_prompter"
    VIRAL_SCORER = "viral_scorer"
    CAMPAIGN_PLANNER = "campaign_planner"


class TaskStatus(str, enum.Enum):
    PENDING = "pending"
    QUEUED = "queued"
    RUNNING = "running"
    PAUSED = "paused"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"
    RETRYING = "retrying"


class WorkflowStatus(str, enum.Enum):
    DRAFT = "draft"
    ACTIVE = "active"
    RUNNING = "running"
    PAUSED = "paused"
    COMPLETED = "completed"
    FAILED = "failed"
    ARCHIVED = "archived"


class StepStatus(str, enum.Enum):
    PENDING = "pending"
    RUNNING = "running"
    COMPLETED = "completed"
    FAILED = "failed"
    SKIPPED = "skipped"


class StepType(str, enum.Enum):
    LLM_CALL = "llm_call"
    TOOL_CALL = "tool_call"
    HUMAN_APPROVAL = "human_approval"
    CONDITION = "condition"
    PARALLEL_FORK = "parallel_fork"
    PARALLEL_JOIN = "parallel_join"
    API_CALL = "api_call"
    DATA_TRANSFORM = "data_transform"
    NOTIFICATION = "notification"
    DELAY = "delay"


class LLMProvider(str, enum.Enum):
    OPENAI = "openai"
    ANTHROPIC = "anthropic"
    GOOGLE = "google"
    MISTRAL = "mistral"
    GROQ = "groq"
    LOCAL = "local"


# ─── Agent Workflow Model ─────────────────────────────────────────────────────

class AgentWorkflow(Base):
    """
    A reusable workflow template that defines a multi-step AI agent pipeline.
    Can be triggered manually, on a schedule, or by system events.
    """
    __tablename__ = "agent_workflows"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    agent_type: Mapped[AgentType] = mapped_column(Enum(AgentType), nullable=False, index=True)
    status: Mapped[WorkflowStatus] = mapped_column(
        Enum(WorkflowStatus), default=WorkflowStatus.DRAFT, nullable=False, index=True
    )

    # Workflow definition (DAG of steps)
    step_definitions: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)
    # [{"id": "step_1", "type": "llm_call", "depends_on": [], "config": {...}}, ...]

    # Default input schema
    input_schema: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    default_inputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Configuration
    llm_provider: Mapped[LLMProvider] = mapped_column(
        Enum(LLMProvider), default=LLMProvider.ANTHROPIC, nullable=False
    )
    llm_model: Mapped[str] = mapped_column(String(100), default="claude-sonnet-4-6", nullable=False)
    max_tokens: Mapped[int] = mapped_column(Integer, default=4096, nullable=False)
    temperature: Mapped[float] = mapped_column(Float, default=0.7, nullable=False)
    system_prompt: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    tools_enabled: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True, default=list)

    # Execution settings
    max_retries: Mapped[int] = mapped_column(Integer, default=3, nullable=False)
    timeout_seconds: Mapped[int] = mapped_column(Integer, default=300, nullable=False)
    requires_approval: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    is_scheduled: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    schedule_cron: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    schedule_timezone: Mapped[str] = mapped_column(String(50), default="UTC", nullable=False)

    # Usage statistics
    run_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    success_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    failure_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    avg_duration_seconds: Mapped[Optional[float]] = mapped_column(Float, nullable=True)
    last_run_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    next_run_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Versioning
    version: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    is_template: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    parent_template_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("agent_workflows.id", ondelete="SET NULL"), nullable=True
    )

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship("User")
    tasks: Mapped[List["AgentTask"]] = relationship(
        "AgentTask", back_populates="workflow", cascade="all, delete-orphan"
    )
    parent_template: Mapped[Optional["AgentWorkflow"]] = relationship(
        "AgentWorkflow", remote_side="AgentWorkflow.id", foreign_keys=[parent_template_id]
    )

    __table_args__ = (
        Index("ix_agent_workflows_user_type", "user_id", "agent_type"),
        Index("ix_agent_workflows_status", "status"),
        Index("ix_agent_workflows_template", "is_template"),
    )


# ─── Agent Task Model ─────────────────────────────────────────────────────────

class AgentTask(Base):
    """
    A single execution run of an AgentWorkflow.
    Tracks inputs, outputs, status, token usage, and cost.
    """
    __tablename__ = "agent_tasks"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    workflow_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("agent_workflows.id", ondelete="SET NULL"),
        nullable=True,
        index=True,
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    created_by_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )

    agent_type: Mapped[AgentType] = mapped_column(Enum(AgentType), nullable=False, index=True)
    status: Mapped[TaskStatus] = mapped_column(
        Enum(TaskStatus), default=TaskStatus.PENDING, nullable=False, index=True
    )
    priority: Mapped[int] = mapped_column(Integer, default=5, nullable=False)  # 1 (highest) – 10 (lowest)

    # Task definition
    title: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    inputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    context: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Execution results
    outputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    error_message: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    error_traceback: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Token & cost tracking
    input_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    output_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    total_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    estimated_cost_usd: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Timing
    queued_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    started_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    completed_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    duration_seconds: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Retry tracking
    retry_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    max_retries: Mapped[int] = mapped_column(Integer, default=3, nullable=False)
    next_retry_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Celery task
    celery_task_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True, index=True)

    # Approval workflow
    requires_approval: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    approved_by_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    approved_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    rejection_reason: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    workflow: Mapped[Optional["AgentWorkflow"]] = relationship(
        "AgentWorkflow", back_populates="tasks"
    )
    created_by: Mapped["User"] = relationship(
        "User", foreign_keys=[created_by_id], back_populates="agent_tasks"
    )
    user: Mapped["User"] = relationship("User", foreign_keys=[user_id])
    results: Mapped[List["AgentResult"]] = relationship(
        "AgentResult", back_populates="task", cascade="all, delete-orphan"
    )
    steps: Mapped[List["WorkflowStep"]] = relationship(
        "WorkflowStep", back_populates="task", cascade="all, delete-orphan"
    )

    __table_args__ = (
        Index("ix_agent_tasks_user_status", "user_id", "status"),
        Index("ix_agent_tasks_workflow_status", "workflow_id", "status"),
        Index("ix_agent_tasks_celery", "celery_task_id"),
        Index("ix_agent_tasks_created", "created_at"),
    )

    @property
    def is_terminal(self) -> bool:
        return self.status in (
            TaskStatus.COMPLETED,
            TaskStatus.FAILED,
            TaskStatus.CANCELLED,
        )


# ─── Agent Result Model ───────────────────────────────────────────────────────

class AgentResult(Base):
    """
    Individual structured outputs produced by an agent task.
    A single task can produce multiple results (e.g., 10 captions, 5 content ideas).
    """
    __tablename__ = "agent_results"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    task_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("agent_tasks.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )

    result_type: Mapped[str] = mapped_column(String(100), nullable=False)
    # e.g., "caption", "strategy", "content_idea", "hashtag_set", "analytics_insight"
    # "image_prompt", "schedule_plan", "trend_report", "competitor_analysis"

    title: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    content: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    structured_data: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    # Rich structured output (e.g., a full content plan, a SWOT analysis dict)

    # User feedback / action taken
    is_accepted: Mapped[Optional[bool]] = mapped_column(Boolean, nullable=True)
    is_edited: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    user_rating: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)  # 1–5 stars
    user_notes: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    applied_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Reference to any entities created from this result
    created_content_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), nullable=True
    )  # FK to content_pieces if agent created a content piece

    sort_order: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    quality_score: Mapped[Optional[float]] = mapped_column(Float, nullable=True)  # 0–100

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    task: Mapped["AgentTask"] = relationship("AgentTask", back_populates="results")

    __table_args__ = (
        Index("ix_agent_results_task_type", "task_id", "result_type"),
        Index("ix_agent_results_user", "user_id"),
    )


# ─── Workflow Step Model ──────────────────────────────────────────────────────

class WorkflowStep(Base):
    """
    An individual step within an AgentTask execution.
    Provides granular tracing of multi-step agent pipelines.
    """
    __tablename__ = "workflow_steps"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    task_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("agent_tasks.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    parent_step_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("workflow_steps.id", ondelete="SET NULL"), nullable=True
    )

    step_key: Mapped[str] = mapped_column(String(100), nullable=False)  # matches step_definitions[].id
    step_name: Mapped[str] = mapped_column(String(255), nullable=False)
    step_type: Mapped[StepType] = mapped_column(Enum(StepType), nullable=False)
    status: Mapped[StepStatus] = mapped_column(
        Enum(StepStatus), default=StepStatus.PENDING, nullable=False, index=True
    )
    sort_order: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

    # Inputs / outputs for this step
    inputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    outputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    error_message: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # LLM-specific
    llm_provider: Mapped[Optional[LLMProvider]] = mapped_column(Enum(LLMProvider), nullable=True)
    llm_model: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    system_prompt: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    user_prompt: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    raw_response: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    input_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    output_tokens: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    estimated_cost_usd: Mapped[Optional[float]] = mapped_column(Float, nullable=True)

    # Tool call specifics
    tool_name: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    tool_inputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    tool_outputs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    # Timing
    started_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    completed_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    duration_ms: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)

    # Retry
    attempt_number: Mapped[int] = mapped_column(Integer, default=1, nullable=False)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    # Relationships
    task: Mapped["AgentTask"] = relationship("AgentTask", back_populates="steps")
    parent_step: Mapped[Optional["WorkflowStep"]] = relationship(
        "WorkflowStep", remote_side="WorkflowStep.id", foreign_keys=[parent_step_id]
    )

    __table_args__ = (
        Index("ix_workflow_steps_task_order", "task_id", "sort_order"),
        Index("ix_workflow_steps_status", "status"),
    )
