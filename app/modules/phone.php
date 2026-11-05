<?php
/**
 * أرقام التليفون، المكالمات، والـ SMS (Zernio: Phone Numbers / SMS / Voice / Calls / Branded Calling / iMessage / RCS).
 * كل الدوال بتاخد Zernio $z. الكتابة (شراء/إلغاء رقم، إرسال SMS، مكالمة) بتكلف فلوس، فالصفحات بتحميها.
 */

/* ---------- الأرقام ---------- */

/** كل أرقام الشركة: ['numbers'=>[], 'connected'=>[], 'imessage'=>[], 'sandbox'=>?] */
function ph_numbers(Zernio $z): array
{
    return $z->request('GET', '/v1/phone-numbers', ['profileId' => zernio_profile()]);
}

function ph_number(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/phone-numbers/' . rawurlencode($id));
}

/** الدول المتاحة للشراء ومعاها السعر الشهري (بالسنت) لكل نوع */
function ph_countries(Zernio $z): array
{
    return $z->request('GET', '/v1/phone-numbers/countries')['countries'] ?? [];
}

function ph_available(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/phone-numbers/available', $q);
}

/** شراء رقم (بيكلف فلوس شهرياً) */
function ph_purchase(Zernio $z, array $body): array
{
    return $z->request('POST', '/v1/phone-numbers/purchase', [], $body);
}

/** إلغاء رقم نهائياً (مالوش رجوع) */
function ph_release(Zernio $z, string $id): array
{
    return $z->request('DELETE', '/v1/phone-numbers/' . rawurlencode($id));
}

/** الأسباب اللي المراجع رفض بيها الرقم (للأرقام المرفوضة) */
function ph_remediation(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/phone-numbers/' . rawurlencode($id) . '/remediate');
}

/* ---------- SMS ---------- */

function ph_send_sms(Zernio $z, string $from, string $to, string $text): array
{
    return $z->request('POST', '/v1/sms/messages', [], ['from' => $from, 'to' => $to, 'text' => $text]);
}

function ph_registrations(Zernio $z): array
{
    return $z->request('GET', '/v1/sms/registrations')['registrations'] ?? [];
}

function ph_opt_outs(Zernio $z, int $limit = 200): array
{
    return $z->request('GET', '/v1/sms/opt-outs', ['limit' => $limit]);
}

/** محادثات SMS / iMessage من صندوق الرسائل الموحد */
function ph_conversations(Zernio $z, string $platform, int $limit = 30): array
{
    return $z->request('GET', '/v1/inbox/conversations', ['profileId' => zernio_profile(), 'platform' => $platform, 'limit' => $limit])['data'] ?? [];
}

function ph_rcs_agents(Zernio $z): array
{
    return $z->request('GET', '/v1/rcs/agents')['agents'] ?? [];
}

/* ---------- المكالمات ---------- */

function ph_calls(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/calls', $q);
}

function ph_call(Zernio $z, string $id): array
{
    $r = $z->request('GET', '/v1/calls/' . rawurlencode($id));
    return $r['call'] ?? $r;
}

function ph_call_recording(Zernio $z, string $id): string
{
    return (string)($z->request('GET', '/v1/calls/' . rawurlencode($id) . '/recording', ['as' => 'json'])['url'] ?? '');
}

function ph_call_estimate(Zernio $z, string $to, string $from = ''): array
{
    return $z->request('GET', '/v1/voice/calls/estimate', ['to' => $to, 'from' => $from, 'minutes' => 1]);
}

/** مكالمة صادرة (بتكلف فلوس) */
function ph_place_call(Zernio $z, array $body): array
{
    return $z->request('POST', '/v1/voice/calls', [], $body);
}

/** تفعيل المكالمات على رقم وتحديث إعداداتها (التحويل، البريد الصوتي) */
function ph_voice_settings(Zernio $z, string $id, array $body): array
{
    return $z->request('POST', '/v1/phone-numbers/' . rawurlencode($id) . '/voice', [], $body);
}

function ph_branded_identities(Zernio $z): array
{
    return $z->request('GET', '/v1/branded-calling/identities')['identities'] ?? [];
}

/* ---------- مساعدات العرض ---------- */

/** يحول رقم لصيغة E.164 (+ وأرقام بس)، أو '' لو مش صالح */
function ph_e164(string $s): string
{
    $s = trim($s);
    if (strpos($s, '00') === 0) $s = '+' . substr($s, 2);
    $d = preg_replace('/\D/', '', $s);
    if ($s === '' || $s[0] !== '+' || strlen($d) < 7 || strlen($d) > 15) return '';
    return '+' . $d;
}

function ph_money_cents($cents): string
{
    return '$' . number_format(((int)$cents) / 100, 2);
}

function ph_duration(int $sec): string
{
    return sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
}

