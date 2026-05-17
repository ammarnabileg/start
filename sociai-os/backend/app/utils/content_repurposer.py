"""
Smart content repurposing engine – AI-powered transformation between content formats and platforms.

Converts:
  • Blog posts       → Platform-optimized social posts (all platforms)
  • Videos           → Twitter/X threads, LinkedIn articles, carousels
  • Podcasts         → Short clips, quote cards, tweetstorms
  • Newsletters      → Social teaser posts with links
  • Long-form posts  → Micro-content for TikTok/Reels/Shorts
  • Any content      → Localized versions (Arabic, English, Arabizi)
"""
from __future__ import annotations

import asyncio
import logging
import os
import re
from typing import Any, Dict, List, Optional

logger = logging.getLogger(__name__)

PLATFORM_CHAR_LIMITS: Dict[str, int] = {
    "twitter":   280,
    "threads":   500,
    "snapchat":  250,
    "instagram": 2200,
    "tiktok":    2200,
    "linkedin":  3000,
    "facebook":  63206,
    "youtube":   5000,
    "pinterest": 500,
}

PLATFORM_HASHTAG_COUNTS: Dict[str, int] = {
    "instagram": 12,
    "tiktok":    5,
    "twitter":   2,
    "linkedin":  4,
    "facebook":  3,
    "youtube":   8,
    "threads":   3,
}


