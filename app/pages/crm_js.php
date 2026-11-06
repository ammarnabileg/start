<?php
/* الكود اللي بيتحط على موقعك: 1) لو الزائر جه من رابط متتبع، بيسجّل الصفحات اللي فتحها  2) زرار عائم (واتساب + فورم) لو مفعّل */
header('Content-Type: application/javascript; charset=utf-8'); header('Cache-Control: public, max-age=600'); header('Access-Control-Allow-Origin: *');
$wid = (int)($_GET['w'] ?? 0); $t = (string)($_GET['t'] ?? '');
$real = $wid ? (string)setting('crm_form_token_' . $wid, '') : '';
if ($real === '' || !hash_equals($real, $t)) exit('/* off */');
$c = crm_track_cfg($wid);
$base = base_url() . '/index.php';
$form = $base . '?' . http_build_query(['p' => 'lead_form', 'w' => $wid, 't' => $real]);
$wa = preg_replace('/\D/', '', (string)$c['wa']);
$color = preg_match('/^#[0-9a-f]{6}$/i', (string)$c['color']) ? $c['color'] : '#4f46e5';
$cfg = ['v' => $base . '?p=crm_v&w=' . $wid, 'form' => setting('crm_form_on_' . $wid, '1') === '1' ? $form : '', 'wa' => $wa, 'btn' => (int)$c['btn'], 'color' => $color,
    'label' => trim((string)$c['label']) ?: 'تواصل معانا', 'waLabel' => 'WhatsApp', 'formLabel' => 'ابعتلنا'];
?>
(function(){var C=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var K=null;try{var m=location.search.match(/[?&]ap=([0-9]+\.[0-9a-f]{12})/);if(m){K=m[1];localStorage.setItem('ap_k',K);}else K=localStorage.getItem('ap_k');}catch(e){}
if(K){var i=new Image();i.src=C.v+'&k='+encodeURIComponent(K)+'&u='+encodeURIComponent(location.href)+'&r='+Date.now();}
if(!C.btn||(!C.wa&&!C.form))return;
var d=document,s=d.createElement('div');s.style.cssText='position:fixed;bottom:20px;right:20px;z-index:2147483000;font-family:system-ui,Tahoma,sans-serif';
var b=d.createElement('button');b.type='button';b.textContent='💬 '+C.label;b.style.cssText='border:0;border-radius:999px;padding:12px 18px;background:'+C.color+';color:#fff;font-size:15px;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.2);cursor:pointer';
var p=d.createElement('div');p.style.cssText='display:none;position:absolute;bottom:56px;right:0;width:320px;max-width:calc(100vw - 40px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.25);overflow:hidden';
if(C.wa){var w=d.createElement('a');w.href='https://wa.me/'+C.wa+'?text='+encodeURIComponent('أهلاً، عندي استفسار');w.target='_blank';w.rel='noopener';w.textContent='🟢 '+C.waLabel;w.style.cssText='display:block;padding:14px 16px;color:#111;text-decoration:none;border-bottom:1px solid #eee;font-weight:600';p.appendChild(w);}
if(C.form){var f=d.createElement('iframe');f.src=C.form;f.title='form';f.style.cssText='width:100%;height:400px;border:0;display:block';p.appendChild(f);}
b.onclick=function(){p.style.display=p.style.display==='none'?'block':'none';};
s.appendChild(p);s.appendChild(b);(d.body||d.documentElement).appendChild(s);})();
<?php exit;
