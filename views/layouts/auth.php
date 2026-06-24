<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'HireAI') ?> — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'HireAI') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
.gradient-bg { background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 40%, #7c3aed 70%, #9333ea 100%); }
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation: fadeUp 0.5s ease forwards; }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
.float { animation: float 6s ease-in-out infinite; }
input:focus { outline: none; }
</style>
</head>
<body class="min-h-screen bg-white">
<?= $content ?>
</body>
</html>
