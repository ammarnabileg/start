"""
app/main.py
───────────
FastAPI application factory for SociAI OS.

Includes:
- All routers via the api_router aggregator
- CORS middleware
- Trusted host / request-size middleware
- Structured logging middleware
- Prometheus metrics
- Sentry integration
- Startup / shutdown lifecycle events (DB + Redis health checks)
- WebSocket connection manager
- Global exception handlers
"""
from __future__ import annotations

import time
import uuid
from contextlib import asynccontextmanager
from typing import AsyncGenerator

import structlog
from fastapi import FastAPI, Request, Response, status
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.gzip import GZipMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse, ORJSONResponse
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.middleware.sessions import SessionMiddleware

from app.core.config import settings
from app.core.database import check_connection as db_check, close_engine
from app.core.redis_client import check_connection as redis_check, close_redis

logger = structlog.get_logger(__name__)


# ─── Lifespan ────────────────────────────────────────────────────────────────

@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncGenerator[None, None]:
    """Application lifespan: startup and shutdown hooks."""
    # ── Startup ──────────────────────────────────────────────────────────────
    logger.info(
        "app.startup",
        name=settings.APP_NAME,
        version=settings.APP_VERSION,
        environment=settings.ENVIRONMENT,
    )

    # Sentry
    if settings.SENTRY_DSN:
        import sentry_sdk
        from sentry_sdk.integrations.fastapi import FastApiIntegration
        from sentry_sdk.integrations.sqlalchemy import SqlalchemyIntegration

        sentry_sdk.init(
            dsn=settings.SENTRY_DSN,
            environment=settings.ENVIRONMENT,
            traces_sample_rate=settings.SENTRY_TRACES_SAMPLE_RATE,
            integrations=[
                FastApiIntegration(transaction_style="endpoint"),
                SqlalchemyIntegration(),
            ],
        )
        logger.info("app.sentry_initialized")

    # Database connectivity check
    if not await db_check():
        logger.critical("app.startup_aborted", reason="database unreachable")
        raise RuntimeError("Cannot connect to the database on startup.")
    logger.info("app.database_ok")

    # Redis connectivity check
    if not await redis_check():
        logger.critical("app.startup_aborted", reason="redis unreachable")
        raise RuntimeError("Cannot connect to Redis on startup.")
    logger.info("app.redis_ok")

    logger.info("app.ready")
    yield

    # ── Shutdown ─────────────────────────────────────────────────────────────
    logger.info("app.shutdown_started")
    await close_engine()
    await close_redis()
    logger.info("app.shutdown_complete")


# ─── App Factory ─────────────────────────────────────────────────────────────

def create_application() -> FastAPI:
    app = FastAPI(
        title=settings.APP_NAME,
        version=settings.APP_VERSION,
        description=(
            "Enterprise-grade AI-powered Social Media Operating System. "
            "Manage all 11 major platforms, generate content with LLMs, "
            "schedule posts, track analytics, and run autonomous AI agents."
        ),
        docs_url="/docs" if settings.ENVIRONMENT != "production" else None,
        redoc_url="/redoc" if settings.ENVIRONMENT != "production" else None,
        openapi_url="/openapi.json" if settings.ENVIRONMENT != "production" else None,
        default_response_class=ORJSONResponse,
        lifespan=lifespan,
    )

    _register_middleware(app)
    _register_exception_handlers(app)
    _register_routers(app)
    _register_metrics(app)

    return app


# ─── Middleware ───────────────────────────────────────────────────────────────

class RequestIDMiddleware(BaseHTTPMiddleware):
    """Attach a unique request ID to every request for tracing."""

    async def dispatch(self, request: Request, call_next):  # type: ignore[override]
        request_id = request.headers.get("X-Request-ID") or str(uuid.uuid4())
        with structlog.contextvars.bound_contextvars(request_id=request_id):
            response: Response = await call_next(request)
        response.headers["X-Request-ID"] = request_id
        return response


