"""
StrategyAgent - acts as the brand's Chief Strategy Officer.

Responsibilities:
- Parse uploaded strategy documents (PDF / DOCX)
- Extract brand tone, content pillars, target audience segments
- Generate monthly content calendars with full post briefs
"""

import io
import json
import os
import re
from datetime import date, timedelta
from pathlib import Path
from typing import Any, Dict, List, Optional

import anthropic

from .base_agent import AgentResult, BaseAgent

_ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6")

SYSTEM_PROMPT = """You are the Chief Strategy Officer of a world-class social media agency.
You have deep expertise in brand positioning, audience psychology, content strategy,
and platform algorithms. Your output is always structured, data-driven, and immediately
actionable. Respond ONLY in valid JSON unless explicitly told otherwise."""


class StrategyAgent(BaseAgent):
    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        super().__init__("strategy_agent", brand_id, redis_url)
        self._client = anthropic.AsyncAnthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))

    # ------------------------------------------------------------------
    # Primary execute dispatcher
    # ------------------------------------------------------------------

    async def execute(self, task: str, **kwargs) -> AgentResult:
        cached = await self.get_cached(task, **kwargs)
        if cached:
            return cached

        start = self._start_timer()
        try:
            dispatch = {
                "analyze_strategy_document": self._analyze_strategy_document,
                "extract_brand_tone": self._extract_brand_tone,
                "extract_content_pillars": self._extract_content_pillars,
                "analyze_target_audience": self._analyze_target_audience,
                "generate_monthly_plan": self._generate_monthly_plan,
            }
            if task not in dispatch:
                raise ValueError(f"Unknown task: {task}")

            data = await dispatch[task](**kwargs)
            result = self._make_result(task, data, start)
            await self.cache_result(task, result, **kwargs)
            self._log_execution(result)
            return result

        except Exception as exc:
            self._log.exception("strategy_agent_error", extra={"task": task})
            return self._make_result(task, None, start, error=str(exc))

    # ------------------------------------------------------------------
    # Public API (called directly or via execute)
    # ------------------------------------------------------------------

    async def analyze_strategy_document(self, file_path: str) -> Dict[str, Any]:
        return await self._analyze_strategy_document(file_path=file_path)

    async def extract_brand_tone(self, brand_context: Optional[str] = None) -> Dict[str, Any]:
        return await self._extract_brand_tone(brand_context=brand_context)

    async def extract_content_pillars(self, brand_context: Optional[str] = None) -> List[Dict]:
        return await self._extract_content_pillars(brand_context=brand_context)

    async def analyze_target_audience(self, brand_context: Optional[str] = None) -> Dict[str, Any]:
        return await self._analyze_target_audience(brand_context=brand_context)

    async def generate_monthly_plan(
        self,
        month: Optional[str] = None,
        platforms: Optional[List[str]] = None,
    ) -> Dict[str, Any]:
        return await self._generate_monthly_plan(month=month, platforms=platforms)

    # ------------------------------------------------------------------
    # Internal implementations
    # ------------------------------------------------------------------

    async def _analyze_strategy_document(self, file_path: str, **_) -> Dict[str, Any]:
        text = await self._extract_text(file_path)
        # Store raw text in memory so other methods can reference it
        await self.remember("strategy_doc_text", text[:20000])

        prompt = f"""Analyze this brand strategy document and extract ALL strategic information.
Return a JSON object with these exact keys:
{{
  "brand_name": "string",
  "industry": "string",
  "mission": "string",
  "vision": "string",
  "usp": "string",
  "brand_tone": {{"primary": "string", "secondary": ["string"], "avoid": ["string"]}},
  "content_pillars": [{{"name": "string", "description": "string", "percentage": 0}}],
  "target_audiences": [{{"segment": "string", "age_range": "string", "interests": ["string"], "pain_points": ["string"], "platforms": ["string"]}}],
  "competitors": [{{"name": "string", "strength": "string", "weakness": "string"}}],
  "goals": [{{"goal": "string", "metric": "string", "target": "string", "timeline": "string"}}],
  "platforms": ["string"],
  "posting_frequency": {{"platform": "string", "per_week": 0}},
  "budget_tier": "string",
  "language": "string"
}}

Strategy Document:
{text[:15000]}"""

        response = await self._call_claude(prompt, task="analyze_strategy_document")
        result = self._parse_json(response["text"])
        await self.remember("brand_strategy", result)
        return result

    async def _extract_brand_tone(self, brand_context: Optional[str] = None, **_) -> Dict[str, Any]:
        context = brand_context or await self._get_stored_context()

        prompt = f"""Analyze this brand and identify its communication style and tone.
Return a JSON object:
{{
  "primary_tone": "string (e.g. Professional, Friendly, Luxury, Bold)",
  "personality_traits": ["string"],
  "voice_descriptors": ["string"],
  "writing_style": {{
    "sentence_length": "short|medium|long",
    "vocabulary": "simple|professional|technical|luxurious",
    "use_emoji": true|false,
    "use_hashtags": true|false,
    "formality": "casual|semi-formal|formal"
  }},
  "tone_by_platform": {{
    "instagram": "string",
    "linkedin": "string",
    "tiktok": "string",
    "twitter": "string"
  }},
  "avoid": ["string"],
  "sample_phrases": ["string"],
  "banned_words": ["string"]
}}

Brand context:
{context}"""

        response = await self._call_claude(prompt, task="extract_brand_tone")
        result = self._parse_json(response["text"])
        await self.remember("brand_tone", result)
        return result

    async def _extract_content_pillars(self, brand_context: Optional[str] = None, **_) -> List[Dict]:
        context = brand_context or await self._get_stored_context()

        prompt = f"""Identify the core content pillars for this brand's social media strategy.
Return a JSON array of 4-6 pillars:
[
  {{
    "name": "string",
    "description": "string",
    "percentage_of_content": 0,
    "content_types": ["string"],
    "example_topics": ["string"],
    "platforms": ["string"],
    "goal": "string (awareness|engagement|conversion|retention)"
  }}
]

Brand context:
{context}"""

        response = await self._call_claude(prompt, task="extract_content_pillars")
        result = self._parse_json(response["text"])
        await self.remember("content_pillars", result)
        return result

    async def _analyze_target_audience(self, brand_context: Optional[str] = None, **_) -> Dict[str, Any]:
        context = brand_context or await self._get_stored_context()

        prompt = f"""Perform deep audience segmentation for this brand.
Return a JSON object:
{{
  "primary_audience": {{
    "segment_name": "string",
    "age_range": "string",
    "gender_split": "string",
    "location": ["string"],
    "income_level": "string",
    "education": "string",
    "occupation": ["string"],
    "interests": ["string"],
    "values": ["string"],
    "pain_points": ["string"],
    "goals": ["string"],
    "preferred_content": ["string"],
    "active_platforms": ["string"],
    "active_hours": "string",
    "purchase_behavior": "string",
    "influencer_type": "string"
  }},
  "secondary_audiences": [
    {{
      "segment_name": "string",
      "description": "string",
      "size": "string",
      "value": "string"
    }}
  ],
  "audience_insights": {{
    "biggest_challenge": "string",
    "content_format_preference": "string",
    "engagement_triggers": ["string"],
    "purchase_motivators": ["string"]
  }}
}}

Brand context:
{context}"""

        response = await self._call_claude(prompt, task="analyze_target_audience")
        result = self._parse_json(response["text"])
        await self.remember("target_audience", result)
        return result

    async def _generate_monthly_plan(
        self,
        month: Optional[str] = None,
        platforms: Optional[List[str]] = None,
        **_,
    ) -> Dict[str, Any]:
        strategy = await self.recall("brand_strategy") or {}
        tone = await self.recall("brand_tone") or {}
        pillars = await self.recall("content_pillars") or []
        audience = await self.recall("target_audience") or {}

        if not month:
            today = date.today()
            first_next = (today.replace(day=1) + timedelta(days=32)).replace(day=1)
            month = first_next.strftime("%Y-%m")

        target_platforms = platforms or strategy.get("platforms", ["instagram", "linkedin", "tiktok"])

        context_summary = json.dumps(
            {"strategy": strategy, "tone": tone, "pillars": pillars[:3], "audience": audience},
            ensure_ascii=False,
        )[:6000]

        prompt = f"""Create a complete, detailed social media content calendar for {month}.

Brand Context (summary):
{context_summary}

Platforms: {", ".join(target_platforms)}

Generate a full month calendar. Return a JSON object:
{{
  "month": "{month}",
  "theme": "string (overarching monthly theme)",
  "kpis": [{{"metric": "string", "target": "string"}}],
  "weekly_themes": [
    {{
      "week": 1,
      "theme": "string",
      "focus_pillar": "string"
    }}
  ],
  "posts": [
    {{
      "date": "YYYY-MM-DD",
      "platform": "string",
      "content_type": "reel|carousel|static|story|thread|article",
      "pillar": "string",
      "topic": "string",
      "headline": "string",
      "caption_brief": "string",
      "hook": "string",
      "visual_direction": "string",
      "hashtag_strategy": "string",
      "cta": "string",
      "estimated_reach": "string",
      "priority": "high|medium|low"
    }}
  ],
  "campaign_moments": [
    {{
      "date": "YYYY-MM-DD",
      "event": "string",
      "content_opportunity": "string"
    }}
  ],
  "ab_tests": [
    {{
      "week": 1,
      "element": "string",
      "variant_a": "string",
      "variant_b": "string"
    }}
  ]
}}

Generate at least 20-30 posts spread across the month and platforms."""

        response = await self._call_claude(prompt, task="generate_monthly_plan", max_tokens=4096)
        result = self._parse_json(response["text"])
        result["generated_at"] = date.today().isoformat()
        result["cost_usd"] = response.get("cost", 0)
        await self.remember(f"monthly_plan_{month}", result, ttl=86400 * 40)
        return result

    # ------------------------------------------------------------------
    # Internals
    # ------------------------------------------------------------------

    async def _call_claude(
        self,
        prompt: str,
        task: str = "unknown",
        max_tokens: int = 2048,
        temperature: float = 0.3,
    ) -> Dict[str, Any]:
        message = await self._client.messages.create(
            model=_ANTHROPIC_MODEL,
            max_tokens=max_tokens,
            system=SYSTEM_PROMPT,
            messages=[{"role": "user", "content": prompt}],
        )
        input_tokens = message.usage.input_tokens
        output_tokens = message.usage.output_tokens
        cost = await self.track_cost(_ANTHROPIC_MODEL, input_tokens, output_tokens, task)
        text = message.content[0].text
        return {"text": text, "cost": cost, "input_tokens": input_tokens, "output_tokens": output_tokens}

    async def _extract_text(self, file_path: str) -> str:
        path = Path(file_path)
        if not path.exists():
            raise FileNotFoundError(f"Strategy document not found: {file_path}")

        suffix = path.suffix.lower()

        if suffix == ".pdf":
            try:
                import pypdf
                reader = pypdf.PdfReader(str(path))
                return "\n".join(page.extract_text() or "" for page in reader.pages)
            except ImportError:
                raise ImportError("Install pypdf: pip install pypdf")

        if suffix in (".docx", ".doc"):
            try:
                import docx
                doc = docx.Document(str(path))
                return "\n".join(p.text for p in doc.paragraphs)
            except ImportError:
                raise ImportError("Install python-docx: pip install python-docx")

        if suffix in (".txt", ".md"):
            return path.read_text(encoding="utf-8")

        raise ValueError(f"Unsupported file type: {suffix}. Supported: PDF, DOCX, TXT, MD")

    async def _get_stored_context(self) -> str:
        strategy = await self.recall("brand_strategy")
        if strategy:
            return json.dumps(strategy, ensure_ascii=False)[:8000]
        doc_text = await self.recall("strategy_doc_text")
        if doc_text:
            return doc_text[:8000]
        return "No brand context available. Please analyze a strategy document first."

    @staticmethod
    def _parse_json(text: str) -> Any:
        # Strip markdown fences if present
        text = text.strip()
        if text.startswith("```"):
            text = re.sub(r"^```[a-z]*\n?", "", text)
            text = re.sub(r"\n?```$", "", text)
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            # Attempt to extract the first JSON object/array
            match = re.search(r"(\{[\s\S]*\}|\[[\s\S]*\])", text)
            if match:
                return json.loads(match.group(1))
            return {"raw_response": text}
