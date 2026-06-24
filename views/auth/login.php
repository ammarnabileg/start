<?php
$platformName = $_ENV['APP_NAME'] ?? 'HireAI';
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<div class="min-h-screen flex">
  <!-- LEFT: Brand Panel -->
  <div class="hidden lg:flex lg:w-5/12 gradient-bg flex-col justify-between p-12 relative overflow-hidden">
    <!-- Decorative circles -->
    <div class="absolute -top-20 -left-20 w-96 h-96 bg-white/5 rounded-full"></div>
    <div class="absolute -bottom-32 -right-20 w-[500px] h-[500px] bg-white/5 rounded-full"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-white/3 rounded-full"></div>

    <!-- Logo -->
    <div class="relative z-10">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/>
          </svg>
        </div>
        <span class="text-white text-xl font-bold"><?= htmlspecialchars($platformName) ?></span>
      </div>
    </div>

    <!-- Center Content -->
    <div class="relative z-10 float">
      <div class="w-32 h-32 bg-white/10 backdrop-blur rounded-3xl flex items-center justify-center mx-auto mb-8">
        <svg class="w-16 h-16 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-white text-center leading-tight mb-4">
        AI-Powered Recruitment,<br>Redefined
      </h1>
      <p class="text-violet-200 text-center text-sm leading-relaxed max-w-xs mx-auto">
        Automate your entire first-stage hiring process with intelligent AI interviews that never sleep.
      </p>
    </div>

    <!-- Feature Pills -->
    <div class="relative z-10">
      <div class="flex flex-wrap gap-2 justify-center mb-6">
        <?php foreach(['AI Interviews', 'Smart Matching', 'CV Analysis', 'Instant Evaluation', 'Multi-Tenant'] as $feat): ?>
        <span class="bg-white/15 text-white text-xs font-medium rounded-full px-3 py-1.5 backdrop-blur border border-white/20"><?= $feat ?></span>
        <?php endforeach; ?>
      </div>
      <p class="text-center text-violet-300 text-xs">Trusted by leading companies worldwide</p>
    </div>
  </div>

  <!-- RIGHT: Login Form -->
  <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 lg:px-12 bg-white">
    <!-- Mobile logo -->
    <div class="lg:hidden mb-8 text-center">
      <div class="w-12 h-12 bg-violet-700 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/>
        </svg>
      </div>
      <div class="font-bold text-gray-900 text-xl"><?= htmlspecialchars($platformName) ?></div>
    </div>

    <div class="w-full max-w-md fade-up">
      <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome back</h2>
        <p class="text-gray-500">Sign in to your account to continue</p>
      </div>

      <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl px-5 py-4 mb-6 flex items-center gap-3 text-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="/login" id="loginForm" novalidate>
        <input type="hidden" name="_csrf" value="<?= (new Request())->csrf() ?>">
        <div class="space-y-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
            <input type="email" name="email" placeholder="you@company.com" required autofocus
              class="w-full border border-gray-300 rounded-2xl px-5 py-4 text-gray-900 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all placeholder:text-gray-400"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm font-semibold text-gray-700">Password</label>
              <a href="/forgot-password" class="text-sm text-violet-600 hover:text-violet-800 font-medium">Forgot password?</a>
            </div>
            <div class="relative">
              <input type="password" name="password" id="passwordInput" placeholder="••••••••" required
                class="w-full border border-gray-300 rounded-2xl px-5 py-4 pr-14 text-gray-900 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all placeholder:text-gray-400">
              <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <label for="remember" class="text-sm text-gray-600">Remember me for 30 days</label>
          </div>
        </div>

        <button type="submit" id="submitBtn"
          class="w-full mt-7 bg-violet-700 hover:bg-violet-800 text-white rounded-2xl py-4 font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow-violet-200 hover:shadow-lg">
          <span id="btnText">Sign In</span>
          <svg id="btnSpinner" class="hidden w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </button>
      </form>

      <div class="mt-8 text-center">
        <p class="text-gray-500 text-sm">
          Are you a candidate?
          <a href="/register" class="text-violet-600 hover:text-violet-800 font-semibold">Create an account →</a>
        </p>
      </div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  input.type = input.type === 'password' ? 'text' : 'password';
}
document.getElementById('loginForm').addEventListener('submit', function() {
  document.getElementById('btnText').textContent = 'Signing in...';
  document.getElementById('btnSpinner').classList.remove('hidden');
  document.getElementById('submitBtn').disabled = true;
});
</script>
