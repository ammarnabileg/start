"""
Authentication routes for SociAI OS.

Endpoints:
  POST   /auth/register
  POST   /auth/login
  POST   /auth/logout
  POST   /auth/refresh-token
  POST   /auth/forgot-password
  POST   /auth/reset-password
  POST   /auth/verify-email
  GET    /auth/me
  PATCH  /auth/me
  POST   /auth/2fa/setup
  POST   /auth/2fa/verify
  DELETE /auth/2fa/disable
  POST   /auth/2fa/backup-codes
  GET    /auth/sessions
  DELETE /auth/sessions/{session_id}
  GET    /auth/login-history
  GET    /auth/oauth/{platform}/authorize
  GET    /auth/oauth/{platform}/callback
"""
from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, BackgroundTasks, Body, Depends, HTTPException, Request, status
from fastapi.responses import RedirectResponse
from pydantic import BaseModel, EmailStr, Field, field_validator
from sqlalchemy import select, update
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import (
    get_current_active_user,
    get_current_user,
    get_db,
    get_redis,
    rate_limit,
)
from app.core.config import settings
from app.core.security import (
    create_access_token,
    create_refresh_token,
    decode_refresh_token,
    generate_backup_codes,
    generate_totp_qr_code,
    generate_totp_secret,
    hash_backup_code,
    hash_password,
    verify_backup_code,
    verify_password,
    verify_totp,
)
from app.services.auth_service import AuthService

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Request / Response Schemas ───────────────────────────────────────────────

class RegisterRequest(BaseModel):
    email: EmailStr
    password: str = Field(min_length=8, max_length=128)
    full_name: str = Field(min_length=1, max_length=128)
    company_name: Optional[str] = Field(default=None, max_length=256)

    @field_validator("password")
    @classmethod
    def password_strength(cls, v: str) -> str:
        if not any(c.isupper() for c in v):
            raise ValueError("Password must contain at least one uppercase letter")
        if not any(c.isdigit() for c in v):
            raise ValueError("Password must contain at least one digit")
        return v


class LoginRequest(BaseModel):
    email: EmailStr
    password: str
    totp_code: Optional[str] = Field(default=None, min_length=6, max_length=8)
    remember_me: bool = False


class RefreshTokenRequest(BaseModel):
    refresh_token: str


class ForgotPasswordRequest(BaseModel):
    email: EmailStr


class ResetPasswordRequest(BaseModel):
    token: str
    new_password: str = Field(min_length=8, max_length=128)


class VerifyEmailRequest(BaseModel):
    token: str


class UpdateProfileRequest(BaseModel):
    full_name: Optional[str] = Field(default=None, max_length=128)
    company_name: Optional[str] = Field(default=None, max_length=256)
    avatar_url: Optional[str] = None
    timezone: Optional[str] = None
    notification_preferences: Optional[Dict[str, Any]] = None


class TwoFactorSetupResponse(BaseModel):
    secret: str
    qr_code: str          # data:image/png;base64,…
    backup_codes: List[str]


class TwoFactorVerifyRequest(BaseModel):
    code: str = Field(min_length=6, max_length=8)


class TwoFactorDisableRequest(BaseModel):
    password: str
    code: str = Field(min_length=6, max_length=8)


