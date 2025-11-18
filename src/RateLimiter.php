<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * Rate Limiter
 * 
 * Implements rate limiting using Redis (token bucket algorithm)
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class RateLimiter
{
    /** @var int Maximum requests per window */
    protected $limit;
    
    /** @var int Time window in seconds */
    protected $window;

    /**
     * Constructor
     * 
     * @param int $limit Maximum requests per window
     * @param int $window Time window in seconds
     */
    public function __construct(int $limit = Constants::DEFAULT_USER_RATE_LIMIT, int $window = Constants::DEFAULT_USER_RATE_WINDOW)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Check if request is allowed for user
     * 
     * @param int|string $userId User ID
     * @return bool True if allowed, false if rate limit exceeded
     */
    public function allow($userId): bool
    {
        // Check if Redis is available
        if (!Yii::$app->has('redis')) {
            return true; // Skip rate limiting if Redis not available
        }

        try {
            $redis = Yii::$app->redis;
            $key = Constants::REDIS_KEY_RATE_USER_PREFIX . $userId;
            $count = $redis->incr($key);
            
            if ($count == 1) {
                $redis->expire($key, $this->window);
            }
            
            return $count <= $this->limit;
        } catch (\Throwable $e) {
            // If Redis fails, allow request
            Yii::error([
                'error' => $e->getMessage(),
                'userId' => $userId
            ], Constants::LOG_CATEGORY_RATE_LIMIT_ERROR);
            return true;
        }
    }
}
