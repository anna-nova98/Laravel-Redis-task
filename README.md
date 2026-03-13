# Telegram Bot – Laravel 12 + Redis Rate Limiting

Laravel 12 project (PHP + Redis only, no SQL database) that sends Telegram messages via a queue job with **Redis-enforced rate limits**:

- **Global:** no more than 30 messages per second across the entire bot  
- **Per chat:** no more than 20 messages per minute per `chat_id`

Limits are enforced via **job middleware** using Laravel's `RateLimiter` facade (Redis-backed). No custom polling or `env()` in application code; configuration is read only from config files.

---

## Requirements

- Docker and Docker Compose  
- (Optional) [Telegram Bot Token](https://t.me/BotFather) for real sends  

---

## Quick start

### 1. Clone and enter the project

```bash
cd smsManager
```

### 2. Environment

```bash
copy .env.example .env
php artisan key:generate
```

Edit `.env` and set `TELEGRAM_BOT_TOKEN` from [@BotFather](https://t.me/BotFather) (optional; without it, jobs run but skip sending).  
**Never commit `.env`** — it is listed in `.gitignore`; use `.env.example` as a template only.

### 3. Start app and Redis

```bash
docker compose up -d
```

This starts **Redis** and the **queue worker** (app). The worker processes jobs and sends messages to Telegram.

### 4. Dispatch test jobs

```bash
# From host (jobs go to Redis; worker in container processes them)
docker compose run --rm app php artisan telegram:dispatch-test 100
```

Or target one chat:

```bash
docker compose run --rm app php artisan telegram:dispatch-test 10 --chat=-5230897744
```

Messages appear in Telegram as the worker processes the queue (rate limits are applied per job).

---

## Configuration

| Variable | Description |
|--------|-------------|
| `TELEGRAM_BOT_TOKEN` | Bot token from BotFather (optional for testing) |
| `TELEGRAM_GLOBAL_LIMIT_PER_SECOND` | Max messages per second globally (default: 30) |
| `TELEGRAM_CHAT_LIMIT_PER_MINUTE` | Max messages per minute per chat (default: 20) |
| `REDIS_HOST` | Redis host (`redis` in Docker, `127.0.0.1` for local Redis) |
| `REDIS_CLIENT` | `phpredis` in Docker, `predis` when running PHP locally without the redis extension |

---

## Project layout

| Path | Description |
|------|-------------|
| `app/Jobs/SendMessageJob.php` | Queue job: sends via `TelegramService`; rate limiting via middleware |
| `app/Jobs/Middleware/TelegramRateLimitMiddleware.php` | Job middleware: Laravel `RateLimiter` (Redis) — 30/s global, 20/min per chat |
| `app/Services/TelegramService.php` | Telegram API abstraction: `sendMessage()` using config only |
| `app/Console/Commands/DispatchTelegramTestCommand.php` | Artisan command to dispatch N test jobs |
| `config/services.php` | Telegram token and rate-limit values (from env; never `env()` in app code) |
| `config/telegram.php` | Default chat IDs for test command |
| `config/queue.php`, `config/redis.php` | Queue and Redis |
| `docker-compose.yml` | App (queue worker) + Redis |
| `Dockerfile` | PHP 8.4, Redis extension; required for `docker compose build` |

---

## Rate limiting (Laravel RateLimiter)

- **Global:** `RateLimiter::hit('telegram-global:' . time(), 2)` with max 30 attempts; over limit → job released with 1s delay.  
- **Per chat:** `RateLimiter::hit('telegram-chat:{chat_id}:{minute}', 120)` with max 20 attempts; over limit → job released until next minute.

Uses Laravel's built-in `Illuminate\Support\Facades\RateLimiter` (Redis cache driver). No SQL database.

---

## Useful commands

```bash
# Start app + Redis
docker compose up -d

# Stop
docker compose down

# App logs (queue worker)
docker compose logs -f app

# Dispatch 100 jobs (default: alternating chat IDs)
docker compose run --rm app php artisan telegram:dispatch-test 100

# Dispatch 5 jobs to one chat
docker compose run --rm app php artisan telegram:dispatch-test 5 --chat=YOUR_CHAT_ID
```

---

## Getting your Telegram chat ID

1. Send a message to your bot (e.g. `/start`).  
2. Open: `https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates`  
3. In the JSON, use `result[].message.chat.id` (e.g. `-5230897744` for a group).

Use that value as `--chat=...` or in `SendMessageJob::dispatch($chatId, $text)`.
