<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\Tests\Concerns\InteractsWithPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class VkCommunityPublisherTest extends TestCase
{
    use InteractsWithPublisher;

    public function test_publishes_message_without_link(): void
    {
        $this->fakeWallPostResponse(10);

        $published = $this->publisher()->publish($this->makePost());

        $this->assertSame(10, $published->postId);

        $this->assertApiRequestSent('wall.post', fn (Request $request) => (int) $request['owner_id'] === -123456
            && (int) $request['from_group'] === 1
            && $request['message'] === 'Hello'
            && ! isset($request['attachments']));
    }

    public function test_publishes_link_with_preview_photo_and_title(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::response($this->parseResponse(), 200));
        $this->fakeWallPostResponse(11);

        $this->publisher()->publish($this->makePost(linkUrl: 'https://example.com/page'));

        $this->assertApiRequestSent(
            'wall.parseAttachedLink',
            fn (Request $request) => $request['links'] === '["https://example.com/page"]'
        );

        $this->assertApiRequestSent('wall.post', fn (Request $request) => $request['attachments'] === 'https://example.com/page'
            && $request['link_photo_id'] === '-123456_987'
            && $request['link_title'] === 'Page title');
    }

    public function test_uses_separate_parse_url_and_explicit_title(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::response($this->parseResponse(title: 'Parsed'), 200));
        $this->fakeWallPostResponse(12);

        $this->publisher()->publish($this->makePost(
            linkUrl: 'https://example.com/page',
            linkParseUrl: 'https://example.com/page?vk_crop=abc',
            linkTitle: 'Explicit title',
        ));

        $this->assertApiRequestSent(
            'wall.parseAttachedLink',
            fn (Request $request) => $request['links'] === '["https://example.com/page?vk_crop=abc"]'
        );

        $this->assertApiRequestSent('wall.post', fn (Request $request) => $request['attachments'] === 'https://example.com/page'
            && $request['link_title'] === 'Explicit title');
    }

    public function test_retries_parse_when_vk_returns_no_photo(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::sequence()
            ->push($this->emptyParseResponse(), 200)
            ->push($this->parseResponse(), 200));
        $this->fakeWallPostResponse(13);

        $this->publisher()->publish($this->makePost(linkUrl: 'https://example.com/page'));

        $this->assertSame(2, $this->apiRequestCount('wall.parseAttachedLink'));
    }

    public function test_retries_parse_when_photo_is_too_small(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::sequence()
            ->push($this->parseResponse(123, 476, 132), 200)
            ->push($this->parseResponse(), 200));
        $this->fakeWallPostResponse(14);

        $this->publisher()->publish($this->makePost(linkUrl: 'https://example.com/page'));

        $this->assertSame(2, $this->apiRequestCount('wall.parseAttachedLink'));

        $this->assertApiRequestSent('wall.post', fn (Request $request) => $request['link_photo_id'] === '-123456_987');
    }

    public function test_fails_when_parse_photo_remains_too_small(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::response($this->parseResponse(123, 476, 132), 200));

        $this->expectPublishFailure($this->makePost(linkUrl: 'https://example.com/page'));
    }

    public function test_fails_when_parse_never_returns_photo(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::response($this->emptyParseResponse(), 200));

        $this->expectPublishFailure($this->makePost(linkUrl: 'https://example.com/page'));
    }

    public function test_propagates_vk_api_error_from_parse(): void
    {
        $this->fakeApiMethod('wall.parseAttachedLink', Http::response([
            'error' => ['error_code' => 100, 'error_msg' => 'Invalid link'],
        ], 200));

        $this->expectPublishFailure($this->makePost(linkUrl: 'https://example.com/page'), 100);
    }

    public function test_deletes_post(): void
    {
        $this->fakeApiMethod('wall.delete', Http::response(['response' => 1], 200));

        $this->publisher()->delete($this->credentials(), 42);

        $this->assertApiRequestSent('wall.delete', fn (Request $request) => (int) $request['owner_id'] === -123456
            && (int) $request['post_id'] === 42);
    }
}
