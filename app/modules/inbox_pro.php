<?php
/**
 * Zernio helpers for the advanced inbox, comment automations and workflows.
 * Plain functions taking Zernio $z (app/zernio.php is not edited).
 */

function ip_enc(string $s): string
{
    return rawurlencode($s);
}

/* ---------- Conversations ---------- */

function ip_conversations(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/inbox/conversations', $filters);
}

function ip_search_conversations(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/inbox/conversations/search', $filters);
}

function ip_conversation(Zernio $z, string $convId, string $accountId): array
{
    $r = $z->request('GET', '/v1/inbox/conversations/' . ip_enc($convId), ['accountId' => $accountId]);
    return $r['data'] ?? $r;
}

function ip_set_status(Zernio $z, string $convId, string $accountId, string $status): array
{
    return $z->request('PUT', '/v1/inbox/conversations/' . ip_enc($convId), [], ['accountId' => $accountId, 'status' => $status]);
}

function ip_delete_conversation(Zernio $z, string $convId, string $accountId): array
{
    return $z->request('DELETE', '/v1/inbox/conversations/' . ip_enc($convId), ['accountId' => $accountId]);
}

function ip_accept_request(Zernio $z, string $convId, string $accountId, string $message): array
{
    return $z->request('POST', '/v1/inbox/conversations/' . ip_enc($convId) . '/accept', [], ['accountId' => $accountId, 'message' => $message]);
}

/* ---------- Messages ---------- */

function ip_edit_message(Zernio $z, string $convId, string $msgId, string $accountId, string $text): array
{
    return $z->request('PATCH', '/v1/inbox/conversations/' . ip_enc($convId) . '/messages/' . ip_enc($msgId), [], ['accountId' => $accountId, 'text' => $text]);
}

function ip_delete_message(Zernio $z, string $convId, string $msgId, string $accountId): array
{
    return $z->request('DELETE', '/v1/inbox/conversations/' . ip_enc($convId) . '/messages/' . ip_enc($msgId), ['accountId' => $accountId]);
}

function ip_react(Zernio $z, string $convId, string $msgId, string $accountId, string $emoji): array
{
    return $z->request('POST', '/v1/inbox/conversations/' . ip_enc($convId) . '/messages/' . ip_enc($msgId) . '/reactions', [], ['accountId' => $accountId, 'emoji' => $emoji]);
}

function ip_unreact(Zernio $z, string $convId, string $msgId, string $accountId): array
{
    return $z->request('DELETE', '/v1/inbox/conversations/' . ip_enc($convId) . '/messages/' . ip_enc($msgId) . '/reactions', ['accountId' => $accountId]);
}

/** Platforms where the API allows editing / deleting an own message. */
function ip_can_edit(string $platform): bool
{
    return in_array($platform, ['telegram', 'imessage'], true);
}

function ip_can_delete(string $platform): bool
{
    return in_array($platform, ['telegram', 'twitter', 'bluesky', 'reddit', 'imessage'], true);
}

function ip_can_react(string $platform): bool
{
    return in_array($platform, ['telegram', 'whatsapp', 'instagram', 'facebook', 'imessage'], true);
}

/* ---------- Labels (Facebook Messenger only) ---------- */

function ip_labels(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/inbox/labels', ['accountId' => $accountId])['labels'] ?? [];
}

function ip_create_label(Zernio $z, string $accountId, string $name): array
{
    return $z->request('POST', '/v1/inbox/labels', [], ['accountId' => $accountId, 'name' => $name]);
}

function ip_delete_label(Zernio $z, string $accountId, string $labelId): array
{
    return $z->request('DELETE', '/v1/inbox/labels/' . ip_enc($labelId), ['accountId' => $accountId]);
}

function ip_conversation_labels(Zernio $z, string $convId, string $accountId): array
{
    return $z->request('GET', '/v1/inbox/conversations/' . ip_enc($convId) . '/labels', ['accountId' => $accountId])['labels'] ?? [];
}

function ip_add_conv_label(Zernio $z, string $convId, string $accountId, string $labelId): array
{
    return $z->request('POST', '/v1/inbox/conversations/' . ip_enc($convId) . '/labels', [], ['accountId' => $accountId, 'labelId' => $labelId]);
}

function ip_remove_conv_label(Zernio $z, string $convId, string $accountId, string $labelId): array
{
    return $z->request('DELETE', '/v1/inbox/conversations/' . ip_enc($convId) . '/labels/' . ip_enc($labelId), ['accountId' => $accountId]);
}

/* ---------- Comments ---------- */

