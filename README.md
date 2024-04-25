# Laravel Redis Session Enhanced Driver

<p>
<a href="https://packagist.org/packages/craftsys/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/dt/craftsys/laravel-redis-session-enhanced" alt="Total Downloads" /></a>
<a href="https://packagist.org/packages/craftsys/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/v/craftsys/laravel-redis-session-enhanced?label=version" alt="Latest Stable Version" /></a>
<a href="https://packagist.org/packages/craftsys/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/l/craftsys/laravel-redis-session-enhanced" alt="License" /></a>
<a href="https://packagist.org/packages/craftsys/laravel-redis-session-enhanced"><img src="https://img.shields.io/github/workflow/status/craftsys/laravel-redis-session-enhanced/tests?label=tests" alt="Status" /></a>
</p>


The [Laravel's Database Session Driver](https://laravel.com/docs/session#database) manages sessions in the Database which associates following attributes (along with the payload) with every session update: `user_id`, `ip_address`, `user_agent`, and `last_activty`. These attributes can be accessed and modified to provide following capabilities to your customers

- Track Active Sessions
- Remove Other Sessions (Logout from other devices)
- Allow Admins to force logout some/everyone
- Block Multiple Sessions

But with the Database driver, **every request to your application will do a Database update** to sessions table to track the latest session information, specifically, the `last_activty` attribute. This database update is required to validate unauthenticated requests if the session becomes inactive for the configured `SESSION_LIFETIME`. These session updates on every requests are fast and should not have much performance impact on your request time. But, if you ARE facing performance issues and want to store the session in the redis cache, you can go the [Laravel's Redis Session Driver](https://laravel.com/docs/session#redis). The redis driver will store and validate the session automatically but you will loose the above mentioned capabilities for your customers (tracking, logouts etc.).

If you want to have similar capabilities as the Database Session driver but want to use Redis for session storage, this project is for you.


## Table of Contents

-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Usage](#usage)

## Installation

The packages is available on [Packagist](https://packagist.org/packages/craftsys/laravel-redis-session-enhanced) and can be installed via [Composer](https://getcomposer.org/) by executing following command in shell.

```bash
composer require craftsys/laravel-redis-session-enhanced
```

**prerequisite**

-   php^7.1
-   laravel^5|^6|^7|^8|^9|^10|^11
-   redis installed and configured for laravel

The package is tested for 5.8+,^6.0,^7.0,^8.0,^9.0,^10.0,^11.0 only.

### Laravel 5.5+

If you're using Laravel 5.5 or above, the package will automatically register the `Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancedServiceProvider` provider.

### Laravel 5.4 and below

Add `Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancedServiceProvider` to the `providers` array in your `config/app.php`:

```php
'providers' => [
     // Other service providers...
     Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancedServiceProvider::class,
],
```

## Configuration

1. Add a new connection named `session` in your `config/database.php` redis configuration

```php
[
  'redis' => [
     // ... existing configuration
     // Add new connection for session
     'session' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        // new DB, only for sessions for quick access and cleanup, change the value if 2 is already taken
        'database' => env('REDIS_CACHE_DB', 2),
    ],
  ]
];
```

2. Update the .env file with the session driver and connection

```sh
SESSION_DRIVER=redis-session
SESSION_CONNECTION=session
```


## Usage
