<?php

namespace App\Jobs;

use App\Services\TelegramRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public string|int $chatId,
        public string $text,
        public array $options = [],
    ) {}

    public function handle(TelegramRateLimiter $rateLimiter): void
    {
        // Prefer env() so the queue worker (e.g. in Docker) gets the token from its environment
        $token = env('TELEGRAM_BOT_TOKEN') ?: config('services.telegram.token');
        if (empty($token)) {
            Log::warning('SendMessageJob: TELEGRAM_BOT_TOKEN not set, skipping send.', [
                'chat_id' => $this->chatId,
                'text_preview' => \Illuminate\Support\Str::limit($this->text, 50),
            ]);
            return;
        }

        $rateLimiter->acquireSlot($this->chatId);

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = array_merge([
            'chat_id' => $this->chatId,
            'text' => $this->text,
            'parse_mode' => $this->options['parse_mode'] ?? null,
            'disable_web_page_preview' => $this->options['disable_web_page_preview'] ?? true,
        ], $this->options);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $response = Http::timeout(10)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('SendMessageJob: Telegram API error', [
                'chat_id' => $this->chatId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            // Don't throw on 4xx (e.g. chat not found) - job completes, no retries
            if ($response->status() >= 500 || $response->status() === 429) {
                throw new \RuntimeException('Telegram API error: ' . $response->body());
            }
            return;
        }

        Log::info('SendMessageJob: message sent', ['chat_id' => $this->chatId]);
    }
}
