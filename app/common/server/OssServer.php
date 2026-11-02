<?php

namespace app\common\server;

use app\common\server\storage\Driver;
use RuntimeException;

/**
 * OSS 工具类
 */
class OssServer
{
    /**
     * 上传文件
     * @param mixed $file 上传源（UploadedFile/字段名/数组）
     * @param string $saveDir 保存目录
     * @param string|null $storage 使用的存储引擎
     * @return array 包含 uri 与文件信息
     */
    public static function upload(mixed $file, string $saveDir, ?string $storage = null): array
    {
        $driver = self::driver($storage);
        $driver->setUploadFile($file);

        if (!$driver->upload($saveDir)) {
            throw new RuntimeException($driver->getError() ?: '文件上传失败');
        }

        $uri = $driver->getFileUri();
        $storageName = $driver->storage();

        return [
            'storage' => $storageName,
            'uri' => $uri,
            'url' => self::url($uri, $storageName),
            'info' => $driver->getFileInfo(),
        ];
    }

    /**
     * 获取存储驱动
     */
    public static function driver(?string $storage = null): Driver
    {
        $config = config('storage');
        if (!$config) {
            throw new RuntimeException('缺少存储配置 storage.php');
        }

        return new Driver($config, $storage);
    }

    /**
     * 设置对象 ACL
     */
    public static function setAcl(string $uri, string $acl = 'public-read', ?string $storage = null): bool
    {
        $driver = self::driver($storage);
        return $driver->setAcl($uri, $acl);
    }

    /**
     * 根据存储类型生成可访问 URL
     */
    public static function url(string $uri, ?string $storage = null): string
    {
        $uri = ltrim($uri, '/');
        $config = config('storage');
        if (!$config) {
            return '/' . $uri;
        }
        $storageName = strtolower($storage ?? ($config['default'] ?? 'local'));

        if ($storageName === 'aliyun') {
            $engine = $config['engine']['aliyun'] ?? [];
            $domain = $engine['domain'] ?? '';
            if ($domain) {
                $base = rtrim($domain, '/');
            } else {
                $endpoint = $engine['endpoint'] ?? '';
                if (!$endpoint) {
                    return '/' . $uri;
                }
                $endpoint = ltrim($endpoint, '/');
                if (!str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
                    $endpoint = 'https://' . $endpoint;
                }
                $bucket = $engine['bucket'] ?? '';
                $base = rtrim($endpoint, '/');
                if ($bucket) {
                    $parsed = parse_url($base);
                    if ($parsed && isset($parsed['host'])) {
                        $scheme = $parsed['scheme'] ?? 'https';
                        $base = $scheme . '://' . $bucket . '.' . $parsed['host'];
                        if (isset($parsed['port'])) {
                            $base .= ':' . $parsed['port'];
                        }
                        $base .= $parsed['path'] ?? '';
                    } else {
                        $base .= '/' . $bucket;
                    }
                }
            }
            return rtrim($base, '/') . '/' . $uri;
        }

        $engine = $config['engine']['local'] ?? [];
        $base = $engine['url'] ?? '';
        if ($base) {
            return rtrim($base, '/') . '/' . $uri;
        }
        return '/' . $uri;
    }
}
