<?php

namespace shokirjonmk\telegram;

use Yii;
use yii\httpclient\Client;

class Telegram
{
    public $botToken;
    public $apiUrl = "https://api.telegram.org/bot";
    public $timeout = 5;
    public $enableLogs = true;

    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
        ]);
    }

    private function request($method, $params = [])
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        try {
            $response = $this->client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->setData($params)
                ->setOptions(['timeout' => $this->timeout])
                ->send();

            if (!$response->isOk) {
                throw new TelegramException("Telegram API error: " . $response->content);
            }

            return $response->data;

        } catch (\Throwable $e) {
            if ($this->enableLogs) {
                Yii::error([
                    'method' => $method,
                    'params' => $params,
                    'error' => $e->getMessage()
                ], 'telegram-error');
            }

            throw new TelegramException($e->getMessage());
        }
    }

    public function sendMessage($chatId, $text, $keyboard = null, $parse = "MarkdownV2")
    {
        return $this->request("sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parse,
            'reply_markup' => $keyboard ? json_encode($keyboard) : null,
        ]);
    }

    public function sendPhoto($chatId, $photo, $caption = null, $keyboard = null)
    {
        return $this->request("sendPhoto", [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => "MarkdownV2",
            'reply_markup' => $keyboard ? json_encode($keyboard) : null
        ]);
    }

    public function editMessageText($chatId, $messageId, $text, $keyboard = null)
    {
        return $this->request("editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => "MarkdownV2",
            'reply_markup' => $keyboard ? json_encode($keyboard) : null
        ]);
    }

    public function answerCallbackQuery($callbackId, $text = null, $alert = false)
    {
        return $this->request("answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $alert
        ]);
    }

    public function getFile($fileId)
    {
        return $this->request("getFile", [
            'file_id' => $fileId
        ]);
    }

    public static function escape($text)
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!])/', '\\\\$1', $text);
    }
}

