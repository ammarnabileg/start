<?php
/** نظام التصميم: مرجع حيّ للألوان والخطوط والمكونات الموحّدة (لمدير المنصة). */
render(t('نظام التصميم'), 'styleguide', function () {
    $sec = function ($title, $desc = '') {
        echo '<div class="mb-3"><h2 class="text-lg font-bold text-ink-900">' . e($title) . '</h2>'
            . ($desc ? '<p class="text-sm text-ink-500 mt-0.5">' . e($desc) . '</p>' : '') . '</div>';
    };
    $palettes = ['brand' => t('الأساسي'), 'ink' => t('المحايد'), 'lime' => t('نجاح'), 'amber' => t('تنبيه'), 'red' => t('خطأ'), 'cyan' => t('مميز')];
    $shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
    ?>
    <div class="max-w-5xl space-y-10">
      <div>
        <h1 class="text-2xl font-bold text-ink-900"><?= e(t('نظام التصميم الموحّد')) ?></h1>
        <p class="text-sm text-ink-500 mt-1"><?= e(t('الألوان والخطوط والمكونات اللي بتبني منها كل صفحات المنصة. استخدم نفس الكلاسات دي للحفاظ على اتساق المنتج.')) ?></p>
      </div>

      <!-- الألوان -->
      <section>
        <?php $sec(t('الألوان (Tokens)'), t('متغيّرات CSS موحّدة — نفس القيم في الوضع الفاتح والداكن تلقائيًا.')); ?>
        <div class="space-y-3">
        <?php foreach ($palettes as $key => $label): ?>
          <div>
            <div class="text-xs text-ink-500 mb-1"><?= e($label) ?> <span class="text-ink-400">(<?= e($key) ?>)</span></div>
            <div class="flex flex-wrap gap-1">
              <?php foreach ($shades as $s): ?>
                <div class="flex flex-col items-center">
                  <span class="w-12 h-10 rounded-lg border border-ink-200/60" style="background:rgb(var(--<?= e($key) ?>-<?= $s ?>))"></span>
                  <span class="text-[10px] text-ink-400 mt-0.5"><?= $s ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>

      <!-- الخطوط -->
      <section>
        <?php $sec(t('التدرّج الطباعي'), t('خط Alexandria. التدرّج ثابت عبر المنصة.')); ?>
        <div class="card p-6 space-y-3">
          <div class="text-3xl font-bold text-ink-900"><?= e(t('عنوان كبير · Heading XL')) ?> <span class="text-xs text-ink-400 font-normal">text-3xl / 700</span></div>
          <div class="text-xl font-bold text-ink-900"><?= e(t('عنوان · Heading')) ?> <span class="text-xs text-ink-400 font-normal">text-xl / 700</span></div>
          <div class="text-base font-semibold text-ink-800"><?= e(t('عنوان فرعي · Subtitle')) ?> <span class="text-xs text-ink-400 font-normal">text-base / 600</span></div>
          <div class="text-sm text-ink-700"><?= e(t('نص أساسي — ده شكل الفقرة العادية في الصفحات.')) ?> <span class="text-xs text-ink-400">text-sm</span></div>
          <div class="text-xs text-ink-500"><?= e(t('نص مساعد ثانوي · Caption')) ?> <span class="text-ink-400">text-xs</span></div>
        </div>
      </section>

      <!-- الأزرار -->
      <section>
        <?php $sec(t('الأزرار'), t('الأساسي للفعل الرئيسي، الشبح للثانوي، والأحمر للخطر.')); ?>
        <div class="card p-6 flex flex-wrap items-center gap-3">
          <button class="btn-primary">.btn-primary</button>
          <button class="btn-ghost">.btn-ghost</button>
          <button class="btn-danger">.btn-danger</button>
          <button class="btn-danger-ghost">.btn-danger-ghost</button>
          <button class="btn-primary" disabled>disabled</button>
        </div>
      </section>

      <!-- الحقول -->
      <section>
        <?php $sec(t('حقول الإدخال'), t('كلاس .input موحّد لكل الحقول، مع .label للعناوين.')); ?>
        <div class="card p-6 grid sm:grid-cols-2 gap-4">
          <label class="block"><span class="label"><?= e(t('نص')) ?></span><input class="input" placeholder="placeholder"></label>
          <label class="block"><span class="label"><?= e(t('قائمة')) ?></span><select class="input"><option><?= e(t('اختيار')) ?></option></select></label>
          <label class="block sm:col-span-2"><span class="label"><?= e(t('نص طويل')) ?></span><textarea class="input" rows="2"></textarea></label>
        </div>
      </section>

      <!-- الشارات والحالة -->
      <section>
        <?php $sec(t('الشارات ومؤشّرات الحالة'), t('.ds-status / .ds-dot موحّدة لكل حالات النظام.')); ?>
        <div class="card p-6 space-y-4">
          <div class="flex flex-wrap gap-2">
            <span class="badge">.badge</span>
            <span class="badge badge-green">badge-green</span>
            <span class="badge bg-amber-50 text-amber-700">amber</span>
            <span class="badge bg-red-50 text-red-600">red</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="ds-status ds-status--ok"><span class="ds-dot ds-dot--ok"></span><?= e(t('نشط')) ?></span>
            <span class="ds-status ds-status--warn"><span class="ds-dot ds-dot--warn"></span><?= e(t('قيد المراجعة')) ?></span>
            <span class="ds-status ds-status--danger"><span class="ds-dot ds-dot--danger"></span><?= e(t('موقوف')) ?></span>
            <span class="ds-status ds-status--info"><span class="ds-dot ds-dot--info ds-pulse"></span><?= e(t('يعمل الآن')) ?></span>
          </div>
        </div>
      </section>

      <!-- حالات التحميل -->
      <section>
        <?php $sec(t('حالات التحميل (Skeletons)'), t('.ds-skel أثناء جلب البيانات بدل ترك الشاشة فاضية.')); ?>
        <div class="card p-6 space-y-4">
          <div><span class="ds-skel ds-skel-title"></span><span class="ds-skel ds-skel-text md"></span><span class="ds-skel ds-skel-text sm"></span></div>
          <div class="ds-bar"></div>
          <div class="grid sm:grid-cols-3 gap-3"><span class="ds-skel ds-skel-card"></span><span class="ds-skel ds-skel-card"></span><span class="ds-skel ds-skel-card"></span></div>
        </div>
      </section>

      <!-- حالات الفراغ والخطأ والنجاح -->
      <section>
        <?php $sec(t('حالات: فراغ / خطأ / نجاح'), t('.ds-state موحّدة — دايمًا فيها سبب وخطوة تالية.')); ?>
        <div class="grid md:grid-cols-3 gap-4">
          <div class="ds-state">
            <span class="ds-state-ico"><?= ui_icon('inbox', 'w-7 h-7') ?></span>
            <span class="ds-state-title"><?= e(t('لا توجد عناصر')) ?></span>
            <span class="ds-state-text"><?= e(t('ابدأ بإضافة أول عنصر.')) ?></span>
            <span class="ds-state-actions"><button class="btn-primary !py-1.5"><?= e(t('إضافة')) ?></button></span>
          </div>
          <div class="ds-state ds-state--error">
            <span class="ds-state-ico"><?= ui_icon('alert', 'w-7 h-7') ?></span>
            <span class="ds-state-title"><?= e(t('حصل خطأ')) ?></span>
            <span class="ds-state-text"><?= e(t('تعذّر تحميل البيانات. جرّب تاني.')) ?></span>
            <span class="ds-state-actions"><button class="btn-ghost !py-1.5"><?= e(t('إعادة المحاولة')) ?></button></span>
          </div>
          <div class="ds-state ds-state--success">
            <span class="ds-state-ico"><?= ui_icon('check', 'w-7 h-7') ?></span>
            <span class="ds-state-title"><?= e(t('تم بنجاح')) ?></span>
            <span class="ds-state-text"><?= e(t('اتحفظت كل التغييرات.')) ?></span>
          </div>
        </div>
      </section>

      <!-- البطاقات -->
      <section>
        <?php $sec(t('البطاقات'), t('.card هي الحاوية الأساسية للمحتوى.')); ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="card p-5"><div class="font-semibold text-ink-900 mb-1"><?= e(t('بطاقة')) ?></div><p class="text-sm text-ink-500"><?= e(t('محتوى داخل .card بحشوة p-5.')) ?></p></div>
          <div class="card p-5 ring-2 ring-brand-500"><div class="font-semibold text-ink-900 mb-1"><?= e(t('بطاقة مختارة')) ?></div><p class="text-sm text-ink-500"><?= e(t('مع ring-2 ring-brand-500 للتمييز.')) ?></p></div>
        </div>
      </section>
    </div>
    <?php
});
