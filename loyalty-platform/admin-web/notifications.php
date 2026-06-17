<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('notifications', 'view');

$lists = all("select id, name, (select count(*) from admin.list_members m where m.list_id=user_lists.id) c
              from admin.user_lists order by name");

if (is_post()) {
  csrf_check();
  require_perm('notifications', 'create');
  $title_in = trim((string) post('title'));
  $body_in  = trim((string) post('body'));
  $audience = (string) post('audience');     // all | list
  $listId   = (string) post('list_id');
  if ($title_in === '') { flash('العنوان مطلوب.', 'error'); redirect('notifications.php'); }

  if ($audience === 'list' && $listId) {
    q("insert into public.notifications (user_id, type, title, body, data)
         select user_id, 'announcement', :t, :b, jsonb_build_object('source','admin')
         from admin.list_members where list_id=:l", ['t'=>$title_in,'b'=>$body_in ?: null,'l'=>$listId]);
    $count = (int) scalar("select count(*) from admin.list_members where list_id=:l", ['l'=>$listId]);
    $aud = 'قائمة';
  } else {
    q("insert into public.notifications (user_id, type, title, body, data)
         select id, 'announcement', :t, :b, jsonb_build_object('source','admin') from public.users",
       ['t'=>$title_in,'b'=>$body_in ?: null]);
    $count = (int) scalar("select count(*) from public.users");
    $aud = 'كل المستخدمين';
  }
  audit('broadcast', 'notification', null, ['title'=>$title_in,'audience'=>$aud,'count'=>$count]);
  flash("تم إرسال الإشعار إلى {$count} مستخدم ({$aud}).");
  redirect('notifications.php');
}

$pre = (string) get('list', '');
$title = 'إرسال إشعارات';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">إشعار جديد</div>
    <?php if (!can('notifications','create')): ?>
      <div class="text-gray-400">لديك صلاحية العرض فقط.</div>
    <?php else: ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?>
      <input name="title" placeholder="العنوان" required class="w-full border rounded-lg px-4 py-2.5">
      <textarea name="body" placeholder="النص" rows="4" class="w-full border rounded-lg px-4 py-2.5"></textarea>
      <div>
        <label class="block text-sm text-gray-500 mb-1">الجمهور</label>
        <div class="flex gap-4 mb-2">
          <label class="inline-flex items-center gap-2"><input type="radio" name="audience" value="all" <?= $pre?'':'checked' ?>> كل المستخدمين</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="audience" value="list" <?= $pre?'checked':'' ?>> قائمة محدّدة</label>
        </div>
        <select name="list_id" class="w-full border rounded-lg px-4 py-2.5">
          <?php foreach ($lists as $l): ?>
            <option value="<?= e($l['id']) ?>" <?= $pre===$l['id']?'selected':'' ?>><?= e($l['name']) ?> (<?= n($l['c']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button onclick="return confirm('تأكيد إرسال الإشعار؟')" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">إرسال</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">القوائم المتاحة</div>
    <?php foreach ($lists as $l): ?>
      <div class="flex justify-between py-2 border-b last:border-0 text-sm">
        <span class="font-bold"><?= e($l['name']) ?></span><span class="text-gray-500"><?= n($l['c']) ?> عضو</span>
      </div>
    <?php endforeach; ?>
    <?php if (!$lists): ?><div class="text-gray-400 text-sm">أنشئ قوائم أولًا من صفحة «القوائم».</div><?php endif; ?>
    <p class="text-xs text-gray-400 mt-4">يُسجَّل الإشعار داخل التطبيق فورًا لكل مستخدم. (إشعارات الدفع Push تُرسَل عبر خدمة FCM في الباك‑إند.)</p>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
