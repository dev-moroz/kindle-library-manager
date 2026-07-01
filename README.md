# Kindle Library Manager

A small self-hosted app for building and managing your personal e-book library.

Send a book to a Telegram bot, and it gets saved to your server. A simple web page then lets you browse and download everything from any device — including your Kindle's built-in browser.

## How it works

The project has two parts:

**Telegram bot (`site/bot.php`)** — You send a book file to the bot. It saves the file into the `books/` folder on your server. Before saving, it can optionally rename the file, and if the format is one the target device doesn't support, it converts it (via the [CloudConvert](https://cloudconvert.com) API) to a Kindle-friendly format such as `.mobi`. Access is gated: new users must be approved by the admin before they can upload.

**Web library (`site/index.php`)** — A minimalist page that lists every book in the `books/` folder, newest first, with pagination and one-click downloads. It's plain HTML with no JavaScript, so it loads on almost anything.

## Supported formats

The library page lists and serves these formats: `epub`, `fb2`, `fb2.zip`, `mobi`, `azw`, `azw3`, `pdf`, `djvu`, `txt`.

Files already in a Kindle-friendly format (`azw`, `prc`, `mobi`, `txt`) are stored as-is. Others are converted to `.mobi` on upload.

## Setup

The app runs in Docker (PHP 8.2 + Nginx). Nginx serves the `site/` folder as the web root and exposes it on port `8085`.

1. Clone the repository.
2. Create a `.env` file in the project root with the following variables:

   ```env
   TELEGRAM_BOT_TOKEN=your_telegram_bot_token
   TELEGRAM_ADMIN_ID=your_telegram_user_id
   SITE_URL=https://your-library-url
   CONVERTER_API_KEY=your_cloudconvert_api_key
   ```

3. Start the containers:

   ```bash
   docker compose up -d
   ```

4. Register the bot's webhook with Telegram so incoming messages reach `bot.php`.

The library is then available at your `SITE_URL`, and books can be added by messaging the bot.

## Configuration

| Variable | Purpose |
| --- | --- |
| `TELEGRAM_BOT_TOKEN` | Token for your Telegram bot (from @BotFather). |
| `TELEGRAM_ADMIN_ID` | Your Telegram user ID; the admin who approves new users. |
| `SITE_URL` | Public URL of the web library, used in the bot's "Open library" button. |
| `CONVERTER_API_KEY` | CloudConvert API key for format conversion (optional — without it, files are saved as-is). |

## Project structure

```
├── docker-compose.yml    # PHP + Nginx services
├── nginx.conf            # Web server config
├── books/                # Stored book files
└── site/
    ├── index.php         # Web library (browse & download)
    ├── bot.php           # Telegram bot (receive, convert, save)
    ├── style.css         # Library styling
    └── lang/
        └── en.php        # Bot message strings (English)
        └── ru.php        # Bot message strings (Russian)
```