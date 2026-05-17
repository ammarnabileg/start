"""
VideoAgent - AI-driven video content planning, scripting, and production guidance.

Handles reel/TikTok scripts, talking-head formats, storyboards, B-roll suggestions,
music recommendations, subtitle generation, and hook-first editing guides.
"""

import json
import os
import re
from typing import Any, Dict, List, Optional

import anthropic

from .base_agent import AgentResult, BaseAgent

_ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6")

SYSTEM_PROMPT = """You are a world-class video director and social media producer with credits
on viral campaigns, Netflix content, and top-tier brand commercials. You understand the psychology
of retention, the science of hooks, and the craft of visual storytelling. You know every platform's
algorithm and what makes videos get pushed to the FYP or go viral. Always respond in JSON."""

HOOK_TYPES = {
    "pattern_interrupt": "Start with something unexpected that breaks the viewer's autopilot scrolling",
    "bold_claim": "Open with a controversial or surprising statement",
    "question": "Ask a question that creates an irresistible need to know the answer",
    "pain_point": "Immediately address a pain the viewer feels right now",
    "teaser": "Show the end result first, then explain how to get there",
    "visual": "Lead with a striking, unexpected visual moment",
    "story": "Begin mid-story with tension already established",
    "social_proof": "Open with impressive results or social validation",
}

TRANSITION_STYLES = {
    "tiktok": ["jump cut", "whip pan", "match cut", "zoom transition", "spin transition"],
    "instagram": ["smooth dissolve", "swipe cut", "color transition", "freeze frame"],
    "youtube": ["j-cut", "l-cut", "cutaway", "cross dissolve", "smash cut"],
    "linkedin": ["clean cut", "subtle dissolve", "title card transition"],
}

MUSIC_MOODS = {
    "energetic": {"tempo": "120-140 BPM", "genres": ["EDM", "Pop", "Hip-Hop"], "feel": "pump-up, exciting"},
    "inspirational": {"tempo": "90-110 BPM", "genres": ["Cinematic", "Indie", "Pop"], "feel": "uplifting, emotional"},
    "luxury": {"tempo": "70-90 BPM", "genres": ["Jazz", "Classical", "Lo-fi"], "feel": "sophisticated, premium"},
    "funny": {"tempo": "variable", "genres": ["Comedy", "Quirky", "Cartoon"], "feel": "playful, light-hearted"},
    "educational": {"tempo": "80-100 BPM", "genres": ["Lo-fi", "Ambient", "Light Jazz"], "feel": "focused, calm"},
    "dramatic": {"tempo": "60-80 BPM", "genres": ["Cinematic", "Orchestral"], "feel": "intense, emotional"},
    "chill": {"tempo": "70-90 BPM", "genres": ["Lo-fi", "Chillhop", "Acoustic"], "feel": "relaxed, cozy"},
}


