<?php

namespace shokirjonmk\telegram;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job for sending messages via Telegram in background
 */
class SendMessageJob extends BaseObject implements JobInterface
{
    public $payload; // array with botToken, chatId, text, options

    public function execute($queue)
    {
        try {
            if (empty($this->payload['botToken'])) {
                throw new TelegramException('Bot token is required');
            }
            if (empty($this->payload['chatId'])) {
                throw new TelegramException('Chat ID is required');
            }
            if (empty($this->payload['text'])) {
                throw new TelegramException('Message text is required');
            }

            // we create a TelegramComponent for the given token (ad-hoc)
            $component = new TelegramComponent([
                'token' => $this->payload['botToken'],
                'enableLogs' => true,
            ]);

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
            ], 'telegram-error');
            // Re-throw to let queue handle retries
            throw $e;
        }
    }
}
