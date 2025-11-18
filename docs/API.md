# API Documentation

## Table of Contents

- [TelegramComponent](#telegramcomponent)
- [TelegramManager](#telegrammanager)
- [CommandRouter](#commandrouter)
- [UpdateHandlerMiddleware](#updatehandlermiddleware)
- [RateLimiter](#ratelimiter)
- [Keyboard](#keyboard)
- [FileHelper](#filehelper)
- [Constants](#constants)

---

## TelegramComponent

Main class for interacting with Telegram Bot API.

### Methods

#### sendMessage($chatId, string $text, array $options = []): array

Send text message to chat.

**Parameters:**
- `$chatId` (int|string): Chat ID or username
- `$text` (string): Message text
- `$options` (array): Additional options
  - `parse_mode` (string): 'MarkdownV2', 'Markdown', 'HTML' (default: 'MarkdownV2')
  - `disable_web_page_preview` (bool): Disable link preview
  - `reply_to_message_id` (int): Reply to message ID
  - `keyboard` (array): Keyboard markup
  - `extra` (array): Additional parameters
  - `attempts` (int): Retry attempts (default: 3)

**Returns:** array - API response

**Example:**
```php
$component->sendMessage($chatId, "Hello!", [
    'parse_mode' => 'MarkdownV2',
    'keyboard' => Keyboard::inline([...])
]);
```

#### sendPhoto($chatId, $photo, ?string $caption = null, ?array $keyboard = null, array $options = []): array

Send photo to chat.

**Parameters:**
- `$chatId` (int|string): Chat ID
- `$photo` (string|resource): File ID, URL, or file resource
- `$caption` (string|null): Photo caption
- `$keyboard` (array|null): Keyboard markup
- `$options` (array): Additional options

**Returns:** array - API response

#### editMessageText($chatId, int $messageId, string $text, array $options = []): array

Edit message text.

**Parameters:**
- `$chatId` (int|string): Chat ID
- `$messageId` (int): Message ID to edit
- `$text` (string): New text
- `$options` (array): Additional options

**Returns:** array - API response

#### deleteMessage($chatId, int $messageId): array

Delete message.

**Parameters:**
- `$chatId` (int|string): Chat ID
- `$messageId` (int): Message ID

**Returns:** array - API response

#### answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array

Answer callback query.

**Parameters:**
- `$callbackQueryId` (string): Callback query ID
- `$text` (string|null): Text to show
- `$showAlert` (bool): Show as alert

**Returns:** array - API response

#### sendChatAction($chatId, string $action = Constants::ACTION_TYPING): array

Send chat action (typing, uploading, etc.).

**Parameters:**
- `$chatId` (int|string): Chat ID
- `$action` (string): Action type (see Constants::ACTION_*)

**Returns:** array - API response

#### getFile(string $fileId): array

Get file information.

**Parameters:**
- `$fileId` (string): File ID

**Returns:** array - API response with file info

#### setWebhook(string $url, array $options = []): array

Set webhook URL.

**Parameters:**
- `$url` (string): Webhook URL
- `$options` (array): Additional options
  - `allowed_updates` (array): Allowed update types
  - `drop_pending_updates` (bool): Drop pending updates

**Returns:** array - API response

#### getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null, ?array $allowedUpdates = null): array

Get updates (for polling).

**Parameters:**
- `$offset` (int|null): Offset for pagination
- `$limit` (int|null): Maximum number of updates
- `$timeout` (int|null): Timeout in seconds
- `$allowedUpdates` (array|null): Allowed update types

**Returns:** array - API response with updates

#### enqueueSendMessage($chatId, string $text, array $options = []): bool

Enqueue message for background sending.

**Parameters:**
- `$chatId` (int|string): Chat ID
- `$text` (string): Message text
- `$options` (array): Message options

**Returns:** bool - True on success

**Throws:** TelegramException if queue not configured

#### static escapeMarkdownV2(string $text): string

Escape text for MarkdownV2.

**Parameters:**
- `$text` (string): Text to escape

**Returns:** string - Escaped text

---

## TelegramManager

Manages multiple bot configurations.

### Methods

#### get(?string $name = null): TelegramComponent

Get bot component by name.

**Parameters:**
- `$name` (string|null): Bot name (uses default if null)

**Returns:** TelegramComponent - Bot component instance

**Throws:** InvalidArgumentException if bot not found

#### getBotNames(): array

Get all registered bot names.

**Returns:** array - List of bot names

#### has(string $name): bool

Check if bot exists.

**Parameters:**
- `$name` (string): Bot name

**Returns:** bool - True if bot exists

---

## CommandRouter

Routes commands and callback queries to handlers.

### Methods

#### register(string $name, callable $callable): void

Register command handler.

**Parameters:**
- `$name` (string): Command name (e.g., '/start', 'callback_data')
- `$callable` (callable): Handler function/closure

**Example:**
```php
$router->register('/start', function($message, $component) {
    $component->sendMessage($message['chat']['id'], "Hello!");
});
```

#### handleUpdate(array $update, TelegramComponent $component): bool

Handle Telegram update.

**Parameters:**
- `$update` (array): Telegram update array
- `$component` (TelegramComponent): Telegram component

**Returns:** bool - True if handled

---

## UpdateHandlerMiddleware

Middleware for handling updates with rate limiting.

### Methods

#### handle(array $update, TelegramComponent $component): void

Handle Telegram update with rate limiting.

**Parameters:**
- `$update` (array): Telegram update array
- `$component` (TelegramComponent): Telegram component

---

## RateLimiter

Rate limiting using Redis.

### Methods

#### allow($userId): bool

Check if request is allowed for user.

**Parameters:**
- `$userId` (int|string): User ID

**Returns:** bool - True if allowed

---

## Keyboard

Helper class for building keyboards.

### Methods

#### static inline(array $buttons): array

Create inline keyboard.

**Parameters:**
- `$buttons` (array): Array of button rows

**Returns:** array - Inline keyboard markup

#### static inlineButton(string $text, string $callbackData): array

Create inline button.

**Parameters:**
- `$text` (string): Button text
- `$callbackData` (string): Callback data

**Returns:** array - Button array

#### static reply(array $buttons, bool $resize = true): array

Create reply keyboard.

**Parameters:**
- `$buttons` (array): Array of button rows
- `$resize` (bool): Resize keyboard

**Returns:** array - Reply keyboard markup

---

## FileHelper

Helper for downloading files.

### Methods

#### static downloadFile(TelegramComponent $component, string $fileId, string $saveDir = '@runtime/tg_files'): string

Download file from Telegram.

**Parameters:**
- `$component` (TelegramComponent): Telegram component
- `$fileId` (string): File ID
- `$saveDir` (string): Save directory (Yii alias supported)

**Returns:** string - Local file path

**Throws:** TelegramException if download fails

---

## Constants

Constants for Telegram Bot Extension.

### API Constants
- `Constants::API_URL` - Telegram API base URL
- `Constants::API_TIMEOUT` - Default timeout

### Parse Modes
- `Constants::PARSE_MODE_MARKDOWN` - Markdown
- `Constants::PARSE_MODE_MARKDOWN_V2` - MarkdownV2
- `Constants::PARSE_MODE_HTML` - HTML

### Chat Actions
- `Constants::ACTION_TYPING` - Typing
- `Constants::ACTION_UPLOAD_PHOTO` - Upload photo
- `Constants::ACTION_RECORD_VOICE` - Record voice
- ... (see Constants.php for full list)

### Rate Limiting
- `Constants::DEFAULT_RATE_LIMIT_PER_SECOND` - Default bot rate limit
- `Constants::DEFAULT_USER_RATE_LIMIT` - Default user rate limit
- `Constants::DEFAULT_USER_RATE_WINDOW` - Default rate window

### Log Categories
- `Constants::LOG_CATEGORY_ERROR` - Error logs
- `Constants::LOG_CATEGORY_COMMAND_ERROR` - Command error logs
- `Constants::LOG_CATEGORY_RATE_LIMIT` - Rate limit logs

