<?php
/**
 * SociAI OS - Meta Platform Client (Facebook + Instagram via Graph API)
 * Uses Graph API v19.0
 */

declare(strict_types=1);

namespace SociAI\Platforms;

class MetaPlatform extends BasePlatform
{
    private const BASE_URL = 'https://graph.facebook.com/v19.0';

    /** facebook or instagram */
    private string $accountType;

    /** Instagram Business Account ID (when type = instagram) */
    private string $igUserId;

    public function __construct(
        string $accessToken,
        string $accountId,
        string $accountType = 'facebook',
        string $igUserId    = ''
    ) {
        parent::__construct($accessToken, $accountId);
        $this->accountType = $accountType;
        $this->igUserId    = $igUserId;
    }

    // --------------------------------------------------------
    // Comments
    // --------------------------------------------------------

    /**
     * Get page feed comments (Facebook) or media comments (Instagram).
     */
    public function getComments(?string $since = null): array
    {
        $results = [];

        if ($this->accountType === 'facebook') {
            $params = [
                'fields'       => 'comments{id,from,message,created_time},created_time,id',
                'access_token' => $this->accessToken,
            ];
            if ($since !== null) {
                $params['since'] = $since;
            }

            try {
                $data = $this->httpGet(self::BASE_URL . '/' . $this->accountId . '/feed', $params, []);

                foreach ($data['data'] ?? [] as $post) {
                    foreach ($post['comments']['data'] ?? [] as $comment) {
                        $results[] = $this->normaliseInteraction(
                            externalId:   $comment['id'],
                            type:         'comment',
                            authorName:   $comment['from']['name'] ?? 'Unknown',
                            authorId:     $comment['from']['id']   ?? '',
                            content:      $comment['message']      ?? '',
                            createdAt:    $comment['created_time'] ?? date('Y-m-d H:i:s'),
                            authorAvatar: 'https://graph.facebook.com/' . ($comment['from']['id'] ?? '') . '/picture',
                            postId:       $post['id']              ?? '',
                            extra:        ['post_created_time' => $post['created_time'] ?? null]
                        );
                    }
                }
            } catch (PlatformException $e) {
                error_log('[MetaPlatform] getComments (FB) error: ' . $e->getMessage());
            }
        } else {
            // Instagram media comments
            $igId = $this->igUserId ?: $this->accountId;
            try {
                $mediaData = $this->httpGet(self::BASE_URL . '/' . $igId . '/media', [
                    'fields'       => 'id,timestamp',
                    'access_token' => $this->accessToken,
                    'limit'        => 10,
                ]);

                foreach ($mediaData['data'] ?? [] as $media) {
                    $commentData = $this->httpGet(
                        self::BASE_URL . '/' . $media['id'] . '/comments',
                        [
                            'fields'       => 'id,from,text,timestamp,username',
                            'access_token' => $this->accessToken,
                        ]
                    );

                    foreach ($commentData['data'] ?? [] as $comment) {
                        if ($since !== null && strtotime($comment['timestamp'] ?? '') < strtotime($since)) {
                            continue;
                        }
                        $results[] = $this->normaliseInteraction(
                            externalId:   $comment['id'],
                            type:         'comment',
                            authorName:   $comment['username'] ?? ($comment['from']['name'] ?? 'Unknown'),
                            authorId:     $comment['from']['id'] ?? $comment['username'] ?? '',
                            content:      $comment['text']      ?? '',
                            createdAt:    $comment['timestamp'] ?? date('Y-m-d H:i:s'),
                            postId:       $media['id'],
                        );
                    }
                }
            } catch (PlatformException $e) {
                error_log('[MetaPlatform] getComments (IG) error: ' . $e->getMessage());
            }
        }

        return $results;
    }

    // --------------------------------------------------------
    // DMs (Instagram only via Meta)
    // --------------------------------------------------------

