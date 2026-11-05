<?php
/**
 * واتساب بيزنس: القوالب، جهات الاتصال، الرسائل الجماعية (Broadcasts)، السلاسل (Sequences)، وبروفايل الرقم.
 * كل الدوال بتاخد Zernio $z وبتنادي $z->request مباشرة.
 */

/* ---------- حسابات واتساب ---------- */

/** كل حسابات واتساب المربوطة في الـ Profile الحالي (ومعاها رقم Zernio التجريبي Sandbox لو مفعل، في الآخر) */
function wa_accounts(Zernio $z): array
{
    $r = $z->request('GET', '/v1/accounts', ['profileId' => zernio_profile(), 'platform' => 'whatsapp', 'includeSandbox' => 'true']);
    $real = [];
    $sandbox = [];
    foreach ($r['accounts'] ?? [] as $a) {
        if (($a['platform'] ?? '') !== 'whatsapp') continue;
        if (!empty($a['metadata']['isSandbox'])) $sandbox[] = $a; else $real[] = $a;
    }
    return array_merge($real, $sandbox);
}

/** يختار حساب واتساب: من ?wa= أو آخر اختيار في الجلسة أو أول حساب */
function wa_pick_account(array $accounts): ?string
{
    if (!$accounts) {
        return null;
    }
    $ids = array_map(function ($a) { return (string)($a['_id'] ?? ''); }, $accounts);
    $want = (string)($_GET['wa'] ?? $_POST['wa'] ?? ($_SESSION['wa_account'] ?? ''));
    $id = in_array($want, $ids, true) ? $want : $ids[0];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['wa_account'] = $id;
    }
    return $id;
}

function wa_account_label(array $a): string
{
    $n = trim((string)($a['displayName'] ?? $a['username'] ?? ''));
    if (!empty($a['metadata']['isSandbox'])) $n = t('رقم تجريبي'); // من غير اسم مزود الخدمة
    $phone = (string)($a['metadata']['displayPhoneNumber'] ?? $a['phoneNumber'] ?? '');
    $label = $phone && strpos($n, $phone) === false ? trim($n . ' · ' . $phone, ' ·') : ($n ?: (string)($a['_id'] ?? ''));
    return !empty($a['metadata']['isSandbox']) ? t('%s (تجريبي)', $label) : $label;
}

/** قائمة اختيار الحساب لو فيه أكتر من رقم واتساب */
function wa_account_picker(array $accounts, ?string $current, string $page): void
{
    if (!$accounts || (count($accounts) < 2 && empty($accounts[0]['metadata']['isSandbox']))) {
        return;
    }
    ?>
    <form method="get" class="flex items-center gap-2 mb-4 text-sm">
      <input type="hidden" name="p" value="<?= e($page) ?>">
      <span class="label"><?= e(t('رقم الواتساب:')) ?></span>
      <select name="wa" class="input !mt-0 !w-auto" onchange="this.form.submit()">
        <?php foreach ($accounts as $a): ?>
          <option value="<?= e($a['_id']) ?>" <?= $a['_id'] === $current ? 'selected' : '' ?>><?= e(wa_account_label($a)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php
}

/** صندوق: مفيش رقم واتساب مربوط */
function wa_no_account_box(): void
{
    echo '<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5 text-sm">';
    echo '<p class="font-bold mb-1">' . e(t('مفيش رقم واتساب بيزنس مربوط')) . '</p>';
    echo '<a class="underline font-semibold" href="' . e(url('accounts')) . '">' . e(t('اربط رقم واتساب من صفحة الحسابات.')) . '</a>';
    echo '</div>';
}

/* ---------- القوالب ---------- */

function wa_templates(Zernio $z, string $accountId, ?string $status = null): array
{
    return $z->request('GET', '/v1/whatsapp/templates', ['accountId' => $accountId, 'status' => $status])['templates'] ?? [];
}

function wa_create_template(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/whatsapp/templates', [], $payload);
}

function wa_delete_template(Zernio $z, string $accountId, string $name, ?string $language = null): void
{
    $z->request('DELETE', '/v1/whatsapp/templates/' . rawurlencode($name), ['accountId' => $accountId, 'language' => $language]);
}

/** يجيب مكوّن من القالب (header/body/footer/buttons) بغض النظر عن حالة الحروف */
function wa_component(array $tpl, string $type): ?array
{
    foreach ($tpl['components'] ?? [] as $c) {
        if (strtolower((string)($c['type'] ?? '')) === $type) {
            return $c;
        }
    }
    return null;
}

/** أرقام المتغيرات {{1}} {{2}} أو أسماؤها في نص */
function wa_vars_in(string $text): array
{
    preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $m);
    return array_values(array_unique($m[1]));
}

