"""
Proprietary viral prediction engine combining content signals, engagement metrics,
trend alignment, hook analysis, timing optimization, and platform-specific weighting.

The ViralPredictor calculates a 0-10 score across multiple dimensions and returns
a classification, probability estimate, and actionable improvement tips.
"""
from __future__ import annotations

import math
import re
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple


# ---------------------------------------------------------------------------
# Platform-specific dimension weights
# ---------------------------------------------------------------------------

PLATFORM_WEIGHTS: Dict[str, Dict[str, float]] = {
    "tiktok": {
        "hook_strength":    0.30,
        "trend_alignment":  0.25,
        "visual_appeal":    0.15,
        "cta_clarity":      0.10,
        "timing":           0.10,
        "caption_quality":  0.05,
        "hashtag_strategy": 0.05,
    },
    "instagram": {
        "hook_strength":    0.20,
        "trend_alignment":  0.15,
        "visual_appeal":    0.25,
        "cta_clarity":      0.15,
        "timing":           0.10,
        "caption_quality":  0.10,
        "hashtag_strategy": 0.05,
    },
    "linkedin": {
        "hook_strength":    0.30,
        "trend_alignment":  0.10,
        "visual_appeal":    0.10,
        "cta_clarity":      0.25,
        "timing":           0.10,
        "caption_quality":  0.15,
        "hashtag_strategy": 0.00,
    },
    "twitter": {
        "hook_strength":    0.35,
        "trend_alignment":  0.25,
        "visual_appeal":    0.08,
        "cta_clarity":      0.18,
        "timing":           0.08,
        "caption_quality":  0.06,
        "hashtag_strategy": 0.00,
    },
    "youtube": {
        "hook_strength":    0.25,
        "trend_alignment":  0.15,
        "visual_appeal":    0.25,
        "cta_clarity":      0.15,
        "timing":           0.10,
        "caption_quality":  0.10,
        "hashtag_strategy": 0.00,
    },
    "facebook": {
        "hook_strength":    0.20,
        "trend_alignment":  0.10,
        "visual_appeal":    0.20,
        "cta_clarity":      0.20,
        "timing":           0.15,
        "caption_quality":  0.15,
        "hashtag_strategy": 0.00,
    },
}

# Power words that significantly boost hook strength
POWER_WORDS = {
    "curiosity":   ["secret", "nobody", "discover", "finally", "revealed", "truth", "shocking", "surprising"],
    "urgency":     ["now", "today", "immediately", "urgent", "limited", "deadline", "last chance", "expiring"],
    "authority":   ["proven", "research", "expert", "science", "data", "study", "fact", "official"],
    "fear":        ["warning", "danger", "mistake", "wrong", "avoid", "stop", "never", "caution"],
    "desire":      ["free", "instant", "easy", "amazing", "powerful", "best", "ultimate", "breakthrough"],
    "social":      ["everyone", "viral", "trending", "famous", "millions", "popular", "biggest"],
}

# High-value action verbs for CTA scoring
CTA_ACTION_VERBS = [
    "save", "share", "follow", "subscribe", "click", "download", "get", "join",
    "comment", "tag", "dm", "visit", "book", "buy", "learn", "discover",
    "watch", "read", "try", "start", "sign up", "register", "grab",
]

# Best posting hours by platform (UTC, 0-23)
PEAK_HOURS: Dict[str, List[int]] = {
    "tiktok":    [6, 10, 14, 19, 20, 21],
    "instagram": [8, 11, 14, 17, 20],
    "linkedin":  [7, 8, 10, 12, 17, 18],
    "twitter":   [8, 10, 12, 15, 17, 18],
    "youtube":   [14, 15, 16, 20, 21],
    "facebook":  [9, 13, 15, 19, 20],
}

