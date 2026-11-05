<?php

namespace Goodport\VkCommunityPublisher\Contracts;

use Goodport\VkCommunityPublisher\Data\CommunityCredentials;
use Goodport\VkCommunityPublisher\Data\CommunityPost;
use Goodport\VkCommunityPublisher\Data\PublishedPost;

interface CommunityPublisher
{
    public function publish(CommunityPost $post): PublishedPost;

    public function delete(CommunityCredentials $credentials, int $postId): void;
}
