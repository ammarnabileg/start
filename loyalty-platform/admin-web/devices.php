<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('devices', 'view');

if (is_post()) {
  csrf_check();
  require_perm('devices', 'edit');
  $act = (string) post('action'); $uid = (string) post('user_id');
  if ($act === 'ban') {
    q("update public.users set banned_at=now() where id=:id", ['id'=>$uid]);
    $del = (int) scalar("select count(*) from public.device_tokens where user_id=:id", ['id'=>$uid]);
    q("delete from public.device_tokens where user_id=:id", ['id'=>$uid]); // قطع الإشعارات فورًا
    audit('ban', 'user', $uid, ['tokens_removed'=>$del]); flash('تم حظر المستخدم وإزالة أجهزته.');
  } elseif ($act === 'unban') {
    q("update public.users set banned_at=null where id=:id", ['id'=>$uid]);
    audit('unban', 'user', $uid); flash('تم رفع الحظر.');
  } elseif ($act === 'del_token') {
    q("delete from public.device_tokens where id=:id", ['id'=>(string)post('token_id')]);
    audit('delete', 'device_token', (string)post('token_id')); flash('تم حذف الجهاز.');
  }
  redirect('devices.php?' . http_build_query(array_filter(['q'=>get('q')])));
}

$qstr = trim((string) get('q', ''));
$rows = $qstr !== ''
  ? all("select u.*, (select count(*) from public.device_tokens d where d.user_id=u.id) tokens
         from public.users u where u.name ilike :q or u.phone ilike :q or u.email ilike :q order by u.created_at desc limit 25", ['q'=>'%'.$qstr.'%'])
  : all("select u.*, (select count(*) from public.device_tokens d where d.user_id=u.id) tokens
         from public.users u where u.banned_at is not null order by u.banned_at desc limit 25");
$sel = (string) get('view', '');
$tokens = $sel ? all("select * from public.device_tokens where user_id=:id order by created_at desc", ['id'=>$sel]) : [];

$title = 'الأجهزة والحظر';
require __DIR__ . '/partials/header.php';
?>
<form class="flex gap-2 mb-4"><input name="q" value="<?= e($qstr) ?>" placeholder="ابحث عن مستخدم (اسم/جوال/بريد)" class="flex-1 border rounded-lg px-4 py-2">
  <button class="bg-gray-800 text-white rounded-lg px-5 py-2 font-bold">بحث</button></form>
<p class="text-sm text-gray-500 mb-3"><?= $qstr===''?'المحظورون حاليًا:':'نتائج البحث:' ?></p>

<div class="bg-white rounded-xl border overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-gray-500 text-right"><tr>
      <th class="px-4 py-3 font-medium">المستخدم</th><th class="px-4 py-3 font-medium">الأجهزة</th>
      <th class="px-4 py-3 font-medium">الحالة</th><th class="px-4 py-3 font-medium">إجراءات</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-4 py-3"><div class="font-bold"><?= e($u['name']) ?></div><div class="text-xs text-gray-400"><?= e($u['phone']) ?></div></td>
        <td class="px-4 py-3"><a href="?view=<?= e($u['id']) ?><?= $qstr?'&q='.urlencode($qstr):'' ?>" class="text-amber-600 font-bold"><?= n($u['tokens']) ?> جهاز</a></td>
        <td class="px-4 py-3"><?= $u['banned_at'] ? badge('محظور','red') : status_badge('active') ?></td>
        <td class="px-4 py-3">
          <?php if (can('devices','edit')): ?>
          <form method="post" class="inline" onsubmit="return confirm('تأكيد؟')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $u['banned_at']?'unban':'ban' ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
            <button class="px-3 py-1 rounded text-xs font-bold <?= $u['banned_at']?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= $u['banned_at']?'رفع الحظر':'حظر' ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($sel === $u['id']): ?>
        <tr class="bg-gray-50"><td colspan="4" class="px-6 py-3">
          <div class="font-bold mb-2 text-sm">أجهزة <?= e($u['name']) ?></div>
          <?php foreach ($tokens as $t): ?>
            <div class="flex items-center justify-between py-1.5 border-b last:border-0 text-sm">
              <span><?= badge($t['platform'],'blue') ?> <span class="font-mono text-xs text-gray-500 ltr"><?= e(substr($t['token'],0,28)) ?>…</span> · <?= d($t['created_at']) ?></span>
              <?php if (can('devices','edit')): ?>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="del_token"><input type="hidden" name="token_id" value="<?= e($t['id']) ?>"><input type="hidden" name="q" value="<?= e($qstr) ?>">
                <button class="text-red-600 text-xs">حذف</button></form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$tokens): ?><div class="text-gray-400 text-sm">لا أجهزة مسجّلة.</div><?php endif; ?>
        </td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="px-4 py-10 text-center text-gray-400"><?= $qstr?'لا نتائج.':'لا مستخدمين محظورين.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-gray-400 mt-3">الحظر يضبط <code>users.banned_at</code> ويزيل توكنات الأجهزة (يوقف الإشعارات فورًا). لمنع الدخول كليًا أضِف فحص <code>banned_at</code> في سياسات RLS / دوال الحافة.</p>
<?php require __DIR__ . '/partials/footer.php'; ?>
