<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecruitAI — AI-Powered Recruitment Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #9333ea 100%); }
        .gradient-text { background: linear-gradient(135deg, #a5b4fc, #e879f9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-white text-gray-900">

<!-- Navbar -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-gray-900">RecruitAI</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="#features" class="text-sm font-medium text-gray-600 hover:text-gray-900 hidden sm:block transition-colors">Features</a>
                <a href="/login" class="text-sm font-medium text-gray-700 hover:text-indigo-600 transition-colors px-3 py-2 rounded-lg hover:bg-gray-50">Sign In</a>
                <a href="/login" class="text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors px-4 py-2 rounded-lg shadow-sm">Get Started</a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="gradient-hero pt-32 pb-24 px-4 sm:px-6 lg:px-8 text-white overflow-hidden relative">
    <!-- Background decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-white/5 rounded-full"></div>
        <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-white/5 rounded-full"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-white/3 rounded-full"></div>
    </div>

    <div class="max-w-4xl mx-auto text-center relative z-10">
        <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-sm border border-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-8">
            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
            <span>Powered by Advanced AI</span>
        </div>

        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight mb-6 tracking-tight">
            Recruit Smarter<br>
            <span class="gradient-text">with RecruitAI</span>
        </h1>

        <p class="text-xl sm:text-2xl text-indigo-100 mb-10 max-w-2xl mx-auto leading-relaxed font-light">
            AI-Powered Recruitment Platform that screens, ranks, and engages candidates so your team can focus on what matters most.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/login"
               class="inline-flex items-center justify-center px-8 py-4 bg-white text-indigo-700 font-semibold rounded-xl shadow-lg hover:shadow-xl hover:bg-gray-50 transition-all text-base">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                Sign In
            </a>
            <a href="/login"
               class="inline-flex items-center justify-center px-8 py-4 bg-white/10 backdrop-blur-sm text-white font-semibold rounded-xl border border-white/30 hover:bg-white/20 transition-all text-base">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Post a Job
            </a>
        </div>

        <!-- Stats -->
        <div class="mt-16 grid grid-cols-3 gap-8 max-w-lg mx-auto">
            <div class="text-center">
                <div class="text-3xl font-bold text-white">10x</div>
                <div class="text-sm text-indigo-200 mt-1">Faster Screening</div>
            </div>
            <div class="text-center border-x border-white/20">
                <div class="text-3xl font-bold text-white">85%</div>
                <div class="text-sm text-indigo-200 mt-1">Match Accuracy</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-white">60%</div>
                <div class="text-sm text-indigo-200 mt-1">Cost Reduction</div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section id="features" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Everything You Need to Hire Better</h2>
            <p class="text-lg text-gray-500 max-w-2xl mx-auto">Streamline your entire recruitment process with cutting-edge AI technology.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Card 1: AI Screening -->
            <div class="card-hover bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                <div class="w-14 h-14 bg-indigo-50 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h.01M12 9h.01M15 9h.01"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">AI Screening</h3>
                <p class="text-gray-500 leading-relaxed">
                    Our AI automatically reviews CVs and conducts structured video interviews, scoring candidates against your job requirements with remarkable precision.
                </p>
                <ul class="mt-5 space-y-2">
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Automated CV parsing & scoring
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        AI video interview analysis
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Bias-free candidate ranking
                    </li>
                </ul>
            </div>

            <!-- Card 2: Smart Pipeline -->
            <div class="card-hover bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                <div class="w-14 h-14 bg-purple-50 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Smart Pipeline</h3>
                <p class="text-gray-500 leading-relaxed">
                    Visualize your entire hiring funnel with drag-and-drop Kanban boards. Track every candidate's journey from application to offer in real time.
                </p>
                <ul class="mt-5 space-y-2">
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Customizable hiring stages
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Automated stage progression
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Team collaboration tools
                    </li>
                </ul>
            </div>

            <!-- Card 3: Video Interviews -->
            <div class="card-hover bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                <div class="w-14 h-14 bg-pink-50 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Video Interviews</h3>
                <p class="text-gray-500 leading-relaxed">
                    Conduct asynchronous AI-powered video interviews at scale. Candidates record responses on their schedule; AI delivers instant detailed assessments.
                </p>
                <ul class="mt-5 space-y-2">
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        AI avatar interviewers
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Sentiment & tone analysis
                    </li>
                    <li class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Transcript & scoring reports
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-indigo-600 to-purple-600">
    <div class="max-w-3xl mx-auto text-center text-white">
        <h2 class="text-3xl sm:text-4xl font-bold mb-4">Ready to Transform Your Hiring?</h2>
        <p class="text-indigo-100 text-lg mb-8">Join hundreds of companies hiring faster, smarter, and more fairly.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/login" class="inline-flex items-center justify-center px-8 py-4 bg-white text-indigo-700 font-semibold rounded-xl shadow hover:shadow-md hover:bg-gray-50 transition-all">
                Sign In to Dashboard
            </a>
            <a href="/login" class="inline-flex items-center justify-center px-8 py-4 bg-white/10 text-white font-semibold rounded-xl border border-white/30 hover:bg-white/20 transition-all">
                Post a Job Today
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-base font-bold text-white">RecruitAI</span>
            </div>
            <div class="flex items-center space-x-6 text-sm">
                <a href="#features" class="hover:text-white transition-colors">Features</a>
                <a href="/login" class="hover:text-white transition-colors">Sign In</a>
                <a href="/register" class="hover:text-white transition-colors">Register</a>
            </div>
            <p class="text-sm">&copy; <?= date('Y') ?> RecruitAI. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>
