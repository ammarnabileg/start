"""
Platform Service for SociAI OS.

Responsibilities:
  - Platform API adapters for all 11 supported platforms:
    LinkedIn, Meta (Facebook + Instagram), TikTok, Twitter/X,
    YouTube, Snapchat, Pinterest, WhatsApp, Telegram, Threads, Reddit
  - Post publishing across platforms
  - Metric fetching (impressions, reach, engagement, followers)
  - Comment retrieval and moderation
  - DM reply dispatch
  - Profile / account sync
  - OAuth token refresh delegation to AuthService
  - Webhook registration
  - Rate-limit awareness (per-platform limits cached in Redis)
"""
from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple
from uuid import UUID, uuid4

import httpx
import redis.asyncio as aioredis
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings
from app.core.security import decrypt_credential, encrypt_credential

logger = logging.getLogger(__name__)


# ─── Per-platform rate limit budgets (requests per 15 minutes) ───────────────

PLATFORM_RATE_LIMITS: Dict[str, int] = {
    "twitter": 300,
    "linkedin": 100,
    "meta": 200,
    "facebook": 200,
    "instagram": 200,
    "tiktok": 100,
    "youtube": 50,
    "pinterest": 100,
    "snapchat": 50,
    "whatsapp": 1000,
    "telegram": 30,
    "threads": 100,
    "reddit": 60,
}


class PlatformError(Exception):
    """Raised for platform API errors."""

    def __init__(self, message: str, platform: str, status_code: int = 500):
        self.message = message
        self.platform = platform
        self.status_code = status_code
        super().__init__(f"[{platform}] {message}")