class ContentRepurposer:
    """
    Repurpose content across formats using Claude AI.
    All methods are async and designed for use in FastAPI/Celery contexts.
    """

    def __init__(self):
        self._client = None

    def _get_client(self):
        if self._client is None:
            import anthropic
            self._client = anthropic.AsyncAnthropic(
                api_key=os.getenv("ANTHROPIC_API_KEY", "")
            )
        return self._client

    async def blog_to_social(
        self,
        blog_content: str,
        target_platforms: List[str],
        brand_voice: str = "professional",
        include_hashtags: bool = True,
        language: str = "english",
    ) -> Dict[str, Any]:
        """Convert a blog post into platform-optimized social media posts."""
        tasks = {
            platform: self._adapt_blog_for_platform(
                blog_content, platform, brand_voice, include_hashtags, language
            )
            for platform in target_platforms
        }
        results = await asyncio.gather(*tasks.values(), return_exceptions=True)
        return {
            platform: (result if not isinstance(result, Exception) else {"error": str(result)})
            for platform, result in zip(tasks.keys(), results)
        }

    async def _adapt_blog_for_platform(
        self,
        content: str,
        platform: str,
        voice: str,
        include_hashtags: bool,
        language: str,
    ) -> Dict[str, Any]:
        limit = PLATFORM_CHAR_LIMITS.get(platform, 2200)
        hashtag_count = PLATFORM_HASHTAG_COUNTS.get(platform, 5) if include_hashtags else 0
        lang_note = f"Write in {language}." if language != "english" else ""

        prompt = (
            f"Repurpose this blog post into a high-performing {platform} post.\n\n"
            f"Brand voice: {voice}\n"
            f"Character limit: {limit}\n"
            f"Include {hashtag_count} hashtags: {'yes' if hashtag_count > 0 else 'no'}\n"
            f"{lang_note}\n\n"
            f"Platform behavior: {self._get_platform_note(platform)}\n\n"
            f"Blog post:\n{content[:2500]}\n\n"
            "Return JSON:\n"
            '{"post": "full post text", "hook": "first line only", '
            '"hashtags": ["list"], "cta": "call to action", '
            '"char_count": 0, "key_insight": "main takeaway used"}'
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=600,
                system="You are a social media repurposing expert. Respond only with valid JSON.",
                messages=[{"role": "user", "content": prompt}],
            )
            return self._parse_json(msg.content[0].text)
        except Exception as e:
            logger.warning(f"Blog→{platform} repurposing failed: {e}")
            return {"platform": platform, "post": content[:limit], "error": str(e)}

    async def video_to_thread(
        self,
        transcript: str,
        platform: str = "twitter",
        num_tweets: int = 8,
        include_hook: bool = True,
        language: str = "english",
    ) -> List[Dict[str, str]]:
        """Convert a video transcript into a numbered Twitter/X or LinkedIn thread."""
        lang_note = f"Write in {language}." if language != "english" else ""

        prompt = (
            f"Convert this video transcript into a {num_tweets}-part {platform} thread.\n\n"
            f"{lang_note}\n"
            f"{'Start with a viral hook that forces people to read on.' if include_hook else ''}\n"
            f"Max 280 characters per tweet. End with a strong CTA.\n\n"
            f"Transcript:\n{transcript[:2500]}\n\n"
            f"Return a JSON array of {num_tweets} objects:\n"
            '[{"number": 1, "text": "...", "char_count": 0, "type": "hook|value|cta"}]'
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=1500,
                system="Social media thread writer. Return valid JSON only.",
                messages=[{"role": "user", "content": prompt}],
            )
            result = self._parse_json(msg.content[0].text)
            if isinstance(result, list):
                return result
            return [{"number": 1, "text": transcript[:280], "type": "hook"}]
        except Exception as e:
            logger.warning(f"Video→thread conversion failed: {e}")
            return [{"number": 1, "text": transcript[:280], "error": str(e)}]

    async def podcast_to_clips(
        self,
        transcript: str,
        duration_minutes: int,
        clip_count: int = 5,
        platforms: Optional[List[str]] = None,
    ) -> List[Dict[str, Any]]:
        """
        Identify the most shareable moments from a podcast transcript.
        Returns structured clip data with timestamps, quotes, and captions.
        """
        target_platforms = platforms or ["tiktok", "instagram", "youtube"]

        prompt = (
            f"From this {duration_minutes}-minute podcast transcript, identify "
            f"the {clip_count} most viral-worthy clip moments.\n\n"
            f"Target platforms: {', '.join(target_platforms)}\n\n"
            "For each clip identify:\n"
            "- The exact quote or exchange (verbatim)\n"
            "- Estimated timestamp (MM:SS)\n"
            "- Why it's shareable (insight/story/controversy/emotion)\n"
            "- Best platform for this specific clip\n"
            "- Suggested clip duration (15-60 seconds)\n"
            "- Caption for social media\n\n"
            f"Transcript:\n{transcript[:4000]}\n\n"
            f"Return JSON array of {clip_count} clips:\n"
            '[{"clip_number": 1, "timestamp_start": "MM:SS", "timestamp_end": "MM:SS", '
            '"quote": "...", "shareability_reason": "...", "emotion": "...", '
            '"best_platform": "...", "duration_seconds": 30, "caption": "...", '
            '"hook_text": "overlay text for the clip"}]'
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=2000,
                system="Podcast content strategist. Return valid JSON only.",
                messages=[{"role": "user", "content": prompt}],
            )
            result = self._parse_json(msg.content[0].text)
            return result if isinstance(result, list) else [{"raw": str(result)}]
        except Exception as e:
            logger.warning(f"Podcast→clips failed: {e}")
            return [{"error": str(e), "transcript_length": len(transcript)}]

    async def long_post_to_micro_content(
        self,
        post_content: str,
        source_platform: str,
        target_formats: Optional[List[str]] = None,
        language: str = "english",
    ) -> Dict[str, Any]:
        """
        Break a long LinkedIn/Facebook post into micro-content:
        TikTok hook, carousel slides, story quote cards, and short tweets.
        """
        formats = target_formats or ["tiktok_hook", "carousel_slides", "story_quote", "tweet"]

        prompt = (
            f"Repurpose this {source_platform} post into multiple micro-content formats.\n\n"
            f"Language: {language}\n\n"
            f"Original post:\n{post_content[:2000]}\n\n"
            "Generate these formats:\n"
            "1. tiktok_hook: 3-second opening line for a TikTok video (max 100 chars)\n"
            "2. carousel_slides: 5-7 slide titles for an Instagram carousel\n"
            "3. story_quote: Best single quote for an Instagram Story (max 200 chars)\n"
            "4. tweet: 280-char Twitter version with maximum impact\n\n"
            "Return JSON:\n"
            '{"tiktok_hook": "...", "carousel_slides": ["title1", "title2"], '
            '"story_quote": "...", "tweet": "...", "key_message": "..."}'
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=800,
                system="Content repurposing expert. Return valid JSON only.",
                messages=[{"role": "user", "content": prompt}],
            )
            return self._parse_json(msg.content[0].text)
        except Exception as e:
            logger.warning(f"Long post→micro failed: {e}")
            return {"error": str(e)}

    async def newsletter_to_social(
        self,
        newsletter_content: str,
        newsletter_title: str,
        target_platforms: Optional[List[str]] = None,
        teaser_length: str = "medium",
        language: str = "english",
    ) -> Dict[str, Any]:
        """Convert a newsletter issue into social media teasers to drive subscriptions."""
        platforms = target_platforms or ["instagram", "linkedin", "twitter"]
        teaser_chars = {"short": 150, "medium": 300, "long": 500}
        max_chars = teaser_chars.get(teaser_length, 300)

        prompt = (
            f"Convert this newsletter into social media teasers to drive sign-ups.\n\n"
            f"Newsletter title: {newsletter_title}\n"
            f"Teaser length: {teaser_length} (~{max_chars} chars)\n"
            f"Language: {language}\n"
            f"Target platforms: {', '.join(platforms)}\n\n"
            f"Newsletter content:\n{newsletter_content[:2000]}\n\n"
            "For each platform, create a teaser that:\n"
            "- Highlights the most compelling insight\n"
            "- Creates FOMO for subscribers\n"
            "- Has a clear 'subscribe' or 'read full' CTA\n\n"
            "Return JSON with a key per platform, each containing: "
            "{teaser, hook, cta, hashtags}"
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=1200,
                system="Newsletter-to-social conversion expert. Return valid JSON only.",
                messages=[{"role": "user", "content": prompt}],
            )
            return self._parse_json(msg.content[0].text)
        except Exception as e:
            logger.warning(f"Newsletter→social failed: {e}")
            return {"error": str(e)}

    async def localize_content(
        self,
        content: str,
        target_language: str,
        dialect: str = "standard",
        brand_voice: str = "professional",
        adapt_cultural_references: bool = True,
    ) -> Dict[str, Any]:
        """
        Localize and culturally adapt social media content.
        Supports Arabic dialects, Arabizi, and major world languages.
        """
        language_map = {
            "arabic_gulf": "Gulf Arabic (Emirati/Saudi dialect, عامية خليجية)",
            "arabic_levantine": "Levantine Arabic (Syrian/Lebanese/Jordanian dialect)",
            "arabic_egyptian": "Egyptian Arabic (عامية مصرية)",
            "arabic_formal": "Modern Standard Arabic (فصحى رسمية)",
            "arabic": "Modern Standard Arabic (فصحى معاصرة)",
            "mixed": "Arabizi (Latin-script Arabic-English mix)",
            "english": "English",
            "french": "French",
            "spanish": "Spanish",
        }
        lang_desc = language_map.get(target_language.lower(), target_language)
        cultural_note = (
            "Adapt idioms, references, and examples to be culturally appropriate "
            "for the target audience. Replace any culturally misaligned references."
            if adapt_cultural_references
            else "Keep cultural references as-is."
        )

        prompt = (
            f"Localize this social media content.\n\n"
            f"Target language: {lang_desc}\n"
            f"Dialect: {dialect}\n"
            f"Brand voice: {brand_voice}\n"
            f"Cultural adaptation: {cultural_note}\n\n"
            f"Original content:\n{content}\n\n"
            "Rules:\n"
            "- Maintain the same tone and brand voice\n"
            "- Keep formatting (line breaks, emojis)\n"
            "- Adapt hashtags to the target language\n"
            "- Preserve the CTA intent\n\n"
            "Return JSON: "
            '{"localized_content": "...", "adapted_hashtags": ["..."], '
            '"cultural_notes": "changes made", "back_translation": "brief English summary"}'
        )

        try:
            client = self._get_client()
            msg = await client.messages.create(
                model=os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6"),
                max_tokens=800,
                system="Expert content localizer and cultural adapter. Return valid JSON only.",
                messages=[{"role": "user", "content": prompt}],
            )
            return self._parse_json(msg.content[0].text)
        except Exception as e:
            logger.warning(f"Content localization failed: {e}")
            return {"localized_content": content, "error": str(e)}

    async def repurpose_for_all_platforms(
        self,
        source_content: str,
        source_platform: str,
        brand_voice: str = "professional",
        language: str = "english",
    ) -> Dict[str, Any]:
        """
        One-click repurpose: take content from one platform and adapt for all others.
        Returns a complete content package ready for distribution.
        """
        target_platforms = [p for p in PLATFORM_CHAR_LIMITS if p != source_platform]

        blog_results = await self.blog_to_social(
            blog_content=source_content,
            target_platforms=target_platforms,
            brand_voice=brand_voice,
            language=language,
        )

        return {
            "source_platform": source_platform,
            "source_content_length": len(source_content),
            "repurposed_for": target_platforms,
            "platform_posts": blog_results,
            "total_platforms": len(target_platforms),
        }

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _get_platform_note(platform: str) -> str:
        notes = {
            "instagram": "Visual-first. Hook in line 1. Use line breaks. Story-driven.",
            "tiktok": "Hook-first. Trend-aware. Short, punchy captions.",
            "linkedin": "Thought leadership. Personal + professional. Short paragraphs.",
            "twitter": "Concise. One big idea. Controversy or insight drives RTs.",
            "facebook": "Community-focused. Questions drive engagement.",
            "threads": "Raw, conversational, honest. Text-first.",
            "youtube": "SEO-driven. Detailed description. Keyword-rich.",
        }
        return notes.get(platform.lower(), "Optimize for engagement on this platform.")

    @staticmethod
    def _parse_json(text: str) -> Any:
        import json
        text = text.strip()
        text = re.sub(r"^```[a-z]*\n?", "", text)
        text = re.sub(r"\n?```$", "", text)
        try:
            return json.loads(text)
        except Exception:
            match = re.search(r"(\{[\s\S]*\}|\[[\s\S]*\])", text)
            if match:
                try:
                    return json.loads(match.group(1))
                except Exception:
                    pass
        return {"raw_response": text}


# Module-level singleton
content_repurposer = ContentRepurposer()
