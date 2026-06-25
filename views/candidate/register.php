<?php
$errors = $errors ?? [];
$old    = $old ?? [];
?>
<div class="min-h-screen bg-gray-50 flex">
  <!-- Left panel -->
  <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-violet-900 via-violet-800 to-indigo-900 flex-col justify-between p-10">
    <div>
      <div class="flex items-center gap-2 mb-12">
        <div class="w-10 h-10 bg-amber-400 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <span class="text-white font-bold text-xl"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'HireAI') ?></span>
      </div>
      <h2 class="text-3xl font-bold text-white mb-4">Start Your Career Journey</h2>
      <p class="text-violet-200 text-lg leading-relaxed">Join thousands of candidates getting hired faster with AI-powered screening.</p>
    </div>
    <div class="space-y-4">
      <?php foreach (['AI matches you to the right jobs', 'Smart interview coaching', 'Real-time application tracking', 'Personalized offer negotiation'] as $f): ?>
      <div class="flex items-center gap-3 text-violet-200">
        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        </div>
        <span><?= htmlspecialchars($f) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right panel -->
  <div class="flex-1 flex items-center justify-center p-8">
    <div class="w-full max-w-md">
      <div class="lg:hidden flex items-center gap-2 mb-8">
        <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <span class="font-bold text-gray-900"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'HireAI') ?></span>
      </div>

      <h1 class="text-2xl font-bold text-gray-900 mb-2">Create your account</h1>
      <p class="text-gray-500 mb-6">Join as a candidate and discover your next opportunity</p>

      <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
        <?php foreach ($errors as $e): ?>
        <p class="text-red-700 text-sm"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="/register" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            placeholder="John Smith">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            placeholder="john@example.com">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input type="password" name="password" required minlength="8"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            placeholder="At least 8 characters">
        </div>
        <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold py-3 rounded-xl transition-colors">
          Create Account
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-6">
        Already have an account? <a href="/login" class="text-violet-600 font-medium hover:underline">Sign in</a>
      </p>
    </div>
  </div>
</div>
