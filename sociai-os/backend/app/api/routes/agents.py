"""
AI Agent orchestration routes for SociAI OS.

Endpoints:
  POST   /agents/tasks               – Trigger an agent task
  GET    /agents/tasks               – List all agent tasks (filterable)
  GET    /agents/tasks/{task_id}     – Get task status + result
  DELETE /agents/tasks/{task_id}     – Cancel a running task
  GET    /agents/workflows           – List available workflow templates
  POST   /agents/workflows           – Create a custom workflow
  GET    /agents/workflows/{id}      – Get workflow definition
  POST   /agents/workflows/{id}/run  – Execute a workflow
  GET    /agents/executions          – List workflow executions
  GET    /agents/executions/{id}     – Get execution detail + step logs
  POST   /agents/executions/{id}/cancel – Cancel a running execution
  GET    /agents/capabilities        – List all agent capabilities
  GET    /agents/logs/{task_id}      – Stream/get agent reasoning logs
"""
from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID, uuid4

from fastapi import APIRouter, Depends, HTTPException, Query, status
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import get_current_active_user, get_db, get_pagination, get_redis, PaginationParams, require_role

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class TriggerTaskRequest(BaseModel):
    agent_type: str = Field(
        ...,
        description=(
            "content_creator|trend_scout|engagement_manager|analytics_reporter|"
            "competitor_spy|scheduler|community_manager|brand_guardian|"
            "hashtag_researcher|campaign_strategist|crisis_manager|ab_tester"
        ),
    )
    task_name: str = Field(..., max_length=256)
    parameters: Dict[str, Any] = Field(default_factory=dict)
    priority: str = Field(default="normal", pattern="^(low|normal|high|urgent)$")
    depends_on: Optional[List[str]] = Field(default=None, description="List of task IDs that must complete first")
    callback_url: Optional[str] = None
    timeout_seconds: int = Field(default=300, ge=30, le=3600)


class WorkflowCreateRequest(BaseModel):
    name: str = Field(..., max_length=256)
    description: Optional[str] = None
    steps: List[Dict[str, Any]] = Field(
        ...,
        min_length=1,
        description="Ordered list of {agent_type, task_name, parameters, depends_on}",
    )
    trigger: str = Field(default="manual", description="manual|schedule|event")
    schedule_cron: Optional[str] = Field(default=None, description="Cron expression if trigger='schedule'")
    event_type: Optional[str] = Field(default=None, description="Event type if trigger='event'")
    is_public_template: bool = False


class WorkflowRunRequest(BaseModel):
    parameters: Optional[Dict[str, Any]] = None
    priority: str = Field(default="normal", pattern="^(low|normal|high|urgent)$")


class TaskResponse(BaseModel):
    id: str
    agent_type: str
    task_name: str
    status: str   # queued|running|completed|failed|cancelled
    priority: str
    parameters: Dict[str, Any]
    result: Optional[Dict[str, Any]]
    error_message: Optional[str]
    progress_pct: Optional[float]
    started_at: Optional[datetime]
    completed_at: Optional[datetime]
    created_at: datetime
    tokens_used: Optional[int]
    cost_usd: Optional[float]
    reasoning_summary: Optional[str]


class WorkflowResponse(BaseModel):
    id: str
    name: str
    description: Optional[str]
    steps: List[Dict[str, Any]]
    trigger: str
    schedule_cron: Optional[str]
    is_template: bool
    is_public_template: bool
    executions_count: int
    last_run_at: Optional[datetime]
    avg_duration_seconds: Optional[float]
    created_at: datetime


class WorkflowStepLog(BaseModel):
    step_index: int
    agent_type: str
    task_name: str
    status: str
    started_at: Optional[datetime]
    completed_at: Optional[datetime]
    duration_seconds: Optional[float]
    log_entries: List[Dict[str, Any]]
    result_preview: Optional[str]


class ExecutionResponse(BaseModel):
    id: str
    workflow_id: str
    workflow_name: str
    status: str
    priority: str
    parameters: Optional[Dict[str, Any]]
    started_at: Optional[datetime]
    completed_at: Optional[datetime]
    duration_seconds: Optional[float]
    steps: List[WorkflowStepLog]
    error_message: Optional[str]
    tokens_used: Optional[int]
    cost_usd: Optional[float]


class AgentCapability(BaseModel):
    agent_type: str
    name: str
    description: str
    input_schema: Dict[str, Any]
    output_schema: Dict[str, Any]
    avg_duration_seconds: float
    avg_cost_usd: float
    requires_platform: bool
    supports_batch: bool


