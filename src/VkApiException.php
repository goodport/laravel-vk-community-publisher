<?php

namespace Goodport\VkCommunityPublisher;

use RuntimeException;

/**
 * Error returned by VK API or by the publisher when a post cannot be built.
 */
class VkApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $errorCode = 0,
        public readonly ?string $method = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public static function fromVkError(array $error, ?string $method = null): self
    {
        $code = isset($error['error_code']) && is_numeric($error['error_code'])
            ? (int) $error['error_code']
            : 0;

        $message = isset($error['error_msg']) && is_string($error['error_msg'])
            ? $error['error_msg']
            : 'Unknown VK API error';

        $methodSuffix = $method !== null && $method !== '' ? ' ('.$method.')' : '';

        return new self($message.$methodSuffix, $code, $method);
    }
}
