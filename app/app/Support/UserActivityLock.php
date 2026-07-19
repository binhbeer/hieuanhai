<?php

namespace App\Support;

use App\Exceptions\AccountDeletedException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class UserActivityLock
{
    public const SECONDS = 1500;

    public function __construct(private ?Repository $repository = null) {}

    /** @var array<int, int> */
    private static array $held = [];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $userId, callable $callback, int $waitSeconds = 5): mixed
    {
        if ($this->deleting($userId)) {
            throw new AccountDeletedException;
        }

        if (isset(self::$held[$userId])) {
            return $callback();
        }

        return $this->lock($userId)->block($waitSeconds, function () use ($callback, $userId): mixed {
            if ($this->deleting($userId)) {
                throw new AccountDeletedException;
            }

            self::$held[$userId] = (self::$held[$userId] ?? 0) + 1;

            try {
                return $callback();
            } finally {
                if (--self::$held[$userId] === 0) {
                    unset(self::$held[$userId]);
                }
            }
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function delete(int $userId, callable $callback): mixed
    {
        $cache = $this->cache();
        $marker = $this->marker($userId);
        $cache->put($marker, true, self::SECONDS * 2);

        try {
            return $this->lock($userId)->block(self::SECONDS, $callback);
        } finally {
            $cache->forget($marker);
        }
    }

    public function deleting(int $userId): bool
    {
        return $this->cache()->has($this->marker($userId));
    }

    private function lock(int $userId): Lock
    {
        $store = $this->cache()->getStore();

        if (! $store instanceof LockProvider) {
            throw new \RuntimeException('Cache store does not support locks.');
        }

        return $store->lock('user-activity:'.$userId, self::SECONDS);
    }

    private function marker(int $userId): string
    {
        return 'user-deleting:'.$userId;
    }

    private function cache(): Repository
    {
        return $this->repository ?? Cache::store();
    }
}
