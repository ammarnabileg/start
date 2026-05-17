"""ResearchAgent – trend scanning, competitor analysis, hashtag research, viral content discovery."""
from __future__ import annotations
import asyncio
import logging
from datetime import datetime
from typing import Any, Optional
import httpx
from app.agents.base_agent import BaseAgent, AgentResult
logger = logging.getLogger(__name__)


class ResearchAgent(BaseAgent):
    agent_type = "research"

    async def execute(self, task: str, **kwargs) -> AgentResult:
        start = self._start_timer()
        try:
            result = await getattr(self, task)(**kwargs)
            return self._make_result(True, result, task, self._elapsed_ms(start))
        except Exception as e:
            logger.exception(f"ResearchAgent.{task} failed")
            return self._make_result(False, None, task, self._elapsed_ms(start), error=str(e))

    async def scan_platform_trends(self, platform: str, niche: str = "general") -> list[dict[str, Any]]:
        """Use AI to simulate trend scanning for a given platform and niche."""
        from app.core.config import settings
        prompt = (
            f"Generate a list of 8 current trending topics/formats/sounds on {platform} "
            f"in the {niche} niche as of today ({datetime.utcnow().strftime('%B %Y')}). "
            "For each trend provide: name, format (video/image/text), estimated daily volume, "
            "virality_score (1-10), content angle, relevant hashtags (3), decay speed (fast/medium/slow). "
            "Return as JSON array."
        )
        result = await self._call_claude_json(prompt, max_tokens=1000)
        return result if isinstance(result, list) else [{"trend": "AI trend data", "platform": platform}]

    async def scan_all_trends(self, niche: str, platforms: Optional[list[str]] = None) -> dict[str, Any]:
        platforms = platforms or ["tiktok", "instagram", "linkedin", "twitter", "youtube"]
        tasks = [self.scan_platform_trends(p, niche) for p in platforms]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        trend_map = {}
        for platform, result in zip(platforms, results):
            trend_map[platform] = result if not isinstance(result, Exception) else []
        all_trends = [t for trends in trend_map.values() for t in trends]
        top_cross_platform = sorted(all_trends, key=lambda t: t.get("virality_score", 0), reverse=True)[:5]
        return {"by_platform": trend_map, "top_cross_platform": top_cross_platform, "niche": niche, "scanned_at": datetime.utcnow().isoformat()}

    async def analyze_hashtags(self, niche: str, platform: str, count: int = 20) -> dict[str, Any]:
        from app.core.config import settings
        prompt = (
            f"Provide {count} optimal hashtags for {niche} content on {platform}. "
            "Group them as: mega (>10M posts), large (1M-10M), medium (100K-1M), niche (<100K). "
            "For each: hashtag name, estimated daily posts, engagement quality (low/medium/high), relevance (1-10). "
            "Return JSON with groups."
        )
        result = await self._call_claude_json(prompt, max_tokens=800)
        return {"platform": platform, "niche": niche, "hashtags": result, "generated_at": datetime.utcnow().isoformat()}

    async def scrape_competitor_content(self, competitor_handles: list[str], platform: str) -> dict[str, Any]:
        """Analyze competitor content strategy (AI-simulated analysis)."""
        prompt = (
            f"Analyze the social media content strategy of these {platform} accounts: {', '.join(competitor_handles)}. "
            "Identify: top content types they use, posting frequency, best-performing formats, "
            "content themes, engagement patterns, weaknesses we can exploit, and content gaps. "
            "Provide specific, actionable intelligence."
        )
        from app.core.config import settings
        import anthropic
        try:
            client = anthropic.AsyncAnthropic(api_key=settings.ANTHROPIC_API_KEY)
            msg = await client.messages.create(
                model=settings.ANTHROPIC_MODEL, max_tokens=800,
                messages=[{"role": "user", "content": prompt}],
            )
            analysis = msg.content[0].text
        except Exception as e:
            analysis = f"Competitor analysis unavailable: {e}"
        return {"competitors": competitor_handles, "platform": platform, "analysis": analysis, "analyzed_at": datetime.utcnow().isoformat()}

    async def generate_reactive_content(self, trend: dict[str, Any], brand: dict[str, Any]) -> dict[str, Any]:
        prompt = (
            f"Create reactive content for this trend: {trend.get('name', 'trending topic')} on {trend.get('platform', 'social media')}.\n"
            f"Brand: {brand.get('name', 'the brand')}, Voice: {brand.get('voice', 'professional')}, Industry: {brand.get('industry', 'general')}.\n"
            "Generate: 1 caption, 1 hook, relevant hashtags, content angle, and posting urgency note. "
            "Make it on-brand yet trend-native. Return JSON."
        )
        result = await self._call_claude_json(prompt, max_tokens=600)
        return result if isinstance(result, dict) else {"content": result, "trend": trend.get("name")}

    async def monitor_news(self, topics: list[str], industries: list[str]) -> list[dict[str, Any]]:
        prompt = (
            f"Identify the top 5 news stories and industry developments this week relevant to: "
            f"Topics: {', '.join(topics)}. Industries: {', '.join(industries)}. "
            "For each: headline, why it matters for social media content, content opportunity angle, urgency."
        )
        result = await self._call_claude_json(prompt, max_tokens=700)
        return result if isinstance(result, list) else [{"news": result}]

    async def find_viral_sounds(self, platform: str = "tiktok") -> list[dict[str, Any]]:
        prompt = (
            f"List 8 currently trending audio tracks/sounds on {platform} "
            f"as of {datetime.utcnow().strftime('%B %Y')}. "
            "For each: track name/description, genre, use count estimate, best content pairing, decay speed."
        )
        result = await self._call_claude_json(prompt, max_tokens=600)
        return result if isinstance(result, list) else [{"sounds": result}]

    async def _call_claude_json(self, prompt: str, max_tokens: int = 800) -> Any:
        from app.core.config import settings
        import anthropic, json
        try:
            client = anthropic.AsyncAnthropic(api_key=settings.ANTHROPIC_API_KEY)
            msg = await client.messages.create(
                model=settings.ANTHROPIC_MODEL, max_tokens=max_tokens,
                system="You are a social media research analyst. Always respond with valid JSON only, no markdown.",
                messages=[{"role": "user", "content": prompt}],
            )
            text = msg.content[0].text.strip()
            if text.startswith("```"):
                text = text.split("```")[1]
                if text.startswith("json"):
                    text = text[4:]
            return json.loads(text)
        except Exception as e:
            logger.warning(f"Claude JSON call failed: {e}")
            return {}
