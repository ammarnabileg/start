"""AgentOrchestrator – coordinates all AI agents in multi-step workflows."""
from __future__ import annotations
import asyncio
import logging
import uuid
from datetime import datetime
from typing import Any, Optional
from app.agents.strategy_agent import StrategyAgent
from app.agents.copywriting_agent import CopywritingAgent
from app.agents.design_agent import DesignAgent
from app.agents.video_agent import VideoAgent
from app.agents.voice_agent import VoiceAgent
from app.agents.publishing_agent import PublishingAgent
from app.agents.analytics_agent import AnalyticsAgent
from app.agents.community_agent import CommunityAgent
from app.agents.research_agent import ResearchAgent

logger = logging.getLogger(__name__)


class AgentOrchestrator:
    """Coordinates all 9 AI agents via shared Redis context and async workflows."""

    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        self.brand_id = brand_id
        self.redis_url = redis_url
        self._agents: dict[str, Any] = {}

    def _get_agent(self, agent_class, name: str):
        if name not in self._agents:
            self._agents[name] = agent_class(brand_id=self.brand_id, redis_url=self.redis_url)
        return self._agents[name]

    @property
    def strategy(self) -> StrategyAgent:
        return self._get_agent(StrategyAgent, "strategy")

    @property
    def copywriting(self) -> CopywritingAgent:
        return self._get_agent(CopywritingAgent, "copywriting")

    @property
    def design(self) -> DesignAgent:
        return self._get_agent(DesignAgent, "design")

    @property
    def video(self) -> VideoAgent:
        return self._get_agent(VideoAgent, "video")

    @property
    def voice(self) -> VoiceAgent:
        return self._get_agent(VoiceAgent, "voice")

    @property
    def publishing(self) -> PublishingAgent:
        return self._get_agent(PublishingAgent, "publishing")

    @property
    def analytics(self) -> AnalyticsAgent:
        return self._get_agent(AnalyticsAgent, "analytics")

    @property
    def community(self) -> CommunityAgent:
        return self._get_agent(CommunityAgent, "community")

    @property
    def research(self) -> ResearchAgent:
        return self._get_agent(ResearchAgent, "research")

    async def run_full_strategy_workflow(self, strategy_doc_path: str) -> dict[str, Any]:
        """Strategy → Content Pillars → Monthly Plan → Campaign Ideas → Posting Calendar."""
        workflow_id = str(uuid.uuid4())
        logger.info(f"[Workflow {workflow_id}] Starting full strategy workflow for brand {self.brand_id}")
        results: dict[str, Any] = {"workflow_id": workflow_id, "started_at": datetime.utcnow().isoformat()}

        # Step 1: Strategy analysis
        strategy_result = await self.strategy.execute("analyze_document", file_path=strategy_doc_path)
        results["strategy"] = strategy_result.data
        await self.strategy.set_shared_context(workflow_id, "strategy_data", strategy_result.data)

        # Step 2: Parallel – audience + pillars + tone
        pillar_task = self.strategy.execute("extract_content_pillars")
        audience_task = self.strategy.execute("extract_target_audience")
        tone_task = self.strategy.execute("detect_brand_tone")
        pillars_r, audience_r, tone_r = await asyncio.gather(pillar_task, audience_task, tone_task, return_exceptions=True)

        results["content_pillars"] = getattr(pillars_r, "data", None)
        results["target_audience"] = getattr(audience_r, "data", None)
        results["brand_tone"] = getattr(tone_r, "data", None)

        # Step 3: Monthly plan
        plan_result = await self.strategy.execute("generate_monthly_plan")
        results["monthly_plan"] = plan_result.data

        # Step 4: Research – parallel trend scan
        trend_result = await self.research.scan_all_trends(niche=str(results.get("content_pillars", "general"))[:50])
        results["current_trends"] = trend_result

        results["completed_at"] = datetime.utcnow().isoformat()
        results["status"] = "completed"
        logger.info(f"[Workflow {workflow_id}] Full strategy workflow completed")
        return results

    async def run_content_creation_workflow(self, brief: dict[str, Any]) -> dict[str, Any]:
        """Brief → Copy → Design Concept → Video Script → Schedule."""
        workflow_id = str(uuid.uuid4())
        platform = brief.get("platform", "instagram")
        topic = brief.get("topic", "")
        style = brief.get("style", "viral")
        language = brief.get("language", "english")

        # Step 1: Generate hooks + caption in parallel
        hook_task = self.copywriting.execute("generate_hooks", topic=topic, platform=platform, count=5, style=style, language=language)
        caption_task = self.copywriting.execute("generate_caption", platform=platform, topic=topic, style=style, language=language)
        cta_task = self.copywriting.execute("generate_cta", goal=brief.get("goal", "engagement"), platform=platform, style=style)
        hooks_r, caption_r, cta_r = await asyncio.gather(hook_task, caption_task, cta_task, return_exceptions=True)

        # Step 2: Video script if needed
        video_script = None
        if brief.get("content_type") in ("reel", "tiktok", "short"):
            video_result = await self.video.execute(
                "generate_reel_concept",
                topic=topic, platform=platform,
                hook=getattr(hooks_r, "data", {}).get("hooks", [""])[0] if not isinstance(hooks_r, Exception) else "",
            )
            video_script = getattr(video_result, "data", None)

        # Step 3: Design brief
        design_brief = await self.design.execute(
            "generate_social_post",
            topic=topic,
            platform=platform,
            style=style,
            caption=getattr(caption_r, "data", {}).get("caption", "") if not isinstance(caption_r, Exception) else "",
        )

        return {
            "workflow_id": workflow_id,
            "brief": brief,
            "hooks": getattr(hooks_r, "data", None),
            "caption": getattr(caption_r, "data", None),
            "cta": getattr(cta_r, "data", None),
            "video_script": video_script,
            "design_brief": getattr(design_brief, "data", None),
            "created_at": datetime.utcnow().isoformat(),
        }

    async def run_daily_operations(self) -> dict[str, Any]:
        """Daily automation: trend scan + community management check + analytics snapshot."""
        trend_task = self.research.scan_all_trends(niche="general", platforms=["tiktok", "instagram", "linkedin"])
        analytics_task = self.analytics.execute("generate_performance_report", brand_id=self.brand_id, period="last_7_days")
        trend_r, analytics_r = await asyncio.gather(trend_task, analytics_task, return_exceptions=True)
        return {
            "trends": trend_r if not isinstance(trend_r, Exception) else {"error": str(trend_r)},
            "analytics": getattr(analytics_r, "data", None),
            "run_at": datetime.utcnow().isoformat(),
            "brand_id": self.brand_id,
        }

    async def run_analytics_workflow(self, period: str = "last_30_days") -> dict[str, Any]:
        performance = await self.analytics.execute("generate_performance_report", brand_id=self.brand_id, period=period)
        weaknesses = await self.analytics.execute("identify_weaknesses", brand_id=self.brand_id)
        opportunities = await self.analytics.detect_trend_opportunities(industry="general", platforms=["instagram", "tiktok", "linkedin"])
        return {
            "performance": getattr(performance, "data", None),
            "weaknesses": getattr(weaknesses, "data", None),
            "opportunities": opportunities,
            "generated_at": datetime.utcnow().isoformat(),
        }

    async def run_community_management(self, interactions: list[dict[str, Any]], brand_voice: str) -> list[dict[str, Any]]:
        """Process a batch of community interactions in parallel."""
        tasks = []
        for interaction in interactions:
            if interaction.get("type") == "comment":
                tasks.append(self.community.auto_reply_comment(
                    comment=interaction.get("text", ""),
                    brand_voice=brand_voice,
                    platform=interaction.get("platform", "instagram"),
                ))
            else:
                tasks.append(self.community.handle_dm(
                    message=interaction.get("text", ""),
                    platform=interaction.get("platform", "instagram"),
                    brand_context={"voice": brand_voice},
                ))
        results = await asyncio.gather(*tasks, return_exceptions=True)
        return [r if not isinstance(r, Exception) else {"error": str(r)} for r in results]

    async def close(self) -> None:
        close_tasks = [a.close() for a in self._agents.values() if hasattr(a, "close")]
        await asyncio.gather(*close_tasks, return_exceptions=True)
