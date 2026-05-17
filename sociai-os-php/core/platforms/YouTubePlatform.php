<?php
/**
 * SociAI OS - YouTube Platform Client
 * Uses YouTube Data API v3
 */

declare(strict_types=1);

namespace SociAI\Platforms;

class YouTubePlatform extends BasePlatform
{
    private const BASE_URL        = 'https://www.googleapis.com/youtube/v3';
    private const UPLOAD_BASE_URL = 'https://www.googleapis.com/upload/youtube/v3';

    // --------------------------------------------------------
    // Comments
    // --------------------------------------------------------

    public function getComments(?string $since = null): array
    {
        $results = [];

        try {
            $params = [
                'part'                            => 'snippet',
                'allThreadsRelatedToChannelId'    => $this->accountId,
                'maxResults'                      => 50,
                'order'                           => 'time',
            ];
            if ($since !== null) {
                // YouTube doesn't support since_id; we use publishedAfter (ISO 8601)
                $params['publishedAfter'] = (str_contains($since, 'T') ? $since : date('c', strtotime($since)));
            }

            $data = $this->httpGet(self::BASE_URL . '/commentThreads', $params);

            foreach ($data['items'] ?? [] as $item) {
                $top     = $item['snippet']['topLevelComment']['snippet'] ?? [];
                $videoId = $item['snippet']['videoId'] ?? '';
                $results[] = $this->normaliseInteraction(
                    externalId:   $item['id'] ?? '',
                    type:         'comment',
                    authorName:   $top['authorDisplayName']    ?? 'Unknown',
                    authorId:     $top['authorChannelId']['value'] ?? '',
                    content:      $top['textOriginal']         ?? $top['textDisplay'] ?? '',
                    createdAt:    date('Y-m-d H:i:s', strtotime($top['publishedAt'] ?? 'now')),
                    authorAvatar: $top['authorProfileImageUrl'] ?? '',
                    postId:       $videoId,
                    postUrl:      'https://youtube.com/watch?v=' . $videoId,
                    extra:        [
                        'like_count'        => $top['likeCount']            ?? 0,
                        'total_reply_count' => $item['snippet']['totalReplyCount'] ?? 0,
                        'video_id'          => $videoId,
                    ]
                );
            }
        } catch (PlatformException $e) {
            error_log('[YouTubePlatform] getComments error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // DMs — YouTube has no DM API
    // --------------------------------------------------------

    public function getDMs(?string $since = null): array
    {
        return [];
    }

    // --------------------------------------------------------
    // Reply to comment
    // --------------------------------------------------------

    public function replyToComment(string $commentId, string $text): bool
    {
        try {
            $this->httpPost(self::BASE_URL . '/comments?part=snippet', [
                'snippet' => [
                    'parentId'    => $commentId,
                    'textOriginal'=> $text,
                ],
            ]);
            return true;
        } catch (PlatformException $e) {
            error_log('[YouTubePlatform] replyToComment error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Reply to DM — Not supported
    // --------------------------------------------------------

    public function replyToDM(string $conversationId, string $text): bool
    {
        error_log('[YouTubePlatform] DMs not supported via YouTube API.');
        return false;
    }

    // --------------------------------------------------------
    // Publish video (multipart upload)
    // --------------------------------------------------------

    public function publishPost(array $content): array
    {
        try {
            $localPath   = $content['video_path']  ?? '';
            $title       = $content['title']        ?? $content['caption'] ?? 'New Video';
            $description = $content['description']  ?? $content['caption'] ?? '';
            $tags        = $content['tags']          ?? [];
            $category    = $content['category_id']  ?? '22'; // People & Blogs

            if (empty($localPath) || !file_exists($localPath)) {
                return [
                    'success' => false,
                    'error'   => 'YouTube requires a video file. Provide video_path in content array.',
                ];
            }

            $metadata = [
                'snippet' => [
                    'title'       => substr($title, 0, 100),
                    'description' => substr($description, 0, 5000),
                    'tags'        => $tags,
                    'categoryId'  => $category,
                ],
                'status' => [
                    'privacyStatus'  => $content['privacy'] ?? 'public',
                    'selfDeclaredMadeForKids' => false,
                ],
            ];

            // Resumable upload
            $uploadUrl = $this->initResumableUpload($metadata, $localPath);
            if (empty($uploadUrl)) {
                throw new PlatformException("Failed to initialize YouTube resumable upload.");
            }

            $videoId = $this->performResumableUpload($uploadUrl, $localPath);

            return [
                'success'          => $videoId !== null,
                'platform_post_id' => $videoId,
                'url'              => 'https://youtube.com/watch?v=' . $videoId,
            ];
        } catch (PlatformException $e) {
            error_log('[YouTubePlatform] publishPost error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function initResumableUpload(array $metadata, string $localPath): string
    {
        $fileSize = filesize($localPath);
        $mimeType = mime_content_type($localPath) ?: 'video/mp4';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::UPLOAD_BASE_URL . '/videos?uploadType=resumable&part=snippet,status',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($metadata),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Upload-Content-Type: ' . $mimeType,
                'X-Upload-Content-Length: ' . $fileSize,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // Extract Location header
        if (preg_match('/Location: (.+)\r\n/', (string)$response, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function performResumableUpload(string $uploadUrl, string $localPath): ?string
    {
        $fileSize = filesize($localPath);
        $mimeType = mime_content_type($localPath) ?: 'video/mp4';
        $fh       = fopen($localPath, 'rb');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $uploadUrl,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fh,
            CURLOPT_INFILESIZE     => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: ' . $mimeType,
                'Content-Length: ' . $fileSize,
            ],
            CURLOPT_TIMEOUT => 600,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($code !== 200 && $code !== 201) {
            throw new PlatformException("YouTube upload failed with code {$code}: " . substr((string)$raw, 0, 200));
        }

        $decoded = json_decode((string)$raw, true);
        return $decoded['id'] ?? null;
    }
}
