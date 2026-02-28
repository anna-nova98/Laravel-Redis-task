<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-based rate limiter for Telegram Bot API.
 * Ensures: max 30 messages/second globally, max 20 messages/minute per chat_id.
 */
final class TelegramRateLimiter
{
    private const KEY_GLOBAL_PREFIX = 'telegram:rate:global:';
    private const KEY_CHAT_PREFIX = 'telegram:rate:chat:';
    private const TTL_SECOND = 2;
    private const TTL_MINUTE = 120;

    public function __construct(
        private int $globalLimitPerSecond = 30,
        private int $chatLimitPerMinute = 20,
    ) {
        $this->globalLimitPerSecond = (int) env('TELEGRAM_GLOBAL_LIMIT_PER_SECOND', $globalLimitPerSecond);
        $this->chatLimitPerMinute = (int) env('TELEGRAM_CHAT_LIMIT_PER_MINUTE', $chatLimitPerMinute);
    }

    /**
     * Block until a slot is available for the given chat_id, then reserve it.
     * Call this from the job immediately before sending the message.
     */
    public function acquireSlot(string|int $chatId): void
    {
        $this->waitForGlobalSlot();
        $this->waitForChatSlot((string) $chatId);
    }

    private function waitForGlobalSlot(): void
    {
        $maxWaitMs = 2000;
        $start = microtime(true) * 1000;

        while (true) {
            $now = (int) floor(microtime(true));
            $key = self::KEY_GLOBAL_PREFIX . $now;

            $redis = Redis::connection();
            $current = (int) $redis->incr($key);
            if ($redis->ttl($key) < 0) {
                $redis->expire($key, self::TTL_SECOND);
            }

            if ($current <= $this->globalLimitPerSecond) {
                return;
            }

            $redis->decr($key);

            $elapsed = (microtime(true) * 1000) - $start;
            if ($elapsed >= $maxWaitMs) {
                throw new \RuntimeException('Telegram rate limit: could not acquire global slot within timeout.');
            }

            usleep(50_000); // 50ms before retry
        }
    }

    private function waitForChatSlot(string $chatId): void
    {
        $maxWaitMs = 65_000; // slightly over 1 minute
        $start = microtime(true) * 1000;

        while (true) {
            $minute = (int) floor(time() / 60);
            $key = self::KEY_CHAT_PREFIX . $chatId . ':' . $minute;

            $redis = Redis::connection();
            $current = (int) $redis->incr($key);
            if ($redis->ttl($key) < 0) {
                $redis->expire($key, self::TTL_MINUTE);
            }

            if ($current <= $this->chatLimitPerMinute) {
                return;
            }

            $redis->decr($key);

            $elapsed = (microtime(true) * 1000) - $start;
            if ($elapsed >= $maxWaitMs) {
                throw new \RuntimeException("Telegram rate limit: could not acquire chat slot for {$chatId} within timeout.");
            }

            $secondsUntilNextMinute = 60 - (time() % 60);
            sleep(min(1, $secondsUntilNextMinute));
        }
    }
}
