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

- `sendMessage($chatId, $text, $options = [])` - Xabar yuborish
- `sendPhoto($chatId, $photo, $caption = null, $keyboard = null)` - Rasm yuborish
- `editMessageText($chatId, $messageId, $text, $options = [])` - Xabarni o'zgartirish
- `answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false)` - Callback javob berish
- `sendChatAction($chatId, $action = 'typing')` - Chat action yuborish
- `getFile($fileId)` - Fayl ma'lumotlarini olish
- `setWebhook($url)` - Webhook o'rnatish
- `enqueueSendMessage($chatId, $text, $options = [])` - Queue orqali xabar yuborish
- `escapeMarkdownV2($text)` - MarkdownV2 uchun matnni escape qilish

### TelegramManager

- `get($name = null)` - Bot component olish

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

## 📝 License

MIT

## 👤 Author

ShokirjonMK
