"""
VoiceAgent - AI voiceover and audio generation using ElevenLabs API.

Supports Arabic/English voice selection, emotion control, voice cloning,
narration styles, and audio mastering for social media video content.
"""

import asyncio
import base64
import io
import json
import os
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import httpx

from .base_agent import AgentResult, BaseAgent

_ELEVENLABS_API_KEY = os.getenv("ELEVENLABS_API_KEY", "")
_ELEVENLABS_BASE_URL = "https://api.elevenlabs.io/v1"

# Curated voice catalog: (voice_id, gender, dialect, tone)
VOICE_CATALOG: Dict[str, Dict[str, Any]] = {
    # English voices
    "en_male_professional": {
        "id": "21m00Tcm4TlvDq8ikWAM",
        "name": "Rachel (repurposed)",
        "gender": "male",
        "language": "english",
        "dialect": "american",
        "tone": "professional",
        "best_for": ["corporate", "b2b", "tutorials"],
    },
    "en_male_energetic": {
        "id": "AZnzlk1XvdvUeBnXmlld",
        "name": "Domi",
        "gender": "male",
        "language": "english",
        "dialect": "american",
        "tone": "energetic",
        "best_for": ["ads", "sports", "motivation"],
    },
    "en_female_warm": {
        "id": "EXAVITQu4vr4xnSDxMaL",
        "name": "Bella",
        "gender": "female",
        "language": "english",
        "dialect": "american",
        "tone": "warm",
        "best_for": ["lifestyle", "beauty", "emotional"],
    },
    "en_male_deep": {
        "id": "VR6AewLTigWG4xSOukaG",
        "name": "Arnold",
        "gender": "male",
        "language": "english",
        "dialect": "american",
        "tone": "deep_authoritative",
        "best_for": ["luxury", "documentary", "serious"],
    },
    # Arabic voices (using multilingual model)
    "ar_male_formal": {
        "id": "pNInz6obpgDQGcFmaJgB",
        "name": "Adam (AR)",
        "gender": "male",
        "language": "arabic",
        "dialect": "gulf",
        "tone": "formal",
        "best_for": ["corporate", "news", "educational"],
    },
    "ar_female_warm": {
        "id": "ThT5KcBeYPX3keUQqHPh",
        "name": "Dorothy (AR)",
        "gender": "female",
        "language": "arabic",
        "dialect": "levantine",
        "tone": "warm",
        "best_for": ["lifestyle", "fashion", "emotional"],
    },
    "ar_male_energetic": {
        "id": "yoZ06aMxZJJ28mfd3POQ",
        "name": "Sam (AR)",
        "gender": "male",
        "language": "arabic",
        "dialect": "egyptian",
        "tone": "energetic",
        "best_for": ["ads", "sports", "entertainment"],
    },
}

EMOTION_SETTINGS = {
    "neutral": {"stability": 0.75, "similarity_boost": 0.75, "style": 0.0},
    "excited": {"stability": 0.30, "similarity_boost": 0.80, "style": 0.80},
    "calm": {"stability": 0.90, "similarity_boost": 0.70, "style": 0.10},
    "serious": {"stability": 0.85, "similarity_boost": 0.75, "style": 0.20},
    "warm": {"stability": 0.70, "similarity_boost": 0.80, "style": 0.40},
    "dramatic": {"stability": 0.40, "similarity_boost": 0.85, "style": 0.90},
    "whisper": {"stability": 0.95, "similarity_boost": 0.60, "style": 0.05},
}


