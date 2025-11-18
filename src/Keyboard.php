<?php

namespace shokirjonmk\telegram;

/**
 * Keyboard Builder
 * 
 * Helper class for building Telegram keyboard markups
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class Keyboard
{
    /**
     * Create inline keyboard
     * 
     * @param array $buttons Array of button rows, each row is array of buttons
     * @return array Inline keyboard markup
     * 
     * @example
     * Keyboard::inline([
     *     [
     *         Keyboard::inlineButton("Button 1", "callback1"),
     *         Keyboard::inlineButton("Button 2", "callback2")
     *     ],
     *     [Keyboard::inlineButton("Button 3", "callback3")]
     * ])
     */
    public static function inline(array $buttons): array
    {
        return [
            'inline_keyboard' => $buttons
        ];
    }

    /**
     * Create inline button
     * 
     * @param string $text Button text
     * @param string $callbackData Callback data
     * @return array Button array
     */
    public static function inlineButton(string $text, string $callbackData): array
    {
        return [
            'text' => $text,
            'callback_data' => $callbackData
        ];
    }

    /**
     * Create reply keyboard
     * 
     * @param array $buttons Array of button rows
     * @param bool $resize Resize keyboard (default: true)
     * @return array Reply keyboard markup
     * 
     * @example
     * Keyboard::reply([
     *     [['text' => 'Button 1'], ['text' => 'Button 2']],
     *     [['text' => 'Button 3']]
     * ])
     */
    public static function reply(array $buttons, bool $resize = true): array
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resize
        ];
    }
}
