<?php

namespace Goodport\VkCommunityPublisher\Data;

use InvalidArgumentException;

/**
 * Credentials of the VK community whose wall receives the post.
 */
final class CommunityCredentials
{
    public function __construct(
        public readonly int $groupId,
        public readonly string $accessToken,
    ) {
        if ($groupId <= 0) {
            throw new InvalidArgumentException('VK community id must be a positive integer.');
        }

        if ($accessToken === '') {
            throw new InvalidArgumentException('VK community access token must not be empty.');
        }
    }

    /**
     * Owner id used by wall methods: communities are addressed with a negative id.
     */
    public function ownerId(): int
    {
        return -$this->groupId;
    }
}
