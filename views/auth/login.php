<?php
$csrfToken = $_SESSION['_csrf'] ?? '';
?>

<div class="mb-6 text-center">
    <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
    <p class="mt-1 text-sm text-gray-500">Sign in to your account to continue</p>
</div>

<?php if (!empty($_SESSION['flash']['error'])): ?>
    <?php $flashError = $_SESSION['flash']['error']; unset($_SESSION['flash']['error']); ?>
    <div class="mb-5 border-l-4 border-red-400 bg-red-50 p-3 rounded text-sm text-red-800 font-medium">
        <?= htmlspecialchars($flashError) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="mb-5 border-l-4 border-red-400 bg-red-50 p-3 rounded">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $error): ?>
                <li class="text-sm text-red-700"><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="/login" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="space-y-5">
        <!-- Email -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
                autocomplete="email"
                placeholder="you@example.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            >
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <a href="/forgot-password" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium transition-colors">Forgot password?</a>
            </div>
            <div class="relative">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10"
                >
                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                    <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Remember me -->
        <div class="flex items-center">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                value="1"
                <?= !empty($_POST['remember']) ? 'checked' : '' ?>
                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            >
            <label for="remember" class="ml-2 block text-sm text-gray-600">Remember me for 30 days</label>
        </div>

        <!-- Submit -->
        <button
            type="submit"
            class="w-full flex items-center justify-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Sign In
        </button>
    </div>
</form>

<p class="mt-6 text-center text-sm text-gray-500">
    Looking to apply for a job?
    <a href="/register" class="font-medium text-indigo-600 hover:text-indigo-700 transition-colors">Create a candidate account</a>
</p>

<script>
function togglePassword() {
    var input = document.getElementById('password');
    var icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}
</script>