class RequestLoggingMiddleware(BaseHTTPMiddleware):
    """Log every HTTP request with timing information."""

    async def dispatch(self, request: Request, call_next):  # type: ignore[override]
        start = time.perf_counter()
        response: Response = await call_next(request)
        duration_ms = (time.perf_counter() - start) * 1000

        log = logger.bind(
            method=request.method,
            path=request.url.path,
            status_code=response.status_code,
            duration_ms=round(duration_ms, 2),
            client_ip=request.client.host if request.client else "unknown",
        )

        if response.status_code >= 500:
            log.error("http.request")
        elif response.status_code >= 400:
            log.warning("http.request")
        else:
            log.info("http.request")

        return response


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    """Add security-related HTTP headers to all responses."""

    async def dispatch(self, request: Request, call_next):  # type: ignore[override]
        response: Response = await call_next(request)
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Permissions-Policy"] = "geolocation=(), microphone=(), camera=()"
        if settings.ENVIRONMENT == "production":
            response.headers["Strict-Transport-Security"] = (
                "max-age=63072000; includeSubDomains; preload"
            )
        return response


def _register_middleware(app: FastAPI) -> None:
    # Order matters: outermost middleware is applied last to responses, first to requests.

    # CORS — must be early so preflight OPTIONS requests get headers
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.ALLOWED_ORIGINS,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
        expose_headers=["X-Request-ID", "X-RateLimit-Limit", "X-RateLimit-Remaining"],
    )

    # Session (needed for OAuth state params stored server-side)
    app.add_middleware(
        SessionMiddleware,
        secret_key=settings.SECRET_KEY,
        session_cookie="sociai_session",
        max_age=3600,
        same_site="lax",
        https_only=(settings.ENVIRONMENT == "production"),
    )

    # Gzip compression for large responses
    app.add_middleware(GZipMiddleware, minimum_size=1024)

    # Trusted hosts
    if settings.ENVIRONMENT == "production":
        from urllib.parse import urlparse
        frontend_host = urlparse(settings.FRONTEND_URL).netloc
        app.add_middleware(
            TrustedHostMiddleware,
            allowed_hosts=["localhost", "127.0.0.1", frontend_host, "*.sociai-os.com"],
        )

    # Custom middleware (applied in reverse registration order for requests)
    app.add_middleware(SecurityHeadersMiddleware)
    app.add_middleware(RequestLoggingMiddleware)
    app.add_middleware(RequestIDMiddleware)


# ─── Exception Handlers ───────────────────────────────────────────────────────

def _register_exception_handlers(app: FastAPI) -> None:

    @app.exception_handler(RequestValidationError)
    async def validation_error_handler(
        request: Request, exc: RequestValidationError
    ) -> JSONResponse:
        errors = []
        for err in exc.errors():
            errors.append(
                {
                    "field": ".".join(str(loc) for loc in err["loc"][1:]),
                    "message": err["msg"],
                    "type": err["type"],
                }
            )
        logger.warning("http.validation_error", path=request.url.path, errors=errors)
        return JSONResponse(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            content={"detail": "Validation error", "errors": errors},
        )

    @app.exception_handler(404)
    async def not_found_handler(request: Request, exc: Exception) -> JSONResponse:
        return JSONResponse(
            status_code=404,
            content={"detail": f"Resource not found: {request.url.path}"},
        )

    @app.exception_handler(500)
    async def internal_error_handler(request: Request, exc: Exception) -> JSONResponse:
        logger.error(
            "http.internal_error",
            path=request.url.path,
            error=str(exc),
            exc_info=True,
        )
        return JSONResponse(
            status_code=500,
            content={"detail": "Internal server error. Our team has been notified."},
        )


# ─── Routers ─────────────────────────────────────────────────────────────────

