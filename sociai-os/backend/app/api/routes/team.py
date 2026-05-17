"""
Team management routes for SociAI OS.

Endpoints:
  GET    /team/members                 – List team members
  POST   /team/members/invite          – Invite a team member
  GET    /team/members/{id}            – Get member profile
  PATCH  /team/members/{id}            – Update member role/permissions
  DELETE /team/members/{id}            – Remove a team member
  GET    /team/roles                   – List available roles and permissions
  POST   /team/roles                   – Create a custom role
  PATCH  /team/roles/{id}              – Update a custom role
  DELETE /team/roles/{id}              – Delete a custom role
  GET    /team/invitations             – List pending invitations
  DELETE /team/invitations/{id}        – Cancel an invitation
  POST   /team/invitations/{token}/accept – Accept an invitation
  GET    /team/approvals               – List pending content approvals
  POST   /team/approvals/{id}/approve  – Approve a content item
  POST   /team/approvals/{id}/reject   – Reject a content item
  GET    /team/notifications           – Get user notifications
  PATCH  /team/notifications/{id}/read – Mark notification as read
  POST   /team/notifications/read-all  – Mark all as read
  GET    /team/activity                – Team activity feed
"""
from __future__ import annotations

import logging
import secrets
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Query, status
from pydantic import BaseModel, EmailStr, Field
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

import redis.asyncio as aioredis

from app.api.deps import (
    get_current_active_user,
    get_db,
    get_pagination,
    get_redis,
    PaginationParams,
    require_role,
)

logger = logging.getLogger(__name__)
router = APIRouter()


# ─── Schemas ──────────────────────────────────────────────────────────────────

AVAILABLE_PERMISSIONS = [
    "content.create",
    "content.edit",
    "content.delete",
    "content.approve",
    "content.publish",
    "content.schedule",
    "analytics.view",
    "analytics.export",
    "platforms.connect",
    "platforms.disconnect",
    "campaigns.create",
    "campaigns.manage",
    "team.invite",
    "team.manage",
    "billing.view",
    "billing.manage",
    "settings.manage",
    "agents.trigger",
    "agents.manage",
    "strategy.view",
    "strategy.edit",
]

BUILT_IN_ROLES = {
    "owner": AVAILABLE_PERMISSIONS,
    "admin": AVAILABLE_PERMISSIONS,
    "manager": [
        "content.create", "content.edit", "content.approve", "content.publish",
        "content.schedule", "analytics.view", "analytics.export",
        "campaigns.create", "campaigns.manage", "team.invite",
        "agents.trigger", "strategy.view", "strategy.edit",
    ],
    "editor": [
        "content.create", "content.edit", "content.schedule",
        "analytics.view", "agents.trigger", "strategy.view",
    ],
    "analyst": ["analytics.view", "analytics.export", "strategy.view"],
    "viewer": ["analytics.view"],
}


class InviteMemberRequest(BaseModel):
    email: EmailStr
    role: str = Field(default="editor", description="owner|admin|manager|editor|analyst|viewer")
    custom_role_id: Optional[str] = None
    message: Optional[str] = Field(default=None, max_length=500)
    platforms_access: Optional[List[str]] = None  # restrict to specific platform accounts


class UpdateMemberRequest(BaseModel):
    role: Optional[str] = None
    custom_role_id: Optional[str] = None
    is_active: Optional[bool] = None
    permissions_override: Optional[List[str]] = None
    platforms_access: Optional[List[str]] = None


class CreateRoleRequest(BaseModel):
    name: str = Field(..., max_length=64)
    description: Optional[str] = None
    permissions: List[str] = Field(..., min_length=1)


