<div class="text-center py-8">
    <!-- Big 404 -->
    <div class="text-8xl font-extrabold text-indigo-100 mb-4 select-none tracking-tight">404</div>

    <!-- Icon -->
    <div class="flex justify-center mb-6 -mt-4">
        <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>

    <!-- Heading -->
    <h1 class="text-2xl font-bold text-gray-900 mb-3">Page Not Found</h1>

    <!-- Description -->
    <p class="text-gray-500 text-sm max-w-sm mx-auto mb-8 leading-relaxed">
        Oops! The page you're looking for doesn't exist or has been moved. Check the URL or head back to where you came from.
    </p>

    <!-- Helpful links -->
    <div class="bg-gray-50 rounded-xl p-5 mb-8 text-left max-w-xs mx-auto">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Try these instead</p>
        <ul class="space-y-2">
            <li>
                <a href="/" class="flex items-center text-sm text-indigo-600 hover:text-indigo-700 font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Home page
                </a>
            </li>
            <li>
                <a href="/login" class="flex items-center text-sm text-indigo-600 hover:text-indigo-700 font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign in
                </a>
            </li>
            <li>
                <a href="/candidate/jobs" class="flex items-center text-sm text-indigo-600 hover:text-indigo-700 font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Browse jobs
                </a>
            </li>
        </ul>
    </div>

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
            Take Me Home
        </a>
    </div>
</div>