/** عدد متغيرات البودي في قالب (المتغيرات الموضعية بس، دي اللي بنعملها mapping) */
function wa_template_var_keys(array $tpl): array
{
    $body = wa_component($tpl, 'body');
    return wa_vars_in((string)($body['text'] ?? ''));
}

/** شارة لحالة القالب */
function wa_template_status_badge(string $s): string
{
    $s = strtoupper($s);
    $map = [
        'APPROVED' => [t('مقبول'), 'badge badge-green'],
        'PENDING' => [t('مستني موافقة Meta'), 'badge bg-amber-100 text-amber-800'],
        'REJECTED' => [t('مرفوض'), 'badge bg-red-100 text-red-700'],
        'PAUSED' => [t('متوقف'), 'badge bg-amber-100 text-amber-800'],
        'DISABLED' => [t('معطل'), 'badge bg-red-100 text-red-700'],
        'IN_APPEAL' => [t('في استئناف'), 'badge'],
        'PENDING_DELETION' => [t('بيتمسح'), 'badge'],
    ];
    [$l, $c] = $map[$s] ?? [$s, 'badge'];
    return '<span class="' . $c . '">' . e($l) . '</span>';
}

function wa_category_label(string $c): string
{
    $c = strtoupper($c);
    $m = ['MARKETING' => t('تسويق'), 'UTILITY' => t('خدمي'), 'AUTHENTICATION' => t('تحقق (OTP)')];
    return $m[$c] ?? $c;
}

/** القوالب المقبولة كخيارات <option> فيها عدد المتغيرات ونص البودي (لـ JavaScript) */
function wa_template_options(array $templates, string $selected = ''): string
{
    $html = '<option value="">' . e(t('— اختار قالب —')) . '</option>';
    foreach ($templates as $tpl) {
        if (strtoupper((string)($tpl['status'] ?? '')) !== 'APPROVED') {
            continue;
        }
        $val = ($tpl['name'] ?? '') . '|' . ($tpl['language'] ?? '');
        $body = (string)(wa_component($tpl, 'body')['text'] ?? '');
        $html .= '<option value="' . e($val) . '" data-vars="' . e(implode(',', wa_template_var_keys($tpl))) . '" data-body="' . e($body) . '"' . ($val === $selected ? ' selected' : '') . '>'
            . e(($tpl['name'] ?? '') . ' (' . ($tpl['language'] ?? '') . ')') . '</option>';
    }
    return $html;
}

