<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
 header('Location: /index.php');
 exit;
}

$error = '';
$success = '';
$ref_code = $_GET['ref'] ?? '';
$referrer = null;
if ($ref_code) {
 $referrer = db_fetch('SELECT id, username, first_name FROM users WHERE affiliate_code = ?', [$ref_code]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (!verify_csrf($_POST['csrf_token'] ?? '')) {
 $error = 'Invalid security token.';
 } else {
 $first_name = trim($_POST['first_name'] ?? '');
 $last_name = trim($_POST['last_name'] ?? '');
 $username = strtolower(trim($_POST['username'] ?? ''));
 $email = strtolower(trim($_POST['email'] ?? ''));
 $password = $_POST['password'] ?? '';
 $confirm = $_POST['confirm_password'] ?? '';
 $ref = trim($_POST['ref_code'] ?? '');

 if (!$first_name || !$username || !$email || !$password) {
 $error = 'Please fill in all required fields.';
 } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
 $error = 'Username must be 3-30 characters: letters, numbers, underscores only.';
 } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
 $error = 'Please enter a valid email address.';
 } elseif (strlen($password) < 8) {
 $error = 'Password must be at least 8 characters.';
 } elseif ($password !== $confirm) {
 $error = 'Passwords do not match.';
 } elseif (db_fetch('SELECT id FROM users WHERE username = ?', [$username])) {
 $error = 'Username is already taken.';
 } elseif (db_fetch('SELECT id FROM users WHERE email = ?', [$email])) {
 $error = 'Email is already registered.';
 } else {
 $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
 $affiliate_code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
 while (db_fetch('SELECT id FROM users WHERE affiliate_code = ?', [$affiliate_code])) {
 $affiliate_code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
 }

 $referred_by = null;
 if ($ref) {
 $ref_user = db_fetch('SELECT id FROM users WHERE affiliate_code = ?', [$ref]);
 if ($ref_user) $referred_by = $ref_user['id'];
 }

 $user_id = db_insert(
 'INSERT INTO users (username, email, password_hash, first_name, last_name, affiliate_code, referred_by) VALUES (?,?,?,?,?,?,?)',
 [$username, $email, $hash, $first_name, $last_name, $affiliate_code, $referred_by]
 );

 db_insert('INSERT INTO notification_settings (user_id) VALUES (?)', [$user_id]);

 if ($referred_by) {
 create_notification($referred_by, 'affiliate_referral', 'New Referral!',
 "{$first_name} {$last_name} joined via your referral link.",
 '/settings.php?tab=affiliates'
 );
 }

 login_user($user_id);
 header('Location: /index.php');
 exit;
 }
 }
}

try { $platform_name = get_platform_setting('platform_name', 'Discover'); } catch(Exception $e) { $platform_name = 'Discover'; }
$page_title = 'Create Account';
include __DIR__ . '/includes/header.php';
?>

<main class="min-h-[calc(100vh-80px)] flex items-center justify-center px-4 py-12">
 <div class="w-full max-w-md">
 <!-- Logo -->
 <div class="text-center mb-8">
 <a href="/index.php" class="inline-flex flex-col items-center gap-3">
 <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center text-white font-black text-xl ">
 <?= strtoupper(substr($platform_name, 0, 1)) ?>
 </div>
 <span class="font-black text-2xl bg-gradient-to-r from-primary-600 to-accent-500 bg-clip-text text-transparent"><?= e($platform_name) ?></span>
 </a>
 <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-4">Create your account</h1>
 <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">It's free and always will be</p>
 </div>

 <?php if ($referrer): ?>
 <div class="mb-4 p-3 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-500/30 rounded-xl text-primary-700 dark:text-primary-400 text-sm text-center">
 You were invited by <strong class="text-primary-600 dark:text-primary-300"><?= e($referrer['first_name']) ?></strong>
 </div>
 <?php endif; ?>

 <!-- Card -->
 <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-8 ">
 <?php if ($error): ?>
 <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-500/30 rounded-xl text-red-600 dark:text-red-400 text-sm flex items-center gap-2">
 <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
 <?= e($error) ?>
 </div>
 <?php endif; ?>

 <form method="POST" class="space-y-4">
 <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 <input type="hidden" name="ref_code" value="<?= e($ref_code) ?>">

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">First Name <span class="text-red-400">*</span></label>
 <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>"
 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="Ahmad" required>
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Last Name</label>
 <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>"
 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="Al-Rashid">
 </div>
 </div>

 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Username <span class="text-red-400">*</span></label>
 <div class="relative">
 <span class="absolute left-4 top-3.5 text-gray-400 text-sm">@</span>
 <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>"
 class="w-full pl-8 pr-4 py-3 bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="yourhandle" pattern="[a-z0-9_]{3,30}" required>
 </div>
 <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">3-30 chars: letters, numbers, underscores</p>
 </div>

 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email <span class="text-red-400">*</span></label>
 <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="your@email.com" required>
 </div>

 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password <span class="text-red-400">*</span></label>
 <input type="password" name="password"
 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="Min. 8 characters" required minlength="8">
 </div>

 <div>
 <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm Password <span class="text-red-400">*</span></label>
 <input type="password" name="confirm_password"
 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
 placeholder="Repeat password" required>
 </div>

 <div class="flex items-start gap-2 pt-1">
 <input type="checkbox" id="terms" required
 class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-white/20 bg-gray-50 dark:bg-[#2a2a2a] text-primary-600 focus:ring-primary-500">
 <label for="terms" class="text-xs text-gray-500 dark:text-gray-400">I agree to the <a href="#" class="text-primary-600 dark:text-primary-400 hover:underline">Terms of Service</a> and <a href="#" class="text-primary-600 dark:text-primary-400 hover:underline">Privacy Policy</a></label>
 </div>

 <button type="submit"
 class="w-full py-3 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white font-semibold text-sm hover:from-primary-700 hover:to-accent-600 transition-all mt-2">
 Create Account — It's Free
 </button>
 </form>

 <div class="mt-6 text-center border-t border-gray-200 dark:border-white/10 pt-5">
 <p class="text-sm text-gray-500 dark:text-gray-500">
 Already have an account?
 <a href="/login.php" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline ml-1">Sign in</a>
 </p>
 </div>
 </div>
 </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
