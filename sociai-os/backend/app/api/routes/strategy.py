"""
Strategy management routes for SociAI OS.

Endpoints:
  POST   /strategy/upload              – Upload strategy document (PDF/DOCX/TXT)
  POST   /strategy/parse               – Trigger AI extraction from uploaded doc
  GET    /strategy/summary             – Get parsed strategy summary
  GET    /strategy/brand-guidelines    – Retrieve current brand guidelines
  PUT    /strategy/brand-guidelines    – Update brand guidelines
  GET    /strategy/content-pillars     – List content pillars
  POST   /strategy/content-pillars     – Create a content pillar
  PATCH  /strategy/content-pillars/{id}– Update a content pillar
  DELETE /strategy/content-pillars/{id}– Delete a content pillar
  GET    /strategy/audience-personas   – List target audience personas
  POST   /strategy/audience-personas   – Create a persona
  GET    /strategy/business-goals      – List business goals
  POST   /strategy/business-goals      – Create a goal
  GET    /strategy/competitors         – List tracked competitors
  POST   /strategy/competitors         – Add a competitor
  DELETE /strategy/competitors/{id}    – Remove a competitor
"""
from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, BackgroundTasks, Depends, File, HTTPException, UploadFile, status
from pydantic import BaseModel, Field, HttpUrl
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import get_current_active_user, get_db, get_redis, require_role

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

class BrandGuidelineUpdate(BaseModel):
    brand_name: Optional[str] = None
    tagline: Optional[str] = None
    brand_voice: Optional[str] = Field(default=None, description="Tone/voice descriptors e.g. 'professional, witty'")
    brand_values: Optional[List[str]] = None
    color_palette: Optional[Dict[str, str]] = None   # {"primary": "#FF5733", ...}
    typography: Optional[Dict[str, str]] = None
    logo_url: Optional[str] = None
    dos: Optional[List[str]] = None
    donts: Optional[List[str]] = None
    hashtag_sets: Optional[Dict[str, List[str]]] = None   # {"primary": [...], "niche": [...]}
    emoji_policy: Optional[str] = None
    language_preferences: Optional[List[str]] = None


class BrandGuidelineResponse(BaseModel):
    id: str
    brand_name: Optional[str]
    tagline: Optional[str]
    brand_voice: Optional[str]
    brand_values: List[str]
    color_palette: Dict[str, str]
    typography: Dict[str, str]
    logo_url: Optional[str]
    dos: List[str]
    donts: List[str]
    hashtag_sets: Dict[str, List[str]]
    emoji_policy: Optional[str]
    language_preferences: List[str]
    updated_at: str


class ContentPillarCreate(BaseModel):
    name: str = Field(..., max_length=128)
    description: Optional[str] = Field(default=None, max_length=1000)
    content_types: List[str] = Field(default_factory=list)
    posting_frequency: Optional[str] = None  # "3x/week", "daily", etc.
    target_platforms: List[str] = Field(default_factory=list)
    sample_topics: Optional[List[str]] = None
    color_code: Optional[str] = None   # hex for UI display
    weight_percentage: Optional[float] = Field(default=None, ge=0, le=100)


class ContentPillarResponse(BaseModel):
    id: str
    name: str
    description: Optional[str]
    content_types: List[str]
    posting_frequency: Optional[str]
    target_platforms: List[str]
    sample_topics: List[str]
    color_code: Optional[str]
    weight_percentage: Optional[float]
    post_count: int


class AudiencePersonaCreate(BaseModel):
    name: str = Field(..., max_length=128)
    description: Optional[str] = None
    age_range: Optional[str] = None
    gender: Optional[str] = None
    locations: Optional[List[str]] = None
    interests: Optional[List[str]] = None
    pain_points: Optional[List[str]] = None
    goals: Optional[List[str]] = None
    preferred_content_types: Optional[List[str]] = None
    preferred_platforms: Optional[List[str]] = None
    income_bracket: Optional[str] = None
    education_level: Optional[str] = None
    job_titles: Optional[List[str]] = None


