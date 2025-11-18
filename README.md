# Telegram Bot Extension for Yii2

Professional Telegram Bot Extension for Yii2 Framework with advanced features: multi-bot support, queue integration, command routing, rate limiting, and more.

## ✨ Xususiyatlar

- ✔ Yii2 uchun maxsus yozilgan
- ✔ **Multi-bot support** - bir nechta botlarni boshqarish (student, staff, va boshqalar)
- ✔ **Queue integration** - background message sending
- ✔ **Command Router** - Laravel-like command handling
- ✔ **Rate Limiting** - per-user va per-bot rate limiting (Redis bilan)
- ✔ **Retry mechanism** - exponential backoff bilan avtomatik retry
- ✔ **File Helper** - Telegram fayllarini yuklab olish
- ✔ **Update Handler Middleware** - update handling uchun middleware
- ✔ Exception, Logging, MarkdownV2 escape
- ✔ Inline keyboard builder
- ✔ Chat actions support

## 📦 O'rnatish

### Composer orqali

```bash
composer require shokirjonmk/telegram-bot
```

Yoki `composer.json` ga qo'shing:

```json
{
    "require": {
        "shokirjonmk/telegram-bot": "*"
    }
}
```

### Qo'shimcha paketlar (ixtiyoriy, lekin tavsiya etiladi)

Queue va rate limiting uchun:

```bash
composer require yiisoft/yii2-queue
composer require yiisoft/yii2-redis
```

## ⚙️ Konfiguratsiya

### Asosiy konfiguratsiya (config/web.php va config/console.php)

```php
'components' => [
    // Redis (rate limiting va queue uchun)
    'redis' => [
        'class' => 'yii\redis\Connection',
        'hostname' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
    ],

    // Queue (background jobs uchun)
    'queue' => [
        'class' => \yii\queue\redis\Queue::class,
        'redis' => 'redis',
        'channel' => 'queue',
    ],

    // HTTP Client
    'httpClient' => [
        'class' => \yii\httpclient\Client::class,
    ],

    // Telegram Manager (multi-bot support)
    'telegramManager' => [
        'class' => 'shokirjonmk\telegram\TelegramManager',
        'bots' => [
            'student' => [
                'token' => getenv('TELEGRAM_STUDENT_TOKEN'),
                'apiUrl' => 'https://api.telegram.org/bot',
            ],
            'staff' => [
                'token' => getenv('TELEGRAM_STAFF_TOKEN'),
                'apiUrl' => 'https://api.telegram.org/bot',
            ],
        ],
        'defaultBot' => 'student',
        'enableLogs' => true,
    ],

    // Yoki oddiy single bot (backward compatibility)
    'telegram' => [
        'class' => 'shokirjonmk\telegram\Telegram',
        'botToken' => getenv('TELEGRAM_TOKEN'),
        'apiUrl' => 'https://api.telegram.org/bot',
        'timeout' => 5,
        'enableLogs' => true,
    ],
]
```

### Queue worker ishga tushirish

```bash
php yii queue/listen
# yoki
php yii queue/run
```

### Lokal polling ishga tushirish (Development)

```bash
# Console command orqali
php yii telegram-polling/polling --bot=student --timeout=30

# Yoki standalone script
php examples/polling.php --bot=student --timeout=30
```

## 🚀 Ishlatish

### 1. Oddiy xabar yuborish (eski usul - backward compatible)

```php
use shokirjonmk\telegram\Telegram;

Yii::$app->telegram->sendMessage(
    $chatId,
    Telegram::escape("Salom, aka!")
);
```

### 2. Multi-bot support (yangi usul)

```php
// Student bot
$studentBot = Yii::$app->telegramManager->get('student');
$studentBot->sendMessage($chatId, "Student botdan xabar");

// Staff bot
$staffBot = Yii::$app->telegramManager->get('staff');
$staffBot->sendMessage($chatId, "Staff botdan xabar");
```

### 3. Options bilan xabar yuborish

```php
use shokirjonmk\telegram\TelegramComponent;
use shokirjonmk\telegram\Keyboard;

$tg = Yii::$app->telegramManager->get('student');

$kb = Keyboard::inline([
    [
        Keyboard::inlineButton("📊 Statistika", "stats"),
        Keyboard::inlineButton("📅 Darslar", "schedule")
    ]
]);

$tg->sendMessage($chatId, "Menyuni tanlang:", [
    'keyboard' => $kb,
    'parse_mode' => 'MarkdownV2',
    'disable_web_page_preview' => true,
]);
```

### 4. Queue orqali background xabar yuborish

```php
$tg = Yii::$app->telegramManager->get('student');
$tg->enqueueSendMessage(
    $chatId,
    TelegramComponent::escapeMarkdownV2("Background xabar!")
);
```

### 5. Chat action yuborish

