<?php

namespace shokirjonmk\telegram;

use Yii;
use yii\httpclient\Client;

/**
 * Lightweight Telegram client with:
 * - retry (exponential backoff)
 * - rate limiting (via Redis)
 * - enqueue helper (queue job)
 * - chat actions
 */
class TelegramComponent
{
    public $token;
    public $apiUrl = 'https://api.telegram.org/bot';
    public $timeout = 5;
    public $enableLogs = true;
    public $rateLimitPerSecond = 20; // global per-bot (adjust)
    public $rateLimitUserKeyPrefix = 'tg_rate_user_';

    private $http;

    public function __construct($config = [])
    {
        foreach ($config as $k => $v) {
            $this->$k = $v;
        }
        $this->http = new Client(['transport' => 'yii\httpclient\CurlTransport']);
    }

    protected function buildUrl($method)
    {
        return rtrim($this->apiUrl, '/') . '/' . $this->token . '/' . $method;
    }

    /**
     * Low-level request with retry
     */
    protected function sendRequest($method, $params = [], $attempts = 3)
    {
        $url = $this->buildUrl($method);
        $attempt = 0;
        while ($attempt < $attempts) {
            try {
                $attempt++;
                $response = $this->http->createRequest()
                    ->setMethod('POST')
                    ->setUrl($url)
                    ->setOptions(['timeout' => $this->timeout])
                    ->setData($params)
                    ->send();

                if ($response->isOk) {
                    return $response->data;
                }

                throw new \Exception('HTTP error: ' . $response->content);

            } catch (\Throwable $e) {
                if ($this->enableLogs) {
                    Yii::error([
                        'method' => $method,
                        'params' => $params,
                        'err' => $e->getMessage(),
                        'attempt' => $attempt
                    ], 'telegram-error');
                }

                // exponential backoff
                if ($attempt < $attempts) {
                    sleep(pow(2, $attempt - 1));
                }
                if ($attempt >= $attempts) {
                    throw new TelegramException($e->getMessage());
                }
                // retry loop continues
            }
        }
        throw new TelegramException('Failed to send request');
    }

    /**
     * Rate limiter: simple token bucket per-bot user or global
     */
    protected function checkRateLimit($key = null)
    {
        // Check if Redis is available
        if (!Yii::$app->has('redis')) {
            return true; // Skip rate limiting if Redis not available
        }

        // default global key per bot
        $key = $key ?: 'tg_rate_bot_' . md5($this->token);
        $redis = Yii::$app->redis;
        // Use INCR + EXPIRE
        $count = $redis->incr($key);
        if ($count == 1) {
            $redis->expire($key, 1);
        }
        if ($count > $this->rateLimitPerSecond) {
            return false;
        }
        return true;
    }

    /** PUBLIC API METHODS **/

    public function sendMessage($chatId, $text, $options = [])
    {
        if (!$this->checkRateLimit()) {
            // optionally enqueue or throw
            throw new TelegramException('Rate limit exceeded');
        }

        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => isset($options['parse_mode']) ? $options['parse_mode'] : 'MarkdownV2',
            'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
        ], $options['extra'] ?? []);

        if (isset($options['keyboard'])) {
            $data['reply_markup'] = json_encode($options['keyboard']);
        }

        return $this->sendRequest('sendMessage', $data, $options['attempts'] ?? 3);
    }

    public function sendPhoto($chatId, $photo, $caption = null, $keyboard = null)
    {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'parse_mode' => 'MarkdownV2',
        ];

        if ($caption !== null) {
            $data['caption'] = $caption;
        }

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        return $this->sendRequest('sendPhoto', $data);
    }

    public function editMessageText($chatId, $messageId, $text, $options = [])
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'MarkdownV2',
        ], $options['extra'] ?? []);

        if (isset($options['keyboard'])) {
            $data['reply_markup'] = json_encode($options['keyboard']);
        }

        return $this->sendRequest('editMessageText', $data, $options['attempts'] ?? 3);
    }

    public function answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false)
    {
        return $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? true : false,
        ]);
    }

    public function sendChatAction($chatId, $action = 'typing')
    {
        return $this->sendRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function getFile($fileId)
    {
        return $this->sendRequest('getFile', ['file_id' => $fileId]);
    }

    public function setWebhook($url)
    {
        return $this->sendRequest('setWebhook', ['url' => $url]);
    }

    /**
     * Enqueue sending message (background)
     */
    public function enqueueSendMessage($chatId, $text, $options = [])
    {
        if (!Yii::$app->has('queue')) {
            throw new TelegramException('Queue component not configured');
        }

        $payload = [
            'botToken' => $this->token,
            'chatId' => $chatId,
            'text' => $text,
            'options' => $options,
        ];
        Yii::$app->queue->push(new SendMessageJob(['payload' => $payload]));
        return true;
    }

    /**
     * escape for MarkdownV2
     */
    public static function escapeMarkdownV2($text)
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!])/', '\\\\$1', $text);
    }

    /**
     * Alias for backward compatibility
     */
    public static function escape($text)
    {
        return self::escapeMarkdownV2($text);
    }
}

