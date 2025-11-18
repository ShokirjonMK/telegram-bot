<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * Update Handler Middleware
 * 
 * Middleware for handling Telegram updates with rate limiting.
 * Applies rate limiting before dispatching to command router.
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class UpdateHandlerMiddleware
{
    /** @var CommandRouter Command router instance */
    protected $router;
    
    /** @var RateLimiter Rate limiter instance */
    protected $rateLimiter;

    /**
     * Constructor
     * 
     * @param CommandRouter $router Command router
     * @param RateLimiter $rateLimiter Rate limiter
     */
    public function __construct(CommandRouter $router, RateLimiter $rateLimiter)
    {
        $this->router = $router;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Handle Telegram update
     * 
     * @param array $update Telegram update array
     * @param TelegramComponent $component Telegram component instance
     * @return void
     */
    public function handle(array $update, TelegramComponent $component): void
    {
        $userId = $this->extractUserId($update);

        if ($userId && !$this->rateLimiter->allow($userId)) {
            // Too many requests from this user
            if (isset($update['callback_query'])) {
                try {
                    $component->answerCallbackQuery(
                        $update['callback_query']['id'],
                        'Too many requests, try again later',
                        false
                    );
                } catch (\Throwable $e) {
                    // Ignore errors when answering rate limit
                }
            }
            return;
        }

        // Let command router handle update
        $this->router->handleUpdate($update, $component);
    }

    /**
     * Extract user ID from update
     * 
     * @param array $update Telegram update
     * @return int|null User ID or null
     */
    protected function extractUserId(array $update): ?int
    {
        if (isset($update['message']['from']['id'])) {
            return $update['message']['from']['id'];
        }
        if (isset($update['callback_query']['from']['id'])) {
            return $update['callback_query']['from']['id'];
        }
        return null;
    }
}
