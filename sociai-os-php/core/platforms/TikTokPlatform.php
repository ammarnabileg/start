<?php
/**
 * SociAI OS - TikTok Platform Client
 * Uses TikTok Open Platform API v2
 */

declare(strict_types=1);

namespace SociAI\Platforms;

class TikTokPlatform extends BasePlatform
{
    private const BASE_URL = 'https://open.tiktokapis.com/v2';

    // --------------------------------------------------------
    // Comments
    // --------------------------------------------------------

    public function getComments(?string $since = null): array
    {
        $results = [];

        try {
            // Get recent video list first
            $videos = $this->httpPost(self::BASE_URL . '/video/list/', [
                'fields' => ['id', 'create_time', 'title'],
            ]);

            foreach ($videos['data']['videos'] ?? [] as $video) {
                $videoId = $video['id'] ?? '';
                if (empty($videoId)) {
                    continue;
                }

                try {
                    $commentResponse = $this->httpPost(self::BASE_URL . '/video/comment/list/', [
                        'video_id' => $videoId,
                        'count'    => 20,
                    ]);

                    foreach ($commentResponse['data']['comments'] ?? [] as $comment) {
                        $createdAt = date('Y-m-d H:i:s', (int)($comment['create_time'] ?? time()));
                        if ($since !== null && strtotime($createdAt) < strtotime($since)) {
                            continue;
                        }

                        $results[] = $this->normaliseInteraction(
                            externalId:   (string)($comment['id'] ?? ''),
                            type:         'comment',
                            authorName:   $comment['user']['display_name'] ?? 'TikTok User',
                            authorId:     $comment['user']['open_id']     ?? '',
                            content:      $comment['text']                ?? '',
                            createdAt:    $createdAt,
                            authorAvatar: $comment['user']['avatar_url']  ?? '',
                            postId:       $videoId,
                            extra:        [
                                'like_count'        => $comment['like_count']        ?? 0,
                                'reply_count'       => $comment['reply_count']       ?? 0,
                                'parent_comment_id' => $comment['parent_comment_id'] ?? null,
                                'video_id'          => $videoId,
                            ]
                        );
                    }
                } catch (PlatformException $e) {
                    error_log('[TikTokPlatform] getComments for video error: ' . $e->getMessage());
                }
            }
        } catch (PlatformException $e) {
            error_log('[TikTokPlatform] getComments error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // DMs — TikTok API does not expose DMs
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
        // commentId should be "video_id:comment_id" or we need extra data from platform_data
        [$videoId, $parentCommentId] = array_pad(explode(':', $commentId, 2), 2, '');

        if (empty($videoId)) {
            error_log('[TikTokPlatform] replyToComment: missing video_id in commentId: ' . $commentId);
            return false;
        }

        try {
            $this->httpPost(self::BASE_URL . '/video/comment/create/', [
                'video_id'          => $videoId,
                'text'              => $text,
                'parent_comment_id' => $parentCommentId ?: null,
            ]);
            return true;
        } catch (PlatformException $e) {
            error_log('[TikTokPlatform] replyToComment error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Reply to DM — Not supported
    // --------------------------------------------------------

    public function replyToDM(string $conversationId, string $text): bool
    {
        error_log('[TikTokPlatform] DM replies not supported via TikTok API.');
        return false;
    }

    // --------------------------------------------------------
    // Publish video
    // --------------------------------------------------------

    public function publishPost(array $content): array
    {
        try {
            // TikTok requires multipart video upload
            $localPath = $content['video_path'] ?? '';
            if (empty($localPath) || !file_exists($localPath)) {
                // For caption-only or text-based posts, we can't publish to TikTok without a video
                return [
                    'success' => false,
                    'error'   => 'TikTok requires a video file. Provide video_path in content array.',
                ];
            }

            // Step 1: Upload video
            $uploadResult = $this->uploadTikTokVideo($localPath, $content['title'] ?? '', $content['caption'] ?? '');
            return $uploadResult;
        } catch (PlatformException $e) {
            error_log('[TikTokPlatform] publishPost error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function uploadTikTokVideo(string $localPath, string $title, string $caption): array
    {
        // TikTok direct post upload
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE_URL . '/video/upload/',
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: multipart/form-data',
            ],
            CURLOPT_POSTFIELDS => [
                'video'   => new \CURLFile($localPath, 'video/mp4', basename($localPath)),
                'title'   => $title,
                'caption' => $caption,
                'privacy_level' => 'PUBLIC_TO_EVERYONE',
            ],
            CURLOPT_TIMEOUT => 300,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new PlatformException("TikTok upload cURL error: {$err}");
        }

        $decoded = json_decode((string)$raw, true) ?? [];
        if ($code >= 400) {
            throw new PlatformException("TikTok upload error {$code}: " . json_encode($decoded));
        }

        $postId = $decoded['data']['video_id'] ?? null;
        return [
            'success'          => $postId !== null,
            'platform_post_id' => $postId,
            'url'              => null, // TikTok does not return post URL immediately
        ];
    }
}
