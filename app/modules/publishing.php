<?php
/* النشر المتقدم: تعديل منشور، طوابير النشر، الرفع بالجملة، الكمبوزر المتقدم */

function pub_get_post(Zernio $z, string $id): array
{
    $r = $z->request('GET', '/v1/posts/' . rawurlencode($id));
    return $r['post'] ?? $r;
}

function pub_update_post(Zernio $z, string $id, array $payload): array
{
    return $z->request('PUT', '/v1/posts/' . rawurlencode($id), [], $payload);
}

function pub_edit_published(Zernio $z, string $id, string $platform, string $content, ?string $accountId = null): array
{
    $body = ['platform' => $platform, 'content' => $content];
    if ($accountId) $body['accountId'] = $accountId;
    return $z->request('POST', '/v1/posts/' . rawurlencode($id) . '/edit', [], $body);
}

function pub_retry_post(Zernio $z, string $id): array
{
    return $z->request('POST', '/v1/posts/' . rawurlencode($id) . '/retry');
}

function pub_unpublish(Zernio $z, string $id, string $platform, ?string $accountId = null): array
{
    $body = ['platform' => $platform];
    if ($accountId) $body['accountId'] = $accountId;
    return $z->request('POST', '/v1/posts/' . rawurlencode($id) . '/unpublish', [], $body);
}

/** المنصات اللي Zernio بيسمح تعديل منشورها بعد النشر */
function pub_editable_platforms(): array
{
    return ['twitter', 'discord', 'facebook', 'reddit', 'linkedin', 'telegram', 'pinterest', 'googlebusiness', 'youtube', 'slack'];
}

/** المنصات اللي ينفع نمسح المنشور من عليها */
function pub_unpublishable_platforms(): array
{
    return ['threads', 'facebook', 'twitter', 'linkedin', 'youtube', 'pinterest', 'reddit', 'bluesky', 'googlebusiness', 'telegram'];
}

/** accountId ممكن يرجع نص أو object */
function pub_acc_id($acc): string
{
    return is_array($acc) ? (string)($acc['_id'] ?? '') : (string)$acc;
}

/* ---------- الطوابير ---------- */

function pub_queues(Zernio $z, string $profileId): array
{
    return $z->request('GET', '/v1/queue/slots', ['profileId' => $profileId, 'all' => 'true'])['queues'] ?? [];
}

function pub_queue_create(Zernio $z, array $body): array
{
    return $z->request('POST', '/v1/queue/slots', [], $body);
}

function pub_queue_update(Zernio $z, array $body): array
{
    return $z->request('PUT', '/v1/queue/slots', [], $body);
}

function pub_queue_delete(Zernio $z, string $profileId, string $queueId): array
{
    return $z->request('DELETE', '/v1/queue/slots', ['profileId' => $profileId, 'queueId' => $queueId]);
}

function pub_queue_preview(Zernio $z, string $profileId, ?string $queueId = null, int $count = 10): array
{
    return $z->request('GET', '/v1/queue/preview', ['profileId' => $profileId, 'queueId' => $queueId, 'count' => $count])['slots'] ?? [];
}

/** أيام الأسبوع بترتيب Zernio (0 = الأحد) */
function pub_weekdays(): array
{
    return [t('الأحد'), t('الاتنين'), t('التلات'), t('الأربع'), t('الخميس'), t('الجمعة'), t('السبت')];
}

/* ---------- الرفع بالجملة ---------- */

function pub_bulk_upload(Zernio $z, string $path, bool $dryRun): array
{
    $q = $dryRun ? '?dryRun=true' : '';
    return $z->request('POST', '/v1/posts/bulk-upload' . $q, [], ['file' => new CURLFile($path, 'text/csv', 'posts.csv')], true);
}

/* ---------- أدوات ---------- */

function pub_validate_post(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/tools/validate/post', [], $payload);
}

function pub_pinterest_boards(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/pinterest-boards')['boards'] ?? [];
}
