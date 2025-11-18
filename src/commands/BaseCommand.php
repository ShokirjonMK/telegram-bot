<?php

namespace shokirjonmk\telegram\commands;

use shokirjonmk\telegram\TelegramComponent;

abstract class BaseCommand
{
    abstract public function handle(array $message, TelegramComponent $tg);
}

