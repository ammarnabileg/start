<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('content', 'view');

if (is_post()) {
  csrf_check();
  require_perm('content', 'edit');
  q("update public.platform_settings set
        terms_url=:t, privacy_url=:p, support_email=:se, support_phone=:sp,
        min_app_version=:mv, maintenance_mode=:mm,
        default_notifications_monthly_quota=:q, default_customers_view_enabled=:cv,
        updated_at=now() where id=true", [
    't'=>trim((string)post('terms_url')) ?: null, 'p'=>trim((string)post('privacy_url')) ?: null,
    'se'=>trim((string)post('support_email')) ?: null, 'sp'=>trim((string)post('support_phone')) ?: null,
    'mv'=>trim((string)post('min_app_version')) ?: null, 'mm'=>post('maintenance_mode')?'true':'false',
    'q'=>max(0,(int)post('quota')), 'cv'=>post('customers_view')?'true':'false',
  ]);
  audit('update', 'platform_settings'); flash('تم حفظ إعدادات المنصّة.'); redirect('content.php');
}

$s = platform_settings();
$ce = can('content', 'edit');
$title = 'مركز المحتوى وإعدادات المنصّة';
require __DIR__ . '/partials/header.php';
?>
<form method="post" class="grid md:grid-cols-2 gap-6"><?= csrf_field() ?>
  <div class="bg-white rounded-xl border p-5 space-y-4">
    <div class="font-bold">روابط ومحتوى</div>
    <?php
      $fld = fn($n,$l,$v,$ph='')=>'<label class="block"><span class="text-xs text-gray-500">'.e($l).'</span>'
        .'<input name="'.$n.'" value="'.e($v).'" placeholder="'.e($ph).'" '.($ce?'':'disabled').' class="mt-1 w-full border rounded-lg px-3 py-2"></label>';
      echo $fld('terms_url','رابط الشروط والأحكام',$s['terms_url']??'', 'https://...');
      echo $fld('privacy_url','رابط سياسة الخصوصية',$s['privacy_url']??'', 'https://...');
      echo $fld('support_email','بريد الدعم',$s['support_email']??'');
      echo $fld('support_phone','هاتف الدعم',$s['support_phone']??'');
    ?>
  </div>
  <div class="bg-white rounded-xl border p-5 space-y-4">
    <div class="font-bold">تحكّم تشغيلي</div>
    <?= $fld('min_app_version','أدنى إصدار مطلوب للتطبيق',$s['min_app_version']??'', 'مثال: 1.4.0') ?>
    <label class="block"><span class="text-xs text-gray-500">الحدّ الشهري الافتراضي للإشعارات (لكل تاجر)</span>
      <input name="quota" type="number" min="0" value="<?= e($s['default_notifications_monthly_quota']??2000) ?>" <?= $ce?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
    <label class="flex items-center gap-2 mt-2"><input type="checkbox" name="customers_view" <?= !empty($s['default_customers_view_enabled'])?'checked':'' ?> <?= $ce?'':'disabled' ?>> تفعيل دليل العملاء افتراضيًا للتجار</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="maintenance_mode" <?= !empty($s['maintenance_mode'])?'checked':'' ?> <?= $ce?'':'disabled' ?>> <span class="text-red-600 font-bold">وضع الصيانة</span></label>
    <p class="text-xs text-gray-400">آخر تحديث: <?= dt($s['updated_at']??null) ?></p>
  </div>
  <?php if ($ce): ?><div class="md:col-span-2"><button class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-8 py-2.5">حفظ</button></div><?php endif; ?>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
