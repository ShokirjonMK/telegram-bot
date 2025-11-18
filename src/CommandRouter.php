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
            $chatId = $message['chat']['id'] ?? null;
            if ($text && strpos($text, '/') === 0) {
                $cmd = explode(' ', trim($text))[0];
                if (isset($this->commands[$cmd])) {
                    call_user_func($this->commands[$cmd], $message, $component);
                    return true;
                }
            }
            // fallback: no command matched
            if (isset($this->commands['/default'])) {
                call_user_func($this->commands['/default'], $message, $component);
            }
        }

        if (isset($update['callback_query'])) {
            $cb = $update['callback_query'];
            // you can route callback_data to commands too
            $data = $cb['data'] ?? '';
            if ($data && isset($this->commands[$data])) {
                call_user_func($this->commands[$data], $cb, $component);
                return true;
            }
            if (isset($this->commands['/callback'])) {
                call_user_func($this->commands['/callback'], $cb, $component);
            }
        }

        return false;
    }
}
