"""
DesignAgent - AI-powered visual content generation and brand-consistent design.

Integrates with Stability AI, DALL-E 3, and custom template engines to produce
platform-optimized images, carousels, thumbnails, stories, and ad creatives.
"""

import asyncio
import base64
import io
import json
import os
import re
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import httpx

from .base_agent import AgentResult, BaseAgent

_STABILITY_API_KEY = os.getenv("STABILITY_API_KEY", "")
_OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
_STABILITY_BASE_URL = "https://api.stability.ai/v1"
_OPENAI_BASE_URL = "https://api.openai.com/v1"

PLATFORM_DIMENSIONS: Dict[str, Dict[str, Tuple[int, int]]] = {
    "instagram": {
        "feed_square": (1080, 1080),
        "feed_portrait": (1080, 1350),
        "feed_landscape": (1080, 566),
        "story": (1080, 1920),
        "reel": (1080, 1920),
    },
    "tiktok": {
        "video": (1080, 1920),
        "profile": (200, 200),
    },
    "linkedin": {
        "feed": (1200, 627),
        "story": (1080, 1920),
        "banner": (1584, 396),
    },
    "twitter": {
        "feed": (1600, 900),
        "card": (800, 418),
    },
    "facebook": {
        "feed": (1200, 630),
        "story": (1080, 1920),
        "ad": (1200, 628),
    },
    "youtube": {
        "thumbnail": (1280, 720),
        "channel_art": (2560, 1440),
        "shorts": (1080, 1920),
    },
}

STYLE_PRESETS = {
    "luxury": "ultra-luxury aesthetic, dark moody tones, gold accents, cinematic lighting, premium feel",
    "minimal": "minimalist design, white space, clean typography, muted palette, zen aesthetic",
    "viral": "bold colors, high contrast, eye-catching composition, trendy aesthetic, scroll-stopping",
    "corporate": "professional, clean, structured layout, brand colors, trustworthy",
    "gen_z": "Y2K aesthetic, bright neons, grain texture, eclectic typography, chaotic energy",
    "natural": "organic textures, earth tones, authentic feel, lifestyle photography style",
    "tech": "futuristic, dark mode, glowing elements, data visualization aesthetic, sleek",
}


