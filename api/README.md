# Telegram invoice webhook

This folder contains the PHP webhook and shared invoice API for the Telegram bot.

## Environment variables

Configure these variables in the PHP hosting environment. Do not put real values in the repository.

```text
TELEGRAM_BOT_TOKEN=token-from-botfather
TELEGRAM_ADMIN_CHAT_ID=your-telegram-chat-id
TELEGRAM_WEBHOOK_SECRET=random-long-secret
PUBLIC_BASE_URL=https://example.com
```

The web server must allow PHP to write to `data/invoices.json` and `data/telegram-state.json`.

## Register the webhook

Run this once after deployment, replacing the placeholders locally:

```text
https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://example.com/api/telegram-webhook.php&secret_token=<TELEGRAM_WEBHOOK_SECRET>
```

The bot accepts `/start`, `/new`, `/list`, `/link ID`, `/stop ID`, `/start_invoice ID`, `/delete ID`, and `/cancel`.

The `TELEGRAM_ADMIN_CHAT_ID` check means only one Telegram account can control invoices.

Each webhook request receives an 8-character trace ID. The step log is written to `data/telegram-webhook.log` and is protected from direct web access by `data/.htaccess`. Errors are also reported to the admin chat with their trace ID.