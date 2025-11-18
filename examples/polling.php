<?php
/**
 * Standalone polling script for local development
 * 
 * Usage:
 * php examples/polling.php
 * 
 * Or with options:
 * php examples/polling.php --bot=student --timeout=30
 */

require __DIR__ . '/../vendor/autoload.php';

// Yii2 bootstrap (adjust path if needed)
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

// Load config (adjust path if needed)
$config = require __DIR__ . '/../config/console.php';
new yii\console\Application($config);

use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;
use shokirjonmk\telegram\Keyboard;

// Parse command line arguments
$options = [
    'bot' => 'student',
    'timeout' => 30,
];

foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2));
        if (count($parts) === 2) {
            $options[$parts[0]] = $parts[1];
        }
    }
}

echo "Starting Telegram bot polling...\n";
echo "Bot: {$options['bot']}\n";
echo "Timeout: {$options['timeout']}s\n";
echo "Press Ctrl+C to stop\n\n";

// Get bot component
$manager = Yii::$app->telegramManager;
$component = $manager->get($options['bot']);

// Setup command router
$router = new CommandRouter();
registerCommands($router);

// Setup rate limiter
$rateLimiter = new RateLimiter(6, 1);

// Setup middleware
$handler = new UpdateHandlerMiddleware($router, $rateLimiter);

$offset = 0;

while (true) {
    try {
        // Get updates
        $updates = $component->getUpdates($offset, 100, (int)$options['timeout'], null);

        if (empty($updates['ok']) || !$updates['ok']) {
            echo "Error getting updates: " . ($updates['description'] ?? 'Unknown') . "\n";
            sleep(5);
            continue;
        }

        $results = $updates['result'] ?? [];

        if (empty($results)) {
            // No updates, continue polling
            continue;
        }

        foreach ($results as $update) {
            $updateId = $update['update_id'] ?? 0;
            $offset = $updateId + 1;

            // Handle update
            try {
                $handler->handle($update, $component);
            } catch (\Throwable $e) {
                echo "Error handling update: " . $e->getMessage() . "\n";
                Yii::error([
                    'update' => $update,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 'telegram-polling-error');
            }
        }

    } catch (\Throwable $e) {
        echo "Polling error: " . $e->getMessage() . "\n";
        Yii::error([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'telegram-polling-error');
        sleep(5); // Wait before retry
    }
}

/**
 * Register your commands here
 */
function registerCommands(CommandRouter $router)
{
    // Start command
    $router->register('/start', function($message, $component) {
        $chatId = $message['chat']['id'];
        $kb = Keyboard::inline([
            [
                Keyboard::inlineButton("📊 Statistika", "stats"),
                Keyboard::inlineButton("📅 Darslar", "schedule")
            ]
        ]);
        $component->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Welcome, aka!"), [
            'keyboard' => $kb
        ]);
    });

    // Help command
    $router->register('/help', function($message, $component) {
        $chatId = $message['chat']['id'];
        $component->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Available commands:\n/start - Start bot\n/help - Show help"));
    });

    // Default command
    $router->register('/default', function($message, $component) {
        $component->sendMessage($message['chat']['id'], "Unknown command. Type /start or /help");
    });

    // Callback handlers
    $router->register('stats', function($callback, $component) {
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $component->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📊 Statistika:\n\nFoydalanuvchilar: 100\nXabarlar: 500"));
        $component->answerCallbackQuery($callback['id'], "Statistika ko'rsatildi");
    });

    $router->register('schedule', function($callback, $component) {
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $component->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📅 Darslar jadvali:\n\nDushanba: 9:00 - Matematika\nSeshanba: 10:00 - Fizika"));
        $component->answerCallbackQuery($callback['id'], "Darslar jadvali ko'rsatildi");
    });

    $router->register('/callback', function($callback, $component) {
        $component->answerCallbackQuery($callback['id'], "Callback qabul qilindi");
    });
}