class TokenResponse(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"
    expires_in: int = settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60
    user: Dict[str, Any]


class SessionResponse(BaseModel):
    id: str
    ip_address: Optional[str]
    user_agent: Optional[str]
    created_at: datetime
    last_used_at: datetime
    is_current: bool


class LoginHistoryEntry(BaseModel):
    id: str
    ip_address: Optional[str]
    user_agent: Optional[str]
    success: bool
    failure_reason: Optional[str]
    country: Optional[str]
    city: Optional[str]
    created_at: datetime


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _user_to_dict(user) -> Dict[str, Any]:
    return {
        "id": str(user.id),
        "email": user.email,
        "full_name": user.full_name,
        "company_name": getattr(user, "company_name", None),
        "role": user.role,
        "is_active": user.is_active,
        "email_verified": user.email_verified,
        "two_factor_enabled": getattr(user, "two_factor_enabled", False),
        "avatar_url": getattr(user, "avatar_url", None),
        "timezone": getattr(user, "timezone", "UTC"),
        "created_at": user.created_at.isoformat() if user.created_at else None,
    }


async def _issue_tokens(
    user_id: str,
    redis: aioredis.Redis,
    remember_me: bool = False,
) -> tuple[str, str]:
    """Issue access + refresh token pair, storing refresh token in Redis."""
    access_token = create_access_token(str(user_id))
    refresh_token = create_refresh_token(str(user_id))
    ttl = settings.REFRESH_TOKEN_EXPIRE_DAYS * 86400
    await redis.setex(f"refresh_token:{refresh_token}", ttl, str(user_id))
    return access_token, refresh_token


# ─── Registration ─────────────────────────────────────────────────────────────

@router.post(
    "/register",
    response_model=TokenResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Register a new account",
    dependencies=[Depends(rate_limit(limit=5, window=60, key_prefix="register"))],
)
async def register(
    payload: RegisterRequest,
    background_tasks: BackgroundTasks,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AuthService(db, redis)
    user = await svc.register_user(
        email=payload.email,
        password=payload.password,
        full_name=payload.full_name,
        company_name=payload.company_name,
    )
    background_tasks.add_task(svc.send_verification_email, user)
    access_token, refresh_token = await _issue_tokens(str(user.id), redis)
    return TokenResponse(
        access_token=access_token,
        refresh_token=refresh_token,
        user=_user_to_dict(user),
    )


# ─── Login ────────────────────────────────────────────────────────────────────

@router.post(
    "/login",
    response_model=TokenResponse,
    summary="Authenticate and obtain tokens",
    dependencies=[Depends(rate_limit(limit=settings.RATE_LIMIT_AUTH, window=60, key_prefix="login"))],
)
async def login(
    payload: LoginRequest,
    request: Request,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AuthService(db, redis)
    user = await svc.authenticate(
        email=payload.email,
        password=payload.password,
        totp_code=payload.totp_code,
        ip_address=request.client.host if request.client else None,
        user_agent=request.headers.get("user-agent"),
    )
    access_token, refresh_token = await _issue_tokens(
        str(user.id), redis, remember_me=payload.remember_me
    )
    return TokenResponse(
        access_token=access_token,
        refresh_token=refresh_token,
        user=_user_to_dict(user),
    )


# ─── Logout ───────────────────────────────────────────────────────────────────

@router.post("/logout", status_code=status.HTTP_204_NO_CONTENT, summary="Revoke current session tokens")
async def logout(
    payload: RefreshTokenRequest,
    current_user=Depends(get_current_user),
    redis: aioredis.Redis = Depends(get_redis),
):
    # Revoke the refresh token stored in Redis
    await redis.delete(f"refresh_token:{payload.refresh_token}")
    # We can't truly revoke a JWT access token without a blocklist entry.
    # The client should discard it.  We just acknowledge success.


# ─── Refresh Token ────────────────────────────────────────────────────────────

@router.post(
    "/refresh-token",
    response_model=TokenResponse,
    summary="Rotate access + refresh token pair",
    dependencies=[Depends(rate_limit(limit=30, window=60, key_prefix="refresh"))],
)
async def refresh_token(
    payload: RefreshTokenRequest,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.user import User
    from jose import JWTError

    try:
        token_payload = decode_refresh_token(payload.refresh_token)
    except JWTError as exc:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or expired refresh token",
        ) from exc

    user_id = token_payload.get("sub")
    # Validate stored refresh token
    stored = await redis.get(f"refresh_token:{payload.refresh_token}")
    if not stored or stored != user_id:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Refresh token not found or already rotated",
        )

    # Rotate – delete old, issue new
    await redis.delete(f"refresh_token:{payload.refresh_token}")
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user or not user.is_active:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found")

    access_token, new_refresh = await _issue_tokens(str(user.id), redis)
    return TokenResponse(
        access_token=access_token,
        refresh_token=new_refresh,
        user=_user_to_dict(user),
    )


# ─── Forgot / Reset Password ──────────────────────────────────────────────────

@router.post(
    "/forgot-password",
    status_code=status.HTTP_202_ACCEPTED,
    summary="Request a password-reset email",
    dependencies=[Depends(rate_limit(limit=3, window=300, key_prefix="forgot"))],
)
async def forgot_password(
    payload: ForgotPasswordRequest,
    background_tasks: BackgroundTasks,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AuthService(db, redis)
    # Always return 202 to avoid email enumeration
    background_tasks.add_task(svc.initiate_password_reset, payload.email)
    return {"detail": "If that email is registered, a reset link has been sent."}


@router.post(
    "/reset-password",
    status_code=status.HTTP_200_OK,
    summary="Complete password reset with token",
    dependencies=[Depends(rate_limit(limit=5, window=300, key_prefix="reset"))],
)
async def reset_password(
    payload: ResetPasswordRequest,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AuthService(db, redis)
    await svc.reset_password(token=payload.token, new_password=payload.new_password)
    return {"detail": "Password has been reset successfully."}


# ─── Email Verification ───────────────────────────────────────────────────────

@router.post(
    "/verify-email",
    status_code=status.HTTP_200_OK,
    summary="Verify email address with token",
)
async def verify_email(
    payload: VerifyEmailRequest,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    svc = AuthService(db, redis)
    await svc.verify_email(token=payload.token)
    return {"detail": "Email verified successfully."}


# ─── Profile ──────────────────────────────────────────────────────────────────

@router.get("/me", summary="Get current user profile")
async def get_me(current_user=Depends(get_current_active_user)):
    return _user_to_dict(current_user)


@router.patch("/me", summary="Update current user profile")
async def update_me(
    payload: UpdateProfileRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    update_data = payload.model_dump(exclude_none=True)
    for field, value in update_data.items():
        setattr(current_user, field, value)
    current_user.updated_at = datetime.now(timezone.utc)
    db.add(current_user)
    await db.flush()
    return _user_to_dict(current_user)


# ─── Two-Factor Authentication ────────────────────────────────────────────────

@router.post(
    "/2fa/setup",
    response_model=TwoFactorSetupResponse,
    summary="Generate TOTP secret and QR code for 2FA enrollment",
)
async def setup_2fa(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    if getattr(current_user, "two_factor_enabled", False):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Two-factor authentication is already enabled",
        )
    secret = generate_totp_secret()
    qr_code = generate_totp_qr_code(secret, current_user.email)
    backup_codes = generate_backup_codes()

    # Persist pending secret (not yet activated until verified)
    current_user.totp_secret_pending = secret
    current_user.totp_backup_codes = [hash_backup_code(c) for c in backup_codes]
    db.add(current_user)
    await db.flush()

    return TwoFactorSetupResponse(
        secret=secret,
        qr_code=qr_code,
        backup_codes=backup_codes,
    )


@router.post(
    "/2fa/verify",
    status_code=status.HTTP_200_OK,
    summary="Activate 2FA by verifying the first TOTP code",
)
async def verify_2fa_setup(
    payload: TwoFactorVerifyRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    pending_secret = getattr(current_user, "totp_secret_pending", None)
    if not pending_secret:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="No pending 2FA setup found. Call /2fa/setup first.",
        )
    if not verify_totp(pending_secret, payload.code):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid TOTP code",
        )
    current_user.totp_secret = pending_secret
    current_user.totp_secret_pending = None
    current_user.two_factor_enabled = True
    db.add(current_user)
    await db.flush()
    return {"detail": "Two-factor authentication enabled successfully."}


@router.delete(
    "/2fa/disable",
    status_code=status.HTTP_200_OK,
    summary="Disable 2FA (requires password + valid TOTP code)",
)
async def disable_2fa(
    payload: TwoFactorDisableRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    if not verify_password(payload.password, current_user.hashed_password):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Incorrect password")
    if not getattr(current_user, "two_factor_enabled", False):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="2FA is not enabled")
    if not verify_totp(current_user.totp_secret, payload.code):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid TOTP code")

    current_user.totp_secret = None
    current_user.two_factor_enabled = False
    current_user.totp_backup_codes = []
    db.add(current_user)
    await db.flush()
    return {"detail": "Two-factor authentication disabled."}


@router.post(
    "/2fa/backup-codes",
    summary="Regenerate backup codes (invalidates old ones)",
)
async def regenerate_backup_codes(
    payload: TwoFactorVerifyRequest,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    if not getattr(current_user, "two_factor_enabled", False):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="2FA is not enabled")
    if not verify_totp(current_user.totp_secret, payload.code):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid TOTP code")

    new_codes = generate_backup_codes()
    current_user.totp_backup_codes = [hash_backup_code(c) for c in new_codes]
    db.add(current_user)
    await db.flush()
    return {"backup_codes": new_codes}


# ─── Sessions ─────────────────────────────────────────────────────────────────

@router.get(
    "/sessions",
    response_model=List[SessionResponse],
    summary="List all active sessions for current user",
)
async def list_sessions(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    request: Request = None,
):
    from app.models.user import UserSession
    result = await db.execute(
        select(UserSession)
        .where(UserSession.user_id == current_user.id, UserSession.is_revoked == False)
        .order_by(UserSession.last_used_at.desc())
    )
    sessions = result.scalars().all()
    current_jti = getattr(request.state, "jti", None) if request else None
    return [
        SessionResponse(
            id=str(s.id),
            ip_address=s.ip_address,
            user_agent=s.user_agent,
            created_at=s.created_at,
            last_used_at=s.last_used_at,
            is_current=(str(s.jti) == current_jti) if current_jti else False,
        )
        for s in sessions
    ]


@router.delete(
    "/sessions/{session_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Revoke a specific session",
)
async def revoke_session(
    session_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.models.user import UserSession
    result = await db.execute(
        select(UserSession).where(
            UserSession.id == session_id,
            UserSession.user_id == current_user.id,
        )
    )
    session = result.scalar_one_or_none()
    if not session:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Session not found")
    session.is_revoked = True
    db.add(session)
    # Add JTI to revocation list
    await redis.setex(
        f"revoked_token:{session.jti}",
        settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        "1",
    )
    await db.flush()


# ─── Login History ────────────────────────────────────────────────────────────

@router.get(
    "/login-history",
    response_model=List[LoginHistoryEntry],
    summary="Get recent login attempts for current user",
)
async def get_login_history(
    limit: int = 50,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import LoginHistory
    result = await db.execute(
        select(LoginHistory)
        .where(LoginHistory.user_id == current_user.id)
        .order_by(LoginHistory.created_at.desc())
        .limit(limit)
    )
    rows = result.scalars().all()
    return [
        LoginHistoryEntry(
            id=str(r.id),
            ip_address=r.ip_address,
            user_agent=r.user_agent,
            success=r.success,
            failure_reason=r.failure_reason,
            country=r.country,
            city=r.city,
            created_at=r.created_at,
        )
        for r in rows
    ]


# ─── OAuth – Platform Connection ──────────────────────────────────────────────

SUPPORTED_OAUTH_PLATFORMS = {
    "linkedin", "meta", "tiktok", "twitter", "youtube", "snapchat", "pinterest",
}


@router.get(
    "/oauth/{platform}/authorize",
    summary="Redirect user to platform OAuth authorization URL",
)
async def oauth_authorize(
    platform: str,
    current_user=Depends(get_current_active_user),
    redis: aioredis.Redis = Depends(get_redis),
):
    if platform not in SUPPORTED_OAUTH_PLATFORMS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported platform: {platform}. Supported: {sorted(SUPPORTED_OAUTH_PLATFORMS)}",
        )
    svc_redis = redis
    from app.services.auth_service import AuthService
    # Use a temporary DB-less auth service just for OAuth URL generation
    auth_svc = AuthService(db=None, redis=svc_redis)
    auth_url, state = await auth_svc.get_oauth_authorization_url(platform, str(current_user.id))
    # Store state → user_id mapping in Redis (5 min TTL)
    await redis.setex(f"oauth_state:{state}", 300, str(current_user.id))
    return {"authorization_url": auth_url, "state": state}


@router.get(
    "/oauth/{platform}/callback",
    summary="Handle OAuth callback, store tokens",
)
async def oauth_callback(
    platform: str,
    code: str,
    state: str,
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    if platform not in SUPPORTED_OAUTH_PLATFORMS:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Unsupported platform")

    user_id = await redis.get(f"oauth_state:{state}")
    if not user_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid or expired OAuth state parameter",
        )
    await redis.delete(f"oauth_state:{state}")

    svc = AuthService(db, redis)
    platform_account = await svc.exchange_oauth_code(
        platform=platform, code=code, user_id=user_id
    )
    return RedirectResponse(
        url=f"{settings.FRONTEND_URL}/platforms?connected={platform}&account={platform_account.platform_user_id}",
        status_code=status.HTTP_302_FOUND,
    )