/** حقول ربط المتغيرات (تتعرض حسب القالب المختار بالـ JavaScript في wa_mapping_script) */
function wa_mapping_fields(string $prefix, int $max = 8): string
{
    $fields = ['name' => t('اسم جهة الاتصال'), 'phone' => t('رقم التليفون'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'custom' => t('قيمة ثابتة')];
    $h = '<div class="wa-map space-y-2 mt-2" data-prefix="' . e($prefix) . '">';
    $h .= '<p class="wa-preview hidden text-xs bg-lime-50 border border-lime-200 rounded-xl p-3 whitespace-pre-line"></p>';
    for ($i = 0; $i < $max; $i++) {
        $h .= '<div class="wa-map-row hidden flex flex-wrap items-center gap-2 text-sm" data-i="' . $i . '">'
            . '<input type="hidden" name="' . e($prefix) . '[' . $i . '][key]" value="">'
            . '<span class="wa-key badge font-mono"></span>'
            . '<select name="' . e($prefix) . '[' . $i . '][field]" class="input !mt-0 !w-auto wa-field">';
        foreach ($fields as $k => $l) {
            $h .= '<option value="' . $k . '">' . e($l) . '</option>';
        }
        $h .= '</select><input name="' . e($prefix) . '[' . $i . '][value]" class="input !mt-0 flex-1 min-w-[10rem] wa-custom hidden" placeholder="' . e(t('القيمة')) . '"></div>';
    }
    return $h . '</div>';
}

/** يحول حقول الـ mapping المبعوتة لـ variableMapping */
function wa_mapping_from_post($rows): array
{
    $out = [];
    foreach ((array)$rows as $r) {
        $k = trim((string)($r['key'] ?? ''));
        if ($k === '') continue;
        $f = (string)($r['field'] ?? 'name');
        if (!in_array($f, ['name', 'phone', 'email', 'company', 'custom'], true)) $f = 'name';
        $m = ['field' => $f];
        if ($f === 'custom') $m['customValue'] = (string)($r['value'] ?? '');
        $out[$k] = $m;
    }
    return $out;
}

/** JavaScript: لما تختار قالب يظهر المعاينة وصفوف المتغيرات */
function wa_mapping_script(): string
{
    return <<<'JS'
<script>
(function () {
  function sync(sel) {
    var box = sel.closest('[data-wa-step]') || document;
    var map = box.querySelector('.wa-map'); if (!map) return;
    var opt = sel.options[sel.selectedIndex];
    var vars = opt && opt.dataset.vars ? opt.dataset.vars.split(',').filter(Boolean) : [];
    var pv = map.querySelector('.wa-preview');
    if (opt && opt.dataset.body) { pv.textContent = opt.dataset.body; pv.classList.remove('hidden'); } else { pv.classList.add('hidden'); }
    map.querySelectorAll('.wa-map-row').forEach(function (row) {
      var i = +row.dataset.i, k = vars[i];
      row.classList.toggle('hidden', k === undefined);
      row.querySelector('input[type=hidden]').value = k === undefined ? '' : k;
      row.querySelector('.wa-key').textContent = k === undefined ? '' : '{{' + k + '}}';
    });
  }
  document.querySelectorAll('select.wa-tpl').forEach(function (s) { s.addEventListener('change', function () { sync(s); }); sync(s); });
  document.addEventListener('change', function (ev) {
    if (ev.target.classList.contains('wa-field')) {
      ev.target.parentNode.querySelector('.wa-custom').classList.toggle('hidden', ev.target.value !== 'custom');
    }
  });
})();
</script>
JS;
}

/* ---------- جهات الاتصال ---------- */

function wa_contacts(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/contacts', $filters + ['profileId' => zernio_profile()]);
}

function wa_contact(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/contacts/' . rawurlencode($id))['contact'] ?? [];
}

function wa_create_contact(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/contacts', [], $payload);
}

function wa_update_contact(Zernio $z, string $id, array $payload): array
{
    return $z->request('PATCH', '/v1/contacts/' . rawurlencode($id), [], $payload);
}

function wa_delete_contact(Zernio $z, string $id): void
{
    $z->request('DELETE', '/v1/contacts/' . rawurlencode($id));
}

function wa_bulk_contacts(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/contacts/bulk', [], $payload);
}

/** "a, b ,c" => ['a','b','c'] */
function wa_split_tags(string $s): array
{
    return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،]/u', $s)), 'strlen')));
}

/* ---------- الرسائل الجماعية ---------- */

function wa_broadcasts(Zernio $z, array $filters = []): array
{
    return $z->request('GET', '/v1/broadcasts', $filters + ['profileId' => zernio_profile(), 'platform' => 'whatsapp']);
}

function wa_broadcast(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/broadcasts/' . rawurlencode($id))['broadcast'] ?? [];
}

function wa_broadcast_recipients(Zernio $z, string $id, int $limit = 50): array
{
    return $z->request('GET', '/v1/broadcasts/' . rawurlencode($id) . '/recipients', ['limit' => $limit]);
}

function wa_create_broadcast(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/broadcasts', [], $payload)['broadcast'] ?? [];
}

function wa_add_broadcast_recipients(Zernio $z, string $id, array $payload): array
{
    return $z->request('POST', '/v1/broadcasts/' . rawurlencode($id) . '/recipients', [], $payload);
}

function wa_broadcast_action(Zernio $z, string $id, string $action, ?array $body = null): array
{
    // action: send | schedule | cancel
    return $z->request('POST', '/v1/broadcasts/' . rawurlencode($id) . '/' . $action, [], $body);
}