class UpdateRoleRequest(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    permissions: Optional[List[str]] = None


class ApprovalActionRequest(BaseModel):
    comment: Optional[str] = None


class TeamMemberResponse(BaseModel):
    id: str
    user_id: str
    email: str
    full_name: str
    avatar_url: Optional[str]
    role: str
    custom_role_id: Optional[str]
    is_active: bool
    permissions: List[str]
    platforms_access: Optional[List[str]]
    last_active_at: Optional[datetime]
    joined_at: datetime


class RoleResponse(BaseModel):
    id: Optional[str]   # None for built-in roles
    name: str
    description: Optional[str]
    permissions: List[str]
    is_built_in: bool
    member_count: int


class InvitationResponse(BaseModel):
    id: str
    email: str
    role: str
    invited_by: str
    invite_token: str
    expires_at: datetime
    accepted: bool
    created_at: datetime


class ApprovalResponse(BaseModel):
    id: str
    post_id: str
    post_title: Optional[str]
    post_preview: str
    platform_accounts: List[str]
    scheduled_at: Optional[datetime]
    submitted_by: str
    submitted_at: datetime
    status: str   # pending|approved|rejected
    approved_by: Optional[str]
    action_at: Optional[datetime]
    comment: Optional[str]


class NotificationResponse(BaseModel):
    id: str
    notification_type: str
    title: str
    message: str
    is_read: bool
    link: Optional[str]
    metadata: Dict[str, Any]
    created_at: datetime


class ActivityEntry(BaseModel):
    id: str
    actor_name: str
    actor_avatar: Optional[str]
    action: str
    target_type: str
    target_id: Optional[str]
    target_name: Optional[str]
    details: Optional[Dict[str, Any]]
    created_at: datetime


# ─── Routes ───────────────────────────────────────────────────────────────────

@router.get(
    "/members",
    response_model=List[TeamMemberResponse],
    summary="List all team members",
)
async def list_members(
    role: Optional[str] = Query(default=None),
    is_active: Optional[bool] = Query(default=True),
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import TeamMember, User
    from sqlalchemy import and_

    filters = [TeamMember.organization_id == current_user.id]  # owner's ID as org
    if role:
        filters.append(TeamMember.role == role)
    if is_active is not None:
        filters.append(TeamMember.is_active == is_active)

    result = await db.execute(
        select(TeamMember, User)
        .join(User, User.id == TeamMember.user_id)
        .where(and_(*filters))
        .order_by(TeamMember.created_at.asc())
    )
    rows = result.all()
    return [
        TeamMemberResponse(
            id=str(tm.id),
            user_id=str(tm.user_id),
            email=u.email,
            full_name=u.full_name,
            avatar_url=getattr(u, "avatar_url", None),
            role=tm.role,
            custom_role_id=str(tm.custom_role_id) if getattr(tm, "custom_role_id", None) else None,
            is_active=tm.is_active,
            permissions=BUILT_IN_ROLES.get(tm.role, []),
            platforms_access=getattr(tm, "platforms_access", None),
            last_active_at=getattr(u, "last_active_at", None),
            joined_at=tm.created_at,
        )
        for tm, u in rows
    ]


@router.post(
    "/members/invite",
    response_model=InvitationResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Invite a new team member by email",
)
async def invite_member(
    payload: InviteMemberRequest,
    background_tasks: BackgroundTasks,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    if payload.role not in BUILT_IN_ROLES and not payload.custom_role_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid role '{payload.role}'. Built-in roles: {list(BUILT_IN_ROLES.keys())}",
        )

    invite_token = secrets.token_urlsafe(32)
    expires_at = datetime.now(timezone.utc) + timedelta(days=7)

    # Store invitation in Redis
    import json
    invite_data = {
        "email": payload.email,
        "role": payload.role,
        "custom_role_id": payload.custom_role_id,
        "invited_by": str(current_user.id),
        "organization_id": str(current_user.id),
        "expires_at": expires_at.isoformat(),
        "platforms_access": payload.platforms_access,
    }
    await redis.setex(f"invitation:{invite_token}", 7 * 86400, json.dumps(invite_data))

    # Persist in DB
    from app.models.user import Role  # or a TeamInvitation model
    invitation_id = str(UUID(int=0))  # Would be real UUID from DB

    # Send email in background
    from app.services.auth_service import AuthService
    auth_svc = AuthService(db, redis)
    background_tasks.add_task(
        auth_svc.send_team_invitation_email,
        email=payload.email,
        invite_token=invite_token,
        inviter_name=current_user.full_name,
        role=payload.role,
        message=payload.message,
    )

    return InvitationResponse(
        id=invitation_id,
        email=payload.email,
        role=payload.role,
        invited_by=current_user.full_name,
        invite_token=invite_token,
        expires_at=expires_at,
        accepted=False,
        created_at=datetime.now(timezone.utc),
    )


@router.get(
    "/members/{member_id}",
    response_model=TeamMemberResponse,
    summary="Get a specific team member",
)
async def get_member(
    member_id: UUID,
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import TeamMember, User

    result = await db.execute(
        select(TeamMember, User)
        .join(User, User.id == TeamMember.user_id)
        .where(TeamMember.id == member_id)
    )
    row = result.first()
    if not row:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Team member not found")
    tm, u = row
    return TeamMemberResponse(
        id=str(tm.id),
        user_id=str(tm.user_id),
        email=u.email,
        full_name=u.full_name,
        avatar_url=getattr(u, "avatar_url", None),
        role=tm.role,
        custom_role_id=str(tm.custom_role_id) if getattr(tm, "custom_role_id", None) else None,
        is_active=tm.is_active,
        permissions=BUILT_IN_ROLES.get(tm.role, []),
        platforms_access=getattr(tm, "platforms_access", None),
        last_active_at=getattr(u, "last_active_at", None),
        joined_at=tm.created_at,
    )


@router.patch(
    "/members/{member_id}",
    response_model=TeamMemberResponse,
    summary="Update a team member's role or permissions",
)
async def update_member(
    member_id: UUID,
    payload: UpdateMemberRequest,
    current_user=Depends(require_role("admin", "owner")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import TeamMember, User

    result = await db.execute(
        select(TeamMember, User)
        .join(User, User.id == TeamMember.user_id)
        .where(TeamMember.id == member_id)
    )
    row = result.first()
    if not row:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Team member not found")
    tm, u = row

    if payload.role:
        if payload.role not in BUILT_IN_ROLES:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Invalid role: {payload.role}")
        tm.role = payload.role
    if payload.is_active is not None:
        tm.is_active = payload.is_active
    if payload.platforms_access is not None:
        tm.platforms_access = payload.platforms_access
    db.add(tm)
    await db.flush()

    return TeamMemberResponse(
        id=str(tm.id),
        user_id=str(tm.user_id),
        email=u.email,
        full_name=u.full_name,
        avatar_url=getattr(u, "avatar_url", None),
        role=tm.role,
        custom_role_id=str(tm.custom_role_id) if getattr(tm, "custom_role_id", None) else None,
        is_active=tm.is_active,
        permissions=BUILT_IN_ROLES.get(tm.role, []),
        platforms_access=getattr(tm, "platforms_access", None),
        last_active_at=getattr(u, "last_active_at", None),
        joined_at=tm.created_at,
    )


@router.delete(
    "/members/{member_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Remove a team member",
)
async def remove_member(
    member_id: UUID,
    current_user=Depends(require_role("admin", "owner")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import TeamMember

    result = await db.execute(
        select(TeamMember).where(TeamMember.id == member_id)
    )
    member = result.scalar_one_or_none()
    if not member:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Team member not found")
    if str(member.user_id) == str(current_user.id):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Cannot remove yourself")
    member.is_active = False
    db.add(member)
    await db.flush()


# ── Roles ─────────────────────────────────────────────────────────────────────

@router.get(
    "/roles",
    response_model=List[RoleResponse],
    summary="List all roles and their permissions",
)
async def list_roles(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    built_in = [
        RoleResponse(
            id=None,
            name=role,
            description=f"Built-in {role} role",
            permissions=perms,
            is_built_in=True,
            member_count=0,
        )
        for role, perms in BUILT_IN_ROLES.items()
    ]
    from app.models.user import Role
    result = await db.execute(
        select(Role).where(Role.user_id == current_user.id)
    )
    custom_roles = result.scalars().all()
    custom = [
        RoleResponse(
            id=str(r.id),
            name=r.name,
            description=getattr(r, "description", None),
            permissions=getattr(r, "permissions", None) or [],
            is_built_in=False,
            member_count=0,
        )
        for r in custom_roles
    ]
    return built_in + custom


@router.post(
    "/roles",
    response_model=RoleResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Create a custom role",
)
async def create_role(
    payload: CreateRoleRequest,
    current_user=Depends(require_role("admin", "owner")),
    db: AsyncSession = Depends(get_db),
):
    invalid = [p for p in payload.permissions if p not in AVAILABLE_PERMISSIONS]
    if invalid:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid permissions: {invalid}. Available: {AVAILABLE_PERMISSIONS}",
        )
    from app.models.user import Role
    role = Role(
        user_id=current_user.id,
        name=payload.name,
        description=payload.description,
        permissions=payload.permissions,
    )
    db.add(role)
    await db.flush()
    return RoleResponse(
        id=str(role.id),
        name=role.name,
        description=getattr(role, "description", None),
        permissions=payload.permissions,
        is_built_in=False,
        member_count=0,
    )


@router.patch("/roles/{role_id}", response_model=RoleResponse, summary="Update a custom role")
async def update_role(
    role_id: UUID,
    payload: UpdateRoleRequest,
    current_user=Depends(require_role("admin", "owner")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import Role

    result = await db.execute(
        select(Role).where(Role.id == role_id, Role.user_id == current_user.id)
    )
    role = result.scalar_one_or_none()
    if not role:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Role not found")
    if payload.name:
        role.name = payload.name
    if payload.description is not None:
        role.description = payload.description
    if payload.permissions:
        invalid = [p for p in payload.permissions if p not in AVAILABLE_PERMISSIONS]
        if invalid:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Invalid permissions: {invalid}")
        role.permissions = payload.permissions
    db.add(role)
    await db.flush()
    return RoleResponse(
        id=str(role.id),
        name=role.name,
        description=getattr(role, "description", None),
        permissions=getattr(role, "permissions", []),
        is_built_in=False,
        member_count=0,
    )


@router.delete("/roles/{role_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Delete a custom role")
async def delete_role(
    role_id: UUID,
    current_user=Depends(require_role("admin", "owner")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.user import Role

    result = await db.execute(
        select(Role).where(Role.id == role_id, Role.user_id == current_user.id)
    )
    role = result.scalar_one_or_none()
    if not role:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Role not found")
    await db.delete(role)
    await db.flush()


# ── Invitations ───────────────────────────────────────────────────────────────

@router.get("/invitations", response_model=List[InvitationResponse], summary="List pending invitations")
async def list_invitations(
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    return []


@router.delete("/invitations/{invitation_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Cancel a pending invitation")
async def cancel_invitation(
    invitation_id: str,
    current_user=Depends(require_role("admin", "owner", "manager")),
    redis: aioredis.Redis = Depends(get_redis),
):
    # Would delete from DB and Redis
    pass


@router.post(
    "/invitations/{token}/accept",
    summary="Accept a team invitation",
    status_code=status.HTTP_200_OK,
)
async def accept_invitation(
    token: str,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    import json
    invite_data = await redis.get(f"invitation:{token}")
    if not invite_data:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid or expired invitation token")

    data = json.loads(invite_data)
    if data["email"] != current_user.email:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Invitation was not sent to your email")

    from app.models.user import TeamMember
    member = TeamMember(
        user_id=current_user.id,
        organization_id=data["organization_id"],
        role=data["role"],
        is_active=True,
        platforms_access=data.get("platforms_access"),
    )
    db.add(member)
    await redis.delete(f"invitation:{token}")
    await db.flush()

    return {"detail": "Invitation accepted. Welcome to the team!", "role": data["role"]}


# ── Content Approvals ─────────────────────────────────────────────────────────

@router.get(
    "/approvals",
    response_model=List[ApprovalResponse],
    summary="List content items pending your approval",
)
async def list_approvals(
    status_filter: Optional[str] = Query(default="pending", alias="status"),
    platform: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
):
    from app.models.content import Post
    from sqlalchemy import and_

    filters = [Post.requires_approval == True]
    if status_filter == "pending":
        filters.append(Post.status == "pending_approval")
    elif status_filter == "approved":
        filters.append(Post.status.in_(["scheduled", "published"]))
    elif status_filter == "rejected":
        filters.append(Post.status == "rejected")

    result = await db.execute(
        select(Post)
        .where(and_(*filters))
        .order_by(Post.created_at.desc())
        .offset(pagination.offset)
        .limit(pagination.limit)
    )
    posts = result.scalars().all()
    return [
        ApprovalResponse(
            id=str(p.id),
            post_id=str(p.id),
            post_title=getattr(p, "title", None),
            post_preview=getattr(p, "content", "")[:200],
            platform_accounts=[],
            scheduled_at=getattr(p, "scheduled_at", None),
            submitted_by=str(p.user_id),
            submitted_at=p.created_at,
            status=getattr(p, "status", "pending_approval"),
            approved_by=str(p.approved_by) if getattr(p, "approved_by", None) else None,
            action_at=getattr(p, "approved_at", None),
            comment=getattr(p, "rejected_reason", None),
        )
        for p in posts
    ]


@router.post("/approvals/{post_id}/approve", summary="Approve a content item")
async def approve_content(
    post_id: UUID,
    payload: ApprovalActionRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    post = await svc.approve_post(
        post_id=str(post_id),
        approver_id=str(current_user.id),
        comment=payload.comment,
    )
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return {"success": True, "post_id": str(post_id), "status": post.status}


@router.post("/approvals/{post_id}/reject", summary="Reject a content item")
async def reject_content(
    post_id: UUID,
    payload: ApprovalActionRequest,
    current_user=Depends(require_role("admin", "owner", "manager", "editor")),
    db: AsyncSession = Depends(get_db),
    redis: aioredis.Redis = Depends(get_redis),
):
    from app.services.content_service import ContentService
    svc = ContentService(db, redis)
    post = await svc.reject_post(
        post_id=str(post_id),
        rejector_id=str(current_user.id),
        reason=payload.comment,
    )
    if not post:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Post not found")
    return {"success": True, "post_id": str(post_id), "status": post.status}


# ── Notifications ─────────────────────────────────────────────────────────────

@router.get(
    "/notifications",
    response_model=List[NotificationResponse],
    summary="Get user notifications",
)
async def list_notifications(
    unread_only: bool = Query(default=False),
    notification_type: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    return []


@router.patch(
    "/notifications/{notification_id}/read",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Mark a notification as read",
)
async def mark_notification_read(
    notification_id: UUID,
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    pass


@router.post(
    "/notifications/read-all",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Mark all notifications as read",
)
async def mark_all_notifications_read(
    current_user=Depends(get_current_active_user),
    db: AsyncSession = Depends(get_db),
):
    pass


# ── Activity Feed ─────────────────────────────────────────────────────────────

@router.get(
    "/activity",
    response_model=List[ActivityEntry],
    summary="Team activity feed",
)
async def get_team_activity(
    days: int = Query(default=7, ge=1, le=90),
    actor_id: Optional[str] = Query(default=None),
    action_type: Optional[str] = Query(default=None),
    pagination: PaginationParams = Depends(get_pagination),
    current_user=Depends(require_role("admin", "owner", "manager")),
    db: AsyncSession = Depends(get_db),
):
    return []
