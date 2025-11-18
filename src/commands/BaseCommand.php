<?php

namespace shokirjonmk\telegram\commands;

use shokirjonmk\telegram\TelegramComponent;

/**
 * Base Command Class
 * 
 * Abstract base class for Telegram bot commands.
 * Extend this class to create custom commands.
 * 
 * @package shokirjonmk\telegram\commands
 * @author ShokirjonMK
 */
abstract class BaseCommand
{
    /**
     * Handle command
     * 
     * @param array $message Telegram message array
     * @param TelegramComponent $tg Telegram component instance
     * @return void
     */
    abstract public function handle(array $message, TelegramComponent $tg): void;
}
