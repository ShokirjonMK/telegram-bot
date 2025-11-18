<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * Handles incoming update:
 * - applies rate limit per user
 * - dispatches to CommandRouter
 * - supports callback queries
 */
class UpdateHandlerMiddleware
{
    protected $router;
    protected $rateLimiter;

    public function __construct(CommandRouter $router, RateLimiter $rateLimiter)
    {
        $this->router = $router;
        $this->rateLimiter = $rateLimiter;
    }

    public function handle(array $update, TelegramComponent $component)
    {
        $userId = null;
        if (isset($update['message'])) {
            $userId = $update['message']['from']['id'] ?? null;
        } elseif (isset($update['callback_query'])) {
            $userId = $update['callback_query']['from']['id'] ?? null;
        }

        if ($userId && !$this->rateLimiter->allow($userId)) {
            // Too many requests from this user: answer callback or ignore
            if (isset($update['callback_query'])) {
                $component->answerCallbackQuery($update['callback_query']['id'], 'Too many requests, try again later', false);
            }
            return;
        }

        // Let command router handle update
        $this->router->handleUpdate($update, $component);
    }
}

