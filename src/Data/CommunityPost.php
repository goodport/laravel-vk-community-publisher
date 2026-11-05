<?php

namespace Goodport\VkCommunityPublisher\Data;

use InvalidArgumentException;

/**
 * A post to publish on a community wall.
 *
 * Without a link the message is published as is. With a link, VK parses
 * linkParseUrl (or linkUrl) to build the link card and its preview image.
 */
final class CommunityPost
{
    public function __construct(
        public readonly CommunityCredentials $credentials,
        public readonly string $message,
        public readonly ?string $linkUrl = null,
        public readonly ?string $linkParseUrl = null,
        public readonly ?string $linkTitle = null,
    ) {
        if ($linkUrl !== null && $linkUrl === '') {
            throw new InvalidArgumentException('Link URL must not be empty.');
        }

        if ($linkParseUrl !== null && $linkUrl === null) {
            throw new InvalidArgumentException('Link parse URL cannot be used without a link URL.');
        }

        if ($linkParseUrl !== null && $linkParseUrl === '') {
            throw new InvalidArgumentException('Link parse URL must not be empty.');
        }
    }

    public function hasLink(): bool
    {
        return $this->linkUrl !== null;
    }

    public function getParseUrl(): ?string
    {
        return $this->linkParseUrl ?? $this->linkUrl;
    }
}
