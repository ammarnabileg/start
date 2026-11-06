/* تطبيق الموبايل (PWA): بيحفظ الملفات الثابتة بس (CSS/JS/خطوط/أيقونات) عشان الفتح يبقى أسرع.
   الصفحات نفسها مابتتحفظش على الجهاز أبدًا (فيها بيانات العملاء)، ولو مفيش نت بتظهر صفحة "مفيش اتصال". */
var V = 'aipush-v1', OFF = 'offline.html';
self.addEventListener('install', function (e) { e.waitUntil(caches.open(V).then(function (c) { return c.addAll([OFF]); }).then(function () { return self.skipWaiting(); })); });
self.addEventListener('activate', function (e) { e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k !== V; }).map(function (k) { return caches.delete(k); })); }).then(function () { return self.clients.claim(); })); });
self.addEventListener('fetch', function (e) {
  var r = e.request; if (r.method !== 'GET') return;
  var u = new URL(r.url); if (u.origin !== location.origin) return;
  if (/\/assets\/.+\.(css|js|png|svg|woff2?)$/.test(u.pathname)) { // ملفات ثابتة: من الكاش، وبتتحدث في الخلفية
    e.respondWith(caches.open(V).then(function (c) { return c.match(r).then(function (hit) { var net = fetch(r).then(function (res) { if (res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; }); return hit || net; }); }));
    return;
  }
  if (r.mode === 'navigate') e.respondWith(fetch(r).catch(function () { return caches.match(OFF); }));
});
