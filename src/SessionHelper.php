<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionHelper
{
    public static function getForUser(
        int $user_id,
        bool $only_active = false
    ): Collection {
        if (!self::isUsingValidDriver()) {
            return collect([]);
        }
        return collect(
            self::queryBuilerForUser($user_id)
                ->when($only_active, function (Collection $sessions) {
                    return $sessions->where(
                        'last_activity',
                        '>=',
                        self::getTimestampOfLastActivityForActiveSession(),
                    );
                })
                ->sortByDesc('last_activity')
                ->values(),
        );
    }

    public static function getCountForUser(
        int $user_id,
        bool $only_active = false
    ): int {
        if (!self::isUsingValidDriver()) {
            return 0;
        }
        return self::queryBuilerForUser($user_id)
            ->when($only_active, function (Collection $sessions) {
                return $sessions->where(
                    'last_activity',
                    '>=',
                    self::getTimestampOfLastActivityForActiveSession(),
                );
            })
            ->count();
    }

    public static function getActiveCountForUser(int $user_id): int
    {
        return self::getCountForUser($user_id, true);
    }

    /**
     * @param string|array<string> $session_id
     */
    public static function deleteForUserExceptSession(
        int $user_id,
        $except_session_id
    ): void {
        if (self::isUsingValidDriver()) {
            $session_ids = Arr::wrap($except_session_id);
            self::queryBuilerForUser($user_id)
                ->when(count($session_ids), function (Collection $builder) use (
                    $session_ids
                ) {
                    return $builder->whereNotIn('id', $session_ids);
                })
                ->each(function ($session) {
                    $id = $session->id;
                    Session::getHandler()->destroy($id);
                });
        }
    }

    protected static function queryBuilerForUser(int $user_id): Collection
    {
        if (self::isUsingDatabaseDriver()) {
            return DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user_id)
                ->get();
        }
        if (self::isUsingRedisDatabaseDriver()) {
            /** @var RedisDatabaseLikeSessionHandler */
            $handler = Session::getHandler();
            return $handler->readAll()->where('user_id', $user_id);
        }
        throw new \Exception(
            'SessionHelper can only be used for database/redis drivers',
        );
    }

    public static function destroyAll(): void
    {
        switch (true) {
            case self::isUsingDatabaseDriver():
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->truncate();
                break;
            case self::isUsingRedisDatabaseDriver():
                /** @var RedisDatabaseLikeSessionHandler */
                $handler = Session::getHandler();
                $handler->destroyAll();
                break;
            default:
                throw new \Exception(
                    'SessionHelper can only be used for database/redis drivers',
                );
        }
    }

    protected static function isUsingDatabaseDriver(): bool
    {
        return config('session.driver') == 'database';
    }

    protected static function isUsingRedisDatabaseDriver(): bool
    {
        return config('session.driver') == 'redis-session';
    }

    public static function isUsingValidDriver(): bool
    {
        return self::isUsingDatabaseDriver() ||
            self::isUsingRedisDatabaseDriver();
    }

    /**
     * Get the timestamp of last activity which results in an active session
     */
    public static function getTimestampOfLastActivityForActiveSession(): int
    {
        return now()
            ->subMinutes(config('session.lifetime'))
            ->getTimestamp();
    }
}
