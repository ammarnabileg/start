<?php
/**
 * Login form FRAGMENT — embedded inside views/layouts/auth.php (right half).
 * No <html>; the layout supplies <head>/<body> and a mobile logo above this.
 * Receives (optional): $error (string), $email (prefill), $csrf|$csrf_token.
 */
$__csrf  = $csrf ?? ($csrf_token ?? '');
$__email = $email ?? '';
$__error = $error ?? '';
?>
<div class="text-center lg:text-start">
  <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900"><?= e(app_lang('login_title')) ?></h1>
  <p class="mt-2 text-gray-500"><?= e(app_lang('login_subtitle')) ?></p>
</div>

<?php if ($__error !== ''): ?>
  <div role="alert" class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
    <span><?= e($__error) ?></span>
  </div>
<?php endif; ?>

<form method="POST" action="/login" class="mt-8 space-y-5" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e($__csrf) ?>">

  <!-- Email -->
  <div>
    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5"><?= e(app_lang('email')) ?></label>
    <div class="relative">
      <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
      </span>
      <input id="email" name="email" type="email" autocomplete="email" required
             value="<?= e($__email) ?>" placeholder="you@company.com"
             class="w-full rounded-lg border-gray-300 ps-10 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-violet-500 focus:ring-violet-500">
    </div>
  </div>

  <!-- Password -->
  <div>
    <div class="flex items-center justify-between mb-1.5">
      <label for="password" class="block text-sm font-medium text-gray-700"><?= e(app_lang('password')) ?></label>
      <a href="/forgot-password" class="text-sm font-medium text-brand hover:text-brand-dark transition"><?= e(app_lang('forgot_password')) ?></a>
    </div>
    <div class="relative">
      <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
      </span>
      <input id="password" name="password" type="password" autocomplete="current-password" required
             placeholder="••••••••"
             class="w-full rounded-lg border-gray-300 ps-10 pe-11 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-violet-500 focus:ring-violet-500">
      <button type="button" id="toggle-password" aria-label="<?= e(app_lang('password')) ?>"
              class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400 hover:text-gray-600 transition">
        <svg id="icon-eye" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <svg id="icon-eye-off" class="hidden w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
      </button>
    </div>
  </div>

  <!-- Remember me -->
  <div class="flex items-center">
    <input id="remember" name="remember" type="checkbox" value="1"
           class="h-4 w-4 rounded border-gray-300 text-brand focus:ring-violet-500">
    <label for="remember" class="ms-2 text-sm text-gray-600"><?= e(app_lang('remember_me')) ?></label>
  </div>

  <!-- Submit -->
  <button type="submit" class="btn-primary w-full justify-center text-base py-3">
    <?= e(app_lang('sign_in')) ?>
    <svg class="w-5 h-5 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
  </button>
</form>

<!-- Candidate portal note -->
<div class="mt-8 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
  <?= e(app_lang('are_you_candidate')) ?>
  <a href="/candidate/jobs" class="font-semibold text-brand hover:text-brand-dark transition"><?= e(app_lang('view_open_positions')) ?> &rarr;</a>
</div>

<script>
  (function () {
    var btn = document.getElementById('toggle-password');
    var pwd = document.getElementById('password');
    var eye = document.getElementById('icon-eye');
    var eyeOff = document.getElementById('icon-eye-off');
    if (btn && pwd) {
      btn.addEventListener('click', function () {
        var show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        if (eye) eye.classList.toggle('hidden', show);
        if (eyeOff) eyeOff.classList.toggle('hidden', !show);
      });
    }
  })();
</script>
