"""
Dynamic prompt builder – constructs richly-contextualized prompts for every AI agent task.

Injects brand voice, platform constraints, audience data, tone examples, language
settings, and strategy context to maximize AI output quality and on-brand consistency.
"""
from __future__ import annotations

import json
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional


# ---------------------------------------------------------------------------
# Context dataclass
# ---------------------------------------------------------------------------

@dataclass
class PromptContext:
    """Encapsulates all brand + task context needed to build a precise prompt."""
    platform: str = "instagram"
    language: str = "english"
    writing_style: str = "viral"
    brand_name: str = ""
    brand_voice: str = "professional"
    industry: str = ""
    usp: str = ""
    content_pillars: List[str] = field(default_factory=list)
    target_audience: Dict[str, Any] = field(default_factory=dict)
    brand_colors: Dict[str, str] = field(default_factory=dict)
    competitors_to_avoid: List[str] = field(default_factory=list)
    tone_examples: List[str] = field(default_factory=list)
    banned_words: List[str] = field(default_factory=list)
    content_type: str = "post"  # post|reel|story|carousel|thread|ad|dm
    goal: str = "engagement"    # engagement|awareness|conversion|retention

    def to_brand_brief(self) -> str:
        """Render a compact brand context string for injection into prompts."""
        parts = []
        if self.brand_name:
            parts.append(f"Brand: {self.brand_name}")
        if self.industry:
            parts.append(f"Industry: {self.industry}")
        if self.usp:
            parts.append(f"USP: {self.usp}")
        if self.brand_voice:
            parts.append(f"Voice: {self.brand_voice}")
        if self.content_pillars:
            parts.append(f"Pillars: {', '.join(self.content_pillars[:3])}")
        if self.target_audience:
            aud = self.target_audience
            parts.append(f"Audience: {aud.get('segment_name', aud.get('description', 'general'))}")
        if self.tone_examples:
            parts.append(f"Tone examples: {' | '.join(self.tone_examples[:2])}")
        if self.banned_words:
            parts.append(f"Never use: {', '.join(self.banned_words[:5])}")
        return ". ".join(parts)


# ---------------------------------------------------------------------------
# Static tables
# ---------------------------------------------------------------------------

PLATFORM_CHARS: Dict[str, int] = {
    "twitter":   280,
    "threads":   500,
    "snapchat":  250,
    "instagram": 2200,
    "tiktok":    2200,
    "facebook":  63206,
    "linkedin":  3000,
    "youtube":   5000,
    "pinterest": 500,
    "whatsapp":  1000,
    "telegram":  4096,
}

PLATFORM_CONTEXT: Dict[str, str] = {
    "instagram": (
        "Visual-first platform. Hook in the first line (before 'see more' cut-off). "
        "Use strategic line breaks. 8-15 hashtags for reach. Stories and Reels drive discovery. "
        "Carousels have highest save rates. Authentic aesthetics outperform over-polished content."
    ),
    "tiktok": (
        "Algorithm rewards watch-time and replays. Hook in first 1-3 seconds is critical. "
        "3-5 hashtags max. Native, raw feel outperforms high production. Trending audio boosts reach. "
        "Captions should match hook-first editing. FYP pushes content based on completion rate."
    ),
    "linkedin": (
        "Professional network for B2B and thought leadership. Personal story + business insight = viral. "
        "First 2 lines are shown before 'see more' — make them count. Short paragraphs. "
        "3-5 hashtags max. Polls and documents get extra distribution. Controversy drives comments."
    ),
    "twitter": (
        "Concise and punchy. Controversy, insights, and hot takes drive retweets. "
        "1-2 hashtags max. Threads allow longer narratives. Replies are indexed for reach. "
        "First tweet of thread must stand alone. Timing and trending topics matter enormously."
    ),
    "youtube": (
        "SEO-critical. Hook in title AND first 30 seconds. Include keyword-rich description. "
        "Chapters improve watch-time. 5-10 hashtags in description. Thumbnails drive 80% of clicks. "
        "Pattern interrupts every 2 minutes prevent drop-off."
    ),
    "facebook": (
        "Community and sharing-focused. Questions drive engagement. Longer-form content accepted. "
        "2-5 hashtags. Groups amplify reach significantly. Video native to Facebook outperforms links."
    ),
    "threads": (
        "Conversational, raw, honest tone. Text-first platform. No hashtag spam. "
        "Authenticity over polish. Short to medium length. Opinion pieces drive replies."
    ),
    "pinterest": (
        "Keyword-rich. Inspirational and aspirational. How-to and tutorial formats perform best. "
        "Vertical images (2:3 ratio). Evergreen content gets discovered for months."
    ),
    "snapchat": (
        "Gen-Z native. Ephemeral feel. Casual, fun, unfiltered. Very short captions. "
        "AR lenses and filters drive engagement. 24-hour Stories mindset."
    ),
    "whatsapp": (
        "Personal, direct, conversational. Broadcast-style or community groups. "
        "Value-first messaging. No hard selling. High open rates — treat as premium channel."
    ),
    "telegram": (
        "Channel-style broadcasting. Can be long-form. Supports rich formatting. "
        "Links and files drive value. Community discussion in groups. Less algorithmic noise."
    ),
}

