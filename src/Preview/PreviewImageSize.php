<?php

namespace Goodport\VkCommunityPublisher\Preview;

/**
 * Calculates a preview size with the VK link card aspect ratio.
 */
final class PreviewImageSize
{
    public const CARD_WIDTH = 600;

    public const CARD_HEIGHT = 315;

    public const MIN_WIDTH = 537;

    public const MIN_HEIGHT = 240;

    /**
     * @return array{width: int, height: int}|null null when the source is smaller than VK accepts.
     */
    public static function resolve(
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        int $maxHeight,
        int $minWidth,
        int $minHeight,
        bool $allowUpscale = false,
    ): ?array {
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return null;
        }

        if ($allowUpscale) {
            return ['width' => $maxWidth, 'height' => $maxHeight];
        }

        $ratio = $maxWidth / $maxHeight;

        $width = min($maxWidth, $sourceWidth);
        $height = (int) round($width / $ratio);

        if ($height > $sourceHeight) {
            $height = min($maxHeight, $sourceHeight);
            $width = (int) round($height * $ratio);
        }

        if ($width < $minWidth || $height < $minHeight) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }
}
