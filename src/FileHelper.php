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
        $info = $component->getFile($fileId);

        if (empty($info['ok']) || !$info['ok']) {
            throw new TelegramException('Cannot get file info');
        }
        $filePath = $info['result']['file_path'];
        $url = rtrim($component->apiUrl, '/') . '/' . $component->token . '/file/' . $filePath;

        $saveDir = Yii::getAlias($saveDir);
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        $local = $saveDir . DIRECTORY_SEPARATOR . basename($filePath);
        $content = @file_get_contents($url);
        if ($content === false) {
            throw new TelegramException('Failed to download file from ' . $url);
        }
        file_put_contents($local, $content);
        return $local;
    }
}
