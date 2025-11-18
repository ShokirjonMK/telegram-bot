<?php

/**
 * Console command for polling Telegram updates (local development)
 * 
 * Usage:
 * php yii telegram/polling [--bot=student] [--timeout=30]
 * 
 * Place this in your console/commands/TelegramPollingCommand.php
 */

namespace app\console\commands;

use Yii;
use yii\console\Controller;
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;
use shokirjonmk\telegram\Keyboard;

class TelegramPollingCommand extends Controller
{
    public $bot = 'student';
    public $timeout = 30;

    public function options($actionID)
    {
        return ['bot', 'timeout'];
    }

    public function actionPolling()
    {
        $this->stdout("Starting Telegram bot polling...\n", \yii\helpers\Console::FG_GREEN);
        $this->stdout("Bot: {$this->bot}\n");
        $this->stdout("Timeout: {$this->timeout}s\n");
        $this->stdout("Press Ctrl+C to stop\n\n");

        // Get bot component
        $manager = Yii::$app->telegramManager;
        $component = $manager->get($this->bot);

        // Setup command router
        $router = new CommandRouter();
        $this->registerCommands($router);

        // Setup rate limiter
        $rateLimiter = new RateLimiter(6, 1);

        // Setup middleware
        $handler = new UpdateHandlerMiddleware($router, $rateLimiter);

        $offset = 0;

        while (true) {
            try {
                // Get updates
                $updates = $component->getUpdates($offset, 100, $this->timeout, null);

                if (empty($updates['ok']) || !$updates['ok']) {
                    $this->stdout("Error getting updates: " . ($updates['description'] ?? 'Unknown') . "\n", \yii\helpers\Console::FG_RED);
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
                        $this->stdout("Error handling update: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                        Yii::error([
                            'update' => $update,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ], 'telegram-polling-error');
                    }
                }
            } catch (\Throwable $e) {
                $this->stdout("Polling error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                Yii::error([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 'telegram-polling-error');
                sleep(5); // Wait before retry
            }
        }
    }

    /**
     * Register your commands here
     */
    protected function registerCommands(CommandRouter $router)
    {
        // Start command
        $router->register('/start', function ($message, $component) {
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
        $router->register('/help', function ($message, $component) {
            $chatId = $message['chat']['id'];
            $component->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Available commands:\n/start - Start bot\n/help - Show help"));
        });

        // Default command
        $router->register('/default', function ($message, $component) {
            $component->sendMessage($message['chat']['id'], "Unknown command. Type /start or /help");
        });

        // Callback handlers
        $router->register('stats', function ($callback, $component) {
            $chatId = $callback['message']['chat']['id'];
            $messageId = $callback['message']['message_id'];
            $component->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📊 Statistika:\n\nFoydalanuvchilar: 100\nXabarlar: 500"));
            $component->answerCallbackQuery($callback['id'], "Statistika ko'rsatildi");
        });

        $router->register('schedule', function ($callback, $component) {
            $chatId = $callback['message']['chat']['id'];
            $messageId = $callback['message']['message_id'];
            $component->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📅 Darslar jadvali:\n\nDushanba: 9:00 - Matematika\nSeshanba: 10:00 - Fizika"));
            $component->answerCallbackQuery($callback['id'], "Darslar jadvali ko'rsatildi");
        });

        $router->register('/callback', function ($callback, $component) {
            $component->answerCallbackQuery($callback['id'], "Callback qabul qilindi");
        });
    }
}
