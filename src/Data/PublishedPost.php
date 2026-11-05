<?php

namespace Goodport\VkCommunityPublisher\Data;

final class PublishedPost
{
    public function __construct(
        public readonly int $postId,
    ) {}
}