function ph_status_badge(string $s): string
{
    $map = [
        'active' => ['badge-green', t('شغال')],
        'provisioning' => ['bg-amber-100 text-amber-800', t('بيتجهز')],
        'verifying' => ['bg-amber-100 text-amber-800', t('بيتأكد')],
        'pending_payment' => ['bg-amber-100 text-amber-800', t('مستني الدفع')],
        'pending_regulatory' => ['bg-amber-100 text-amber-800', t('تحت المراجعة القانونية')],
        'regulatory_declined' => ['bg-red-100 text-red-800', t('المراجعة رفضته')],
        'suspended' => ['bg-red-100 text-red-800', t('متوقف')],
        'releasing' => ['bg-ink-100 text-ink-700', t('بيتلغي')],
        'released' => ['bg-ink-100 text-ink-700', t('اتلغى')],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-ink-100 text-ink-700', $s];
    return '<span class="badge ' . e($cls) . '">' . e($label) . '</span>';
}

function ph_reg_status_badge(string $s): string
{
    $map = [
        'approved' => ['badge-green', t('مقبول')],
        'pending' => ['bg-amber-100 text-amber-800', t('تحت المراجعة')],
        'requested' => ['bg-amber-100 text-amber-800', t('تحت المراجعة')],
        'changes_requested' => ['bg-amber-100 text-amber-800', t('مطلوب تعديلات')],
        'rejected' => ['bg-red-100 text-red-800', t('مرفوض')],
        'deactivated' => ['bg-ink-100 text-ink-700', t('متوقف')],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-ink-100 text-ink-700', $s];
    return '<span class="badge ' . e($cls) . '">' . e($label) . '</span>';
}

/** خطوة جاية بكلام بسيط لرقم حسب حالته */
function ph_next_step(array $n): string
{
    $s = (string)($n['status'] ?? '');
    if ($s === 'regulatory_declined') return t('المراجعة رفضت بيانات الرقم. صحح الحاجات المرفوضة وابعتها تاني من غير ما تدفع تاني.');
    if (($n['regulatoryInfoStatus'] ?? '') === 'action_required') return t('المراجع طالب تصحيح في البيانات. صححها من الزرار اللي جنب الرسالة.');
    if ($s === 'pending_regulatory') return (!empty($n['verifyUrl']) || !empty($n['onfidoVerificationUrl'])) ? t('لازم صاحب الرقم يأكد هويته من لينك التحقق.') : t('الرقم تحت المراجعة. مش محتاج تعمل حاجة دلوقتي.');
    if ($s === 'pending_payment') return t('ضيف طريقة دفع في حساب التكامل عشان الرقم يتفعل.');
    if ($s === 'suspended') return t('الرقم متوقف. كلم الدعم الفني.');
    if ($s === 'active' && !empty($n['smsRequested']) && empty($n['smsSendApproved'])) {
        return !empty($n['smsRegistrationPending']) ? t('تسجيل الـ SMS تحت المراجعة عند شركات المحمول.') : t('الرسايل لأرقام أمريكا محتاجة تسجيل 10DLC الأول (من لوحة التكامل).');
    }
    return '';
}

/** أرقام الشركة اللي تقدر تبعت SMS */
function ph_sms_numbers(array $numbers): array
{
    return array_values(array_filter($numbers, function ($n) {
        return ($n['status'] ?? '') === 'active' && (!empty($n['smsCapable']) || !empty($n['smsRequested']));
    }));
}

/** أرقام الشركة اللي عليها مكالمات عادية */
function ph_voice_numbers(array $numbers): array
{
    return array_values(array_filter($numbers, function ($n) {
        return ($n['status'] ?? '') === 'active' && !empty($n['pstnVoiceEnabled']);
    }));
}

/** رسالة: مفيش أرقام لسه */
function ph_empty_numbers(): void
{
    ?>
    <div class="card p-8 text-center">
      <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-ink-100 text-ink-500 flex items-center justify-center"><?= ui_icon('phone', 'w-7 h-7') ?></div>
      <p class="font-semibold mb-1"><?= e(t('الشركة لسه معندهاش أرقام تليفون')) ?></p>
      <p class="text-sm text-ink-500 mb-4"><?= e(t('اشتري رقم عشان تستقبل وتعمل مكالمات وتبعت رسايل SMS من اسم شركتك.')) ?></p>
      <a class="btn-primary" href="<?= e(url('phone_numbers', ['buy' => 1])) ?>"><?= e(t('دور على رقم واشتريه')) ?></a>
    </div>
    <?php
}

/* ---------- KYC من جوه المنصة ---------- */

/** مواصفات فورم بيانات الهوية لدولة/نوع (حقول text/date/address/file/action) */
function ph_kyc_spec(Zernio $z, string $country, string $type = ''): array
{
    return $z->request('GET', '/v1/phone-numbers/kyc', array_filter(['country' => $country, 'numberType' => $type]));
}

/** يبعت بيانات الهوية ويطلب الرقم (بيتحسب) */
function ph_kyc_submit(Zernio $z, array $body): array
{
    return $z->request('POST', '/v1/phone-numbers/kyc', [], $body);
}

/** يبعت التصحيحات لرقم المراجعة رفضته (نفس الطلب، مش بيتحسب تاني) */
function ph_remediate_submit(Zernio $z, string $id, array $body): array
{
    return $z->request('POST', '/v1/phone-numbers/' . rawurlencode($id) . '/remediate', [], $body);
}

/** علم الدولة كإيموجي (بيترسم على ويندوز بخط الأعلام اللي في app.css) */
function country_flag(string $cc): string
{
    $cc = strtoupper($cc);
    return preg_match('/^[A-Z]{2}$/', $cc) ? mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65) : '🌐';
}
