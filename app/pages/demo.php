<?php
/** وضع التعلّم: الدخول/الخروج من وورك سبيس الديمو المشتركة (عرض فقط) */
if (isset($_GET['exit'])) { demo_exit(); redirect(url('dashboard')); }
if (demo_ws_id() <= 0) { flash('error', t('وضع العرض غير متاح حاليًا.')); redirect(url('dashboard')); }
if (demo_enter()) { redirect(url('dashboard')); }
flash('error', t('تعذّر فتح وضع العرض.'));
redirect(url('dashboard'));
