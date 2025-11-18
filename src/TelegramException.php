<?php

namespace shokirjonmk\telegram;

/**
 * Telegram Bot Extension Exception
 * 
 * Custom exception class for Telegram-related errors
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class TelegramException extends \Exception
{
    /**
     * Create exception with formatted message
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
