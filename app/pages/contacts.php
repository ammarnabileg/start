<?php
$err = null;
$z = zernio_or_null($err);
$accounts = [];
$acc = null;
$apiError = null;
if ($z) {
    try {
        $accounts = wa_accounts($z);
        $acc = wa_pick_account($accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$sub = (string)($_GET['sub'] ?? '');
if (!in_array($sub, ['', 'true', 'false'], true)) $sub = '';
$skip = max(0, (int)($_GET['skip'] ?? 0));
$back = url('contacts', ['q' => $q, 'tag' => $tag, 'sub' => $sub, 'skip' => $skip ?: null]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (string)($_POST['id'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = preg_replace('/[^0-9+]/', '', (string)($_POST['phone'] ?? ''));
            if ($name === '') {
                flash('error', t('الاسم مطلوب.'));
                redirect($back);
            }
            $data = [
                'name' => $name,
                'email' => trim((string)($_POST['email'] ?? '')),
                'company' => trim((string)($_POST['company'] ?? '')),
                'tags' => wa_split_tags((string)($_POST['tags'] ?? '')),
                'isSubscribed' => !empty($_POST['isSubscribed']),
                'notes' => trim((string)($_POST['notes'] ?? '')),
            ];
            if ($id !== '') {
                wa_update_contact($z, $id, $data);
                log_activity('contact.update', $id);
                flash('success', t('تم حفظ جهة الاتصال.'));
            } else {
                $data['profileId'] = current_profile_id($z);
                if ($phone !== '') {
                    if (!$acc) {
                        flash('error', t('مفيش رقم واتساب مربوط عشان نضيف الرقم.'));
                        redirect($back);
                    }
                    $data += ['accountId' => $acc, 'platform' => 'whatsapp', 'platformIdentifier' => ltrim($phone, '+'), 'displayIdentifier' => $phone];
                }
                $r = wa_create_contact($z, $data);
                log_activity('contact.create', $name);
                flash('success', t('تمت إضافة جهة الاتصال.'));
                if (!empty($r['warning'])) flash('error', (string)$r['warning']);
            }
        } elseif ($action === 'delete') {
            if (!can('whatsapp.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                $id = (string)($_POST['id'] ?? '');
                wa_delete_contact($z, $id);
                log_activity('contact.delete', $id);
                flash('success', t('تم حذف جهة الاتصال.'));
            }
        } elseif ($action === 'import') {
            $f = $_FILES['csv'] ?? null;
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2 * 1024 * 1024) {
                flash('error', t('ارفع ملف CSV (أقصى حجم 2 ميجا).'));
                redirect($back);
            }
            $extraTags = wa_split_tags((string)($_POST['tags'] ?? ''));
            $fh = fopen($f['tmp_name'], 'r');
            $head = null;
            $rows = [];
            $withPhone = false;
            while (($line = fgetcsv($fh)) !== false) {
                if ($head === null) {
                    $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$line[0]);
                    $head = array_map(function ($h) { return strtolower(trim((string)$h)); }, $line);
                    continue;
                }
                $r = [];
                foreach ($head as $i => $h) $r[$h] = trim((string)($line[$i] ?? ''));
                if (($r['name'] ?? '') === '') continue;
                $c = ['name' => $r['name']];
                foreach (['email', 'company'] as $k) if (!empty($r[$k])) $c[$k] = $r[$k];
                $tags = array_merge(wa_split_tags(str_replace(';', ',', $r['tags'] ?? '')), $extraTags);
                if ($tags) $c['tags'] = array_values(array_unique($tags));
                $ph = preg_replace('/[^0-9]/', '', $r['phone'] ?? '');
                if ($ph !== '') { $c['platformIdentifier'] = $ph; $c['displayIdentifier'] = '+' . $ph; $withPhone = true; }
                $rows[] = $c;
                if (count($rows) >= 1000) break;
            }
            fclose($fh);
            if (!$head || !in_array('name', $head, true)) {
                flash('error', t('أول سطر في الملف لازم يكون أسماء الأعمدة وفيه عمود name.'));
                redirect($back);
            }
            if (!$rows) {
                flash('error', t('الملف مفيهوش جهات اتصال.'));
                redirect($back);
            }
            $payload = ['profileId' => current_profile_id($z), 'contacts' => $rows];
            if ($withPhone) {
                if (!$acc) {
                    flash('error', t('مفيش رقم واتساب مربوط عشان نضيف الأرقام.'));
                    redirect($back);
                }
                $payload['accountId'] = $acc;
            }
            $r = wa_bulk_contacts($z, $payload);
            log_activity('contact.import', (string)count($rows));
            flash('success', t('الاستيراد خلص: اتضاف %s، واتساب %s، من %s.', (int)($r['created'] ?? 0), (int)($r['skipped'] ?? 0), (int)($r['total'] ?? count($rows))));
            if (!empty($r['errors'])) flash('error', implode(' · ', array_slice((array)$r['errors'], 0, 5)));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$contacts = [];
$allTags = [];
$total = 0;
$hasMore = false;
$edit = null;
$limit = 50;
if ($z && !$apiError) {
    try {
        $r = wa_contacts($z, ['search' => $q ?: null, 'tag' => $tag ?: null, 'isSubscribed' => $sub ?: null, 'limit' => $limit, 'skip' => $skip]);
        $contacts = $r['contacts'] ?? [];
        $allTags = $r['filters']['tags'] ?? [];
        $total = (int)($r['pagination']['total'] ?? count($contacts));
        $hasMore = !empty($r['pagination']['hasMore']);
        if (!empty($_GET['edit'])) {
            $edit = wa_contact($z, (string)$_GET['edit']);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
$showForm = $edit || !empty($_GET['new']);

render(t('جهات الاتصال'), 'contacts', function () use ($z, $accounts, $acc, $contacts, $allTags, $total, $hasMore, $skip, $limit, $q, $tag, $sub, $edit, $showForm, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    wa_account_picker($accounts, $acc, 'contacts');
    $tagLinks = function ($tags) use ($q, $sub) {
        $h = '';
        foreach ((array)$tags as $t) $h .= '<a href="' . e(url('contacts', ['tag' => $t, 'q' => $q, 'sub' => $sub])) . '" class="badge hover:bg-ink-200">' . e($t) . '</a> ';
        return $h;
    };
    ?>
    <div data-ai-insights="segments,contacts_clean" class="mb-6"></div>
    <div class="flex flex-wrap items-end gap-3 mb-4">
      <form method="get" class="flex flex-wrap items-end gap-2 flex-1">
        <input type="hidden" name="p" value="contacts">
        <input name="q" value="<?= e($q) ?>" class="input !mt-0 !w-56" placeholder="<?= e(t('بحث بالاسم أو الإيميل أو الشركة')) ?>">
        <select name="tag" class="input !mt-0 !w-auto">
          <option value=""><?= e(t('كل التاجات')) ?></option>
          <?php foreach ($allTags as $t): ?><option value="<?= e($t) ?>" <?= $t === $tag ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <select name="sub" class="input !mt-0 !w-auto">
          <option value=""><?= e(t('الكل')) ?></option>
          <option value="true" <?= $sub === 'true' ? 'selected' : '' ?>><?= e(t('موافق على الرسائل')) ?></option>
          <option value="false" <?= $sub === 'false' ? 'selected' : '' ?>><?= e(t('ألغى الاشتراك')) ?></option>
        </select>
        <button class="btn-ghost"><?= e(t('بحث')) ?></button>
      </form>
      <a href="<?= e(url('contacts', ['new' => 1])) ?>" class="btn-primary"><?= e(t('+ جهة اتصال')) ?></a>
      <button type="button" class="btn-ghost" onclick="document.getElementById('importBox').classList.toggle('hidden')"><?= e(t('استيراد CSV')) ?></button>
    </div>

    <form id="importBox" method="post" enctype="multipart/form-data" class="card p-5 mb-4 space-y-3 hidden">
      <?= csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="wa" value="<?= e((string)$acc) ?>">
      <h2 class="font-semibold"><?= e(t('استيراد جهات اتصال من ملف CSV')) ?></h2>
      <p class="text-sm text-ink-500"><?= e(t('أول سطر: أسماء الأعمدة. الأعمدة المدعومة: name (مطلوب)، phone (بالكود الدولي)، email، company، tags (مفصولة بـ ;). أقصى عدد 1000 في المرة.')) ?></p>
      <pre class="text-xs bg-ink-50 border border-ink-200 rounded-xl p-3" dir="ltr">name,phone,email,company,tags
Ahmed Ali,201001234567,ahmed@example.com,ACME,vip;cairo</pre>
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="label"><?= e(t('الملف')) ?></span><input type="file" name="csv" accept=".csv,text/csv" required class="input"></label>
        <label class="block"><span class="label"><?= e(t('تاجات تتضاف للكل (اختياري)')) ?></span><input name="tags" class="input" placeholder="<?= e(t('مثال: معرض 2026')) ?>"></label>
      </div>
      <p class="text-xs text-ink-500"><?= e(t('ضيف بس الناس اللي وافقوا يستقبلوا رسايل منك على واتساب.')) ?></p>
      <button class="btn-primary"><?= e(t('استيراد')) ?></button>
    </form>

    <?php if ($showForm): $c = $edit ?: []; ?>
    <form method="post" class="card p-5 mb-4 space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($c['id'] ?? '') ?>"><input type="hidden" name="wa" value="<?= e((string)$acc) ?>">
      <div class="flex justify-between"><h2 class="font-semibold"><?= e($edit ? t('تعديل جهة اتصال') : t('جهة اتصال جديدة')) ?></h2><a href="<?= e(url('contacts')) ?>" class="text-sm text-ink-500"><?= e(t('إلغاء')) ?></a></div>
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="label"><?= e(t('الاسم')) ?></span><input name="name" required class="input" value="<?= e($c['name'] ?? '') ?>"></label>
        <?php if (!$edit): ?>
        <label class="block"><span class="label"><?= e(t('رقم الواتساب (بالكود الدولي)')) ?></span><input name="phone" dir="ltr" class="input" placeholder="+201001234567"></label>
        <?php endif; ?>
        <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input name="email" type="email" dir="ltr" class="input" value="<?= e($c['email'] ?? '') ?>"></label>
        <label class="block"><span class="label"><?= e(t('الشركة')) ?></span><input name="company" class="input" value="<?= e($c['company'] ?? '') ?>"></label>
        <label class="block"><span class="label"><?= e(t('التاجات (مفصولة بفاصلة)')) ?></span><input name="tags" class="input" value="<?= e(implode(', ', (array)($c['tags'] ?? []))) ?>"></label>
        <label class="block"><span class="label"><?= e(t('ملاحظات')) ?></span><input name="notes" class="input" value="<?= e($c['notes'] ?? '') ?>"></label>
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="isSubscribed" value="1" <?= ($c['isSubscribed'] ?? true) ? 'checked' : '' ?>> <?= e(t('وافق يستقبل رسايل (Opt-in)')) ?></label>
      <button class="btn-primary"><?= e(t('حفظ')) ?></button>
    </form>
    <?php endif; ?>

    <div class="card overflow-x-auto">
      <?php if (!$contacts): ?>
        <p class="p-6 text-sm text-ink-500"><?= e(t('مفيش جهات اتصال.')) ?></p>
      <?php else: ?>
      <table class="w-full text-sm">
        <thead class="text-ink-500 text-xs border-b border-ink-200"><tr>
          <th class="text-start p-3"><?= e(t('الاسم')) ?></th><th class="text-start p-3"><?= e(t('الرقم / القناة')) ?></th>
          <th class="text-start p-3"><?= e(t('التاجات')) ?></th><th class="text-start p-3"><?= e(t('الاشتراك')) ?></th>
          <th class="text-start p-3"><?= e(t('آخر رسالة')) ?></th><th class="p-3"></th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $c): $last = max((string)($c['lastMessageSentAt'] ?? ''), (string)($c['lastMessageReceivedAt'] ?? '')); ?>
          <tr class="border-b border-ink-100 last:border-0">
            <td class="p-3"><div class="flex items-center gap-2"><?= avatar($c['avatarUrl'] ?? null, $c['name'] ?? '', 'w-8 h-8') ?><div class="min-w-0"><div class="font-medium truncate"><?= e($c['name'] ?? '') ?></div><div class="text-xs text-ink-500 truncate"><?= e(trim(($c['email'] ?? '') . ' ' . ($c['company'] ?? ''))) ?></div></div></div></td>
            <td class="p-3 text-xs"><span dir="ltr"><?= e($c['displayIdentifier'] ?? $c['platformIdentifier'] ?? '') ?></span> <?php if (!empty($c['platform'])): ?><span class="text-ink-500"><?= e(platform_label((string)$c['platform'])) ?></span><?php endif; ?></td>
            <td class="p-3"><?= $tagLinks($c['tags'] ?? []) ?></td>
            <td class="p-3"><?php if (!empty($c['isBlocked'])): ?><span class="badge bg-red-100 text-red-700"><?= e(t('محظور')) ?></span><?php elseif (!empty($c['isSubscribed'])): ?><span class="badge badge-green"><?= e(t('مشترك')) ?></span><?php else: ?><span class="badge"><?= e(t('غير مشترك')) ?></span><?php endif; ?></td>
            <td class="p-3 text-xs text-ink-500 whitespace-nowrap"><?= $last ? e(fmt_date($last, 'd/m h:i A')) : '—' ?></td>
            <td class="p-3 text-end whitespace-nowrap">
              <a class="link text-xs" href="<?= e(url('contacts', ['edit' => $c['id'], 'q' => $q, 'tag' => $tag])) ?>"><?= e(t('تعديل')) ?></a>
              <?php if (can('whatsapp.manage')): ?>
              <form method="post" class="inline ms-2" onsubmit="return confirm('<?= tjs('حذف جهة الاتصال دي نهائياً؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><button class="text-xs text-red-600"><?= e(t('حذف')) ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="flex items-center justify-between mt-3 text-sm text-ink-500">
      <span><?= e(t('الإجمالي: %s', $total)) ?></span>
      <div class="flex gap-3">
        <?php if ($skip > 0): ?><a class="link" href="<?= e(url('contacts', ['q' => $q, 'tag' => $tag, 'sub' => $sub, 'skip' => max(0, $skip - $limit)])) ?>"><?= e(t('السابق')) ?></a><?php endif; ?>
        <?php if ($hasMore): ?><a class="link" href="<?= e(url('contacts', ['q' => $q, 'tag' => $tag, 'sub' => $sub, 'skip' => $skip + $limit])) ?>"><?= e(t('التالي')) ?></a><?php endif; ?>
      </div>
    </div>
    <?php
});
