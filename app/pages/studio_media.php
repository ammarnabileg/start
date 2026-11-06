<?php
/** استوديو المحتوى: الصور والفيديو بالذكاء الاصطناعي (Higgsfield). النتايج بتتحفظ في مكتبة الوسائط وتتفتح في المصمم أو منشور جديد */
$wid = (int)ws('id');
$hf = connector('higgsfield');
$on = $hf && ai_feature_on('image');
$lib = $on ? hf_lib_list($wid, 30) : [];
render(t('استوديو المحتوى'), 'studio_media', function () use ($on, $lib, $hf) {
    $ratios = ['1:1' => [t('مربع'), 'aspect-square'], '4:5' => [t('بوست'), 'aspect-[4/5]'], '9:16' => [t('ستوري'), 'aspect-[9/16]'], '16:9' => [t('عرضي'), 'aspect-video']];
    ?>
  <?php if (!$on): ?>
    <div class="card p-8 text-center"><div class="mx-auto mb-3 w-12 h-12 rounded-2xl bg-ink-900 text-white grid place-items-center font-bold">H</div>
      <b class="block text-ink-900"><?= e($hf ? t('توليد الصور متوقف في إعدادات الذكاء الاصطناعي') : t('اربط Higgsfield من التكاملات')) ?></b>
      <p class="text-sm text-ink-500 mt-1"><?= e(t('توليد الصور والفيديو بيستخدم حساب Higgsfield بتاعك، والنتايج بتتحفظ في مكتبة الوسائط.')) ?></p>
      <a href="<?= e(url($hf ? 'assistant' : 'connectors')) ?>" class="btn-primary mt-4 inline-flex"><?= e($hf ? t('إعدادات الذكاء الاصطناعي') : t('فتح التكاملات')) ?></a></div>
  <?php else: ?>
  <div class="card p-5 space-y-4" id="sm">
    <div class="relative"><textarea id="sm-p" rows="3" class="input !mt-0 text-base leading-7 pe-28" dir="auto" maxlength="1500" placeholder="<?= e(t('صِف الصورة: مثلًا طبق مشويات على طاولة خشب بإضاءة دافية، تصوير احترافي')) ?>"></textarea>
      <button type="button" id="sm-go" class="btn-primary absolute bottom-3 end-3"><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('ولّد')) ?></span></button></div>
    <div class="flex flex-wrap items-center gap-4">
      <div class="inline-flex rounded-xl bg-ink-100 p-1 text-sm"><?php foreach (['image' => [t('صورة'), 'image']] + (ai_feature_on('video') ? ['video' => [t('فيديو'), 'film']] : []) as $k => [$l, $ic]): ?><label class="cp-kind flex items-center gap-1.5 rounded-lg px-3 py-1.5 cursor-pointer text-ink-600 has-[:checked]:bg-white has-[:checked]:text-brand-700 has-[:checked]:shadow-sm"><input type="radio" name="sm-kind" value="<?= $k ?>" class="sr-only" <?= $k === 'image' ? 'checked' : '' ?>><?= ui_icon($ic, 'w-4 h-4') ?><?= e($l) ?></label><?php endforeach; ?></div>
      <div class="flex gap-1.5"><?php foreach ($ratios as $k => [$l, $cls]): ?><label class="cursor-pointer"><input type="radio" name="sm-ar" value="<?= $k ?>" class="peer sr-only" <?= $k === '1:1' ? 'checked' : '' ?>><span class="flex flex-col items-center gap-1 rounded-xl border border-ink-200 px-3 py-2 text-[11px] text-ink-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800"><span class="block w-5 <?= $cls ?> rounded-[3px] border-2 border-current"></span><?= e($l) ?></span></label><?php endforeach; ?></div>
      <label class="inline-flex items-center gap-2 text-sm text-ink-600"><input type="checkbox" id="sm-two" class="rounded"> <?= e(t('نسختين')) ?></label>
      <span class="ms-auto text-[11px] text-ink-400"><?= e(t('بيستهلك من رصيد Higgsfield، وبيستخدم ستايل شركتك من ملف الشركة.')) ?></span>
    </div>
    <p id="sm-st" class="hidden text-sm"></p>
  </div>
  <div class="flex items-center gap-2 mt-6 mb-3"><b class="text-ink-900"><?= e(t('اللي ولّدته')) ?></b><span class="text-xs text-ink-400"><?= e(t('بيتحفظ في مكتبة الوسائط تلقائيًا')) ?></span></div>
  <div id="sm-g" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3">
    <?php foreach ($lib as $n): $v = substr($n, -4) === '.mp4'; $src = url('ai_task', ['img' => $n]); ?>
      <div class="sm-it group relative rounded-2xl overflow-hidden bg-ink-100 aspect-square" data-src="<?= e($src) ?>" data-v="<?= $v ? 1 : 0 ?>">
        <?php if ($v): ?><video src="<?= e($src) ?>" muted loop playsinline preload="metadata" class="w-full h-full object-cover"></video><?php else: ?><img src="<?= e($src) ?>" loading="lazy" alt="" class="w-full h-full object-cover transition group-hover:scale-105"><?php endif; ?>
        <div class="absolute inset-x-0 bottom-0 p-2 flex gap-1.5 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition">
          <?php if (!$v): ?><a class="flex-1 rounded-lg bg-white/95 text-ink-900 text-xs font-medium py-1.5 text-center" href="<?= e(url('designer', ['img' => $src])) ?>"><?= e(t('صمّم بيها')) ?></a><?php endif; ?>
          <a class="flex-1 rounded-lg bg-brand-600 text-white text-xs font-medium py-1.5 text-center" href="<?= e(url('compose')) ?>"><?= e(t('في منشور')) ?></a>
          <a class="rounded-lg bg-white/95 text-ink-900 px-2 grid place-items-center" href="<?= e($src) ?>" download title="<?= e(t('تحميل')) ?>"><?= ui_icon('download', 'w-4 h-4') ?></a></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p id="sm-none" class="<?= $lib ? 'hidden' : '' ?> text-center text-sm text-ink-500 py-10"><?= e(t('لسه مولدتش حاجة. اكتب وصف ودوس ولّد.')) ?></p>
  <script>window.SM = <?= json_encode(['csrf' => csrf_token(), 'designer' => url('designer'), 'compose' => url('compose'), 'L' => ['busy' => t('بنولّد… غالبًا من 10 لـ 60 ثانية'), 'vbusy' => t('بنولّد الفيديو… ممكن ياخد كام دقيقة'), 'err' => t('حصلت مشكلة.'), 'design' => t('صمّم بيها'), 'post' => t('في منشور'), 'done' => t('اتولدت ✓ واتحفظت في المكتبة'), 'slow' => t('التوليد أخد وقت أطول من العادي. جرّب تاني.')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/studio-media.js?v=<?= e(APP_VERSION) ?>"></script>
  <?php endif; ?>
<?php });