function wa_delete_broadcast(Zernio $z, string $id): void
{
    $z->request('DELETE', '/v1/broadcasts/' . rawurlencode($id));
}

function wa_broadcast_status_badge(string $s): string
{
    $map = [
        'draft' => [t('مسودة'), 'badge'],
        'scheduled' => [t('مجدول'), 'badge bg-brand-100 text-brand-700'],
        'sending' => [t('بيتبعت'), 'badge bg-amber-100 text-amber-800'],
        'completed' => [t('اتبعت'), 'badge badge-green'],
        'failed' => [t('فشل'), 'badge bg-red-100 text-red-700'],
        'cancelled' => [t('اتلغى'), 'badge'],
    ];
    [$l, $c] = $map[$s] ?? [$s, 'badge'];
    return '<span class="' . $c . '">' . e($l) . '</span>';
}

/* ---------- السلاسل ---------- */

function wa_sequences(Zernio $z, array $filters = []): array
{
    return $z->request('GET', '/v1/sequences', $filters + ['profileId' => zernio_profile()]);
}

function wa_sequence(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/sequences/' . rawurlencode($id))['sequence'] ?? [];
}

function wa_create_sequence(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/sequences', [], $payload)['sequence'] ?? [];
}

function wa_sequence_action(Zernio $z, string $id, string $action): void
{
    // activate | pause
    $z->request('POST', '/v1/sequences/' . rawurlencode($id) . '/' . $action);
}

function wa_delete_sequence(Zernio $z, string $id): void
{
    $z->request('DELETE', '/v1/sequences/' . rawurlencode($id));
}

function wa_enroll(Zernio $z, string $id, array $contactIds): array
{
    return $z->request('POST', '/v1/sequences/' . rawurlencode($id) . '/enroll', [], ['contactIds' => array_values($contactIds)]);
}

function wa_unenroll(Zernio $z, string $id, string $contactId): void
{
    $z->request('DELETE', '/v1/sequences/' . rawurlencode($id) . '/enroll/' . rawurlencode($contactId));
}

function wa_enrollments(Zernio $z, string $id, int $limit = 50): array
{
    return $z->request('GET', '/v1/sequences/' . rawurlencode($id) . '/enrollments', ['limit' => $limit])['enrollments'] ?? [];
}

/** دقائق => نص مقروء (يوم/ساعة/دقيقة) */
function wa_delay_label(int $min): string
{
    if ($min <= 0) return t('فوراً');
    if ($min % 1440 === 0) return t('بعد %s يوم', $min / 1440);
    if ($min % 60 === 0) return t('بعد %s ساعة', $min / 60);
    return t('بعد %s دقيقة', $min);
}

/* ---------- بروفايل الرقم ---------- */

function wa_business_profile(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/whatsapp/business-profile', ['accountId' => $accountId])['businessProfile'] ?? [];
}

function wa_update_business_profile(Zernio $z, array $payload): void
{
    $z->request('POST', '/v1/whatsapp/business-profile', [], $payload);
}

function wa_profile_photo_url(Zernio $z, string $accountId, string $url): void
{
    $z->request('POST', '/v1/whatsapp/business-profile/photo', [], ['accountId' => $accountId, 'url' => $url]);
}

/** تسجيل الرقم على WhatsApp Cloud API (بيصلح خطأ 133010 Account not registered) */
function wa_register_number(Zernio $z, string $accountId, string $pin): array
{
    return $z->request('POST', '/v1/accounts/' . rawurlencode($accountId) . '/whatsapp/register', [], ['pin' => $pin]);
}

function wa_number_info(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/whatsapp/number-info', ['accountId' => $accountId]);
}

/** هل الحساب المختار هو رقم Zernio التجريبي المشترك؟ (ممنوع عليه التعديل والحذف) */
function wa_is_sandbox(array $accounts, ?string $acc): bool
{
    foreach ($accounts as $a) {
        if (($a['_id'] ?? '') === $acc) return !empty($a['metadata']['isSandbox']);
    }
    return false;
}

function wa_sandbox_note(): void
{
    echo '<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-3 text-sm mb-4">' . e(t('ده الرقم التجريبي المشترك (Sandbox). تقدر تتفرج بس، والتعديل والحذف مقفولين. اربط رقمك الحقيقي من صفحة الحسابات.')) . '</div>';
}
