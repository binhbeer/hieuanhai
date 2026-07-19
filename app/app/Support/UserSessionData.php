<?php

namespace App\Support;

use Illuminate\Cache\RedisStore;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Redis as PhpRedis;
use Throwable;

class UserSessionData
{
    public function delete(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();

        if (config('session.driver') !== 'redis') {
            return;
        }

        $session = app('session')->driver();

        if (! $session instanceof Store || ! $session->getHandler() instanceof CacheBasedSessionHandler) {
            return;
        }

        $store = $session->getHandler()->getCache()->getStore();

        if (! $store instanceof RedisStore) {
            return;
        }

        $client = $store->connection()->client();

        if (! $client instanceof PhpRedis) {
            return;
        }

        $clientPrefix = (string) $client->getOption(PhpRedis::OPT_PREFIX);
        $physicalPrefix = $clientPrefix.$store->getPrefix();
        $authKey = 'login_web_'.sha1('Illuminate\Auth\SessionGuard');
        $originalPrefix = $clientPrefix;
        $cursor = null;

        try {
            $client->setOption(PhpRedis::OPT_PREFIX, '');

            do {
                $keys = $client->scan($cursor, $physicalPrefix.'*', 100);

                if (! is_array($keys)) {
                    continue;
                }

                foreach ($keys as $key) {
                    $payload = $client->get($key);

                    if (is_string($payload) && $this->belongsTo($payload, $authKey, $userId)) {
                        $client->del($key);
                    }
                }
            } while ($cursor !== 0);
        } catch (Throwable $e) {
            report($e);
        } finally {
            $client->setOption(PhpRedis::OPT_PREFIX, $originalPrefix);
        }
    }

    private function belongsTo(string $payload, string $authKey, int $userId): bool
    {
        $decoded = @unserialize($payload, ['allowed_classes' => false]);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) && (int) ($decoded[$authKey] ?? 0) === $userId;
    }
}
