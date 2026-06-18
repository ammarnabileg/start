<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('reports', 'view');

$id = (string) get('id');
$report = one("select r.*, u.name uname, u.phone uphone, m.business_name
  from public.reports r
  left join public.users u on u.id=r.user_id
  left join public.merchants m on m.id=r.merchant_id
  where r.id=:id", ['id' => $id]);
if (!$report) { http_response_code(404); exit('البلاغ غير موجود'); }

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'reply') {
    require_perm('reports', 'reply');
    $body = trim((string) post('body'));
    $att  = trim((string) post('attachment_url'));
    $reply_to = (string) post('reply_to') ?: null;
    if ($body !== '') {
      $admin = current_admin();
      q("insert into public.report_messages
           (report_id, sender_role, sender_name, body, attachment_url, reply_to_id)
         values (:rid,'admin',:nm,:body,:att,:rt)",
        ['rid' => $id, 'nm' => $admin['name'] ?? 'إدارة المنصّة',
         'body' => $body, 'att' => $att !== '' ? $att : null, 'rt' => $reply_to]);
      q("update public.reports set last_message_at=now() where id=:id", ['id' => $id]);
      // إشعار صاحب البلاغ
      q("insert into public.notifications(user_id,type,title,body,data)
         values (:uid,'report_reply','رد جديد على بلاغك','ردّت إدارة المنصّة على بلاغك',
                 jsonb_build_object('report_id', :rid::text))",
        ['uid' => $report['user_id'], 'rid' => $id]);
      audit('reply', 'report', $id);
      flash('تم إرسال ردّك.');
    }
  } elseif ($act === 'edit_msg') {
    require_perm('reports', 'edit');
    $mid = (string) post('msg_id');
    $body = trim((string) post('body'));
    if ($body !== '') {
      q("update public.report_messages
            set original_body = coalesce(original_body, body),
                body = :b, edited_at = now()
          where id = :mid and report_id = :rid",
        ['b' => $body, 'mid' => $mid, 'rid' => $id]);
      audit('edit', 'report_message', $mid);
      flash('تم تعديل الرسالة (مع الإبقاء على الأصل للأطراف).');
    }
  } elseif ($act === 'hide' || $act === 'unhide') {
    require_perm('reports', 'edit');
    $mid = (string) post('msg_id');
    q("update public.report_messages set hidden=:h where id=:mid and report_id=:rid",
      ['h' => $act === 'hide' ? 'true' : 'false', 'mid' => $mid, 'rid' => $id]);
    audit($act, 'report_message', $mid);
    flash($act === 'hide' ? 'تم إخفاء الرسالة عن الطرفين.' : 'تمت إعادة إظهار الرسالة.');
  } elseif ($act === 'status') {
    require_perm('reports', 'edit');
    $s = (string) post('status');
    if (in_array($s, ['open', 'reviewing', 'resolved'], true)) {
      q("update public.reports set status=:s where id=:id", ['s' => $s, 'id' => $id]);
      audit('status', 'report', $id, ['status' => $s]);
      flash('تم تحديث حالة البلاغ.');
    }
  }
  redirect('report_view.php?id=' . urlencode($id));
}

