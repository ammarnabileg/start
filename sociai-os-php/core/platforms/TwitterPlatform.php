<?php
/**
 * SociAI OS - Twitter/X Platform Client
 * Uses Twitter API v2
 */

declare(strict_types=1);

namespace SociAI\Platforms;

class TwitterPlatform extends BasePlatform
{
    private const BASE_URL = 'https://api.twitter.com/2';

    // --------------------------------------------------------
    // Comments / Mentions
    // --------------------------------------------------------

    /**
     * Get mentions of the authenticated user.
     * @param string|null $since since_id (tweet ID to paginate from)
     */
    public function getComments(?string $since = null): array
    {
        $results = [];

        try {
            $params = [
                'max_results'  => 100,
                'tweet.fields' => 'created_at,author_id,in_reply_to_user_id,referenced_tweets,text',
                'expansions'   => 'author_id',
                'user.fields'  => 'name,username,profile_image_url',
            ];
            if ($since !== null) {
                $params['since_id'] = $since;
            }

            $data = $this->httpGet(
                self::BASE_URL . '/users/' . $this->accountId . '/mentions',
                $params
            );

            // Build user lookup map from includes
            $userMap = [];
            foreach ($data['includes']['users'] ?? [] as $user) {
                $userMap[$user['id']] = $user;
            }

            foreach ($data['data'] ?? [] as $tweet) {
                $author = $userMap[$tweet['author_id']] ?? [];
                $results[] = $this->normaliseInteraction(
                    externalId:   $tweet['id'],
                    type:         'mention',
                    authorName:   $author['name']     ?? 'Unknown',
                    authorId:     $tweet['author_id'] ?? '',
                    content:      $tweet['text']      ?? '',
                    createdAt:    $tweet['created_at'] ?? date('Y-m-d H:i:s'),
                    authorAvatar: $author['profile_image_url'] ?? '',
                    extra:        [
                        'username'              => $author['username'] ?? '',
                        'in_reply_to_user_id'   => $tweet['in_reply_to_user_id'] ?? null,
                        'referenced_tweets'     => $tweet['referenced_tweets'] ?? [],
                    ]
                );
            }
        } catch (PlatformException $e) {
            error_log('[TwitterPlatform] getComments error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // DMs
    // --------------------------------------------------------

    public function getDMs(?string $since = null): array
    {
        $results = [];

        try {
            $params = [
                'dm_event.fields' => 'text,sender_id,created_at,dm_conversation_id',
                'expansions'      => 'sender_id',
                'user.fields'     => 'name,username,profile_image_url',
                'max_results'     => 50,
            ];

            $data = $this->httpGet(self::BASE_URL . '/dm_conversations', $params);

            $userMap = [];
            foreach ($data['includes']['users'] ?? [] as $user) {
                $userMap[$user['id']] = $user;
            }

            foreach ($data['data'] ?? [] as $event) {
                if ($since !== null && strtotime($event['created_at'] ?? '') < strtotime($since)) {
                    continue;
                }
                $sender = $userMap[$event['sender_id']] ?? [];
                $results[] = $this->normaliseInteraction(
                    externalId:   $event['id'] ?? '',
                    type:         'dm',
                    authorName:   $sender['name']     ?? 'Unknown',
                    authorId:     $event['sender_id'] ?? '',
                    content:      $event['text']      ?? '',
                    createdAt:    $event['created_at'] ?? date('Y-m-d H:i:s'),
                    authorAvatar: $sender['profile_image_url'] ?? '',
                    extra:        [
                        'username'            => $sender['username'] ?? '',
                        'dm_conversation_id'  => $event['dm_conversation_id'] ?? null,
                    ]
                );
            }
        } catch (PlatformException $e) {
            error_log('[TwitterPlatform] getDMs error: ' . $e->getMessage());
        }

        return $results;
    }

    // --------------------------------------------------------
    // Reply to tweet (mention)
    // --------------------------------------------------------

    public function replyToComment(string $commentId, string $text): bool
    {
        try {
            $this->httpPost(self::BASE_URL . '/tweets', [
                'text'  => $text,
                'reply' => ['in_reply_to_tweet_id' => $commentId],
            ]);
            return true;
        } catch (PlatformException $e) {
            error_log('[TwitterPlatform] replyToComment error: ' . $e->getMessage());
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
                self::BASE_URL . '/dm_conversations/' . $conversationId . '/messages',
                ['text' => $text]
            );
            return true;
        } catch (PlatformException $e) {
            error_log('[TwitterPlatform] replyToDM error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Publish tweet
    // --------------------------------------------------------

    public function publishPost(array $content): array
    {
        try {
            $payload = ['text' => $content['caption'] ?? ($content['text'] ?? '')];

            // Thread support
            if (!empty($content['thread'])) {
                return $this->publishThread($content['thread']);
            }

            $result = $this->httpPost(self::BASE_URL . '/tweets', $payload);
            $tweetId = $result['data']['id'] ?? null;

            return [
                'success'          => $tweetId !== null,
                'platform_post_id' => $tweetId,
                'url'              => 'https://twitter.com/i/web/status/' . $tweetId,
            ];
        } catch (PlatformException $e) {
            error_log('[TwitterPlatform] publishPost error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function publishThread(array $tweets): array
    {
        $previousId = null;
        $firstId    = null;

        foreach ($tweets as $text) {
            $payload = ['text' => (string)$text];
            if ($previousId !== null) {
                $payload['reply'] = ['in_reply_to_tweet_id' => $previousId];
            }
            $result = $this->httpPost(self::BASE_URL . '/tweets', $payload);
            $tweetId = $result['data']['id'] ?? null;
            if ($tweetId === null) {
                break;
            }
            $firstId    ??= $tweetId;
            $previousId   = $tweetId;
        }

        return [
            'success'          => $firstId !== null,
            'platform_post_id' => $firstId,
            'url'              => 'https://twitter.com/i/web/status/' . $firstId,
        ];
    }

    // --------------------------------------------------------
    // Override default headers — Twitter uses Bearer
    // --------------------------------------------------------

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
}
