<?php
/** قاعدة المعرفة: كل اللي الذكاء الاصطناعي يعرفه عن الشركة، والملفات، وقواعد التحويل لموظف */
$wid = (int)ws('id');
$schema = kb_schema();
$canEdit = is_ws_owner() || can('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $json = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    $action = (string)($_POST['action'] ?? '');
    if ($action !== 'kb_test' && !$canEdit) $json(['error' => t('مالكش صلاحية تعدّل قاعدة المعرفة. اطلبها من صاحب الشركة.')], 403);
    $str = function ($v, $max = 4000) { return mb_substr(trim((string)$v), 0, $max); };

    if ($action === 'kb_save') {
        $sec = (string)($_POST['section'] ?? '');
        if (!isset($schema[$sec])) $json(['error' => t('طلب غير صحيح.')], 400);
        $type = $schema[$sec]['type'];
        $in = (array)($_POST['d'] ?? []);
        switch ($type) {
            case 'fields':
                $data = [];
                foreach ($schema[$sec]['fields'] as $fk => $f) $data[$fk] = $str($in[$fk] ?? '', $f[1] === 'area' ? 4000 : 500);
                break;
            case 'rows':
                $rows = [];
                foreach ((array)($in['rows'] ?? []) as $r) {
                    $row = [];
                    foreach (array_keys($schema[$sec]['cols']) as $c) $row[$c] = $str($r[$c] ?? '', 2000);
                    if (implode('', $row) !== '') $rows[] = $row;
                }
                $data = ['rows' => array_slice($rows, 0, 300)];
                break;
            case 'tone':
                $data = ['style' => array_values(array_intersect((array)($in['style'] ?? []), array_keys(kb_tone_options()))), 'dialect' => isset(kb_dialect_options()[$in['dialect'] ?? '']) ? $in['dialect'] : 'match',
                    'emoji' => in_array($in['emoji'] ?? '', ['none', 'light', 'normal'], true) ? $in['emoji'] : 'light', 'notes' => $str($in['notes'] ?? '', 2000)];
                break;
            case 'antihype':
                $data = ['rules' => array_values(array_intersect((array)($in['rules'] ?? []), array_keys(kb_antihype_rules()))), 'words' => $str($in['words'] ?? '', 1000)];
                break;
            case 'visual':
                $data = ['colors' => $str($in['colors'] ?? '', 500), 'style' => $str($in['style'] ?? '', 1500)];
                break;
            case 'escalation':
                $emails = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)($in['email'] ?? ''))));
                foreach ($emails as $em) if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $json(['error' => t('الإيميل مش صحيح: %s', $em)], 422);
                $data = ['email' => implode(', ', $emails), 'triggers' => array_values(array_intersect((array)($in['triggers'] ?? []), array_keys(kb_trigger_options()))),
                    'keywords' => $str($in['keywords'] ?? '', 1000), 'message' => $str($in['message'] ?? '', 600)];
                break;
            default:
                $data = ['text' => $str($in['text'] ?? '', 8000)];
        }
        kb_save($wid, $sec, $data);
        log_activity('kb.save', $sec);
        $json(['ok' => true, 'filled' => kb_filled($data, $type)]);
    }

    if ($action === 'kb_upload' && empty($_FILES['file']) && isset($_POST['client_text'], $_POST['client_name'])) {
        // الملف كبير: المتصفح طلّع النص وبعته لوحده من غير الملف نفسه
        $name = mb_substr(preg_replace('/[\x00-\x1f\/\\\\]+/u', '_', basename((string)$_POST['client_name'])), 0, 180);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $text = kb_clean((string)$_POST['client_text']);
        if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'bmp'], true) || mb_strlen(preg_replace('/\s+/u', '', $text)) < 15) $json(['error' => t('فشل رفع الملف.')], 422);
        $text = mb_substr($text, 0, 200000);
        $method = preg_replace('/[^a-z+]/', '', (string)($_POST['client_method'] ?? '')) ?: 'browser';
        $mime = $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        kb_file_store($wid, $name, $mime, (int)($_POST['client_size'] ?? 0), $method, $text);
        log_activity('kb.file', $name);
        $json(['ok' => true, 'id' => $GLOBALS['kb_last_id'], 'name' => $name, 'chars' => mb_strlen($text), 'method' => $method, 'preview' => mb_substr($text, 0, 600)]);
    }

    if ($action === 'kb_upload') {
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) $json(['error' => t('فشل رفع الملف.')], 422);
        $f = $_FILES['file'];
        if ($f['size'] > 15 * 1048576) $json(['error' => t('الملف أكبر من 15 ميجا.')], 422);
        $name = mb_substr(preg_replace('/[\x00-\x1f\/\\\\]+/u', '_', basename((string)$f['name'])), 0, 180);
        $mime = (string)mime_content_type($f['tmp_name']);
        try {
            [$text, $method] = kb_extract($f['tmp_name'], $name, $mime, isset($_POST['client_text']) ? ['text' => (string)$_POST['client_text'], 'method' => (string)($_POST['client_method'] ?? '')] : null);
        } catch (Throwable $e) {
            $json(['error' => $name . ': ' . $e->getMessage()], 422);
        }
        $text = mb_substr($text, 0, 200000);
        kb_file_store($wid, $name, $mime, (int)$f['size'], $method, $text);
        kb_orig_save($wid, (int)$GLOBALS['kb_last_id'], $f['tmp_name'], $name);
        log_activity('kb.file', $name);
        $json(['ok' => true, 'id' => $GLOBALS['kb_last_id'], 'name' => $name, 'chars' => mb_strlen($text), 'method' => $method, 'preview' => mb_substr($text, 0, 600)]);
    }

    // الملف الأصلي بيترفع على أجزاء صغيرة (عشان حد الرفع في الاستضافة)
    if ($action === 'kb_chunk') {
        $id = (int)($_POST['id'] ?? 0); $i = (int)($_POST['i'] ?? 0);
        $st = db()->prepare("SELECT name FROM kb_files WHERE id = ? AND workspace_id = ?"); $st->execute([$id, $wid]); $name = $st->fetchColumn();
        if ($name === false || empty($_FILES['part']['tmp_name']) || !is_uploaded_file($_FILES['part']['tmp_name'])) $json(['error' => t('فشل رفع الملف.')], 422);
        $d = kb_orig_dir($wid); if (!is_dir($d)) @mkdir($d, 0750, true);
        $part = $d . '/' . $id . '.part';
        if ($i === 0) { kb_orig_del($wid, $id); @unlink($part); }
        if ((is_file($part) ? filesize($part) : 0) + filesize($_FILES['part']['tmp_name']) > 15 * 1048576) { @unlink($part); $json(['error' => t('الملف أكبر من 15 ميجا.')], 422); }
        file_put_contents($part, file_get_contents($_FILES['part']['tmp_name']), FILE_APPEND);
        if (($_POST['last'] ?? '') === '1') rename($part, $d . '/' . $id . '.' . kb_orig_ext((string)($_POST['name'] ?? $name)));
        $json(['ok' => true]);
    }

    if ($action === 'kb_file_raw') {
        $id = (int)($_POST['id'] ?? 0); $st = db()->prepare("SELECT name FROM kb_files WHERE id = ? AND workspace_id = ?"); $st->execute([$id, $wid]);
        $o = $st->fetchColumn() !== false ? kb_orig($wid, $id) : null;
        if (!$o) $json(['error' => 'none'], 404);
        header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff'); header('X-Ext: ' . pathinfo($o, PATHINFO_EXTENSION));
        readfile($o); exit;
    }

    // إعادة قراءة على السيرفر (Word/Excel/نص، أو لو المتصفح ماعرفش يقرا)
    if ($action === 'kb_reread') {
        $id = (int)($_POST['id'] ?? 0); $st = db()->prepare("SELECT name, mime FROM kb_files WHERE id = ? AND workspace_id = ?"); $st->execute([$id, $wid]); $row = $st->fetch();
        $o = $row ? kb_orig($wid, $id) : null; if (!$o) $json(['error' => t('فشل رفع الملف.')], 404);
        $ext = pathinfo($o, PATHINFO_EXTENSION); $nm = preg_replace('/\.[^.]*$/', '', (string)$row['name']) . '.' . $ext;
        try { [$text, $method] = kb_extract($o, $nm, (string)mime_content_type($o), null); } catch (Throwable $e) { $json(['error' => $e->getMessage()], 422); }
        $text = mb_substr($text, 0, 200000);
        db()->prepare("UPDATE kb_files SET method = ?, content = ? WHERE id = ? AND workspace_id = ?")->execute([$method, $text, $id, $wid]);
        $json(['ok' => true, 'id' => $id, 'chars' => mb_strlen($text), 'method' => $method]);
    }

    if ($action === 'kb_file_rename') {
        $name = trim(mb_substr(preg_replace('/[\x00-\x1f\/\\\\]+/u', '_', (string)($_POST['name'] ?? '')), 0, 180));
        if ($name === '') $json(['error' => t('اكتب اسم للملف.')], 422);
        db()->prepare("UPDATE kb_files SET name = ? WHERE id = ? AND workspace_id = ?")->execute([$name, (int)($_POST['id'] ?? 0), $wid]);
        $json(['ok' => true, 'name' => $name]);
    }

    if ($action === 'kb_file_get') {
        $st = db()->prepare("SELECT content FROM kb_files WHERE id = ? AND workspace_id = ?"); $st->execute([(int)($_POST['id'] ?? 0), $wid]);
        $c = $st->fetchColumn(); if ($c === false) $json(['error' => t('طلب غير صحيح.')], 404);
        $json(['ok' => true, 'content' => (string)$c]);
    }

    if (in_array($action, ['kb_file_toggle', 'kb_file_delete', 'kb_file_save'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'kb_file_delete') { db()->prepare("DELETE FROM kb_files WHERE id = ? AND workspace_id = ?")->execute([$id, $wid]); kb_orig_del($wid, $id); }
        elseif ($action === 'kb_file_save') db()->prepare("UPDATE kb_files SET content = ? WHERE id = ? AND workspace_id = ?")->execute([mb_substr((string)($_POST['content'] ?? ''), 0, 200000), $id, $wid]);
        else db()->prepare("UPDATE kb_files SET enabled = ? WHERE id = ? AND workspace_id = ?")->execute([($_POST['on'] ?? '') === '1' ? 1 : 0, $id, $wid]);
        $json(['ok' => true]);
    }

    // ملء قسم بالذكاء الاصطناعي من الملفات والأقسام التانية وبيانات الحساب. بيرجع اقتراح، مش بيحفظ.
    if ($action === 'kb_fill') {
        $sec = (string)($_POST['section'] ?? '');
        if (!isset($schema[$sec])) $json(['error' => t('طلب غير صحيح.')], 400);
        if (!ai_connection()) $json(['error' => t('اربط مزود ذكاء اصطناعي الأول من صفحة التكاملات (OpenRouter أو Anthropic أو OpenAI أو Gemini).')], 422);
        $type = $schema[$sec]['type'];
        $lang = lang() === 'ar' ? 'Modern Standard Arabic (العربية الفصحى المبسطة), never colloquial dialect' : 'English';
        $shape = [
            'text' => '{"text":"..."}',
            'rows' => $sec === 'faq' ? '{"rows":[{"q":"question","a":"approved answer"}]}' : ($sec === 'pos_offers' ? '{"rows":[{"offer":"","audience":"","problem":"","frame":"","pod":"","rtb":""}]}' : '{"rows":[{"name":"","price":"","details":""}]}'),
            'list' => '{"text":"one item per line"}',
            'tone' => '{"style":[' . implode('|', array_map(function ($k) { return '"' . $k . '"'; }, array_keys(kb_tone_options()))) . '],"dialect":"' . implode('|', array_keys(kb_dialect_options())) . '","emoji":"none|light|normal","notes":"..."}',
            'antihype' => '{"rules":[' . implode('|', array_map(function ($k) { return '"' . $k . '"'; }, array_keys(kb_antihype_rules()))) . '],"words":"comma separated banned words"}',
            'visual' => '{"colors":"...","style":"..."}',
            'escalation' => '{"triggers":[' . implode('|', array_map(function ($k) { return '"' . $k . '"'; }, array_keys(kb_trigger_options()))) . '],"keywords":"comma separated","message":"short polite hand-off message to the customer"}',
            'fields' => '',
        ][$type];
        if ($type === 'fields') $shape = json_encode(array_map(function ($f) { return $f[0]; }, $schema[$sec]['fields']), JSON_UNESCAPED_UNICODE) . ' (same keys; each value is the content for that field, empty string if unknown)';
        $guide = [
            'contact' => 'Contact channels. Copy ONLY exact website, emails, phone/WhatsApp numbers, map links and social links found in the sources (account data included). Never invent or guess any of them; leave the field empty if unknown.',
            'hours' => 'Working hours per day. ONLY times written in the sources; never guess. Leave a day empty if unknown. Use one format: 10:00 - 22:00, or مغلق.',
            'company' => 'Describe the company like a professional profile: what it sells, its value, location/branches, hours, payment and delivery. Hard facts only from the sources; placeholders for missing ones.',
            'services' => 'List every product/service. PRICES: copy a price ONLY if it is written in the sources, exactly as written. If not written, leave "price" as an empty string. Never suggest, estimate, round or use a placeholder for prices.',
            'faq' => 'Write 8-15 FAQs. Prefer questions customers actually asked in the recent messages, then the questions customers of this kind of business usually ask. Answer from the sources; where a hard fact is missing use a placeholder instead of guessing.',
            'tone' => 'Infer the tone from how the company writes its posts and replies.',
            'audience' => 'Positioning step 1. Write two short labelled parts: the precise TARGET AUDIENCE (who, age, place, situation) and the PROBLEM/NEED that makes them look for a solution. Base it on posts, messages, documents and the business type; be specific, not generic.',
            'pos_offers' => 'Per-offering positioning. One row per product/service from the SERVICES section and documents (use the exact offering name). For each: the specific target audience of THAT offering, their problem/need, the category it competes in with competitor types, its point of difference, and reasons to believe (only proof found in the sources; otherwise suggest proof to collect, marked as a suggestion). Different offerings usually have different audiences: do not copy the same audience to every row unless it is truly the same.',
            'frame_ref' => 'Positioning step 2 (frame of reference). category = the CATEGORY the customer puts this business in (one line); competitors = competitors or competitor types in that same category, one per line.',
            'pod' => 'Positioning step 3 (point of difference). State the 1-3 most important, customer-relevant differences versus competitors in the same category. Only differences the sources support; mark expert suggestions.',
            'rtb' => 'Positioning step 4 (reasons to believe). List concrete proof that makes the point of difference credible: facts, numbers, certifications, experience, guarantees, reviews — ONLY those found in the sources; never invent numbers or testimonials. If proof is missing, suggest what proof to collect, marked as a suggestion.',
            'focus' => 'Suggest what to promote now, based on the best-performing posts and the services.',
            'never_say' => 'Suggest things the AI must never say for this kind of business: unlisted prices, delivery promises, competitor talk, medical/legal claims, etc. 5-10 lines.',
            'anti_hype' => 'Choose suitable anti-hype rules and suggest banned hype words in the company language.',
            'visual_style' => 'Infer brand colors and image style from documents/posts if mentioned; otherwise suggest a fitting style and say so in "style".',
            'escalation' => 'Choose sensible hand-off triggers for this business, instant hand-off keywords in the customer language (incl. dialect), and a short polite hand-off message.',
        ][$sec] ?? '';
        $files = '';
        try {
            $st = db()->prepare("SELECT name, content FROM kb_files WHERE workspace_id = ? AND enabled = 1 ORDER BY id");
            $st->execute([$wid]);
            foreach ($st->fetchAll() as $f) { $files .= "\n### " . $f['name'] . "\n" . mb_substr((string)$f['content'], 0, 8000); if (mb_strlen($files) > 24000) break; }
        } catch (Throwable $e) {}
        $cur = kb_get($wid)[$sec] ?? [];
        $update = ($_POST['mode'] ?? '') === 'update' && kb_filled($cur, $type); // rewrite/fill: من الأول
        if ($update) $guide .= ' UPDATE MODE: the CURRENT CONTENT below was written by the owner. Keep everything that is still correct (do not remove the owner\'s entries unless the sources clearly contradict them), add what is missing from the sources, fix contradictions, and improve clarity. Return the full updated section.';
        $sys = "You fill one section of a company's knowledge base. You are three experts at once: a senior marketing strategist (positioning, audience, messaging), a professional brand consultant for this industry, and an expert prompt engineer."
            . " This text will be fed back to other AI models as context for replies, posts, ads and strategy, so write it the way an AI uses best: clear, specific, unambiguous and dense."
            . " STYLE: short labelled lines or bullets; one fact per line; concrete nouns and numbers over adjectives; consistent terms (same name for the same thing everywhere); no filler, no slogans, no hype, no repetition of other sections; state rules as direct instructions (\"Do X\" / \"Never Y\") where the section is about behaviour."
            . " Section: \"" . $schema[$sec]['label'] . "\". " . $guide
            . " Act as a senior expert consultant for this kind of business. Use EVERYTHING you know from the SOURCES (documents, the other knowledge-base sections, posts, messages, metrics) and connect the dots."
            . " Where the sources are silent, propose what a seasoned professional would recommend for this specific business and market, so the section is complete and useful."
            . " PRICES ARE ABSOLUTE: never suggest, estimate, compare or invent any price, fee, cost or discount anywhere (including FAQ answers); mention a price only if it is written in the sources, copied exactly. HARD LIMITS: never invent hard facts the business would be held to: prices, phone numbers, addresses, opening hours, delivery times, discounts, guarantees or legal/medical claims. If such a fact is missing, leave it empty or write it as a placeholder like [أضف السعر] / [add price]."
            . " Mark professional assumptions (not from the sources) with \"(" . (lang() === 'ar' ? 'اقتراح' : 'suggested') . ")\" at the end of that line. Write in $lang (keep product names as written)."
            . " Return ONLY valid JSON in exactly this shape (no markdown, no comments; use \\n for line breaks inside strings): " . $shape;
        $user = ($update ? "CURRENT CONTENT OF THIS SECTION (JSON):\n" . json_encode($cur, JSON_UNESCAPED_UNICODE) . "\n\n" : '') . "SOURCES\n\n## EXISTING KNOWLEDGE BASE\n" . (kb_compile($wid) ?: '-') . "\n\n## DOCUMENTS" . ($files ?: "\n-") . "\n\n## ACCOUNT DATA (posts, messages, metrics)\n" . assistant_context(true);
        try {
            $msgs = [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => mb_substr($user, 0, 60000)]];
            $r = ai_chat($msgs, 4000, 'analyze');
            $d = kb_parse_json((string)$r['text']);
            if (!is_array($d)) { // محاولة تانية: نطلب JSON سليم بس
                $msgs[] = ['role' => 'assistant', 'content' => mb_substr((string)$r['text'], 0, 6000)];
                $msgs[] = ['role' => 'user', 'content' => 'That was not valid JSON. Reply again with ONLY the JSON object in the exact shape requested.'];
                $r = ai_chat($msgs, 4000, 'analyze'); $d = kb_parse_json((string)$r['text']);
            }
            if (is_array($d)) { $d = kb_strip_unsourced_prices($d, $user); if (in_array($sec, ['contact', 'hours'], true)) $d = kb_strip_unsourced_facts($d, $user); }
            if (!is_array($d) && in_array($type, ['text', 'list'], true) && trim((string)$r['text']) !== '') $d = kb_strip_unsourced_prices(['text' => trim(preg_replace('/^```\w*|```$/m', '', (string)$r['text']))], $user);
        } catch (Throwable $e) {
            $json(['error' => $e->getMessage()], 502);
        }
        if (!is_array($d)) $json(['error' => t('الموديل لم يرجع اقتراحًا مفهومًا. جرّب مرة أخرى أو موديلًا أقوى.')], 502);
        $log = $r['model'] . (isset($r['cost']) && $r['cost'] !== null ? ' · $' . rtrim(rtrim(number_format((float)$r['cost'], 5), '0'), '.') : '');
        $json(['ok' => true, 'data' => $d, 'meta' => $log, 'mode' => $update ? 'update' : 'fill']);
    }

    if ($action === 'kb_test') {
        if (!ai_connection()) $json(['error' => t('اربط مزود ذكاء اصطناعي الأول من صفحة التكاملات (OpenRouter أو Anthropic أو OpenAI أو Gemini).')], 422);
        $msg = $str($_POST['message'] ?? '', 2000);
        if ($msg === '') $json(['error' => t('اكتب رسالة العميل أولًا.')], 422);
        try {
            $d = kb_decide($wid, $msg, ($_POST['channel'] ?? '') === 'comment' ? 'comment' : 'message');
            $json($d + ['ok' => true]);
        } catch (Throwable $e) {
            $json(['error' => $e->getMessage()], 502);
        }
    }
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $json(['error' => t('الملف أكبر من المسموح على السيرفر (%s ميجا) ومش قادر أقرا نصه في المتصفح. صغّر الملف أو قسّمه.', (string)round(kb_upload_limit() / 1048576, 1))], 413);
    $json(['error' => t('طلب غير صحيح.')], 400);
}

