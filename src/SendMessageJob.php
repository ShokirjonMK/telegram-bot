<?php

namespace shokirjonmk\telegram;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Send Message Job
 * 
 * Queue job for sending Telegram messages in background
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class SendMessageJob extends BaseObject implements JobInterface
{
    /** @var array Job payload with botToken, chatId, text, options */
    public $payload;

    /**
     * Execute job
     * 
     * @param \yii\queue\Queue $queue Queue instance
     * @return void
     * @throws TelegramException
     */
    public function execute($queue): void
    {
        try {
            // Validate payload
            if (empty($this->payload['botToken'])) {
                throw new TelegramException('Bot token is required');
            }
            if (empty($this->payload['chatId'])) {
                throw new TelegramException('Chat ID is required');
            }
            if (empty($this->payload['text'])) {
                throw new TelegramException('Message text is required');
            }

            // Create Telegram component for the given token
            $component = new TelegramComponent([
                'token' => $this->payload['botToken'],
                'enableLogs' => true,
            ]);

            // Send message
            $component->sendMessage(
                $this->payload['chatId'],
                $this->payload['text'],
                $this->payload['options'] ?? []
            );
        } catch (\Throwable $e) {
            Yii::error([
                'job' => 'SendMessageJob',
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $this->payload
            ], Constants::LOG_CATEGORY_ERROR);

            // Re-throw to let queue handle retries
            throw $e;
        }
    }
}
