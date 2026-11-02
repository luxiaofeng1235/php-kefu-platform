<?php

namespace app\common\server\storage\engine;

use RuntimeException;

/**
 * 本地存储引擎
 */
class Local extends Server
{
    /**
     * 执行上传
     */
    public function upload(string $saveDir): bool
    {
        $saveDir = trim($saveDir, '/');
        $basePath = $this->config['root'] ?? public_path();
        $targetDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($saveDir ? str_replace('/', DIRECTORY_SEPARATOR, $saveDir) : '');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $this->error = "无法创建目录：{$targetDir}";
            return false;
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $this->getFileName();

        try {
            if ($this->isInternal) {
                if (!copy($this->getRealPath(), $targetPath)) {
                    throw new RuntimeException('复制文件失败');
                }
            } else {
                $this->file->move($targetDir, $this->getFileName());
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * 抓取远程资源
     */
    public function fetch(string $url, string $key): bool
    {
        $basePath = $this->config['root'] ?? public_path();
        $targetPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($key, '/');

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $this->error = "无法创建目录：{$targetDir}";
            return false;
        }

        $content = @file_get_contents($url);
        if ($content === false) {
            $this->error = '抓取远程资源失败';
            return false;
        }

        if (file_put_contents($targetPath, $content) === false) {
            $this->error = '写入文件失败';
            return false;
        }

        return true;
    }

    /**
     * 删除文件
     */
    public function delete(string $fileName): bool
    {
        $basePath = $this->config['root'] ?? public_path();
        $targetPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($fileName, '/');

        if (!is_file($targetPath)) {
            return true;
        }

        return unlink($targetPath);
    }

    /**
     * 获取保存文件名
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }
}
