<?php

namespace Goodport\VkCommunityPublisher;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Minimal HTTP client for VK API calls used by community publishing.
 *
 * Access tokens are never written to logs; every occurrence is replaced
 * with "***" before the request or response is logged.
 */
class VkApiClient
{
    public function __construct(
        private readonly string $apiVersion,
        private readonly string $baseUrl = 'https://api.vk.ru/method',
        private readonly int $timeout = 30,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<int|string, mixed>|int|string
     */
    public function call(string $method, string $accessToken, array $params = []): array|int|string
    {
        if ($accessToken === '') {
            throw new InvalidArgumentException('VK access token must not be empty.');
        }

        $params = array_merge($params, [
            'access_token' => $accessToken,
            'v' => $this->apiVersion,
        ]);

        $url = rtrim($this->baseUrl, '/').'/'.$method;

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($url, $params);

        $this->logCall($method, $params, $accessToken, $response->status(), $response->body());

        $json = $response->json();

        if (! is_array($json)) {
            throw new VkApiException('VK API response is not a JSON object.', 0, $method);
        }

        if (isset($json['error']) && is_array($json['error'])) {
            throw VkApiException::fromVkError($json['error'], $method);
        }

        if (! array_key_exists('response', $json)) {
            throw new VkApiException('VK API response has no "response" field.', 0, $method);
        }

        /** @var array<int|string, mixed>|int|string $payload */
        $payload = $json['response'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function logCall(string $method, array $params, string $accessToken, int $status, string $body): void
    {
        if ($this->logger === null) {
            return;
        }

        $params['access_token'] = '***';
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $safeBody = str_replace($accessToken, '***', $body);

        $this->logger->info(
            "\n===== VK API {$method} =====\n"
            ."POST {$this->baseUrl}/{$method}\n"
            ."Content-Type: application/x-www-form-urlencoded\n\n"
            .$query."\n\n"
            ."----- RESPONSE HTTP {$status} -----\n"
            .$safeBody
        );
    }
}
