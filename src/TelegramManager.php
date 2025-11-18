<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * TelegramManager - stores multiple bot configs and returns TelegramComponent instances
 */
class TelegramManager extends \yii\base\Component
{
    public $bots = []; // ['student'=>['token'=>'','apiUrl'=>''], ...]
    public $defaultBot = 'student';
    public $enableLogs = true;

    private $_instances = [];

    /**
     * get bot component by key
     */
    public function get($name = null)
    {
        $name = $name ?: $this->defaultBot;
        if (!isset($this->_instances[$name])) {
            if (!isset($this->bots[$name])) {
                throw new \InvalidArgumentException("Bot config '{$name}' not found");
            }
            $cfg = $this->bots[$name];
            $comp = new TelegramComponent($cfg + [
                'enableLogs' => $this->enableLogs,
            ]);
            $this->_instances[$name] = $comp;
        }
        return $this->_instances[$name];
    }
}