class VideoAgent(BaseAgent):
    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        super().__init__("video_agent", brand_id, redis_url)
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
            "generate_reel_script": self._generate_reel_script,
            "generate_video_concept": self._generate_video_concept,
            "create_talking_head_script": self._create_talking_head_script,
            "generate_storyboard": self._generate_storyboard,
            "suggest_broll": self._suggest_broll,
            "suggest_music": self._suggest_music,
            "generate_subtitles": self._generate_subtitles,
            "suggest_transitions": self._suggest_transitions,
            "hook_first_edit_guide": self._hook_first_edit_guide,
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
            self._log.exception("video_agent_error", extra={"task": task})
            return self._make_result(task, None, start, error=str(exc))

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    async def generate_reel_script(
        self,
        topic: str,
        duration: int = 30,
        hook_type: str = "pattern_interrupt",
        language: str = "english",
        platform: str = "instagram",
    ) -> Dict[str, Any]:
        return await self._generate_reel_script(
            topic=topic, duration=duration, hook_type=hook_type,
            language=language, platform=platform,
        )

    async def generate_video_concept(
        self,
        trend: str,
        brand: Optional[Dict] = None,
        platform: str = "tiktok",
        count: int = 5,
    ) -> List[Dict[str, Any]]:
        return await self._generate_video_concept(
            trend=trend, brand=brand, platform=platform, count=count,
        )

    async def create_talking_head_script(
        self,
        topic: str,
        duration: int = 60,
        cta: str = "",
        style: str = "educational",
        language: str = "english",
    ) -> Dict[str, Any]:
        return await self._create_talking_head_script(
            topic=topic, duration=duration, cta=cta, style=style, language=language,
        )

    async def generate_storyboard(
        self,
        script: Dict[str, Any],
        style: str = "cinematic",
        platform: str = "instagram",
    ) -> List[Dict[str, Any]]:
        return await self._generate_storyboard(
            script=script, style=style, platform=platform,
        )

    async def suggest_broll(
        self,
        scene_description: str,
        count: int = 10,
        platform: str = "instagram",
    ) -> List[Dict[str, str]]:
        return await self._suggest_broll(
            scene_description=scene_description, count=count, platform=platform,
        )

    async def suggest_music(
        self,
        mood: str,
        genre: Optional[str] = None,
        duration: int = 30,
        platform: str = "instagram",
    ) -> Dict[str, Any]:
        return await self._suggest_music(
            mood=mood, genre=genre, duration=duration, platform=platform,
        )

    async def generate_subtitles(
        self,
        transcript: str,
        style: str = "bold",
        language: str = "english",
        platform: str = "tiktok",
    ) -> Dict[str, Any]:
        return await self._generate_subtitles(
            transcript=transcript, style=style, language=language, platform=platform,
        )

    async def suggest_transitions(
        self,
        video_type: str,
        platform: str = "tiktok",
        scene_count: int = 5,
    ) -> List[Dict[str, str]]:
        return await self._suggest_transitions(
            video_type=video_type, platform=platform, scene_count=scene_count,
        )

    async def hook_first_edit_guide(
        self,
        content: str,
        platform: str = "tiktok",
        duration: int = 30,
    ) -> Dict[str, Any]:
        return await self._hook_first_edit_guide(
            content=content, platform=platform, duration=duration,
        )

    # ------------------------------------------------------------------
    # Internal implementations
    # ------------------------------------------------------------------

    async def _generate_reel_script(
        self,
        topic: str,
        duration: int = 30,
        hook_type: str = "pattern_interrupt",
        language: str = "english",
        platform: str = "instagram",
        **_,
    ) -> Dict[str, Any]:
        hook_desc = HOOK_TYPES.get(hook_type, HOOK_TYPES["pattern_interrupt"])
        words_needed = int(duration * 2.5)  # ~150 WPM for energetic delivery

        prompt = f"""Write a viral {platform} reel script about: "{topic}"

Duration: {duration} seconds (~{words_needed} words when spoken)
Hook type: {hook_type} — {hook_desc}
Language: {language}
Platform: {platform}

{platform.upper()} ALGORITHM RULES:
- First 1-3 seconds are make-or-break
- Retention curve must stay above 70% at 15-second mark
- Pattern interrupt every 5-7 seconds
- End with a loop or cliffhanger for rewatch rate

Return a JSON object:
{{
  "title": "internal title",
  "hook_line": "exact first words (spoken in seconds 0-3)",
  "hook_visual": "what happens visually in seconds 0-3",
  "script_segments": [
    {{
      "second_start": 0,
      "second_end": 5,
      "spoken": "exact words",
      "visual": "camera/scene direction",
      "text_overlay": "on-screen text if any",
      "action": "presenter action",
      "retention_strategy": "why viewers stay here"
    }}
  ],
  "cta": "closing call to action",
  "caption": "short post caption",
  "hashtags": ["list"],
  "estimated_viral_score": 0-100,
  "production_tips": ["list of production tips"],
  "trend_elements": ["trending elements to incorporate"]
}}"""

        response = await self._call_claude(prompt, task="generate_reel_script", max_tokens=3000)
        return self._parse_json(response["text"])

    async def _generate_video_concept(
        self,
        trend: str,
        brand: Optional[Dict] = None,
        platform: str = "tiktok",
        count: int = 5,
        **_,
    ) -> List[Dict[str, Any]]:
        brand_note = json.dumps(brand) if brand else "General brand"

        prompt = f"""Generate {count} creative video concepts based on this trend: "{trend}"

Platform: {platform}
Brand context: {brand_note}

For each concept, think about how to authentically adapt the trend to the brand
while maximizing shareability and algorithm performance.

Return a JSON array:
[
  {{
    "concept_title": "string",
    "trend_angle": "how this uses the trend",
    "hook": "opening 3 seconds description",
    "full_concept": "2-3 sentence description",
    "format": "duet|stitch|original|reaction|tutorial|storytime",
    "difficulty": "easy|medium|hard",
    "estimated_budget": "zero|low|medium|high",
    "equipment_needed": ["list"],
    "viral_potential": "low|medium|high|very_high",
    "target_audience": "string",
    "best_day_to_post": "string",
    "trend_longevity": "days_left_in_trend"
  }}
]"""

        response = await self._call_claude(prompt, task="generate_video_concept", max_tokens=3000)
        return self._parse_json(response["text"])

    async def _create_talking_head_script(
        self,
        topic: str,
        duration: int = 60,
        cta: str = "",
        style: str = "educational",
        language: str = "english",
        **_,
    ) -> Dict[str, Any]:
        words_needed = int(duration * 130 / 60)  # normal speaking pace

        prompt = f"""Write a talking-head video script about: "{topic}"

Duration: {duration} seconds (~{words_needed} words)
Style: {style}
Language: {language}
CTA goal: {cta or "Follow for more"}

Talking-head best practices:
- Look directly into lens at all times
- Vary energy every 15 seconds to maintain attention
- Use hand gestures cues in the script
- Include pauses for emphasis
- Personal stories or analogies for connection

Return a JSON object:
{{
  "title": "video title",
  "thumbnail_hook": "text overlay for thumbnail",
  "script": [
    {{
      "segment": "intro|hook|body|transition|conclusion|cta",
      "time_range": "0:00-0:10",
      "spoken_words": "exact script",
      "delivery_note": "how to deliver (tone, speed, emphasis)",
      "gesture_cue": "suggested hand/body movement",
      "energy_level": "low|medium|high"
    }}
  ],
  "total_words": 0,
  "key_messages": ["main points"],
  "b_roll_opportunities": ["moments where cutaways add value"],
  "captions_highlight_words": ["words to emphasize in captions"],
  "thumbnail_text": "3-5 word thumbnail hook"
}}"""

        response = await self._call_claude(prompt, task="create_talking_head_script", max_tokens=3000)
        return self._parse_json(response["text"])

    async def _generate_storyboard(
        self,
        script: Dict[str, Any],
        style: str = "cinematic",
        platform: str = "instagram",
        **_,
    ) -> List[Dict[str, Any]]:
        script_summary = json.dumps(script, ensure_ascii=False)[:4000]

        prompt = f"""Create a detailed visual storyboard from this video script.

Script data:
{script_summary}

Visual style: {style}
Platform: {platform}

Return a JSON array of storyboard frames:
[
  {{
    "frame_number": 1,
    "timestamp": "0:00-0:03",
    "shot_type": "extreme_close_up|close_up|medium|wide|aerial|pov",
    "camera_angle": "eye_level|low_angle|high_angle|dutch_angle",
    "camera_movement": "static|pan|tilt|dolly|handheld|zoom",
    "scene_description": "detailed visual description",
    "lighting": "natural|studio|golden_hour|artificial|moody",
    "color_palette": ["hex or description"],
    "subject_position": "center|rule_of_thirds_left|rule_of_thirds_right",
    "background": "description",
    "props": ["list of props"],
    "text_overlay": "any text on screen",
    "transition_out": "cut|dissolve|swipe|zoom",
    "mood": "string",
    "production_note": "any special instruction"
  }}
]"""

        response = await self._call_claude(prompt, task="generate_storyboard", max_tokens=4000)
        return self._parse_json(response["text"])

    async def _suggest_broll(
        self,
        scene_description: str,
        count: int = 10,
        platform: str = "instagram",
        **_,
    ) -> List[Dict[str, str]]:
        prompt = f"""Suggest {count} specific B-roll shots for this scene: "{scene_description}"
Platform: {platform}

Think about visual variety, emotional impact, and what's achievable without a professional crew.

Return a JSON array:
[
  {{
    "shot_description": "exact shot description",
    "shot_type": "cutaway|insert|reaction|establishing|detail|abstract",
    "location": "where to film this",
    "equipment": "phone|camera|drone|tripod|gimbal",
    "duration": "recommended duration in seconds",
    "storytelling_purpose": "why this shot adds value",
    "difficulty": "easy|medium|hard",
    "stock_alternative": "search term to find stock footage alternative"
  }}
]"""

        response = await self._call_claude(prompt, task="suggest_broll", max_tokens=2000)
        return self._parse_json(response["text"])

    async def _suggest_music(
        self,
        mood: str,
        genre: Optional[str] = None,
        duration: int = 30,
        platform: str = "instagram",
        **_,
    ) -> Dict[str, Any]:
        mood_data = MUSIC_MOODS.get(mood.lower(), MUSIC_MOODS["energetic"])
        genre_note = f"Preferred genre: {genre}" if genre else ""

        prompt = f"""Suggest the perfect music for a {duration}-second {platform} video.

Mood: {mood}
{genre_note}
Mood characteristics: {json.dumps(mood_data)}

Platform considerations for {platform}:
- TikTok: Trending sounds boost reach dramatically
- Instagram: Use licensed music from Meta's library
- LinkedIn: Instrumental preferred, avoid trending audio
- YouTube: Use YouTube Audio Library or licensed tracks

Return a JSON object:
{{
  "primary_recommendation": {{
    "description": "type of track to look for",
    "tempo": "BPM range",
    "key_feel": "string",
    "placement": "how to use it (fade in, full track, loop)",
    "volume_mixing": "bg music level vs voice"
  }},
  "search_terms": ["list of search terms for music libraries"],
  "free_sources": ["Epidemic Sound", "Artlist", "YouTube Audio Library", "Pixabay"],
  "tiktok_trending_keywords": ["keywords to search on TikTok sounds"],
  "timing_guide": {{
    "intro": "music instruction for intro",
    "main": "music instruction for main content",
    "cta": "music instruction for CTA",
    "outro": "music instruction for outro"
  }},
  "sound_design": ["additional sound effects to layer"],
  "royalty_free_notes": "licensing guidance"
}}"""

        response = await self._call_claude(prompt, task="suggest_music", max_tokens=1500)
        return self._parse_json(response["text"])

    async def _generate_subtitles(
        self,
        transcript: str,
        style: str = "bold",
        language: str = "english",
        platform: str = "tiktok",
        **_,
    ) -> Dict[str, Any]:
        subtitle_styles = {
            "bold": "large, bold, white text with black outline, center screen",
            "minimal": "small, clean sans-serif, bottom third, semi-transparent background",
            "highlight": "word-by-word highlighting as spoken, karaoke style",
            "gen_z": "varied font sizes, color emphasis on key words, dynamic positioning",
            "branded": "brand font and colors, consistent positioning",
        }
        style_desc = subtitle_styles.get(style, subtitle_styles["bold"])

        prompt = f"""Process this transcript into optimized {platform} subtitles.

Transcript:
{transcript[:5000]}

Subtitle style: {style} — {style_desc}
Language: {language}

Return a JSON object:
{{
  "subtitle_style": "{style}",
  "total_duration_estimate": "string",
  "subtitles": [
    {{
      "id": 1,
      "start_time": "00:00:00,000",
      "end_time": "00:00:03,000",
      "text": "subtitle text",
      "styling": {{
        "font_size": "string",
        "font_weight": "normal|bold",
        "color": "#ffffff",
        "background": "none|semi|full",
        "position": "bottom|center|top",
        "emphasis_words": ["words to highlight"]
      }}
    }}
  ],
  "srt_content": "full SRT formatted subtitle file content",
  "vtt_content": "full WebVTT formatted subtitle file content",
  "production_tips": [
    "tip about subtitle placement",
    "tip about timing"
  ]
}}"""

        response = await self._call_claude(prompt, task="generate_subtitles", max_tokens=4000)
        return self._parse_json(response["text"])

    async def _suggest_transitions(
        self,
        video_type: str,
        platform: str = "tiktok",
        scene_count: int = 5,
        **_,
    ) -> List[Dict[str, str]]:
        platform_transitions = TRANSITION_STYLES.get(platform.lower(), TRANSITION_STYLES["tiktok"])

        prompt = f"""Suggest creative transitions for a {video_type} video on {platform}.

Number of scenes: {scene_count}
Available platform-native transitions: {platform_transitions}

Return a JSON array with one entry per transition (between scenes):
[
  {{
    "transition_number": 1,
    "from_scene": "description of outgoing scene",
    "to_scene": "description of incoming scene",
    "transition_type": "specific transition name",
    "execution": "how to film/edit this transition",
    "difficulty": "easy|medium|hard",
    "equipment_needed": ["list"],
    "timing": "cut point timing guidance",
    "why_it_works": "psychological/visual reason",
    "tutorial_search": "search term to find tutorial"
  }}
]"""

        response = await self._call_claude(prompt, task="suggest_transitions", max_tokens=2000)
        return self._parse_json(response["text"])

    async def _hook_first_edit_guide(
        self,
        content: str,
        platform: str = "tiktok",
        duration: int = 30,
        **_,
    ) -> Dict[str, Any]:
        prompt = f"""Create a hook-first editing guide for this video content on {platform}.

Content description: "{content}"
Target duration: {duration} seconds

Hook-first editing philosophy:
- The most compelling moment goes FIRST, not chronologically
- Retention drops 40% in first 3 seconds — make every frame count
- Create a "curiosity gap" that forces viewers to watch to the end
- Re-hook every 5-7 seconds with new information or pattern interrupt

Return a JSON object:
{{
  "edit_philosophy": "string",
  "hook_first_structure": [
    {{
      "position": 1,
      "time_range": "0-3s",
      "content": "what goes here",
      "purpose": "hook/retain/deliver/close",
      "original_chronology": "where this moment was in raw footage",
      "why_here": "editing reason"
    }}
  ],
  "retention_strategies": [
    {{
      "timestamp": "Xs",
      "technique": "pattern interrupt|new info|cliffhanger|social proof",
      "execution": "how to implement"
    }}
  ],
  "cut_pacing_guide": {{
    "hook_section": "cut every X seconds",
    "main_content": "cut every X seconds",
    "cta_section": "cut every X seconds"
  }},
  "color_grade_suggestion": "string",
  "music_sync_points": ["list of moments to sync music beats"],
  "caption_timing_guide": "string",
  "thumbnail_frame": "timestamp of best thumbnail frame",
  "loop_optimization": "how to make the video loop seamlessly",
  "estimated_retention_rate": "string"
}}"""

        response = await self._call_claude(prompt, task="hook_first_edit_guide", max_tokens=3000)
        return self._parse_json(response["text"])

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    async def _call_claude(
        self,
        prompt: str,
        task: str = "unknown",
        max_tokens: int = 2048,
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
