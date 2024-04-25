<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionHelper
{
    /**
     * Get all the session for the given user id
     * @param int|string $user_id Id/Key of the user, must match the Id used in sessions
     * @param bool $only_active Get the active only sessions
     */
    public static function getForUser(
        $user_id,
        bool $only_active = false
    ): Collection {
        if (!self::isUsingValidDriver()) {
            return collect([]);
        }
        return collect(
            self::getAll($user_id)
                ->when($only_active, function (Collection $sessions) {
                    return $sessions->where(
                        'last_activity',
                        '>=',
                        self::getTimestampOfLastActivityForActiveSession(),
                    );
                })
                ->sortByDesc('last_activity')
                ->values()
        );
    }

    /**
     * Delete a User's sessions except the given session IDs
     * @param int|string $user_id
     * @param string|array<string> $except_session_id Non-deletable session ids, pass empty to delete all
     */
    public static function deleteForUserExceptSession(
        $user_id,
        $except_session_id = []
    ): void {
        $session_ids = Arr::wrap($except_session_id);
        self::getForUser($user_id)
            ->when(count($session_ids), function (Collection $sessions) use (
                $session_ids
            ) {
                return $sessions->whereNotIn('id', $session_ids);
            })
            ->each(function ($session) {
                $id = $session->id;
                Session::getHandler()->destroy($id);
            });
    }

    /**
     * Get all the sessions from the store
     * @param null|int|string $user_id Optionally get all the stored sessions of a particular user
     */
    public static function getAll($user_id = null): Collection
    {
        if (self::isUsingDatabaseDriver()) {
            return DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->when($user_id, function ($sessions) use ($user_id) {
                    return $sessions->where('user_id', $user_id);
                })
                ->get();
        }
        if (self::isUsingRedisDatabaseDriver()) {
            /** @var RedisDatabaseLikeSessionHandler */
            $handler = Session::getHandler();
            $all = $handler->readAll();
            if ($user_id) {
                $all = $all->where('user_id', $user_id);
            }
            return $all;
        }
        throw new \Exception(
            'SessionHelper can only be used for database/redis drivers',
        );
    }

    /**
     * Destroy all the sessions data
     */
    public static function deleteAll(): void
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
