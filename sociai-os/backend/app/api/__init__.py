"""
SociAI OS – API package.

Exports the main v1 router, which aggregates every sub-router.
"""
from __future__ import annotations

from fastapi import APIRouter

from app.api.routes import (
    agents,
    analytics,
    auth,
    campaigns,
    community,
    content,
    platforms,
    strategy,
    team,
    trends,
    websocket,
)
from app.core.config import settings

api_router = APIRouter(prefix=settings.API_V1_PREFIX)

api_router.include_router(auth.router,       prefix="/auth",       tags=["Auth"])
api_router.include_router(platforms.router,  prefix="/platforms",  tags=["Platforms"])
api_router.include_router(strategy.router,   prefix="/strategy",   tags=["Strategy"])
api_router.include_router(content.router,    prefix="/content",    tags=["Content"])
api_router.include_router(analytics.router,  prefix="/analytics",  tags=["Analytics"])
api_router.include_router(agents.router,     prefix="/agents",     tags=["Agents"])
api_router.include_router(campaigns.router,  prefix="/campaigns",  tags=["Campaigns"])
api_router.include_router(community.router,  prefix="/community",  tags=["Community"])
api_router.include_router(trends.router,     prefix="/trends",     tags=["Trends"])
api_router.include_router(team.router,       prefix="/team",       tags=["Team"])
api_router.include_router(websocket.router,  prefix="/ws",         tags=["WebSocket"])

__all__ = ["api_router"]
