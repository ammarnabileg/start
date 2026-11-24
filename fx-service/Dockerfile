# صورة Playwright الرسمية فيها Chromium وكل مكتباته جاهزة
FROM mcr.microsoft.com/playwright:v1.49.1-jammy

WORKDIR /app
ENV NODE_ENV=production
COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund
COPY server.js effects.js ./

EXPOSE 8080
# مرّر التوكن وقت التشغيل:  docker run -e FX_TOKEN=... -p 8080:8080 shohad-fx
CMD ["node", "server.js"]