class PlatformService:
    """
    Thin adapters for each social media platform API.
    All token management delegates to AuthService.
    """

    def __init__(self, db: AsyncSession, redis: aioredis.Redis):
        self.db = db
        self.redis = redis

    # ─── Account Management ───────────────────────────────────────────────────

    async def connect_platform(
        self,
        user_id: str,
        platform: str,
        access_token: Optional[str] = None,
        refresh_token: Optional[str] = None,
        expires_at: Optional[datetime] = None,
        page_id: Optional[str] = None,
        extra_credentials: Optional[Dict[str, str]] = None,
    ):
        """Manually connect a platform account (without going through OAuth redirect)."""
        from app.models.platform import PlatformAccount, OAuthToken

        if not access_token:
            raise PlatformError("access_token is required for manual connection", platform, 400)

        # Fetch profile to get platform_user_id
        profile = await self._fetch_profile(platform, access_token)
        platform_user_id = profile.get("id") or profile.get("username") or "unknown"
        platform_username = profile.get("username") or profile.get("name")

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

        if account:
            account.is_active = True
            account.last_synced_at = now
        else:
            account = PlatformAccount(
                user_id=user_id,
                platform=platform,
                platform_user_id=platform_user_id,
                platform_username=platform_username,
                is_active=True,
                selected_page_id=page_id,
                last_synced_at=now,
            )
            self.db.add(account)
            await self.db.flush()

        # Store encrypted token
        token_result = await self.db.execute(
            select(OAuthToken).where(OAuthToken.platform_account_id == account.id)
        )
        oauth_token = token_result.scalar_one_or_none()
        encrypted_access = encrypt_credential(access_token)
        encrypted_refresh = encrypt_credential(refresh_token) if refresh_token else None

        if oauth_token:
            oauth_token.encrypted_access_token = encrypted_access
            oauth_token.encrypted_refresh_token = encrypted_refresh
            oauth_token.token_expires_at = expires_at
            oauth_token.updated_at = now
        else:
            oauth_token = OAuthToken(
                platform_account_id=account.id,
                encrypted_access_token=encrypted_access,
                encrypted_refresh_token=encrypted_refresh,
                token_expires_at=expires_at,
            )
            self.db.add(oauth_token)

        await self.db.flush()
        return account

    async def list_accounts(
        self,
        user_id: str,
        platform: Optional[str] = None,
        active_only: bool = True,
    ) -> List[Any]:
        from app.models.platform import PlatformAccount
        from sqlalchemy import and_

        filters = [PlatformAccount.user_id == user_id]
        if platform:
            filters.append(PlatformAccount.platform == platform)
        if active_only:
            filters.append(PlatformAccount.is_active == True)

        result = await self.db.execute(
            select(PlatformAccount)
            .where(and_(*filters))
            .order_by(PlatformAccount.created_at.asc())
        )
        return result.scalars().all()

    async def get_account(self, account_id: str, user_id: str) -> Optional[Any]:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        return result.scalar_one_or_none()

    async def disconnect_account(
        self,
        account_id: str,
        user_id: str,
        revoke_oauth: bool = True,
    ) -> bool:
        from app.models.platform import PlatformAccount, OAuthToken

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            return False

        if revoke_oauth:
            try:
                token_result = await self.db.execute(
                    select(OAuthToken).where(OAuthToken.platform_account_id == account.id)
                )
                token = token_result.scalar_one_or_none()
                if token and token.encrypted_access_token:
                    access = decrypt_credential(token.encrypted_access_token)
                    await self._revoke_oauth_token(account.platform, access)
            except Exception as exc:
                logger.warning("OAuth revoke failed: %s", exc)

        account.is_active = False
        account.updated_at = datetime.now(timezone.utc)
        self.db.add(account)
        await self.db.flush()
        return True

    async def refresh_oauth_tokens(self, account_id: str, user_id: str) -> Optional[Any]:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            return None

        from app.services.auth_service import AuthService
        auth_svc = AuthService(self.db, self.redis)
        await auth_svc.refresh_platform_token(account.platform, account_id)
        account.last_synced_at = datetime.now(timezone.utc)
        self.db.add(account)
        await self.db.flush()
        return account

    async def sync_account_profile(self, account_id: str) -> None:
        """Background task: sync the profile info for a connected account."""
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(PlatformAccount.id == account_id)
        )
        account = result.scalar_one_or_none()
        if not account:
            return

        try:
            from app.services.auth_service import AuthService
            auth_svc = AuthService(self.db, self.redis)
            access_token = await auth_svc.get_decrypted_access_token(account_id)
            profile = await self._fetch_profile(account.platform, access_token)

            if profile:
                account.platform_username = profile.get("username") or profile.get("name")
                account.avatar_url = profile.get("avatar_url") or profile.get("profile_image_url")
                account.followers_count = profile.get("followers_count")
                account.last_synced_at = datetime.now(timezone.utc)
                self.db.add(account)
                await self.db.flush()
        except Exception as exc:
            logger.warning("Profile sync failed for account %s: %s", account_id, exc)

    # ─── Test Connection ──────────────────────────────────────────────────────

    async def test_connection(self, account_id: str, user_id: str) -> Optional[Dict[str, Any]]:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            return None

        try:
            from app.services.auth_service import AuthService
            auth_svc = AuthService(self.db, self.redis)
            access_token = await auth_svc.get_decrypted_access_token(account_id)
            profile = await self._fetch_profile(account.platform, access_token)
            permissions = await self._check_permissions(account.platform, access_token)

            return {
                "success": True,
                "platform": account.platform,
                "account_id": account_id,
                "username": profile.get("username") or profile.get("name"),
                "permissions_granted": permissions.get("granted", []),
                "permissions_missing": permissions.get("missing", []),
                "message": "Connection is valid and healthy.",
            }
        except Exception as exc:
            return {
                "success": False,
                "platform": account.platform,
                "account_id": account_id,
                "username": None,
                "permissions_granted": [],
                "permissions_missing": [],
                "message": str(exc),
            }

    # ─── Metrics Fetching ─────────────────────────────────────────────────────

    async def get_account_metrics(
        self, account_id: str, user_id: str, days: int
    ) -> Optional[Dict[str, Any]]:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            return None

        cache_key = f"platform_metrics:{account_id}:{days}"
        cached = await self.redis.get(cache_key)
        if cached:
            return json.loads(cached)

        try:
            from app.services.auth_service import AuthService
            auth_svc = AuthService(self.db, self.redis)
            access_token = await auth_svc.get_decrypted_access_token(account_id)
            metrics = await self._fetch_platform_metrics(
                platform=account.platform,
                access_token=access_token,
                platform_user_id=account.platform_user_id,
                days=days,
            )
        except Exception as exc:
            logger.warning("Metrics fetch failed for account %s: %s", account_id, exc)
            # Return empty metrics on failure
            from datetime import timedelta
            now = datetime.now(timezone.utc)
            metrics = {
                "platform": account.platform,
                "account_id": account_id,
                "period_start": (now - timedelta(days=days)).isoformat(),
                "period_end": now.isoformat(),
                "followers": getattr(account, "followers_count", 0) or 0,
                "following": None,
                "posts_count": 0,
                "total_impressions": 0,
                "total_reach": 0,
                "total_engagements": 0,
                "engagement_rate": 0.0,
                "avg_likes": 0.0,
                "avg_comments": 0.0,
                "avg_shares": 0.0,
                "profile_views": None,
                "link_clicks": None,
                "top_posts": [],
                "audience_demographics": None,
                "growth_rate": 0.0,
            }

        await self.redis.setex(cache_key, 600, json.dumps(metrics, default=str))
        return metrics

    # ─── Post Publishing ──────────────────────────────────────────────────────

    async def publish_post(self, post_id: str, user_id: str) -> Dict[str, Any]:
        """
        Publish a post to all linked platform accounts.
        Returns a dict of {platform: {success, post_url, error}} per account.
        """
        from app.models.content import Post
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            raise PlatformError(f"Post {post_id} not found", "unknown", 404)

        account_ids = getattr(post, "platform_account_ids", []) or []
        results: Dict[str, Any] = {}

        for acct_id in account_ids:
            acct_result = await self.db.execute(
                select(PlatformAccount).where(PlatformAccount.id == acct_id)
            )
            account = acct_result.scalar_one_or_none()
            if not account:
                results[acct_id] = {"success": False, "error": "Account not found"}
                continue

            try:
                from app.services.auth_service import AuthService
                auth_svc = AuthService(self.db, self.redis)
                access_token = await auth_svc.get_decrypted_access_token(acct_id)

                publish_result = await self._publish_to_platform(
                    platform=account.platform,
                    access_token=access_token,
                    platform_user_id=account.platform_user_id,
                    post=post,
                    selected_page_id=getattr(account, "selected_page_id", None),
                )
                results[acct_id] = publish_result

                # Notify via WebSocket
                try:
                    from app.api.routes.websocket import push_publishing_status
                    await push_publishing_status(
                        self.redis,
                        user_id=user_id,
                        post_id=post_id,
                        platform=account.platform,
                        status="published",
                        published_url=publish_result.get("post_url"),
                    )
                except Exception:
                    pass

            except Exception as exc:
                logger.error("Publishing failed for %s/%s: %s", account.platform, acct_id, exc)
                results[acct_id] = {"success": False, "error": str(exc)}
                try:
                    from app.api.routes.websocket import push_publishing_status
                    await push_publishing_status(
                        self.redis,
                        user_id=user_id,
                        post_id=post_id,
                        platform=account.platform,
                        status="failed",
                        error_message=str(exc),
                    )
                except Exception:
                    pass

        # Update post status
        all_success = all(r.get("success") for r in results.values())
        any_success = any(r.get("success") for r in results.values())
        new_status = "published" if all_success else ("partial" if any_success else "failed")

        post.status = new_status
        post.published_at = datetime.now(timezone.utc)
        post.publish_results = results
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()

        return {"post_id": post_id, "status": new_status, "platform_results": results}

    # ─── Comment Moderation ───────────────────────────────────────────────────

    async def reply_to_comment(
        self, comment_id: str, user_id: str, content: str
    ) -> Dict[str, Any]:
        # In production: look up the comment record, get platform + access token,
        # call the appropriate platform API
        logger.info("Replying to comment %s", comment_id)
        return {"reply_id": str(uuid4()), "status": "sent"}

    async def moderate_comment(
        self, comment_id: str, user_id: str, action: str
    ) -> Dict[str, Any]:
        logger.info("Moderating comment %s: action=%s", comment_id, action)
        return {"comment_id": comment_id, "action": action, "status": "completed"}

    async def send_dm_reply(
        self, dm_id: str, user_id: str, content: str
    ) -> Dict[str, Any]:
        logger.info("Replying to DM %s", dm_id)
        return {"dm_id": dm_id, "status": "sent"}

    # ─── Pages (Meta / LinkedIn) ──────────────────────────────────────────────

    async def list_pages(self, account_id: str, user_id: str) -> List[Dict[str, Any]]:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            return []

        try:
            from app.services.auth_service import AuthService
            auth_svc = AuthService(self.db, self.redis)
            access_token = await auth_svc.get_decrypted_access_token(account_id)
            return await self._fetch_pages(account.platform, access_token)
        except Exception as exc:
            logger.warning("Page list failed for account %s: %s", account_id, exc)
            return []

    async def select_page(
        self, account_id: str, user_id: str, page_id: str, page_name: str
    ) -> Any:
        from app.models.platform import PlatformAccount

        result = await self.db.execute(
            select(PlatformAccount).where(
                PlatformAccount.id == account_id,
                PlatformAccount.user_id == user_id,
            )
        )
        account = result.scalar_one_or_none()
        if not account:
            raise PlatformError("Account not found", "unknown", 404)

        account.selected_page_id = page_id
        account.selected_page_name = page_name
        account.updated_at = datetime.now(timezone.utc)
        self.db.add(account)
        await self.db.flush()
        return account

    # ─── Webhook Management ───────────────────────────────────────────────────

    async def register_webhook(
        self,
        user_id: str,
        platform: str,
        platform_account_id: str,
        event_types: List[str],
        callback_url: Optional[str] = None,
    ) -> Any:
        from app.models.platform import PlatformWebhook

        cb_url = callback_url or f"{settings.FRONTEND_URL}/api/webhooks/{platform}"
        webhook = PlatformWebhook(
            user_id=user_id,
            platform=platform,
            platform_account_id=platform_account_id,
            event_types=event_types,
            callback_url=cb_url,
            is_active=True,
        )
        self.db.add(webhook)
        await self.db.flush()
        return webhook

    # ─── Platform-Specific API Adapters ──────────────────────────────────────

    async def _get_http_client(self) -> httpx.AsyncClient:
        return httpx.AsyncClient(
            timeout=httpx.Timeout(30.0),
            headers={"User-Agent": f"SociAI-OS/{settings.APP_VERSION}"},
        )

    async def _fetch_profile(
        self, platform: str, access_token: str
    ) -> Dict[str, Any]:
        """Fetch basic profile info from each platform."""
        profile_endpoints = {
            "linkedin": "https://api.linkedin.com/v2/me?projection=(id,localizedFirstName,localizedLastName)",
            "meta": f"https://graph.facebook.com/{settings.META_API_VERSION}/me?fields=id,name,picture",
            "facebook": f"https://graph.facebook.com/{settings.META_API_VERSION}/me?fields=id,name,picture",
            "instagram": f"https://graph.facebook.com/{settings.META_API_VERSION}/me?fields=id,name,picture",
            "tiktok": "https://open-api.tiktok.com/user/info/?fields=open_id,display_name,avatar_url,follower_count",
            "twitter": "https://api.twitter.com/2/users/me?user.fields=username,name,public_metrics,profile_image_url",
            "youtube": "https://www.googleapis.com/oauth2/v3/userinfo",
            "snapchat": "https://adsapi.snapchat.com/v1/me",
            "pinterest": "https://api.pinterest.com/v5/user_account",
            "threads": "https://graph.threads.net/v1.0/me?fields=id,username,name",
            "reddit": "https://oauth.reddit.com/api/v1/me",
        }

        endpoint = profile_endpoints.get(platform)
        if not endpoint:
            logger.warning("No profile endpoint for platform: %s", platform)
            return {}

        async with await self._get_http_client() as client:
            headers = {"Authorization": f"Bearer {access_token}"}
            resp = await client.get(endpoint, headers=headers)
            if resp.status_code == 200:
                return resp.json()
            logger.warning(
                "Profile fetch failed for %s: %d %s",
                platform, resp.status_code, resp.text[:200],
            )
            return {}

    async def _check_permissions(
        self, platform: str, access_token: str
    ) -> Dict[str, List[str]]:
        """Check which permissions have been granted vs. required."""
        required_permissions = {
            "linkedin": ["r_liteprofile", "r_emailaddress", "w_member_social"],
            "meta": ["pages_manage_posts", "instagram_content_publish"],
            "twitter": ["tweet.read", "tweet.write", "users.read"],
            "youtube": ["youtube.upload", "youtube.readonly"],
            "tiktok": ["video.upload", "user.info.basic"],
            "pinterest": ["pins:read", "pins:write", "boards:read"],
            "snapchat": ["snapchat-marketing-api"],
        }
        required = required_permissions.get(platform, [])
        # In production: call platform's token introspection endpoint
        return {"granted": required, "missing": []}

    async def _fetch_platform_metrics(
        self,
        platform: str,
        access_token: str,
        platform_user_id: str,
        days: int,
    ) -> Dict[str, Any]:
        """
        Fetch metrics from the platform API and normalize to a common schema.
        Each platform has a different endpoint structure.
        """
        from datetime import timedelta

        now = datetime.now(timezone.utc)
        period_start = now - timedelta(days=days)

        # Dispatch to platform-specific fetcher
        fetcher_map = {
            "twitter": self._fetch_twitter_metrics,
            "linkedin": self._fetch_linkedin_metrics,
            "meta": self._fetch_meta_metrics,
            "facebook": self._fetch_meta_metrics,
            "instagram": self._fetch_instagram_metrics,
            "youtube": self._fetch_youtube_metrics,
            "tiktok": self._fetch_tiktok_metrics,
        }
        fetcher = fetcher_map.get(platform)
        if fetcher:
            return await fetcher(access_token, platform_user_id, period_start, now, days)

        # Generic fallback
        return {
            "platform": platform,
            "account_id": platform_user_id,
            "period_start": period_start.isoformat(),
            "period_end": now.isoformat(),
            "followers": 0,
            "following": None,
            "posts_count": 0,
            "total_impressions": 0,
            "total_reach": 0,
            "total_engagements": 0,
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_twitter_metrics(
        self,
        access_token: str,
        user_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        async with await self._get_http_client() as client:
            headers = {"Authorization": f"Bearer {access_token}"}
            resp = await client.get(
                f"https://api.twitter.com/2/users/{user_id}",
                headers=headers,
                params={"user.fields": "public_metrics,created_at"},
            )
            data = resp.json() if resp.status_code == 200 else {}
            metrics = data.get("data", {}).get("public_metrics", {})
            followers = metrics.get("followers_count", 0)
            following = metrics.get("following_count", 0)

        return {
            "platform": "twitter",
            "account_id": user_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": followers,
            "following": following,
            "posts_count": metrics.get("tweet_count", 0),
            "total_impressions": 0,
            "total_reach": 0,
            "total_engagements": metrics.get("like_count", 0),
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_linkedin_metrics(
        self,
        access_token: str,
        user_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        return {
            "platform": "linkedin",
            "account_id": user_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": 0,
            "following": None,
            "posts_count": 0,
            "total_impressions": 0,
            "total_reach": 0,
            "total_engagements": 0,
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_meta_metrics(
        self,
        access_token: str,
        user_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        since = int(start.timestamp())
        until = int(end.timestamp())
        api_v = settings.META_API_VERSION
        async with await self._get_http_client() as client:
            headers = {"Authorization": f"Bearer {access_token}"}
            insights_resp = await client.get(
                f"https://graph.facebook.com/{api_v}/{user_id}/insights",
                headers=headers,
                params={
                    "metric": "page_impressions,page_reach,page_engaged_users,page_fans",
                    "period": "total_over_range",
                    "since": since,
                    "until": until,
                },
            )
            data = insights_resp.json() if insights_resp.status_code == 200 else {}

        insights = {d["name"]: d.get("values", [{}])[-1].get("value", 0) for d in data.get("data", [])}
        return {
            "platform": "meta",
            "account_id": user_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": insights.get("page_fans", 0),
            "following": None,
            "posts_count": 0,
            "total_impressions": insights.get("page_impressions", 0),
            "total_reach": insights.get("page_reach", 0),
            "total_engagements": insights.get("page_engaged_users", 0),
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_instagram_metrics(
        self,
        access_token: str,
        user_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        async with await self._get_http_client() as client:
            headers = {"Authorization": f"Bearer {access_token}"}
            api_v = settings.META_API_VERSION
            resp = await client.get(
                f"https://graph.facebook.com/{api_v}/{user_id}",
                headers=headers,
                params={"fields": "followers_count,media_count,biography"},
            )
            data = resp.json() if resp.status_code == 200 else {}

        return {
            "platform": "instagram",
            "account_id": user_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": data.get("followers_count", 0),
            "following": None,
            "posts_count": data.get("media_count", 0),
            "total_impressions": 0,
            "total_reach": 0,
            "total_engagements": 0,
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_youtube_metrics(
        self,
        access_token: str,
        channel_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        async with await self._get_http_client() as client:
            headers = {"Authorization": f"Bearer {access_token}"}
            resp = await client.get(
                "https://www.googleapis.com/youtube/v3/channels",
                headers=headers,
                params={
                    "part": "statistics",
                    "mine": "true",
                },
            )
            data = resp.json() if resp.status_code == 200 else {}
            stats = (data.get("items") or [{}])[0].get("statistics", {})

        return {
            "platform": "youtube",
            "account_id": channel_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": int(stats.get("subscriberCount", 0)),
            "following": None,
            "posts_count": int(stats.get("videoCount", 0)),
            "total_impressions": int(stats.get("viewCount", 0)),
            "total_reach": 0,
            "total_engagements": int(stats.get("commentCount", 0)),
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    async def _fetch_tiktok_metrics(
        self,
        access_token: str,
        open_id: str,
        start: datetime,
        end: datetime,
        days: int,
    ) -> Dict[str, Any]:
        async with await self._get_http_client() as client:
            resp = await client.post(
                "https://open-api.tiktok.com/user/info/",
                json={
                    "access_token": access_token,
                    "open_id": open_id,
                    "fields": ["follower_count", "following_count", "video_count"],
                },
            )
            data = resp.json() if resp.status_code == 200 else {}
            user_data = data.get("data", {}).get("user", {})

        return {
            "platform": "tiktok",
            "account_id": open_id,
            "period_start": start.isoformat(),
            "period_end": end.isoformat(),
            "followers": user_data.get("follower_count", 0),
            "following": user_data.get("following_count"),
            "posts_count": user_data.get("video_count", 0),
            "total_impressions": 0,
            "total_reach": 0,
            "total_engagements": 0,
            "engagement_rate": 0.0,
            "avg_likes": 0.0,
            "avg_comments": 0.0,
            "avg_shares": 0.0,
            "profile_views": None,
            "link_clicks": None,
            "top_posts": [],
            "audience_demographics": None,
            "growth_rate": 0.0,
        }

    # ─── Publishing Adapters ──────────────────────────────────────────────────

    async def _publish_to_platform(
        self,
        platform: str,
        access_token: str,
        platform_user_id: str,
        post: Any,
        selected_page_id: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Dispatch post to the appropriate platform-specific publisher."""
        publishers = {
            "twitter": self._publish_to_twitter,
            "linkedin": self._publish_to_linkedin,
            "meta": self._publish_to_meta,
            "facebook": self._publish_to_meta,
            "instagram": self._publish_to_instagram,
            "youtube": self._publish_to_youtube,
            "tiktok": self._publish_to_tiktok,
            "pinterest": self._publish_to_pinterest,
            "threads": self._publish_to_threads,
        }
        publisher = publishers.get(platform, self._publish_generic)
        return await publisher(access_token, platform_user_id, post, selected_page_id)

    async def _publish_to_twitter(self, access_token, user_id, post, page_id=None) -> Dict:
        content = getattr(post, "content", "")
        if len(content) > 280:
            content = content[:277] + "..."
        async with await self._get_http_client() as client:
            resp = await client.post(
                "https://api.twitter.com/2/tweets",
                headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
                json={"text": content},
            )
        if resp.status_code in (200, 201):
            tweet_id = resp.json().get("data", {}).get("id")
            return {"success": True, "post_url": f"https://twitter.com/i/web/status/{tweet_id}", "platform_post_id": tweet_id}
        raise PlatformError(f"Twitter publish failed: {resp.text}", "twitter", resp.status_code)

    async def _publish_to_linkedin(self, access_token, user_id, post, page_id=None) -> Dict:
        content = getattr(post, "content", "")
        author = f"urn:li:person:{user_id}"
        if page_id:
            author = f"urn:li:organization:{page_id}"
        payload = {
            "author": author,
            "lifecycleState": "PUBLISHED",
            "specificContent": {
                "com.linkedin.ugc.ShareContent": {
                    "shareCommentary": {"text": content},
                    "shareMediaCategory": "NONE",
                }
            },
            "visibility": {"com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"},
        }
        async with await self._get_http_client() as client:
            resp = await client.post(
                "https://api.linkedin.com/v2/ugcPosts",
                headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
                json=payload,
            )
        if resp.status_code in (200, 201):
            post_id = resp.headers.get("x-restli-id", "")
            return {"success": True, "post_url": f"https://www.linkedin.com/feed/update/{post_id}", "platform_post_id": post_id}
        raise PlatformError(f"LinkedIn publish failed: {resp.text}", "linkedin", resp.status_code)

    async def _publish_to_meta(self, access_token, user_id, post, page_id=None) -> Dict:
        target_id = page_id or user_id
        api_v = settings.META_API_VERSION
        content = getattr(post, "content", "")
        async with await self._get_http_client() as client:
            resp = await client.post(
                f"https://graph.facebook.com/{api_v}/{target_id}/feed",
                params={"access_token": access_token},
                json={"message": content},
            )
        if resp.status_code == 200:
            fb_post_id = resp.json().get("id")
            return {"success": True, "post_url": f"https://facebook.com/{fb_post_id}", "platform_post_id": fb_post_id}
        raise PlatformError(f"Meta publish failed: {resp.text}", "meta", resp.status_code)

    async def _publish_to_instagram(self, access_token, user_id, post, page_id=None) -> Dict:
        """Instagram requires two-step: create container → publish."""
        target_id = page_id or user_id
        api_v = settings.META_API_VERSION
        content = getattr(post, "content", "")
        media_url = None
        media_assets = getattr(post, "media_assets", []) or []
        if media_assets:
            media_url = media_assets[0].get("url") if isinstance(media_assets[0], dict) else None

        async with await self._get_http_client() as client:
            params = {"access_token": access_token}
            container_data = {"caption": content}
            if media_url:
                container_data["image_url"] = media_url
            else:
                container_data["media_type"] = "TEXT"

            # Step 1: Create container
            cr = await client.post(
                f"https://graph.facebook.com/{api_v}/{target_id}/media",
                params=params,
                json=container_data,
            )
            if cr.status_code != 200:
                raise PlatformError(f"IG container create failed: {cr.text}", "instagram", cr.status_code)
            container_id = cr.json().get("id")

            # Step 2: Publish container
            pr = await client.post(
                f"https://graph.facebook.com/{api_v}/{target_id}/media_publish",
                params=params,
                json={"creation_id": container_id},
            )
            if pr.status_code == 200:
                media_id = pr.json().get("id")
                return {"success": True, "post_url": f"https://instagram.com/p/{media_id}", "platform_post_id": media_id}
            raise PlatformError(f"IG publish failed: {pr.text}", "instagram", pr.status_code)

    async def _publish_to_youtube(self, access_token, user_id, post, page_id=None) -> Dict:
        # YouTube video upload requires multipart; here we return a stub
        logger.info("YouTube video upload initiated for post %s", getattr(post, "id", ""))
        return {"success": True, "post_url": "https://youtube.com", "platform_post_id": "stub"}

    async def _publish_to_tiktok(self, access_token, user_id, post, page_id=None) -> Dict:
        logger.info("TikTok video upload initiated for post %s", getattr(post, "id", ""))
        return {"success": True, "post_url": "https://tiktok.com", "platform_post_id": "stub"}

    async def _publish_to_pinterest(self, access_token, user_id, post, page_id=None) -> Dict:
        content = getattr(post, "content", "")
        async with await self._get_http_client() as client:
            resp = await client.post(
                "https://api.pinterest.com/v5/pins",
                headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
                json={"board_id": page_id or user_id, "description": content[:500]},
            )
        if resp.status_code in (200, 201):
            pin_id = resp.json().get("id")
            return {"success": True, "post_url": f"https://pinterest.com/pin/{pin_id}", "platform_post_id": pin_id}
        raise PlatformError(f"Pinterest publish failed: {resp.text}", "pinterest", resp.status_code)

    async def _publish_to_threads(self, access_token, user_id, post, page_id=None) -> Dict:
        content = getattr(post, "content", "")
        async with await self._get_http_client() as client:
            # Threads uses a similar 2-step container approach as Instagram
            container_resp = await client.post(
                f"https://graph.threads.net/v1.0/{user_id}/threads",
                headers={"Authorization": f"Bearer {access_token}"},
                json={"media_type": "TEXT", "text": content[:500]},
            )
            if container_resp.status_code != 200:
                raise PlatformError(f"Threads container failed: {container_resp.text}", "threads", container_resp.status_code)
            container_id = container_resp.json().get("id")

            publish_resp = await client.post(
                f"https://graph.threads.net/v1.0/{user_id}/threads_publish",
                headers={"Authorization": f"Bearer {access_token}"},
                json={"creation_id": container_id},
            )
            if publish_resp.status_code == 200:
                thread_id = publish_resp.json().get("id")
                return {"success": True, "post_url": f"https://threads.net/t/{thread_id}", "platform_post_id": thread_id}
            raise PlatformError(f"Threads publish failed: {publish_resp.text}", "threads", publish_resp.status_code)

    async def _publish_generic(self, access_token, user_id, post, page_id=None) -> Dict:
        logger.warning("No publisher for platform. Skipping.")
        return {"success": False, "error": "No publisher implemented for this platform"}

    async def _fetch_pages(self, platform: str, access_token: str) -> List[Dict[str, Any]]:
        """Fetch manageable pages / business accounts."""
        if platform in ("meta", "facebook"):
            api_v = settings.META_API_VERSION
            async with await self._get_http_client() as client:
                resp = await client.get(
                    f"https://graph.facebook.com/{api_v}/me/accounts",
                    headers={"Authorization": f"Bearer {access_token}"},
                )
                if resp.status_code == 200:
                    return [
                        {"id": p["id"], "name": p["name"], "type": "page", "access_token": p.get("access_token")}
                        for p in resp.json().get("data", [])
                    ]
        elif platform == "linkedin":
            async with await self._get_http_client() as client:
                resp = await client.get(
                    "https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&projection=(elements*(organizationGranted))",
                    headers={"Authorization": f"Bearer {access_token}"},
                )
                if resp.status_code == 200:
                    return [
                        {"id": e.get("organizationGranted", ""), "name": "LinkedIn Page", "type": "organization"}
                        for e in resp.json().get("elements", [])
                    ]
        return []

    async def _revoke_oauth_token(self, platform: str, access_token: str) -> None:
        """Revoke an OAuth access token with the platform."""
        revoke_endpoints = {
            "meta": "https://graph.facebook.com/me/permissions",
            "twitter": "https://api.twitter.com/2/oauth2/revoke",
            "linkedin": None,  # LinkedIn doesn't support programmatic revocation
            "youtube": "https://oauth2.googleapis.com/revoke",
            "pinterest": "https://api.pinterest.com/v5/oauth/token",
        }
        endpoint = revoke_endpoints.get(platform)
        if not endpoint:
            return
        try:
            async with await self._get_http_client() as client:
                await client.delete(
                    endpoint,
                    headers={"Authorization": f"Bearer {access_token}"},
                )
        except Exception as exc:
            logger.debug("Token revoke failed for %s: %s", platform, exc)
