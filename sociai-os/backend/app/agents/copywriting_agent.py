"""
CopywritingAgent - AI-powered copywriter for all social media formats and platforms.

Supports multi-language output (Arabic, English, Arabizi), multiple brand styles,
and all major content formats: captions, threads, scripts, carousels, ads, DMs, and more.
"""

import json
import os
import re
from typing import Any, Dict, List, Optional

import anthropic

from .base_agent import AgentResult, BaseAgent

_ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6")

SYSTEM_PROMPT = """You are an elite social media copywriter with 15+ years of experience across
Fortune 500 brands and viral startups. You master every style—from luxury whisper to Gen-Z chaos.
You write copy that stops the scroll, triggers emotion, and drives action. Every word is intentional.
You always respond with valid JSON unless instructed otherwise."""

PLATFORM_CONSTRAINTS = {
    "instagram": {"caption_limit": 2200, "hashtag_optimal": 15, "line_breaks": True},
    "tiktok": {"caption_limit": 2200, "hashtag_optimal": 5, "line_breaks": False},
    "linkedin": {"caption_limit": 3000, "hashtag_optimal": 5, "line_breaks": True},
    "twitter": {"caption_limit": 280, "hashtag_optimal": 2, "line_breaks": False},
    "facebook": {"caption_limit": 63206, "hashtag_optimal": 3, "line_breaks": True},
    "youtube": {"caption_limit": 5000, "hashtag_optimal": 10, "line_breaks": True},
    "snapchat": {"caption_limit": 250, "hashtag_optimal": 0, "line_breaks": False},
    "threads": {"caption_limit": 500, "hashtag_optimal": 3, "line_breaks": True},
}

STYLE_INSTRUCTIONS = {
    "corporate": "Professional, authoritative, data-driven. Clear structure. No slang.",
    "luxury": "Exclusive, aspirational, understated elegance. Short sentences. Evocative language.",
    "viral": "Bold, provocative, shareable. Pattern interrupts. Strong hooks. Controversy-adjacent.",
    "aggressive": "Direct, no-fluff, results-focused. Power words. Urgency. No apologies.",
    "educational": "Teach, inform, enlighten. Step-by-step logic. Analogies. Value-first.",
    "emotional": "Story-driven, empathetic, vulnerable. Touch the heart before the mind.",
    "minimal": "Less is more. White space. One idea per line. Zen.",
    "gen-z": "Raw, chaotic, self-aware, ironic. Internet-native. Lowercase often. Abbreviations.",
    "b2b": "ROI-focused, solution-oriented, professional. Speak to the decision-maker.",
    "b2c": "Relatable, benefit-driven, fun. Speak to the human, not the buyer.",
}

LANGUAGE_INSTRUCTIONS = {
    "english": "Write in fluent, natural English.",
    "arabic": "اكتب باللغة العربية الفصحى المعاصرة (MSA) أو العامية المناسبة للسياق.",
    "mixed": "Write in Arabizi (Arabic-English mix, Latin script for Arabic sounds). Example: 'ana 3arif eno...'",
    "arabic_formal": "اكتب بالعربية الفصحى الرسمية فقط.",
    "arabic_gulf": "اكتب بالعامية الخليجية (إماراتي/سعودي).",
    "arabic_levantine": "اكتب بالعامية الشامية.",
}


