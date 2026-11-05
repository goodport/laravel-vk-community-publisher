<?php

namespace Goodport\VkCommunityPublisher\Preview;

use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

/**
 * Crops an image to the VK link card size and encodes it as JPEG.
 */
final class PreviewImageFactory
{
    private readonly ImageManager $manager;

    public function __construct(
        private readonly int $maxWidth = 1200,
        private readonly int $maxHeight = 630,
        private readonly int $minWidth = 537,
        private readonly int $minHeight = 240,
        private readonly int $quality = 92,
        ?ImageManager $manager = null,
    ) {
        $this->manager = $manager ?? new ImageManager(['driver' => 'gd']);
    }

    /**
     * @param  bool  $allowUpscale  true for sources that must be stretched, e.g. legacy avatars.
     */
    public function make(string $binary, bool $allowUpscale = false): ?PreviewImage
    {
        if ($binary === '') {
            throw new InvalidArgumentException('Image binary must not be empty.');
        }

        try {
            $image = $this->manager->make($binary);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Image binary cannot be decoded.', 0, $exception);
        }

        $size = PreviewImageSize::resolve(
            $image->width(),
            $image->height(),
            $this->maxWidth,
            $this->maxHeight,
            $this->minWidth,
            $this->minHeight,
            $allowUpscale,
        );

        if ($size === null) {
            $image->destroy();

            return null;
        }

        $image->fit($size['width'], $size['height']);
        $encoded = (string) $image->encode('jpg', $this->quality);
        $width = $image->width();
        $height = $image->height();
        $image->destroy();

        return new PreviewImage($encoded, $width, $height);
    }
}
