<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * CommandRouter: register commands and dispatch by text ("/start", "/help", etc.)
 */
class CommandRouter
{
    protected $commands = [];

    public function register($name, $callable)
    {
        $this->commands[$name] = $callable;
    }

    public function handleUpdate(array $update, TelegramComponent $component)
    {
        // Detect message text or callback_data
        if (isset($update['message'])) {
            $message = $update['message'];
            $text = $message['text'] ?? '';
            
            if ($text && strpos($text, '/') === 0) {
                $cmd = explode(' ', trim($text))[0];
                $cmd = strtolower($cmd); // Normalize command
                
                if (isset($this->commands[$cmd])) {
                    try {
                        call_user_func($this->commands[$cmd], $message, $component);
                        return true;
                    } catch (\Throwable $e) {
                        Yii::error([
                            'command' => $cmd,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ], 'telegram-command-error');
                        return false;
                    }
                }
            }
            
            // fallback: no command matched
            if (isset($this->commands['/default'])) {
                try {
                    call_user_func($this->commands['/default'], $message, $component);
                    return true;
                } catch (\Throwable $e) {
                    Yii::error([
                        'command' => '/default',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ], 'telegram-command-error');
                }
            }
        }

        if (isset($update['callback_query'])) {
            $cb = $update['callback_query'];
            // you can route callback_data to commands too
            $data = $cb['data'] ?? '';
            
            if ($data && isset($this->commands[$data])) {
                try {
                    call_user_func($this->commands[$data], $cb, $component);
                    return true;
                } catch (\Throwable $e) {
                    Yii::error([
                        'callback' => $data,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ], 'telegram-command-error');
                    // Answer callback with error
                    try {
                        $component->answerCallbackQuery($cb['id'], 'Error occurred', false);
                    } catch (\Throwable $e2) {
                        // Ignore
                    }
                    return false;
                }
            }
            
            if (isset($this->commands['/callback'])) {
                try {
                    call_user_func($this->commands['/callback'], $cb, $component);
                    return true;
                } catch (\Throwable $e) {
                    Yii::error([
                        'command' => '/callback',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ], 'telegram-command-error');
                }
            }
        }

        return false;
    }
}
