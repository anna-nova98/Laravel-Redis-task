<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function __construct(
        private ?string $token = null,
    ) {
        $this->token = $token ?? config('services.telegram.token');
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @return array{ok: bool, result?: object}|array{ok: false, error_code: int, description: string}
     * @throws \RuntimeException On 5xx or 429 (retryable).
     */
    public function sendMessage(string|int $chatId, string $text, array $options = []): array
    {
        if (empty($this->token)) {
            Log::warning('TelegramService: token not configured, skipping send.', [
                'chat_id' => $chatId,
                'text_preview' => \Illuminate\Support\Str::limit($text, 50),
            ]);
            return ['ok' => false, 'error_code' => 0, 'description' => 'Token not configured'];
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
        ], $options);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $response = Http::timeout(10)->post($url, $payload);
        $body = $response->json() ?? ['ok' => false, 'description' => $response->body()];

        if (!$response->successful()) {
            Log::error('TelegramService: API error', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $body,
            ]);
            if ($response->status() >= 500 || $response->status() === 429) {
                throw new \RuntimeException('Telegram API error: ' . $response->body());
            }
            return $body;
        }

        return $body;
    }
}