# Best days of week (0=Monday ... 6=Sunday)
PEAK_DAYS: Dict[str, List[int]] = {
    "tiktok":    [1, 2, 3, 4, 5, 6],      # Tue-Sun
    "instagram": [1, 2, 3, 4],              # Tue-Fri
    "linkedin":  [1, 2, 3, 4],              # Tue-Fri
    "twitter":   [1, 2, 3, 4],              # Tue-Fri
    "youtube":   [4, 5, 6],                 # Fri-Sun
    "facebook":  [2, 3, 4, 5, 6],           # Wed-Sun
}


class ViralPredictor:
    """
    Multi-signal viral score calculator (0–10 scale).

    Scoring dimensions:
        1. hook_strength    – First-line / first-3-second power analysis
        2. trend_alignment  – How well content aligns with current trends
        3. visual_appeal    – Media type, quality signals, and format
        4. cta_clarity      – Presence and strength of call-to-action
        5. timing           – Publishing time relative to platform peak hours
        6. caption_quality  – Length, structure, readability, line breaks
        7. hashtag_strategy – Quantity, mix of niche/broad, relevance

    Each dimension is weighted per platform (PLATFORM_WEIGHTS).
    Final score → probability_viral via sigmoid function.
    """

    def predict(
        self,
        content: Dict[str, Any],
        platform: str = "instagram",
        metrics: Optional[Dict[str, Any]] = None,
        posted_at: Optional[datetime] = None,
    ) -> Dict[str, Any]:
        """
        Full viral prediction.

        Args:
            content: Dict with keys like hook, caption, cta, hashtags,
                     has_video, has_carousel, media_urls, etc.
            platform: Target platform name.
            metrics: Optional real engagement metrics (likes, shares, etc.)
                     If provided, scores are blended with actual performance.
            posted_at: When the post was (or will be) published.

        Returns:
            Comprehensive prediction dict with scores, classification,
            probability, and ranked improvement suggestions.
        """
        weights = PLATFORM_WEIGHTS.get(platform.lower(), PLATFORM_WEIGHTS["instagram"])

        # --- Compute raw dimension scores ---
        scores: Dict[str, float] = {
            "hook_strength":    self._score_hook(content.get("hook", "") or content.get("caption", "")[:150]),
            "trend_alignment":  float(content.get("trend_alignment_score", 5.0)),
            "visual_appeal":    self._score_visual(content),
            "cta_clarity":      self._score_cta(content.get("cta", "") or content.get("caption", "")),
            "timing":           self._score_timing(platform, posted_at),
            "caption_quality":  self._score_caption(content.get("caption", "")),
            "hashtag_strategy": self._score_hashtags(content.get("hashtags", []), platform),
        }

        # --- Blend with real metrics if available ---
        if metrics and any(v > 0 for v in metrics.values()):
            scores = self._blend_with_metrics(scores, metrics)

        # --- Weighted aggregate ---
        overall = sum(scores.get(dim, 5.0) * w for dim, w in weights.items())
        overall = max(0.0, min(10.0, overall))

        probability_viral = self._score_to_probability(overall)
        classification = self._classify(overall)
        tips = self._generate_tips(scores, platform)

        # --- Predicted engagement ranges ---
        engagement_ranges = self._predict_engagement_ranges(overall, platform)

        return {
            "overall_score":        round(overall, 2),
            "probability_viral":    round(probability_viral, 3),
            "classification":       classification,
            "dimension_scores":     {k: round(v, 2) for k, v in scores.items()},
            "dimension_weights":    weights,
            "platform":             platform,
            "improvement_tips":     tips,
            "predicted_engagement": engagement_ranges,
            "top_strength":         max(scores, key=lambda k: scores[k] * weights.get(k, 0.1)),
            "top_weakness":         min(scores, key=lambda k: scores[k] * weights.get(k, 0.1)),
            "posting_time_optimal": self._score_timing(platform, posted_at) >= 7.0,
        }

    # ------------------------------------------------------------------
    # Dimension scorers
    # ------------------------------------------------------------------

    def _score_hook(self, hook: str) -> float:
        if not hook:
            return 2.5
        score = 4.0

        lower = hook.lower()
        # Power word detection
        for category, words in POWER_WORDS.items():
            hits = sum(1 for w in words if w in lower)
            if hits > 0:
                # Different categories have different weights
                category_bonus = {"curiosity": 1.5, "urgency": 1.2, "fear": 1.3, "desire": 1.0, "authority": 0.8, "social": 0.7}
                score += min(hits, 2) * category_bonus.get(category, 1.0)

        # Hook structure signals
        stripped = hook.strip()
        if stripped and stripped[-1] == "?":
            score += 1.5  # Question hook
        if re.search(r"\b\d+\b", stripped[:30]):
            score += 1.0  # Number in first 30 chars
        if stripped.startswith(("Stop", "Wait", "Listen", "Warning", "PSA")):
            score += 1.2  # Pattern interrupt opener

        # Length optimization
        if 40 <= len(hook) <= 120:
            score += 0.5   # Ideal hook length
        elif len(hook) > 200:
            score -= 0.5   # Too long

        # Ellipsis / cliffhanger
        if stripped.endswith(("...", "…")):
            score += 0.8

        return min(score, 10.0)

    def _score_visual(self, content: Dict[str, Any]) -> float:
        score = 4.0

        # Media presence
        if content.get("media_urls") or content.get("image_url"):
            score += 2.0
        if content.get("has_video") or content.get("video_url"):
            score += 2.5
        if content.get("has_carousel") or content.get("carousel_slides"):
            score += 1.5

        # Quality signals
        if content.get("brand_colors_applied"):
            score += 0.5
        if content.get("professional_design"):
            score += 0.5
        if content.get("thumbnail_url"):
            score += 0.5

        # Format signals
        if content.get("aspect_ratio") in ("9:16", "1:1"):
            score += 0.5  # Optimal ratios

        return min(score, 10.0)

    def _score_cta(self, cta: str) -> float:
        if not cta:
            return 2.0
        score = 3.5
        lower = cta.lower()

        verb_hits = sum(1 for v in CTA_ACTION_VERBS if v in lower)
        score += min(verb_hits, 4) * 1.2

        # Specificity bonus
        if any(w in lower for w in ["now", "today", "here", "below", "link", "bio"]):
            score += 0.8
        # Benefit-driven CTA
        if any(w in lower for w in ["free", "discount", "%", "off", "exclusive", "limited"]):
            score += 0.8
        # Length: short CTAs perform better
        if 10 <= len(cta) <= 80:
            score += 0.5

        return min(score, 10.0)

    def _score_timing(self, platform: str, posted_at: Optional[datetime] = None) -> float:
        if posted_at is None:
            return 6.0  # Neutral default when time unknown

        hour = posted_at.hour   # UTC hour
        day = posted_at.weekday()  # 0=Monday

        peak_hours = PEAK_HOURS.get(platform.lower(), [9, 12, 18])
        peak_days = PEAK_DAYS.get(platform.lower(), [1, 2, 3, 4])

        score = 4.0

        # Hour scoring
        if hour in peak_hours:
            score += 3.0
        elif any(abs(hour - ph) <= 1 for ph in peak_hours):
            score += 1.5
        elif 1 <= hour <= 5:
            score -= 2.0  # Dead zone

        # Day scoring
        if day in peak_days:
            score += 1.5
        elif day == 6:  # Sunday - low for most platforms
            score -= 0.5

        return min(max(score, 0.0), 10.0)

    def _score_caption(self, caption: str) -> float:
        if not caption:
            return 2.0
        score = 5.0

        word_count = len(caption.split())
        line_breaks = caption.count("\n")
        emoji_count = len(re.findall(r"[\U00010000-\U0010ffff]|[☀-⟿]", caption))

        # Length scoring
        if 50 <= word_count <= 150:
            score += 1.5
        elif word_count < 10:
            score -= 1.5
        elif word_count > 300:
            score -= 0.5

        # Structure
        if line_breaks >= 2:
            score += 1.0   # Good readability
        if 1 <= emoji_count <= 5:
            score += 0.5   # Light emoji use

        # Engagement triggers in body
        lower = caption.lower()
        if "?" in caption:
            score += 0.5   # Question encourages comments
        if any(w in lower for w in ["comment", "tag", "share", "save", "follow"]):
            score += 0.5

        return min(score, 10.0)

    def _score_hashtags(self, hashtags: List[str], platform: str) -> float:
        if not hashtags:
            if platform.lower() == "linkedin":
                return 5.0  # Hashtags less critical on LinkedIn
            return 2.5

        count = len(hashtags)
        score = 4.0

        optimal_counts = {
            "instagram": (8, 20),
            "tiktok": (3, 8),
            "twitter": (1, 3),
            "linkedin": (3, 7),
            "facebook": (2, 5),
            "youtube": (5, 15),
        }
        low, high = optimal_counts.get(platform.lower(), (5, 15))

        if low <= count <= high:
            score += 2.5
        elif count < low:
            score += 1.0
        else:
            score -= 0.5  # Too many hashtags hurt reach

        # Hashtag diversity (mix of sizes) heuristic via length
        lengths = [len(h.lstrip("#")) for h in hashtags]
        has_short = any(l <= 8 for l in lengths)     # broad/mega
        has_medium = any(8 < l <= 20 for l in lengths)  # medium
        has_long = any(l > 20 for l in lengths)      # niche
        diversity = sum([has_short, has_medium, has_long])
        score += diversity * 0.8

        return min(score, 10.0)

    # ------------------------------------------------------------------
    # Blending with real metrics
    # ------------------------------------------------------------------

    def _blend_with_metrics(
        self,
        predicted_scores: Dict[str, float],
        metrics: Dict[str, Any],
    ) -> Dict[str, float]:
        """Blend predicted scores with early real-world engagement data."""
        impressions = max(metrics.get("impressions", 1), 1)
        likes = metrics.get("likes", 0)
        comments = metrics.get("comments", 0)
        shares = metrics.get("shares", 0)
        saves = metrics.get("saves", 0)

        real_engagement = (likes + comments * 2 + shares * 3 + saves * 2) / impressions * 100
        real_share_signal = min(shares / max(impressions / 1000, 1) * 2, 10)

        # Blend: 70% predicted + 30% real for early data
        blended = dict(predicted_scores)
        if real_engagement > 0:
            engagement_score = min(real_engagement * 1.5, 10.0)
            blended["hook_strength"] = blended["hook_strength"] * 0.7 + engagement_score * 0.3
        if real_share_signal > 0:
            blended["trend_alignment"] = blended["trend_alignment"] * 0.7 + real_share_signal * 0.3

        return blended

    # ------------------------------------------------------------------
    # Utility methods
    # ------------------------------------------------------------------

    @staticmethod
    def _score_to_probability(score: float) -> float:
        """Sigmoid mapping of 0-10 score to 0-1 viral probability."""
        return 1 / (1 + math.exp(-0.7 * (score - 6.5)))

    @staticmethod
    def _classify(score: float) -> str:
        if score >= 8.5:
            return "viral_potential"
        if score >= 7.0:
            return "high_performer"
        if score >= 5.5:
            return "above_average"
        if score >= 4.0:
            return "average"
        return "needs_improvement"

    @staticmethod
    def _predict_engagement_ranges(overall: float, platform: str) -> Dict[str, str]:
        """Map overall score to rough engagement rate ranges by platform."""
        base_rates = {
            "instagram": (1, 3, 6, 12),    # (poor, avg, good, viral) engagement %
            "tiktok":    (2, 8, 20, 50),
            "linkedin":  (0.5, 2, 5, 10),
            "twitter":   (0.1, 0.5, 2, 5),
            "youtube":   (1, 4, 8, 20),
            "facebook":  (0.5, 2, 4, 8),
        }
        rates = base_rates.get(platform.lower(), (1, 3, 6, 12))

        if overall >= 8.5:
            low, high = rates[2], rates[3]
        elif overall >= 7.0:
            low, high = rates[1], rates[2]
        elif overall >= 5.0:
            low, high = rates[0], rates[1]
        else:
            low, high = 0, rates[0]

        multiplier = 1 + (overall - 5) * 0.1
        estimated_reach = f"{int(1000 * multiplier)}–{int(10000 * multiplier)}"

        return {
            "engagement_rate": f"{low}%–{high}%",
            "estimated_reach": estimated_reach,
            "estimated_saves": f"{int(low * 3)}–{int(high * 5)} per 1K views",
        }

    def _generate_tips(
        self, scores: Dict[str, float], platform: str
    ) -> List[str]:
        """Generate ranked, actionable improvement tips for the lowest-scoring dimensions."""
        weights = PLATFORM_WEIGHTS.get(platform.lower(), PLATFORM_WEIGHTS["instagram"])

        # Score weighted impact: low-score × high-weight = biggest improvement opportunity
        impacts: List[Tuple[float, str, str]] = []

        tip_map = {
            "hook_strength": [
                "Start with a number: '3 things...' or '90% of people don't know...'",
                "Use a question hook that creates immediate curiosity",
                "Add a power word in the first 5 words: 'Warning', 'Secret', 'Finally'",
            ],
            "trend_alignment": [
                "Search trending sounds/hashtags on the platform and align your content",
                "React to a trending topic relevant to your niche within 24 hours",
                "Use the most viral audio track in your next reel",
            ],
            "visual_appeal": [
                "Add a high-quality image or video – text-only posts underperform by 40%",
                "Create a 5-10 slide carousel for higher save/share rates",
                "Apply brand colors consistently to build recognition",
            ],
            "cta_clarity": [
                "End every post with ONE specific CTA: 'Save this for later' or 'Tag someone who needs this'",
                "Use urgency language: 'Comment NOW' performs better than 'Comment below'",
                "Ask a direct question to drive comments: 'What's your biggest challenge with X?'",
            ],
            "timing": [
                f"Post during peak hours for {platform}: {', '.join(str(h) + ':00' for h in PEAK_HOURS.get(platform, [9, 18])[:3])} UTC",
                "Test posting on different days this week and compare engagement",
                "Use your platform's native insights to find when your specific audience is online",
            ],
            "caption_quality": [
                "Break your caption into 2-3 line chunks for mobile readability",
                "Add 1-3 relevant emojis to increase visual scanning",
                "Include a personal story or specific example to build connection",
            ],
            "hashtag_strategy": [
                "Mix hashtag sizes: 30% large (1M+), 40% medium (100K-1M), 30% niche (<100K)",
                f"Use {PLATFORM_WEIGHTS.get(platform, {})}-optimized hashtag count for {platform}",
                "Research competitor hashtags and adopt the top performers",
            ],
        }

        for dim, weight in weights.items():
            dim_score = scores.get(dim, 5.0)
            if dim_score < 7.0 and weight > 0:
                impact = (10 - dim_score) * weight
                tips = tip_map.get(dim, ["Improve this dimension"])
                # Pick the best tip based on how low the score is
                tip_idx = 0 if dim_score < 4 else (1 if dim_score < 6 else 2)
                impacts.append((impact, dim, tips[min(tip_idx, len(tips) - 1)]))

        impacts.sort(key=lambda x: x[0], reverse=True)
        return [tip for _, _, tip in impacts[:3]]

    async def predict_async(
        self,
        content: Dict[str, Any],
        platform: str = "instagram",
        metrics: Optional[Dict[str, Any]] = None,
        posted_at: Optional[datetime] = None,
    ) -> Dict[str, Any]:
        """Async-compatible wrapper for use in async contexts."""
        return self.predict(content=content, platform=platform, metrics=metrics, posted_at=posted_at)


# Module-level singleton
viral_predictor = ViralPredictor()
