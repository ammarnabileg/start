<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('ai.use');

$pageTitle = 'AI Copilot';
$uid = auth_id();

// Get/create active conversation
$convId = (int)($_GET['c'] ?? 0);
if (!$convId) {
    $latest = db_one('SELECT id FROM ' . tbl('ai_conversations') . ' WHERE user_id = :u ORDER BY updated_at DESC LIMIT 1', ['u' => $uid]);
    $convId = $latest ? (int)$latest['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $message = trim($_POST['message'] ?? '');
    if ($message === '') { redirect('modules/ai/'); }

    if (!$convId) {
        $convId = (int)db_insert(tbl('ai_conversations'), [
            'user_id' => $uid,
            'title'   => mb_substr($message, 0, 60),
        ]);
    }

    db_insert(tbl('ai_messages'), [
        'conversation_id' => $convId,
        'role'            => 'user',
        'content'         => $message,
    ]);

    // Send to Claude API if configured, else return a helpful stub.
    $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '';
    $response = '';

    if ($apiKey) {
        $context = ai_build_context($uid);
        $messages = ai_load_messages($convId);
        $response = ai_call_claude($apiKey, $context, $messages);
    } else {
        $response = "🤖 لتفعيل المساعد الفعلي، أضف متغير البيئة `ANTHROPIC_API_KEY` على السيرفر.\n\n"
                  . "بعد التفعيل، سأستطيع:\n"
                  . "• تلخيص حالة عملائك وصفقاتك\n"
                  . "• اقتراح أولويات اليوم\n"
                  . "• كتابة رسائل متابعة\n"
                  . "• تحليل أداء فريقك\n\n"
                  . "(رسالتك المُستلمة: «" . mb_substr($message, 0, 100) . "»)";
    }

    db_insert(tbl('ai_messages'), [
        'conversation_id' => $convId,
        'role'            => 'assistant',
        'content'         => $response,
    ]);

    redirect('modules/ai/?c=' . $convId);
}

$conversations = db_all('SELECT * FROM ' . tbl('ai_conversations') . ' WHERE user_id = :u ORDER BY updated_at DESC LIMIT 30', ['u' => $uid]);
$messages = $convId ? db_all('SELECT * FROM ' . tbl('ai_messages') . ' WHERE conversation_id = :c ORDER BY created_at ASC', ['c' => $convId]) : [];

function ai_build_context(int $uid): string {
    $myDeals = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('deals') . ' WHERE owner_id = :u AND stage NOT IN ("won","lost")', ['u' => $uid]);
    $myTasks = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND status IN ("open","in_progress")', ['u' => $uid]);
    $overdue = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND status IN ("open","in_progress") AND due_at < NOW()', ['u' => $uid]);
    return "أنت Hala AI، مساعد ذكي داخل CRM شركة هلا كارير. السياق الحالي للمستخدم: {$myDeals} صفقة مفتوحة، {$myTasks} مهمة، منها {$overdue} متأخرة. أجب بالعربية باختصار وعملية.";
}

function ai_load_messages(int $convId): array {
    $rows = db_all('SELECT role, content FROM ' . tbl('ai_messages') . ' WHERE conversation_id = :c ORDER BY created_at ASC LIMIT 30', ['c' => $convId]);
    return array_map(fn($r) => ['role' => $r['role'], 'content' => $r['content']], $rows);
}

function ai_call_claude(string $apiKey, string $systemPrompt, array $messages): string {
    $payload = [
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 1024,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) return '⚠️ خطأ اتصال: ' . curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return "⚠️ Claude API HTTP $code";
    $data = json_decode($raw, true);
    return $data['content'][0]['text'] ?? '⚠️ رد غير مفهوم';
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 h-[calc(100vh-180px)]">
  <div class="bg-white rounded-xl border p-3 overflow-y-auto">
    <a href="<?= url('modules/ai/') ?>" class="block bg-emerald-600 text-white text-center py-2 rounded-lg mb-3 text-sm">+ محادثة جديدة</a>
    <?php foreach ($conversations as $c): ?>
      <a href="?c=<?= $c['id'] ?>" class="block px-3 py-2 rounded text-sm hover:bg-gray-100 <?= $convId == $c['id'] ? 'bg-emerald-50 font-medium' : '' ?>">
        <?= e($c['title']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="lg:col-span-3 bg-white rounded-xl border flex flex-col">
    <div class="flex-1 overflow-y-auto p-6 space-y-4">
      <?php if (!$messages): ?>
        <div class="text-center text-gray-500 mt-20">
          <div class="text-5xl mb-3">🤖</div>
          <p class="font-bold">مرحبًا، أنا Hala AI Copilot</p>
          <p class="text-sm mt-1">اسألني عن صفقاتك، عملائك، أو أولويات اليوم.</p>
        </div>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <div class="flex <?= $m['role'] === 'user' ? 'justify-start' : 'justify-end' ?>">
          <div class="max-w-2xl rounded-2xl p-4 <?= $m['role'] === 'user' ? 'bg-emerald-600 text-white' : 'bg-gray-100' ?>">
            <div class="text-xs opacity-70 mb-1"><?= $m['role'] === 'user' ? 'أنت' : '🤖 Hala AI' ?></div>
            <div class="whitespace-pre-line text-sm"><?= e($m['content']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <form method="post" class="border-t p-4 flex gap-2">
      <?= csrf_field() ?>
      <input name="message" required placeholder="اسأل عن أي حاجة..." class="flex-1 px-4 py-3 border rounded-lg" autofocus>
      <button class="bg-emerald-600 text-white px-6 py-3 rounded-lg hover:bg-emerald-700">إرسال</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
