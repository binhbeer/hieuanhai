<?php

namespace App\Support;

use App\Exceptions\AccountDeletedException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class UserActivityLock
{
    public const SECONDS = 1500;

    public const API_SLOT_COUNT = 10;

    public function __construct(private ?Repository $repository = null) {}

    /** @var array<int, int> */
    private static array $held = [];

    /** @var array<int, int> */
    private static array $heldApiPermits = [];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $userId, callable $callback, int $waitSeconds = 5): mixed
    {
        if (isset(self::$held[$userId]) || isset(self::$heldApiPermits[$userId])) {
            return $callback();
        }

        if ($this->deleting($userId)) {
            throw new AccountDeletedException;
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
    public function runApi(int $userId, int $limit, callable $callback): mixed
    {
        if (isset(self::$heldApiPermits[$userId])) {
            return $callback();
        }

        foreach (range(0, min(self::API_SLOT_COUNT, max(1, $limit)) - 1) as $slot) {
            $lock = $this->apiLock($userId, $slot);

            if (! $lock->get()) {
                continue;
            }

            try {
                if ($this->deleting($userId)) {
                    throw new AccountDeletedException;
                }

                self::$heldApiPermits[$userId] = (self::$heldApiPermits[$userId] ?? 0) + 1;

                try {
                    return $callback();
                } finally {
                    if (--self::$heldApiPermits[$userId] === 0) {
                        unset(self::$heldApiPermits[$userId]);
                    }
                }
            } finally {
                $lock->release();
            }
        }

        throw new LockTimeoutException;
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
            return $this->lock($userId)->block(self::SECONDS, function () use ($callback, $userId): mixed {
                $locks = [];

                try {
                    foreach (range(0, self::API_SLOT_COUNT - 1) as $slot) {
                        $lock = $this->apiLock($userId, $slot);
                        $lock->block(self::SECONDS);
                        $locks[] = $lock;
                    }

                    return $callback();
                } finally {
                    foreach (array_reverse($locks) as $lock) {
                        $lock->release();
                    }
                }
            });
        } finally {
            $cache->forget($marker);
        }
    }

    /** @phpstan-impure */
    public function deleting(int $userId): bool
    {
        return $this->cache()->has($this->marker($userId));
    }

    private function lock(int $userId): Lock
    {
        return $this->lockNamed('user-activity:'.$userId);
    }

    private function apiLock(int $userId, int $slot): Lock
    {
        return $this->lockNamed('user-api-image:'.$userId.':'.$slot);
    }

    private function lockNamed(string $name): Lock
    {
        $store = $this->cache()->getStore();

        if (! $store instanceof LockProvider) {
            throw new \RuntimeException('Cache store does not support locks.');
        }

        return $store->lock($name, self::SECONDS);
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
