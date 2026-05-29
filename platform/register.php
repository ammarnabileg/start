<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: /platform/index.php');
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
        $last_name  = trim($_POST['last_name'] ?? '');
        $username   = strtolower(trim($_POST['username'] ?? ''));
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $ref        = trim($_POST['ref_code'] ?? '');

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
            // Ensure unique affiliate code
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

            // Default notification settings
            db_insert('INSERT INTO notification_settings (user_id) VALUES (?)', [$user_id]);

            // Notify referrer
            if ($referred_by) {
                create_notification($referred_by, 'affiliate_referral', 'New Referral!',
                    "{$first_name} {$last_name} joined via your referral link.",
                    '/platform/settings.php?tab=affiliates'
                );
            }

            login_user($user_id);
            header('Location: /platform/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - Discover</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { 50:'#f0fdfa',100:'#ccfbf1',500:'#14b8a6',600:'#0d9488',700:'#0f766e',800:'#115e59',900:'#134e4a' },
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
  <!-- Left gradient panel -->
  <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-accent-600 via-primary-600 to-primary-900 relative overflow-hidden flex-col justify-center items-center p-12">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-10 right-10 w-80 h-80 bg-white rounded-full blur-3xl"></div>
      <div class="absolute bottom-10 left-10 w-64 h-64 bg-accent-300 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 text-white text-center max-w-md">
      <div class="text-6xl mb-6">🚀</div>
      <h1 class="text-4xl font-black mb-4">Join the community</h1>
      <p class="text-primary-100 text-lg leading-relaxed">Connect with thousands of learners, creators, and professionals across the Gulf region.</p>
      <div class="mt-10 space-y-3">
        <?php foreach (['Learn from world-class creators', 'Build meaningful connections', 'Earn certificates & badges', 'Arabic & English content'] as $feat): ?>
          <div class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3 backdrop-blur-sm border border-white/20">
            <div class="w-6 h-6 bg-primary-400 rounded-full flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <span class="text-sm font-medium"><?= $feat ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($referrer): ?>
        <div class="mt-8 bg-white/10 rounded-2xl p-4 backdrop-blur-sm border border-white/20">
          <p class="text-sm">🎁 You were invited by <strong><?= e($referrer['first_name']) ?></strong></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="flex-1 flex flex-col justify-center items-center p-6 sm:p-12 overflow-y-auto">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="/platform/index.php" class="inline-flex items-center gap-2 mb-6">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center">
            <span class="text-white font-bold text-lg">D</span>
          </div>
          <span class="font-black text-2xl gradient-text">Discover</span>
        </a>
        <h2 class="text-2xl font-bold text-gray-900">Create your account</h2>
        <p class="text-gray-500 mt-2 text-sm">Already have an account? <a href="/platform/login.php" class="text-primary-600 font-semibold hover:underline">Sign in</a></p>
      </div>

      <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="ref_code" value="<?= e($ref_code) ?>">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
              placeholder="Ahmad" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
            <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
              placeholder="Al-Rashid">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="absolute left-3 top-2.5 text-gray-400 text-sm">@</span>
            <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>"
              class="w-full pl-8 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
              placeholder="yourhandle" pattern="[a-z0-9_]{3,30}" required>
          </div>
          <p class="text-xs text-gray-400 mt-1">3-30 chars: letters, numbers, underscores</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
            placeholder="your@email.com" required>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
          <input type="password" name="password"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
            placeholder="Min. 8 characters" required minlength="8">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
          <input type="password" name="confirm_password"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
            placeholder="Repeat password" required>
        </div>

        <div class="flex items-start gap-2 pt-1">
          <input type="checkbox" id="terms" required class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
          <label for="terms" class="text-xs text-gray-600">I agree to the <a href="#" class="text-primary-600 hover:underline">Terms of Service</a> and <a href="#" class="text-primary-600 hover:underline">Privacy Policy</a></label>
        </div>

        <button type="submit"
          class="w-full py-3 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white font-semibold text-sm hover:shadow-lg hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5 active:translate-y-0">
          Create Account — It's Free!
        </button>
      </form>
    </div>
  </div>
</body>
</html>
