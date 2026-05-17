from __future__ import annotations

import base64
import io
import secrets
import time
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, Optional, Tuple

import pyotp
import qrcode
from cryptography.fernet import Fernet, InvalidToken
from jose import JWTError, jwt
from passlib.context import CryptContext

from app.core.config import settings

# ─── Password Hashing ─────────────────────────────────────────────────────────

pwd_context = CryptContext(
    schemes=["bcrypt"],
    deprecated="auto",
    bcrypt__rounds=settings.BCRYPT_ROUNDS,
)


def hash_password(plain_password: str) -> str:
    """Hash a plaintext password using bcrypt."""
    return pwd_context.hash(plain_password)


def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verify a plaintext password against its bcrypt hash."""
    return pwd_context.verify(plain_password, hashed_password)


def needs_rehash(hashed_password: str) -> bool:
    """Check if a password hash needs to be upgraded (e.g., rounds changed)."""
    return pwd_context.needs_update(hashed_password)


# ─── JWT Tokens ───────────────────────────────────────────────────────────────

TokenData = Dict[str, Any]

_ACCESS_TOKEN_TYPE = "access"
_REFRESH_TOKEN_TYPE = "refresh"
_EMAIL_TOKEN_TYPE = "email_verification"
_RESET_TOKEN_TYPE = "password_reset"


def _build_token(
    subject: str,
    token_type: str,
    expires_delta: timedelta,
    extra_claims: Optional[Dict[str, Any]] = None,
) -> str:
    now = datetime.now(timezone.utc)
    expire = now + expires_delta
    payload: Dict[str, Any] = {
        "sub": str(subject),
        "type": token_type,
        "iat": int(now.timestamp()),
        "exp": int(expire.timestamp()),
        "jti": secrets.token_urlsafe(16),
    }
    if extra_claims:
        payload.update(extra_claims)
    return jwt.encode(payload, settings.SECRET_KEY, algorithm=settings.JWT_ALGORITHM)


def create_access_token(
    user_id: str,
    *,
    extra_claims: Optional[Dict[str, Any]] = None,
    expires_delta: Optional[timedelta] = None,
) -> str:
    """Create a short-lived JWT access token."""
    delta = expires_delta or timedelta(minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES)
    return _build_token(user_id, _ACCESS_TOKEN_TYPE, delta, extra_claims)


def create_refresh_token(
    user_id: str,
    *,
    extra_claims: Optional[Dict[str, Any]] = None,
) -> str:
    """Create a long-lived JWT refresh token."""
    delta = timedelta(days=settings.REFRESH_TOKEN_EXPIRE_DAYS)
    return _build_token(user_id, _REFRESH_TOKEN_TYPE, delta, extra_claims)


def create_email_verification_token(email: str) -> str:
    """Create a token for email address verification."""
    delta = timedelta(hours=settings.EMAIL_VERIFICATION_TOKEN_EXPIRE_HOURS)
    return _build_token(email, _EMAIL_TOKEN_TYPE, delta)


def create_password_reset_token(user_id: str) -> str:
    """Create a short-lived token for password reset."""
    delta = timedelta(hours=settings.PASSWORD_RESET_TOKEN_EXPIRE_HOURS)
    return _build_token(user_id, _RESET_TOKEN_TYPE, delta)


def decode_token(token: str, expected_type: Optional[str] = None) -> Dict[str, Any]:
    """
    Decode and validate a JWT token.

    Raises:
        JWTError: if the token is invalid, expired, or has wrong type.
    """
    payload = jwt.decode(
        token,
        settings.SECRET_KEY,
        algorithms=[settings.JWT_ALGORITHM],
        options={"require": ["sub", "type", "exp", "jti"]},
    )
    if expected_type and payload.get("type") != expected_type:
        raise JWTError(f"Expected token type '{expected_type}', got '{payload.get('type')}'")
    return payload


def decode_access_token(token: str) -> Dict[str, Any]:
    return decode_token(token, expected_type=_ACCESS_TOKEN_TYPE)


def decode_refresh_token(token: str) -> Dict[str, Any]:
    return decode_token(token, expected_type=_REFRESH_TOKEN_TYPE)


def decode_email_verification_token(token: str) -> Dict[str, Any]:
    return decode_token(token, expected_type=_EMAIL_TOKEN_TYPE)


def decode_password_reset_token(token: str) -> Dict[str, Any]:
    return decode_token(token, expected_type=_RESET_TOKEN_TYPE)


def rotate_tokens(refresh_token: str) -> Tuple[str, str]:
    """
    Validate a refresh token and issue a new access + refresh token pair.
    The caller is responsible for revoking the old refresh token (e.g., via Redis).
    """
    payload = decode_refresh_token(refresh_token)
    user_id: str = payload["sub"]
    new_access = create_access_token(user_id)
    new_refresh = create_refresh_token(user_id)
    return new_access, new_refresh


# ─── Fernet Encryption (Platform Credentials) ────────────────────────────────

def _get_fernet() -> Fernet:
    """
    Build a Fernet instance from settings.ENCRYPTION_KEY.
    The key is URL-safe base64 encoded; we derive a 32-byte key and
    re-encode it so Fernet is satisfied.
    """
    raw = settings.ENCRYPTION_KEY.encode()
    # Fernet requires exactly 32 url-safe base64 bytes
    padded = base64.urlsafe_b64encode(raw[:32].ljust(32, b"\x00"))
    return Fernet(padded)


_fernet: Optional[Fernet] = None


def get_fernet() -> Fernet:
    global _fernet
    if _fernet is None:
        _fernet = _get_fernet()
    return _fernet


def encrypt_credential(plaintext: str) -> str:
    """Encrypt a platform credential string (OAuth token, API key, etc.)."""
    return get_fernet().encrypt(plaintext.encode()).decode()


def decrypt_credential(ciphertext: str) -> str:
    """
    Decrypt a previously encrypted platform credential.

    Raises:
        ValueError: if the ciphertext is tampered or the key has changed.
    """
    try:
        return get_fernet().decrypt(ciphertext.encode()).decode()
    except InvalidToken as exc:
        raise ValueError("Unable to decrypt credential — invalid token or wrong key.") from exc


def encrypt_dict(data: Dict[str, str]) -> Dict[str, str]:
    """Encrypt every value in a dict of credentials."""
    return {k: encrypt_credential(v) for k, v in data.items()}


def decrypt_dict(data: Dict[str, str]) -> Dict[str, str]:
    """Decrypt every value in a dict of credentials."""
    return {k: decrypt_credential(v) for k, v in data.items()}


# ─── Two-Factor Authentication (TOTP) ────────────────────────────────────────

def generate_totp_secret() -> str:
    """Generate a new base32-encoded TOTP secret."""
    return pyotp.random_base32()


def get_totp(secret: str) -> pyotp.TOTP:
    """Return a TOTP object for the given secret."""
    return pyotp.TOTP(
        secret,
        digits=settings.TOTP_DIGITS,
        interval=settings.TOTP_INTERVAL,
        issuer=settings.TOTP_ISSUER_NAME,
    )


def generate_totp_provisioning_uri(secret: str, account_name: str) -> str:
    """Return an otpauth:// URI for use in an authenticator app or QR code."""
    totp = get_totp(secret)
    return totp.provisioning_uri(
        name=account_name,
        issuer_name=settings.TOTP_ISSUER_NAME,
    )


