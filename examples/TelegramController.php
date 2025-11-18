<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use shokirjonmk\telegram\CommandRouter;
use shokirjonmk\telegram\UpdateHandlerMiddleware;
use shokirjonmk\telegram\RateLimiter;
use shokirjonmk\telegram\TelegramComponent;
use shokirjonmk\telegram\Keyboard;

/**
 * Example Telegram Webhook Controller
 * 
 * Place this in your controllers directory and configure routes
 */
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

        // Choose bot key: if you set different webhook endpoints per bot, use that;
        // Example: pass ?bot=staff
        $botName = Yii::$app->request->get('bot', 'student');
        $manager = Yii::$app->telegramManager;
        $component = $manager->get($botName);

        try {
            // Create router + middleware dynamically or fetch from DI container
            $router = new CommandRouter();

            // Register commands
            $router->register('/start', function($msg, $tg) {
                $chatId = $msg['chat']['id'];
                $kb = Keyboard::inline([
                    [
                        Keyboard::inlineButton("📊 Statistika", "stats"),
                        Keyboard::inlineButton("📅 Darslar", "schedule")
                    ]
                ]);
                $tg->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Welcome, aka!"), [
                    'keyboard' => $kb
                ]);
            });

            $router->register('/help', function($msg, $tg) {
                $chatId = $msg['chat']['id'];
                $tg->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Available commands:\n/start - Start bot\n/help - Show help"));
            });

            $router->register('/default', function($msg, $tg) {
                $tg->sendMessage($msg['chat']['id'], "Unknown command. Type /start or /help");
            });

            // Callback query handlers
            $router->register('stats', function($cb, $tg) {
                $chatId = $cb['message']['chat']['id'];
                $messageId = $cb['message']['message_id'];
                $tg->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📊 Statistika:\n\nFoydalanuvchilar: 100\nXabarlar: 500"));
                $tg->answerCallbackQuery($cb['id'], "Statistika ko'rsatildi");
            });

            $router->register('schedule', function($cb, $tg) {
                $chatId = $cb['message']['chat']['id'];
                $messageId = $cb['message']['message_id'];
                $tg->editMessageText($chatId, $messageId, TelegramComponent::escapeMarkdownV2("📅 Darslar jadvali:\n\nDushanba: 9:00 - Matematika\nSeshanba: 10:00 - Fizika"));
                $tg->answerCallbackQuery($cb['id'], "Darslar jadvali ko'rsatildi");
            });

            $router->register('/callback', function($cb, $tg) {
                $tg->answerCallbackQuery($cb['id'], "Callback qabul qilindi");
            });

            // Rate Limiter (6 requests per second per user)
            $rateLimiter = new RateLimiter(6, 1);

            // Middleware
            $handler = new UpdateHandlerMiddleware($router, $rateLimiter);

            // Option: push the update into queue to process asynchronously
            // Yii::$app->queue->push(new \yii\queue\jobs\Job(['payload'=>$update]));
            $handler->handle($update, $component);

        } catch (\Throwable $e) {
            Yii::error(['err' => $e->getMessage(), 'update' => $update], 'telegram-error');
        }

        return 'ok';
    }
}

