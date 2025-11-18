<?php

namespace shokirjonmk\telegram;

use Yii;

/**
 * File Helper
 * 
 * Helper class for downloading files from Telegram
 * 
 * @package shokirjonmk\telegram
 * @author ShokirjonMK
 */
class FileHelper
{
    /**
     * Download file from Telegram
     * 
     * Downloads file by file_id, saves to specified directory and returns local path.
     * 
     * @param TelegramComponent $component Telegram component instance
     * @param string $fileId File ID from Telegram
     * @param string $saveDir Directory to save file (Yii alias supported)
     * @return string Local file path
     * @throws TelegramException If download fails
     * 
     * @example
     * $localPath = FileHelper::downloadFile($component, $fileId, '@runtime/tg_files');
     */
    public static function downloadFile(TelegramComponent $component, string $fileId, string $saveDir = '@runtime/tg_files'): string
    {
        if (empty($fileId)) {
            throw new TelegramException('File ID is required');
        }

        // Get file info from Telegram
        $info = $component->getFile($fileId);

        if (empty($info['ok']) || !$info['ok']) {
            $error = $info['description'] ?? 'Unknown error';
            throw new TelegramException('Cannot get file info: ' . $error);
        }

        if (empty($info['result']['file_path'])) {
            throw new TelegramException('File path not found in response');
        }

        $filePath = $info['result']['file_path'];
        $url = rtrim($component->apiUrl, '/') . '/' . $component->token . '/file/' . $filePath;

        // Prepare save directory
        $saveDir = Yii::getAlias($saveDir);
        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true)) {
                throw new TelegramException('Failed to create directory: ' . $saveDir);
            }
        }

        if (!is_writable($saveDir)) {
            throw new TelegramException('Directory is not writable: ' . $saveDir);
        }

        $local = $saveDir . DIRECTORY_SEPARATOR . basename($filePath);
        
        // Download file
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => true,
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            throw new TelegramException('Failed to download file from ' . $url);
        }

        if (file_put_contents($local, $content) === false) {
            throw new TelegramException('Failed to save file to ' . $local);
        }

        return $local;
    }
}
