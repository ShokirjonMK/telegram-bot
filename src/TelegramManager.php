<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * Telegram Manager - Multi-bot Support
 * 
 * Manages multiple bot configurations and provides access to bot instances.
 * Supports lazy loading and singleton pattern for bot instances.
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class TelegramManager extends \yii\base\Component
{
    /** @var array Bot configurations ['botName' => ['token' => '...', 'apiUrl' => '...'], ...] */
    public $bots = [];
    
    /** @var string Default bot name */
    public $defaultBot = 'student';
    
    /** @var bool Enable logging for all bots */
    public $enableLogs = true;
    
    /** @var array Cached bot instances */
    private $_instances = [];

    /**
     * Get bot component by name
     * 
     * @param string|null $name Bot name (uses default if null)
     * @return TelegramComponent Bot component instance
     * @throws \InvalidArgumentException If bot not found or token missing
     */
    public function get(?string $name = null): TelegramComponent
    {
        $name = $name ?: $this->defaultBot;
        
        if (!isset($this->_instances[$name])) {
            if (!isset($this->bots[$name])) {
                $available = implode(', ', array_keys($this->bots));
                throw new \InvalidArgumentException(
                    "Bot config '{$name}' not found. Available bots: {$available}"
                );
            }
            
            $cfg = $this->bots[$name];
            
            if (empty($cfg['token'])) {
                throw new \InvalidArgumentException("Bot token is required for bot '{$name}'");
            }
            
            $comp = new TelegramComponent($cfg + [
                'enableLogs' => $this->enableLogs,
            ]);
            $this->_instances[$name] = $comp;
        }
        
        return $this->_instances[$name];
    }

    /**
     * Get all registered bot names
     * 
     * @return array List of bot names
     */
    public function getBotNames(): array
    {
        return array_keys($this->bots);
    }

    /**
     * Check if bot exists
     * 
     * @param string $name Bot name
     * @return bool True if bot exists
     */
    public function has(string $name): bool
    {
        return isset($this->bots[$name]);
    }
}
