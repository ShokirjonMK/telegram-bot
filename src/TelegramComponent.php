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
        if (empty($this->token)) {
            throw new TelegramException('Bot token is not set');
        }

        $url = $this->buildUrl($method);
        $attempt = 0;
        $lastException = null;

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
                    $data = $response->data;
                    // Check Telegram API response
                    if (isset($data['ok']) && !$data['ok']) {
                        $error = $data['description'] ?? 'Unknown Telegram API error';
                        throw new TelegramException('Telegram API error: ' . $error);
                    }
                    return $data;
                }

                throw new \Exception('HTTP error: ' . $response->content);
            } catch (TelegramException $e) {
                // Don't retry Telegram API errors (like invalid token, etc.)
                throw $e;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($this->enableLogs) {
                    Yii::error([
                        'method' => $method,
                        'params' => $params,
                        'err' => $e->getMessage(),
                        'attempt' => $attempt,
                        'trace' => $e->getTraceAsString()
                    ], 'telegram-error');
                }

                // exponential backoff
                if ($attempt < $attempts) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        // All attempts failed
        throw new TelegramException('Failed to send request after ' . $attempts . ' attempts: ' . ($lastException ? $lastException->getMessage() : 'Unknown error'));
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

        try {
            // default global key per bot
            $key = $key ?: 'tg_rate_bot_' . md5($this->token);
            $redis = Yii::$app->redis;
            // Use INCR + EXPIRE
            $count = $redis->incr($key);
            if ($count == 1) {
                $redis->expire($key, 1);
            }
            if ($count > $this->rateLimitPerSecond) {
                if ($this->enableLogs) {
                    Yii::warning([
                        'key' => $key,
                        'count' => $count,
                        'limit' => $this->rateLimitPerSecond
                    ], 'telegram-rate-limit');
                }
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            // If Redis fails, allow request but log error
            if ($this->enableLogs) {
                Yii::error([
                    'error' => $e->getMessage(),
                    'key' => $key
                ], 'telegram-rate-limit-error');
            }
            return true; // Allow request if rate limiting fails
        }
    }

    /** PUBLIC API METHODS **/

    public function sendMessage($chatId, $text, $options = [])
    {
        if (!$this->checkRateLimit()) {
            // optionally enqueue or throw
            throw new TelegramException('Rate limit exceeded');
        }

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        // Parse mode
        if (isset($options['parse_mode'])) {
            $data['parse_mode'] = $options['parse_mode'];
        } else {
            $data['parse_mode'] = 'MarkdownV2';
        }

        // Disable web page preview
        if (isset($options['disable_web_page_preview'])) {
            $data['disable_web_page_preview'] = $options['disable_web_page_preview'];
        }

        // Reply to message
        if (isset($options['reply_to_message_id'])) {
            $data['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        // Keyboard
        if (isset($options['keyboard'])) {
            $data['reply_markup'] = json_encode($options['keyboard']);
        }

        // Extra options
        if (isset($options['extra']) && is_array($options['extra'])) {
            $data = array_merge($data, $options['extra']);
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

    public function setWebhook($url, $options = [])
    {
        $data = ['url' => $url];
        if (isset($options['allowed_updates'])) {
            $data['allowed_updates'] = $options['allowed_updates'];
        }
        if (isset($options['drop_pending_updates'])) {
            $data['drop_pending_updates'] = $options['drop_pending_updates'];
        }
        return $this->sendRequest('setWebhook', $data);
    }

    public function deleteWebhook($dropPendingUpdates = false)
    {
        $data = [];
        if ($dropPendingUpdates) {
            $data['drop_pending_updates'] = true;
        }
        return $this->sendRequest('deleteWebhook', $data);
    }

    public function getWebhookInfo()
    {
        return $this->sendRequest('getWebhookInfo');
    }

    public function deleteMessage($chatId, $messageId)
    {
        return $this->sendRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function forwardMessage($chatId, $fromChatId, $messageId, $options = [])
    {
        $data = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ];
        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }
        return $this->sendRequest('forwardMessage', $data);
    }

    public function sendDocument($chatId, $document, $caption = null, $keyboard = null, $options = [])
    {
        $data = [
            'chat_id' => $chatId,
            'document' => $document,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? 'MarkdownV2';
        }
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }
        return $this->sendRequest('sendDocument', $data);
    }

    public function sendVideo($chatId, $video, $caption = null, $keyboard = null, $options = [])
    {
        $data = [
            'chat_id' => $chatId,
            'video' => $video,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? 'MarkdownV2';
        }
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        if (isset($options['duration'])) {
            $data['duration'] = $options['duration'];
        }
        if (isset($options['width'])) {
            $data['width'] = $options['width'];
        }
        if (isset($options['height'])) {
            $data['height'] = $options['height'];
        }
        return $this->sendRequest('sendVideo', $data);
    }

    public function sendLocation($chatId, $latitude, $longitude, $keyboard = null, $options = [])
    {
        $data = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        if (isset($options['live_period'])) {
            $data['live_period'] = $options['live_period'];
        }
        return $this->sendRequest('sendLocation', $data);
    }

    public function editMessageReplyMarkup($chatId, $messageId, $keyboard = null)
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        return $this->sendRequest('editMessageReplyMarkup', $data);
    }

    public function getMe()
    {
        return $this->sendRequest('getMe');
    }

    public function getUpdates($offset = null, $limit = null, $timeout = null, $allowedUpdates = null)
    {
        $data = [];
        if ($offset !== null) {
            $data['offset'] = $offset;
        }
        if ($limit !== null) {
            $data['limit'] = $limit;
        }
        if ($timeout !== null) {
            $data['timeout'] = $timeout;
        }
        if ($allowedUpdates !== null) {
            $data['allowed_updates'] = $allowedUpdates;
        }
        return $this->sendRequest('getUpdates', $data);
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