STYLE_SYSTEM_PROMPTS: Dict[str, str] = {
    "corporate": (
        "Write in a professional, authoritative, and polished corporate tone. "
        "Use clear, confident language. Reference data and credibility signals. "
        "Avoid slang. Structure thoughts logically."
    ),
    "luxury": (
        "Write with understated elegance and exclusivity. Every word must evoke premium quality. "
        "Short, evocative sentences. Sensory language. Never use discount language or urgency tactics. "
        "The brand speaks to connoisseurs, not bargain hunters."
    ),
    "viral": (
        "Write to maximize shares, comments, and saves. Use bold claims, curiosity gaps, "
        "and pattern interrupts. Challenge conventional wisdom. Create FOMO. "
        "Be provocative but not offensive. Every line should earn the next."
    ),
    "aggressive": (
        "Write with raw urgency and directness. No fluff. Power verbs. Results-focused. "
        "Challenge the reader. Use scarcity and loss aversion. Don't apologize. "
        "Make them feel they'll lose if they don't act NOW."
    ),
    "educational": (
        "Write to genuinely teach and add value. Explain complex things simply. "
        "Use analogies, examples, and step-by-step structures. Acknowledge what readers already know. "
        "Build authority through expertise, not ego."
    ),
    "emotional": (
        "Write to connect on a human level. Tell stories with vulnerability and authenticity. "
        "Touch the heart before the mind. Use specific sensory details. "
        "Show, don't tell emotions. Make the reader feel seen and understood."
    ),
    "minimal": (
        "Write with extreme economy. Every word must earn its place. "
        "Short sentences. White space is content. "
        "One idea per line. Zen-like restraint. Silence is powerful."
    ),
    "gen_z": (
        "Write in authentic Gen-Z voice: lowercase often, self-aware, ironic, internet-native. "
        "Abbreviations are fine. Be real, not corporate. Meme-adjacent references. "
        "Chaotic energy is a feature. Anti-cringe is cringe. Be unapologetically yourself."
    ),
    "b2b": (
        "Write for business decision-makers: C-suite, managers, founders. "
        "ROI-focused, problem-solution framing. Data-backed claims. "
        "Professional but not boring. Speak to their business outcomes, not features."
    ),
    "b2c": (
        "Write for everyday consumers. Benefit-driven. Make them feel something. "
        "Inclusive language. Accessible vocabulary. Relatable situations. "
        "Celebrate the customer, not the product."
    ),
}

LANGUAGE_INSTRUCTIONS: Dict[str, str] = {
    "english": "Write in clear, natural English. Match register to the brand voice.",
    "arabic": (
        "اكتب باللغة العربية الفصحى المعاصرة (MSA). "
        "استخدم تنسيقاً من اليمين إلى اليسار. "
        "أضف هاشتاقات عربية ذات صلة."
    ),
    "arabic_formal": (
        "اكتب بالعربية الفصحى الرسمية فقط. "
        "لا تستخدم العامية. مستوى لغوي رفيع."
    ),
    "arabic_gulf": (
        "اكتب بالعامية الخليجية (إماراتية/سعودية). "
        "استخدم تعابير وكلمات دارجة في منطقة الخليج. "
        "احتفظ بالأصالة والطابع المحلي."
    ),
    "arabic_levantine": (
        "اكتب بالعامية الشامية (سورية/لبنانية/أردنية). "
        "استخدم تعابير شامية طبيعية وأصيلة."
    ),
    "arabic_egyptian": (
        "اكتب بالعامية المصرية. "
        "استخدم الأسلوب المصري الطبيعي والمعروف."
    ),
    "mixed": (
        "Write in Arabizi: a natural mix of Arabic words and English, "
        "using Latin script for Arabic sounds. "
        "Example: 'ana 3arif eno el content lazem ykoon authentic, "
        "not just copy-paste from competitors.' "
        "Code-switch as a bilingual Arab millennial would naturally."
    ),
    "french": "Écrivez en français courant et naturel. Adaptez le registre au style de la marque.",
    "spanish": "Escribe en español natural y fluido. Adapta el registro al estilo de la marca.",
}

