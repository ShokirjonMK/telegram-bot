<?php

namespace shokirjonmk\telegram;

class Keyboard
{
    public static function inline($buttons)
    {
        return [
            'inline_keyboard' => $buttons
        ];
    }

    public static function inlineButton($text, $callback)
    {
        return [
            'text' => $text,
            'callback_data' => $callback
        ];
    }

    public static function reply($buttons, $resize = true)
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resize
        ];
    }
}
