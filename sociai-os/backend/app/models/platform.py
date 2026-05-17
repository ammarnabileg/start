"""
app/models/platform.py
──────────────────────
PlatformAccount, PlatformCredential, OAuthToken, SocialAccount models.

Supports all 11 platforms:
  LinkedIn, Facebook, Instagram, TikTok, Twitter/X,
  YouTube, Snapchat, Pinterest, WhatsApp, Telegram, Threads
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
    from app.models.content import Post
    from app.models.analytics import MetricSnapshot


# ─── Enums ────────────────────────────────────────────────────────────────────

class PlatformType(str, enum.Enum):
    LINKEDIN = "linkedin"
    FACEBOOK = "facebook"
    INSTAGRAM = "instagram"
    TIKTOK = "tiktok"
    TWITTER = "twitter"
    YOUTUBE = "youtube"
    SNAPCHAT = "snapchat"
    PINTEREST = "pinterest"
    WHATSAPP = "whatsapp"
    TELEGRAM = "telegram"
    THREADS = "threads"


class AccountType(str, enum.Enum):
    PERSONAL = "personal"
    BUSINESS = "business"
    CREATOR = "creator"
    PAGE = "page"
    CHANNEL = "channel"
    BOT = "bot"


class ConnectionStatus(str, enum.Enum):
    CONNECTED = "connected"
    DISCONNECTED = "disconnected"
    TOKEN_EXPIRED = "token_expired"
    SUSPENDED = "suspended"
    RATE_LIMITED = "rate_limited"
    ERROR = "error"


class OAuthGrantType(str, enum.Enum):
    AUTHORIZATION_CODE = "authorization_code"
    CLIENT_CREDENTIALS = "client_credentials"
    IMPLICIT = "implicit"
    DEVICE_CODE = "device_code"


# ─── Platform Account Model ───────────────────────────────────────────────────

class PlatformAccount(Base):
    """
    A social media account connected to a SociAI OS user.
    One user can have multiple accounts per platform.
    """
    __tablename__ = "platform_accounts"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    platform: Mapped[PlatformType] = mapped_column(
        Enum(PlatformType), nullable=False, index=True
    )
    account_type: Mapped[AccountType] = mapped_column(
        Enum(AccountType), default=AccountType.PERSONAL, nullable=False
    )

    # Platform-native identifiers
    platform_user_id: Mapped[str] = mapped_column(String(255), nullable=False)
    platform_username: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    platform_display_name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    platform_profile_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    platform_avatar_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    platform_bio: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Connection state
    status: Mapped[ConnectionStatus] = mapped_column(
        Enum(ConnectionStatus), default=ConnectionStatus.CONNECTED, nullable=False, index=True
    )
    is_primary: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    last_sync_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    last_error: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    error_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

    # Platform-specific audience data (followers, subscribers, etc.)
    follower_count: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    following_count: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    post_count: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)

    # Platform-specific metadata (page ID, channel ID, workspace ID, etc.)
    platform_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Rate limit tracking
    rate_limit_remaining: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)
    rate_limit_reset_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Timestamps
    connected_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship("User", back_populates="platform_accounts")
    credentials: Mapped[List["PlatformCredential"]] = relationship(
        "PlatformCredential", back_populates="platform_account", cascade="all, delete-orphan"
    )
    oauth_tokens: Mapped[List["OAuthToken"]] = relationship(
        "OAuthToken", back_populates="platform_account", cascade="all, delete-orphan"
    )
    social_accounts: Mapped[List["SocialAccount"]] = relationship(
        "SocialAccount", back_populates="platform_account", cascade="all, delete-orphan"
    )
    posts: Mapped[List["Post"]] = relationship(
        "Post", back_populates="platform_account"
    )
    metric_snapshots: Mapped[List["MetricSnapshot"]] = relationship(
        "MetricSnapshot", back_populates="platform_account"
    )

    __table_args__ = (
        UniqueConstraint(
            "user_id", "platform", "platform_user_id",
            name="uq_platform_account_user_platform_id"
        ),
        Index("ix_platform_accounts_user_platform", "user_id", "platform"),
        Index("ix_platform_accounts_deleted", "deleted_at"),
    )

    @property
    def is_connected(self) -> bool:
        return self.status == ConnectionStatus.CONNECTED and self.deleted_at is None


# ─── Platform Credential Model ────────────────────────────────────────────────

class PlatformCredential(Base):
    """
    Encrypted platform credentials (API keys, secrets, tokens for non-OAuth flows).
    All values are Fernet-encrypted before storage.
    """
    __tablename__ = "platform_credentials"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    platform_account_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    credential_type: Mapped[str] = mapped_column(
        String(100), nullable=False
    )  # e.g., "api_key", "api_secret", "bearer_token", "webhook_secret"
    # Encrypted value (Fernet)
    encrypted_value: Mapped[str] = mapped_column(Text, nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    expires_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    platform_account: Mapped["PlatformAccount"] = relationship(
        "PlatformAccount", back_populates="credentials"
    )

    __table_args__ = (
        UniqueConstraint(
            "platform_account_id", "credential_type",
            name="uq_platform_credential_type"
        ),
    )


# ─── OAuth Token Model ────────────────────────────────────────────────────────

class OAuthToken(Base):
    """
    OAuth 2.0 tokens for a connected platform account.
    Sensitive fields (access_token, refresh_token) are Fernet-encrypted.
    """
    __tablename__ = "oauth_tokens"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    platform_account_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    grant_type: Mapped[OAuthGrantType] = mapped_column(
        Enum(OAuthGrantType), default=OAuthGrantType.AUTHORIZATION_CODE, nullable=False
    )

    # Encrypted token values
    encrypted_access_token: Mapped[str] = mapped_column(Text, nullable=False)
    encrypted_refresh_token: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    encrypted_id_token: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Token metadata (non-sensitive)
    token_type: Mapped[str] = mapped_column(String(50), default="Bearer", nullable=False)
    scope: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    access_token_expires_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True), nullable=True
    )
    refresh_token_expires_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True), nullable=True
    )
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False, index=True)

    # Platform-specific extras (e.g., LinkedIn member URN, Meta user token)
    extra_data: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    platform_account: Mapped["PlatformAccount"] = relationship(
        "PlatformAccount", back_populates="oauth_tokens"
    )

    __table_args__ = (
        Index("ix_oauth_tokens_active_account", "platform_account_id", "is_active"),
        Index("ix_oauth_tokens_expires", "access_token_expires_at"),
    )

    @property
    def is_expired(self) -> bool:
        if not self.access_token_expires_at:
            return False
        from datetime import timezone
        return datetime.now(timezone.utc) >= self.access_token_expires_at

    @property
    def can_refresh(self) -> bool:
        if not self.encrypted_refresh_token:
            return False
        if not self.refresh_token_expires_at:
            return True
        from datetime import timezone
        return datetime.now(timezone.utc) < self.refresh_token_expires_at


# ─── Social Account Model ─────────────────────────────────────────────────────

class SocialAccount(Base):
    """
    Sub-accounts / pages / channels tied to a PlatformAccount.
    Example: A Meta account may manage multiple Facebook Pages and Instagram accounts.
    """
    __tablename__ = "social_accounts"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    platform_account_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("platform_accounts.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    # Native identifiers on the platform
    native_id: Mapped[str] = mapped_column(String(255), nullable=False)
    native_name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    native_username: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    native_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    picture_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)

    account_type: Mapped[AccountType] = mapped_column(
        Enum(AccountType), default=AccountType.PAGE, nullable=False
    )
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    is_selected: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)

    # Page/channel-specific access token (encrypted if needed)
    encrypted_page_token: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Audience metrics cached at last sync
    follower_count: Mapped[int] = mapped_column(BigInteger, default=0, nullable=False)
    platform_metadata: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    platform_account: Mapped["PlatformAccount"] = relationship(
        "PlatformAccount", back_populates="social_accounts"
    )

    __table_args__ = (
        UniqueConstraint(
            "platform_account_id", "native_id",
            name="uq_social_account_platform_native_id"
        ),
    )