AGENT_ROLE_PROMPTS: Dict[str, str] = {
    "creative_director": (
        "You are a world-class Creative Director with 20+ years at top-tier global agencies. "
        "You've launched campaigns for Fortune 500 brands and built viral moments from scratch. "
        "You see the big picture AND the details. You balance creativity with conversion."
    ),
    "copywriter": (
        "You are an elite social media copywriter known for writing content that stops scrolls, "
        "builds communities, and drives sales. You've written viral posts across every platform. "
        "You master every tone from luxury whisper to Gen-Z chaos."
    ),
    "strategist": (
        "You are a senior social media strategist who builds scalable growth systems for 8-figure brands. "
        "You think in frameworks, validate with data, and always connect content to business outcomes. "
        "You've managed strategy for brands across MENA, Europe, and North America."
    ),
    "community_manager": (
        "You are an expert community manager with deep empathy and brand fluency. "
        "You turn angry customers into brand advocates and lurkers into evangelists. "
        "You handle everything from delighted fans to crisis comments with equal grace."
    ),
    "analyst": (
        "You are a data-driven social media analyst who translates raw metrics into clear business insights. "
        "You spot patterns, identify opportunities, and present findings in plain language. "
        "You blend quantitative analysis with qualitative content intelligence."
    ),
    "researcher": (
        "You are a social media trend intelligence analyst. "
        "You monitor platform algorithms, viral patterns, competitor strategies, and cultural moments. "
        "You surface opportunities before they peak and advise on reactive content timing."
    ),
    "video_director": (
        "You are a world-class video director who has directed viral social media campaigns, "
        "Netflix-level content, and award-winning brand commercials. "
        "You understand retention psychology, hook engineering, and platform-specific production."
    ),
    "cso": (
        "You are the Chief Strategy Officer of a world-class social media agency. "
        "You have deep expertise in brand positioning, audience psychology, content strategy, "
        "and platform algorithms. Your output is always structured, data-driven, and immediately actionable."
    ),
}


