<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\Contracts\CommunityPublisher;
use Goodport\VkCommunityPublisher\Data\CommunityCredentials;
use Goodport\VkCommunityPublisher\Data\CommunityPost;
use Goodport\VkCommunityPublisher\VkApiException;
use Illuminate\Support\Facades\Http;

class VkCommunityPublisherTest extends TestCase
{
    private function credentials(): CommunityCredentials
    {
        return new CommunityCredentials(123456, 'test-token');
    }

    private function parsePayload(string $title = 'Page title'): array
    {
        return [
            'response' => [
                'data' => [
                    [
                        'photo' => [
                            'id' => 987,
                            'owner_id' => -123456,
                            'sizes' => [
                                ['type' => 'x', 'width' => 1200, 'height' => 630],
                            ],
                        ],
                        'link' => ['title' => $title],
                    ],
                ],
            ],
        ];
    }

    private function smallPhotoPayload(): array
    {
        return [
            'response' => [
                'data' => [
                    [
                        'photo' => [
                            'id' => 123,
                            'owner_id' => -123456,
                            'sizes' => [
                                ['type' => 'x', 'width' => 476, 'height' => 132],
                            ],
                        ],
                        'link' => ['title' => 'Page title'],
                    ],
                ],
            ],
        ];
    }

    public function test_publishes_message_without_link(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 10]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $published = $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
        ));

        $this->assertSame(10, $published->postId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.vk.com/method/wall.post'
                && (int) $request['owner_id'] === -123456
                && (int) $request['from_group'] === 1
                && $request['message'] === 'Hello'
                && ! isset($request['attachments']);
        });
    }

    public function test_publishes_link_with_preview_photo_and_title(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::response($this->parsePayload(), 200),
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 11]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
            linkUrl: 'https://example.com/page',
        ));

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.vk.com/method/wall.parseAttachedLink') {
                return false;
            }

            return $request['links'] === '["https://example.com/page"]';
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.vk.com/method/wall.post') {
                return false;
            }

            return $request['attachments'] === 'https://example.com/page'
                && $request['link_photo_id'] === '-123456_987'
                && $request['link_title'] === 'Page title';
        });
    }

    public function test_uses_separate_parse_url_and_explicit_title(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::response($this->parsePayload('Parsed'), 200),
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 12]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
            linkUrl: 'https://example.com/page',
            linkParseUrl: 'https://example.com/page?vk_crop=abc',
            linkTitle: 'Explicit title',
        ));

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.vk.com/method/wall.parseAttachedLink') {
                return false;
            }

            return $request['links'] === '["https://example.com/page?vk_crop=abc"]';
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.vk.com/method/wall.post') {
                return false;
            }

            return $request['attachments'] === 'https://example.com/page'
                && $request['link_title'] === 'Explicit title';
        });
    }

    public function test_retries_parse_when_vk_returns_no_photo(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::sequence()
                ->push(['response' => ['data' => []]], 200)
                ->push($this->parsePayload(), 200),
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 13]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
            linkUrl: 'https://example.com/page',
        ));

        $parseRequests = collect(Http::recorded())
            ->filter(fn (array $pair) => $pair[0]->url() === 'https://api.vk.com/method/wall.parseAttachedLink');

        $this->assertCount(2, $parseRequests);
    }

    public function test_retries_parse_when_photo_is_too_small(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::sequence()
                ->push($this->smallPhotoPayload(), 200)
                ->push($this->parsePayload(), 200),
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 14]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
            linkUrl: 'https://example.com/page',
        ));

        $parseRequests = collect(Http::recorded())
            ->filter(fn (array $pair) => $pair[0]->url() === 'https://api.vk.com/method/wall.parseAttachedLink');

        $this->assertCount(2, $parseRequests);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.vk.com/method/wall.post') {
                return false;
            }

            return $request['link_photo_id'] === '-123456_987';
        });
    }

    public function test_fails_when_parse_photo_remains_too_small(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::response($this->smallPhotoPayload(), 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $this->expectException(VkApiException::class);

        try {
            $publisher->publish(new CommunityPost(
                credentials: $this->credentials(),
                message: 'Hello',
                linkUrl: 'https://example.com/page',
            ));
        } finally {
            Http::assertNotSent(fn ($request) => $request->url() === 'https://api.vk.com/method/wall.post');
        }
    }

    public function test_fails_when_parse_never_returns_photo(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::response(['response' => ['data' => []]], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $this->expectException(VkApiException::class);

        try {
            $publisher->publish(new CommunityPost(
                credentials: $this->credentials(),
                message: 'Hello',
                linkUrl: 'https://example.com/page',
            ));
        } finally {
            Http::assertNotSent(fn ($request) => $request->url() === 'https://api.vk.com/method/wall.post');
        }
    }

    public function test_propagates_vk_api_error_from_parse(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.parseAttachedLink' => Http::response([
                'error' => ['error_code' => 100, 'error_msg' => 'Invalid link'],
            ], 200),
        ]);

        $publisher = $this->app->make(CommunityPublisher::class);

        $this->expectException(VkApiException::class);
        $this->expectExceptionCode(100);

        $publisher->publish(new CommunityPost(
            credentials: $this->credentials(),
            message: 'Hello',
            linkUrl: 'https://example.com/page',
        ));
    }

    public function test_deletes_post(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.delete' => Http::response(['response' => 1], 200),
        ]);

        $this->app->make(CommunityPublisher::class)->delete($this->credentials(), 42);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.vk.com/method/wall.delete'
                && (int) $request['owner_id'] === -123456
                && (int) $request['post_id'] === 42;
        });
    }
}