```php
$tg = Yii::$app->telegramManager->get('student');
$tg->sendChatAction($chatId, 'typing'); // typing, upload_photo, record_voice, etc.
```

### 6. Fayl yuklab olish

```php
use shokirjonmk\telegram\FileHelper;

$tg = Yii::$app->telegramManager->get('student');
$localPath = FileHelper::downloadFile($tg, $fileId, '@runtime/tg_files');
```

### 7. Command Router ishlatish

```php
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\TelegramComponent;
use shokirjonmk\telegram\commands\StartCommand;

$router = new CommandRouter();

// Closure bilan
$router->register('/start', function($message, $tg) {
    $chatId = $message['chat']['id'];
    $tg->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Welcome, aka!"));
});

// Class bilan
$router->register('/start', [new StartCommand(), 'handle']);

// Default command
$router->register('/default', function($message, $tg) {
    $tg->sendMessage($message['chat']['id'], "Type /start or /help");
});

// Callback query
$router->register('stats', function($callback, $tg) {
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $tg->editMessageText($chatId, $messageId, "📊 Statistika:");
    $tg->answerCallbackQuery($callback['id'], "Statistika ko'rsatildi");
});
```

### 8. Update Handler Middleware bilan to'liq webhook

```php
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;

public function actionWebhook()
{
    $body = file_get_contents('php://input');
    $update = json_decode($body, true);

    if (!$update) {
        return 'ok';
    }

    $botName = Yii::$app->request->get('bot', 'student');
    $manager = Yii::$app->telegramManager;
    $component = $manager->get($botName);

    // Command Router
    $router = new CommandRouter();
    $router->register('/start', function($msg, $tg) {
        $tg->sendMessage($msg['chat']['id'], TelegramComponent::escapeMarkdownV2("Welcome, aka!"));
    });
    $router->register('/default', function($msg, $tg) {
        $tg->sendMessage($msg['chat']['id'], "Unknown command");
    });

    // Rate Limiter (6 requests per second per user)
    $rateLimiter = new RateLimiter(6, 1);

    // Middleware
    $handler = new UpdateHandlerMiddleware($router, $rateLimiter);
    $handler->handle($update, $component);

    return 'ok';
}
```

## 📚 API Metodlari

### TelegramComponent

#### Xabar metodlari
- `sendMessage($chatId, $text, $options = [])` - Xabar yuborish
- `editMessageText($chatId, $messageId, $text, $options = [])` - Xabarni o'zgartirish
- `editMessageReplyMarkup($chatId, $messageId, $keyboard = null)` - Xabar keyboardini o'zgartirish
- `deleteMessage($chatId, $messageId)` - Xabarni o'chirish
- `forwardMessage($chatId, $fromChatId, $messageId, $options = [])` - Xabarni forward qilish

#### Media metodlari
- `sendPhoto($chatId, $photo, $caption = null, $keyboard = null)` - Rasm yuborish
- `sendDocument($chatId, $document, $caption = null, $keyboard = null, $options = [])` - Hujjat yuborish
- `sendVideo($chatId, $video, $caption = null, $keyboard = null, $options = [])` - Video yuborish
- `sendLocation($chatId, $latitude, $longitude, $keyboard = null, $options = [])` - Lokatsiya yuborish

#### Callback va Actions
- `answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false)` - Callback javob berish
- `sendChatAction($chatId, $action = 'typing')` - Chat action yuborish (typing, upload_photo, record_voice, etc.)

#### Webhook va Updates
- `setWebhook($url, $options = [])` - Webhook o'rnatish
- `deleteWebhook($dropPendingUpdates = false)` - Webhook o'chirish
- `getWebhookInfo()` - Webhook ma'lumotlarini olish
- `getUpdates($offset = null, $limit = null, $timeout = null, $allowedUpdates = null)` - Updates olish (polling)

#### Fayl va Bot ma'lumotlari
- `getFile($fileId)` - Fayl ma'lumotlarini olish
- `getMe()` - Bot ma'lumotlarini olish

#### Queue va Utility
- `enqueueSendMessage($chatId, $text, $options = [])` - Queue orqali xabar yuborish
- `escapeMarkdownV2($text)` - MarkdownV2 uchun matnni escape qilish
- `escape($text)` - Alias for escapeMarkdownV2

### TelegramManager

- `get($name = null)` - Bot component olish
- `getBotNames()` - Barcha bot nomlarini olish
- `has($name)` - Bot mavjudligini tekshirish

### Keyboard

- `Keyboard::inline($buttons)` - Inline keyboard yaratish
- `Keyboard::inlineButton($text, $callback)` - Inline button yaratish
- `Keyboard::reply($buttons, $resize = true)` - Reply keyboard yaratish

### FileHelper

- `FileHelper::downloadFile($component, $fileId, $saveDir)` - Fayl yuklab olish

