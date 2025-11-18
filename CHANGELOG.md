# Changelog

## [1.0.0] - 2024

### Added
- Initial release of Telegram Bot Extension for Yii2
- Multi-bot support via TelegramManager
- Queue integration for background message sending
- Command Router for Laravel-like command handling
- Rate Limiting (per-user and per-bot) with Redis support
- Retry mechanism with exponential backoff
- File Helper for downloading Telegram files
- Update Handler Middleware
- Comprehensive error handling and logging
- Support for multiple Telegram API methods:
  - sendMessage, sendPhoto, sendDocument, sendVideo, sendLocation
  - editMessageText, editMessageReplyMarkup
  - deleteMessage, forwardMessage
  - answerCallbackQuery, sendChatAction
  - setWebhook, deleteWebhook, getWebhookInfo
  - getUpdates, getMe, getFile

### Fixed
- Improved error handling in all components
- Better validation and error messages
- Fixed rate limiting with proper error handling
- Enhanced CommandRouter with error handling
- Improved FileHelper with better error messages and validation
- Better retry logic that doesn't retry Telegram API errors

### Improved
- Enhanced sendMessage options handling
- Better logging with stack traces
- Improved TelegramManager with validation and helper methods
- More robust SendMessageJob with validation

