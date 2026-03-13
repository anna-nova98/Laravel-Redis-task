<?php

namespace App\Jobs;

use App\Jobs\Middleware\TelegramRateLimitMiddleware;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function middleware(): array
    {
        return [new TelegramRateLimitMiddleware];
    }

    public function handle(TelegramService $telegram): void
    {
        $result = $telegram->sendMessage($this->chatId, $this->text, $this->options);

        if ($result['ok'] ?? false) {
            Log::info('SendMessageJob: message sent', ['chat_id' => $this->chatId]);
        }
    }
}
