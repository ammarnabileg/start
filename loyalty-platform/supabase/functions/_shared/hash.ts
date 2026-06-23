// sha256 hex — لتجزئة مفاتيح POS (لا نخزّن المفتاح الخام).
export async function sha256Hex(input: string): Promise<string> {
  const data = new TextEncoder().encode(input);
  const buf = await crypto.subtle.digest("SHA-256", data);
  return Array.from(new Uint8Array(buf))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

/// يولّد مفتاح POS عشوائيًا: pos_live_<64 hex>
export function generatePosKey(): string {
  const bytes = new Uint8Array(24);
  crypto.getRandomValues(bytes);
  const hex = Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
  return `pos_live_${hex}`;
}
