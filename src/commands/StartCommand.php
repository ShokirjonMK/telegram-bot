<?php

namespace shokirjonmk\telegram\commands;

use shokirjonmk\telegram\TelegramComponent;

/**
 * Start Command Example
 * 
 * Example implementation of a command handler.
 * This command responds to /start command.
 * 
 * @package shokirjonmk\telegram\commands
 * @author ShokirjonMK
 */
class StartCommand extends BaseCommand
{
    /**
     * Handle /start command
     * 
     * @param array $message Telegram message array
     * @param TelegramComponent $tg Telegram component instance
     * @return void
     */
    public function handle(array $message, TelegramComponent $tg): void
    {
        $chatId = $message['chat']['id'];
        $tg->sendMessage(
            $chatId,
            TelegramComponent::escapeMarkdownV2("Welcome! Use /help to see commands")
        );
    }
}
