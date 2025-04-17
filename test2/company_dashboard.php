<?php
$users = [
  ["name" => "أحمد علي", "major" => "حاسب آلي", "skills" => "PHP, HTML", "phone" => "0123456789"],
  ["name" => "منى محمد", "major" => "إدارة أعمال", "skills" => "Excel, Word", "phone" => "0112345678"]
];

$filter = $_GET['major'] ?? '';
$balance = 10;

function hide_phone($phone) {
    return substr($phone, 0, 3) . "*****";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>لوحة الشركات</title>
  <link href="https://fonts.googleapis.com/css2?family=Alexandria&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Alexandria', sans-serif; padding: 2rem; background: #f0f0f0; }
    .card { background: white; padding: 1rem; margin-bottom: 1rem; border-radius: 10px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
    .filter { margin-bottom: 2rem; }
  </style>
</head>
<body>
  <h1>مرحباً بك في لوحة الشركات</h1>
  <div class="filter">
    <form method="GET">
      <label>فلترة بالتخصص:</label>
      <input type="text" name="major" value="<?= htmlspecialchars($filter) ?>">
      <button type="submit">بحث</button>
    </form>
  </div>
  <?php foreach ($users as $user): ?>
    <?php if (!$filter || stripos($user['major'], $filter) !== false): ?>
      <div class="card">
        <h2><?= $user['name'] ?></h2>
        <p><strong>التخصص:</strong> <?= $user['major'] ?></p>
        <p><strong>المهارات:</strong> <?= $user['skills'] ?></p>
        <p><strong>الهاتف:</strong>
          <form method="POST" action="show_number.php" style="display:inline;">
            <input type="hidden" name="phone" value="<?= $user['phone'] ?>">
            <button type="submit">اظهار الرقم (خصم 1ج)</button>
          </form>
        </p>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</body>
</html>