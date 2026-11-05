<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\VkApiClient;
use Goodport\VkCommunityPublisher\VkApiException;
use Illuminate\Support\Facades\Http;
use Psr\Log\AbstractLogger;

class VkApiClientTest extends TestCase
{
    private function client(?AbstractLogger $logger = null): VkApiClient
    {
        return new VkApiClient('5.199', self::API_URL, 30, $logger);
    }

    /**
     * @param  array<int|string, mixed>  $body
     */
    private function fakeWallPost(array $body): void
    {
        $this->fakeApiMethod('wall.post', Http::response($body, 200));
    }

    public function test_redacts_access_token_from_logs(): void
    {
        $logger = new class extends AbstractLogger
        {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $this->fakeWallPost(['response' => ['post_id' => 1]]);

        $this->client($logger)->call('wall.post', 'super-secret-token', ['message' => 'Hello']);

        $log = implode("\n", $logger->messages);

        $this->assertStringNotContainsString('super-secret-token', $log);
        $this->assertStringContainsString('access_token=', $log);
        $this->assertStringContainsString('%2A%2A%2A', $log);
    }

    public function test_throws_vk_exception_on_api_error(): void
    {
        $this->fakeWallPost([
            'error' => ['error_code' => 15, 'error_msg' => 'Access denied'],
        ]);

        $this->expectException(VkApiException::class);
        $this->expectExceptionCode(15);

        $this->client()->call('wall.post', 'token', ['message' => 'Hello']);
    }

    public function test_throws_when_response_has_no_response_field(): void
    {
        $this->fakeWallPost(['ok' => true]);

        $this->expectException(VkApiException::class);

        $this->client()->call('wall.post', 'token', ['message' => 'Hello']);
    }
}
