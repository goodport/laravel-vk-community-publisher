<?php

namespace Goodport\VkCommunityPublisher;

use Goodport\VkCommunityPublisher\Contracts\CommunityPublisher;
use Goodport\VkCommunityPublisher\Data\CommunityCredentials;
use Goodport\VkCommunityPublisher\Data\CommunityPost;
use Goodport\VkCommunityPublisher\Data\PublishedPost;
use InvalidArgumentException;

/**
 * Publishes posts on VK community walls.
 *
 * When a post contains a link, VK parses the link preview and the publisher
 * uses the returned photo as link_photo_id. The page behind the link must
 * expose a preview image to VK (vk:image / og:image); this package does not
 * render application pages.
 */
final class VkCommunityPublisher implements CommunityPublisher
{
    public function __construct(
        private readonly VkApiClient $client,
        private readonly int $linkParseAttempts = 3,
        private readonly int $linkParseRetryDelayMs = 500,
        private readonly int $minPreviewWidth = 537,
        private readonly int $minPreviewHeight = 240,
    ) {
        if ($linkParseAttempts < 1) {
            throw new InvalidArgumentException('Link parse attempts must be at least 1.');
        }

        if ($linkParseRetryDelayMs < 0) {
            throw new InvalidArgumentException('Link parse retry delay must not be negative.');
        }

        if ($minPreviewWidth < 1 || $minPreviewHeight < 1) {
            throw new InvalidArgumentException('Minimum preview dimensions must be positive.');
        }
    }

    public function publish(CommunityPost $post): PublishedPost
    {
        if (! $post->hasLink()) {
            return $this->postMessage($post);
        }

        return $this->postLink($post);
    }

    public function delete(CommunityCredentials $credentials, int $postId): void
    {
        $this->client->call('wall.delete', $credentials->accessToken, [
            'owner_id' => $credentials->ownerId(),
            'post_id' => $postId,
        ]);
    }

    private function postMessage(CommunityPost $post): PublishedPost
    {
        $response = $this->client->call('wall.post', $post->credentials->accessToken, [
            'owner_id' => $post->credentials->ownerId(),
            'from_group' => 1,
            'message' => $post->message,
        ]);

        return $this->makePublishedPost($response);
    }

    private function postLink(CommunityPost $post): PublishedPost
    {
        $snippet = $this->parseLinkPreview($post);

        $params = [
            'owner_id' => $post->credentials->ownerId(),
            'from_group' => 1,
            'message' => $post->message,
            'attachments' => $post->linkUrl,
            'link_photo_id' => $snippet['link_photo_id'],
        ];

        $linkTitle = $post->linkTitle ?? $snippet['link_title'];
        if ($linkTitle !== null && $linkTitle !== '') {
            $params['link_title'] = $linkTitle;
        }

        $response = $this->client->call('wall.post', $post->credentials->accessToken, $params);

        return $this->makePublishedPost($response);
    }

    /**
     * @return array{link_photo_id: string, link_title: ?string}
     */
    private function parseLinkPreview(CommunityPost $post): array
    {
        $parseUrl = $post->getParseUrl();
        $links = json_encode([$parseUrl], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($links === false) {
            throw new VkApiException('Link URL cannot be encoded for VK API.', 0, 'wall.parseAttachedLink');
        }

        $snippet = ['link_photo_id' => null, 'link_title' => null, 'photo_suitable' => false];

        for ($attempt = 1; $attempt <= $this->linkParseAttempts; $attempt++) {
            $payload = $this->client->call('wall.parseAttachedLink', $post->credentials->accessToken, [
                'links' => $links,
            ]);

            $snippet = $this->extractSnippet($payload);

            if ($snippet['link_photo_id'] !== null && $snippet['photo_suitable']) {
                break;
            }

            if ($attempt < $this->linkParseAttempts) {
                usleep($this->linkParseRetryDelayMs * 1000 * $attempt);
            }
        }

        if ($snippet['link_photo_id'] === null) {
            throw new VkApiException(
                'VK did not return a preview photo for the link.',
                0,
                'wall.parseAttachedLink'
            );
        }

        if (! $snippet['photo_suitable']) {
            throw new VkApiException(
                'VK returned a preview photo smaller than '.$this->minPreviewWidth.'x'.$this->minPreviewHeight.'.',
                0,
                'wall.parseAttachedLink'
            );
        }

        return $snippet;
    }

    /**
     * @param  array<int|string, mixed>|int|string  $payload
     * @return array{link_photo_id: ?string, link_title: ?string, photo_suitable: bool}
     */
    private function extractSnippet(array|int|string $payload): array
    {
        if (! is_array($payload)) {
            return ['link_photo_id' => null, 'link_title' => null, 'photo_suitable' => false];
        }

        $items = $payload['data'] ?? null;
        if (! is_array($items) || $items === []) {
            return ['link_photo_id' => null, 'link_title' => null, 'photo_suitable' => false];
        }

        $item = reset($items);
        if (! is_array($item)) {
            return ['link_photo_id' => null, 'link_title' => null, 'photo_suitable' => false];
        }

        $photo = $item['photo'] ?? ($item['link']['photo'] ?? null);
        $photoId = $this->extractPhotoId($photo);

        $title = $item['link']['title'] ?? ($item['title'] ?? null);

        return [
            'link_photo_id' => $photoId,
            'link_title' => is_string($title) && $title !== '' ? $title : null,
            'photo_suitable' => $this->isPhotoSuitable($photo),
        ];
    }

    private function isPhotoSuitable(mixed $photo): bool
    {
        if (! is_array($photo)) {
            return false;
        }

        $candidates = [];

        $sizes = $photo['sizes'] ?? null;
        if (is_array($sizes)) {
            foreach ($sizes as $size) {
                if (is_array($size)) {
                    $candidates[] = $size;
                }
            }
        }

        $originalPhoto = $photo['orig_photo'] ?? null;
        if (is_array($originalPhoto)) {
            $candidates[] = $originalPhoto;
        }

        if (isset($photo['width'], $photo['height'])) {
            $candidates[] = $photo;
        }

        foreach ($candidates as $candidate) {
            $width = $candidate['width'] ?? null;
            $height = $candidate['height'] ?? null;

            if (! is_numeric($width) || ! is_numeric($height)) {
                continue;
            }

            $width = (int) $width;
            $height = (int) $height;

            // VK rejects link photos that are too small or portrait-oriented.
            if ($width >= $this->minPreviewWidth && $height >= $this->minPreviewHeight && $width >= $height) {
                return true;
            }
        }

        return false;
    }

    private function extractPhotoId(mixed $photo): ?string
    {
        if (! is_array($photo)) {
            return null;
        }

        $ownerId = $photo['owner_id'] ?? null;
        $photoId = $photo['id'] ?? null;

        if (! is_numeric($ownerId) || ! is_numeric($photoId)) {
            return null;
        }

        return $ownerId.'_'.$photoId;
    }

    /**
     * @param  array<int|string, mixed>|int|string  $response
     */
    private function makePublishedPost(array|int|string $response): PublishedPost
    {
        if (! is_array($response) || ! isset($response['post_id']) || ! is_numeric($response['post_id'])) {
            throw new VkApiException('VK API response has no post_id.', 0, 'wall.post');
        }

        return new PublishedPost((int) $response['post_id']);
    }
}
