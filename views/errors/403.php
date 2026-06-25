<div class="text-center py-8">
    <!-- Lock icon -->
    <div class="flex justify-center mb-6">
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center">
            <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
    </div>

    <!-- Error code -->
    <div class="text-6xl font-extrabold text-red-100 mb-2 select-none">403</div>

    <!-- Heading -->
    <h1 class="text-2xl font-bold text-gray-900 mb-3">Access Denied</h1>

    <!-- Description -->
    <p class="text-gray-500 text-sm max-w-xs mx-auto mb-8 leading-relaxed">
        You don't have permission to view this page. If you believe this is a mistake, please contact your administrator.
    </p>

    <!-- Actions -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button onclick="history.back()"
                class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Go Back
        </button>
        <a href="/"
           class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Home
        </a>
    </div>
</div>