class DesignAgent(BaseAgent):
    def __init__(self, brand_id: str, redis_url: str = "redis://localhost:6379/0"):
        super().__init__("design_agent", brand_id, redis_url)
        self._http: Optional[httpx.AsyncClient] = None

    async def _get_http(self) -> httpx.AsyncClient:
        if self._http is None or self._http.is_closed:
            self._http = httpx.AsyncClient(timeout=120.0)
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
            "generate_post_image": self._generate_post_image,
            "generate_carousel_slides": self._generate_carousel_slides,
            "generate_thumbnail": self._generate_thumbnail,
            "generate_story_design": self._generate_story_design,
            "generate_ad_creative": self._generate_ad_creative,
            "apply_brand_colors": self._apply_brand_colors,
            "resize_for_platform": self._resize_for_platform,
        }
        try:
            if task not in dispatch:
                raise ValueError(f"Unknown task: {task}")
            data = await dispatch[task](**kwargs)
            result = self._make_result(task, data, start)
            await self.cache_result(task, result, ttl=7200, **kwargs)
            self._log_execution(result)
            return result
        except Exception as exc:
            self._log.exception("design_error", extra={"task": task})
            return self._make_result(task, None, start, error=str(exc))

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    async def generate_post_image(
        self,
        prompt: str,
        brand_colors: Optional[List[str]] = None,
        size: str = "1024x1024",
        platform: str = "instagram",
        style: str = "viral",
        negative_prompt: str = "",
    ) -> Dict[str, Any]:
        return await self._generate_post_image(
            prompt=prompt, brand_colors=brand_colors, size=size,
            platform=platform, style=style, negative_prompt=negative_prompt,
        )

    async def generate_carousel_slides(
        self,
        content: List[Dict[str, str]],
        brand_kit: Optional[Dict] = None,
        platform: str = "instagram",
    ) -> List[Dict[str, Any]]:
        return await self._generate_carousel_slides(
            content=content, brand_kit=brand_kit, platform=platform,
        )

    async def generate_thumbnail(
        self,
        title: str,
        style: str = "viral",
        platform: str = "youtube",
        brand_colors: Optional[List[str]] = None,
    ) -> Dict[str, Any]:
        return await self._generate_thumbnail(
            title=title, style=style, platform=platform, brand_colors=brand_colors,
        )

    async def generate_story_design(
        self,
        text: str,
        template: str = "clean",
        brand: Optional[Dict] = None,
        platform: str = "instagram",
    ) -> Dict[str, Any]:
        return await self._generate_story_design(
            text=text, template=template, brand=brand, platform=platform,
        )

    async def generate_ad_creative(
        self,
        copy: str,
        product: str,
        style: str = "luxury",
        platform: str = "instagram",
        brand_colors: Optional[List[str]] = None,
    ) -> Dict[str, Any]:
        return await self._generate_ad_creative(
            copy=copy, product=product, style=style,
            platform=platform, brand_colors=brand_colors,
        )

    async def apply_brand_colors(
        self,
        image_url: str,
        brand_colors: List[str],
        strength: float = 0.5,
    ) -> Dict[str, Any]:
        return await self._apply_brand_colors(
            image_url=image_url, brand_colors=brand_colors, strength=strength,
        )

    async def resize_for_platform(
        self,
        image: str,
        platform: str,
        format_type: str = "feed_square",
    ) -> Dict[str, Any]:
        return await self._resize_for_platform(
            image=image, platform=platform, format_type=format_type,
        )

    # ------------------------------------------------------------------
    # Implementations
    # ------------------------------------------------------------------

    async def _generate_post_image(
        self,
        prompt: str,
        brand_colors: Optional[List[str]] = None,
        size: str = "1024x1024",
        platform: str = "instagram",
        style: str = "viral",
        negative_prompt: str = "",
        **_,
    ) -> Dict[str, Any]:
        style_preset = STYLE_PRESETS.get(style.lower(), "")
        color_note = f"Use these brand colors prominently: {', '.join(brand_colors)}" if brand_colors else ""

        enriched_prompt = f"{prompt}. {style_preset}. {color_note}. Professional social media visual, {platform} optimized, high quality, sharp focus, 4k".strip()
        enriched_negative = f"text, watermark, logo, blurry, low quality, {negative_prompt}".strip(", ")

        # Try Stability AI first, fall back to DALL-E 3
        if _STABILITY_API_KEY:
            return await self._stability_generate(
                prompt=enriched_prompt,
                negative_prompt=enriched_negative,
                size=size,
                platform=platform,
                style=style,
            )
        elif _OPENAI_API_KEY:
            return await self._dalle_generate(
                prompt=enriched_prompt,
                size=size,
                platform=platform,
                style=style,
            )
        else:
            # Return a mock response for development
            return self._mock_image_response(prompt, platform, size, style)

    async def _stability_generate(
        self,
        prompt: str,
        negative_prompt: str,
        size: str,
        platform: str,
        style: str,
    ) -> Dict[str, Any]:
        width, height = self._parse_size(size)
        http = await self._get_http()

        payload = {
            "text_prompts": [
                {"text": prompt, "weight": 1.0},
                {"text": negative_prompt, "weight": -1.0},
            ],
            "cfg_scale": 7,
            "steps": 40,
            "width": width,
            "height": height,
            "samples": 1,
            "style_preset": self._map_stability_style(style),
        }

        response = await http.post(
            f"{_STABILITY_BASE_URL}/generation/stable-diffusion-xl-1024-v1-0/text-to-image",
            headers={
                "Authorization": f"Bearer {_STABILITY_API_KEY}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            json=payload,
        )
        response.raise_for_status()
        data = response.json()

        images = data.get("artifacts", [])
        if not images:
            raise ValueError("No images returned from Stability AI")

        image_data = images[0]["base64"]
        image_url = await self._save_image(image_data, f"{platform}_{int(time.time())}.png")
        cost = await self.track_cost("stability-ai", 40, 0, "generate_image")

        return {
            "image_url": image_url,
            "image_base64": image_data[:100] + "...",
            "provider": "stability-ai",
            "prompt_used": prompt,
            "platform": platform,
            "dimensions": f"{width}x{height}",
            "style": style,
            "cost_usd": cost,
        }

    async def _dalle_generate(
        self,
        prompt: str,
        size: str,
        platform: str,
        style: str,
    ) -> Dict[str, Any]:
        http = await self._get_http()
        dalle_size = self._map_dalle_size(size)

        response = await http.post(
            f"{_OPENAI_BASE_URL}/images/generations",
            headers={
                "Authorization": f"Bearer {_OPENAI_API_KEY}",
                "Content-Type": "application/json",
            },
            json={
                "model": "dall-e-3",
                "prompt": prompt[:4000],
                "n": 1,
                "size": dalle_size,
                "quality": "hd",
                "response_format": "url",
            },
        )
        response.raise_for_status()
        data = response.json()

        image_url = data["data"][0]["url"]
        revised_prompt = data["data"][0].get("revised_prompt", prompt)
        cost = await self.track_cost("dall-e-3", 0, 1, "generate_image")

        return {
            "image_url": image_url,
            "provider": "dall-e-3",
            "prompt_used": prompt,
            "revised_prompt": revised_prompt,
            "platform": platform,
            "dimensions": dalle_size,
            "style": style,
            "cost_usd": cost,
        }

    def _mock_image_response(self, prompt: str, platform: str, size: str, style: str) -> Dict[str, Any]:
        """Development fallback when no API keys are configured."""
        return {
            "image_url": f"https://placeholder.sociai.os/{platform}/{size}/{style}.png",
            "provider": "mock",
            "prompt_used": prompt,
            "platform": platform,
            "dimensions": size,
            "style": style,
            "cost_usd": 0.0,
            "note": "Configure STABILITY_API_KEY or OPENAI_API_KEY for real image generation",
        }

    async def _generate_carousel_slides(
        self,
        content: List[Dict[str, str]],
        brand_kit: Optional[Dict] = None,
        platform: str = "instagram",
        **_,
    ) -> List[Dict[str, Any]]:
        slides = []
        brand_colors = brand_kit.get("colors", []) if brand_kit else []
        style = brand_kit.get("style", "minimal") if brand_kit else "minimal"

        tasks = []
        for i, slide in enumerate(content):
            headline = slide.get("headline", "")
            body = slide.get("body", "")
            visual = slide.get("visual_direction", f"professional slide design for {platform}")
            prompt = f"Carousel slide {i + 1}: {visual}. Text layout: '{headline}'. Clean, {platform} optimized design."

            tasks.append(self._generate_post_image(
                prompt=prompt,
                brand_colors=brand_colors,
                size="1080x1080",
                platform=platform,
                style=style,
            ))

        results = await asyncio.gather(*tasks, return_exceptions=True)
        for i, (slide, result) in enumerate(zip(content, results)):
            if isinstance(result, Exception):
                slides.append({"slide": i + 1, "error": str(result), **slide})
            else:
                slides.append({"slide": i + 1, **slide, **result})

        return slides

    async def _generate_thumbnail(
        self,
        title: str,
        style: str = "viral",
        platform: str = "youtube",
        brand_colors: Optional[List[str]] = None,
        **_,
    ) -> Dict[str, Any]:
        dims = PLATFORM_DIMENSIONS.get(platform, {}).get("thumbnail", (1280, 720))
        size = f"{dims[0]}x{dims[1]}"
        style_desc = STYLE_PRESETS.get(style.lower(), "eye-catching, professional")

        prompt = (
            f"YouTube thumbnail for: '{title}'. {style_desc}. "
            "Large bold text space, dramatic lighting, face with expression if applicable, "
            "high contrast, immediate visual impact, professional content creator quality."
        )

        return await self._generate_post_image(
            prompt=prompt,
            brand_colors=brand_colors,
            size="1280x720",
            platform=platform,
            style=style,
        )

    async def _generate_story_design(
        self,
        text: str,
        template: str = "clean",
        brand: Optional[Dict] = None,
        platform: str = "instagram",
        **_,
    ) -> Dict[str, Any]:
        brand_colors = brand.get("colors", []) if brand else []
        brand_name = brand.get("name", "") if brand else ""

        templates = {
            "clean": "minimal white background, large clean typography",
            "bold": "dark background, oversized bold text, dramatic",
            "gradient": "beautiful gradient background, modern typography",
            "photo_overlay": "full-bleed lifestyle photo with text overlay",
            "branded": f"on-brand design for {brand_name}, consistent visual identity",
        }
        template_desc = templates.get(template, templates["clean"])

        prompt = (
            f"Instagram/TikTok story design, vertical 9:16 format. "
            f"Style: {template_desc}. Content theme: '{text[:200]}'. "
            "Story-format visual, tap-worthy, swipe-up area at bottom."
        )

        result = await self._generate_post_image(
            prompt=prompt,
            brand_colors=brand_colors,
            size="1080x1920",
            platform=platform,
            style="minimal",
        )
        result["template"] = template
        result["text_overlay"] = text
        return result

    async def _generate_ad_creative(
        self,
        copy: str,
        product: str,
        style: str = "luxury",
        platform: str = "instagram",
        brand_colors: Optional[List[str]] = None,
        **_,
    ) -> Dict[str, Any]:
        style_desc = STYLE_PRESETS.get(style.lower(), "professional, high-quality")

        prompt = (
            f"Social media advertisement for: {product}. Ad copy concept: '{copy[:300]}'. "
            f"Style: {style_desc}. Platform: {platform}. "
            "Product showcase, compelling visual hierarchy, clear focal point, "
            "commercial photography quality, immediately communicates the offer."
        )

        result = await self._generate_post_image(
            prompt=prompt,
            brand_colors=brand_colors,
            size="1080x1080",
            platform=platform,
            style=style,
        )
        result["ad_copy"] = copy
        result["product"] = product
        return result

    async def _apply_brand_colors(
        self,
        image_url: str,
        brand_colors: List[str],
        strength: float = 0.5,
        **_,
    ) -> Dict[str, Any]:
        """
        Apply brand color grading to an existing image via img2img.
        Uses Stability AI's image-to-image endpoint when available.
        """
        if not _STABILITY_API_KEY:
            return {
                "original_url": image_url,
                "processed_url": image_url,
                "brand_colors": brand_colors,
                "strength": strength,
                "note": "Configure STABILITY_API_KEY for color processing",
            }

        http = await self._get_http()

        # Download the source image
        img_response = await http.get(image_url)
        img_response.raise_for_status()
        img_data = img_response.content

        color_prompt = f"color graded with brand palette: {', '.join(brand_colors)}, professional photo editing"

        form_data = {
            "init_image": ("image.png", img_data, "image/png"),
        }
        data = {
            "text_prompts[0][text]": color_prompt,
            "text_prompts[0][weight]": "1",
            "cfg_scale": "7",
            "image_strength": str(1 - strength),
            "steps": "30",
            "samples": "1",
        }

        response = await http.post(
            f"{_STABILITY_BASE_URL}/generation/stable-diffusion-xl-1024-v1-0/image-to-image",
            headers={"Authorization": f"Bearer {_STABILITY_API_KEY}"},
            files=form_data,
            data=data,
        )
        response.raise_for_status()
        result_data = response.json()

        images = result_data.get("artifacts", [])
        if not images:
            return {"original_url": image_url, "error": "No output from Stability AI"}

        processed_b64 = images[0]["base64"]
        processed_url = await self._save_image(processed_b64, f"branded_{int(time.time())}.png")

        return {
            "original_url": image_url,
            "processed_url": processed_url,
            "brand_colors": brand_colors,
            "strength": strength,
            "provider": "stability-ai",
        }

    async def _resize_for_platform(
        self,
        image: str,
        platform: str,
        format_type: str = "feed_square",
        **_,
    ) -> Dict[str, Any]:
        """Return resize instructions and dimensions for platform-specific formats."""
        platform_formats = PLATFORM_DIMENSIONS.get(platform.lower(), {})

        if not platform_formats:
            raise ValueError(f"Unknown platform: {platform}. Supported: {list(PLATFORM_DIMENSIONS.keys())}")

        if format_type not in platform_formats:
            format_type = list(platform_formats.keys())[0]

        dimensions = platform_formats[format_type]
        all_formats = {fmt: list(dims) for fmt, dims in platform_formats.items()}

        result = {
            "source_image": image,
            "platform": platform,
            "format": format_type,
            "target_width": dimensions[0],
            "target_height": dimensions[1],
            "aspect_ratio": f"{dimensions[0]}:{dimensions[1]}",
            "all_platform_formats": all_formats,
            "resize_instructions": {
                "method": "smart_crop",
                "focal_point": "center",
                "upscale_if_needed": True,
                "maintain_quality": "max",
                "output_format": "JPEG",
                "quality": 95,
            },
        }

        # Attempt actual resize using Pillow if available
        try:
            from PIL import Image as PILImage
            if image.startswith("data:"):
                img_data = base64.b64decode(image.split(",")[1])
            elif image.startswith("http"):
                http = await self._get_http()
                resp = await http.get(image)
                img_data = resp.content
            else:
                img_data = Path(image).read_bytes()

            img = PILImage.open(io.BytesIO(img_data))
            img_resized = img.resize(dimensions, PILImage.LANCZOS)

            buffer = io.BytesIO()
            img_resized.save(buffer, format="JPEG", quality=95)
            resized_b64 = base64.b64encode(buffer.getvalue()).decode()
            saved_url = await self._save_image(resized_b64, f"{platform}_{format_type}_{int(time.time())}.jpg")

            result["resized_image_url"] = saved_url
            result["processed"] = True
        except ImportError:
            result["note"] = "Install Pillow for automatic resizing: pip install Pillow"
            result["processed"] = False
        except Exception as e:
            result["resize_error"] = str(e)
            result["processed"] = False

        return result

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _parse_size(size: str) -> Tuple[int, int]:
        parts = size.lower().replace("x", " ").split()
        if len(parts) == 2:
            return int(parts[0]), int(parts[1])
        return 1024, 1024

    @staticmethod
    def _map_dalle_size(size: str) -> str:
        w, h = DesignAgent._parse_size(size)
        if w == h:
            return "1024x1024"
        if w > h:
            return "1792x1024"
        return "1024x1792"

    @staticmethod
    def _map_stability_style(style: str) -> str:
        mapping = {
            "luxury": "cinematic",
            "viral": "digital-art",
            "minimal": "minimize",
            "corporate": "photographic",
            "gen_z": "neon-punk",
            "natural": "photographic",
            "tech": "sci-fi",
        }
        return mapping.get(style.lower(), "photographic")

    async def _save_image(self, base64_data: str, filename: str) -> str:
        """Save base64 image to disk and return a local URL path."""
        upload_dir = Path(os.getenv("UPLOAD_DIR", "/tmp/sociai_uploads"))
        upload_dir.mkdir(parents=True, exist_ok=True)

        img_bytes = base64.b64decode(base64_data)
        file_path = upload_dir / filename
        file_path.write_bytes(img_bytes)

        base_url = os.getenv("MEDIA_BASE_URL", "http://localhost:8000/media")
        return f"{base_url}/{filename}"
