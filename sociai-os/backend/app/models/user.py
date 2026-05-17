"""
app/models/user.py
──────────────────
User, Role, Permission, TeamMember, Session, LoginHistory, TwoFactorAuth models.
"""
from __future__ import annotations

import uuid
from datetime import datetime
from typing import TYPE_CHECKING, List, Optional

from sqlalchemy import (
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
from sqlalchemy.dialects.postgresql import ARRAY, JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.core.database import Base

if TYPE_CHECKING:
    from app.models.platform import PlatformAccount
    from app.models.content import ContentPiece, Campaign
    from app.models.strategy import MarketingStrategy
    from app.models.agent import AgentTask, AgentWorkflow


# ─── Enums ────────────────────────────────────────────────────────────────────

import enum


class UserStatus(str, enum.Enum):
    ACTIVE = "active"
    INACTIVE = "inactive"
    SUSPENDED = "suspended"
    PENDING_VERIFICATION = "pending_verification"


class SubscriptionTier(str, enum.Enum):
    FREE = "free"
    STARTER = "starter"
    PROFESSIONAL = "professional"
    ENTERPRISE = "enterprise"


class TeamRole(str, enum.Enum):
    OWNER = "owner"
    ADMIN = "admin"
    EDITOR = "editor"
    ANALYST = "analyst"
    VIEWER = "viewer"


class LoginMethod(str, enum.Enum):
    EMAIL_PASSWORD = "email_password"
    GOOGLE = "google"
    GITHUB = "github"
    MICROSOFT = "microsoft"


# ─── Permission Model ─────────────────────────────────────────────────────────

class Permission(Base):
    __tablename__ = "permissions"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    name: Mapped[str] = mapped_column(String(100), unique=True, nullable=False, index=True)
    codename: Mapped[str] = mapped_column(String(100), unique=True, nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    resource: Mapped[str] = mapped_column(String(50), nullable=False)  # e.g. "content", "analytics"
    action: Mapped[str] = mapped_column(String(50), nullable=False)    # e.g. "read", "write", "delete"
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    # Relationships
    roles: Mapped[List["Role"]] = relationship(
        "Role", secondary="role_permissions", back_populates="permissions"
    )

    __table_args__ = (
        UniqueConstraint("resource", "action", name="uq_permission_resource_action"),
        Index("ix_permissions_resource", "resource"),
    )


# ─── Role Model ───────────────────────────────────────────────────────────────

class Role(Base):
    __tablename__ = "roles"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    name: Mapped[str] = mapped_column(String(100), unique=True, nullable=False, index=True)
    display_name: Mapped[str] = mapped_column(String(150), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    is_system: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    metadata_: Mapped[Optional[dict]] = mapped_column("metadata", JSONB, nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    permissions: Mapped[List[Permission]] = relationship(
        "Permission", secondary="role_permissions", back_populates="roles"
    )
    team_members: Mapped[List["TeamMember"]] = relationship("TeamMember", back_populates="role")


# ─── Role–Permission Association Table ────────────────────────────────────────

from sqlalchemy import Table, Column  # noqa: E402

role_permissions = Table(
    "role_permissions",
    Base.metadata,
    Column("role_id", Integer, ForeignKey("roles.id", ondelete="CASCADE"), primary_key=True),
    Column(
        "permission_id",
        Integer,
        ForeignKey("permissions.id", ondelete="CASCADE"),
        primary_key=True,
    ),
)


# ─── User Model ───────────────────────────────────────────────────────────────

class User(Base):
    __tablename__ = "users"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    email: Mapped[str] = mapped_column(String(255), unique=True, nullable=False, index=True)
    email_verified: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    hashed_password: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    login_method: Mapped[LoginMethod] = mapped_column(
        Enum(LoginMethod), default=LoginMethod.EMAIL_PASSWORD, nullable=False
    )

    # Profile
    full_name: Mapped[str] = mapped_column(String(255), nullable=False)
    display_name: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    avatar_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    bio: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    timezone: Mapped[str] = mapped_column(String(50), default="UTC", nullable=False)
    locale: Mapped[str] = mapped_column(String(10), default="en", nullable=False)

    # Account state
    status: Mapped[UserStatus] = mapped_column(
        Enum(UserStatus), default=UserStatus.PENDING_VERIFICATION, nullable=False, index=True
    )
    subscription_tier: Mapped[SubscriptionTier] = mapped_column(
        Enum(SubscriptionTier), default=SubscriptionTier.FREE, nullable=False
    )
    subscription_expires_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True), nullable=True
    )
    is_superuser: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)

    # Business context
    company_name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    website_url: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    industry: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)

    # Settings / preferences (flexible JSON blob)
    settings: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)
    notification_prefs: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True, default=dict)

    # Soft delete
    deleted_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)

    # Timestamps
    last_login_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    sessions: Mapped[List["UserSession"]] = relationship(
        "UserSession", back_populates="user", cascade="all, delete-orphan"
    )
    login_history: Mapped[List["LoginHistory"]] = relationship(
        "LoginHistory", back_populates="user", cascade="all, delete-orphan"
    )
    two_factor_auth: Mapped[Optional["TwoFactorAuth"]] = relationship(
        "TwoFactorAuth", back_populates="user", uselist=False, cascade="all, delete-orphan"
    )
    team_memberships: Mapped[List["TeamMember"]] = relationship(
        "TeamMember", foreign_keys="TeamMember.user_id", back_populates="user"
    )
    owned_teams: Mapped[List["TeamMember"]] = relationship(
        "TeamMember",
        foreign_keys="TeamMember.invited_by_id",
        back_populates="invited_by",
        overlaps="team_memberships",
    )
    platform_accounts: Mapped[List["PlatformAccount"]] = relationship(
        "PlatformAccount", back_populates="user", cascade="all, delete-orphan"
    )
    content_pieces: Mapped[List["ContentPiece"]] = relationship(
        "ContentPiece", back_populates="creator"
    )
    campaigns: Mapped[List["Campaign"]] = relationship(
        "Campaign", back_populates="owner"
    )
    strategies: Mapped[List["MarketingStrategy"]] = relationship(
        "MarketingStrategy", back_populates="owner"
    )
    agent_tasks: Mapped[List["AgentTask"]] = relationship(
        "AgentTask", back_populates="created_by"
    )

    __table_args__ = (
        Index("ix_users_email_status", "email", "status"),
        Index("ix_users_deleted_at", "deleted_at"),
    )

    @property
    def is_active(self) -> bool:
        return self.status == UserStatus.ACTIVE and self.deleted_at is None

    @property
    def is_deleted(self) -> bool:
        return self.deleted_at is not None


