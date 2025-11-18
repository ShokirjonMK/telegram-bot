<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * Command Router
 * 
 * Routes Telegram commands and callback queries to registered handlers.
 * Supports command registration and automatic dispatching.
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class CommandRouter
{
    /** @var array Registered commands ['command' => callable, ...] */
    protected $commands = [];

    /**
     * Register command handler
     * 
     * @param string $name Command name (e.g., '/start', 'callback_data')
     * @param callable $callable Handler function/closure
     * @return void
     */
    public function register(string $name, callable $callable): void
    {
        $this->commands[$name] = $callable;
    }

    /**
     * Handle Telegram update
     * 
     * @param array $update Telegram update array
     * @param TelegramComponent $component Telegram component instance
     * @return bool True if handled, false otherwise
     */
    public function handleUpdate(array $update, TelegramComponent $component): bool
    {
        // Handle message commands
        if (isset($update['message'])) {
            $message = $update['message'];
            $text = $message['text'] ?? '';
            
            if ($text && strpos($text, '/') === 0) {
                $cmd = explode(' ', trim($text))[0];
                $cmd = strtolower($cmd); // Normalize command
                
                if (isset($this->commands[$cmd])) {
                    return $this->executeCommand($cmd, $this->commands[$cmd], $message, $component);
                }
            }
            
            // Fallback: default command
            if (isset($this->commands['/default'])) {
                return $this->executeCommand('/default', $this->commands['/default'], $message, $component);
            }
        }

        // Handle callback queries
        if (isset($update['callback_query'])) {
            $cb = $update['callback_query'];
            $data = $cb['data'] ?? '';
            
            if ($data && isset($this->commands[$data])) {
                return $this->executeCallback($data, $this->commands[$data], $cb, $component);
            }
            
            // Fallback: default callback handler
            if (isset($this->commands['/callback'])) {
                return $this->executeCallback('/callback', $this->commands['/callback'], $cb, $component);
            }
        }

        return false;
    }

    /**
     * Execute command handler
     * 
     * @param string $cmd Command name
     * @param callable $handler Handler function
     * @param array $message Message data
     * @param TelegramComponent $component Telegram component
     * @return bool True on success
     */
    protected function executeCommand(string $cmd, callable $handler, array $message, TelegramComponent $component): bool
    {
        try {
            call_user_func($handler, $message, $component);
            return true;
        } catch (\Throwable $e) {
            Yii::error([
                'command' => $cmd,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Constants::LOG_CATEGORY_COMMAND_ERROR);
            return false;
        }
    }

    /**
     * Execute callback handler
     * 
     * @param string $callback Callback data
     * @param callable $handler Handler function
     * @param array $callbackQuery Callback query data
     * @param TelegramComponent $component Telegram component
     * @return bool True on success
     */
    protected function executeCallback(string $callback, callable $handler, array $callbackQuery, TelegramComponent $component): bool
    {
        try {
            call_user_func($handler, $callbackQuery, $component);
            return true;
        } catch (\Throwable $e) {
            Yii::error([
                'callback' => $callback,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Constants::LOG_CATEGORY_COMMAND_ERROR);
            
            // Answer callback with error
            try {
                $component->answerCallbackQuery($callbackQuery['id'], 'Error occurred', false);
            } catch (\Throwable $e2) {
                // Ignore
            }
            return false;
        }
    }
}
