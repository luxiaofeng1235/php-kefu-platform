<?php

namespace app\common\server\storage;

use app\common\server\storage\engine\Aliyun;
use app\common\server\storage\engine\Local;
use app\common\server\storage\engine\Server;
use RuntimeException;

/**
 * 存储驱动调度器
 */
class Driver
{
    /**
     * 配置
     * @var array
     */
    protected array $config;

    /**
     * 当前引擎
     * @var Server
     */
    protected Server $engine;

    /**
     * 当前使用的存储引擎名称
     * @var string
     */
    protected string $storageName;

    /**
     * 最近一次的保存目录
     * @var string
     */
    protected string $lastSaveDir = '';

    /**
     * 最近一次上传的文件信息
     * @var array
     */
    protected array $lastFileInfo = [];

    /**
     * @param array $config
     * @param string|null $storage
     */
    public function __construct(array $config, ?string $storage = null)
    {
        $this->config = $config;
        $this->engine = $this->resolveEngine($storage);
    }

    /**
     * 设置上传文件
     */
    public function setUploadFile(mixed $source): void
    {
        $this->engine->setUploadFile($source);
        $this->lastFileInfo = $this->engine->getFileInfo();
    }

    /**
     * 指定真实路径文件
     */
    public function setUploadFileByReal(string $path): void
    {
        $this->engine->setUploadFileByReal($path);
    }

    /**
     * 执行上传
     */
    public function upload(string $saveDir): bool
    {
        $this->lastSaveDir = trim($saveDir, '/');
        return $this->engine->upload($this->lastSaveDir);
    }

    /**
     * 抓取远程资源
     */
    public function fetch(string $url, string $key): bool
    {
        return $this->engine->fetch($url, $key);
    }

    /**
     * 删除文件
     */
    public function delete(string $fileName): bool
    {
        return $this->engine->delete($fileName);
    }

    /**
     * 获取错误信息
     */
    public function getError(): ?string
    {
        return $this->engine->getError();
    }

    /**
     * 获取文件信息
     */
    public function getFileInfo(): array
    {
        return $this->lastFileInfo ?: $this->engine->getFileInfo();
    }

    /**
     * 获取本次上传的对象 key
     */
    public function getFileUri(): string
    {
        return $this->lastSaveDir
            ? $this->lastSaveDir . '/' . $this->engine->getFileName()
            : $this->engine->getFileName();
    }

    /**
     * 设置对象 ACL
     */
    public function setAcl(string $fileName, string $acl): bool
    {
        return $this->engine->setAcl($fileName, $acl);
    }

    /**
     * 生成带签名的访问地址
     */
    public function getSignUrl(string $fileName): ?string
    {
        return $this->engine->getSignUrl($fileName);
    }

    /**
     * 获取当前存储名称
     */
    public function storage(): string
    {
        return $this->storageName;
    }

    /**
     * 获取当前引擎配置
     */
    public function engineConfig(): array
    {
        return $this->config['engine'][$this->storageName] ?? [];
    }

    /**
     * 解析存储引擎
     */
    protected function resolveEngine(?string $storage): Server
    {
        $name = $storage ?? ($this->config['default'] ?? 'local');
        $name = strtolower($name);

        $engines = [
            'local' => Local::class,
            'aliyun' => Aliyun::class,
        ];

        if (!isset($engines[$name])) {
            throw new RuntimeException("不支持的存储引擎：{$name}");
        }

        $this->storageName = $name;
        $engineConfig = $this->config['engine'][$name] ?? [];

        /** @var Server $instance */
        $instance = new $engines[$name]($engineConfig);
        return $instance;
    }
}
