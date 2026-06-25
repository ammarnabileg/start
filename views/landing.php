<?php
$platformName = $_ENV['APP_NAME'] ?? 'HireAI';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($platformName) ?> — AI-Powered Recruitment Platform</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
.gradient-hero { background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 40%, #7c3aed 70%, #8b5cf6 100%); }
.gradient-card { background: linear-gradient(135deg, rgba(109,40,217,.08) 0%, rgba(139,92,246,.04) 100%); }
.float { animation: float 4s ease-in-out infinite; }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
.fade-up { animation: fadeUp .6s ease both; }
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.delay-1 { animation-delay: .1s; }
.delay-2 { animation-delay: .2s; }
.delay-3 { animation-delay: .3s; }
.delay-4 { animation-delay: .4s; }
.glow { box-shadow: 0 0 60px rgba(139,92,246,.35); }
.card-hover { transition: all .3s ease; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(109,40,217,.15); }
::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
</style>
</head>
<body class="bg-white">

<!-- NAVBAR -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur border-b border-gray-100">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-violet-700 rounded-xl flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
      </div>
      <span class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($platformName) ?></span>
    </div>
    <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
      <a href="#features" class="hover:text-violet-700 transition-colors">Features</a>
      <a href="#how" class="hover:text-violet-700 transition-colors">How it Works</a>
      <a href="#stats" class="hover:text-violet-700 transition-colors">Results</a>
    </div>
    <div class="flex items-center gap-3">
      <a href="/login" class="text-sm font-semibold text-gray-700 hover:text-violet-700 transition-colors px-4 py-2">Sign In</a>
      <a href="/register" class="bg-violet-700 hover:bg-violet-800 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition-colors shadow-sm">Apply for Jobs →</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="gradient-hero min-h-screen flex items-center pt-16 relative overflow-hidden">
  <!-- Background decoration -->
  <div class="absolute inset-0 overflow-hidden">
    <div class="absolute -top-40 -right-40 w-[600px] h-[600px] bg-white/5 rounded-full"></div>
    <div class="absolute -bottom-60 -left-40 w-[700px] h-[700px] bg-white/5 rounded-full"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] bg-white/3 rounded-full"></div>
  </div>

  <div class="max-w-7xl mx-auto px-6 py-24 relative z-10">
    <div class="grid lg:grid-cols-2 gap-16 items-center">
      <!-- Left: Text -->
      <div>
        <div class="inline-flex items-center gap-2 bg-white/15 border border-white/20 rounded-full px-4 py-2 mb-8 fade-up">
          <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
          <span class="text-white/90 text-sm font-medium">AI-Powered · Smart Matching · Real-time</span>
        </div>
        <h1 class="text-5xl lg:text-6xl font-black text-white leading-tight mb-6 fade-up delay-1">
          Hire Smarter.<br>
          <span class="text-violet-200">Not Harder.</span>
        </h1>
        <p class="text-violet-100 text-lg leading-relaxed mb-10 fade-up delay-2 max-w-lg">
          Automate your entire screening process with AI-powered interviews, instant candidate evaluation, and intelligent pipeline management.
        </p>
        <div class="flex flex-wrap gap-4 fade-up delay-3">
          <a href="/login" class="bg-white text-violet-800 font-bold rounded-2xl px-8 py-4 text-sm hover:bg-violet-50 transition-all shadow-xl hover:shadow-2xl flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            HR / Company Login
          </a>
          <a href="/register" class="bg-white/15 border border-white/30 text-white font-bold rounded-2xl px-8 py-4 text-sm hover:bg-white/25 transition-all backdrop-blur flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Apply as Candidate
          </a>
        </div>
        <!-- Trust badges -->
        <div class="mt-12 flex flex-wrap gap-3 fade-up delay-4">
          <?php foreach(['AI Interviews','CV Analysis','Smart Pipeline','Offer Management','Multi-Tenant'] as $b): ?>
          <span class="bg-white/10 border border-white/20 text-white/80 text-xs font-medium rounded-full px-3 py-1.5"><?= $b ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right: Dashboard mockup -->
      <div class="hidden lg:block fade-up delay-2">
        <div class="relative">
          <div class="float bg-white/10 backdrop-blur border border-white/20 rounded-3xl p-6 glow">
            <!-- Mock dashboard -->
            <div class="flex items-center gap-2 mb-5">
              <div class="w-3 h-3 rounded-full bg-red-400/70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400/70"></div>
              <div class="w-3 h-3 rounded-full bg-green-400/70"></div>
              <div class="ml-2 h-5 bg-white/10 rounded-full flex-1 max-w-xs"></div>
            </div>
            <!-- Stats row -->
            <div class="grid grid-cols-3 gap-3 mb-4">
              <?php foreach([['248','Active Jobs','violet'],['1.2k','Candidates','blue'],['94%','AI Score','emerald']] as [$n,$l,$c]): ?>
              <div class="bg-white/10 rounded-2xl p-3 text-center">
                <div class="text-white font-black text-xl"><?= $n ?></div>
                <div class="text-white/60 text-xs mt-0.5"><?= $l ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- Pipeline stages -->
            <div class="bg-white/10 rounded-2xl p-4 mb-3">
              <div class="text-white/70 text-xs font-semibold mb-3 uppercase tracking-wider">Recruitment Pipeline</div>
              <div class="space-y-2">
                <?php foreach([['Applied','34','violet-300'],['AI Screening','21','blue-300'],['Qualified','15','emerald-300'],['Interview','8','amber-300'],['Offer','3','rose-300']] as [$stage,$n,$color]): ?>
                <div class="flex items-center gap-3">
                  <span class="text-white/60 text-xs w-24"><?= $stage ?></span>
                  <div class="flex-1 bg-white/10 rounded-full h-2">
                    <div class="bg-<?= $color ?> h-2 rounded-full" style="width:<?= min(100, (int)$n * 3) ?>%"></div>
                  </div>
                  <span class="text-white text-xs font-bold w-6 text-right"><?= $n ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <!-- AI badge -->
            <div class="bg-emerald-500/20 border border-emerald-400/30 rounded-xl px-3 py-2 flex items-center gap-2">
              <div class="w-6 h-6 bg-emerald-500/30 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
              </div>
              <span class="text-emerald-200 text-xs font-medium">AI evaluated 3 new candidates · just now</span>
            </div>
          </div>
          <!-- Floating mini cards -->
          <div class="absolute -top-6 -right-6 bg-white rounded-2xl shadow-2xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 bg-violet-100 rounded-xl flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
              <div class="text-gray-900 text-xs font-bold">Interview Done</div>
              <div class="text-gray-400 text-xs">Score: 92/100</div>
            </div>
          </div>
          <div class="absolute -bottom-6 -left-6 bg-white rounded-2xl shadow-2xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 bg-emerald-100 rounded-xl flex items-center justify-center">
              <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
              <div class="text-gray-900 text-xs font-bold">Offer Accepted</div>
              <div class="text-gray-400 text-xs">Sarah M. → Developer</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<section id="stats" class="py-16 bg-gray-50 border-y border-gray-100">
  <div class="max-w-7xl mx-auto px-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
      <?php foreach([
        ['70%','Less time screening'],
        ['3x','More qualified hires'],
        ['24/7','AI interviews running'],
        ['100%','Bias-free evaluation'],
      ] as [$n,$l]): ?>
      <div>
        <div class="text-4xl font-black text-violet-700 mb-2"><?= $n ?></div>
        <div class="text-gray-500 text-sm font-medium"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-24 bg-white">
  <div class="max-w-7xl mx-auto px-6">
    <div class="text-center mb-16">
      <div class="inline-flex items-center gap-2 bg-violet-50 text-violet-700 rounded-full px-4 py-2 text-sm font-semibold mb-4">
        Everything you need
      </div>
      <h2 class="text-4xl font-black text-gray-900 mb-4">Built for modern recruiting teams</h2>
      <p class="text-gray-500 text-lg max-w-2xl mx-auto">From job posting to offer letter — the entire hiring process in one intelligent platform.</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php
      $features = [
        ['AI Interviews', 'Automated video interviews powered by AI avatars. Candidates interview 24/7, you review results.', 'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'violet'],
        ['Smart Pipeline', 'Visual Kanban board to track every candidate from application to hire. Drag, drop, done.', 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7', 'blue'],
        ['CV Analysis', 'AI scans and scores CVs instantly. Get ranked shortlists without reading a single resume.', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'emerald'],
        ['Offer Management', 'Create, send and track offer letters. Candidates sign digitally — no paperwork.', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'amber'],
        ['Team Collaboration', 'Invite recruiters, hiring managers and interviewers. Role-based access for everyone.', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'rose'],
        ['AI Analytics', 'Real-time dashboards on hiring speed, AI usage, candidate quality and team performance.', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'indigo'],
      ];
      $colors = ['violet'=>'bg-violet-100 text-violet-700','blue'=>'bg-blue-100 text-blue-700','emerald'=>'bg-emerald-100 text-emerald-700','amber'=>'bg-amber-100 text-amber-700','rose'=>'bg-rose-100 text-rose-700','indigo'=>'bg-indigo-100 text-indigo-700'];
      foreach($features as [$title,$desc,$icon,$color]):
      ?>
      <div class="gradient-card border border-gray-100 rounded-3xl p-7 card-hover">
        <div class="w-12 h-12 <?= $colors[$color] ?> rounded-2xl flex items-center justify-center mb-5">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $icon ?>"/></svg>
        </div>
        <h3 class="text-gray-900 font-bold text-lg mb-2"><?= $title ?></h3>
        <p class="text-gray-500 text-sm leading-relaxed"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="py-24 bg-gray-50">
  <div class="max-w-7xl mx-auto px-6">
    <div class="text-center mb-16">
      <h2 class="text-4xl font-black text-gray-900 mb-4">How it works</h2>
      <p class="text-gray-500 text-lg">From zero to hired in 4 simple steps</p>
    </div>
    <div class="grid md:grid-cols-4 gap-8 relative">
      <!-- connector line -->
      <div class="hidden md:block absolute top-10 left-[12.5%] right-[12.5%] h-0.5 bg-gradient-to-r from-violet-200 via-violet-400 to-violet-200"></div>
      <?php
      $steps = [
        ['01','Post a Job','Create a job with AI-generated description and questions in minutes.'],
        ['02','Candidates Apply','Candidates submit their CV and complete an AI video interview 24/7.'],
        ['03','AI Evaluates','Our AI scores, ranks and summarizes every candidate automatically.'],
        ['04','You Hire','Review top candidates, schedule human interviews, and send offers.'],
      ];
      foreach($steps as [$num,$title,$desc]):
      ?>
      <div class="text-center relative">
        <div class="w-20 h-20 bg-violet-700 text-white rounded-3xl flex items-center justify-center text-2xl font-black mx-auto mb-5 shadow-lg shadow-violet-200"><?= $num ?></div>
        <h3 class="font-bold text-gray-900 mb-2"><?= $title ?></h3>
        <p class="text-gray-500 text-sm leading-relaxed"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="gradient-hero py-24 relative overflow-hidden">
  <div class="absolute inset-0">
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-white/5 rounded-full translate-y-1/2"></div>
  </div>
  <div class="max-w-3xl mx-auto px-6 text-center relative z-10">
    <h2 class="text-4xl lg:text-5xl font-black text-white mb-6">Ready to transform your hiring?</h2>
    <p class="text-violet-200 text-lg mb-10">Join hundreds of companies using AI to hire faster and smarter.</p>
    <div class="flex flex-wrap gap-4 justify-center">
      <a href="/login" class="bg-white text-violet-800 font-bold rounded-2xl px-10 py-4 text-sm hover:bg-violet-50 transition-all shadow-xl">
        Login to Dashboard →
      </a>
      <a href="/register" class="bg-white/15 border border-white/30 text-white font-bold rounded-2xl px-10 py-4 text-sm hover:bg-white/25 transition-all">
        Apply for a Job
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="bg-gray-900 text-gray-400 py-12">
  <div class="max-w-7xl mx-auto px-6">
    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-violet-700 rounded-xl flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
        </div>
        <span class="font-bold text-white"><?= htmlspecialchars($platformName) ?></span>
      </div>
      <div class="flex gap-8 text-sm">
        <a href="/login" class="hover:text-white transition-colors">Login</a>
        <a href="/register" class="hover:text-white transition-colors">Register</a>
        <a href="/careers" class="hover:text-white transition-colors">Browse Jobs</a>
      </div>
      <div class="text-sm">© <?= date('Y') ?> <?= htmlspecialchars($platformName) ?>. All rights reserved.</div>
    </div>
  </div>
</footer>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    e.preventDefault();
    document.querySelector(a.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' });
  });
});
</script>
</body>
</html>
