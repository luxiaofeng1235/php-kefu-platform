<?php

namespace app\common\server\storage\engine;

use RuntimeException;
use Webman\Http\UploadFile;

/**
 * 存储引擎抽象基类
 */
abstract class Server
{
    /**
     * 当前上传文件
     */
    protected ?UploadFile $file = null;

    /**
     * 文件元信息
     */
    protected array $fileInfo = [];

    /**
     * 存储文件名
     */
    protected string $fileName = '';

    /**
     * 最近一次错误
     */
    protected ?string $error = null;

    /**
     * 是否为内部文件上传
     */
    protected bool $isInternal = false;

    /**
     * 引擎配置
     */
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 设置上传文件
     * @param mixed $source
     */
    public function setUploadFile(mixed $source): void
    {
        $uploaded = $this->normalizeUploadedFile($source);
        if (method_exists($uploaded, 'isValid') && !$uploaded->isValid()) {
            throw new RuntimeException('上传文件无效或已损坏');
        }

        $this->file = $uploaded;
        $this->fileInfo = [
            'ext' => strtolower($this->detectExtension($uploaded)),
            'size' => (int)$this->detectSize($uploaded),
            'mime' => $this->detectMime($uploaded),
            'name' => $this->detectName($uploaded),
            'realPath' => $uploaded->getPathname(),
        ];
        $this->fileName = $this->buildSaveName();
        $this->isInternal = false;
    }

    /**
     * 从真实路径设置文件
     */
    public function setUploadFileByReal(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("文件不存在：{$filePath}");
        }

        $this->file = null;
        $this->fileInfo = [
            'ext' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size' => filesize($filePath),
            'mime' => mime_content_type($filePath) ?: 'application/octet-stream',
            'name' => basename($filePath),
            'realPath' => $filePath,
        ];
        $this->fileName = $this->buildSaveName();
        $this->isInternal = true;
    }

    abstract public function upload(string $saveDir): bool;

    abstract public function fetch(string $url, string $key): bool;

    abstract public function delete(string $fileName): bool;

    abstract public function getFileName(): string;

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getFileInfo(): array
    {
        return $this->fileInfo;
    }

    public function setAcl(string $fileName, string $acl): bool
    {
        return true;
    }

    public function getSignUrl(string $fileName): ?string
    {
        return null;
    }

    protected function getRealPath(): string
    {
        return $this->fileInfo['realPath'] ?? '';
    }

    /**
     * 标准化上传文件
     */
    protected function normalizeUploadedFile(mixed $source): UploadFile
    {
        if ($source instanceof UploadFile) {
            return $source;
        }

        if (is_array($source)) {
            foreach ($source as $item) {
                if ($item instanceof UploadFile) {
                    return $item;
                }
            }
        }

        if (is_string($source)) {
            $request = request();
            if ($request) {
                $files = $request->file($source);
                if ($files instanceof UploadFile) {
                    return $files;
                }
                if (is_array($files)) {
                    foreach ($files as $item) {
                        if ($item instanceof UploadFile) {
                            return $item;
                        }
                    }
                }
            }
        }

        throw new RuntimeException('无法识别上传文件来源');
    }

    /**
     * 生成保存文件名
     */
    protected function buildSaveName(): string
    {
        $original = $this->fileInfo['name'] ?? uniqid('file_', true);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $ext = $ext ? '.' . strtolower($ext) : '';
        return date('YmdHis') . substr(sha1($original . microtime(true)), 0, 8) . $ext;
    }

    protected function detectName(UploadFile $uploaded): string
    {
        if (method_exists($uploaded, 'getUploadName')) {
            return $uploaded->getUploadName() ?? basename($uploaded->getPathname());
        }
        return basename($uploaded->getPathname());
    }

    protected function detectMime(UploadFile $uploaded): string
    {
        if (method_exists($uploaded, 'getUploadMimeType')) {
            $mime = $uploaded->getUploadMimeType();
            if ($mime) {
                return $mime;
            }
        }
        return mime_content_type($uploaded->getPathname()) ?: 'application/octet-stream';
    }

    protected function detectExtension(UploadFile $uploaded): string
    {
        if (method_exists($uploaded, 'getUploadExtension')) {
            return $uploaded->getUploadExtension() ?: pathinfo($uploaded->getPathname(), PATHINFO_EXTENSION);
        }
        return pathinfo($uploaded->getPathname(), PATHINFO_EXTENSION);
    }

    protected function detectSize(UploadFile $uploaded): int
    {
        $size = $uploaded->getSize();
        return $size !== false ? (int)$size : (int)filesize($uploaded->getPathname());
    }
}
