<?php

namespace Goodport\VkCommunityPublisher\Tests;

use Goodport\VkCommunityPublisher\VkCommunityPublisherServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [VkCommunityPublisherServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vk-community-publisher.api_version', '5.199');
        $app['config']->set('vk-community-publisher.base_url', 'https://api.vk.com/method');
        $app['config']->set('vk-community-publisher.timeout', 30);
        $app['config']->set('vk-community-publisher.log_channel', null);
        $app['config']->set('vk-community-publisher.link_parse_attempts', 3);
        $app['config']->set('vk-community-publisher.link_parse_retry_delay_ms', 0);
        $app['config']->set('vk-community-publisher.preview.max_width', 1200);
        $app['config']->set('vk-community-publisher.preview.max_height', 630);
        $app['config']->set('vk-community-publisher.preview.min_width', 537);
        $app['config']->set('vk-community-publisher.preview.min_height', 240);
        $app['config']->set('vk-community-publisher.preview.quality', 92);
    }
}
