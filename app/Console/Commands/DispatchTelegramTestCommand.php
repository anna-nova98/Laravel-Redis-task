<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageJob;
use Illuminate\Console\Command;

class DispatchTelegramTestCommand extends Command
{
    protected $signature = 'telegram:dispatch-test
                            {count=100 : Number of jobs to dispatch}
                            {--chat= : Optional single chat_id to use for all jobs (default: alternating 3 chat IDs)}';

    protected $description = 'Dispatch a batch of SendMessageJob jobs to demonstrate Redis rate limiting (30/s global, 20/min per chat).';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $singleChat = $this->option('chat');

        $chatIds = $singleChat !== null
            ? array_fill(0, $count, $singleChat)
            : $this->defaultChatIds($count);

        $this->info("Dispatching {$count} SendMessageJob(s)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($chatIds as $i => $chatId) {
            SendMessageJob::dispatch(
                (string) $chatId,
                sprintf('Test message #%d at %s', $i + 1, now()->toIso8601String()),
                ['disable_web_page_preview' => true]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done. Run the queue worker to process jobs (rate limits enforced in each job).');

        return self::SUCCESS;
    }

    private function defaultChatIds(int $count): array
    {
        $ids = config('telegram.chat_ids', []);
        if (empty($ids)) {
            $this->warn('No chat_ids in config/telegram.php; using a placeholder. Add your chat IDs there.');
            $ids = ['111111'];
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $ids[$i % count($ids)];
        }
        return $result;
    }
}
