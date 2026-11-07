<?php
/** خطط متابعة العملاء: جزء من "الردود التلقائية والـ Workflows" (نفس البيلدر والقواعد) */
require_once __DIR__ . '/../partials/hubx_t.php';
$mgr = can('crm.manage') || is_ws_owner();
render(t('خطط متابعة العملاء'), 'crm_plans', function () use ($mgr) { ?>
  <p class="text-sm text-ink-500 mb-4 max-w-3xl"><?= e(t('خطوات متابعة أوتوماتيك لكل عميل بالأيام: واتساب، مكالمة، إيميل، أو مهمة. تبدأها من نافذة العميل أو تشتغل لوحدها حسب القواعد.')) ?></p>
  <div id="cp-root" class="max-w-4xl"></div>
  <script>window.HUBX = <?= json_encode(['api' => url('crm_leads'), 'self' => url('crm_capture'), 'csrf' => csrf_token(), 'manage' => $mgr, 'plans' => crm_plans(), 'chans' => crm_plan_channels(), 'tpl' => crm_templates(),
    'rules' => crm_plan_rules(), 'setUrl' => url('crm_settings'), 'T' => hubx_t()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/hub-ui.js?v=<?= e(APP_VERSION) ?>"></script>
  <script>(function () { var el = document.getElementById('cp-root'); if (window.HubX && HubX.plans) HubX.plans(el); })();</script>
<?php });
