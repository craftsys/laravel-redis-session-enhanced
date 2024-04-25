<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Foundation\Application;

class RedisSessionEnhancedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Session::extend('redis-session', function (Application $app) {
            $config = $app['config'];
            $handler = (new RedisSessionEnhancerHandler(
                clone $app->make('cache')->store('redis'),
                $config->get('session.lifetime')
            ))->setContainer($app);
            // set the connection
            $connection = $config->get('session.connection');
            if ($connection) {
                $handler
                    ->getCache()
                    ->getStore()
                    ->setConnection($connection);
            }
            return $handler;
        });
    }
}
