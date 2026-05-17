"""
Shared FastAPI dependencies for SociAI OS.

Provides:
  - get_db          – async SQLAlchemy session
  - get_redis       – Redis connection from pool
  - get_current_user – JWT-authenticated user
  - get_current_active_user
  - require_role    – role-based access-control decorator factory
  - optional_current_user – user or None (for public endpoints)
  - rate_limit      – per-endpoint Redis sliding-window rate limiter
  - pagination      – standard skip/limit query params
"""
from __future__ import annotations

import logging
from functools import lru_cache
from typing import AsyncGenerator, Callable, Optional, Set

import redis.asyncio as aioredis
from fastapi import Depends, HTTPException, Query, Request, Security, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jose import JWTError
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings
from app.core.security import check_rate_limit, decode_access_token, RateLimitExceeded
from app.db.session import AsyncSessionLocal  # created by project; see note below

logger = logging.getLogger(__name__)

# ─── Bearer token extractor ──────────────────────────────────────────────────

_bearer_scheme = HTTPBearer(auto_error=False)


# ─── Database ─────────────────────────────────────────────────────────────────

async def get_db() -> AsyncGenerator[AsyncSession, None]:
    """Yield an async database session; rollback on error, close on exit."""
    async with AsyncSessionLocal() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()


# ─── Redis ────────────────────────────────────────────────────────────────────

_redis_pool: Optional[aioredis.Redis] = None


async def get_redis() -> aioredis.Redis:
    """Return a shared async Redis connection from the global pool."""
    global _redis_pool
    if _redis_pool is None:
        _redis_pool = aioredis.from_url(
            settings.REDIS_URL,
            max_connections=settings.REDIS_MAX_CONNECTIONS,
            socket_timeout=settings.REDIS_SOCKET_TIMEOUT,
            socket_connect_timeout=settings.REDIS_SOCKET_CONNECT_TIMEOUT,
            retry_on_timeout=settings.REDIS_RETRY_ON_TIMEOUT,
            decode_responses=settings.REDIS_DECODE_RESPONSES,
        )
    return _redis_pool


# ─── Current User ─────────────────────────────────────────────────────────────

async def _resolve_user_from_token(
    credentials: Optional[HTTPAuthorizationCredentials],
    db: AsyncSession,
    redis: aioredis.Redis,
    require_auth: bool = True,
):
    """
    Internal helper: decode Bearer JWT, check revocation in Redis,
    load User from DB.
    """
    from app.models.user import User  # local import to avoid circular deps
    from sqlalchemy import select

    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )

    if credentials is None:
        if require_auth:
            raise credentials_exception
        return None

    token = credentials.credentials

    # ── 1. Decode JWT ──────────────────────────────────────────────────────────
    try:
        payload = decode_access_token(token)
    except JWTError as exc:
        logger.debug("JWT decode failed: %s", exc)
        if require_auth:
            raise credentials_exception
        return None

    user_id: Optional[str] = payload.get("sub")
    jti: Optional[str] = payload.get("jti")

    if not user_id or not jti:
        if require_auth:
            raise credentials_exception
        return None

    # ── 2. Check token revocation list ────────────────────────────────────────
    revoked = await redis.get(f"revoked_token:{jti}")
    if revoked:
        if require_auth:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Token has been revoked",
                headers={"WWW-Authenticate": "Bearer"},
            )
        return None

    # ── 3. Load user from DB ───────────────────────────────────────────────────
    result = await db.execute(select(User).where(User.id == user_id))
    user: Optional[User] = result.scalar_one_or_none()

    if not user:
        if require_auth:
            raise credentials_exception
        return None

    return user


async def get_current_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Security(_bearer_scheme),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    """Dependency: return the authenticated User model or raise 401."""
    return await _resolve_user_from_token(credentials, db, redis, require_auth=True)


async def optional_current_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Security(_bearer_scheme),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    """Dependency: return the authenticated User model, or None for guests."""
    return await _resolve_user_from_token(credentials, db, redis, require_auth=False)


async def get_current_active_user(
    current_user=Depends(get_current_user),
):
    """Dependency: authenticated + account active (not suspended/deleted)."""
    if not current_user.is_active:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Inactive account",
        )
    return current_user


async def get_verified_user(
    current_user=Depends(get_current_active_user),
):
    """Dependency: authenticated + email verified."""
    if not current_user.email_verified:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Email address not verified",
        )
    return current_user


# ─── Role-Based Access Control ────────────────────────────────────────────────

def require_role(*roles: str) -> Callable:
    """
    Factory that returns a FastAPI dependency enforcing one of the given roles.

    Usage::

        @router.get("/admin-only")
        async def admin_endpoint(user=Depends(require_role("admin", "owner"))):
            ...
    """
    allowed: Set[str] = set(roles)

    async def _check(current_user=Depends(get_current_active_user)):
        user_role: str = getattr(current_user, "role", "viewer")
        if user_role not in allowed:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Role '{user_role}' is not authorised for this action. "
                       f"Required: {sorted(allowed)}",
            )
        return current_user

    return _check


def require_permission(permission: str) -> Callable:
    """
    Factory that returns a FastAPI dependency checking a named permission.
    Permissions are stored as a comma-separated string on the user model
    (or delegated to a Role → RolePermission join in more complex setups).
    """
    async def _check(current_user=Depends(get_current_active_user)):
        user_permissions: str = getattr(current_user, "permissions", "") or ""
        perm_set = {p.strip() for p in user_permissions.split(",") if p.strip()}
        # Admins and owners bypass all permission checks
        if current_user.role in ("admin", "owner"):
            return current_user
        if permission not in perm_set:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Permission '{permission}' is required.",
            )
        return current_user

    return _check


# ─── Rate Limiting ────────────────────────────────────────────────────────────

def rate_limit(
    limit: int = settings.RATE_LIMIT_DEFAULT,
    window: int = settings.RATE_LIMIT_WINDOW_SECONDS,
    key_prefix: str = "rl",
) -> Callable:
    """
    Per-endpoint rate-limit dependency (sliding-window backed by Redis).

    Usage::

        @router.post("/login")
        async def login(
            _=Depends(rate_limit(limit=10, window=60, key_prefix="login")),
            ...
        ):
    """
    async def _check(
        request: Request,
        redis: aioredis.Redis = Depends(get_redis),
    ):
        # Key by IP address so unauthenticated endpoints are still protected
        client_ip = request.client.host if request.client else "unknown"
        redis_key = f"{key_prefix}:{client_ip}"
        try:
            count, remaining = await check_rate_limit(redis, redis_key, limit, window)
            request.state.rate_limit_remaining = remaining
        except RateLimitExceeded as exc:
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail="Rate limit exceeded",
                headers={"Retry-After": str(exc.retry_after)},
            ) from exc

    return _check


# ─── Pagination ───────────────────────────────────────────────────────────────

class PaginationParams:
    """Standard pagination query-string parameters."""

    def __init__(
        self,
        page: int = Query(default=1, ge=1, description="Page number (1-based)"),
        page_size: int = Query(default=20, ge=1, le=100, description="Items per page"),
    ):
        self.page = page
        self.page_size = page_size
        self.offset = (page - 1) * page_size
        self.limit = page_size


def get_pagination(
    page: int = Query(default=1, ge=1),
    page_size: int = Query(default=20, ge=1, le=100),
) -> PaginationParams:
    return PaginationParams(page=page, page_size=page_size)
