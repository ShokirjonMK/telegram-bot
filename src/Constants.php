<?php

namespace shokirjonmk\telegram;

/**
 * Constants for Telegram Bot Extension
 */
class Constants
{
    // Telegram API
    const API_URL = 'https://api.telegram.org/bot';
    const API_TIMEOUT = 5;

    // Parse modes
    const PARSE_MODE_MARKDOWN = 'Markdown';
    const PARSE_MODE_MARKDOWN_V2 = 'MarkdownV2';
    const PARSE_MODE_HTML = 'HTML';

    // Chat actions
    const ACTION_TYPING = 'typing';
    const ACTION_UPLOAD_PHOTO = 'upload_photo';
    const ACTION_RECORD_VIDEO = 'record_video';
    const ACTION_UPLOAD_VIDEO = 'upload_video';
    const ACTION_RECORD_VOICE = 'record_voice';
    const ACTION_UPLOAD_VOICE = 'upload_voice';
    const ACTION_UPLOAD_DOCUMENT = 'upload_document';
    const ACTION_FIND_LOCATION = 'find_location';
    const ACTION_RECORD_VIDEO_NOTE = 'record_video_note';
    const ACTION_UPLOAD_VIDEO_NOTE = 'upload_video_note';

    // Rate limiting
    const DEFAULT_RATE_LIMIT_PER_SECOND = 20;
    const DEFAULT_USER_RATE_LIMIT = 5;
    const DEFAULT_USER_RATE_WINDOW = 1; // seconds

    // Retry
    const DEFAULT_RETRY_ATTEMPTS = 3;

    // Redis keys
    const REDIS_KEY_RATE_BOT_PREFIX = 'tg_rate_bot_';
    const REDIS_KEY_RATE_USER_PREFIX = 'tg_rate_user_';

    // Log categories
    const LOG_CATEGORY_ERROR = 'telegram-error';
    const LOG_CATEGORY_COMMAND_ERROR = 'telegram-command-error';
    const LOG_CATEGORY_RATE_LIMIT = 'telegram-rate-limit';
    const LOG_CATEGORY_RATE_LIMIT_ERROR = 'telegram-rate-limit-error';
    const LOG_CATEGORY_POLLING_ERROR = 'telegram-polling-error';
}
