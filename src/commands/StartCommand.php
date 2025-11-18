<?php

namespace shokirjonmk\telegram\commands;

use shokirjonmk\telegram\TelegramComponent;

class StartCommand extends BaseCommand
{
    public function handle(array $message, TelegramComponent $tg)
    {
        $chatId = $message['chat']['id'];
        $tg->sendMessage($chatId, TelegramComponent::escapeMarkdownV2("Welcome! Use /help to see commands"));
    }
}

