<?php

namespace Craftsys\Tests\LaravelRedisSessionEnhanced;

use Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancerHandler;
use Illuminate\Support\Facades\Session;

class DriverSetupTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('session.driver', 'redis-session');
        $app['config']->set('session.connection', 'session');
    }

    /**
     * Test that we have correct handler from container binding.
     *
     * @return void
     */
    public function testClientResolutionFromContainer()
    {
        $this->assertInstanceOf(RedisSessionEnhancerHandler::class, Session::getHandler());
    }
}
