#!/usr/bin/env bash
# يبني assets/app.css من Tailwind (بدل cdn.tailwindcss.com). شغّله بعد أي تعديل في الكلاسات.
# الأداة: ملف واحد من https://github.com/tailwindlabs/tailwindcss/releases/tag/v3.4.16 (tailwindcss-linux-x64)
# ومسارها في TAILWIND_BIN. الكلاسات بتتجمع من app/ و assets/ ومن tailwind/safelist.txt (كلاسات بتتركب وقت التشغيل).
set -e
cd "$(dirname "$0")"
BIN="${TAILWIND_BIN:-./tailwindcss}"
"$BIN" -c tailwind/tailwind.config.js -i tailwind/input.css -o assets/app.css --minify
