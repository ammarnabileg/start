<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Denied — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'HireAI') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
<div class="text-center max-w-md px-4">
  <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
    <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
  </div>
  <h1 class="text-6xl font-bold text-red-500 mb-2">403</h1>
  <h2 class="text-2xl font-semibold text-gray-900 mb-3">Access Denied</h2>
  <p class="text-gray-500 mb-8">You don't have permission to access this page. Contact your administrator if you think this is an error.</p>
  <div class="flex flex-col sm:flex-row gap-3 justify-center">
    <a href="javascript:history.back()" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full text-sm font-medium">Go Back</a>
    <a href="/dashboard" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-medium">Dashboard</a>
  </div>
</div>
</body></html>