def generate_totp_qr_code(secret: str, account_name: str) -> str:
    """
    Generate a QR code PNG image encoded as a base64 data URI.
    Returns a string like: 'data:image/png;base64,...'
    """
    uri = generate_totp_provisioning_uri(secret, account_name)
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    qr.add_data(uri)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white")
    buffer = io.BytesIO()
    img.save(buffer, format="PNG")
    b64 = base64.b64encode(buffer.getvalue()).decode()
    return f"data:image/png;base64,{b64}"


def verify_totp(secret: str, code: str, *, valid_window: int = 1) -> bool:
    """
    Verify a TOTP code.

    Args:
        secret:       The user's TOTP secret.
        code:         The 6-digit code to verify.
        valid_window: Number of intervals before/after the current one to accept
                      (helps with clock skew). Default is 1 (±30 seconds).
    """
    totp = get_totp(secret)
    return totp.verify(code, valid_window=valid_window)


def generate_backup_codes(count: int = 10) -> list[str]:
    """
    Generate one-time-use backup codes for 2FA recovery.
    Returns a list of hyphenated 10-character codes (e.g. 'ABCDE-FGHIJ').
    """
    codes: list[str] = []
    for _ in range(count):
        raw = secrets.token_hex(5).upper()
        codes.append(f"{raw[:5]}-{raw[5:]}")
    return codes


def hash_backup_code(code: str) -> str:
    """Hash a backup code for storage (same bcrypt pipeline as passwords)."""
    return hash_password(code.replace("-", "").upper())


def verify_backup_code(plain_code: str, hashed_code: str) -> bool:
    """Verify a user-supplied backup code against its stored hash."""
    normalized = plain_code.replace("-", "").upper()
    return verify_password(normalized, hashed_code)


# ─── Rate Limiting Helpers ────────────────────────────────────────────────────

class RateLimitExceeded(Exception):
    """Raised when a client exceeds the allowed request rate."""

    def __init__(self, retry_after: int):
        self.retry_after = retry_after
        super().__init__(f"Rate limit exceeded. Retry after {retry_after}s.")


async def check_rate_limit(
    redis_client: Any,
    key: str,
    limit: int,
    window_seconds: int,
) -> Tuple[int, int]:
    """
    Sliding-window rate limiter backed by Redis.

    Returns:
        (current_count, remaining_requests)

    Raises:
        RateLimitExceeded: if the limit has been reached.
    """
    now = int(time.time())
    window_start = now - window_seconds

    pipe = redis_client.pipeline()
    pipe.zremrangebyscore(key, "-inf", window_start)
    pipe.zadd(key, {f"{now}:{secrets.token_hex(4)}": now})
    pipe.zcard(key)
    pipe.expire(key, window_seconds)
    results = await pipe.execute()

    current_count: int = results[2]
    if current_count > limit:
        retry_after = window_seconds - (now - window_start)
        raise RateLimitExceeded(retry_after=max(1, retry_after))

    remaining = max(0, limit - current_count)
    return current_count, remaining


def generate_secure_token(nbytes: int = 32) -> str:
    """Generate a URL-safe random token (for webhook secrets, etc.)."""
    return secrets.token_urlsafe(nbytes)