$kb = kb_get($wid);
$st = db()->prepare("SELECT id, name, size, method, enabled, CHAR_LENGTH(content) chars, LEFT(content, 600) preview, created_at FROM kb_files WHERE workspace_id = ? ORDER BY id DESC");
$st->execute([$wid]);
$files = $st->fetchAll();

render(t('قاعدة المعرفة'), 'knowledge', function () use ($schema, $kb, $files, $canEdit) {
    $filled = 0; foreach ($schema as $k => $s) if (kb_filled($kb[$k] ?? [], $s['type'])) $filled++;
    $total = count($schema) + 1; $done = $filled + ($files ? 1 : 0);
    $ico = function ($d) { return '<svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' . $d . '"/></svg>'; };
    $icons = ['files' => 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6', 'company' => 'M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 9h4a1 1 0 011 1v11M3 21h18M8 8h3M8 12h3M8 16h3',
        'services' => 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM4 7.5l8 4.5 8-4.5M12 12v9', 'faq' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1-1.5 2M12 17h.01',
        'tone' => 'M4 5h16v11H9l-5 4zM8 9h8M8 12h5', 'audience' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.5a4 4 0 010 7.5M22 21v-1a6 6 0 00-4-5.6',
        'focus' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z', 'never_say' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM5.6 5.6l12.8 12.8',
        'anti_hype' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4', 'visual_style' => 'M12 21a9 9 0 110-18c5 0 9 3.6 9 8a4 4 0 01-4 4h-2a2 2 0 00-1 3.7A2 2 0 0112 21zM7.5 11h.01M10.5 7.5h.01M15 7.5h.01',
        'escalation' => 'M16 3h5v5M21 3l-7 7M8 21H3v-5M3 21l7-7', 'pos_offers' => 'M4 6h16M4 12h10M4 18h7M17 15l2 2 3-4', 'contact' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z', 'hours' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2', 'frame_ref' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', 'pod' => 'M12 3l2.5 5.5L20 9l-4 4 1 6-5-3-5 3 1-6-4-4 5.5-.5z', 'rtb' => 'M9 12l2 2 4-4M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', 'test' => 'M7 4l12 8-12 8z'];
    $dis = $canEdit ? '' : 'disabled';
    ?>

    <div class="card p-5 mb-5 flex flex-wrap items-center gap-5">
      <div class="relative w-16 h-16 shrink-0">
        <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(var(--ink-200))" stroke-width="3.5"/><circle id="kb-ring" cx="18" cy="18" r="15.5" fill="none" stroke="rgb(var(--brand-600))" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= round(97.4 * $done / $total, 1) ?> 97.4" style="transition:stroke-dasharray .5s"/></svg>
        <div dir="ltr" class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-ink-900"><span id="kb-done"><?= $done ?></span>/<?= $total ?></div>
      </div>
      <div class="flex-1 min-w-[240px]">
        <h2 class="text-lg font-semibold text-ink-900"><?= e(t('كل ما يعرفه الذكاء الاصطناعي عن شركتك')) ?></h2>
        <p class="text-sm text-ink-500 mt-0.5"><?= e(t('يُستخدم في كل الردود والمنشورات والتحليلات. كلما كان أكمل، كانت الردود أدق وأقل خطأ. الحفظ تلقائي.')) ?></p>
      </div>
      <?php if ($canEdit): ?>
        <button type="button" id="kb-ai-pick" class="btn-ghost"><?= ui_icon('spark', 'w-4 h-4 text-brand-600') ?> <span><?= e(t('املأ بالذكاء الاصطناعي')) ?></span></button>
      <?php endif; ?>
      <button type="button" data-kb-go="test" class="btn-primary"><?= $ico($icons['test']) ?> <?= e(t('جرّب الرد')) ?></button>
      <?php if (!$canEdit): ?><p class="w-full text-xs text-amber-700"><?= e(t('عرض فقط. التعديل لصاحب الشركة أو من لديه صلاحية الإعدادات.')) ?></p><?php endif; ?>
    </div>

    <div class="grid lg:grid-cols-[15rem_1fr] gap-5 items-start" id="kb">
      <nav class="card p-2 lg:sticky lg:top-20 flex lg:flex-col gap-1 overflow-x-auto" id="kb-nav">
        <?php $items = ['files' => [t('ملفات الشركة'), $files ? count($files) : 0]] + array_map(function ($s) { return [$s['nav'] ?? $s['label'], null]; }, $schema) + ['test' => [t('جرّب الرد'), null]]; $grp = null; ?>
        <?php foreach ($items as $k => [$l, $n]): $ok = $k === 'files' ? (bool)$files : ($k === 'test' ? null : kb_filled($kb[$k] ?? [], $schema[$k]['type']));
          $g = $k === 'files' ? 'info' : ($schema[$k]['group'] ?? 'end');
          if ($g !== $grp): $grp = $g; if ($g === 'end'): ?><div class="hidden lg:block my-1 border-t border-ink-100"></div><?php else: ?>
          <div class="shrink-0 px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-400 <?= $g !== 'info' ? 'lg:mt-2 lg:border-t lg:border-ink-100 lg:pt-3' : '' ?>"><?= e(kb_groups()[$g]) ?></div>
          <?php endif; endif; ?>
          <button type="button" data-kb-go="<?= $k ?>" class="kb-tab shrink-0 flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-ink-600 hover:bg-ink-100 hover:text-ink-900 text-start transition">
            <?= $ico($icons[$k]) ?><span class="flex-1 whitespace-nowrap"><?= e($l) ?></span>
            <?php if ($ok !== null): ?><span class="kb-dot w-2 h-2 rounded-full <?= $ok ? 'bg-lime-500' : 'bg-ink-300' ?>" data-dot="<?= $k ?>"></span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </nav>

      <div class="min-w-0">
        <!-- الملفات -->
        <section data-kb="files" class="card p-5 hidden">
          <h3 class="font-semibold text-ink-900"><?= e(t('ملفات الشركة')) ?></h3>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('ارفع كتالوج، قائمة أسعار، سياسات، أي ملف عن الشركة. يتحول لنص، والذكاء الاصطناعي يستخدم الأجزاء المناسبة لكل سؤال.')) ?></p>
          <?php if ($canEdit): ?>
          <label id="kb-drop" class="mt-4 flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-ink-300 bg-ink-50 px-4 py-8 text-center cursor-pointer hover:border-brand-400 hover:bg-brand-50/40 transition">
            <span class="w-12 h-12 rounded-2xl bg-white border border-ink-200 text-brand-600 flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg></span>
            <b class="text-sm text-ink-900"><?= e(t('اسحب الملفات هنا أو اضغط للاختيار')) ?></b>
            <span class="text-xs text-ink-500">PDF · Word · Excel · PowerPoint · TXT · CSV · HTML · <?= e(t('صور')) ?> — <?= e(t('حتى 15 ميجا للملف')) ?></span>
            <input type="file" id="kb-file" class="hidden" multiple accept=".pdf,.docx,.xlsx,.pptx,.txt,.md,.csv,.tsv,.json,.html,.htm,.png,.jpg,.jpeg,.webp">
          </label>
          <?php endif; ?>
          <div id="kb-files" class="mt-4 space-y-2">
            <?php foreach ($files as $f): ?>
              <?= kb_file_row($f, $canEdit) ?>
            <?php endforeach; ?>
          </div>
          <p class="text-xs text-ink-400 mt-3"><?= e(t('الملفات الموقوفة لا تُستخدم في الردود. الذكاء الاصطناعي يأخذ أنسب 5 أجزاء من الملفات لكل سؤال، وليس الملف كله.')) ?></p>
        </section>

        <?php foreach ($schema as $k => $s): $d = $kb[$k] ?? []; ?>
        <section data-kb="<?= $k ?>" class="card p-5 hidden">
          <?php $steps = ['audience', 'frame_ref', 'pod', 'rtb']; $n = array_search($k, $steps, true); if ($n !== false): ?>
          <div class="mb-5">
            <div class="flex items-center gap-1.5 mb-3"><?php foreach ($steps as $i => $st): ?><button type="button" data-kb-go="<?= $st ?>" class="flex-1 h-1.5 rounded-full <?= $i <= $n ? 'bg-brand-600' : 'bg-ink-200' ?>" title="<?= e($schema[$st]['label']) ?>"></button><?php endforeach; ?></div>
            <p class="text-[11px] font-semibold text-brand-700"><?= e(t('بيان التموضع')) ?> · <?= e(t('الخطوة %s من 4', (string)($n + 1))) ?></p>
            <div class="kb-pos-stmt mt-2 rounded-xl bg-gradient-to-br from-brand-50 to-cyan-50 border border-brand-100 p-3.5 text-sm text-ink-700 leading-relaxed" dir="auto"></div>
          </div>
          <?php endif; ?>
          <form class="kb-form space-y-4" data-section="<?= $k ?>" data-type="<?= $s['type'] ?>">
            <div class="flex items-start justify-between gap-3">
              <div><h3 class="font-semibold text-ink-900"><?= e($s['label']) ?></h3><p class="text-sm text-ink-500 mt-1"><?= e($s['hint']) ?></p></div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="kb-saved text-xs text-lime-700 opacity-0 transition whitespace-nowrap"><?= e(t('تم الحفظ')) ?> ✓</span>
                <?php if ($canEdit): ?>
                <button type="button" class="kb-undo hidden btn-ghost !py-1.5 !px-2.5 !text-xs"><?= e(t('تراجع')) ?></button>
                <button type="button" class="kb-fill inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-60 transition"><?= ui_icon('spark', 'w-3.5 h-3.5') ?><span data-l-fill="<?= e(t('املأ بالذكاء الاصطناعي')) ?>" data-l-update="<?= e(t('حدّث بالذكاء الاصطناعي')) ?>"><?= e(kb_filled($d, $s['type']) ? t('حدّث بالذكاء الاصطناعي') : t('املأ بالذكاء الاصطناعي')) ?></span></button>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($s['type'] === 'fields'): ?>
              <div class="grid sm:grid-cols-2 gap-x-4 gap-y-3">
                <?php foreach ($s['fields'] as $fk => $f): $span = ($f[3] ?? 1) === 2; ?>
                  <label class="block <?= $span ? 'sm:col-span-2' : '' ?>"><span class="label"><?= e($f[0]) ?></span>
                    <?php if ($f[1] === 'area'): ?><textarea name="d[<?= $fk ?>]" rows="<?= $span ? 3 : 2 ?>" class="input text-sm leading-relaxed" dir="auto" placeholder="<?= e($f[2]) ?>" <?= $dis ?>><?= e($d[$fk] ?? '') ?></textarea>
                    <?php else: ?><input name="d[<?= $fk ?>]" type="<?= in_array($f[1], ['url', 'email', 'tel'], true) ? $f[1] : 'text' ?>" <?= in_array($f[1], ['url', 'email', 'tel'], true) ? 'dir="ltr"' : 'dir="auto"' ?> class="input text-sm" placeholder="<?= e($f[2]) ?>" value="<?= e($d[$fk] ?? '') ?>" <?= $dis ?>><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php elseif ($s['type'] === 'text'): ?>
              <textarea name="d[text]" rows="9" class="input text-sm leading-relaxed" <?= $dis ?> placeholder="<?= e($s['ph'] ?? '') ?>"><?= e($d['text'] ?? '') ?></textarea>
            <?php elseif ($s['type'] === 'rows'): $rows = (array)($d['rows'] ?? []); if (!$rows) $rows = [[]]; ?>
              <div class="kb-rows space-y-2">
                <?php foreach ($rows as $i => $r): ?>
                  <div class="kb-row grid gap-2 <?= $k === 'faq' ? 'sm:grid-cols-[1fr_1.4fr_auto]' : ($k === 'pos_offers' ? 'sm:grid-cols-2 relative pe-11 !p-3 bg-ink-50/40' : 'sm:grid-cols-[1fr_8rem_1.4fr_auto]') ?> items-start rounded-xl border border-ink-200 p-2">
                    <?php foreach ($s['cols'] as $ck => $cl): ?>
                      <?php if ($k === 'pos_offers'): ?><label class="block <?= in_array($ck, ['offer', 'rtb'], true) ? 'sm:col-span-2' : '' ?>"><span class="text-[11px] font-medium text-ink-500"><?= e($cl) ?></span><?php if ($ck === 'offer'): ?><input data-col="offer" list="kb-offers" class="input !mt-1 text-sm font-semibold" placeholder="<?= e(t('اختار من الخدمات أو اكتب اسم')) ?>" value="<?= e($r[$ck] ?? '') ?>" <?= $dis ?>><?php else: ?><textarea data-col="<?= $ck ?>" rows="2" class="input !mt-1 text-sm" <?= $dis ?>><?= e($r[$ck] ?? '') ?></textarea><?php endif; ?></label>
                      <?php elseif ($ck === 'details' || $ck === 'a'): ?><textarea data-col="<?= $ck ?>" rows="1" class="input !mt-0 text-sm" placeholder="<?= e($cl) ?>" <?= $dis ?>><?= e($r[$ck] ?? '') ?></textarea>
                      <?php else: ?><input data-col="<?= $ck ?>" class="input !mt-0 text-sm" placeholder="<?= e($cl) ?>" value="<?= e($r[$ck] ?? '') ?>" <?= $dis ?>><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($canEdit): ?><button type="button" class="kb-del <?= $k === 'pos_offers' ? 'absolute top-2 end-2 ' : '' ?>p-2 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="<?= e(t('حذف')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if ($canEdit): ?><button type="button" class="kb-add btn-ghost !py-1.5 !text-xs">+ <?= e($k === 'faq' ? t('سؤال جديد') : ($k === 'pos_offers' ? t('منتج أو خدمة') : t('خدمة جديدة'))) ?></button><?php endif; ?>
              <?php if ($k === 'pos_offers'): ?><datalist id="kb-offers"><?php foreach ((array)($kb['services']['rows'] ?? []) as $sr): if (trim((string)($sr['name'] ?? '')) !== ''): ?><option value="<?= e(trim($sr['name'])) ?>"><?php endif; endforeach; ?></datalist><?php endif; ?>
            <?php elseif ($s['type'] === 'list'): ?>
              <textarea name="d[text]" rows="8" class="input text-sm" <?= $dis ?> placeholder="<?= e(t("مثال:\nأي سعر غير موجود في قائمة الخدمات\nمواعيد توصيل محددة\nالكلام عن المنافسين\nخصومات غير معلنة")) ?>"><?= e($d['text'] ?? '') ?></textarea>
            <?php elseif ($s['type'] === 'tone'): ?>
              <div><span class="label"><?= e(t('الأسلوب')) ?></span><div class="flex flex-wrap gap-2 mt-2"><?php foreach (kb_tone_options() as $ok => $ol): ?><label class="chip cursor-pointer has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700"><input type="checkbox" name="d[style][]" value="<?= $ok ?>" class="sr-only" <?= in_array($ok, (array)($d['style'] ?? []), true) ? 'checked' : '' ?> <?= $dis ?>><?= e($ol) ?></label><?php endforeach; ?></div></div>
              <div class="grid sm:grid-cols-2 gap-4">
                <label class="block"><span class="label"><?= e(t('اللغة واللهجة')) ?></span><select name="d[dialect]" class="input" <?= $dis ?>><?php foreach (kb_dialect_options() as $ok => $ol): ?><option value="<?= $ok ?>" <?= ($d['dialect'] ?? 'match') === $ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select></label>
                <label class="block"><span class="label"><?= e(t('الإيموجي')) ?></span><select name="d[emoji]" class="input" <?= $dis ?>><?php foreach (['none' => t('بدون'), 'light' => t('قليل'), 'normal' => t('عادي')] as $ok => $ol): ?><option value="<?= $ok ?>" <?= ($d['emoji'] ?? 'light') === $ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select></label>
              </div>
              <label class="block"><span class="label"><?= e(t('ملاحظات إضافية')) ?></span><textarea name="d[notes]" rows="3" class="input text-sm" <?= $dis ?> placeholder="<?= e(t('مثلاً: نادي العميل باسمه، ونقول "حضرتك"، ونختم بـ "في خدمتك دايمًا"')) ?>"><?= e($d['notes'] ?? '') ?></textarea></label>
            <?php elseif ($s['type'] === 'antihype'): ?>
              <div class="space-y-2"><?php foreach (kb_antihype_rules() as $rk => $rl): ?><label class="flex items-start gap-3 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:border-brand-300 has-[:checked]:bg-brand-50/50 has-[:checked]:border-brand-300"><input type="checkbox" name="d[rules][]" value="<?= $rk ?>" class="mt-0.5" <?= in_array($rk, (array)($d['rules'] ?? ($kb ? [] : array_keys(kb_antihype_rules()))), true) ? 'checked' : '' ?> <?= $dis ?>><span class="text-sm text-ink-800"><?= e($rl) ?></span></label><?php endforeach; ?></div>
              <label class="block"><span class="label"><?= e(t('كلمات ممنوعة')) ?></span><input name="d[words]" class="input" value="<?= e($d['words'] ?? '') ?>" <?= $dis ?> placeholder="<?= e(t('مثلاً: خرافي، لا يُصدق، الأفضل في العالم، مضمون 100%')) ?>"></label>
            <?php elseif ($s['type'] === 'visual'): ?>
              <label class="block"><span class="label"><?= e(t('الألوان')) ?></span><input name="d[colors]" class="input" value="<?= e($d['colors'] ?? '') ?>" <?= $dis ?> placeholder="<?= e(t('مثلاً: أزرق #1D4ED8 وأبيض، ولمسات ذهبي')) ?>"></label>
              <div class="flex flex-wrap gap-2" id="kb-swatches"></div>
              <label class="block"><span class="label"><?= e(t('ستايل الصور')) ?></span><textarea name="d[style]" rows="4" class="input text-sm" <?= $dis ?> placeholder="<?= e(t('مثلاً: تصوير منتجات بإضاءة طبيعية، خلفيات فاتحة بسيطة، أشخاص حقيقيين مش رسومات')) ?>"><?= e($d['style'] ?? '') ?></textarea></label>
              <p class="text-xs text-ink-400"><?= e(t('بيتضاف تلقائيًا لكل صورة بتتولد من Higgsfield، لو مفيش ستايل مكتوب في إعدادات Higgsfield.')) ?></p>
            <?php elseif ($s['type'] === 'escalation'): $tr = (array)($d['triggers'] ?? ['angry', 'refund', 'complaint', 'human']); ?>
              <label class="block"><span class="label"><?= e(t('إيميل الموظف المسؤول')) ?></span><input name="d[email]" dir="ltr" class="input" value="<?= e($d['email'] ?? '') ?>" <?= $dis ?> placeholder="support@company.com, manager@company.com"></label>
              <div><span class="label"><?= e(t('حوّل لموظف لما')) ?></span><div class="grid sm:grid-cols-2 gap-2 mt-2"><?php foreach (kb_trigger_options() as $tk => $tl): ?><label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm cursor-pointer hover:border-brand-300 has-[:checked]:bg-brand-50/50 has-[:checked]:border-brand-300"><input type="checkbox" name="d[triggers][]" value="<?= $tk ?>" <?= in_array($tk, $tr, true) ? 'checked' : '' ?> <?= $dis ?>><?= e($tl) ?></label><?php endforeach; ?></div></div>
              <label class="block"><span class="label"><?= e(t('كلمات تحوّل فورًا')) ?></span><input name="d[keywords]" class="input" value="<?= e($d['keywords'] ?? '') ?>" <?= $dis ?> placeholder="<?= e(t('مثلاً: محامي، نصب، أبلغ، فلوسي')) ?>"></label>
              <label class="block"><span class="label"><?= e(t('الرسالة اللي العميل يشوفها وقت التحويل')) ?></span><textarea name="d[message]" rows="2" class="input text-sm" <?= $dis ?> placeholder="<?= e(t('شكرًا لتواصلك، حوّلنا طلبك لأحد الزملاء وهيرد عليك في أقرب وقت.')) ?>"><?= e($d['message'] ?? '') ?></textarea></label>
            <?php endif; ?>
          </form>
        </section>
        <?php endforeach; ?>

        <!-- جرّب الرد -->
        <section data-kb="test" class="card p-5 hidden">
          <h3 class="font-semibold text-ink-900"><?= e(t('جرّب الرد')) ?></h3>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('اكتب رسالة كأنك عميل، وشوف الذكاء الاصطناعي هيرد إزاي أو هيحوّل لموظف. ده نفس اللي هيحصل في الرد التلقائي.')) ?></p>
          <div class="seg mt-4 w-fit" id="kb-ch"><a href="#" class="on" data-ch="message"><?= e(t('رسالة خاصة')) ?></a><a href="#" data-ch="comment"><?= e(t('تعليق عام')) ?></a></div>
          <div class="flex flex-wrap gap-2 mt-3" id="kb-samples">
            <?php foreach ([t('بكام الأوردر وبيوصل إمتى؟'), t('الطلب وصل بايظ وأنا عايز فلوسي'), t('عندكم فروع فين؟'), t('ممكن أكلم حد من الإدارة؟')] as $smp): ?><button type="button" class="chip !py-1 text-xs"><?= e($smp) ?></button><?php endforeach; ?>
          </div>
          <div class="flex gap-2 mt-3 items-end">
            <textarea id="kb-msg" rows="2" class="input !mt-0 flex-1 text-sm" placeholder="<?= e(t('رسالة العميل...')) ?>"></textarea>
            <button type="button" id="kb-run" class="btn-primary"><?= e(t('جرّب')) ?></button>
          </div>
          <div id="kb-out" class="mt-4"></div>
        </section>
      </div>
    </div>

    <script>window.KB = <?= json_encode(['saved' => t('تم الحفظ'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'reply' => t('هيرد'), 'handoff' => t('هيحوّل لموظف'), 'reason' => t('السبب'), 'conf' => t('الثقة'), 'uploading' => t('جاري التحويل لنص...'), 'reading' => t('بيقرا الملف في المتصفح (من غير ذكاء اصطناعي)...'), 'ocr' => t('بيقرا الكلام من الصور (OCR)...'), 'del' => t('حذف الملف؟'), 'saving' => t('بيحفظ نسخة من الملف...'), 'saveText' => t('حفظ النص'), 'editHint' => t('ده النص الكامل اللي بيستخدمه الذكاء الاصطناعي. امسح أي سطور ملهاش معنى واحفظ.'), 'chars' => t('حرف'), 'maxUp' => kb_upload_limit(), 'tooBig' => t('الملف أكبر من المسموح على السيرفر (%s ميجا) ومش قادر أقرا نصه في المتصفح. صغّر الملف أو قسّمه.'), 'shrink' => t('بيصغّر الصورة...'), 'total' => $total, 'filling' => t('بيقرا الملفات وبياناتك...'), 'filled' => t('راجع الاقتراح وعدّل عليه. اتحفظ تلقائيًا، وتقدر تتراجع.'), 'allDone' => t('اتملت %s أقسام'), 'none' => t('مفيش أقسام فاضية.'), 'allUpdated' => t('اتحدّثت %s أقسام'), 'updated' => t('اتحدّث القسم. راجع التغييرات، وتقدر تتراجع.'), 'pickTitle' => t('املأ بالذكاء الاصطناعي'), 'pickHint' => t('اختار الأقسام. الذكاء الاصطناعي هيستخدم كل الملفات والأقسام التانية وبيانات حسابك، ويكمّل الناقص كخبير. كل قسم بيطلع كاقتراح تراجعه.'), 'pAll' => t('الكل'), 'pEmpty' => t('الفاضية'), 'pFull' => t('المليانة'), 'pNone' => t('ولا حاجة'), 'mKeep' => t('كمّل على الموجود'), 'mNew' => t('اكتبه من جديد'), 'mLbl' => t('الأقسام المليانة:'), 'stFull' => t('مليان'), 'stEmpty' => t('فاضي'), 'go' => t('ابدأ'), 'cancel' => t('إلغاء'), 'cols' => $schema['pos_offers']['cols'], 'posTpl' => t('لـ %1، اللي %2، إحنا %3. على عكس المنافسين، %4. والدليل: %5.'), 'posEmpty' => t('[...]'), 'confirmAll' => t('هيتحدّث كل الأقسام المليانة بالذكاء الاصطناعي. تقدر تتراجع في كل قسم لوحده. تكمل؟'),
      'sugTitle' => t('اقتراح الذكاء الاصطناعي'), 'sugHint' => t('مفيش حاجة اتحفظت لسه. راجع الاقتراح، وشيل العلامة من أي عنصر مش عايزه، وبعدين اعتمد.'), 'apply' => t('اعتمد الاقتراح'), 'dismiss' => t('تجاهل'),
      'newTag' => t('جديد'), 'changed' => t('متعدّل'), 'same' => t('زي ما هو'), 'pending' => t('%s اقتراحات مستنية مراجعتك'), 'noChange' => t('الذكاء الاصطناعي ما اقترحش أي تغيير.'),
      'labels' => array_merge(...array_map(function ($x) { return array_map(function ($f) { return $f[0]; }, $x['fields'] ?? []); }, array_values($schema))) + ['style' => t('الأسلوب'), 'dialect' => t('اللغة واللهجة'), 'emoji' => t('الإيموجي'), 'notes' => t('ملاحظات إضافية'), 'rules' => t('القواعد'), 'words' => t('كلمات ممنوعة'), 'colors' => t('الألوان'), 'triggers' => t('حوّل لموظف لما'), 'keywords' => t('كلمات تحوّل فورًا'), 'message' => t('الرسالة اللي العميل يشوفها وقت التحويل')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/extract.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/knowledge.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});

/** صف ملف في القائمة */
function kb_file_row(array $f, bool $canEdit): string
{
    $m = ['pdftotext' => 'PDF', 'pdfparser' => 'PDF', 'pdfjs' => 'PDF', 'pdfjs+ocr' => 'PDF · OCR', 'ocr' => 'OCR', 'claude' => 'PDF · ' . t('ذكاء اصطناعي'), 'basic' => 'PDF', 'vision' => t('صورة') . ' · ' . t('ذكاء اصطناعي'), 'docx' => 'Word', 'xlsx' => 'Excel', 'pptx' => 'PowerPoint', 'html' => 'HTML', 'text' => t('نص')][$f['method']] ?? $f['method'];
    $h = '<div class="kb-file rounded-xl border border-ink-200 ' . ($f['enabled'] ? '' : 'opacity-60') . '" data-id="' . (int)$f['id'] . '">'
        . '<div class="flex items-center gap-3 p-3">'
        . '<span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0"><svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/></svg></span>'
        . '<div class="flex-1 min-w-0"><div class="flex items-center gap-1 min-w-0"><div class="kb-name text-sm font-medium text-ink-900 truncate" dir="auto">' . e($f['name']) . '</div>'
        . ($canEdit ? '<button type="button" class="kb-ren shrink-0 p-1 rounded-md text-ink-400 hover:text-brand-700 hover:bg-brand-50" title="' . e(t('تغيير الاسم')) . '"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/></svg></button>' : '') . '</div><div class="text-xs text-ink-500">' . e($m) . ' · ' . e(t('%s حرف', number_format((int)$f['chars']))) . '</div></div>'
        . '<button type="button" class="kb-view text-xs link">' . e(t('عرض النص')) . '</button>'
        . '<button type="button" class="kb-reread text-xs link" title="' . e(t('يقرا الملف تاني ويبدّل النص القديم بالجديد')) . '">' . e(t('إعادة قراءة')) . '</button>';
    if ($canEdit) {
        $h .= '<label class="relative inline-flex cursor-pointer" title="' . e(t('استخدام في الردود')) . '"><input type="checkbox" class="kb-on sr-only peer" ' . ($f['enabled'] ? 'checked' : '') . '><span class="w-9 h-5 rounded-full bg-ink-300 peer-checked:bg-lime-500 transition after:absolute after:top-0.5 after:start-0.5 after:w-4 after:h-4 after:rounded-full after:bg-[#fff] after:shadow after:transition-transform peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"></span></label>'
            . '<button type="button" class="kb-rm p-1.5 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="' . e(t('حذف')) . '"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button>';
    }
    return $h . '</div><div class="kb-prev hidden border-t border-ink-100 p-3"><pre class="text-xs whitespace-pre-wrap max-h-64 overflow-auto text-ink-700" dir="auto">' . e((string)$f['preview']) . ((int)$f['chars'] > 600 ? "\n…" : '') . '</pre></div></div>';
}
