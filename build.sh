#!/usr/bin/env bash
# يبني ملف zip جاهز للرفع. الملفات في الجذر مباشرة (بدون مجلد خارجي).
set -euo pipefail
cd "$(dirname "$0")"
VER=$(grep -oE "[0-9]+\.[0-9]+\.[0-9]+" app/version.php)
mkdir -p dist
# CSS: لازم يتبني قبل التغليف عشان الكلاسات الجديدة تبقى موجودة
if [ -n "${TAILWIND_BIN:-}" ] || [ -x ./tailwindcss ]; then ./build-css.sh; else echo "تحذير: TAILWIND_BIN مش متحدد — assets/app.css مش هيتبني من جديد" >&2; fi
OUT="dist/panel-v${VER}.zip"
rm -f "$OUT"
zip -qr "$OUT" index.php setup.php .htaccess app assets uploads/.htaccess README.md sw.js manifest.webmanifest offline.html \
  -x "config.php" "*.DS_Store"
echo "$OUT"