# ─── Agent Capability Registry ────────────────────────────────────────────────

AGENT_CAPABILITIES: List[AgentCapability] = [
    AgentCapability(
        agent_type="content_creator",
        name="Content Creator",
        description="Generates platform-optimised content using brand guidelines and strategy",
        input_schema={"topic": "str", "platforms": "list[str]", "tone": "str?", "pillar_id": "str?"},
        output_schema={"variations": "list", "hashtags": "list", "platform_adaptations": "dict"},
        avg_duration_seconds=15.0,
        avg_cost_usd=0.025,
        requires_platform=False,
        supports_batch=True,
    ),
    AgentCapability(
        agent_type="trend_scout",
        name="Trend Scout",
        description="Identifies trending topics and opportunities across platforms and news feeds",
        input_schema={"platforms": "list[str]?", "industry": "str?", "keywords": "list[str]?"},
        output_schema={"trends": "list", "opportunities": "list", "hashtag_suggestions": "list"},
        avg_duration_seconds=30.0,
        avg_cost_usd=0.05,
        requires_platform=True,
        supports_batch=False,
    ),
    AgentCapability(
        agent_type="engagement_manager",
        name="Engagement Manager",
        description="Generates personalised replies to comments and DMs",
        input_schema={"post_id": "str?", "account_id": "str?", "batch_size": "int?"},
        output_schema={"replies": "list", "dm_drafts": "list", "escalations": "list"},
        avg_duration_seconds=45.0,
        avg_cost_usd=0.03,
        requires_platform=True,
        supports_batch=True,
    ),
    AgentCapability(
        agent_type="analytics_reporter",
        name="Analytics Reporter",
        description="Compiles and narrates analytics reports with actionable insights",
        input_schema={"period": "str", "platforms": "list[str]?", "report_type": "str?"},
        output_schema={"narrative": "str", "kpis": "dict", "recommendations": "list"},
        avg_duration_seconds=60.0,
        avg_cost_usd=0.04,
        requires_platform=False,
        supports_batch=False,
    ),
    AgentCapability(
        agent_type="competitor_spy",
        name="Competitor Spy",
        description="Tracks competitor activity, content strategy, and performance",
        input_schema={"competitor_ids": "list[str]", "platforms": "list[str]?"},
        output_schema={"competitor_reports": "list", "content_gaps": "list", "opportunities": "list"},
        avg_duration_seconds=120.0,
        avg_cost_usd=0.08,
        requires_platform=True,
        supports_batch=True,
    ),
    AgentCapability(
        agent_type="scheduler",
        name="Smart Scheduler",
        description="Calculates optimal posting times and auto-schedules content",
        input_schema={"post_ids": "list[str]?", "horizon_days": "int?"},
        output_schema={"schedule": "list", "rationale": "dict"},
        avg_duration_seconds=20.0,
        avg_cost_usd=0.01,
        requires_platform=False,
        supports_batch=True,
    ),
    AgentCapability(
        agent_type="hashtag_researcher",
        name="Hashtag Researcher",
        description="Discovers high-performing, niche, and trending hashtags",
        input_schema={"topic": "str", "platform": "str", "count": "int?"},
        output_schema={"primary": "list[str]", "niche": "list[str]", "trending": "list[str]", "banned": "list[str]"},
        avg_duration_seconds=25.0,
        avg_cost_usd=0.015,
        requires_platform=False,
        supports_batch=False,
    ),
    AgentCapability(
        agent_type="crisis_manager",
        name="Crisis Manager",
        description="Detects brand crises, escalates alerts, drafts crisis response content",
        input_schema={"threshold": "float?", "platforms": "list[str]?"},
        output_schema={"alerts": "list", "severity": "str", "response_drafts": "list"},
        avg_duration_seconds=30.0,
        avg_cost_usd=0.03,
        requires_platform=True,
        supports_batch=False,
    ),
]


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.get(
    "/capabilities",
    response_model=List[AgentCapability],
    summary="List all available agent capabilities",
)
async def list_capabilities():
    return AGENT_CAPABILITIES


