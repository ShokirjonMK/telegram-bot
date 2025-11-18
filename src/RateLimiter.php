<?php

namespace shokirjonmk\telegram;

use Yii;

class RateLimiter
{
    protected $limit = 5; // per window
    protected $window = 1; // seconds

    public function __construct($limit = 5, $window = 1)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    public function allow($userId)
    {
        // Check if Redis is available
        if (!Yii::$app->has('redis')) {
            return true; // Skip rate limiting if Redis not available
        }

        $redis = Yii::$app->redis;
        $key = "tg_rate_user_{$userId}";
        $count = $redis->incr($key);
        if ($count == 1) {
            $redis->expire($key, $this->window);
        }
        return $count <= $this->limit;
    }
}

