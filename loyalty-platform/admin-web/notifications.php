<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('notifications', 'view');

$lists = all("select * from admin.user_lists order by name");
$push  = setting_get('push', ['function_url'=>'','service_key'=>'']);

function call_push(array $cfg, array $payload): string {
  if (empty($cfg['function_url']) || !function_exists('curl_init')) return '';
  $ch = curl_init($cfg['function_url']);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . ($cfg['service_key'] ?? '')],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
  ]);
  $res = curl_exec($ch); curl_close($ch);
  if ($res === false) return ' (تعذّر الاتصال بخدمة Push)';
  $j = json_decode($res, true);
  return isset($j['sent']) ? " وأُرسِل Push إلى {$j['sent']} جهاز." : ' (استجابة Push غير متوقّعة)';
}

if (is_post()) {
  csrf_check();
  if (post('action') === 'save_push') {
    require_perm('content', 'edit');
    setting_set('push', ['function_url'=>trim((string)post('function_url')), 'service_key'=>trim((string)post('service_key'))]);
    audit('update','push_config'); flash('تم حفظ إعداد Push.'); redirect('notifications.php');
  }
  require_perm('notifications', 'create');
  $t = trim((string) post('title')); $b = trim((string) post('body'));
  $audience = (string) post('audience'); $listId = (string) post('list_id');
  $sendPush = (bool) post('send_push');
  if ($t === '') { flash('العنوان مطلوب.', 'error'); redirect('notifications.php'); }

  $uids = null; // null = all
  $audName = 'كل المستخدمين';
  if ($audience === 'list' && $listId) {
    $list = one("select * from admin.user_lists where id=:id", ['id'=>$listId]);
    if ($list) { $uids = list_user_ids($list); $audName = 'قائمة: ' . $list['name']; }
  }

  // (1) إشعار داخل التطبيق
  if ($uids === null) {
    q("insert into public.notifications (user_id,type,title,body,data)
         select id,'announcement',:t,:b,jsonb_build_object('source','admin') from public.users", ['t'=>$t,'b'=>$b ?: null]);
    $count = (int) scalar("select count(*) from public.users");
  } else {
    q("insert into public.notifications (user_id,type,title,body,data)
         select uid,'announcement',:t,:b,jsonb_build_object('source','admin') from unnest(:ids::uuid[]) uid", ['t'=>$t,'b'=>$b ?: null,'ids'=>uuid_array($uids)]);
    $count = count($uids);
  }

  // (2) Push فعلي (اختياري)
  $pushMsg = '';
  if ($sendPush) {
    $payload = $uids === null ? ['title'=>$t,'body'=>$b,'audience'=>'all'] : ['title'=>$t,'body'=>$b,'user_ids'=>array_values($uids)];
    $pushMsg = call_push($push, $payload);
  }
  audit('broadcast','notification',null,['title'=>$t,'audience'=>$audName,'count'=>$count,'push'=>$sendPush]);
  flash("تم الإرسال داخل التطبيق إلى {$count} مستخدم ({$audName})." . $pushMsg);
  redirect('notifications.php');
}

$pre = (string) get('list', '');
$title = 'إرسال إشعارات';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">إشعار جديد</div>
    <?php if (!can('notifications','create')): ?><div class="text-gray-400">لديك صلاحية العرض فقط.</div><?php else: ?>
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
            <option value="<?= e($l['id']) ?>" <?= $pre===$l['id']?'selected':'' ?>><?= e($l['name']) ?> <?= $l['is_smart']?'(ذكية)':'' ?> — <?= n(list_count($l)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="flex items-center gap-2"><input type="checkbox" name="send_push" <?= !empty($push['function_url'])?'checked':'' ?> <?= empty($push['function_url'])?'disabled':'' ?>> إرسال Push للأجهزة <?= empty($push['function_url'])?'<span class="text-xs text-red-500">(اضبط Push أولًا)</span>':'' ?></label>
      <button onclick="return confirm('تأكيد إرسال الإشعار؟')" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">إرسال</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="space-y-6">
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-4">القوائم المتاحة</div>
      <?php foreach ($lists as $l): ?>
        <div class="flex justify-between py-2 border-b last:border-0 text-sm">
          <span class="font-bold"><?= e($l['name']) ?> <?= $l['is_smart']?badge('ذكية','blue'):'' ?></span><span class="text-gray-500"><?= n(list_count($l)) ?> عضو</span></div>
      <?php endforeach; ?>
      <?php if (!$lists): ?><div class="text-gray-400 text-sm">أنشئ قوائم من صفحة «القوائم».</div><?php endif; ?>
    </div>

    <?php if (can('content','edit')): ?>
    <div class="bg-white rounded-xl border p-5">
      <details <?= empty($push['function_url'])?'open':'' ?>>
        <summary class="font-bold cursor-pointer">إعداد Push (Edge Function)</summary>
        <form method="post" class="space-y-3 mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="save_push">
          <input name="function_url" value="<?= e($push['function_url']??'') ?>" placeholder="https://<ref>.functions.supabase.co/admin-push" class="w-full border rounded-lg px-3 py-2 text-sm ltr">
          <input name="service_key" value="<?= e($push['service_key']??'') ?>" placeholder="ADMIN_PUSH_SECRET" class="w-full border rounded-lg px-3 py-2 text-sm ltr">
          <button class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-bold">حفظ إعداد Push</button>
        </form>
        <p class="text-xs text-gray-400 mt-2">انشر دالة <code>admin-push</code> واضبط <code>ADMIN_PUSH_SECRET</code> و<code>FCM_SERVICE_ACCOUNT</code> في Supabase. بدونها يُكتفى بالإشعار داخل التطبيق.</p>
      </details>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