    public function getDMs(?string $since = null): array
    {
        $results = [];
        $igId    = $this->igUserId ?: $this->accountId;

        try {
            $params = [
                'fields'       => 'messages{message,from,created_time,id}',
                'platform'     => 'instagram',
                'access_token' => $this->accessToken,
                'limit'        => 25,
            ];

            $data = $this->httpGet(self::BASE_URL . '/' . $igId . '/conversations', $params);

            foreach ($data['data'] ?? [] as $conversation) {
                foreach ($conversation['messages']['data'] ?? [] as $msg) {
                    if ($since !== null && strtotime($msg['created_time'] ?? '') < strtotime($since)) {
                        continue;
                    }
                    $results[] = $this->normaliseInteraction(
                        externalId:   $msg['id'],
                        type:         'dm',
                        authorName:   $msg['from']['name']  ?? 'Unknown',
                        authorId:     $msg['from']['id']    ?? '',
                        content:      $msg['message']       ?? '',
                        createdAt:    $msg['created_time']  ?? date('Y-m-d H:i:s'),
                        extra:        ['conversation_id' => $conversation['id'] ?? null]
                    );
                }
            }
        } catch (PlatformException $e) {
            error_log('[MetaPlatform] getDMs error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // Reply to comment
    // --------------------------------------------------------

    public function replyToComment(string $commentId, string $text): bool
    {
        try {
            $this->httpPost(
                self::BASE_URL . '/' . $commentId . '/comments',
                ['message' => $text, 'access_token' => $this->accessToken]
            );
            return true;
        } catch (PlatformException $e) {
            error_log('[MetaPlatform] replyToComment error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Reply to DM
    // --------------------------------------------------------

    public function replyToDM(string $conversationId, string $text): bool
    {
        try {
            $this->httpPost(
                self::BASE_URL . '/' . $conversationId . '/messages',
                ['message' => ['text' => $text], 'access_token' => $this->accessToken]
            );
            return true;
        } catch (PlatformException $e) {
            error_log('[MetaPlatform] replyToDM error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Publish post
    // --------------------------------------------------------

    public function publishPost(array $content): array
    {
        try {
            if ($this->accountType === 'instagram') {
                return $this->publishInstagramPost($content);
            }
            return $this->publishFacebookPost($content);
        } catch (PlatformException $e) {
            error_log('[MetaPlatform] publishPost error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function publishFacebookPost(array $content): array
    {
        $payload = [
            'message'      => $content['caption'] ?? ($content['text'] ?? ''),
            'access_token' => $this->accessToken,
        ];
        if (!empty($content['media_url'])) {
            $payload['link'] = $content['media_url'];
        }

        $result = $this->httpPost(self::BASE_URL . '/' . $this->accountId . '/feed', $payload);
        return [
            'success'          => isset($result['id']),
            'platform_post_id' => $result['id'] ?? null,
            'url'              => 'https://facebook.com/' . ($result['id'] ?? ''),
        ];
    }

    private function publishInstagramPost(array $content): array
    {
        $igId    = $this->igUserId ?: $this->accountId;
        $caption = $content['caption'] ?? '';
        $imageUrl = $content['image_url'] ?? ($content['media_url'] ?? '');

        if (empty($imageUrl)) {
            throw new PlatformException("Instagram posts require an image_url.");
        }

        // Step 1: Create media container
        $container = $this->httpPost(self::BASE_URL . '/' . $igId . '/media', [
            'image_url'    => $imageUrl,
            'caption'      => $caption,
            'access_token' => $this->accessToken,
        ]);

        if (empty($container['id'])) {
            throw new PlatformException("Instagram media container creation failed: " . json_encode($container));
        }

        // Step 2: Publish container
        $published = $this->httpPost(self::BASE_URL . '/' . $igId . '/media_publish', [
            'creation_id'  => $container['id'],
            'access_token' => $this->accessToken,
        ]);

        return [
            'success'          => isset($published['id']),
            'platform_post_id' => $published['id'] ?? null,
            'url'              => 'https://instagram.com/p/' . ($published['id'] ?? ''),
        ];
    }
}
