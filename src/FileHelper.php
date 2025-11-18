<?php

namespace shokirjonmk\telegram;

use Yii;

class FileHelper
{
    /**
     * Download file by file_id, save to $savePath and return local path
     * $component is a TelegramComponent (or manager->get(...))
     */
    public static function downloadFile(TelegramComponent $component, $fileId, $saveDir = '@runtime/tg_files')
    {
        if (empty($fileId)) {
            throw new TelegramException('File ID is required');
        }

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
