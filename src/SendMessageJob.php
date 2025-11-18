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
            // we create a TelegramComponent for the given token (ad-hoc)
            $component = new TelegramComponent([
                'token' => $this->payload['botToken'],
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
                'payload' => $this->payload
            ], 'telegram-error');
            // don't throw — job failed; Yii Queue will manage retries if configured
            throw $e;
        }
    }
}