class AudiencePersonaResponse(BaseModel):
    id: str
    name: str
    description: Optional[str]
    age_range: Optional[str]
    gender: Optional[str]
    locations: List[str]
    interests: List[str]
    pain_points: List[str]
    goals: List[str]
    preferred_content_types: List[str]
    preferred_platforms: List[str]


class BusinessGoalCreate(BaseModel):
    title: str = Field(..., max_length=256)
    description: Optional[str] = None
    metric: str = Field(..., description="Primary metric: followers, engagement, leads, revenue")
    target_value: float
    target_unit: str = Field(..., description="e.g. '%', 'count', 'USD'")
    target_date: Optional[str] = None   # ISO date string
    priority: str = Field(default="medium", pattern="^(low|medium|high|critical)$")


class BusinessGoalResponse(BaseModel):
    id: str
    title: str
    description: Optional[str]
    metric: str
    target_value: float
    target_unit: str
    current_value: Optional[float]
    progress_percentage: Optional[float]
    target_date: Optional[str]
    priority: str
    status: str


class CompetitorCreate(BaseModel):
    name: str = Field(..., max_length=256)
    website: Optional[str] = None
    platforms: Dict[str, str] = Field(
        default_factory=dict,
        description="Map of platform → handle/URL e.g. {'twitter': '@acme', 'linkedin': 'acme-corp'}"
    )
    notes: Optional[str] = None


class CompetitorResponse(BaseModel):
    id: str
    name: str
    website: Optional[str]
    platforms: Dict[str, str]
    notes: Optional[str]
    last_analyzed_at: Optional[str]


class StrategySummaryResponse(BaseModel):
    id: str
    document_name: str
    extracted_at: Optional[str]
    brand_guidelines_extracted: bool
    content_pillars_count: int
    audience_personas_count: int
    business_goals_count: int
    competitors_count: int
    key_messages: List[str]
    unique_value_proposition: Optional[str]
    competitive_advantage: Optional[str]
    content_themes: List[str]
    recommended_platforms: List[str]
    posting_cadence: Optional[str]
    ai_confidence_score: Optional[float]
    raw_extraction: Optional[Dict[str, Any]]


class ParseStrategyRequest(BaseModel):
    document_id: str
    extract_brand_guidelines: bool = True
    extract_content_pillars: bool = True
    extract_audience: bool = True
    extract_goals: bool = True
    overwrite_existing: bool = False


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.post(
    "/upload",
    status_code=status.HTTP_202_ACCEPTED,
    summary="Upload a strategy document for AI parsing",
)
async def upload_strategy_document(
    file: UploadFile = File(...),
    background_tasks: BackgroundTasks = None,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    # Validate file type
    allowed_types = {
        "application/pdf",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/msword",
        "text/plain",
        "text/markdown",
    }
    if file.content_type not in allowed_types:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported file type: {file.content_type}. Supported: PDF, DOCX, DOC, TXT, MD",
        )

    max_size_mb = 20
    content = await file.read()
    if len(content) > max_size_mb * 1024 * 1024:
        raise HTTPException(
            status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
            detail=f"File exceeds maximum size of {max_size_mb}MB",
        )

    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    doc_id = await svc.store_strategy_document(
        user_id=str(current_user.id),
        filename=file.filename,
        content=content,
        content_type=file.content_type,
    )
    return {
        "document_id": doc_id,
        "filename": file.filename,
        "size_bytes": len(content),
        "status": "uploaded",
        "message": "Document uploaded. Call /strategy/parse to trigger AI extraction.",
    }


