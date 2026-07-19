<?php

namespace App\Support;

use App\Exceptions\AccountDeletedException;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

class UserActivityLock
{
    public const SECONDS = 1500;

    /** @template T */
    /** @param  callable(): T  $callback */
    /** @return T */
    public function run(int $userId, callable $callback): mixed
    {
        if ($this->deleting($userId)) {
            throw new AccountDeletedException;
        }

        return $this->lock($userId)->block(self::SECONDS, function () use ($callback, $userId): mixed {
            if ($this->deleting($userId)) {
                throw new AccountDeletedException;
            }

            return $callback();
        });
    }

    /** @template T */
    /** @param  callable(): T  $callback */
    /** @return T */
    public function delete(int $userId, callable $callback): mixed
    {
        $cache = $this->cache();
        $marker = $this->marker($userId);
        $cache->put($marker, true, self::SECONDS);

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
        return $this->cache()->lock('user-activity:'.$userId, self::SECONDS);
    }

    private function marker(int $userId): string
    {
        return 'user-deleting:'.$userId;
    }

    private function cache(): Repository
    {
        try {
            $store = Cache::store('redis');
            $store->getStore()->lock('user-activity-probe')->release();

            return $store;
        } catch (Throwable) {
            return Cache::store();
        }
    }
}
