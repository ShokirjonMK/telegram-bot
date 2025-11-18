# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2024-12-XX

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
- Local polling support (like Python telegram bot)
- Constants class for all magic strings
- Comprehensive PHPDoc documentation
- API documentation (docs/API.md)
- Type hints and return types throughout
- BaseCommand class for custom commands
- Example commands (StartCommand)

### Fixed
- Improved error handling in all components
- Better validation and error messages
- Fixed rate limiting with proper error handling
- Enhanced CommandRouter with error handling
- Improved FileHelper with better error messages and validation
- Better retry logic that doesn't retry Telegram API errors
- Fixed namespace consistency
- Fixed code organization and structure

### Improved
- Enhanced sendMessage options handling
- Better logging with stack traces and categories
- Improved TelegramManager with validation and helper methods
- More robust SendMessageJob with validation
- Better code organization (methods grouped by functionality)
- Comprehensive documentation
- Type safety improvements
- Cleaner code structure
- Removed code duplication

### Refactored
- Complete code refactoring with PHPDoc comments
- Added type hints and return types
- Created Constants class for magic strings
- Improved method organization
- Better error handling patterns
- Enhanced code quality and maintainability

### Documentation
- Added comprehensive API documentation
- Added refactoring notes
- Improved README with examples
- Added code examples in PHPDoc comments