$messages = all("select m.*, ms.role staff_role, ms.phone staff_phone,
    rm.sender_name reply_name, rm.body reply_body
  from public.report_messages m
  left join public.merchant_staff ms on ms.id = m.sender_staff_id
  left join public.report_messages rm on rm.id = m.reply_to_id
  where m.report_id=:id order by m.created_at", ['id' => $id]);

function role_label(string $r): string {
  return ['customer' => 'عميل', 'merchant' => 'المتجر', 'admin' => 'إدارة المنصّة'][$r] ?? $r;
}
function staff_label(?string $r): string {
  return ['merchant_owner' => 'المالك', 'manager' => 'مدير', 'branch_manager' => 'مدير فرع', 'cashier' => 'كاشير'][$r] ?? '';
}

$canReply = can('reports', 'reply');
$canEdit  = can('reports', 'edit');
$title = 'نزاع — ' . ($report['business_name'] ?: 'بلاغ عام');
require __DIR__ . '/partials/header.php';
?>
<a href="reports.php" class="text-sm text-gray-500 mb-3 inline-block">→ رجوع للبلاغات</a>

<div class="flex flex-wrap items-center gap-3 mb-4">
  <h2 class="text-xl font-extrabold">بلاغ #<?= e(substr($report['id'], 0, 8)) ?></h2>
  <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200">
    <?= e($report['uname'] ?: 'عميل') ?> · عميل · <?= e($report['uphone']) ?>
  </span>
  <?php if ($report['business_name']): ?>
    <span class="px-3 py-1.5 rounded-full text-sm font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
      <?= e($report['business_name']) ?> · المتجر
    </span>
  <?php endif; ?>
  <?php if ($report['subject_label']): ?>
    <span class="text-sm text-gray-600">🎫 عن: <b><?= e($report['subject_label']) ?></b></span>
  <?php endif; ?>
  <?php if ($canEdit): ?>
  <form method="post" class="flex items-center gap-2 mr-auto"><?= csrf_field() ?><input type="hidden" name="action" value="status">
    <select name="status" onchange="this.form.submit()" class="border rounded-lg px-3 py-1.5 text-sm bg-white">
      <?php foreach (['open' => 'مفتوح', 'reviewing' => 'قيد المراجعة', 'resolved' => 'محلول'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $report['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl border p-4 mb-4 space-y-3">
  <?php foreach ($messages as $m):
    $role = $m['sender_role']; $me = $role === 'admin'; $hidden = $m['hidden'];
    $name = $m['sender_name'] ?: role_label($role);
    $ident = $name . ' · ' . role_label($role);
    if ($role === 'merchant' && $m['staff_role']) $ident .= ' (' . staff_label($m['staff_role']) . ')';
    if ($role === 'customer' && $report['uphone']) $ident .= ' · ' . $report['uphone'];
    if ($role === 'merchant' && $m['staff_phone']) $ident .= ' · ' . $m['staff_phone'];
    $bubble = $me ? 'bg-amber-100 border-amber-200' : ($role === 'merchant' ? 'bg-emerald-50 border-emerald-100' : 'bg-gray-50 border-gray-200');
  ?>
    <div class="flex <?= $me ? 'justify-start' : 'justify-end' ?>">
      <div class="max-w-[70%] rounded-2xl border px-4 py-3 <?= $bubble ?> <?= $hidden ? 'opacity-60' : '' ?>">
        <div class="text-xs font-extrabold text-gray-600 mb-1"><?= e($ident) ?></div>
        <?php if ($hidden): ?>
          <div class="flex items-center gap-2 mb-2 text-xs font-bold text-red-600 bg-red-50 rounded-lg px-2 py-1">
            🚫 مخفية عن الطرفين — تظهر لك فقط
            <?php if ($canEdit): ?>
            <form method="post" class="mr-auto"><?= csrf_field() ?><input type="hidden" name="action" value="unhide"><input type="hidden" name="msg_id" value="<?= e($m['id']) ?>">
              <button class="bg-emerald-600 text-white rounded px-2 py-0.5">إلغاء الإخفاء</button></form>
          <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($m['reply_name']): ?>
          <div class="border-r-4 border-amber-400 bg-white/60 rounded px-2 py-1 mb-2 text-xs">
            <b><?= e($m['reply_name']) ?></b><br><span class="text-gray-500"><?= e(mb_substr((string)$m['reply_body'], 0, 80)) ?></span>
          </div>
        <?php endif; ?>
        <div class="text-gray-800 whitespace-pre-line"><?= nl2br(e($m['body'])) ?></div>
        <?php if (!empty($m['edited_at'])): ?>
          <div class="mt-1 text-[11px] text-gray-400">✏️ مُعدّلة<?php if (trim((string)$m['original_body']) !== ''): ?> — <span class="line-through">الأصل: <?= e(mb_substr((string)$m['original_body'], 0, 120)) ?></span><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($m['attachment_url']): ?>
          <a href="<?= e($m['attachment_url']) ?>" target="_blank" class="text-blue-600 text-sm mt-1 inline-block">📎 مرفق</a>
        <?php endif; ?>
        <div class="flex items-center gap-3 mt-1 text-[11px] text-gray-400">
          <span><?= dt($m['created_at']) ?></span>
          <?php if ($canEdit && !$hidden): ?>
            <form method="post" onsubmit="return confirm('إخفاء هذه الرسالة عن الطرفين؟')"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="msg_id" value="<?= e($m['id']) ?>">
              <button class="text-red-500 hover:underline">إخفاء</button></form>
          <?php endif; ?>
          <?php if ($canEdit): ?>
            <details class="inline"><summary class="text-blue-500 cursor-pointer hover:underline">تعديل</summary>
              <form method="post" class="mt-1 flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="edit_msg"><input type="hidden" name="msg_id" value="<?= e($m['id']) ?>">
                <input name="body" value="<?= e($m['body']) ?>" class="border rounded px-2 py-0.5 text-xs w-64">
                <button class="bg-gray-800 text-white rounded px-2 text-xs">حفظ</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$messages): ?><div class="text-center text-gray-400 py-6">لا رسائل بعد.</div><?php endif; ?>
</div>

<?php if ($canReply): ?>
<form method="post" class="bg-white rounded-xl border p-4">
  <?= csrf_field() ?><input type="hidden" name="action" value="reply">
  <textarea name="body" rows="3" required placeholder="اكتب ردًّا إداريًا للطرفين…"
    class="w-full border rounded-lg px-3 py-2 mb-2"></textarea>
  <div class="flex flex-wrap items-center gap-3">
    <input name="attachment_url" type="url" placeholder="رابط صورة مرفقة (اختياري — للأدمن فقط)"
      class="flex-1 min-w-[220px] border rounded-lg px-3 py-2 text-sm">
    <button class="bg-amber-500 text-white font-bold rounded-lg px-6 py-2">إرسال كردّ إداري</button>
  </div>
</form>
<?php else: ?>
  <div class="bg-white rounded-xl border p-4 text-center text-gray-400">لا تملك صلاحية الردّ.</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
