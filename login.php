<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$redirect_raw = $_GET['redirect'] ?? '/index.php';
$redirect = (strpos($redirect_raw, '/') === 0 && strpos($redirect_raw, '//') !== 0) ? $redirect_raw : '/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email_or_user = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email_or_user) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $user = db_fetch(
                'SELECT * FROM users WHERE email = ? OR username = ?',
                [$email_or_user, $email_or_user]
            );
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user['id']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email/username or password.';
            }
        }
    }
}

try { $platform_name = get_platform_setting('platform_name', 'Discover'); } catch(Exception $e) { $platform_name = 'Discover'; }
$page_title = 'Sign In';
include __DIR__ . '/includes/header.php';
?>

<main class="min-h-[calc(100vh-80px)] flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="/index.php" class="inline-flex flex-col items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center text-white font-black text-xl shadow-lg">
          <?= strtoupper(substr($platform_name, 0, 1)) ?>
        </div>
        <span class="font-black text-2xl bg-gradient-to-r from-primary-600 to-accent-500 bg-clip-text text-transparent"><?= e($platform_name) ?></span>
      </a>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-4">Welcome back</h1>
      <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Sign in to your account</p>
    </div>

    <!-- Card -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-8 shadow-lg">
      <?php if ($error): ?>
        <div class="mb-5 p-4 bg-red-900/30 border border-red-500/30 rounded-xl text-red-400 text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email or Username</label>
          <input type="text" name="email" value="<?= e($_POST['email'] ?? '') ?>"
                 class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors"
                 placeholder="your@email.com or username" required autocomplete="username">
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
            <a href="#" class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-500 font-medium transition-colors">Forgot password?</a>
          </div>
          <div class="relative">
            <input type="password" name="password" id="password-field"
                   class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors pr-12"
                   placeholder="••••••••" required autocomplete="current-password">
            <button type="button" onclick="togglePassword()" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" id="remember" name="remember"
                 class="w-4 h-4 rounded border-gray-300 dark:border-white/20 bg-gray-50 dark:bg-[#2a2a2a] text-primary-600 focus:ring-primary-500">
          <label for="remember" class="text-sm text-gray-500 dark:text-gray-400">Remember me for 30 days</label>
        </div>

        <button type="submit"
                class="w-full py-3 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white font-semibold text-sm hover:from-primary-700 hover:to-accent-600 transition-all shadow-lg mt-2">
          Sign In
        </button>
      </form>

      <div class="mt-6 text-center border-t border-gray-200 dark:border-white/10 pt-5">
        <p class="text-sm text-gray-500 dark:text-gray-500">
          Don't have an account?
          <a href="/register.php" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline ml-1">Sign up</a>
        </p>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
function togglePassword() {
  const field = document.getElementById('password-field');
  field.type = field.type === 'password' ? 'text' : 'password';
}
</script>