### CommandRouter

- `register($name, $callable)` - Command ro'yxatdan o'tkazish
- `handleUpdate($update, $component)` - Update ni handle qilish

### RateLimiter

- `allow($userId)` - Rate limit tekshirish

## 🎛 Konfiguratsiya parametrlari

### TelegramComponent

- `token` - Bot token
- `apiUrl` - Telegram API URL (default: `https://api.telegram.org/bot`)
- `timeout` - Request timeout (default: 5)
- `enableLogs` - Logging yoqish/yoqmaslik (default: true)
- `rateLimitPerSecond` - Per-bot rate limit (default: 20)

### RateLimiter

- `limit` - Requests per window (default: 5)
- `window` - Time window in seconds (default: 1)

## 📝 Webhook Controller namunasi

`controllers/TelegramController.php`:

```php
<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;

class TelegramController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionWebhook()
    {
        $body = file_get_contents('php://input');
        $update = json_decode($body, true);

        if (!$update) {
            Yii::error('Empty update', 'telegram-error');
            return 'ok';
        }

        $botName = Yii::$app->request->get('bot', 'student');
        $manager = Yii::$app->telegramManager;
        $component = $manager->get($botName);

        try {
            $router = new CommandRouter();
            $router->register('/start', function($msg, $tg) {
                $tg->sendMessage($msg['chat']['id'], TelegramComponent::escapeMarkdownV2("Welcome, aka!"));
            });
            $router->register('/default', function($msg, $tg) {
                $tg->sendMessage($msg['chat']['id'], "Unknown command");
            });

            $rateLimiter = new RateLimiter(6, 1);
            $handler = new UpdateHandlerMiddleware($router, $rateLimiter);
            $handler->handle($update, $component);

        } catch (\Throwable $e) {
            Yii::error(['err' => $e->getMessage(), 'update' => $update], 'telegram-error');
        }

        return 'ok';
    }
}
```

Webhook URL: `https://your.domain/telegram/webhook?bot=student` yoki `?bot=staff`

## 🔧 Qo'shimcha xususiyatlar

- **Retry mechanism**: Exponential backoff bilan avtomatik retry (default: 3 attempts)
- **Rate limiting**: Ikki darajali - global per-bot va per-user
- **Queue support**: Background message sending
- **Logging**: Barcha xatolar `telegram-error` kategoriyasi bilan loglanadi
- **Backward compatibility**: Eski `Telegram` class hali ham ishlaydi
- **Local polling support**: Python telegram bot kabi lokalda ishlash uchun polling

## 🖥️ Lokalda ishlatish (Polling)

Python telegram bot kabi lokalda ishlatish uchun polling ishlatishingiz mumkin. Ikki usul bor:

### 1. Console Command (Tavsiya etiladi)

`console/commands/TelegramPollingCommand.php` faylini yarating (misol: `examples/PollingCommand.php`):

```php
<?php
namespace app\console\commands;

use Yii;
use yii\console\Controller;
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;

class TelegramPollingCommand extends Controller
{
    public $bot = 'student';
    public $timeout = 30;

    public function actionPolling()
    {
        $manager = Yii::$app->telegramManager;
        $component = $manager->get($this->bot);

        $router = new CommandRouter();
        // Register your commands here
        $router->register('/start', function($msg, $tg) {
            $tg->sendMessage($msg['chat']['id'], "Welcome!");
        });

        $rateLimiter = new RateLimiter(6, 1);
        $handler = new UpdateHandlerMiddleware($router, $rateLimiter);

        $offset = 0;
        while (true) {
            $updates = $component->getUpdates($offset, 100, $this->timeout, null);
            if (!empty($updates['ok']) && !empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $offset = $update['update_id'] + 1;
                    $handler->handle($update, $component);
                }
            }
        }
    }
}
```

Ishga tushirish:

```bash
php yii telegram-polling/polling --bot=student --timeout=30
```

### 2. Standalone Script

`examples/polling.php` faylini ishlatishingiz mumkin:

```bash
php examples/polling.php --bot=student --timeout=30
```

### Polling vs Webhook

| Xususiyat | Polling (Lokal) | Webhook (Production) |
|-----------|----------------|---------------------|
| **Ishlash joyi** | Lokal kompyuter | Server |
| **Webhook kerakmi** | ❌ Yo'q | ✅ Ha |
| **Natijaviylik** | Pastroq | Yuqori |
| **Development** | ✅ Qulay | ❌ Qiyin |
| **Production** | ❌ Tavsiya etilmaydi | ✅ Tavsiya etiladi |

**Tavsiya**: 
- **Development** uchun: Polling ishlating (lokalda)
- **Production** uchun: Webhook ishlating (serverda)

## 📝 License

MIT

## 👤 Author

ShokirjonMK
