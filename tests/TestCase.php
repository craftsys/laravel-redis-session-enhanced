<?php

namespace Craftsys\Tests\LaravelRedisSessionEnhanced;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancedServiceProvider as ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }
}