@router.post(
    "/parse",
    status_code=status.HTTP_202_ACCEPTED,
    summary="Trigger AI extraction from an uploaded strategy document",
)
async def parse_strategy(
    payload: ParseStrategyRequest,
    background_tasks: BackgroundTasks,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    task_id = f"strategy_parse:{current_user.id}:{payload.document_id}"
    # Queue in Redis for agent pickup
    import json
    await redis.setex(
        f"task:{task_id}",
        3600,
        json.dumps({
            "type": "parse_strategy",
            "user_id": str(current_user.id),
            "document_id": payload.document_id,
            "options": payload.model_dump(),
            "status": "queued",
        }),
    )
    return {
        "task_id": task_id,
        "status": "queued",
        "message": "Strategy parsing queued. Track progress via /agents/tasks/{task_id}",
    }


@router.get(
    "/summary",
    response_model=StrategySummaryResponse,
    summary="Get the latest parsed strategy summary",
)
async def get_strategy_summary(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import MarketingStrategy
    result = await db.execute(
        select(MarketingStrategy)
        .where(MarketingStrategy.user_id == current_user.id)
        .order_by(MarketingStrategy.created_at.desc())
        .limit(1)
    )
    strategy = result.scalar_one_or_none()
    if not strategy:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="No strategy found. Upload and parse a strategy document first.",
        )
    return StrategySummaryResponse(
        id=str(strategy.id),
        document_name=getattr(strategy, "document_name", "strategy.pdf"),
        extracted_at=strategy.updated_at.isoformat() if getattr(strategy, "updated_at", None) else None,
        brand_guidelines_extracted=getattr(strategy, "brand_guidelines_extracted", False),
        content_pillars_count=getattr(strategy, "content_pillars_count", 0),
        audience_personas_count=getattr(strategy, "audience_personas_count", 0),
        business_goals_count=getattr(strategy, "business_goals_count", 0),
        competitors_count=getattr(strategy, "competitors_count", 0),
        key_messages=getattr(strategy, "key_messages", []) or [],
        unique_value_proposition=getattr(strategy, "unique_value_proposition", None),
        competitive_advantage=getattr(strategy, "competitive_advantage", None),
        content_themes=getattr(strategy, "content_themes", []) or [],
        recommended_platforms=getattr(strategy, "recommended_platforms", []) or [],
        posting_cadence=getattr(strategy, "posting_cadence", None),
        ai_confidence_score=getattr(strategy, "ai_confidence_score", None),
        raw_extraction=getattr(strategy, "raw_extraction", None),
    )


@router.get(
    "/brand-guidelines",
    response_model=BrandGuidelineResponse,
    summary="Get current brand guidelines",
)
async def get_brand_guidelines(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import BrandGuideline
    result = await db.execute(
        select(BrandGuideline)
        .where(BrandGuideline.user_id == current_user.id)
        .limit(1)
    )
    guidelines = result.scalar_one_or_none()
    if not guidelines:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="No brand guidelines configured yet.",
        )
    return _brand_guideline_to_response(guidelines)


@router.put(
    "/brand-guidelines",
    response_model=BrandGuidelineResponse,
    summary="Create or update brand guidelines",
)
async def update_brand_guidelines(
    payload: BrandGuidelineUpdate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import BrandGuideline
    from datetime import datetime, timezone

    result = await db.execute(
        select(BrandGuideline).where(BrandGuideline.user_id == current_user.id).limit(1)
    )
    guidelines = result.scalar_one_or_none()
    update_data = payload.model_dump(exclude_none=True)

    if guidelines is None:
        guidelines = BrandGuideline(user_id=current_user.id, **update_data)
        db.add(guidelines)
    else:
        for field, value in update_data.items():
            setattr(guidelines, field, value)
        guidelines.updated_at = datetime.now(timezone.utc)
        db.add(guidelines)

    await db.flush()
    return _brand_guideline_to_response(guidelines)


# ── Content Pillars ───────────────────────────────────────────────────────────

@router.get(
    "/content-pillars",
    response_model=List[ContentPillarResponse],
    summary="List all content pillars",
)
async def list_content_pillars(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import ContentPillar
    result = await db.execute(
        select(ContentPillar)
        .where(ContentPillar.user_id == current_user.id)
        .order_by(ContentPillar.created_at)
    )
    pillars = result.scalars().all()
    return [_pillar_to_response(p) for p in pillars]


@router.post(
    "/content-pillars",
    response_model=ContentPillarResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a new content pillar",
)
async def create_content_pillar(
    payload: ContentPillarCreate,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import ContentPillar
    pillar = ContentPillar(
        user_id=current_user.id,
        **payload.model_dump(),
    )
    db.add(pillar)
    await db.flush()
    return _pillar_to_response(pillar)


@router.patch(
    "/content-pillars/{pillar_id}",
    response_model=ContentPillarResponse,
    summary="Update a content pillar",
)
async def update_content_pillar(
    pillar_id: UUID,
    payload: ContentPillarCreate,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import ContentPillar
    result = await db.execute(
        select(ContentPillar).where(
            ContentPillar.id == pillar_id,
            ContentPillar.user_id == current_user.id,
        )
    )
    pillar = result.scalar_one_or_none()
    if not pillar:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Content pillar not found")
    for field, value in payload.model_dump(exclude_none=True).items():
        setattr(pillar, field, value)
    db.add(pillar)
    await db.flush()
    return _pillar_to_response(pillar)


@router.delete(
    "/content-pillars/{pillar_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Delete a content pillar",
)
async def delete_content_pillar(
    pillar_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import ContentPillar
    result = await db.execute(
        select(ContentPillar).where(
            ContentPillar.id == pillar_id,
            ContentPillar.user_id == current_user.id,
        )
    )
    pillar = result.scalar_one_or_none()
    if not pillar:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Content pillar not found")
    await db.delete(pillar)
    await db.flush()


# ── Audience Personas ─────────────────────────────────────────────────────────

@router.get("/audience-personas", response_model=List[AudiencePersonaResponse])
async def list_audience_personas(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import AudiencePersona
    result = await db.execute(
        select(AudiencePersona).where(AudiencePersona.user_id == current_user.id)
    )
    return [_persona_to_response(p) for p in result.scalars().all()]


@router.post("/audience-personas", response_model=AudiencePersonaResponse, status_code=status.HTTP_201_CREATED)
async def create_audience_persona(
    payload: AudiencePersonaCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import AudiencePersona
    persona = AudiencePersona(user_id=current_user.id, **payload.model_dump())
    db.add(persona)
    await db.flush()
    return _persona_to_response(persona)


# ── Business Goals ────────────────────────────────────────────────────────────

@router.get("/business-goals", response_model=List[BusinessGoalResponse])
async def list_business_goals(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import BusinessGoal
    result = await db.execute(
        select(BusinessGoal).where(BusinessGoal.user_id == current_user.id)
        .order_by(BusinessGoal.priority.desc())
    )
    return [_goal_to_response(g) for g in result.scalars().all()]


@router.post("/business-goals", response_model=BusinessGoalResponse, status_code=status.HTTP_201_CREATED)
async def create_business_goal(
    payload: BusinessGoalCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import BusinessGoal
    goal = BusinessGoal(user_id=current_user.id, **payload.model_dump())
    db.add(goal)
    await db.flush()
    return _goal_to_response(goal)


# ── Competitors ───────────────────────────────────────────────────────────────

@router.get("/competitors", response_model=List[CompetitorResponse])
async def list_competitors(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import CompetitorRef
    result = await db.execute(
        select(CompetitorRef).where(CompetitorRef.user_id == current_user.id)
    )
    return [_competitor_to_response(c) for c in result.scalars().all()]


@router.post("/competitors", response_model=CompetitorResponse, status_code=status.HTTP_201_CREATED)
async def add_competitor(
    payload: CompetitorCreate,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import CompetitorRef
    comp = CompetitorRef(user_id=current_user.id, **payload.model_dump())
    db.add(comp)
    await db.flush()
    return _competitor_to_response(comp)


@router.delete("/competitors/{competitor_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_competitor(
    competitor_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.strategy import CompetitorRef
    result = await db.execute(
        select(CompetitorRef).where(
            CompetitorRef.id == competitor_id,
            CompetitorRef.user_id == current_user.id,
        )
    )
    comp = result.scalar_one_or_none()
    if not comp:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Competitor not found")
    await db.delete(comp)
    await db.flush()


# ─── Serialization Helpers ────────────────────────────────────────────────────

def _brand_guideline_to_response(g) -> BrandGuidelineResponse:
    return BrandGuidelineResponse(
        id=str(g.id),
        brand_name=getattr(g, "brand_name", None),
        tagline=getattr(g, "tagline", None),
        brand_voice=getattr(g, "brand_voice", None),
        brand_values=getattr(g, "brand_values", None) or [],
        color_palette=getattr(g, "color_palette", None) or {},
        typography=getattr(g, "typography", None) or {},
        logo_url=getattr(g, "logo_url", None),
        dos=getattr(g, "dos", None) or [],
        donts=getattr(g, "donts", None) or [],
        hashtag_sets=getattr(g, "hashtag_sets", None) or {},
        emoji_policy=getattr(g, "emoji_policy", None),
        language_preferences=getattr(g, "language_preferences", None) or [],
        updated_at=g.updated_at.isoformat() if getattr(g, "updated_at", None) else "",
    )


def _pillar_to_response(p) -> ContentPillarResponse:
    return ContentPillarResponse(
        id=str(p.id),
        name=p.name,
        description=getattr(p, "description", None),
        content_types=getattr(p, "content_types", None) or [],
        posting_frequency=getattr(p, "posting_frequency", None),
        target_platforms=getattr(p, "target_platforms", None) or [],
        sample_topics=getattr(p, "sample_topics", None) or [],
        color_code=getattr(p, "color_code", None),
        weight_percentage=getattr(p, "weight_percentage", None),
        post_count=getattr(p, "post_count", 0) or 0,
    )


def _persona_to_response(p) -> AudiencePersonaResponse:
    return AudiencePersonaResponse(
        id=str(p.id),
        name=p.name,
        description=getattr(p, "description", None),
        age_range=getattr(p, "age_range", None),
        gender=getattr(p, "gender", None),
        locations=getattr(p, "locations", None) or [],
        interests=getattr(p, "interests", None) or [],
        pain_points=getattr(p, "pain_points", None) or [],
        goals=getattr(p, "goals", None) or [],
        preferred_content_types=getattr(p, "preferred_content_types", None) or [],
        preferred_platforms=getattr(p, "preferred_platforms", None) or [],
    )


def _goal_to_response(g) -> BusinessGoalResponse:
    target = getattr(g, "target_value", 0) or 0
    current = getattr(g, "current_value", None)
    progress = round((current / target * 100), 2) if current is not None and target > 0 else None
    return BusinessGoalResponse(
        id=str(g.id),
        title=g.title,
        description=getattr(g, "description", None),
        metric=g.metric,
        target_value=target,
        target_unit=getattr(g, "target_unit", ""),
        current_value=current,
        progress_percentage=progress,
        target_date=str(g.target_date) if getattr(g, "target_date", None) else None,
        priority=getattr(g, "priority", "medium"),
        status=getattr(g, "status", "active"),
    )


def _competitor_to_response(c) -> CompetitorResponse:
    return CompetitorResponse(
        id=str(c.id),
        name=c.name,
        website=getattr(c, "website", None),
        platforms=getattr(c, "platforms", None) or {},
        notes=getattr(c, "notes", None),
        last_analyzed_at=str(c.last_analyzed_at) if getattr(c, "last_analyzed_at", None) else None,
    )
