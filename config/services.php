<?php

return [

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'global_limit_per_second' => (int) env('TELEGRAM_GLOBAL_LIMIT_PER_SECOND', 30),
        'chat_limit_per_minute' => (int) env('TELEGRAM_CHAT_LIMIT_PER_MINUTE', 20),
    ],

];