class CopywritingAgent(BaseAgent):
    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        super().__init__("copywriting_agent", brand_id, redis_url)
        self._client = anthropic.AsyncAnthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))

    # ------------------------------------------------------------------
    # Primary dispatcher
    # ------------------------------------------------------------------

    async def execute(self, task: str, **kwargs) -> AgentResult:
        cached = await self.get_cached(task, **kwargs)
        if cached:
            return cached

        start = self._start_timer()
        dispatch = {
            "generate_caption": self._generate_caption,
            "generate_linkedin_post": self._generate_linkedin_post,
            "generate_thread": self._generate_thread,
            "generate_script": self._generate_script,
            "generate_hooks": self._generate_hooks,
            "generate_cta": self._generate_cta,
            "generate_ad_copy": self._generate_ad_copy,
            "generate_carousel_text": self._generate_carousel_text,
            "generate_comment_reply": self._generate_comment_reply,
            "generate_dm_reply": self._generate_dm_reply,
        }
        try:
            if task not in dispatch:
                raise ValueError(f"Unknown task: {task}")
            data = await dispatch[task](**kwargs)
            result = self._make_result(task, data, start)
            await self.cache_result(task, result, **kwargs)
            self._log_execution(result)
            return result
        except Exception as exc:
            self._log.exception("copywriting_error", extra={"task": task})
            return self._make_result(task, None, start, error=str(exc))

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    async def generate_caption(
        self,
        platform: str,
        topic: str,
        style: str = "viral",
        language: str = "english",
        brand_voice: Optional[Dict] = None,
        include_hashtags: bool = True,
        include_cta: bool = True,
        count: int = 3,
    ) -> List[Dict[str, str]]:
        return await self._generate_caption(
            platform=platform, topic=topic, style=style, language=language,
            brand_voice=brand_voice, include_hashtags=include_hashtags,
            include_cta=include_cta, count=count,
        )

    async def generate_linkedin_post(
        self,
        topic: str,
        tone: str = "thought_leadership",
        word_count: int = 250,
        include_hook: bool = True,
    ) -> Dict[str, Any]:
        return await self._generate_linkedin_post(
            topic=topic, tone=tone, word_count=word_count, include_hook=include_hook,
        )

    async def generate_thread(
        self,
        topic: str,
        num_tweets: int = 8,
        style: str = "educational",
        language: str = "english",
    ) -> List[Dict[str, str]]:
        return await self._generate_thread(
            topic=topic, num_tweets=num_tweets, style=style, language=language,
        )

    async def generate_script(
        self,
        video_type: str,
        duration: int,
        hooks: Optional[List[str]] = None,
        topic: str = "",
        language: str = "english",
    ) -> Dict[str, Any]:
        return await self._generate_script(
            video_type=video_type, duration=duration, hooks=hooks,
            topic=topic, language=language,
        )

    async def generate_hooks(
        self,
        topic: str,
        count: int = 10,
        style: str = "viral",
        platform: str = "instagram",
        language: str = "english",
    ) -> List[Dict[str, str]]:
        return await self._generate_hooks(
            topic=topic, count=count, style=style, platform=platform, language=language,
        )

    async def generate_cta(
        self,
        goal: str,
        platform: str,
        style: str = "direct",
        language: str = "english",
        count: int = 5,
    ) -> List[str]:
        return await self._generate_cta(
            goal=goal, platform=platform, style=style, language=language, count=count,
        )

    async def generate_ad_copy(
        self,
        product: str,
        audience: str,
        platform: str,
        objective: str = "conversion",
        style: str = "aggressive",
        language: str = "english",
    ) -> Dict[str, Any]:
        return await self._generate_ad_copy(
            product=product, audience=audience, platform=platform,
            objective=objective, style=style, language=language,
        )

    async def generate_carousel_text(
        self,
        topic: str,
        slides: int = 7,
        style: str = "educational",
        language: str = "english",
    ) -> Dict[str, Any]:
        return await self._generate_carousel_text(
            topic=topic, slides=slides, style=style, language=language,
        )

    async def generate_comment_reply(
        self,
        comment: str,
        brand_voice: Optional[Dict] = None,
        platform: str = "instagram",
        sentiment: str = "auto",
    ) -> str:
        return await self._generate_comment_reply(
            comment=comment, brand_voice=brand_voice,
            platform=platform, sentiment=sentiment,
        )

    async def generate_dm_reply(
        self,
        message: str,
        intent: str = "auto",
        brand_context: Optional[Dict] = None,
        platform: str = "instagram",
    ) -> Dict[str, str]:
        return await self._generate_dm_reply(
            message=message, intent=intent,
            brand_context=brand_context, platform=platform,
        )

    # ------------------------------------------------------------------
    # Internal implementations
    # ------------------------------------------------------------------

    async def _generate_caption(
        self,
        platform: str,
        topic: str,
        style: str = "viral",
        language: str = "english",
        brand_voice: Optional[Dict] = None,
        include_hashtags: bool = True,
        include_cta: bool = True,
        count: int = 3,
        **_,
    ) -> List[Dict[str, str]]:
        constraints = PLATFORM_CONSTRAINTS.get(platform.lower(), PLATFORM_CONSTRAINTS["instagram"])
        style_guide = STYLE_INSTRUCTIONS.get(style.lower(), STYLE_INSTRUCTIONS["viral"])
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])
        brand_note = json.dumps(brand_voice) if brand_voice else "No specific brand voice provided."

        prompt = f"""Create {count} high-performing {platform} captions for this topic: "{topic}"

Style: {style} - {style_guide}
Language: {lang_guide}
Character limit: {constraints['caption_limit']}
Brand voice: {brand_note}
Include hashtags: {include_hashtags} (optimal count: {constraints['hashtag_optimal']})
Include CTA: {include_cta}

Return a JSON array of {count} caption objects:
[
  {{
    "caption": "full caption text",
    "hook": "first line only",
    "hashtags": ["list", "of", "hashtags"],
    "cta": "call to action",
    "estimated_engagement": "low|medium|high|viral",
    "why_it_works": "brief explanation"
  }}
]

Make each version distinctly different. Vary the hook style, angle, and emotional trigger."""

        response = await self._call_claude(prompt, task="generate_caption", max_tokens=2048)
        return self._parse_json(response["text"])

    async def _generate_linkedin_post(
        self,
        topic: str,
        tone: str = "thought_leadership",
        word_count: int = 250,
        include_hook: bool = True,
        **_,
    ) -> Dict[str, Any]:
        prompt = f"""Write a high-performing LinkedIn post about: "{topic}"

Tone: {tone}
Target word count: {word_count}
Include attention hook: {include_hook}

LinkedIn best practices to follow:
- Hook in first 2 lines (before "see more" cutoff)
- Short paragraphs (1-3 lines max)
- Personal story or insight
- Data point or bold claim
- Genuine expertise signal
- Conversation-starter ending

Return a JSON object:
{{
  "post": "full post text with line breaks",
  "hook": "first two lines",
  "structure": ["intro", "body point 1", "body point 2", "story/example", "insight", "cta"],
  "hashtags": ["list of 3-5 relevant hashtags"],
  "estimated_impressions": "string",
  "best_posting_time": "string",
  "engagement_prediction": {{
    "likes": "range",
    "comments": "range",
    "shares": "range"
  }}
}}"""

        response = await self._call_claude(prompt, task="generate_linkedin_post", max_tokens=1500)
        return self._parse_json(response["text"])

    async def _generate_thread(
        self,
        topic: str,
        num_tweets: int = 8,
        style: str = "educational",
        language: str = "english",
        **_,
    ) -> List[Dict[str, str]]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])
        style_guide = STYLE_INSTRUCTIONS.get(style.lower(), STYLE_INSTRUCTIONS["educational"])

        prompt = f"""Write a viral Twitter/X thread of {num_tweets} tweets about: "{topic}"

Style: {style} - {style_guide}
Language: {lang_guide}

Thread rules:
- Tweet 1: Provocative hook that FORCES a click "Show more" or read-on
- Tweets 2-{num_tweets - 1}: Value-packed, each building on the last
- Tweet {num_tweets}: Strong closer with CTA or reflection
- Each tweet max 280 characters
- Number each tweet (1/{num_tweets}, 2/{num_tweets}, etc.)
- Use line breaks strategically

Return a JSON array:
[
  {{
    "number": 1,
    "text": "tweet text",
    "character_count": 0,
    "type": "hook|value|story|insight|cta",
    "engagement_tip": "string"
  }}
]"""

        response = await self._call_claude(prompt, task="generate_thread", max_tokens=2500)
        return self._parse_json(response["text"])

    async def _generate_script(
        self,
        video_type: str,
        duration: int,
        hooks: Optional[List[str]] = None,
        topic: str = "",
        language: str = "english",
        **_,
    ) -> Dict[str, Any]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])
        hook_context = f"Use one of these hooks: {hooks}" if hooks else "Create a powerful original hook."
        words_per_minute = 130  # natural speaking pace
        target_words = int(duration * words_per_minute / 60)

        prompt = f"""Write a complete {video_type} video script.

Topic: {topic}
Duration: {duration} seconds ({target_words} words approximately)
Language: {lang_guide}
Hook guidance: {hook_context}

Video types reference:
- reel/tiktok: Fast-paced, hook in 1-3 seconds, visual cues
- talking_head: Direct-to-camera, conversational, authentic
- tutorial: Step-by-step, clear narration, screen-aware
- testimonial: Story arc, before/after, emotional
- ad: Problem → Solution → CTA, punchy

Return a JSON object:
{{
  "title": "video title",
  "hook": "opening line (first 3 seconds)",
  "script": [
    {{
      "timestamp": "0:00-0:05",
      "spoken": "exact words to say",
      "visual": "what should appear on screen",
      "tone": "energetic|calm|serious|funny",
      "b_roll": "suggested b-roll or action"
    }}
  ],
  "cta": "closing call to action",
  "total_words": 0,
  "estimated_duration": 0,
  "production_notes": "string",
  "caption_text": "short caption for the post"
}}"""

        response = await self._call_claude(prompt, task="generate_script", max_tokens=3000)
        return self._parse_json(response["text"])

    async def _generate_hooks(
        self,
        topic: str,
        count: int = 10,
        style: str = "viral",
        platform: str = "instagram",
        language: str = "english",
        **_,
    ) -> List[Dict[str, str]]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])

        prompt = f"""Generate {count} scroll-stopping hooks for content about: "{topic}"

Platform: {platform}
Style: {style}
Language: {lang_guide}

Hook formula types to cover:
1. Bold claim ("99% of people don't know this...")
2. Curiosity gap ("What happened next shocked everyone...")
3. Controversy ("Unpopular opinion: ...")
4. Personal story ("I lost everything until I discovered...")
5. Number/list ("7 things that will...")
6. Question ("Why does everyone ignore...")
7. Warning ("Stop doing this if you want...")
8. Promise ("After this, you'll never...")
9. Relatable pain ("If you've ever felt like...")
10. Transformation ("From [bad] to [good] in [time]...")

Return a JSON array:
[
  {{
    "hook": "the hook text",
    "type": "hook formula name",
    "emotional_trigger": "curiosity|fear|desire|social_proof|urgency",
    "strength_score": 0-10,
    "platform_fit": "excellent|good|okay"
  }}
]"""

        response = await self._call_claude(prompt, task="generate_hooks", max_tokens=2000)
        return self._parse_json(response["text"])

    async def _generate_cta(
        self,
        goal: str,
        platform: str,
        style: str = "direct",
        language: str = "english",
        count: int = 5,
        **_,
    ) -> List[str]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])

        prompt = f"""Generate {count} powerful calls-to-action for this goal: "{goal}"

Platform: {platform}
Style: {style}
Language: {lang_guide}

CTA principles:
- Action verb first
- Specific benefit
- Low friction language
- Platform-appropriate

Return a JSON array of {count} CTA strings:
["CTA 1", "CTA 2", ...]"""

        response = await self._call_claude(prompt, task="generate_cta", max_tokens=500)
        return self._parse_json(response["text"])

    async def _generate_ad_copy(
        self,
        product: str,
        audience: str,
        platform: str,
        objective: str = "conversion",
        style: str = "aggressive",
        language: str = "english",
        **_,
    ) -> Dict[str, Any]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])
        style_guide = STYLE_INSTRUCTIONS.get(style.lower(), STYLE_INSTRUCTIONS["aggressive"])

        prompt = f"""Write high-converting ad copy for:

Product/Service: {product}
Target Audience: {audience}
Platform: {platform}
Objective: {objective}
Style: {style} - {style_guide}
Language: {lang_guide}

Return a JSON object:
{{
  "headline": "primary headline (max 40 chars for most platforms)",
  "subheadline": "supporting headline",
  "primary_text": "main ad body copy",
  "description": "short description (feed ads)",
  "cta_button": "Shop Now|Learn More|Sign Up|Get Offer",
  "pain_point_addressed": "string",
  "unique_value_proposition": "string",
  "social_proof_element": "string",
  "urgency_element": "string",
  "variants": [
    {{
      "focus": "benefit|pain|social_proof|offer",
      "headline": "string",
      "body": "string"
    }}
  ],
  "a_b_test_suggestion": "string"
}}"""

        response = await self._call_claude(prompt, task="generate_ad_copy", max_tokens=2000)
        return self._parse_json(response["text"])

    async def _generate_carousel_text(
        self,
        topic: str,
        slides: int = 7,
        style: str = "educational",
        language: str = "english",
        **_,
    ) -> Dict[str, Any]:
        lang_guide = LANGUAGE_INSTRUCTIONS.get(language.lower(), LANGUAGE_INSTRUCTIONS["english"])
        style_guide = STYLE_INSTRUCTIONS.get(style.lower(), STYLE_INSTRUCTIONS["educational"])

        prompt = f"""Create complete carousel content for: "{topic}"

Number of slides: {slides}
Style: {style} - {style_guide}
Language: {lang_guide}

Carousel structure:
- Slide 1: Hook slide (make them swipe)
- Slides 2 to {slides - 1}: Content/value slides
- Slide {slides}: CTA slide

Return a JSON object:
{{
  "caption": "Instagram caption for the carousel post",
  "hashtags": ["array of hashtags"],
  "slides": [
    {{
      "slide_number": 1,
      "headline": "bold headline text (short, punchy)",
      "body": "supporting text (2-4 lines max)",
      "visual_direction": "describe the visual design",
      "swipe_prompt": "text to encourage swiping (if applicable)"
    }}
  ],
  "cover_slide_options": ["3 different headline options for the cover"],
  "overall_narrative": "string"
}}"""

        response = await self._call_claude(prompt, task="generate_carousel_text", max_tokens=3000)
        return self._parse_json(response["text"])

    async def _generate_comment_reply(
        self,
        comment: str,
        brand_voice: Optional[Dict] = None,
        platform: str = "instagram",
        sentiment: str = "auto",
        **_,
    ) -> str:
        voice_note = json.dumps(brand_voice) if brand_voice else "Friendly, helpful, on-brand"

        prompt = f"""Write the perfect brand reply to this {platform} comment.

Comment: "{comment}"
Detected sentiment: {sentiment}
Brand voice: {voice_note}

Reply guidelines:
- Acknowledge the commenter personally
- Match the emotional energy appropriately
- Stay in brand voice
- Keep it brief (1-3 sentences max)
- Encourage further engagement
- Never be defensive or robotic
- If negative: empathize, offer solution, take offline if needed

Return a JSON object:
{{
  "reply": "the reply text",
  "detected_sentiment": "positive|negative|neutral|question|complaint|compliment",
  "tone_used": "string",
  "escalation_needed": false,
  "escalation_reason": null
}}"""

        response = await self._call_claude(prompt, task="generate_comment_reply", max_tokens=400)
        result = self._parse_json(response["text"])
        return result.get("reply", result) if isinstance(result, dict) else result

    async def _generate_dm_reply(
        self,
        message: str,
        intent: str = "auto",
        brand_context: Optional[Dict] = None,
        platform: str = "instagram",
        **_,
    ) -> Dict[str, str]:
        context_note = json.dumps(brand_context) if brand_context else "General brand DM"

        prompt = f"""Craft a perfect DM reply for this {platform} message.

Incoming message: "{message}"
Detected intent: {intent}
Brand context: {context_note}

Intent categories: inquiry|complaint|collaboration|purchase_intent|spam|support|general

Return a JSON object:
{{
  "reply": "the DM response",
  "detected_intent": "string",
  "lead_score": "hot|warm|cold|not_a_lead",
  "next_action": "string (what should happen next)",
  "auto_sendable": true,
  "requires_human": false,
  "reason_for_human": null,
  "suggested_follow_up": "string (follow-up message in 24h if no response)"
}}"""

        response = await self._call_claude(prompt, task="generate_dm_reply", max_tokens=600)
        return self._parse_json(response["text"])

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    async def _call_claude(
        self,
        prompt: str,
        task: str = "unknown",
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> Dict[str, Any]:
        message = await self._client.messages.create(
            model=_ANTHROPIC_MODEL,
            max_tokens=max_tokens,
            system=SYSTEM_PROMPT,
            messages=[{"role": "user", "content": prompt}],
        )
        cost = await self.track_cost(
            _ANTHROPIC_MODEL,
            message.usage.input_tokens,
            message.usage.output_tokens,
            task,
        )
        return {
            "text": message.content[0].text,
            "cost": cost,
            "input_tokens": message.usage.input_tokens,
            "output_tokens": message.usage.output_tokens,
        }

    @staticmethod
    def _parse_json(text: str) -> Any:
        text = text.strip()
        if text.startswith("```"):
            text = re.sub(r"^```[a-z]*\n?", "", text)
            text = re.sub(r"\n?```$", "", text)
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            match = re.search(r"(\{[\s\S]*\}|\[[\s\S]*\])", text)
            if match:
                try:
                    return json.loads(match.group(1))
                except json.JSONDecodeError:
                    pass
            return {"raw_response": text}
