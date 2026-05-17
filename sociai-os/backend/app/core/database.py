"""
app/core/database.py
────────────────────
SQLAlchemy 2.0 async engine, session factory, and declarative base.
All models should import `Base` from here and use `AsyncSession` for queries.
"""
from __future__ import annotations

import contextlib
from typing import AsyncGenerator

from sqlalchemy import event, text
from sqlalchemy.ext.asyncio import (
    AsyncConnection,
    AsyncEngine,
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)
from sqlalchemy.orm import DeclarativeBase, MappedColumn
from sqlalchemy.pool import NullPool, AsyncAdaptedQueuePool

import structlog

from app.core.config import settings

logger = structlog.get_logger(__name__)


# ─── Declarative Base ────────────────────────────────────────────────────────

class Base(DeclarativeBase):
    """
    Base class for all ORM models.

    Provides:
    - Automatic __tablename__ derived from class name (snake_case)
    - Common helper methods (to_dict, etc.)
    """

    def to_dict(self) -> dict:
        """Return a plain dict of all column values."""
        return {
            col.name: getattr(self, col.name)
            for col in self.__table__.columns
        }

    def __repr__(self) -> str:
        pk_cols = [c.name for c in self.__table__.primary_key.columns]
        pk_vals = ", ".join(f"{c}={getattr(self, c)!r}" for c in pk_cols)
        return f"<{self.__class__.__name__}({pk_vals})>"


# ─── Engine Factory ──────────────────────────────────────────────────────────

def _build_engine() -> AsyncEngine:
    """Create an async SQLAlchemy engine with sensible pool settings."""
    pool_class = NullPool if settings.ENVIRONMENT == "testing" else AsyncAdaptedQueuePool

    connect_args: dict = {}
    if "asyncpg" in settings.DATABASE_URL:
        connect_args = {
            "server_settings": {
                "jit": "off",  # avoid JIT overhead for short-lived OLTP queries
                "application_name": settings.APP_NAME,
            },
            "command_timeout": 60,
        }

    engine = create_async_engine(
        settings.DATABASE_URL,
        echo=settings.DATABASE_ECHO,
        pool_size=settings.DATABASE_POOL_SIZE if pool_class is not NullPool else 5,
        max_overflow=settings.DATABASE_MAX_OVERFLOW if pool_class is not NullPool else 0,
        pool_timeout=settings.DATABASE_POOL_TIMEOUT,
        pool_recycle=settings.DATABASE_POOL_RECYCLE,
        pool_pre_ping=True,
        connect_args=connect_args,
        poolclass=pool_class,
    )
    return engine


engine: AsyncEngine = _build_engine()

# ─── Session Factory ─────────────────────────────────────────────────────────

AsyncSessionLocal: async_sessionmaker[AsyncSession] = async_sessionmaker(
    bind=engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)


# ─── Dependency (FastAPI) ─────────────────────────────────────────────────────

async def get_db() -> AsyncGenerator[AsyncSession, None]:
    """
    FastAPI dependency that yields a database session per request.

    Usage::

        @router.get("/items")
        async def list_items(db: AsyncSession = Depends(get_db)):
            ...
    """
    async with AsyncSessionLocal() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()


# ─── Context manager (non-FastAPI usage, e.g. Celery tasks) ──────────────────

@contextlib.asynccontextmanager
async def get_db_session() -> AsyncGenerator[AsyncSession, None]:
    """
    Async context manager that provides a database session.

    Usage::

        async with get_db_session() as db:
            result = await db.execute(select(User))
    """
    async with AsyncSessionLocal() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()


# ─── Lifecycle Helpers ───────────────────────────────────────────────────────

async def create_all_tables() -> None:
    """Create all tables defined in the metadata (for testing / first run)."""
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    logger.info("database.tables_created")


async def drop_all_tables() -> None:
    """Drop all tables — DESTRUCTIVE, for testing only."""
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
    logger.warning("database.tables_dropped")


async def check_connection() -> bool:
    """Verify the database is reachable. Returns True on success."""
    try:
        async with engine.connect() as conn:
            await conn.execute(text("SELECT 1"))
        return True
    except Exception as exc:
        logger.error("database.connection_failed", error=str(exc))
        return False


async def close_engine() -> None:
    """Dispose the connection pool cleanly on application shutdown."""
    await engine.dispose()
    logger.info("database.engine_disposed")
