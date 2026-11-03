<?php
/** فتح لينك دعوة: يوافق أو يرفض */
$pdo = db();
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? $_POST['t'] ?? ''));

$st = $pdo->prepare("SELECT i.*, w.name AS ws_name, u.name AS by_name FROM workspace_invites i
    JOIN workspaces w ON w.id = i.workspace_id LEFT JOIN users u ON u.id = i.created_by
    WHERE i.token = ? AND i.expires_at > NOW()");
$st->execute([$token]);
$inv = $token !== '' ? $st->fetch() : false;

if (!$inv) {
    unset($_SESSION['invite_token']);
    auth_page(t('لينك الدعوة'), function () {
        echo t('<p class="text-center text-sm text-slate-600">اللينك ده منتهي أو اتلغى. اطلب لينك جديد من صاحب الشركة.</p>');
        echo '<a href="' . e(url('dashboard')) . '" class="block text-center link">' . e(t('للصفحة الرئيسية')) . '</a>';
    });
}

$me = current_user();
if (!$me) {
    // نفتكر اللينك لحد ما يسجل دخول أو يعمل حساب
    $_SESSION['invite_token'] = $token;
    auth_page(t('دعوة'), function () use ($inv) {
        ?>
        <p class="text-center"><b><?= e($inv['by_name'] ?: t('حد')) ?></b> <?= e(t('بيدعوك تنضم لشركة')) ?> <b><?= e($inv['ws_name']) ?></b></p>
        <a href="<?= e(url('register')) ?>" class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-3"><?= e(t('اعمل حساب')) ?></a>
        <a href="<?= e(url('login')) ?>" class="block text-center w-full border border-slate-300 font-semibold rounded-lg py-3"><?= e(t('عندي حساب، سجل دخول')) ?></a>
        <?php
    });
}

// دعوة لإيميل معين: لازم يكون نفس الإيميل
if ($inv['email'] && strtolower($inv['email']) !== strtolower($me['email'])) {
    unset($_SESSION['invite_token']);
    auth_page(t('دعوة'), function () use ($inv, $me) {
        echo t('<p class="text-center text-sm text-slate-600">الدعوة دي متبعتة لإيميل تاني. انت داخل بـ <span dir="ltr">') . e($me['email']) . t('</span>. اخرج وادخل بالإيميل الصح، أو اطلب دعوة جديدة.</p>');
        echo '<a href="' . e(url('dashboard')) . '" class="block text-center link">' . e(t('للصفحة الرئيسية')) . '</a>';
    });
}

$st = $pdo->prepare("SELECT status FROM workspace_users WHERE workspace_id = ? AND user_id = ?");
$st->execute([$inv['workspace_id'], $me['id']]);
$status = $st->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['invite_token']);
    if (($_POST['answer'] ?? '') === 'accept') {
        if ($status === false) {
            $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status, invited_by) VALUES (?, ?, ?, 'active', ?)")
                ->execute([$inv['workspace_id'], $me['id'], $inv['role'], $inv['created_by']]);
        } else {
            $pdo->prepare("UPDATE workspace_users SET status = 'active' WHERE workspace_id = ? AND user_id = ?")->execute([$inv['workspace_id'], $me['id']]);
        }
        if ($inv['email']) {
            $pdo->prepare("DELETE FROM workspace_invites WHERE id = ?")->execute([$inv['id']]);
        }
        $_SESSION['ws'] = (int)$inv['workspace_id'];
        log_activity('invite.accept', $inv['ws_name']);
        flash('success', t('أهلاً بيك في %s.', $inv['ws_name']));
        redirect(url('dashboard'));
    }
    if ($status === 'pending') {
        $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$inv['workspace_id'], $me['id']]);
    }
    flash('success', t('رفضت الدعوة.'));
    redirect(url('workspaces'));
}

if ($status === 'active') {
    unset($_SESSION['invite_token']);
    $_SESSION['ws'] = (int)$inv['workspace_id'];
    flash('success', t('انت عضو في %s أصلاً.', $inv['ws_name']));
    redirect(url('dashboard'));
}

auth_page(t('دعوة'), function () use ($inv, $token) {
    ?>
    <p class="text-center"><b><?= e($inv['by_name'] ?: t('حد')) ?></b> <?= e(t('بيدعوك تنضم لشركة')) ?> <b><?= e($inv['ws_name']) ?></b> <?= e(t('كـ')) ?> <?= e(role_label($inv['role'])) ?></p>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button name="answer" value="accept" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg py-3"><?= e(t('موافق، انضم')) ?></button>
      <button name="answer" value="decline" class="w-full border border-slate-300 font-semibold rounded-lg py-3"><?= e(t('رفض')) ?></button>
    </form>
    <?php
});
