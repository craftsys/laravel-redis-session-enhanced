<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\ExistenceAwareInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Collection;

class RedisSessionEnhancerHandler
    extends CacheBasedSessionHandler
    implements ExistenceAwareInterface
{
    use InteractsWithTime;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container|null
     */
    protected $container;

    /**
     * The existence state of the session.
     *
     * @var bool
     */
    protected $exists;

    /**
     * {@inheritdoc}
     *
     * @return string|false
     */
    public function read($sessionId): string
    {
        $raw_data = parent::read($sessionId);

        if (!$raw_data) {
            return '';
        }

        try {
            $session = json_decode($raw_data, true);
        } catch (\Exception $e) {
            return '';
        }

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session['payload'])) {
            $this->exists = true;
            return base64_decode($session['payload']) ?: '';
        }

        return '';
    }

    /**
     * Determine if the session is expired.
     *
     * @param  array  $session
     * @return bool
     */
    protected function expired($session): bool
    {
        return isset($session['last_activity']) &&
            $session['last_activity'] <
                Carbon::now()
                    ->subMinutes($this->minutes)
                    ->getTimestamp();
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        parent::write($sessionId, json_encode($payload));

        return $this->exists = true;
    }

    /**
     * Get the default payload for the session.
     *
     * @param  string  $data
     * @return array
     */
    protected function getDefaultPayload($data): array
    {
        $payload = [
            'payload' => base64_encode($data),
            'last_activity' => $this->currentTime(),
        ];

        if (!$this->container) {
            return $payload;
        }

        return tap($payload, function (&$payload) {
            $this->addUserInformation($payload)->addRequestInformation(
                $payload
            );
        });
    }

    /**
     * Add the user information to the session payload.
     *
     * @param  array  $payload
     * @return $this
     */
    protected function addUserInformation(&$payload): self
    {
        if ($this->container->bound(Guard::class)) {
            $payload['user_id'] = $this->userId();
        }

        return $this;
    }

    /**
     * Get the currently authenticated user's ID.
     *
     * @return mixed
     */
    protected function userId()
    {
        return $this->container->make(Guard::class)->id();
    }

    /**
     * Add the request information to the session payload.
     *
     * @param  array  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload): self
    {
        if ($this->container->bound('request')) {
            $payload = array_merge($payload, [
                'ip_address' => $this->ipAddress(),
                'user_agent' => $this->userAgent(),
            ]);
        }

        return $this;
    }

    /**
     * Get the IP address for the current request.
     *
     * @return string|null
     */
    protected function ipAddress(): ?string
    {
        return $this->container->make('request')->ip();
    }

    /**
     * Get the user agent for the current request.
     *
     * @return string
     */
    protected function userAgent(): string
    {
        return substr(
            (string) $this->container->make('request')->header('User-Agent'),
            0,
            500,
        );
    }

    /**
     * Set the application instance used by the handler.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $container
     * @return $this
     */
    public function setContainer($container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set the existence state for the session.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setExists($value): self
    {
        $this->exists = $value;

        return $this;
    }

    public function readAll(): Collection
    {
        /** @var RedisStore */
        $store = $this->cache->getStore();

        $connection = $store->connection();
        // Connections can have a global prefix...
        $connectionPrefix = '';
        // all keys are prefixed with connection prefix and cache prefix
        switch (true) {
            case $connection instanceof PhpRedisConnection:
                /** @var PhpRedisConnection $connection */
                $connectionPrefix = $connection->_prefix('');
                break;
            case $connection instanceof PredisConnection:
                /** @var PredisConnection $connection */
                $connectionPrefix = $connection->getOptions()->prefix ?: '';
                break;
            default:
                $connectionPrefix = '';
                break;
        }

        // create the prefix using connection prefix and cache store prefix
        $prefix = $connectionPrefix . $store->getPrefix();

        // 1. now get all the keys from connection
        $keys = $connection->command('keys', ['*']);
        // remove the prefix from the keys
        $keys = array_map(function ($key) use ($prefix) {
            return str_replace($prefix, '', $key);
        }, $keys);

        // 2. load the data for each keys
        $data = $store->many($keys);

        $active_sessions = [];
        foreach ($data as $session_id => $data) {
            if (!$data) {
                continue;
            }
            // try to parse the session data
            try {
                $parsed_data = json_decode($data, true);
                $active_sessions[] = (object) [
                    'id' => $session_id,
                    'user_id' => $parsed_data['user_id'],
                    'ip_address' => $parsed_data['ip_address'],
                    'user_agent' => $parsed_data['user_agent'],
                    'last_activity' => $parsed_data['last_activity'],
                    'payload' => $parsed_data['payload'],
                ];
            } catch (\Exception $e) {
                // ignore the errors
                continue;
            }
        }

        return collect($active_sessions);
    }

    public function destroyAll(): bool
    {
        /** @var RedisStore */
        $store = $this->cache->getStore();
        return $store->flush();
    }
}
