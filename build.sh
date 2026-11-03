#!/usr/bin/env bash
# يبني ملف zip جاهز للرفع. الملفات في الجذر مباشرة (بدون مجلد خارجي).
set -euo pipefail
cd "$(dirname "$0")"
VER=$(grep -oE "[0-9]+\.[0-9]+\.[0-9]+" app/version.php)
mkdir -p dist
OUT="dist/panel-v${VER}.zip"
rm -f "$OUT"
zip -qr "$OUT" index.php setup.php .htaccess app assets uploads/.htaccess README.md \
  -x "config.php" "*.DS_Store"
echo "$OUT"
