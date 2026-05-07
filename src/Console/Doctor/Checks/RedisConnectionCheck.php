<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Illuminate\Support\Facades\Redis;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Redis bağlantısını test eder.
 * Cache driver redis değilse ya da REDIS_HOST tanımlı değilse uyarı döner.
 * Timeout: 2 saniye (plan gerekliliği).
 */
class RedisConnectionCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Redis Connection';
    }

    public function run(): DoctorReport
    {
        $cacheDriver = config('cache.default', 'file');
        $sessionDriver = config('session.driver', 'file');

        // Redis kullanılmıyorsa skip
        if ($cacheDriver !== 'redis' && $sessionDriver !== 'redis') {
            return DoctorReport::warn(
                $this->name(),
                'Cache or session is not using Redis (cache='.$cacheDriver.', session='.$sessionDriver.').',
                'Redis is recommended in production: CACHE_STORE=redis, SESSION_DRIVER=redis.'
            );
        }

        try {
            // 2 saniye timeout: socket bağlantısını bloke etmemek için
            $redisHost = config('database.redis.default.host', '127.0.0.1');
            $redisPort = (int) config('database.redis.default.port', 6379);

            $socket = @stream_socket_client(
                "tcp://{$redisHost}:{$redisPort}",
                $errno,
                $errstr,
                2.0
            );

            if ($socket === false) {
                throw new \RuntimeException("TCP connection to Redis failed: {$errstr} ({$errno})");
            }

            fclose($socket);

            // Gerçek Redis ping
            Redis::ping();

            return DoctorReport::ok(
                $this->name(),
                "Redis connection successful ({$redisHost}:{$redisPort})."
            );
        } catch (Throwable $e) {
            return DoctorReport::fail(
                $this->name(),
                'Could not connect to Redis: '.$e->getMessage(),
                'Check REDIS_HOST, REDIS_PORT, and REDIS_PASSWORD in your .env file.'
            );
        }
    }
}
