<?php
$csrfToken = $_SESSION['_csrf'] ?? '';
$old = $_POST ?? [];
?>

<div class="mb-6 text-center">
    <h2 class="text-2xl font-bold text-gray-900">Create your account</h2>
    <p class="mt-1 text-sm text-gray-500">Join RecruitAI and start applying for jobs today</p>
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

<form method="POST" action="/register" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="space-y-5">
        <!-- Name row -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">First name</label>
                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                    required
                    autocomplete="given-name"
                    placeholder="Jane"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                >
            </div>
            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">Last name</label>
                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                    required
                    autocomplete="family-name"
                    placeholder="Smith"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                >
            </div>
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                required
                autocomplete="email"
                placeholder="you@example.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            >
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
            <div class="relative">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10"
                >
                <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-1')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                    <svg id="eye-icon-1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <!-- Password strength -->
            <div class="mt-2 flex space-x-1" id="strength-bars">
                <div class="h-1 flex-1 rounded bg-gray-200" id="bar-1"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="bar-2"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="bar-3"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="bar-4"></div>
            </div>
            <p id="strength-label" class="text-xs text-gray-400 mt-1"></p>
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
            <div class="relative">
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    autocomplete="new-password"
                    placeholder="Repeat your password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10"
                >
                <button type="button" onclick="togglePasswordVisibility('password_confirm', 'eye-icon-2')" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                    <svg id="eye-icon-2" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <p id="match-msg" class="text-xs mt-1 hidden"></p>
        </div>

        <!-- Terms -->
        <div class="flex items-start">
            <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 mt-0.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
            <label for="terms" class="ml-2 text-sm text-gray-600">
                I agree to the <a href="/terms" class="text-indigo-600 hover:underline">Terms of Service</a> and <a href="/privacy" class="text-indigo-600 hover:underline">Privacy Policy</a>
            </label>
        </div>

        <!-- Submit -->
        <button
            type="submit"
            class="w-full flex items-center justify-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Create Account
        </button>
    </div>
</form>

<p class="mt-6 text-center text-sm text-gray-500">
    Already have an account?
    <a href="/login" class="font-medium text-indigo-600 hover:text-indigo-700 transition-colors">Sign in</a>
</p>

<script>
function togglePasswordVisibility(inputId, iconId) {
    var input = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}

// Password strength
document.getElementById('password').addEventListener('input', function() {
    var val = this.value;
    var score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var bars = ['bar-1','bar-2','bar-3','bar-4'];
    var colors = ['bg-red-400', 'bg-orange-400', 'bg-yellow-400', 'bg-green-500'];
    var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    var labelColors = ['', 'text-red-500', 'text-orange-500', 'text-yellow-600', 'text-green-600'];

    bars.forEach(function(b, i) {
        var el = document.getElementById(b);
        el.className = 'h-1 flex-1 rounded ' + (i < score ? colors[score - 1] : 'bg-gray-200');
    });

    var lbl = document.getElementById('strength-label');
    lbl.textContent = val.length ? labels[score] : '';
    lbl.className = 'text-xs mt-1 ' + (val.length ? labelColors[score] : 'text-gray-400');
});

// Password match
document.getElementById('password_confirm').addEventListener('input', function() {
    var p1 = document.getElementById('password').value;
    var p2 = this.value;
    var msg = document.getElementById('match-msg');
    if (!p2) { msg.classList.add('hidden'); return; }
    msg.classList.remove('hidden');
    if (p1 === p2) {
        msg.textContent = 'Passwords match';
        msg.className = 'text-xs mt-1 text-green-600';
    } else {
        msg.textContent = 'Passwords do not match';
        msg.className = 'text-xs mt-1 text-red-500';
    }
});
</script>