# ─── Team Member Model ────────────────────────────────────────────────────────

class TeamMember(Base):
    __tablename__ = "team_members"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    # owner_id references the workspace owner (a User row)
    owner_user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    role_id: Mapped[int] = mapped_column(
        Integer, ForeignKey("roles.id", ondelete="RESTRICT"), nullable=False
    )
    team_role: Mapped[TeamRole] = mapped_column(
        Enum(TeamRole), default=TeamRole.VIEWER, nullable=False
    )
    invited_by_id: Mapped[Optional[uuid.UUID]] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    invite_token: Mapped[Optional[str]] = mapped_column(String(255), nullable=True, unique=True)
    invite_accepted_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime(timezone=True), nullable=True
    )
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    custom_permissions: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship(
        "User", foreign_keys=[user_id], back_populates="team_memberships"
    )
    owner: Mapped["User"] = relationship("User", foreign_keys=[owner_user_id])
    invited_by: Mapped[Optional["User"]] = relationship(
        "User", foreign_keys=[invited_by_id], back_populates="owned_teams"
    )
    role: Mapped["Role"] = relationship("Role", back_populates="team_members")

    __table_args__ = (
        UniqueConstraint("user_id", "owner_user_id", name="uq_team_member_workspace"),
        Index("ix_team_members_owner", "owner_user_id"),
    )


# ─── User Session Model ───────────────────────────────────────────────────────

class UserSession(Base):
    __tablename__ = "user_sessions"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    refresh_token_jti: Mapped[str] = mapped_column(
        String(255), unique=True, nullable=False, index=True
    )
    user_agent: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    ip_address: Mapped[Optional[str]] = mapped_column(String(45), nullable=True)  # IPv6 max length
    device_type: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)
    os: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    browser: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    location: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    is_revoked: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    last_used_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship("User", back_populates="sessions")

    __table_args__ = (
        Index("ix_user_sessions_user_id_revoked", "user_id", "is_revoked"),
        Index("ix_user_sessions_expires_at", "expires_at"),
    )


# ─── Login History Model ──────────────────────────────────────────────────────

class LoginHistory(Base):
    __tablename__ = "login_history"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    login_method: Mapped[LoginMethod] = mapped_column(Enum(LoginMethod), nullable=False)
    success: Mapped[bool] = mapped_column(Boolean, nullable=False)
    failure_reason: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    ip_address: Mapped[Optional[str]] = mapped_column(String(45), nullable=True)
    user_agent: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)
    location: Mapped[Optional[dict]] = mapped_column(JSONB, nullable=True)
    mfa_used: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship("User", back_populates="login_history")

    __table_args__ = (
        Index("ix_login_history_user_created", "user_id", "created_at"),
    )


# ─── Two-Factor Auth Model ────────────────────────────────────────────────────

class TwoFactorAuth(Base):
    __tablename__ = "two_factor_auth"

    id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), primary_key=True, default=uuid.uuid4
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("users.id", ondelete="CASCADE"),
        unique=True,
        nullable=False,
        index=True,
    )
    is_enabled: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    # Encrypted TOTP secret (via Fernet)
    totp_secret_encrypted: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    # Hashed backup codes stored as a JSON list
    backup_codes_hashed: Mapped[Optional[list]] = mapped_column(JSONB, nullable=True)
    backup_codes_remaining: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    confirmed_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    last_used_at: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    # Relationships
    user: Mapped["User"] = relationship("User", back_populates="two_factor_auth")
