<?php

namespace Goodport\VkCommunityPublisher\Preview;

final class PreviewImage
{
    public function __construct(
        public readonly string $contents,
        public readonly int $width,
        public readonly int $height,
        public readonly string $mimeType = 'image/jpeg',
    ) {}
}
