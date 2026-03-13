<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class TelegramRateLimitMiddleware
{
    /**
     * Enforce Telegram rate limits using Laravel's RateLimiter (Redis-backed).
     * Global: configurable messages per second; per-chat: configurable messages per minute.
     */
    public function handle(object $job, Closure $next): mixed
    {
        $chatId = $job->chatId ?? null;
        if ($chatId === null) {
            return $next($job);
        }

        $globalLimit = config('services.telegram.global_limit_per_second', 30);
        $chatLimit = config('services.telegram.chat_limit_per_minute', 20);

        $globalKey = 'telegram-global:' . time();
        if (RateLimiter::tooManyAttempts($globalKey, $globalLimit)) {
            return $job->release(1);
        }
        RateLimiter::hit($globalKey, 2);

        $minute = (int) floor(time() / 60);
        $chatKey = 'telegram-chat:' . $chatId . ':' . $minute;
        if (RateLimiter::tooManyAttempts($chatKey, $chatLimit)) {
            $delay = max(1, 60 - (time() % 60));
            return $job->release($delay);
        }
        RateLimiter::hit($chatKey, 120);

        return $next($job);
    }
}
