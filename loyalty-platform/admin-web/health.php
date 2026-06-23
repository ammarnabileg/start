<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('health', 'view');

function safe(callable $fn, $default) { try { return $fn(); } catch (Throwable $e) { return $default; } }

$dbSize = safe(fn() => scalar("select pg_size_pretty(pg_database_size(current_database()))"), '—');
$tables = safe(fn() => all("select relname, n_live_tup rows, pg_size_pretty(pg_total_relation_size(relid)) size, pg_total_relation_size(relid) bytes
                            from pg_stat_user_tables where schemaname='public' order by bytes desc limit 12"), []);
$tokens = safe(fn() => (int) scalar("select count(*) from public.device_tokens"), 0);
$tokByPlat = safe(fn() => all("select platform, count(*) c from public.device_tokens group by platform"), []);
$optIn  = safe(fn() => (int) scalar("select count(*) from public.users where push_opt_in"), 0);

$cronJobs = safe(fn() => all("select jobid, jobname, schedule, active from cron.job order by jobname"), null);
$cronRuns = safe(fn() => all("select jobid, status, start_time, return_message from cron.job_run_details order by start_time desc limit 30"), []);
$lastRun = [];
foreach ($cronRuns as $r) if (!isset($lastRun[$r['jobid']])) $lastRun[$r['jobid']] = $r;

$errors = safe(fn() => all("select * from admin.error_log order by created_at desc limit 15"), []);
$errCount24 = safe(fn() => (int) scalar("select count(*) from admin.error_log where created_at >= now()-interval '24 hours'"), 0);

$title = 'صحة النظام';
require __DIR__ . '/partials/header.php';
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">حجم قاعدة البيانات</div><div class="text-2xl font-extrabold mt-1"><?= e($dbSize) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">أجهزة Push مسجّلة</div><div class="text-2xl font-extrabold text-green-600 mt-1"><?= n($tokens) ?></div><div class="text-xs text-gray-400"><?= n($optIn) ?> مفعّل الإشعارات</div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">مهام cron</div><div class="text-2xl font-extrabold mt-1"><?= $cronJobs===null?'—':n(count($cronJobs)) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">أخطاء (٢٤ ساعة)</div><div class="text-2xl font-extrabold <?= $errCount24?'text-red-600':'' ?> mt-1"><?= n($errCount24) ?></div></div>
</div>

<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border overflow-hidden">
    <div class="px-5 py-3 border-b font-bold">أكبر الجداول</div>
    <table class="w-full text-sm"><tbody>
      <?php foreach ($tables as $t): ?>
        <tr class="border-t"><td class="px-4 py-2 font-bold"><?= e($t['relname']) ?></td>
          <td class="px-4 py-2 text-gray-500"><?= n($t['rows']) ?> صف</td>
          <td class="px-4 py-2 ltr text-left"><?= e($t['size']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$tables): ?><tr><td class="px-4 py-4 text-gray-400">غير متاح.</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div class="bg-white rounded-xl border overflow-hidden">
    <div class="px-5 py-3 border-b font-bold">مهام cron وآخر تشغيل</div>
    <table class="w-full text-sm"><tbody>
      <?php if ($cronJobs === null): ?>
        <tr><td class="px-4 py-4 text-gray-400">pg_cron غير متاح من هذا الاتصال.</td></tr>
      <?php else: foreach ($cronJobs as $j): $lr = $lastRun[$j['jobid']] ?? null; ?>
        <tr class="border-t"><td class="px-4 py-2 font-bold"><?= e($j['jobname']) ?><div class="text-xs text-gray-400 ltr"><?= e($j['schedule']) ?></div></td>
          <td class="px-4 py-2"><?= $lr ? ($lr['status']==='succeeded'?badge('نجح','green'):badge($lr['status'],'red')) : badge('لم يُشغّل','gray') ?></td>
          <td class="px-4 py-2 text-gray-500"><?= $lr ? dt($lr['start_time']) : '—' ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>

  <div class="bg-white rounded-xl border overflow-hidden md:col-span-2">
    <div class="px-5 py-3 border-b font-bold flex justify-between"><span>أحدث الأخطاء (جهة اللوحة)</span>
      <span class="text-xs text-gray-400">أخطاء دوال الحافة تظهر في Supabase → Logs</span></div>
    <table class="w-full text-sm"><tbody>
      <?php foreach ($errors as $er): ?>
        <tr class="border-t"><td class="px-4 py-2 text-gray-500 whitespace-nowrap"><?= dt($er['created_at']) ?></td>
          <td class="px-4 py-2"><?= badge($er['context'],'gray') ?></td>
          <td class="px-4 py-2 text-red-600 text-xs ltr text-left"><?= e($er['message']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$errors): ?><tr><td class="px-4 py-4 text-gray-400">لا أخطاء مُسجّلة 🎉</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
