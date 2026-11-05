<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\Preview\PreviewImageFactory;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;

class PreviewImageFactoryTest extends TestCase
{
    private ImageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new ImageManager(['driver' => 'gd']);
    }

    private function factory(): PreviewImageFactory
    {
        return new PreviewImageFactory(1200, 630, 537, 240, 92, $this->manager);
    }

    private function image(int $width, int $height): string
    {
        return (string) $this->manager->canvas($width, $height, '#336699')->encode('jpg', 90);
    }

    /**
     * @return array<string, array{int, int, int, int}>
     */
    public static function landscapeProvider(): array
    {
        return [
            'landscape downscale' => [1600, 1200, 1200, 630],
            'portrait without upscale' => [600, 900, 600, 315],
        ];
    }

    #[DataProvider('landscapeProvider')]
    public function test_crops_to_card_size(
        int $sourceWidth,
        int $sourceHeight,
        int $expectedWidth,
        int $expectedHeight,
    ): void {
        $preview = $this->factory()->make($this->image($sourceWidth, $sourceHeight));

        $this->assertNotNull($preview);
        $this->assertSame($expectedWidth, $preview->width);
        $this->assertSame($expectedHeight, $preview->height);

        $decoded = $this->manager->make($preview->contents);
        $this->assertSame($expectedWidth, $decoded->width());
        $this->assertSame($expectedHeight, $decoded->height());
    }

    public function test_returns_null_when_source_is_too_small(): void
    {
        $this->assertNull($this->factory()->make($this->image(400, 200)));
    }

    public function test_upscales_when_allowed(): void
    {
        $preview = $this->factory()->make($this->image(400, 400), allowUpscale: true);

        $this->assertNotNull($preview);
        $this->assertSame(1200, $preview->width);
        $this->assertSame(630, $preview->height);
    }

    public function test_rejects_invalid_binary(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory()->make('not-an-image');
    }
}