function ip_private_reply(Zernio $z, string $postId, string $commentId, string $accountId, string $message): array
{
    return $z->request('POST', '/v1/inbox/comments/' . ip_enc($postId) . '/' . ip_enc($commentId) . '/private-reply', [], ['accountId' => $accountId, 'message' => $message]);
}

/* ---------- Comment automations ---------- */

function ip_automations(Zernio $z): array
{
    return $z->request('GET', '/v1/comment-automations', ['profileId' => zernio_profile()])['automations'] ?? [];
}

function ip_automation(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/comment-automations/' . ip_enc($id));
}

function ip_create_automation(Zernio $z, array $body): array
{
    return $z->request('POST', '/v1/comment-automations', [], $body);
}

function ip_update_automation(Zernio $z, string $id, array $body): array
{
    return $z->request('PATCH', '/v1/comment-automations/' . ip_enc($id), [], $body);
}

function ip_delete_automation(Zernio $z, string $id): array
{
    return $z->request('DELETE', '/v1/comment-automations/' . ip_enc($id));
}

/** Platforms that can DM a commenter; the others only post a public reply. */
function ip_automation_dm_platform(string $platform): bool
{
    return in_array($platform, ['instagram', 'facebook'], true);
}

function ip_automation_platforms(): array
{
    return ['instagram', 'facebook', 'tiktok', 'threads', 'linkedin', 'youtube'];
}

/** "a, b\nc" -> ['a','b','c'] */
function ip_split_keywords(string $s): array
{
    $parts = preg_split('/[,،\n]+/u', $s) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return array_values(array_unique($out));
}

/* ---------- Workflows ---------- */

function ip_workflows(Zernio $z, ?string $status = null): array
{
    return $z->request('GET', '/v1/workflows', ['profileId' => zernio_profile(), 'status' => $status, 'limit' => 100])['workflows'] ?? [];
}

function ip_workflow(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/workflows/' . ip_enc($id))['workflow'] ?? [];
}

function ip_workflow_runs(Zernio $z, string $id): array
{
    return $z->request('GET', '/v1/workflows/' . ip_enc($id) . '/executions', ['limit' => 20]);
}

function ip_workflow_action(Zernio $z, string $id, string $action): array
{
    return $z->request('POST', '/v1/workflows/' . ip_enc($id) . '/' . ($action === 'activate' ? 'activate' : 'pause'));
}

function ip_delete_workflow(Zernio $z, string $id): array
{
    return $z->request('DELETE', '/v1/workflows/' . ip_enc($id));
}

function ip_workflow_platforms(): array
{
    return ['whatsapp', 'instagram', 'facebook', 'telegram', 'twitter', 'bluesky', 'reddit', 'tiktok'];
}

/**
 * Simple 2-node workflow: trigger (DM keyword or comment) -> send a text message.
 * Created as draft by the API.
 */
function ip_simple_workflow_body(string $profileId, array $account, string $name, string $triggerType, array $keywords, string $matchType, string $text): array
{
    $trigger = ['triggerType' => $triggerType];
    $mt = $keywords ? $matchType : 'any';
    if ($triggerType === 'comment') {
        $trigger['comment'] = ['keywords' => $keywords, 'matchType' => $mt];
    } else {
        $trigger['keywords'] = $keywords;
        $trigger['matchType'] = $mt;
    }
    return [
        'profileId' => $profileId,
        'accountId' => $account['_id'],
        'platform' => $account['platform'],
        'name' => $name,
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger', 'label' => 'Trigger', 'config' => $trigger, 'position' => ['x' => 0, 'y' => 0]],
            ['id' => 'reply', 'type' => 'send_message', 'label' => 'Reply', 'config' => ['messageType' => 'text', 'text' => $text], 'position' => ['x' => 0, 'y' => 160]],
        ],
        'edges' => [['id' => 'e1', 'source' => 'trigger', 'target' => 'reply']],
        'entryNodeId' => 'trigger',
    ];
}

/** Connected accounts for the selected profile, optionally only some platforms. */
function ip_accounts(Zernio $z, array $platforms = []): array
{
    $out = [];
    foreach ($z->allAccounts(zernio_profile()) as $a) {
        if (empty($a['_id'])) continue;
        if ($platforms && !in_array($a['platform'] ?? '', $platforms, true)) continue;
        $out[] = $a;
    }
    return $out;
}

function ip_account_label(array $a): string
{
    return platform_label($a['platform'] ?? '') . ' · ' . trim((string)($a['displayName'] ?? $a['username'] ?? $a['_id']));
}
