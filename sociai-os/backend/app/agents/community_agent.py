"""CommunityAgent – auto-reply, DM handling, spam filter, lead qualification, escalation."""
from __future__ import annotations
import logging
import re
from typing import Any, Optional
from app.agents.base_agent import BaseAgent, AgentResult
logger = logging.getLogger(__name__)

SPAM_PATTERNS = [
    r"(?i)(follow back|f4f|l4l|like4like|check my profile|dm for promo)",
    r"(?i)(free money|click here|earn \$\d+|make money fast)",
    r"(?i)(giveaway winner|you have been selected|claim your prize)",
]
LEAD_SIGNALS = ["price", "pricing", "cost", "how much", "contact", "order", "buy", "quote", "demo", "trial", "service", "package"]
ESCALATION_TRIGGERS = ["urgent", "lawsuit", "lawyer", "scam", "fraud", "threatening", "abuse", "refund", "emergency"]


class CommunityAgent(BaseAgent):
    agent_type = "community"

    async def execute(self, task: str, **kwargs) -> AgentResult:
        start = self._start_timer()
        try:
            result = await getattr(self, task)(**kwargs)
            return self._make_result(True, result, task, self._elapsed_ms(start))
        except Exception as e:
            logger.exception(f"CommunityAgent.{task} failed")
            return self._make_result(False, None, task, self._elapsed_ms(start), error=str(e))

    async def auto_reply_comment(self, comment: str, brand_voice: str, platform: str, post_context: str = "") -> dict[str, Any]:
        is_spam = self.detect_spam(comment)
        if is_spam["is_spam"]:
            return {"action": "hide", "reason": "spam_detected", "spam_details": is_spam}
        sentiment = self.analyze_comment_sentiment(comment)
        needs_escalation = self.needs_escalation(comment)
        if needs_escalation:
            return {"action": "escalate", "sentiment": sentiment, "reason": "escalation_trigger"}
        reply = await self._generate_reply(comment, brand_voice, platform, "comment", post_context)
        return {"action": "reply", "suggested_reply": reply, "sentiment": sentiment, "requires_approval": sentiment["label"] == "negative"}

    async def handle_dm(self, message: str, platform: str, brand_context: dict[str, Any]) -> dict[str, Any]:
        is_spam = self.detect_spam(message)
        if is_spam["is_spam"]:
            return {"action": "ignore", "reason": "spam"}
        lead_score = self.qualify_lead(message)
        needs_escalation = self.needs_escalation(message)
        sentiment = self.analyze_comment_sentiment(message)
        reply = await self._generate_reply(message, brand_context.get("voice", "friendly"), platform, "dm", str(brand_context))
        return {
            "action": "reply",
            "suggested_reply": reply,
            "lead_score": lead_score,
            "needs_escalation": needs_escalation,
            "sentiment": sentiment,
            "requires_human_review": lead_score["score"] >= 7 or needs_escalation,
        }

    def detect_spam(self, text: str) -> dict[str, Any]:
        for pattern in SPAM_PATTERNS:
            if re.search(pattern, text):
                return {"is_spam": True, "pattern_matched": pattern}
        words = text.lower().split()
        if len(set(words)) / max(len(words), 1) < 0.3 and len(words) > 5:
            return {"is_spam": True, "reason": "low_vocabulary_diversity"}
        return {"is_spam": False}

    def analyze_comment_sentiment(self, text: str) -> dict[str, Any]:
        positive_words = set("love great amazing awesome fantastic wonderful best excellent perfect".split())
        negative_words = set("hate terrible awful worst horrible bad disappointing useless".split())
        words = text.lower().split()
        pos = sum(1 for w in words if w in positive_words)
        neg = sum(1 for w in words if w in negative_words)
        if pos > neg: label, score = "positive", min(0.5 + pos * 0.15, 1.0)
        elif neg > pos: label, score = "negative", max(0.5 - neg * 0.15, 0.0)
        else: label, score = "neutral", 0.5
        return {"label": label, "score": round(score, 3), "positive_signals": pos, "negative_signals": neg}

    def qualify_lead(self, message: str) -> dict[str, Any]:
        lower = message.lower()
        signals_found = [s for s in LEAD_SIGNALS if s in lower]
        score = min(len(signals_found) * 2, 10)
        return {"score": score, "signals": signals_found, "is_qualified": score >= 4}

    def needs_escalation(self, text: str) -> bool:
        lower = text.lower()
        return any(trigger in lower for trigger in ESCALATION_TRIGGERS)

    async def generate_faq_response(self, question: str, brand_context: dict[str, Any]) -> dict[str, Any]:
        reply = await self._generate_reply(question, brand_context.get("voice", "professional"), "general", "faq", str(brand_context))
        return {"question": question, "suggested_answer": reply, "confidence": 0.85}

    async def _generate_reply(self, message: str, brand_voice: str, platform: str, context_type: str, extra_context: str = "") -> str:
        try:
            from app.core.config import settings
            import anthropic
            client = anthropic.AsyncAnthropic(api_key=settings.ANTHROPIC_API_KEY)
            system = (
                f"You are a community manager for a brand with a {brand_voice} voice on {platform}. "
                "Write a short, authentic reply. Be human, helpful, and on-brand. Max 2 sentences."
            )
            msg = await client.messages.create(
                model=settings.ANTHROPIC_MODEL, max_tokens=150,
                system=system, messages=[{"role": "user", "content": f"Reply to this {context_type}: {message}\nContext: {extra_context[:200]}"}],
            )
            return msg.content[0].text
        except Exception as e:
            logger.warning(f"Reply generation failed: {e}")
            return "Thank you for your message! Our team will get back to you shortly. 🙏"