class PromptEngine:
    """
    Constructs optimized, context-rich prompts for every AI agent task.

    Usage:
        engine = PromptEngine()
        ctx = PromptContext(platform="instagram", brand_name="Luxe", writing_style="luxury")
        prompt = engine.build_caption_prompt("Summer collection launch", ctx)
    """

    def build_caption_prompt(self, topic: str, ctx: PromptContext) -> str:
        """Build a full caption generation prompt with all brand context."""
        char_limit = PLATFORM_CHARS.get(ctx.platform, 2200)
        platform_ctx = PLATFORM_CONTEXT.get(ctx.platform, "")
        style_instruction = STYLE_SYSTEM_PROMPTS.get(ctx.writing_style, "")
        lang_instruction = LANGUAGE_INSTRUCTIONS.get(ctx.language, "")
        brand_brief = ctx.to_brand_brief()

        lines = [
            f"You are an elite social media copywriter. {style_instruction}",
            "",
            f"PLATFORM: {ctx.platform.upper()}",
            f"Platform context: {platform_ctx}",
            f"Language: {lang_instruction}",
            f"Character limit: {char_limit}",
            "",
            f"BRAND CONTEXT: {brand_brief}" if brand_brief else "",
            f"Content goal: {ctx.goal}",
            "",
            f"TASK: Create a high-performing {ctx.platform} caption about: {topic}",
            "",
            "Requirements:",
            "• Attention-grabbing hook (first line is everything)",
            "• Value-packed body that delivers on the hook's promise",
            "• One clear CTA matched to the goal",
            f"• {'8-15 relevant hashtags' if ctx.platform in ('instagram',) else '3-5 relevant hashtags' if ctx.platform in ('tiktok', 'linkedin') else '1-2 hashtags' if ctx.platform == 'twitter' else 'relevant hashtags'}",
            "• Strategic line breaks for mobile readability",
            "",
            "Return ONLY the final caption text, ready to copy-paste.",
        ]
        return "\n".join(l for l in lines if l is not None)

    def build_system_prompt(
        self,
        agent_role: str,
        brand_context: Optional[Dict[str, Any]] = None,
        output_format: str = "json",
    ) -> str:
        """Build a role-specific system prompt with optional brand injection."""
        base = AGENT_ROLE_PROMPTS.get(agent_role, "You are an expert AI assistant.")

        if brand_context:
            brand_info = (
                f"\n\nYou are working for: {brand_context.get('name', 'the brand')}. "
                f"Industry: {brand_context.get('industry', 'general')}. "
                f"Brand voice: {brand_context.get('voice', 'professional')}. "
                f"USP: {brand_context.get('usp', '')}."
            )
            base += brand_info

        if output_format == "json":
            base += "\n\nAlways respond with valid JSON only. No markdown fences. No explanations outside the JSON."

        return base

    def build_strategy_prompt(
        self,
        task: str,
        brand_data: Optional[Dict[str, Any]] = None,
        additional_context: str = "",
    ) -> str:
        """Build strategy-layer prompts (CSO persona)."""
        system = AGENT_ROLE_PROMPTS["cso"]
        context_block = json.dumps(brand_data, ensure_ascii=False)[:4000] if brand_data else "No brand data provided."

        return (
            f"{system}\n\n"
            f"Brand data:\n{context_block}\n\n"
            f"{additional_context}\n\n"
            f"Task: {task}\n\n"
            "Respond with valid JSON only."
        )

    def build_community_reply_prompt(
        self,
        message: str,
        message_type: str,
        platform: str,
        brand_voice: str,
        context: str = "",
    ) -> str:
        """Build prompts for community management responses."""
        platform_note = PLATFORM_CONTEXT.get(platform, "")
        return (
            f"You are an expert community manager. Brand voice: {brand_voice}.\n"
            f"Platform: {platform}. {platform_note}\n\n"
            f"Message ({message_type}): \"{message}\"\n"
            f"{'Context: ' + context if context else ''}\n\n"
            "Write a short, human, on-brand reply. Max 2-3 sentences. "
            "Be genuine. Match the message's emotional energy.\n"
            "Return JSON: {\"reply\": \"...\", \"sentiment\": \"...\", \"action\": \"...\"}"
        )

    def build_ad_copy_prompt(
        self,
        product: str,
        audience: str,
        platform: str,
        objective: str,
        style: str = "aggressive",
        language: str = "english",
    ) -> str:
        """Build high-converting ad copy prompts."""
        style_note = STYLE_SYSTEM_PROMPTS.get(style, "")
        lang_note = LANGUAGE_INSTRUCTIONS.get(language, "")
        platform_note = PLATFORM_CONTEXT.get(platform, "")

        return (
            f"You are an expert direct-response copywriter. {style_note}\n\n"
            f"Platform: {platform} — {platform_note}\n"
            f"Language: {lang_note}\n\n"
            f"Product/Service: {product}\n"
            f"Target audience: {audience}\n"
            f"Campaign objective: {objective}\n\n"
            "Create ad copy that converts. Focus on the core benefit, address a specific pain point, "
            "and include a compelling CTA. Use social proof language if possible.\n\n"
            "Return JSON with: headline, subheadline, primary_text, cta_button, pain_addressed, usp."
        )

    def build_research_prompt(
        self,
        platform: str,
        niche: str,
        task_type: str,
        date_context: str = "",
    ) -> str:
        """Build research and trend scanning prompts."""
        return (
            f"You are a social media research analyst specializing in {platform}.\n"
            f"{date_context}\n\n"
            f"Research task: {task_type}\n"
            f"Niche/Industry: {niche}\n"
            f"Platform context: {PLATFORM_CONTEXT.get(platform, '')}\n\n"
            "Provide specific, data-like, actionable intelligence. "
            "When estimating numbers, be realistic based on platform scale. "
            "Return valid JSON only."
        )

    @staticmethod
    def inject_brand_voice(base_prompt: str, ctx: PromptContext) -> str:
        """Append brand voice injection to any existing prompt."""
        injection_parts = []
        if ctx.brand_name:
            injection_parts.append(f"Brand: {ctx.brand_name}")
        if ctx.brand_voice:
            injection_parts.append(f"Voice: {ctx.brand_voice}")
        if ctx.tone_examples:
            injection_parts.append(f"Tone examples: {' | '.join(ctx.tone_examples[:2])}")
        if ctx.banned_words:
            injection_parts.append(f"NEVER use these words: {', '.join(ctx.banned_words)}")
        if ctx.competitors_to_avoid:
            injection_parts.append(f"Don't mention: {', '.join(ctx.competitors_to_avoid)}")

        if injection_parts:
            return base_prompt + "\n\nBrand voice injection:\n" + "\n".join(f"• {p}" for p in injection_parts)
        return base_prompt

    @staticmethod
    def truncate_for_context(text: str, max_chars: int = 4000) -> str:
        """Safely truncate context text for prompt injection."""
        if len(text) <= max_chars:
            return text
        return text[:max_chars - 50] + "\n...[truncated for context window]"


# Module-level singleton
prompt_engine = PromptEngine()
