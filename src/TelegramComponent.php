<?php

namespace shokirjonmk\telegram;

use Yii;
use yii\httpclient\Client;

/**
 * Telegram Bot API Client Component
 * 
 * Lightweight Telegram client with:
 * - Retry mechanism (exponential backoff)
 * - Rate limiting (via Redis)
 * - Queue integration
 * - Chat actions support
 * - Comprehensive error handling
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class TelegramComponent
{
    /** @var string Bot token from @BotFather */
    public $token;

    /** @var string Telegram API base URL */
    public $apiUrl = Constants::API_URL;

    /** @var int Request timeout in seconds */
    public $timeout = Constants::API_TIMEOUT;

    /** @var bool Enable logging */
    public $enableLogs = true;

    /** @var int Rate limit per second (global per-bot) */
    public $rateLimitPerSecond = Constants::DEFAULT_RATE_LIMIT_PER_SECOND;

    /** @var string Redis key prefix for user rate limiting */
    public $rateLimitUserKeyPrefix = Constants::REDIS_KEY_RATE_USER_PREFIX;

    /** @var Client HTTP client instance */
    private $http;

    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
        $this->http = new Client(['transport' => 'yii\httpclient\CurlTransport']);
    }

    /**
     * Build Telegram API URL
     * 
     * @param string $method API method name
     * @return string Full API URL
     */
    protected function buildUrl(string $method): string
    {
        return rtrim($this->apiUrl, '/') . '/' . $this->token . '/' . $method;
    }

    /**
     * Send request to Telegram API with retry mechanism
     * 
     * @param string $method API method name
     * @param array $params Request parameters
     * @param int $attempts Number of retry attempts
     * @return array API response
     * @throws TelegramException
     */
    protected function sendRequest(string $method, array $params = [], int $attempts = Constants::DEFAULT_RETRY_ATTEMPTS): array
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
                    ], Constants::LOG_CATEGORY_ERROR);
                }

                // Exponential backoff
                if ($attempt < $attempts) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        // All attempts failed
        throw new TelegramException(
            'Failed to send request after ' . $attempts . ' attempts: ' .
                ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Check rate limit (simple token bucket per-bot or global)
     * 
     * @param string|null $key Custom Redis key (optional)
     * @return bool True if allowed, false if rate limit exceeded
     */
    protected function checkRateLimit(?string $key = null): bool
    {
        // Check if Redis is available
        if (!Yii::$app->has('redis')) {
            return true; // Skip rate limiting if Redis not available
        }

        try {
            // Default global key per bot
            $key = $key ?: Constants::REDIS_KEY_RATE_BOT_PREFIX . md5($this->token);
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
                    ], Constants::LOG_CATEGORY_RATE_LIMIT);
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
                ], Constants::LOG_CATEGORY_RATE_LIMIT_ERROR);
            }
            return true; // Allow request if rate limiting fails
        }
    }

    // ==================== MESSAGE METHODS ====================

    /**
     * Send text message
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param array $options Additional options:
     *   - parse_mode: 'MarkdownV2', 'Markdown', 'HTML' (default: 'MarkdownV2')
     *   - disable_web_page_preview: bool (default: true)
     *   - reply_to_message_id: int
     *   - keyboard: array (keyboard markup)
     *   - extra: array (additional parameters)
     *   - attempts: int (retry attempts, default: 3)
     * @return array API response
     * @throws TelegramException
     */
    public function sendMessage($chatId, string $text, array $options = []): array
    {
        if (!$this->checkRateLimit()) {
            throw new TelegramException('Rate limit exceeded');
        }

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        // Parse mode
        $data['parse_mode'] = $options['parse_mode'] ?? Constants::PARSE_MODE_MARKDOWN_V2;

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

        return $this->sendRequest('sendMessage', $data, $options['attempts'] ?? Constants::DEFAULT_RETRY_ATTEMPTS);
    }

    /**
     * Edit message text
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $messageId Message ID to edit
     * @param string $text New message text
     * @param array $options Additional options (see sendMessage)
     * @return array API response
     * @throws TelegramException
     */
    public function editMessageText($chatId, int $messageId, string $text, array $options = []): array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? Constants::PARSE_MODE_MARKDOWN_V2,
        ], $options['extra'] ?? []);

        if (isset($options['keyboard'])) {
            $data['reply_markup'] = json_encode($options['keyboard']);
        }

        return $this->sendRequest('editMessageText', $data, $options['attempts'] ?? Constants::DEFAULT_RETRY_ATTEMPTS);
    }

    /**
     * Delete message
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $messageId Message ID to delete
     * @return array API response
     * @throws TelegramException
     */
    public function deleteMessage($chatId, int $messageId): array
    {
        return $this->sendRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Forward message
     * 
     * @param int|string $chatId Target chat ID
     * @param int|string $fromChatId Source chat ID
     * @param int $messageId Message ID to forward
     * @param array $options Additional options:
     *   - disable_notification: bool
     * @return array API response
     * @throws TelegramException
     */
    public function forwardMessage($chatId, $fromChatId, int $messageId, array $options = []): array
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

    // ==================== MEDIA METHODS ====================

    /**
     * Send photo
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|resource $photo Photo file ID, URL, or file resource
     * @param string|null $caption Photo caption
     * @param array|null $keyboard Keyboard markup
     * @param array $options Additional options:
     *   - parse_mode: string
     *   - disable_notification: bool
     * @return array API response
     * @throws TelegramException
     */
    public function sendPhoto($chatId, $photo, ?string $caption = null, ?array $keyboard = null, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
        ];

        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? Constants::PARSE_MODE_MARKDOWN_V2;
        }

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }

        return $this->sendRequest('sendPhoto', $data);
    }

    /**
     * Send document
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|resource $document Document file ID, URL, or file resource
     * @param string|null $caption Document caption
     * @param array|null $keyboard Keyboard markup
     * @param array $options Additional options
     * @return array API response
     * @throws TelegramException
     */
    public function sendDocument($chatId, $document, ?string $caption = null, ?array $keyboard = null, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'document' => $document,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? Constants::PARSE_MODE_MARKDOWN_V2;
        }
        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }
        return $this->sendRequest('sendDocument', $data);
    }

    /**
     * Send video
     * 
     * @param int|string $chatId Chat ID or username
     * @param string|resource $video Video file ID, URL, or file resource
     * @param string|null $caption Video caption
     * @param array|null $keyboard Keyboard markup
     * @param array $options Additional options:
     *   - duration: int (seconds)
     *   - width: int
     *   - height: int
     *   - parse_mode: string
     * @return array API response
     * @throws TelegramException
     */
    public function sendVideo($chatId, $video, ?string $caption = null, ?array $keyboard = null, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'video' => $video,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? Constants::PARSE_MODE_MARKDOWN_V2;
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

    /**
     * Send location
     * 
     * @param int|string $chatId Chat ID or username
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param array|null $keyboard Keyboard markup
     * @param array $options Additional options:
     *   - live_period: int (seconds)
     * @return array API response
     * @throws TelegramException
     */
    public function sendLocation($chatId, float $latitude, float $longitude, ?array $keyboard = null, array $options = []): array
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

    // ==================== CALLBACK METHODS ====================

    /**
     * Answer callback query
     * 
     * @param string $callbackQueryId Callback query ID
     * @param string|null $text Text to show
     * @param bool $showAlert Show as alert (default: false)
     * @return array API response
     * @throws TelegramException
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];
        if ($text !== null) {
            $data['text'] = $text;
        }
        return $this->sendRequest('answerCallbackQuery', $data);
    }

    /**
     * Edit message reply markup (keyboard)
     * 
     * @param int|string $chatId Chat ID or username
     * @param int $messageId Message ID
     * @param array|null $keyboard New keyboard markup
     * @return array API response
     * @throws TelegramException
     */
    public function editMessageReplyMarkup($chatId, int $messageId, ?array $keyboard = null): array
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

    // ==================== CHAT ACTION METHODS ====================

    /**
     * Send chat action (typing, uploading, etc.)
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $action Action type (see Constants::ACTION_*)
     * @return array API response
     * @throws TelegramException
     */
    public function sendChatAction($chatId, string $action = Constants::ACTION_TYPING): array
    {
        return $this->sendRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    // ==================== FILE METHODS ====================

    /**
     * Get file information
     * 
     * @param string $fileId File ID
     * @return array API response with file info
     * @throws TelegramException
     */
    public function getFile(string $fileId): array
    {
        return $this->sendRequest('getFile', ['file_id' => $fileId]);
    }

    // ==================== WEBHOOK METHODS ====================

    /**
     * Set webhook
     * 
     * @param string $url Webhook URL
     * @param array $options Additional options:
     *   - allowed_updates: array
     *   - drop_pending_updates: bool
     * @return array API response
     * @throws TelegramException
     */
    public function setWebhook(string $url, array $options = []): array
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

    /**
     * Delete webhook
     * 
     * @param bool $dropPendingUpdates Drop pending updates
     * @return array API response
     * @throws TelegramException
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        $data = [];
        if ($dropPendingUpdates) {
            $data['drop_pending_updates'] = true;
        }
        return $this->sendRequest('deleteWebhook', $data);
    }

    /**
     * Get webhook information
     * 
     * @return array API response with webhook info
     * @throws TelegramException
     */
    public function getWebhookInfo(): array
    {
        return $this->sendRequest('getWebhookInfo');
    }

    // ==================== BOT METHODS ====================

    /**
     * Get bot information
     * 
     * @return array API response with bot info
     * @throws TelegramException
     */
    public function getMe(): array
    {
        return $this->sendRequest('getMe');
    }

    /**
     * Get updates (for polling)
     * 
     * @param int|null $offset Offset for pagination
     * @param int|null $limit Maximum number of updates
     * @param int|null $timeout Timeout in seconds
     * @param array|null $allowedUpdates Allowed update types
     * @return array API response with updates
     * @throws TelegramException
     */
    public function getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null, ?array $allowedUpdates = null): array
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

    // ==================== QUEUE METHODS ====================

    /**
     * Enqueue message for background sending
     * 
     * @param int|string $chatId Chat ID or username
     * @param string $text Message text
     * @param array $options Message options (see sendMessage)
     * @return bool True on success
     * @throws TelegramException
     */
    public function enqueueSendMessage($chatId, string $text, array $options = []): bool
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

    // ==================== UTILITY METHODS ====================

    /**
     * Escape text for MarkdownV2
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escapeMarkdownV2(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!])/', '\\\\$1', $text);
    }

    /**
     * Alias for escapeMarkdownV2 (backward compatibility)
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escape(string $text): string
    {
        return self::escapeMarkdownV2($text);
    }
}
