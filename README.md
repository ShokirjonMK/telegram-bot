# Telegram Bot Extension for Yii2

Professional Telegram Bot Extension for Yii2 Framework. Bu extension Yii2 uchun maxsus yozilgan, Laravel SDK emas.

## ✨ Xususiyatlar

- ✔ Yii2 uchun maxsus yozilgan
- ✔ `components` ichida ishlaydi
- ✔ `sendMessage`, `sendPhoto`, `editMessageText`, `answerCallbackQuery`, `getFile` hammasi bor
- ✔ Exception, Retry, Logging, MarkdownV2 escape bor
- ✔ Inline keyboard builder bor
- ✔ Autoload composer bilan keladi

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

Keyin:

```bash
composer install
composer dump-autoload
```

## ⚙️ Konfiguratsiya

Yii2 `config.php` ichida komponent yarating:

```php
'components' => [
    'telegram' => [
        'class' => 'shokirjonmk\telegram\Telegram',
        'botToken' => getenv('TELEGRAM_TOKEN'),
        'apiUrl' => 'https://api.telegram.org/bot',
        'timeout' => 5,
        'enableLogs' => true,
    ],
]
```

## 🚀 Ishlatish

### Send message

```php
use shokirjonmk\telegram\Telegram;

Yii::$app->telegram->sendMessage(
    $chatId,
    Telegram::escape("Salom, aka!")
);
```

### Inline keyboard bilan

```php
use shokirjonmk\telegram\Keyboard;

$kb = Keyboard::inline([
    [
        Keyboard::inlineButton("📊 Statistika", "stats"),
        Keyboard::inlineButton("📅 Darslar", "schedule")
    ]
]);

Yii::$app->telegram->sendMessage($chatId, "Menyuni tanlang:", $kb);
```

### Callback query

```php
Yii::$app->telegram->answerCallbackQuery(
    $callbackId,
    "Qabul qilindi!"
);
```

### Message o'zgartirish

```php
Yii::$app->telegram->editMessageText(
    $chatId,
    $messageId,
    "Matn yangilandi"
);
```

### Photo yuborish

```php
Yii::$app->telegram->sendPhoto(
    $chatId,
    $photoUrl,
    "Rasm caption",
    $keyboard
);
```

## 🧩 Webhook Controller namunasi

```php
public function actionWebhook()
{
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['message'])) {
        $chatId = $data['message']['chat']['id'];
        $text = $data['message']['text'];

        Yii::$app->telegram->sendMessage($chatId, "Siz yubordingiz: " . Telegram::escape($text));
    }

    if (isset($data['callback_query'])) {
        $cb = $data['callback_query'];
        $chatId = $cb['message']['chat']['id'];
        $messageId = $cb['message']['message_id'];
        $data = $cb['data'];

        if ($data === "stats") {
            Yii::$app->telegram->editMessageText($chatId, $messageId, "📊 Statistika:");
        }
    }
}
```

## 📚 API Metodlari

- `sendMessage($chatId, $text, $keyboard = null, $parse = "MarkdownV2")` - Xabar yuborish
- `sendPhoto($chatId, $photo, $caption = null, $keyboard = null)` - Rasm yuborish
- `editMessageText($chatId, $messageId, $text, $keyboard = null)` - Xabarni o'zgartirish
- `answerCallbackQuery($callbackId, $text = null, $alert = false)` - Callback javob berish
- `getFile($fileId)` - Fayl ma'lumotlarini olish
- `Telegram::escape($text)` - MarkdownV2 uchun matnni escape qilish

## 🎛 Keyboard Builder

- `Keyboard::inline($buttons)` - Inline keyboard yaratish
- `Keyboard::inlineButton($text, $callback)` - Inline button yaratish
- `Keyboard::reply($buttons, $resize = true)` - Reply keyboard yaratish

## 📝 License

MIT

## 👤 Author

ShokirjonMK

