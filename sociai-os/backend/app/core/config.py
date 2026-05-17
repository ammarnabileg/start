from __future__ import annotations

import secrets
from functools import lru_cache
from typing import Any, List, Optional

from pydantic import AnyHttpUrl, EmailStr, Field, PostgresDsn, RedisDsn, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=True,
        extra="ignore",
    )

    # ─── Application ──────────────────────────────────────────────────────────
    APP_NAME: str = "SociAI OS"
    APP_VERSION: str = "1.0.0"
    ENVIRONMENT: str = Field(default="development", pattern="^(development|staging|production)$")
    DEBUG: bool = False
    FRONTEND_URL: str = "http://localhost:3000"
    ALLOWED_ORIGINS: List[str] = ["http://localhost:3000", "http://localhost:5173"]
    API_V1_PREFIX: str = "/api/v1"

    @field_validator("ALLOWED_ORIGINS", mode="before")
    @classmethod
    def parse_allowed_origins(cls, v: Any) -> List[str]:
        if isinstance(v, str):
            return [origin.strip() for origin in v.split(",")]
        return v

    # ─── Database ─────────────────────────────────────────────────────────────
    DATABASE_URL: str = Field(
        default="postgresql+asyncpg://postgres:password@localhost:5432/sociai_os"
    )
    DATABASE_POOL_SIZE: int = 20
    DATABASE_MAX_OVERFLOW: int = 40
    DATABASE_POOL_TIMEOUT: int = 30
    DATABASE_POOL_RECYCLE: int = 3600
    DATABASE_ECHO: bool = False

    # Sync URL for Alembic migrations
    @property
    def SYNC_DATABASE_URL(self) -> str:
        return self.DATABASE_URL.replace("+asyncpg", "").replace(
            "postgresql+asyncpg", "postgresql"
        )

    # ─── Redis ────────────────────────────────────────────────────────────────
    REDIS_URL: str = Field(default="redis://localhost:6379/0")
    REDIS_MAX_CONNECTIONS: int = 50
    REDIS_SOCKET_TIMEOUT: float = 5.0
    REDIS_SOCKET_CONNECT_TIMEOUT: float = 5.0
    REDIS_RETRY_ON_TIMEOUT: bool = True
    REDIS_DECODE_RESPONSES: bool = True

    # ─── Security & JWT ───────────────────────────────────────────────────────
    SECRET_KEY: str = Field(default_factory=lambda: secrets.token_urlsafe(64))
    ENCRYPTION_KEY: str = Field(default_factory=lambda: secrets.token_urlsafe(32))
    JWT_ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    REFRESH_TOKEN_EXPIRE_DAYS: int = 30
    PASSWORD_RESET_TOKEN_EXPIRE_HOURS: int = 1
    EMAIL_VERIFICATION_TOKEN_EXPIRE_HOURS: int = 24
    BCRYPT_ROUNDS: int = 12

    # ─── Two-Factor Authentication ────────────────────────────────────────────
    TOTP_ISSUER_NAME: str = "SociAI OS"
    TOTP_DIGITS: int = 6
    TOTP_INTERVAL: int = 30

    # ─── AI / LLM ─────────────────────────────────────────────────────────────
    OPENAI_API_KEY: str = ""
    OPENAI_ORG_ID: Optional[str] = None
    OPENAI_DEFAULT_MODEL: str = "gpt-4o"
    OPENAI_FAST_MODEL: str = "gpt-4o-mini"
    OPENAI_MAX_TOKENS: int = 4096
    OPENAI_TEMPERATURE: float = 0.7

    ANTHROPIC_API_KEY: str = ""
    ANTHROPIC_DEFAULT_MODEL: str = "claude-sonnet-4-6"
    ANTHROPIC_MAX_TOKENS: int = 4096

    # ─── Image & Voice Generation ─────────────────────────────────────────────
    STABILITY_API_KEY: str = ""
    STABILITY_API_HOST: str = "https://api.stability.ai"
    STABILITY_DEFAULT_ENGINE: str = "stable-diffusion-xl-1024-v1-0"

    ELEVENLABS_API_KEY: str = ""
    ELEVENLABS_API_HOST: str = "https://api.elevenlabs.io"
    ELEVENLABS_DEFAULT_VOICE_ID: str = "21m00Tcm4TlvDq8ikWAM"  # Rachel

    # ─── LinkedIn ─────────────────────────────────────────────────────────────
    LINKEDIN_CLIENT_ID: str = ""
    LINKEDIN_CLIENT_SECRET: str = ""
    LINKEDIN_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/linkedin/callback"
    LINKEDIN_SCOPE: str = "r_liteprofile r_emailaddress w_member_social"

    # ─── Meta (Facebook / Instagram) ──────────────────────────────────────────
    META_APP_ID: str = ""
    META_APP_SECRET: str = ""
    META_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/meta/callback"
    META_API_VERSION: str = "v19.0"
    META_SCOPE: str = (
        "pages_show_list,pages_read_engagement,pages_manage_posts,"
        "instagram_basic,instagram_content_publish,instagram_manage_insights"
    )

    # ─── TikTok ───────────────────────────────────────────────────────────────
    TIKTOK_CLIENT_KEY: str = ""
    TIKTOK_CLIENT_SECRET: str = ""
    TIKTOK_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/tiktok/callback"
    TIKTOK_SCOPE: str = "user.info.basic,video.list,video.upload"

    # ─── Twitter / X ──────────────────────────────────────────────────────────
    TWITTER_API_KEY: str = ""
    TWITTER_API_SECRET: str = ""
    TWITTER_ACCESS_TOKEN: str = ""
    TWITTER_ACCESS_TOKEN_SECRET: str = ""
    TWITTER_BEARER_TOKEN: str = ""
    TWITTER_CLIENT_ID: str = ""
    TWITTER_CLIENT_SECRET: str = ""
    TWITTER_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/twitter/callback"

    # ─── YouTube / Google ─────────────────────────────────────────────────────
    YOUTUBE_CLIENT_ID: str = ""
    YOUTUBE_CLIENT_SECRET: str = ""
    YOUTUBE_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/youtube/callback"
    YOUTUBE_SCOPE: str = (
        "https://www.googleapis.com/auth/youtube.upload "
        "https://www.googleapis.com/auth/youtube.readonly "
        "https://www.googleapis.com/auth/youtube.force-ssl"
    )

    # ─── Snapchat ─────────────────────────────────────────────────────────────
    SNAPCHAT_CLIENT_ID: str = ""
    SNAPCHAT_CLIENT_SECRET: str = ""
    SNAPCHAT_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/snapchat/callback"
    SNAPCHAT_SCOPE: str = "snapchat-marketing-api"

    # ─── Pinterest ────────────────────────────────────────────────────────────
    PINTEREST_APP_ID: str = ""
    PINTEREST_APP_SECRET: str = ""
    PINTEREST_REDIRECT_URI: str = "http://localhost:8000/api/v1/oauth/pinterest/callback"
    PINTEREST_SCOPE: str = "boards:read,boards:write,pins:read,pins:write,user_accounts:read"

    # ─── WhatsApp (Meta Business) ─────────────────────────────────────────────
    WHATSAPP_TOKEN: str = ""
    WHATSAPP_PHONE_NUMBER_ID: str = ""
    WHATSAPP_BUSINESS_ACCOUNT_ID: str = ""
    WHATSAPP_VERIFY_TOKEN: str = Field(default_factory=lambda: secrets.token_urlsafe(16))
    WHATSAPP_API_VERSION: str = "v19.0"

    # ─── Telegram ─────────────────────────────────────────────────────────────
    TELEGRAM_BOT_TOKEN: str = ""
    TELEGRAM_WEBHOOK_SECRET: str = Field(default_factory=lambda: secrets.token_urlsafe(16))
    TELEGRAM_WEBHOOK_URL: str = ""

    # ─── Celery / Task Queue ──────────────────────────────────────────────────
    CELERY_BROKER_URL: str = Field(default="redis://localhost:6379/1")
    CELERY_RESULT_BACKEND: str = Field(default="redis://localhost:6379/2")
    CELERY_TASK_SERIALIZER: str = "json"
    CELERY_RESULT_SERIALIZER: str = "json"
    CELERY_ACCEPT_CONTENT: List[str] = ["json"]
    CELERY_TIMEZONE: str = "UTC"
    CELERY_TASK_TRACK_STARTED: bool = True
    CELERY_TASK_TIME_LIMIT: int = 3600  # 1 hour
    CELERY_TASK_SOFT_TIME_LIMIT: int = 3300

    # ─── Storage (S3 / compatible) ────────────────────────────────────────────
    AWS_ACCESS_KEY_ID: str = ""
    AWS_SECRET_ACCESS_KEY: str = ""
    AWS_REGION: str = "us-east-1"
    AWS_S3_BUCKET: str = "sociai-os-media"
    AWS_S3_ENDPOINT_URL: Optional[str] = None  # for MinIO / local dev
    MEDIA_CDN_URL: str = ""

    # ─── Email ────────────────────────────────────────────────────────────────
    SMTP_HOST: str = "smtp.sendgrid.net"
    SMTP_PORT: int = 587
    SMTP_USER: str = ""
    SMTP_PASSWORD: str = ""
    SMTP_TLS: bool = True
    EMAIL_FROM: str = "noreply@sociai-os.com"
    EMAIL_FROM_NAME: str = "SociAI OS"

    # ─── Sentry ───────────────────────────────────────────────────────────────
    SENTRY_DSN: Optional[str] = None
    SENTRY_TRACES_SAMPLE_RATE: float = 0.1

    # ─── Rate Limiting ────────────────────────────────────────────────────────
    RATE_LIMIT_DEFAULT: int = 100        # requests per window
    RATE_LIMIT_WINDOW_SECONDS: int = 60
    RATE_LIMIT_AUTH: int = 10            # stricter for auth endpoints

    # ─── WebSocket ────────────────────────────────────────────────────────────
    WS_HEARTBEAT_INTERVAL: int = 30
    WS_MAX_CONNECTIONS_PER_USER: int = 5

    # ─── Feature Flags ────────────────────────────────────────────────────────
    ENABLE_AI_AGENTS: bool = True
    ENABLE_VIRAL_SCORING: bool = True
    ENABLE_SENTIMENT_ANALYSIS: bool = True
    ENABLE_AUTO_SCHEDULING: bool = True
    ENABLE_COMPETITOR_TRACKING: bool = True


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()


settings: Settings = get_settings()