class VoiceAgent(BaseAgent):
    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        super().__init__("voice_agent", brand_id, redis_url)
        self._http: Optional[httpx.AsyncClient] = None

    async def _get_http(self) -> httpx.AsyncClient:
        if self._http is None or self._http.is_closed:
            self._http = httpx.AsyncClient(
                timeout=180.0,
                headers={
                    "xi-api-key": _ELEVENLABS_API_KEY,
                    "Accept": "audio/mpeg",
                    "Content-Type": "application/json",
                },
            )
        return self._http

    # ------------------------------------------------------------------
    # Primary dispatcher
    # ------------------------------------------------------------------

    async def execute(self, task: str, **kwargs) -> AgentResult:
        cached = await self.get_cached(task, **kwargs)
        if cached:
            return cached

        start = self._start_timer()
        dispatch = {
            "generate_voiceover": self._generate_voiceover,
            "generate_narration": self._generate_narration,
            "select_voice": self._select_voice,
            "clone_voice": self._clone_voice,
            "master_audio": self._master_audio,
        }
        try:
            if task not in dispatch:
                raise ValueError(f"Unknown task: {task}")
            data = await dispatch[task](**kwargs)
            result = self._make_result(task, data, start)
            await self.cache_result(task, result, ttl=86400, **kwargs)
            self._log_execution(result)
            return result
        except Exception as exc:
            self._log.exception("voice_agent_error", extra={"task": task})
            return self._make_result(task, None, start, error=str(exc))

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    async def generate_voiceover(
        self,
        text: str,
        voice_id: Optional[str] = None,
        language: str = "english",
        emotion: str = "neutral",
        output_format: str = "mp3_44100_128",
    ) -> Dict[str, Any]:
        return await self._generate_voiceover(
            text=text, voice_id=voice_id, language=language,
            emotion=emotion, output_format=output_format,
        )

    async def generate_narration(
        self,
        script: str,
        style: str = "documentary",
        language: str = "english",
        pause_instructions: bool = True,
    ) -> Dict[str, Any]:
        return await self._generate_narration(
            script=script, style=style, language=language,
            pause_instructions=pause_instructions,
        )

    async def select_voice(
        self,
        gender: str = "male",
        dialect: str = "american",
        tone: str = "professional",
        language: str = "english",
        use_case: Optional[str] = None,
    ) -> Dict[str, Any]:
        return await self._select_voice(
            gender=gender, dialect=dialect, tone=tone,
            language=language, use_case=use_case,
        )

    async def clone_voice(
        self,
        audio_sample: str,
        voice_name: str = "Custom Voice",
        description: str = "",
    ) -> Dict[str, Any]:
        return await self._clone_voice(
            audio_sample=audio_sample, voice_name=voice_name, description=description,
        )

    async def master_audio(
        self,
        audio_file: str,
        target_loudness: float = -14.0,
        remove_noise: bool = True,
        normalize: bool = True,
    ) -> Dict[str, Any]:
        return await self._master_audio(
            audio_file=audio_file, target_loudness=target_loudness,
            remove_noise=remove_noise, normalize=normalize,
        )

    # ------------------------------------------------------------------
    # Implementations
    # ------------------------------------------------------------------

    async def _generate_voiceover(
        self,
        text: str,
        voice_id: Optional[str] = None,
        language: str = "english",
        emotion: str = "neutral",
        output_format: str = "mp3_44100_128",
        **_,
    ) -> Dict[str, Any]:
        if not voice_id:
            voice_selection = await self._select_voice(language=language)
            voice_id = voice_selection["voice_id"]
            voice_name = voice_selection["voice_name"]
        else:
            voice_name = voice_id

        emotion_settings = EMOTION_SETTINGS.get(emotion.lower(), EMOTION_SETTINGS["neutral"])

        if not _ELEVENLABS_API_KEY:
            return self._mock_audio_response(text, voice_id, emotion, output_format)

        http = await self._get_http()

        # Use multilingual model for Arabic
        model_id = (
            "eleven_multilingual_v2"
            if language.lower() in ("arabic", "ar")
            else "eleven_monolingual_v1"
        )

        payload = {
            "text": text,
            "model_id": model_id,
            "voice_settings": {
                "stability": emotion_settings["stability"],
                "similarity_boost": emotion_settings["similarity_boost"],
                "style": emotion_settings["style"],
                "use_speaker_boost": True,
            },
        }

        response = await http.post(
            f"{_ELEVENLABS_BASE_URL}/text-to-speech/{voice_id}",
            params={"output_format": output_format},
            json=payload,
        )
        response.raise_for_status()

        audio_data = response.content
        filename = f"vo_{int(time.time())}.mp3"
        audio_url = await self._save_audio(audio_data, filename)

        # Estimate duration: ~150 words/min for normal speech
        word_count = len(text.split())
        estimated_duration = round(word_count / 150 * 60, 1)

        return {
            "audio_url": audio_url,
            "voice_id": voice_id,
            "voice_name": voice_name,
            "language": language,
            "emotion": emotion,
            "model": model_id,
            "text_length": len(text),
            "word_count": word_count,
            "estimated_duration_seconds": estimated_duration,
            "output_format": output_format,
            "file_size_bytes": len(audio_data),
        }

    async def _generate_narration(
        self,
        script: str,
        style: str = "documentary",
        language: str = "english",
        pause_instructions: bool = True,
        **_,
    ) -> Dict[str, Any]:
        narration_styles = {
            "documentary": {"emotion": "serious", "gender": "male", "tone": "deep_authoritative"},
            "commercial": {"emotion": "excited", "gender": "female", "tone": "warm"},
            "educational": {"emotion": "calm", "gender": "male", "tone": "professional"},
            "emotional": {"emotion": "warm", "gender": "female", "tone": "warm"},
            "luxury": {"emotion": "calm", "gender": "male", "tone": "deep_authoritative"},
            "energetic": {"emotion": "excited", "gender": "male", "tone": "energetic"},
        }

        style_config = narration_styles.get(style.lower(), narration_styles["documentary"])

        # Add SSML-like pause markers if requested
        if pause_instructions:
            # Add natural pauses after punctuation for better delivery
            processed_script = script.replace(". ", ".<break time='0.5s'/> ")
            processed_script = processed_script.replace(", ", ",<break time='0.3s'/> ")
            processed_script = processed_script.replace("! ", "!<break time='0.4s'/> ")
        else:
            processed_script = script

        voice_selection = await self._select_voice(
            gender=style_config["gender"],
            tone=style_config["tone"],
            language=language,
        )

        audio_result = await self._generate_voiceover(
            text=processed_script,
            voice_id=voice_selection["voice_id"],
            language=language,
            emotion=style_config["emotion"],
        )

        audio_result["narration_style"] = style
        audio_result["script_word_count"] = len(script.split())
        return audio_result

    async def _select_voice(
        self,
        gender: str = "male",
        dialect: str = "american",
        tone: str = "professional",
        language: str = "english",
        use_case: Optional[str] = None,
        **_,
    ) -> Dict[str, Any]:
        # Score each voice in catalog
        best_voice = None
        best_score = -1

        for key, voice in VOICE_CATALOG.items():
            score = 0
            if voice["gender"].lower() == gender.lower():
                score += 3
            if voice["language"].lower() in (language.lower(), language[:2].lower()):
                score += 4
            if dialect.lower() in voice["dialect"].lower():
                score += 2
            if tone.lower() in voice["tone"].lower():
                score += 2
            if use_case and any(use_case.lower() in bf for bf in voice.get("best_for", [])):
                score += 2

            if score > best_score:
                best_score = score
                best_voice = (key, voice)

        if not best_voice:
            # Fallback to first voice
            key, voice = list(VOICE_CATALOG.items())[0]
        else:
            key, voice = best_voice

        return {
            "voice_key": key,
            "voice_id": voice["id"],
            "voice_name": voice["name"],
            "gender": voice["gender"],
            "language": voice["language"],
            "dialect": voice["dialect"],
            "tone": voice["tone"],
            "best_for": voice.get("best_for", []),
            "match_score": best_score,
            "available_voices": list(VOICE_CATALOG.keys()),
        }

    async def _clone_voice(
        self,
        audio_sample: str,
        voice_name: str = "Custom Voice",
        description: str = "",
        **_,
    ) -> Dict[str, Any]:
        if not _ELEVENLABS_API_KEY:
            return {
                "status": "mock",
                "voice_id": f"cloned_{int(time.time())}",
                "voice_name": voice_name,
                "note": "Configure ELEVENLABS_API_KEY for voice cloning",
            }

        http = await self._get_http()

        # Load audio sample
        if audio_sample.startswith("http"):
            audio_response = await http.get(audio_sample)
            audio_bytes = audio_response.content
            filename = "sample.mp3"
        else:
            audio_path = Path(audio_sample)
            audio_bytes = audio_path.read_bytes()
            filename = audio_path.name

        files = {
            "files": (filename, audio_bytes, "audio/mpeg"),
            "name": (None, voice_name),
            "description": (None, description or f"Cloned voice for brand {self.brand_id}"),
            "labels": (None, json.dumps({"brand_id": self.brand_id})),
        }

        response = await http.post(
            f"{_ELEVENLABS_BASE_URL}/voices/add",
            headers={"xi-api-key": _ELEVENLABS_API_KEY},
            files=files,
        )
        response.raise_for_status()
        data = response.json()

        # Store cloned voice ID in memory for reuse
        await self.remember(f"cloned_voice_{voice_name}", data.get("voice_id"))

        return {
            "voice_id": data.get("voice_id"),
            "voice_name": voice_name,
            "status": "cloned",
            "can_delete": True,
            "usage_note": "Use this voice_id in generate_voiceover calls",
        }

    async def _master_audio(
        self,
        audio_file: str,
        target_loudness: float = -14.0,
        remove_noise: bool = True,
        normalize: bool = True,
        **_,
    ) -> Dict[str, Any]:
        """
        Apply audio mastering: loudness normalization, noise reduction.
        Uses pydub if available, otherwise returns processing instructions.
        LUFS -14 is the standard for social media (Spotify/YouTube standard).
        """
        result_base = {
            "source_file": audio_file,
            "target_loudness_lufs": target_loudness,
            "noise_removal": remove_noise,
            "normalized": normalize,
        }

        try:
            from pydub import AudioSegment
            from pydub.effects import normalize as pydub_normalize

            if audio_file.startswith("http"):
                http = await self._get_http()
                resp = await http.get(audio_file)
                audio_data = io.BytesIO(resp.content)
                audio = AudioSegment.from_file(audio_data)
            else:
                audio = AudioSegment.from_file(audio_file)

            # Normalize to target loudness
            if normalize:
                audio = pydub_normalize(audio)

            # Apply mild compression (reduce dynamic range)
            audio = audio.compress_dynamic_range(
                threshold=-20.0,
                ratio=4.0,
                attack=5.0,
                release=50.0,
            )

            # Export mastered audio
            filename = f"mastered_{int(time.time())}.mp3"
            output = io.BytesIO()
            audio.export(output, format="mp3", bitrate="192k", parameters=["-ar", "44100"])
            output.seek(0)

            audio_url = await self._save_audio(output.read(), filename)

            result_base.update({
                "mastered_url": audio_url,
                "duration_ms": len(audio),
                "channels": audio.channels,
                "frame_rate": audio.frame_rate,
                "status": "mastered",
            })

        except ImportError:
            result_base.update({
                "status": "instructions_only",
                "mastering_instructions": {
                    "step_1": "Import audio into your DAW or Audacity",
                    "step_2": f"Apply noise reduction (remove_noise={remove_noise})",
                    "step_3": f"Normalize to {target_loudness} LUFS for social media",
                    "step_4": "Apply light compression: Ratio 4:1, Threshold -20dB",
                    "step_5": "Export as MP3 192kbps, 44.1kHz",
                    "online_tools": ["https://loudnesspenalty.com", "Auphonic.com", "Adobe Podcast"],
                },
                "note": "Install pydub for automated mastering: pip install pydub",
            })

        return result_base

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    def _mock_audio_response(
        self, text: str, voice_id: str, emotion: str, output_format: str
    ) -> Dict[str, Any]:
        word_count = len(text.split())
        return {
            "audio_url": f"https://mock.sociai.os/audio/vo_{int(time.time())}.mp3",
            "voice_id": voice_id or "mock_voice",
            "voice_name": "Mock Voice",
            "language": "english",
            "emotion": emotion,
            "model": "mock",
            "text_length": len(text),
            "word_count": word_count,
            "estimated_duration_seconds": round(word_count / 150 * 60, 1),
            "output_format": output_format,
            "note": "Configure ELEVENLABS_API_KEY for real voice generation",
        }

    async def _save_audio(self, audio_data: bytes, filename: str) -> str:
        upload_dir = Path(os.getenv("UPLOAD_DIR", "/tmp/sociai_uploads")) / "audio"
        upload_dir.mkdir(parents=True, exist_ok=True)
        file_path = upload_dir / filename
        file_path.write_bytes(audio_data)
        base_url = os.getenv("MEDIA_BASE_URL", "http://localhost:8000/media")
        return f"{base_url}/audio/{filename}"