@router.post(
    "/tasks",
    response_model=TaskResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Trigger an AI agent task",
)
async def trigger_task(
    payload: TriggerTaskRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    known_agents = {c.agent_type for c in AGENT_CAPABILITIES}
    if payload.agent_type not in known_agents:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unknown agent type: '{payload.agent_type}'. Known: {sorted(known_agents)}",
        )

    task_id = str(uuid4())
    task_data = {
        "id": task_id,
        "user_id": str(current_user.id),
        "agent_type": payload.agent_type,
        "task_name": payload.task_name,
        "parameters": payload.parameters,
        "priority": payload.priority,
        "depends_on": payload.depends_on or [],
        "callback_url": payload.callback_url,
        "timeout_seconds": payload.timeout_seconds,
        "status": "queued",
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    await redis.setex(f"agent_task:{task_id}", 86400, json.dumps(task_data))

    # Enqueue to the appropriate priority queue
    queue_key = f"agent_queue:{payload.priority}"
    await redis.lpush(queue_key, task_id)

    # Store in DB via AgentTask model
    from app.models.agent import AgentTask
    task_record = AgentTask(
        id=task_id,
        user_id=current_user.id,
        agent_type=payload.agent_type,
        task_name=payload.task_name,
        parameters=payload.parameters,
        priority=payload.priority,
        status="queued",
    )
    db.add(task_record)
    await db.flush()

    return _task_to_response(task_record)


@router.get(
    "/tasks",
    summary="List agent tasks",
)
async def list_tasks(
    agent_type: Optional[str] = Query(default=None),
    status_filter: Optional[str] = Query(default=None, alias="status"),
    priority: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import AgentTask
    from sqlalchemy import and_

    filters = [AgentTask.user_id == current_user.id]
    if agent_type:
        filters.append(AgentTask.agent_type == agent_type)
    if status_filter:
        filters.append(AgentTask.status == status_filter)
    if priority:
        filters.append(AgentTask.priority == priority)

    result = await db.execute(
        select(AgentTask)
        .where(and_(*filters))
        .order_by(AgentTask.created_at.desc())
        .offset(pagination.offset)
        .limit(pagination.limit)
    )
    tasks = result.scalars().all()
    return {
        "items": [_task_to_response(t) for t in tasks],
        "page": pagination.page,
        "page_size": pagination.page_size,
    }


@router.get(
    "/tasks/{task_id}",
    response_model=TaskResponse,
    summary="Get the status and result of an agent task",
)
async def get_task(
    task_id: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    # Try Redis cache first (for running tasks)
    cached = await redis.get(f"agent_task:{task_id}")
    if cached:
        data = json.loads(cached)
        if data.get("user_id") != str(current_user.id):
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Not authorised")
        return TaskResponse(
            id=data["id"],
            agent_type=data["agent_type"],
            task_name=data["task_name"],
            status=data["status"],
            priority=data.get("priority", "normal"),
            parameters=data.get("parameters", {}),
            result=data.get("result"),
            error_message=data.get("error_message"),
            progress_pct=data.get("progress_pct"),
            started_at=datetime.fromisoformat(data["started_at"]) if data.get("started_at") else None,
            completed_at=datetime.fromisoformat(data["completed_at"]) if data.get("completed_at") else None,
            created_at=datetime.fromisoformat(data["created_at"]),
            tokens_used=data.get("tokens_used"),
            cost_usd=data.get("cost_usd"),
            reasoning_summary=data.get("reasoning_summary"),
        )

    # Fall back to DB
    from app.models.agent import AgentTask
    result = await db.execute(
        select(AgentTask).where(
            AgentTask.id == task_id,
            AgentTask.user_id == current_user.id,
        )
    )
    task = result.scalar_one_or_none()
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    return _task_to_response(task)


@router.delete(
    "/tasks/{task_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Cancel a queued or running agent task",
)
async def cancel_task(
    task_id: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.agent import AgentTask

    result = await db.execute(
        select(AgentTask).where(
            AgentTask.id == task_id,
            AgentTask.user_id == current_user.id,
        )
    )
    task = result.scalar_one_or_none()
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    if getattr(task, "status", "") in ("completed", "failed", "cancelled"):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Cannot cancel a task with status '{task.status}'",
        )
    task.status = "cancelled"
    task.completed_at = datetime.now(timezone.utc)
    db.add(task)
    await redis.publish(f"cancel_task:{task_id}", "1")
    await db.flush()


# ── Workflows ─────────────────────────────────────────────────────────────────

@router.get(
    "/workflows",
    response_model=List[WorkflowResponse],
    summary="List workflow templates and custom workflows",
)
async def list_workflows(
    include_templates: bool = Query(default=True),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import AgentWorkflow
    from sqlalchemy import or_

    result = await db.execute(
        select(AgentWorkflow)
        .where(
            or_(
                AgentWorkflow.user_id == current_user.id,
                AgentWorkflow.is_public_template == True,
            )
            if include_templates
            else AgentWorkflow.user_id == current_user.id
        )
        .order_by(AgentWorkflow.created_at.desc())
    )
    workflows = result.scalars().all()
    return [_workflow_to_response(w) for w in workflows]


@router.post(
    "/workflows",
    response_model=WorkflowResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a custom workflow",
)
async def create_workflow(
    payload: WorkflowCreateRequest,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import AgentWorkflow
    workflow = AgentWorkflow(
        user_id=current_user.id,
        name=payload.name,
        description=payload.description,
        steps=payload.steps,
        trigger=payload.trigger,
        schedule_cron=payload.schedule_cron,
        event_type=payload.event_type,
        is_public_template=payload.is_public_template,
    )
    db.add(workflow)
    await db.flush()
    return _workflow_to_response(workflow)


@router.get(
    "/workflows/{workflow_id}",
    response_model=WorkflowResponse,
    summary="Get a workflow definition",
)
async def get_workflow(
    workflow_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import AgentWorkflow
    from sqlalchemy import or_

    result = await db.execute(
        select(AgentWorkflow).where(
            AgentWorkflow.id == workflow_id,
            or_(
                AgentWorkflow.user_id == current_user.id,
                AgentWorkflow.is_public_template == True,
            ),
        )
    )
    workflow = result.scalar_one_or_none()
    if not workflow:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Workflow not found")
    return _workflow_to_response(workflow)


@router.post(
    "/workflows/{workflow_id}/run",
    response_model=ExecutionResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Execute a workflow",
)
async def run_workflow(
    workflow_id: UUID,
    payload: WorkflowRunRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.agent import AgentWorkflow, WorkflowExecution

    result = await db.execute(
        select(AgentWorkflow).where(AgentWorkflow.id == workflow_id)
    )
    workflow = result.scalar_one_or_none()
    if not workflow:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Workflow not found")

    execution_id = str(uuid4())
    execution = WorkflowExecution(
        id=execution_id,
        workflow_id=str(workflow_id),
        user_id=current_user.id,
        status="queued",
        priority=payload.priority,
        parameters=payload.parameters,
    )
    db.add(execution)
    await redis.lpush(f"workflow_queue:{payload.priority}", json.dumps({
        "execution_id": execution_id,
        "workflow_id": str(workflow_id),
        "user_id": str(current_user.id),
        "parameters": payload.parameters,
    }))
    await db.flush()
    return _execution_to_response(execution, workflow)


@router.get(
    "/executions",
    summary="List workflow executions",
)
async def list_executions(
    workflow_id: Optional[str] = Query(default=None),
    status_filter: Optional[str] = Query(default=None, alias="status"),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import WorkflowExecution
    from sqlalchemy import and_

    filters = [WorkflowExecution.user_id == current_user.id]
    if workflow_id:
        filters.append(WorkflowExecution.workflow_id == workflow_id)
    if status_filter:
        filters.append(WorkflowExecution.status == status_filter)

    result = await db.execute(
        select(WorkflowExecution)
        .where(and_(*filters))
        .order_by(WorkflowExecution.created_at.desc())
        .offset(pagination.offset)
        .limit(pagination.limit)
    )
    executions = result.scalars().all()
    return {"items": [_execution_to_response(e) for e in executions]}


@router.get(
    "/executions/{execution_id}",
    response_model=ExecutionResponse,
    summary="Get execution details and step logs",
)
async def get_execution(
    execution_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import WorkflowExecution, AgentWorkflow

    result = await db.execute(
        select(WorkflowExecution).where(
            WorkflowExecution.id == execution_id,
            WorkflowExecution.user_id == current_user.id,
        )
    )
    execution = result.scalar_one_or_none()
    if not execution:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Execution not found")

    wf_result = await db.execute(
        select(AgentWorkflow).where(AgentWorkflow.id == execution.workflow_id)
    )
    workflow = wf_result.scalar_one_or_none()
    return _execution_to_response(execution, workflow)


@router.post(
    "/executions/{execution_id}/cancel",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Cancel a running workflow execution",
)
async def cancel_execution(
    execution_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.agent import WorkflowExecution

    result = await db.execute(
        select(WorkflowExecution).where(
            WorkflowExecution.id == execution_id,
            WorkflowExecution.user_id == current_user.id,
        )
    )
    execution = result.scalar_one_or_none()
    if not execution:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Execution not found")
    if getattr(execution, "status", "") in ("completed", "failed", "cancelled"):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Cannot cancel execution with status '{execution.status}'",
        )
    execution.status = "cancelled"
    execution.completed_at = datetime.now(timezone.utc)
    db.add(execution)
    await redis.publish(f"cancel_execution:{execution_id}", "1")
    await db.flush()


@router.get(
    "/logs/{task_id}",
    summary="Get reasoning and execution logs for an agent task",
)
async def get_task_logs(
    task_id: str,
    limit: int = Query(default=100, ge=10, le=500),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.agent import AgentLog

    result = await db.execute(
        select(AgentLog)
        .where(AgentLog.task_id == task_id)
        .order_by(AgentLog.created_at.asc())
        .limit(limit)
    )
    logs = result.scalars().all()
    return {
        "task_id": task_id,
        "logs": [
            {
                "id": str(log.id),
                "level": getattr(log, "level", "info"),
                "message": getattr(log, "message", ""),
                "metadata": getattr(log, "metadata", {}),
                "created_at": log.created_at.isoformat() if log.created_at else None,
            }
            for log in logs
        ],
        "count": len(logs),
    }


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _task_to_response(task) -> TaskResponse:
    return TaskResponse(
        id=str(task.id),
        agent_type=getattr(task, "agent_type", ""),
        task_name=getattr(task, "task_name", ""),
        status=getattr(task, "status", "queued"),
        priority=getattr(task, "priority", "normal"),
        parameters=getattr(task, "parameters", None) or {},
        result=getattr(task, "result", None),
        error_message=getattr(task, "error_message", None),
        progress_pct=getattr(task, "progress_pct", None),
        started_at=getattr(task, "started_at", None),
        completed_at=getattr(task, "completed_at", None),
        created_at=task.created_at,
        tokens_used=getattr(task, "tokens_used", None),
        cost_usd=getattr(task, "cost_usd", None),
        reasoning_summary=getattr(task, "reasoning_summary", None),
    )


def _workflow_to_response(w, extra=None) -> WorkflowResponse:
    return WorkflowResponse(
        id=str(w.id),
        name=w.name,
        description=getattr(w, "description", None),
        steps=getattr(w, "steps", None) or [],
        trigger=getattr(w, "trigger", "manual"),
        schedule_cron=getattr(w, "schedule_cron", None),
        is_template=getattr(w, "is_template", False),
        is_public_template=getattr(w, "is_public_template", False),
        executions_count=getattr(w, "executions_count", 0) or 0,
        last_run_at=getattr(w, "last_run_at", None),
        avg_duration_seconds=getattr(w, "avg_duration_seconds", None),
        created_at=w.created_at,
    )


def _execution_to_response(e, workflow=None) -> ExecutionResponse:
    steps_data = getattr(e, "step_logs", None) or []
    step_logs = [
        WorkflowStepLog(
            step_index=s.get("step_index", i),
            agent_type=s.get("agent_type", ""),
            task_name=s.get("task_name", ""),
            status=s.get("status", "pending"),
            started_at=datetime.fromisoformat(s["started_at"]) if s.get("started_at") else None,
            completed_at=datetime.fromisoformat(s["completed_at"]) if s.get("completed_at") else None,
            duration_seconds=s.get("duration_seconds"),
            log_entries=s.get("log_entries", []),
            result_preview=s.get("result_preview"),
        )
        for i, s in enumerate(steps_data)
    ]
    started = getattr(e, "started_at", None)
    completed = getattr(e, "completed_at", None)
    duration = None
    if started and completed:
        duration = (completed - started).total_seconds()

    return ExecutionResponse(
        id=str(e.id),
        workflow_id=str(getattr(e, "workflow_id", "")),
        workflow_name=workflow.name if workflow else "Unknown",
        status=getattr(e, "status", "queued"),
        priority=getattr(e, "priority", "normal"),
        parameters=getattr(e, "parameters", None),
        started_at=started,
        completed_at=completed,
        duration_seconds=duration,
        steps=step_logs,
        error_message=getattr(e, "error_message", None),
        tokens_used=getattr(e, "tokens_used", None),
        cost_usd=getattr(e, "cost_usd", None),
    )
