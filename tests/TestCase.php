<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\VkCommunityPublisherServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const API_URL = 'https://api.vk.com/method';

    protected function getPackageProviders($app): array
    {
        return [VkCommunityPublisherServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vk-community-publisher', [
            'api_version' => '5.199',
            'base_url' => self::API_URL,
            'timeout' => 30,
            'log_channel' => null,
            'link_parse_attempts' => 3,
            'link_parse_retry_delay_ms' => 0,
            'preview' => [
                'max_width' => 1200,
                'max_height' => 630,
                'min_width' => 537,
                'min_height' => 240,
                'quality' => 92,
            ],
        ]);
    }

    protected function fakeApiMethod(string $method, mixed $response): void
    {
        Http::fake([self::API_URL.'/'.$method => $response]);
    }

    /**
     * @param  callable(Request): bool  $callback
     */
    protected function assertApiRequestSent(string $method, callable $callback): void
    {
        Http::assertSent(function (Request $request) use ($method, $callback): bool {
            return $request->url() === self::API_URL.'/'.$method && $callback($request);
        });
    }

    protected function assertApiRequestNotSent(string $method): void
    {
        Http::assertNotSent(fn (Request $request) => $request->url() === self::API_URL.'/'.$method);
    }

    protected function apiRequestCount(string $method): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $pair) => $pair[0]->url() === self::API_URL.'/'.$method)
            ->count();
    }
}
