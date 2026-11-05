<?php

namespace Goodport\VkCommunityPublisher\Tests\Concerns;

use Goodport\VkCommunityPublisher\Contracts\CommunityPublisher;
use Goodport\VkCommunityPublisher\Data\CommunityCredentials;
use Goodport\VkCommunityPublisher\Data\CommunityPost;
use Goodport\VkCommunityPublisher\VkApiException;
use Illuminate\Support\Facades\Http;

trait InteractsWithPublisher
{
    protected function credentials(): CommunityCredentials
    {
        return new CommunityCredentials(123456, 'test-token');
    }

    protected function publisher(): CommunityPublisher
    {
        return $this->app->make(CommunityPublisher::class);
    }

    protected function makePost(
        ?string $linkUrl = null,
        ?string $linkParseUrl = null,
        ?string $linkTitle = null,
        string $message = 'Hello',
    ): CommunityPost {
        return new CommunityPost(
            credentials: $this->credentials(),
            message: $message,
            linkUrl: $linkUrl,
            linkParseUrl: $linkParseUrl,
            linkTitle: $linkTitle,
        );
    }

    protected function fakeWallPostResponse(int $postId): void
    {
        $this->fakeApiMethod('wall.post', Http::response(['response' => ['post_id' => $postId]], 200));
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseResponse(
        int $photoId = 987,
        int $width = 1200,
        int $height = 630,
        string $title = 'Page title',
    ): array {
        return [
            'response' => [
                'data' => [
                    [
                        'photo' => [
                            'id' => $photoId,
                            'owner_id' => -123456,
                            'sizes' => [
                                ['type' => 'x', 'width' => $width, 'height' => $height],
                            ],
                        ],
                        'link' => ['title' => $title],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyParseResponse(): array
    {
        return ['response' => ['data' => []]];
    }

    protected function expectPublishFailure(CommunityPost $post, ?int $code = null): void
    {
        $this->expectException(VkApiException::class);

        if ($code !== null) {
            $this->expectExceptionCode($code);
        }

        try {
            $this->publisher()->publish($post);
        } finally {
            $this->assertApiRequestNotSent('wall.post');
        }
    }
}
