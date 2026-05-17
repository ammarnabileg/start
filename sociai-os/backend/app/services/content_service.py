"""
Content Service for SociAI OS.

Responsibilities:
  - CRUD for posts, media assets, captions, content pieces
  - AI content generation (via OpenAI / Anthropic)
  - Cross-platform content reformatting
  - Scheduling (delegates timing logic to SchedulerService)
  - Approval workflow state transitions
  - A/B test setup and tracking
  - Calendar view construction
  - Media upload to S3 / compatible storage
  - Strategy document storage
"""
from __future__ import annotations

import hashlib
import io
import json
import logging
import re
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple
from uuid import UUID, uuid4

import redis.asyncio as aioredis
from sqlalchemy import and_, func, or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings

logger = logging.getLogger(__name__)


# ─── Platform Character Limits ────────────────────────────────────────────────

PLATFORM_LIMITS: Dict[str, Dict[str, Any]] = {
    "twitter": {"caption": 280, "image_count": 4, "video": True, "threads": True},
    "linkedin": {"caption": 3000, "image_count": 9, "video": True, "articles": True},
    "instagram": {"caption": 2200, "image_count": 10, "video": True, "reels": True, "stories": True},
    "facebook": {"caption": 63206, "image_count": 10, "video": True},
    "meta": {"caption": 2200, "image_count": 10, "video": True},
    "tiktok": {"caption": 2200, "video_only": True},
    "youtube": {"caption": 5000, "video_only": True, "shorts": True},
    "pinterest": {"caption": 500, "image_required": True, "boards": True},
    "snapchat": {"caption": 250, "image_required": True, "stories": True},
    "threads": {"caption": 500, "image_count": 10},
    "reddit": {"caption": 10000, "link_posts": True, "text_posts": True},
}


