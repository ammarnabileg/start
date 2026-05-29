<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '/index.php';

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

$page_title = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - Discover</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { 50:'#f0fdfa',100:'#ccfbf1',200:'#99f6e4',300:'#5eead4',400:'#2dd4bf',500:'#14b8a6',600:'#0d9488',700:'#0f766e',800:'#115e59',900:'#134e4a' },
            accent: { 400:'#22d3ee',500:'#06b6d4',600:'#0891b2' }
          },
          fontFamily: { sans: ['Inter','sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .gradient-text { background: linear-gradient(135deg, #0d9488, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  </style>
</head>
<body class="min-h-screen bg-gray-50 flex">

  <!-- Left Panel - Gradient -->
  <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary-900 via-primary-700 to-accent-500 relative overflow-hidden flex-col justify-center items-center p-12">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-20 left-20 w-64 h-64 bg-white rounded-full blur-3xl"></div>
      <div class="absolute bottom-20 right-20 w-96 h-96 bg-accent-400 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 text-center text-white max-w-md">
      <div class="w-20 h-20 bg-white/20 rounded-3xl flex items-center justify-center mx-auto mb-8 backdrop-blur-sm border border-white/30">
        <span class="text-4xl font-black text-white">D</span>
      </div>
      <h1 class="text-4xl font-black mb-4">Welcome back!</h1>
      <p class="text-primary-100 text-lg leading-relaxed">Your community, your courses, your growth journey — all in one place.</p>
      <div class="mt-10 grid grid-cols-3 gap-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-white/20 text-center">
          <div class="text-2xl font-black">5k+</div>
          <div class="text-xs text-primary-200 mt-1">Communities</div>
        </div>
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-white/20 text-center">
          <div class="text-2xl font-black">120k+</div>
          <div class="text-xs text-primary-200 mt-1">Members</div>
        </div>
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-white/20 text-center">
          <div class="text-2xl font-black">800+</div>
          <div class="text-xs text-primary-200 mt-1">Courses</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Panel - Form -->
  <div class="flex-1 flex flex-col justify-center items-center p-6 sm:p-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="/index.php" class="inline-flex items-center gap-2 mb-6">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center">
            <span class="text-white font-bold text-lg">D</span>
          </div>
          <span class="font-black text-2xl gradient-text">Discover</span>
        </a>
        <h2 class="text-2xl font-bold text-gray-900">Sign in to your account</h2>
        <p class="text-gray-500 mt-2 text-sm">Don't have an account? <a href="/register.php" class="text-primary-600 font-semibold hover:underline">Get started free</a></p>
      </div>

      <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or Username</label>
          <input type="text" name="email" value="<?= e($_POST['email'] ?? '') ?>"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm"
            placeholder="your@email.com or username" required autocomplete="username">
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-sm font-medium text-gray-700">Password</label>
            <a href="#" class="text-xs text-primary-600 hover:underline font-medium">Forgot password?</a>
          </div>
          <div class="relative">
            <input type="password" name="password" id="password-field"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm pr-12"
              placeholder="••••••••" required autocomplete="current-password">
            <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="eye-icon">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
          <label for="remember" class="text-sm text-gray-600">Remember me for 30 days</label>
        </div>

        <button type="submit"
          class="w-full py-3 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white font-semibold text-sm hover:shadow-lg hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5 active:translate-y-0">
          Sign In
        </button>
      </form>

      <div class="mt-6 text-center">
        <p class="text-xs text-gray-400">Demo: admin@discover.com / password</p>
      </div>
    </div>
  </div>
</body>
<script>
function togglePassword() {
  const field = document.getElementById('password-field');
  field.type = field.type === 'password' ? 'text' : 'password';
}
</script>
</html>
