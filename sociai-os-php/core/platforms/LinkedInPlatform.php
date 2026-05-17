<?php
/**
 * SociAI OS - LinkedIn Platform Client
 * Uses LinkedIn API v2
 */

declare(strict_types=1);

namespace SociAI\Platforms;

class LinkedInPlatform extends BasePlatform
{
    private const BASE_URL = 'https://api.linkedin.com/v2';

    // --------------------------------------------------------
    // Comments
    // --------------------------------------------------------

    public function getComments(?string $since = null): array
    {
        $results = [];

        try {
            // Get recent UGC posts first
            $postsData = $this->httpGet(self::BASE_URL . '/ugcPosts', [
                'q'       => 'authors',
                'authors' => 'List(urn:li:organization:' . $this->accountId . ')',
                'count'   => 10,
            ]);

            foreach ($postsData['elements'] ?? [] as $post) {
                $postUrn = $post['id'] ?? '';
                if (empty($postUrn)) {
                    continue;
                }

                try {
                    $commentsData = $this->httpGet(
                        self::BASE_URL . '/socialActions/' . urlencode($postUrn) . '/comments',
                        ['count' => 50]
                    );

                    foreach ($commentsData['elements'] ?? [] as $comment) {
                        $actor  = $comment['actor'] ?? '';
                        $msgObj = $comment['message'] ?? [];
                        $text   = is_array($msgObj) ? ($msgObj['text'] ?? '') : (string)$msgObj;
                        $ts     = $comment['created']['time'] ?? time() * 1000;
                        $createdAt = date('Y-m-d H:i:s', (int)($ts / 1000));

                        if ($since !== null && strtotime($createdAt) < strtotime($since)) {
                            continue;
                        }

                        $results[] = $this->normaliseInteraction(
                            externalId: $comment['id'] ?? '',
                            type:       'comment',
                            authorName: $this->extractLinkedInName($actor),
                            authorId:   $actor,
                            content:    $text,
                            createdAt:  $createdAt,
                            postId:     $postUrn,
                            extra:      ['post_urn' => $postUrn, 'actor' => $actor]
                        );
                    }
                } catch (PlatformException $e) {
                    error_log('[LinkedInPlatform] getComments for post error: ' . $e->getMessage());
                }
            }
        } catch (PlatformException $e) {
            error_log('[LinkedInPlatform] getComments error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // DMs — LinkedIn does not support DMs via API
    // --------------------------------------------------------

    public function getDMs(?string $since = null): array
    {
        // LinkedIn API does not expose DMs via the standard Marketing API
        return [];
    }

    // --------------------------------------------------------
    // Reply to comment
    // --------------------------------------------------------

    public function replyToComment(string $commentId, string $text): bool
    {
        // commentId format: "urn:li:ugcPostComment:(POST_URN,COMMENT_ID)"
        // We need the parent post URN
        try {
            // Extract post URN from comment ID if possible
            $postUrn = $this->extractPostUrnFromComment($commentId);
            if (empty($postUrn)) {
                error_log('[LinkedInPlatform] Could not extract post URN from commentId: ' . $commentId);
                return false;
            }

            $this->httpPost(
                self::BASE_URL . '/socialActions/' . urlencode($postUrn) . '/comments',
                [
                    'actor'          => 'urn:li:organization:' . $this->accountId,
                    'message'        => ['text' => $text],
                    'parentComment'  => $commentId,
                ]
            );
            return true;
        } catch (PlatformException $e) {
            error_log('[LinkedInPlatform] replyToComment error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Reply to DM — Not supported
    // --------------------------------------------------------

    public function replyToDM(string $conversationId, string $text): bool
    {
        error_log('[LinkedInPlatform] DM replies not supported via API.');
        return false;
    }

    // --------------------------------------------------------
    // Publish post
    // --------------------------------------------------------

    public function publishPost(array $content): array
    {
        try {
            $text = $content['caption'] ?? ($content['text'] ?? '');

            $ugcPost = [
                'author'          => 'urn:li:organization:' . $this->accountId,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => ['text' => $text],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            // Add media if provided
            if (!empty($content['media_url'])) {
                $ugcPost['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
                $ugcPost['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                    [
                        'status'         => 'READY',
                        'originalUrl'    => $content['media_url'],
                        'description'    => ['text' => $content['description'] ?? ''],
                        'title'          => ['text' => $content['title'] ?? 'Post'],
                    ],
                ];
            }

            $result = $this->httpPost(self::BASE_URL . '/ugcPosts', $ugcPost);
            $postId = $result['id'] ?? null;

            return [
                'success'          => $postId !== null,
                'platform_post_id' => $postId,
                'url'              => 'https://linkedin.com/feed/update/' . urlencode($postId ?? ''),
            ];
        } catch (PlatformException $e) {
            error_log('[LinkedInPlatform] publishPost error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    // Helpers
    // --------------------------------------------------------

    private function extractLinkedInName(string $urn): string
    {
        // urn:li:person:xxxx → try to resolve, but just return URN-based name
        if (preg_match('/urn:li:(\w+):(.+)/', $urn, $m)) {
            return ucfirst($m[1]) . ' ' . substr($m[2], 0, 8);
        }
        return $urn;
    }

    private function extractPostUrnFromComment(string $commentId): string
    {
        // commentId might be "urn:li:ugcPostComment:(POST_URN,COMMENT_HASH)"
        if (preg_match('/\((.+?),/', $commentId, $m)) {
            return $m[1];
        }
        return '';
    }
}
