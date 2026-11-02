<?php

namespace app\common\server\storage\engine;

use OSS\Core\OssException;
use OSS\OssClient;

/**
 * 阿里云 OSS 存储引擎
 */
class Aliyun extends Server
{
    /**
     * 上传文件
     */
    public function upload(string $saveDir): bool
    {
        $object = $this->buildObjectKey($saveDir);

        try {
            $client = $this->client();
            $client->uploadFile($this->config['bucket'], $object, $this->getRealPath());
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 抓取远程资源并上传
     */
    public function fetch(string $url, string $key): bool
    {
        try {
            $client = $this->client();
            $content = @file_get_contents($url);
            if ($content === false) {
                $this->error = '抓取远程资源失败';
                return false;
            }
            $client->putObject($this->config['bucket'], ltrim($key, '/'), $content);
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 删除对象
     */
    public function delete(string $fileName): bool
    {
        try {
            $this->client()->deleteObject($this->config['bucket'], ltrim($fileName, '/'));
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 获取保存文件名
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * 设置对象 ACL
     */
    public function setAcl(string $fileName, string $acl): bool
    {
        try {
            $this->client()->putObjectAcl($this->config['bucket'], ltrim($fileName, '/'), $acl);
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 获取带签名的 URL
     */
    public function getSignUrl(string $fileName): ?string
    {
        try {
            return $this->client()->signUrl(
                $this->config['bucket'],
                ltrim($fileName, '/'),
                (int)($this->config['sign_ttl'] ?? 3600)
            );
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }

    /**
     * 构建对象 key
     */
    protected function buildObjectKey(string $saveDir): string
    {
        $saveDir = trim($saveDir, '/');
        return $saveDir ? $saveDir . '/' . $this->getFileName() : $this->getFileName();
    }

    /**
     * 构建 OSS 客户端
     */
    protected function client(): OssClient
    {
        return new OssClient(
            $this->config['access_key_id'] ?? '',
            $this->config['access_key_secret'] ?? '',
            $this->config['endpoint'] ?? ''
        );
    }
}
