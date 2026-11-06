<?php
/** #52 صفحة هبوط بسيطة للحملة (عامة): index.php?p=lp&w=ID&s=slug — عنوان ووصف وصورة وزرار واتساب وفورم */
$wid = (int)($_GET['w'] ?? 0);
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['s'] ?? '')));
$pg = null; foreach (lp_list($wid) as $x) if (($x['slug'] ?? '') === $slug && !empty($x['on'])) $pg = $x;
$tok = $wid ? (string)setting('crm_form_token_' . $wid, '') : '';
if (!$pg || $tok === '') { http_response_code(404); exit('<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif">Page not found.</p>'); }
// عدّاد زيارات بسيط
$pg['views'] = (int)($pg['views'] ?? 0) + 1;
$all = lp_list($wid); foreach ($all as &$x) if ($x['slug'] === $slug) $x['views'] = $pg['views']; unset($x);
save_setting('crm_lp_' . $wid, json_encode($all, JSON_UNESCAPED_UNICODE));
$color = preg_match('/^#[0-9a-f]{6}$/i', (string)($pg['color'] ?? '')) ? $pg['color'] : '#4f46e5';
$q = ['p' => 'lead_form', 'w' => $wid, 't' => $tok, 'utm_source' => 'landing', 'utm_campaign' => $slug];
foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u) if (!empty($_GET[$u])) $q[$u] = mb_substr((string)$_GET[$u], 0, 60);
$form = base_url() . '/index.php?' . http_build_query($q);
$wa = preg_replace('/\D/', '', (string)($pg['wa'] ?? ''));
$img = preg_match('~^https://~i', (string)($pg['img'] ?? '')) ? $pg['img'] : '';
?><!doctype html>
<html lang="<?= is_rtl() ? 'ar' : 'en' ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pg['title'] ?? '') ?></title><meta name="description" content="<?= e(mb_substr((string)($pg['sub'] ?? ''), 0, 160)) ?>">
<meta property="og:title" content="<?= e($pg['title'] ?? '') ?>"><?php if ($img): ?><meta property="og:image" content="<?= e($img) ?>"><?php endif; ?>
<style>
:root{--c:<?= e($color) ?>}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:#f8fafc;color:#0f172a;line-height:1.6}
.w{max-width:720px;margin:0 auto;padding:24px 16px 48px}.hero{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.06)}
.hero img{width:100%;max-height:380px;object-fit:cover;display:block}.in{padding:24px}h1{margin:0 0 6px;font-size:28px;line-height:1.25}.sub{color:#475569;font-size:17px;margin:0 0 14px}
.body{white-space:pre-line;color:#334155}.cta{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:12px 18px;font-weight:700;text-decoration:none;color:#fff;background:var(--c)}
.btn.wa{background:#16a34a}.form{margin-top:20px;background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(15,23,42,.06);padding:8px}iframe{width:100%;border:0;min-height:430px}
</style></head><body><div class="w">
<div class="hero"><?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?><div class="in">
<h1><?= e($pg['title'] ?? '') ?></h1><?php if (!empty($pg['sub'])): ?><p class="sub"><?= e($pg['sub']) ?></p><?php endif; ?>
<?php if (!empty($pg['body'])): ?><div class="body"><?= e($pg['body']) ?></div><?php endif; ?>
<div class="cta"><a class="btn" href="#form"><?= e(($pg['cta'] ?? '') ?: t('سجّل اهتمامك')) ?></a><?php if ($wa): ?><a class="btn wa" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode((string)($pg['title'] ?? '')) ?>" rel="noopener">WhatsApp</a><?php endif; ?></div>
</div></div>
<div class="form" id="form"><iframe src="<?= e($form) ?>" title="form" loading="lazy"></iframe></div>
</div></body></html>
<?php exit;