class ContentService:
    """
    All content management operations for SociAI OS.
    """

    def __init__(self, db: AsyncSession, redis: aioredis.Redis):
        self.db = db
        self.redis = redis

    # ─── AI Content Generation ────────────────────────────────────────────────

    async def generate_content(
        self,
        user_id: str,
        topic: str,
        content_type: str,
        platforms: List[str],
        tone: Optional[str] = None,
        content_pillar_id: Optional[str] = None,
        include_hashtags: bool = True,
        include_emojis: bool = True,
        include_cta: bool = True,
        target_audience: Optional[str] = None,
        max_length: Optional[int] = None,
        reference_urls: Optional[List[str]] = None,
        style_examples: Optional[List[str]] = None,
        language: str = "en",
        variations_count: int = 1,
    ) -> Dict[str, Any]:
        """
        Generate platform-optimised content using the configured LLM.
        Returns variations, platform adaptations, and hashtag suggestions.
        """
        # Load brand guidelines + content pillar for context
        brand_context = await self._load_brand_context(user_id, content_pillar_id)

        prompt = self._build_generation_prompt(
            topic=topic,
            content_type=content_type,
            platforms=platforms,
            tone=tone or brand_context.get("brand_voice", "professional"),
            include_hashtags=include_hashtags,
            include_emojis=include_emojis,
            include_cta=include_cta,
            target_audience=target_audience or brand_context.get("target_audience"),
            max_length=max_length,
            brand_context=brand_context,
            language=language,
            variations_count=variations_count,
        )

        llm_response = await self._call_llm(prompt)
        parsed = self._parse_llm_response(llm_response, platforms)

        # Compute engagement preview / readability
        for variation in parsed.get("variations", []):
            variation["readability_score"] = self._compute_readability(variation.get("content", ""))
            variation["estimated_char_counts"] = {
                p: len(variation.get("content", "")) for p in platforms
            }

        return parsed

    async def _load_brand_context(self, user_id: str, content_pillar_id: Optional[str]) -> Dict[str, Any]:
        cache_key = f"brand_context:{user_id}"
        cached = await self.redis.get(cache_key)
        if cached:
            return json.loads(cached)

        from app.models.strategy import BrandGuideline

        result = await self.db.execute(
            select(BrandGuideline).where(BrandGuideline.user_id == user_id).limit(1)
        )
        guideline = result.scalar_one_or_none()

        context = {}
        if guideline:
            context = {
                "brand_name": getattr(guideline, "brand_name", None),
                "brand_voice": getattr(guideline, "brand_voice", "professional"),
                "brand_values": getattr(guideline, "brand_values", []),
                "dos": getattr(guideline, "dos", []),
                "donts": getattr(guideline, "donts", []),
                "hashtag_sets": getattr(guideline, "hashtag_sets", {}),
                "emoji_policy": getattr(guideline, "emoji_policy", None),
            }

        if content_pillar_id:
            from app.models.content import ContentPillar
            p_result = await self.db.execute(
                select(ContentPillar).where(ContentPillar.id == content_pillar_id)
            )
            pillar = p_result.scalar_one_or_none()
            if pillar:
                context["pillar_name"] = pillar.name
                context["pillar_description"] = getattr(pillar, "description", None)
                context["sample_topics"] = getattr(pillar, "sample_topics", [])

        await self.redis.setex(cache_key, 300, json.dumps(context))
        return context

    def _build_generation_prompt(
        self,
        topic: str,
        content_type: str,
        platforms: List[str],
        tone: str,
        include_hashtags: bool,
        include_emojis: bool,
        include_cta: bool,
        target_audience: Optional[str],
        max_length: Optional[int],
        brand_context: Dict[str, Any],
        language: str,
        variations_count: int,
    ) -> str:
        platform_constraints = "\n".join([
            f"- {p}: max {PLATFORM_LIMITS.get(p, {}).get('caption', 2200)} characters"
            for p in platforms
        ])
        brand_name = brand_context.get("brand_name", "our brand")
        brand_voice = brand_context.get("brand_voice", tone)
        donts = "\n".join(f"  - Don't: {d}" for d in brand_context.get("donts", []))

        return f"""You are a world-class social media content strategist for {brand_name}.

**Task**: Generate {variations_count} variation(s) of a {content_type} about:
"{topic}"

**Target Platforms**: {', '.join(platforms)}
**Platform Character Limits**:
{platform_constraints}

**Brand Voice**: {brand_voice}
**Language**: {language}
**Target Audience**: {target_audience or "general audience"}
{"**Include hashtags**: Yes" if include_hashtags else ""}
{"**Include emojis**: Yes (where appropriate)" if include_emojis else "No emojis"}
{"**Include CTA**: Yes" if include_cta else ""}
{f"**Max length**: {max_length} characters" if max_length else ""}
{donts}

**Output format** (valid JSON only):
{{
  "variations": [
    {{
      "content": "<the main post text>",
      "hashtags": ["hashtag1", "hashtag2"],
      "emojis_used": ["emoji1"],
      "cta": "<call to action text>",
      "platform_adaptations": {{
        "twitter": "<twitter-specific version (max 280 chars)>",
        "linkedin": "<linkedin version (professional tone)>",
        "instagram": "<instagram version with hashtags block>"
      }}
    }}
  ],
  "global_hashtag_suggestions": ["#tag1", "#tag2", "#tag3"],
  "content_themes": ["theme1", "theme2"],
  "suggested_media": "<description of ideal accompanying image/video>"
}}"""

    async def _call_llm(self, prompt: str) -> str:
        """Call the configured LLM (OpenAI GPT-4o or Anthropic Claude)."""
        # Check Redis cache first (semantic caching)
        cache_key = f"llm_cache:{hashlib.md5(prompt.encode()).hexdigest()}"
        cached = await self.redis.get(cache_key)
        if cached:
            return cached

        response_text = ""

        if settings.OPENAI_API_KEY:
            try:
                import openai
                client = openai.AsyncOpenAI(api_key=settings.OPENAI_API_KEY)
                response = await client.chat.completions.create(
                    model=settings.OPENAI_DEFAULT_MODEL,
                    messages=[
                        {"role": "system", "content": "You are an expert social media strategist. Always respond with valid JSON."},
                        {"role": "user", "content": prompt},
                    ],
                    max_tokens=settings.OPENAI_MAX_TOKENS,
                    temperature=settings.OPENAI_TEMPERATURE,
                    response_format={"type": "json_object"},
                )
                response_text = response.choices[0].message.content
            except Exception as exc:
                logger.warning("OpenAI call failed: %s", exc)

        if not response_text and settings.ANTHROPIC_API_KEY:
            try:
                import anthropic
                client = anthropic.AsyncAnthropic(api_key=settings.ANTHROPIC_API_KEY)
                message = await client.messages.create(
                    model=settings.ANTHROPIC_DEFAULT_MODEL,
                    max_tokens=settings.ANTHROPIC_MAX_TOKENS,
                    messages=[{"role": "user", "content": f"Respond with valid JSON only.\n\n{prompt}"}],
                )
                response_text = message.content[0].text
            except Exception as exc:
                logger.warning("Anthropic call failed: %s", exc)

        if not response_text:
            # Fallback stub
            response_text = json.dumps({
                "variations": [{
                    "content": f"Check out our latest insights on {prompt[:50]}... [AI generation unavailable]",
                    "hashtags": [],
                    "emojis_used": [],
                    "cta": "Learn more",
                    "platform_adaptations": {},
                }],
                "global_hashtag_suggestions": [],
                "content_themes": [],
                "suggested_media": "Professional photo",
            })

        await self.redis.setex(cache_key, 3600, response_text)  # Cache 1hr
        return response_text

    def _parse_llm_response(self, raw: str, platforms: List[str]) -> Dict[str, Any]:
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            # Try to extract JSON from markdown code blocks
            match = re.search(r"```(?:json)?\s*([\s\S]+?)\s*```", raw)
            if match:
                data = json.loads(match.group(1))
            else:
                data = {"variations": [{"content": raw, "hashtags": [], "emojis_used": [], "cta": "", "platform_adaptations": {}}]}
        return {
            "variations": data.get("variations", []),
            "platform_adaptations": data.get("platform_adaptations", {}),
            "hashtag_suggestions": data.get("global_hashtag_suggestions", []),
            "content_score": None,
            "readability_score": None,
            "estimated_reach": {p: 0 for p in platforms},
        }

    def _compute_readability(self, text: str) -> float:
        """Simple Flesch-Kincaid approximation (0-100, higher = more readable)."""
        if not text:
            return 0.0
        words = text.split()
        if not words:
            return 0.0
        sentences = max(text.count(".") + text.count("!") + text.count("?"), 1)
        syllables = sum(
            max(1, len(re.findall(r"[aeiouAEIOU]", w))) for w in words
        )
        try:
            fk = 206.835 - 1.015 * (len(words) / sentences) - 84.6 * (syllables / len(words))
        except ZeroDivisionError:
            fk = 0.0
        return round(max(0.0, min(100.0, fk)), 2)

    # ─── Post CRUD ────────────────────────────────────────────────────────────

    async def create_post(self, user_id: str, content: str, platform_account_ids: List[str], **kwargs):
        """Create a new post record."""
        from app.models.content import Post
        post = Post(
            user_id=user_id,
            content=content,
            status="draft",
            platform_account_ids=platform_account_ids,
            **{k: v for k, v in kwargs.items() if v is not None},
        )
        self.db.add(post)
        await self.db.flush()
        return post

    async def get_post(self, post_id: str, user_id: str):
        """Retrieve a post by ID, scoped to user."""
        from app.models.content import Post
        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        return result.scalar_one_or_none()

    async def update_post(
        self, post_id: str, user_id: str, updates: Dict[str, Any]
    ):
        """Update specified fields on a post."""
        from app.models.content import Post
        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            return None
        for field, value in updates.items():
            setattr(post, field, value)
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()
        return post

    async def delete_post(self, post_id: str, user_id: str) -> bool:
        """Soft-delete a post (sets status to 'deleted')."""
        from app.models.content import Post
        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        post = result.scalar_one_or_none()
        if not post:
            return False
        if getattr(post, "status", "") == "published":
            raise ValueError("Cannot delete a published post. Archive it instead.")
        post.status = "deleted"
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()
        return True

    async def list_posts(
        self,
        user_id: str,
        status: Optional[str] = None,
        platform: Optional[str] = None,
        campaign_id: Optional[str] = None,
        content_pillar_id: Optional[str] = None,
        from_date: Optional[datetime] = None,
        to_date: Optional[datetime] = None,
        requires_approval: Optional[bool] = None,
        search: Optional[str] = None,
        offset: int = 0,
        limit: int = 20,
    ) -> Tuple[List[Any], int]:
        """List posts with dynamic filtering and total count."""
        from app.models.content import Post

        filters = [
            Post.user_id == user_id,
            Post.status != "deleted",
        ]
        if status:
            filters.append(Post.status == status)
        if campaign_id:
            filters.append(Post.campaign_id == campaign_id)
        if content_pillar_id:
            filters.append(Post.content_pillar_id == content_pillar_id)
        if from_date:
            filters.append(Post.scheduled_at >= from_date)
        if to_date:
            filters.append(Post.scheduled_at <= to_date)
        if requires_approval is not None:
            filters.append(Post.requires_approval == requires_approval)
        if search:
            filters.append(
                or_(
                    Post.content.ilike(f"%{search}%"),
                    Post.title.ilike(f"%{search}%"),
                )
            )

        count_result = await self.db.execute(
            select(func.count(Post.id)).where(and_(*filters))
        )
        total = count_result.scalar() or 0

        posts_result = await self.db.execute(
            select(Post)
            .where(and_(*filters))
            .order_by(Post.scheduled_at.desc().nullslast(), Post.created_at.desc())
            .offset(offset)
            .limit(limit)
        )
        posts = posts_result.scalars().all()
        return posts, total

    async def duplicate_post(self, post_id: str, user_id: str):
        """Create a copy of a post as a new draft."""
        from app.models.content import Post
        result = await self.db.execute(
            select(Post).where(Post.id == post_id, Post.user_id == user_id)
        )
        original = result.scalar_one_or_none()
        if not original:
            return None

        new_post = Post(
            user_id=user_id,
            content=getattr(original, "content", ""),
            title=f"[Copy] {getattr(original, 'title', '') or ''}".strip(),
            status="draft",
            platform_account_ids=getattr(original, "platform_account_ids", []),
            hashtags=getattr(original, "hashtags", []),
            campaign_id=getattr(original, "campaign_id", None),
            content_pillar_id=getattr(original, "content_pillar_id", None),
            media_asset_ids=getattr(original, "media_asset_ids", []),
            platform_specific=getattr(original, "platform_specific", None),
            labels=getattr(original, "labels", []),
        )
        self.db.add(new_post)
        await self.db.flush()
        return new_post

    # ─── Approval Workflow ────────────────────────────────────────────────────

    async def approve_post(
        self, post_id: str, approver_id: str, comment: Optional[str] = None
    ):
        """Transition post to approved (keeps scheduled_at if set, else draft)."""
        from app.models.content import Post
        result = await self.db.execute(select(Post).where(Post.id == post_id))
        post = result.scalar_one_or_none()
        if not post:
            return None
        new_status = "scheduled" if getattr(post, "scheduled_at", None) else "approved"
        post.status = new_status
        post.approved_by = approver_id
        post.approved_at = datetime.now(timezone.utc)
        post.approval_comment = comment
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()

        # Notify via WebSocket
        try:
            from app.api.routes.websocket import push_notification
            await push_notification(
                self.redis,
                str(post.user_id),
                "approval",
                "Post Approved",
                f"Your post has been approved and is now {new_status}.",
                link=f"/content/posts/{post_id}",
            )
        except Exception as exc:
            logger.debug("WS notification failed: %s", exc)

        return post

    async def reject_post(
        self, post_id: str, rejector_id: str, reason: Optional[str] = None
    ):
        """Return a post to draft with rejection reason."""
        from app.models.content import Post
        result = await self.db.execute(select(Post).where(Post.id == post_id))
        post = result.scalar_one_or_none()
        if not post:
            return None
        post.status = "rejected"
        post.rejected_by = rejector_id
        post.rejected_reason = reason
        post.updated_at = datetime.now(timezone.utc)
        self.db.add(post)
        await self.db.flush()

        try:
            from app.api.routes.websocket import push_notification
            await push_notification(
                self.redis,
                str(post.user_id),
                "rejection",
                "Post Rejected",
                f"Your post was rejected: {reason or 'No reason given.'}",
                link=f"/content/posts/{post_id}",
            )
        except Exception as exc:
            logger.debug("WS notification failed: %s", exc)

        return post

    # ─── Calendar ─────────────────────────────────────────────────────────────

    async def get_calendar(
        self,
        user_id: str,
        year: int,
        month: int,
        platform: Optional[str] = None,
        campaign_id: Optional[str] = None,
    ) -> List[Dict[str, Any]]:
        """Build a calendar view of posts grouped by day."""
        import calendar
        from app.models.content import Post

        _, days_in_month = calendar.monthrange(year, month)
        start = datetime(year, month, 1, tzinfo=timezone.utc)
        end = datetime(year, month, days_in_month, 23, 59, 59, tzinfo=timezone.utc)

        posts, _ = await self.list_posts(
            user_id=user_id,
            from_date=start,
            to_date=end,
            platform=platform,
            campaign_id=campaign_id,
            limit=500,
        )

        # Group by day
        day_map: Dict[int, List[Any]] = {d: [] for d in range(1, days_in_month + 1)}
        for post in posts:
            dt = getattr(post, "scheduled_at", None) or getattr(post, "created_at", None)
            if dt:
                day_map[dt.day].append(post)

        return [
            {
                "day": day,
                "date": f"{year}-{month:02d}-{day:02d}",
                "post_count": len(ps),
                "posts": [
                    {
                        "id": str(p.id),
                        "title": getattr(p, "title", None),
                        "status": getattr(p, "status", "draft"),
                        "scheduled_at": getattr(p, "scheduled_at", None),
                        "platforms": getattr(p, "platform_account_ids", []),
                    }
                    for p in ps
                ],
            }
            for day, ps in sorted(day_map.items())
        ]

    async def export_calendar_ical(self, user_id: str, year: int, month: int) -> bytes:
        """Export the content calendar as an iCal (.ics) file."""
        posts, _ = await self.list_posts(user_id=user_id, limit=500)
        lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//SociAI OS//Content Calendar//EN",
            "CALSCALE:GREGORIAN",
        ]
        for post in posts:
            dt = getattr(post, "scheduled_at", None)
            if not dt:
                continue
            dtstr = dt.strftime("%Y%m%dT%H%M%SZ")
            lines += [
                "BEGIN:VEVENT",
                f"UID:{post.id}@sociai-os",
                f"DTSTAMP:{dtstr}",
                f"DTSTART:{dtstr}",
                f"SUMMARY:{getattr(post, 'title', '') or getattr(post, 'content', '')[:50]}",
                f"DESCRIPTION:Status: {getattr(post, 'status', 'draft')}",
                "END:VEVENT",
            ]
        lines.append("END:VCALENDAR")
        return "\r\n".join(lines).encode()

    # ─── A/B Testing ─────────────────────────────────────────────────────────

    async def setup_ab_test(
        self,
        user_id: str,
        post_id_a: str,
        post_id_b: str,
        test_name: str,
        metric: str,
        duration_hours: int,
        split_percentage: float,
    ) -> Dict[str, Any]:
        test_id = str(uuid4())
        test_data = {
            "id": test_id,
            "user_id": user_id,
            "test_name": test_name,
            "post_id_a": post_id_a,
            "post_id_b": post_id_b,
            "metric": metric,
            "duration_hours": duration_hours,
            "split_percentage": split_percentage,
            "status": "pending",
            "created_at": datetime.now(timezone.utc).isoformat(),
            "winner": None,
            "results": {},
        }
        await self.redis.setex(
            f"ab_test:{test_id}",
            duration_hours * 3600 + 86400,
            json.dumps(test_data),
        )
        logger.info("A/B test created: %s metric=%s", test_id, metric)
        return test_data

    # ─── Media Assets ─────────────────────────────────────────────────────────

    async def upload_media(
        self,
        user_id: str,
        filename: str,
        content: bytes,
        content_type: str,
        alt_text: Optional[str] = None,
    ):
        """Upload a media asset to S3 / compatible storage and create a DB record."""
        from app.models.content import MediaAsset

        # Generate unique S3 key
        file_ext = filename.rsplit(".", 1)[-1] if "." in filename else "bin"
        s3_key = f"media/{user_id}/{uuid4()}.{file_ext}"
        media_url = await self._upload_to_s3(s3_key, content, content_type)

        file_type = content_type.split("/")[0]  # "image", "video", "audio"
        asset = MediaAsset(
            user_id=user_id,
            filename=filename,
            url=media_url,
            file_type=file_type,
            file_size_bytes=len(content),
            content_type=content_type,
            alt_text=alt_text,
            s3_key=s3_key,
        )
        self.db.add(asset)
        await self.db.flush()
        return asset

    async def delete_media_asset(self, asset_id: str, user_id: str) -> bool:
        """Delete a media asset from S3 and DB."""
        from app.models.content import MediaAsset
        result = await self.db.execute(
            select(MediaAsset).where(
                MediaAsset.id == asset_id,
                MediaAsset.user_id == user_id,
            )
        )
        asset = result.scalar_one_or_none()
        if not asset:
            return False
        s3_key = getattr(asset, "s3_key", None)
        if s3_key:
            await self._delete_from_s3(s3_key)
        await self.db.delete(asset)
        await self.db.flush()
        return True

    async def _upload_to_s3(self, key: str, content: bytes, content_type: str) -> str:
        """Upload bytes to S3 or compatible storage. Returns public URL."""
        if not settings.AWS_ACCESS_KEY_ID:
            # Development mode: return a stub URL
            logger.debug("S3 upload skipped (no credentials): %s", key)
            return f"{settings.MEDIA_CDN_URL or 'http://localhost:8000/media'}/{key}"
        try:
            import aioboto3
            session = aioboto3.Session(
                aws_access_key_id=settings.AWS_ACCESS_KEY_ID,
                aws_secret_access_key=settings.AWS_SECRET_ACCESS_KEY,
                region_name=settings.AWS_REGION,
            )
            async with session.client(
                "s3",
                endpoint_url=settings.AWS_S3_ENDPOINT_URL,
            ) as s3:
                await s3.put_object(
                    Bucket=settings.AWS_S3_BUCKET,
                    Key=key,
                    Body=content,
                    ContentType=content_type,
                    ACL="public-read",
                )
            cdn_base = settings.MEDIA_CDN_URL or f"https://{settings.AWS_S3_BUCKET}.s3.amazonaws.com"
            return f"{cdn_base}/{key}"
        except Exception as exc:
            logger.error("S3 upload failed: %s", exc)
            raise

    async def _delete_from_s3(self, key: str) -> None:
        if not settings.AWS_ACCESS_KEY_ID:
            return
        try:
            import aioboto3
            session = aioboto3.Session(
                aws_access_key_id=settings.AWS_ACCESS_KEY_ID,
                aws_secret_access_key=settings.AWS_SECRET_ACCESS_KEY,
                region_name=settings.AWS_REGION,
            )
            async with session.client("s3", endpoint_url=settings.AWS_S3_ENDPOINT_URL) as s3:
                await s3.delete_object(Bucket=settings.AWS_S3_BUCKET, Key=key)
        except Exception as exc:
            logger.warning("S3 delete failed for key %s: %s", key, exc)

    # ─── Strategy Document Storage ────────────────────────────────────────────

    async def store_strategy_document(
        self,
        user_id: str,
        filename: str,
        content: bytes,
        content_type: str,
    ) -> str:
        """Upload strategy document to S3 and return a document ID."""
        doc_id = str(uuid4())
        s3_key = f"strategy/{user_id}/{doc_id}/{filename}"
        url = await self._upload_to_s3(s3_key, content, content_type)

        # Store metadata in Redis
        await self.redis.setex(
            f"strategy_doc:{doc_id}",
            86400 * 30,  # 30 days
            json.dumps({
                "doc_id": doc_id,
                "user_id": user_id,
                "filename": filename,
                "content_type": content_type,
                "s3_key": s3_key,
                "url": url,
                "uploaded_at": datetime.now(timezone.utc).isoformat(),
            }),
        )
        return doc_id
