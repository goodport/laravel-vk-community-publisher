<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\VkApiClient;
use Goodport\VkCommunityPublisher\VkApiException;
use Illuminate\Support\Facades\Http;
use Psr\Log\AbstractLogger;

class VkApiClientTest extends TestCase
{
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

        Http::fake([
            'https://api.vk.com/method/wall.post' => Http::response(['response' => ['post_id' => 1]], 200),
        ]);

        $client = new VkApiClient('5.199', 'https://api.vk.com/method', 30, $logger);
        $client->call('wall.post', 'super-secret-token', ['message' => 'Hello']);

        $log = implode("\n", $logger->messages);

        $this->assertStringNotContainsString('super-secret-token', $log);
        $this->assertStringContainsString('access_token=', $log);
        $this->assertStringContainsString('%2A%2A%2A', $log);
    }

    public function test_throws_vk_exception_on_api_error(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.post' => Http::response([
                'error' => ['error_code' => 15, 'error_msg' => 'Access denied'],
            ], 200),
        ]);

        $client = new VkApiClient('5.199', 'https://api.vk.com/method');

        $this->expectException(VkApiException::class);
        $this->expectExceptionCode(15);

        $client->call('wall.post', 'token', ['message' => 'Hello']);
    }

    public function test_throws_when_response_has_no_response_field(): void
    {
        Http::fake([
            'https://api.vk.com/method/wall.post' => Http::response(['ok' => true], 200),
        ]);

        $client = new VkApiClient('5.199', 'https://api.vk.com/method');

        $this->expectException(VkApiException::class);

        $client->call('wall.post', 'token', ['message' => 'Hello']);
    }
}
