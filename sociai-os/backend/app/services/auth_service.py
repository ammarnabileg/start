"""
Authentication Service for SociAI OS.

Responsibilities:
  - User registration with email verification
  - Login authentication with 2FA support
  - Password reset flow
  - Email verification
  - OAuth2 flows for all supported platforms (LinkedIn, Meta, TikTok,
    Twitter/X, YouTube, Snapchat, Pinterest)
  - Credential encryption / storage
  - Login history recording
  - Session management
  - Team invitation emails
"""
from __future__ import annotations

import json
import logging
import secrets
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, Optional, Tuple
from uuid import UUID

import httpx
import redis.asyncio as aioredis
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings
from app.core.security import (
    decrypt_credential,
    encrypt_credential,
    hash_password,
    verify_password,
    verify_totp,
    verify_backup_code,
    create_email_verification_token,
    create_password_reset_token,
    decode_email_verification_token,
    decode_password_reset_token,
)

logger = logging.getLogger(__name__)


class AuthError(Exception):
    """Raised for authentication-specific failures."""

    def __init__(self, message: str, status_code: int = 401):
        self.message = message
        self.status_code = status_code
        super().__init__(message)


class AuthService:
    """
    Encapsulates all authentication and OAuth business logic.
    `db` may be None for operations that only require Redis (e.g. OAuth URL generation).
    """

    def __init__(self, db: Optional[AsyncSession], redis: aioredis.Redis):
        self.db = db
        self.redis = redis

    # ─── Registration ─────────────────────────────────────────────────────────

    async def register_user(
        self,
        email: str,
        password: str,
        full_name: str,
        company_name: Optional[str] = None,
    ):
        """
        Create a new user account.

        Raises:
            AuthError: if email already in use.
        """
        from app.models.user import User

        result = await self.db.execute(select(User).where(User.email == email))
        existing = result.scalar_one_or_none()
        if existing:
            raise AuthError("An account with this email already exists.", status_code=409)

        user = User(
            email=email,
            hashed_password=hash_password(password),
            full_name=full_name,
            company_name=company_name,
            role="owner",
            is_active=True,
            email_verified=False,
            two_factor_enabled=False,
        )
        self.db.add(user)
        await self.db.flush()
        logger.info("Registered new user: %s (id=%s)", email, user.id)
        return user

    async def send_verification_email(self, user) -> None:
        """Generate a verification token and dispatch an email (stub → replace with SMTP/SendGrid)."""
        token = create_email_verification_token(user.email)
        await self.redis.setex(
            f"email_verification:{token}",
            settings.EMAIL_VERIFICATION_TOKEN_EXPIRE_HOURS * 3600,
            str(user.id),
        )
        verify_url = f"{settings.FRONTEND_URL}/verify-email?token={token}"
        logger.info("Sending verification email to %s: %s", user.email, verify_url)
        # TODO: Integrate SMTP / SendGrid: send HTML email with verify_url

    async def verify_email(self, token: str) -> None:
        """Mark user email as verified."""
        from app.models.user import User
        from jose import JWTError

        try:
            payload = decode_email_verification_token(token)
        except JWTError as exc:
            raise AuthError("Invalid or expired email verification token.", 400) from exc

        email = payload.get("sub")
        stored_user_id = await self.redis.get(f"email_verification:{token}")
        if not stored_user_id:
            raise AuthError("Token has already been used or expired.", 400)

        result = await self.db.execute(select(User).where(User.email == email))
        user = result.scalar_one_or_none()
        if not user:
            raise AuthError("User not found.", 404)

        user.email_verified = True
        user.updated_at = datetime.now(timezone.utc)
        self.db.add(user)
        await self.db.flush()
        await self.redis.delete(f"email_verification:{token}")

    # ─── Login ────────────────────────────────────────────────────────────────

    async def authenticate(
        self,
        email: str,
        password: str,
        totp_code: Optional[str] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
    ):
        """
        Authenticate a user with email/password + optional 2FA.

        Returns the User model on success.
        Raises AuthError on failure.
        """
        from app.models.user import User, LoginHistory

        result = await self.db.execute(select(User).where(User.email == email))
        user = result.scalar_one_or_none()

        async def _record_attempt(success: bool, reason: Optional[str] = None):
            history = LoginHistory(
                user_id=user.id if user else None,
                ip_address=ip_address,
                user_agent=user_agent,
                success=success,
                failure_reason=reason,
            )
            self.db.add(history)
            await self.db.flush()

        if not user or not verify_password(password, user.hashed_password):
            await _record_attempt(False, "invalid_credentials")
            raise AuthError("Invalid email or password.", 401)

        if not user.is_active:
            await _record_attempt(False, "account_inactive")
            raise AuthError("Account is inactive.", 403)

        # 2FA check
        if getattr(user, "two_factor_enabled", False):
            if not totp_code:
                raise AuthError("Two-factor authentication code is required.", 401)

            # Try TOTP first, then backup codes
            totp_valid = verify_totp(user.totp_secret, totp_code)
            backup_valid = False
            if not totp_valid:
                for i, hashed_backup in enumerate(getattr(user, "totp_backup_codes", []) or []):
                    if verify_backup_code(totp_code, hashed_backup):
                        # Invalidate used backup code
                        codes = list(user.totp_backup_codes)
                        codes.pop(i)
                        user.totp_backup_codes = codes
                        self.db.add(user)
                        await self.db.flush()
                        backup_valid = True
                        break

            if not totp_valid and not backup_valid:
                await _record_attempt(False, "invalid_totp")
                raise AuthError("Invalid two-factor authentication code.", 401)

        await _record_attempt(True)
        user.last_login_at = datetime.now(timezone.utc)
        self.db.add(user)
        await self.db.flush()
        return user

    # ─── Password Reset ───────────────────────────────────────────────────────

    async def initiate_password_reset(self, email: str) -> None:
        """
        Send a password reset email if the user exists.
        Always completes without error (prevents email enumeration).
        """
        from app.models.user import User

        result = await self.db.execute(select(User).where(User.email == email))
        user = result.scalar_one_or_none()
        if not user:
            logger.debug("Password reset requested for unknown email: %s", email)
            return

        token = create_password_reset_token(str(user.id))
        await self.redis.setex(
            f"password_reset:{token}",
            settings.PASSWORD_RESET_TOKEN_EXPIRE_HOURS * 3600,
            str(user.id),
        )
        reset_url = f"{settings.FRONTEND_URL}/reset-password?token={token}"
        logger.info("Password reset URL for %s: %s", email, reset_url)
        # TODO: send email with reset_url

    async def reset_password(self, token: str, new_password: str) -> None:
        """Apply a new password using a valid reset token."""
        from app.models.user import User
        from jose import JWTError

        try:
            payload = decode_password_reset_token(token)
        except JWTError as exc:
            raise AuthError("Invalid or expired reset token.", 400) from exc

        user_id = payload.get("sub")
        stored = await self.redis.get(f"password_reset:{token}")
        if not stored or stored != user_id:
            raise AuthError("Reset token has already been used or expired.", 400)

        result = await self.db.execute(select(User).where(User.id == user_id))
        user = result.scalar_one_or_none()
        if not user:
            raise AuthError("User not found.", 404)

        user.hashed_password = hash_password(new_password)
        user.updated_at = datetime.now(timezone.utc)
        self.db.add(user)
        await self.redis.delete(f"password_reset:{token}")
        await self.db.flush()
        logger.info("Password reset completed for user %s", user_id)

    # ─── OAuth Platform Flows ─────────────────────────────────────────────────

    def _get_oauth_config(self, platform: str) -> Dict[str, str]:
        """Return the OAuth configuration dict for a given platform."""
        platform_configs = {
            "linkedin": {
                "client_id": settings.LINKEDIN_CLIENT_ID,
                "client_secret": settings.LINKEDIN_CLIENT_SECRET,
                "redirect_uri": settings.LINKEDIN_REDIRECT_URI,
                "scope": settings.LINKEDIN_SCOPE,
                "authorize_url": "https://www.linkedin.com/oauth/v2/authorization",
                "token_url": "https://www.linkedin.com/oauth/v2/accessToken",
                "profile_url": "https://api.linkedin.com/v2/me",
            },
            "meta": {
                "client_id": settings.META_APP_ID,
                "client_secret": settings.META_APP_SECRET,
                "redirect_uri": settings.META_REDIRECT_URI,
                "scope": settings.META_SCOPE,
                "authorize_url": "https://www.facebook.com/dialog/oauth",
                "token_url": "https://graph.facebook.com/oauth/access_token",
                "profile_url": f"https://graph.facebook.com/{settings.META_API_VERSION}/me",
            },
            "tiktok": {
                "client_id": settings.TIKTOK_CLIENT_KEY,
                "client_secret": settings.TIKTOK_CLIENT_SECRET,
                "redirect_uri": settings.TIKTOK_REDIRECT_URI,
                "scope": settings.TIKTOK_SCOPE,
                "authorize_url": "https://www.tiktok.com/auth/authorize/",
                "token_url": "https://open-api.tiktok.com/oauth/access_token/",
                "profile_url": "https://open-api.tiktok.com/user/info/",
            },
            "twitter": {
                "client_id": settings.TWITTER_CLIENT_ID,
                "client_secret": settings.TWITTER_CLIENT_SECRET,
                "redirect_uri": settings.TWITTER_REDIRECT_URI,
                "scope": "tweet.read tweet.write users.read offline.access",
                "authorize_url": "https://twitter.com/i/oauth2/authorize",
                "token_url": "https://api.twitter.com/2/oauth2/token",
                "profile_url": "https://api.twitter.com/2/users/me",
            },
            "youtube": {
                "client_id": settings.YOUTUBE_CLIENT_ID,
                "client_secret": settings.YOUTUBE_CLIENT_SECRET,
                "redirect_uri": settings.YOUTUBE_REDIRECT_URI,
                "scope": settings.YOUTUBE_SCOPE,
                "authorize_url": "https://accounts.google.com/o/oauth2/v2/auth",
                "token_url": "https://oauth2.googleapis.com/token",
                "profile_url": "https://www.googleapis.com/oauth2/v3/userinfo",
            },
            "snapchat": {
                "client_id": settings.SNAPCHAT_CLIENT_ID,
                "client_secret": settings.SNAPCHAT_CLIENT_SECRET,
                "redirect_uri": settings.SNAPCHAT_REDIRECT_URI,
                "scope": settings.SNAPCHAT_SCOPE,
                "authorize_url": "https://accounts.snapchat.com/login/oauth2/authorize",
                "token_url": "https://accounts.snapchat.com/login/oauth2/access_token",
                "profile_url": "https://adsapi.snapchat.com/v1/me",
            },
            "pinterest": {
                "client_id": settings.PINTEREST_APP_ID,
                "client_secret": settings.PINTEREST_APP_SECRET,
                "redirect_uri": settings.PINTEREST_REDIRECT_URI,
                "scope": settings.PINTEREST_SCOPE,
                "authorize_url": "https://www.pinterest.com/oauth/",
                "token_url": "https://api.pinterest.com/v5/oauth/token",
                "profile_url": "https://api.pinterest.com/v5/user_account",
            },
        }
        if platform not in platform_configs:
            raise AuthError(f"Unsupported OAuth platform: {platform}", 400)
        return platform_configs[platform]

    async def get_oauth_authorization_url(
        self, platform: str, user_id: str
    ) -> Tuple[str, str]:
        """
        Construct the OAuth authorization URL for the given platform.
        Returns (authorization_url, state).
        """
        cfg = self._get_oauth_config(platform)
        state = secrets.token_urlsafe(32)

        params = {
            "client_id": cfg["client_id"],
            "redirect_uri": cfg["redirect_uri"],
            "response_type": "code",
            "scope": cfg["scope"],
            "state": state,
        }

        # Platform-specific extras
        if platform == "youtube":
            params["access_type"] = "offline"
            params["prompt"] = "consent"
        elif platform == "twitter":
            params["code_challenge"] = secrets.token_urlsafe(32)
            params["code_challenge_method"] = "plain"

        from urllib.parse import urlencode
        auth_url = f"{cfg['authorize_url']}?{urlencode(params)}"
        return auth_url, state

    async def exchange_oauth_code(
        self, platform: str, code: str, user_id: str
    ):
        """
        Exchange an authorization code for access/refresh tokens,
        fetch the user profile, and store the platform account.
        """
        cfg = self._get_oauth_config(platform)

        token_params: Dict[str, str] = {
            "grant_type": "authorization_code",
            "code": code,
            "redirect_uri": cfg["redirect_uri"],
            "client_id": cfg["client_id"],
            "client_secret": cfg["client_secret"],
        }

        async with httpx.AsyncClient(timeout=30.0) as client:
            # ── 1. Exchange code for tokens ──────────────────────────────────
            token_resp = await client.post(cfg["token_url"], data=token_params)
            if token_resp.status_code != 200:
                logger.error(
                    "OAuth token exchange failed for %s: %s %s",
                    platform, token_resp.status_code, token_resp.text,
                )
                raise AuthError(
                    f"OAuth token exchange failed for {platform}: {token_resp.text}",
                    502,
                )
            token_data = token_resp.json()
            access_token = token_data.get("access_token", "")
            refresh_token = token_data.get("refresh_token")
            expires_in = token_data.get("expires_in", 3600)

            # ── 2. Fetch user profile ────────────────────────────────────────
            headers = {"Authorization": f"Bearer {access_token}"}
            profile_resp = await client.get(cfg["profile_url"], headers=headers)
            if profile_resp.status_code != 200:
                logger.warning(
                    "Profile fetch failed for %s: %s",
                    platform, profile_resp.status_code,
                )
                profile_data = {}
            else:
                profile_data = profile_resp.json()

        # ── 3. Extract platform user ID / username ───────────────────────────
        platform_user_id, platform_username = self._extract_profile_identifiers(
            platform, profile_data
        )

        # ── 4. Encrypt and store ─────────────────────────────────────────────
        return await self._store_platform_account(
            user_id=user_id,
            platform=platform,
            platform_user_id=platform_user_id,
            platform_username=platform_username,
            access_token=access_token,
            refresh_token=refresh_token,
            expires_in=expires_in,
            raw_profile=profile_data,
        )

    def _extract_profile_identifiers(
        self, platform: str, profile_data: Dict[str, Any]
    ) -> Tuple[str, Optional[str]]:
        """Extract (platform_user_id, platform_username) from a profile response."""
        extractors = {
            "linkedin": lambda d: (
                d.get("id", "unknown"),
                d.get("localizedFirstName", "") + " " + d.get("localizedLastName", ""),
            ),
            "meta": lambda d: (
                d.get("id", "unknown"),
                d.get("name"),
            ),
            "tiktok": lambda d: (
                d.get("data", {}).get("user", {}).get("open_id", "unknown"),
                d.get("data", {}).get("user", {}).get("display_name"),
            ),
            "twitter": lambda d: (
                d.get("data", {}).get("id", "unknown"),
                d.get("data", {}).get("username"),
            ),
            "youtube": lambda d: (
                d.get("sub", "unknown"),
                d.get("name"),
            ),
            "snapchat": lambda d: (
                d.get("me", {}).get("id", "unknown"),
                d.get("me", {}).get("display_name"),
            ),
            "pinterest": lambda d: (
                d.get("username", "unknown"),
                d.get("username"),
            ),
        }
        extractor = extractors.get(platform, lambda d: (d.get("id", "unknown"), None))
        return extractor(profile_data)

    async def _store_platform_account(
        self,
        user_id: str,
        platform: str,
        platform_user_id: str,
        platform_username: Optional[str],
        access_token: str,
        refresh_token: Optional[str],
        expires_in: int,
        raw_profile: Dict[str, Any],
    ):
        """
        Upsert PlatformAccount with encrypted credentials.
        Returns the PlatformAccount model instance.
        """
        from app.models.platform import PlatformAccount, OAuthToken

        # Check for existing account
        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.user_id == user_id,
                PlatformAccount.platform == platform,
                PlatformAccount.platform_user_id == platform_user_id,
            )
        )
        account = result.scalar_one_or_none()
        now = datetime.now(timezone.utc)
        token_expires = now + timedelta(seconds=expires_in)

        if account:
            account.is_active = True
            account.last_synced_at = now
            account.platform_username = platform_username
        else:
            account = PlatformAccount(
                user_id=user_id,
                platform=platform,
                platform_user_id=platform_user_id,
                platform_username=platform_username,
                is_active=True,
                last_synced_at=now,
            )
            self.db.add(account)
            await self.db.flush()

        # Store / rotate encrypted OAuth token
        token_result = await self.db.execute(
            select(OAuthToken).where(OAuthToken.platform_account_id == account.id)
        )
        oauth_token = token_result.scalar_one_or_none()
        encrypted_access = encrypt_credential(access_token)
        encrypted_refresh = encrypt_credential(refresh_token) if refresh_token else None

        if oauth_token:
            oauth_token.encrypted_access_token = encrypted_access
            oauth_token.encrypted_refresh_token = encrypted_refresh
            oauth_token.token_expires_at = token_expires
            oauth_token.updated_at = now
        else:
            oauth_token = OAuthToken(
                platform_account_id=account.id,
                encrypted_access_token=encrypted_access,
                encrypted_refresh_token=encrypted_refresh,
                token_expires_at=token_expires,
            )
            self.db.add(oauth_token)

        await self.db.flush()
        logger.info("OAuth account stored: user=%s platform=%s pid=%s", user_id, platform, platform_user_id)
        return account

    async def refresh_platform_token(self, platform: str, account_id: str) -> str:
        """
        Use the stored refresh token to obtain a new access token.
        Returns the new (decrypted) access token.
        """
        from app.models.platform import OAuthToken
        from jose import JWTError

        result = await self.db.execute(
            select(OAuthToken).where(OAuthToken.platform_account_id == account_id)
        )
        token = result.scalar_one_or_none()
        if not token or not token.encrypted_refresh_token:
            raise AuthError("No refresh token available for this account.", 400)

        cfg = self._get_oauth_config(platform)
        refresh_tok = decrypt_credential(token.encrypted_refresh_token)

        refresh_params = {
            "grant_type": "refresh_token",
            "refresh_token": refresh_tok,
            "client_id": cfg["client_id"],
            "client_secret": cfg["client_secret"],
        }

        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(cfg["token_url"], data=refresh_params)
            if resp.status_code != 200:
                raise AuthError(f"Token refresh failed: {resp.text}", 502)
            data = resp.json()

        new_access = data.get("access_token", "")
        new_refresh = data.get("refresh_token", refresh_tok)
        expires_in = data.get("expires_in", 3600)
        now = datetime.now(timezone.utc)

        token.encrypted_access_token = encrypt_credential(new_access)
        token.encrypted_refresh_token = encrypt_credential(new_refresh)
        token.token_expires_at = now + timedelta(seconds=expires_in)
        token.updated_at = now
        self.db.add(token)
        await self.db.flush()
        return new_access

    # ─── Team Invitations ─────────────────────────────────────────────────────

    async def send_team_invitation_email(
        self,
        email: str,
        invite_token: str,
        inviter_name: str,
        role: str,
        message: Optional[str] = None,
    ) -> None:
        """Dispatch a team invitation email (stub – replace with SMTP/SendGrid)."""
        accept_url = f"{settings.FRONTEND_URL}/team/accept?token={invite_token}"
        logger.info(
            "Team invitation: to=%s inviter=%s role=%s url=%s",
            email, inviter_name, role, accept_url,
        )
        # TODO: send HTML email with accept_url

    # ─── Helpers ──────────────────────────────────────────────────────────────

    async def get_decrypted_access_token(self, platform_account_id: str) -> str:
        """
        Retrieve and decrypt the access token for a platform account,
        auto-refreshing if it's expired (or within 5 minutes of expiry).
        """
        from app.models.platform import OAuthToken, PlatformAccount

        account_result = await self.db.execute(
            select(PlatformAccount).where(PlatformAccount.id == platform_account_id)
        )
        account = account_result.scalar_one_or_none()
        if not account:
            raise AuthError("Platform account not found.", 404)

        result = await self.db.execute(
            select(OAuthToken).where(OAuthToken.platform_account_id == platform_account_id)
        )
        token = result.scalar_one_or_none()
        if not token:
            raise AuthError("No OAuth token stored for this account.", 400)

        now = datetime.now(timezone.utc)
        buffer = timedelta(minutes=5)
        if token.token_expires_at and token.token_expires_at - buffer < now:
            logger.info("Auto-refreshing expired token for account %s", platform_account_id)
            return await self.refresh_platform_token(account.platform, platform_account_id)

        return decrypt_credential(token.encrypted_access_token)
