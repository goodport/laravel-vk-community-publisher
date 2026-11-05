<?php

namespace Goodport\VkCommunityPublisher;

use Goodport\VkCommunityPublisher\Contracts\CommunityPublisher;
use Goodport\VkCommunityPublisher\Preview\PreviewImageFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class VkCommunityPublisherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vk-community-publisher.php', 'vk-community-publisher');

        $this->app->singleton(VkApiClient::class, function (Application $app): VkApiClient {
            $logChannel = config('vk-community-publisher.log_channel');

            return new VkApiClient(
                apiVersion: (string) config('vk-community-publisher.api_version'),
                baseUrl: (string) config('vk-community-publisher.base_url'),
                timeout: (int) config('vk-community-publisher.timeout'),
                logger: $logChannel ? Log::channel((string) $logChannel) : null,
            );
        });

        $this->app->singleton(PreviewImageFactory::class, function (): PreviewImageFactory {
            return new PreviewImageFactory(
                maxWidth: (int) config('vk-community-publisher.preview.max_width'),
                maxHeight: (int) config('vk-community-publisher.preview.max_height'),
                minWidth: (int) config('vk-community-publisher.preview.min_width'),
                minHeight: (int) config('vk-community-publisher.preview.min_height'),
                quality: (int) config('vk-community-publisher.preview.quality'),
            );
        });

        $this->app->singleton(CommunityPublisher::class, function (Application $app): CommunityPublisher {
            return new VkCommunityPublisher(
                client: $app->make(VkApiClient::class),
                linkParseAttempts: (int) config('vk-community-publisher.link_parse_attempts'),
                linkParseRetryDelayMs: (int) config('vk-community-publisher.link_parse_retry_delay_ms'),
                minPreviewWidth: (int) config('vk-community-publisher.preview.min_width'),
                minPreviewHeight: (int) config('vk-community-publisher.preview.min_height'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/vk-community-publisher.php' => config_path('vk-community-publisher.php'),
        ], 'vk-community-publisher-config');
    }
}