def _register_routers(app: FastAPI) -> None:
    from app.api import api_router  # deferred to avoid circular imports

    app.include_router(api_router)

    # Health / readiness / liveness probes (no auth required)
    from fastapi import APIRouter as _Router
    from app.core.database import check_connection as _db_ok
    from app.core.redis_client import check_connection as _redis_ok

    health_router = _Router(tags=["Health"])

    @health_router.get("/health", summary="Application health check")
    async def health_check() -> dict:
        db_ok = await _db_ok()
        r_ok = await _redis_ok()
        healthy = db_ok and r_ok
        return {
            "status": "healthy" if healthy else "degraded",
            "version": settings.APP_VERSION,
            "environment": settings.ENVIRONMENT,
            "services": {
                "database": "ok" if db_ok else "error",
                "redis": "ok" if r_ok else "error",
            },
        }

    @health_router.get("/health/live", summary="Liveness probe")
    async def liveness() -> dict:
        return {"status": "alive"}

    @health_router.get("/health/ready", summary="Readiness probe")
    async def readiness() -> JSONResponse:
        db_ok = await _db_ok()
        r_ok = await _redis_ok()
        if db_ok and r_ok:
            return JSONResponse(status_code=200, content={"status": "ready"})
        return JSONResponse(
            status_code=503,
            content={
                "status": "not ready",
                "services": {
                    "database": "ok" if db_ok else "error",
                    "redis": "ok" if r_ok else "error",
                },
            },
        )

    app.include_router(health_router)


# ─── Prometheus Metrics ───────────────────────────────────────────────────────

def _register_metrics(app: FastAPI) -> None:
    try:
        from prometheus_fastapi_instrumentator import Instrumentator

        Instrumentator(
            should_group_status_codes=True,
            should_ignore_untemplated=True,
            excluded_handlers=["/health", "/health/live", "/health/ready", "/metrics"],
        ).instrument(app).expose(app, endpoint="/metrics", include_in_schema=False)
        logger.info("app.prometheus_metrics_enabled")
    except ImportError:
        logger.warning("app.prometheus_not_available")


# ─── WebSocket Connection Manager ─────────────────────────────────────────────
# Exported so route modules can import and use it.

from fastapi import WebSocket  # noqa: E402


class ConnectionManager:
    """
    In-process WebSocket connection manager.

    For multi-process deployments (multiple Uvicorn workers), use Redis
    pub/sub to broadcast messages across processes (see redis_client.publish).
    """

    def __init__(self) -> None:
        # user_id → list of active WebSocket connections
        self._connections: dict[str, list[WebSocket]] = {}

    async def connect(self, websocket: WebSocket, user_id: str) -> None:
        await websocket.accept()
        self._connections.setdefault(user_id, []).append(websocket)
        logger.info("ws.connected", user_id=user_id)

    def disconnect(self, websocket: WebSocket, user_id: str) -> None:
        conns = self._connections.get(user_id, [])
        if websocket in conns:
            conns.remove(websocket)
        if not conns:
            self._connections.pop(user_id, None)
        logger.info("ws.disconnected", user_id=user_id)

    async def send_to_user(self, user_id: str, message: dict) -> int:
        """Send a JSON message to all connections owned by *user_id*. Returns sent count."""
        sent = 0
        dead: list[WebSocket] = []
        for ws in self._connections.get(user_id, []):
            try:
                await ws.send_json(message)
                sent += 1
            except Exception:
                dead.append(ws)
        for ws in dead:
            self.disconnect(ws, user_id)
        return sent

    async def broadcast(self, message: dict) -> int:
        """Broadcast a JSON message to ALL connected users. Returns sent count."""
        sent = 0
        for user_id in list(self._connections.keys()):
            sent += await self.send_to_user(user_id, message)
        return sent

    @property
    def active_users(self) -> list[str]:
        return list(self._connections.keys())

    @property
    def total_connections(self) -> int:
        return sum(len(v) for v in self._connections.values())


ws_manager: ConnectionManager = ConnectionManager()

# ─── Application Instance ─────────────────────────────────────────────────────

app: FastAPI = create_application()
