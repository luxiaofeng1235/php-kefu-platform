<?php
/**
 * 系统相关接口
 * Copyright (c) 2025 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 1.0
 * @file SystemController.php
 */

namespace app\api\controller;

use app\common\server\JsonServer;
use support\Request;

/**
 * 系统相关接口
 */
class SystemController
{
    /**
     * 获取静态资源版本号（自动遍历 css 和 js 目录）
     *
     * @param Request $request
     * @return \support\Response
     */
    public function assetVersion(Request $request)
    {
        try {
            $basePath = base_path() . '/public';

            // 自动遍历 CSS 和 JS 目录，生成版本号
            $versions = [
                'css' => $this->scanDirectory($basePath . '/css', '.css'),
                'js' => $this->scanDirectory($basePath . '/js', '.js'),
                'timestamp' => time(),
            ];

            return JsonServer::success('获取成功', $versions)->withCors();

        } catch (\Throwable $e) {
            return JsonServer::error('获取资源版本号失败：' . $e->getMessage());
        }
    }

    /**
     * 扫描目录并生成文件版本号映射
     *
     * @param string $directory 目录路径
     * @param string $extension 文件扩展名（如 .css, .js）
     * @return array 文件名 => 版本号的映射
     */
    private function scanDirectory(string $directory, string $extension): array
    {
        $versions = [];

        // 检查目录是否存在
        if (!is_dir($directory)) {
            return $versions;
        }

        // 递归遍历目录
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                // 获取相对路径作为 key（去掉目录前缀和扩展名）
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // 统一使用 / 分隔符
                $key = str_replace($extension, '', $relativePath); // 去掉扩展名

                // 计算文件哈希作为版本号
                $versions[$key] = $this->getFileHash($file->getPathname());
            }
        }

        return $versions;
    }

    /**
     * 计算文件哈希值
     *
     * @param string $filePath
     * @return string
     */
    private function getFileHash(string $filePath): string
    {
        //路径不存在就先给一个默认的
        if (!file_exists($filePath)) {
            return substr(md5(time()), 0, 8);
        }

        // 使用文件 MD5 的前8位作为版本号
        return substr(md5_file($filePath), 0, 8);
    }
}
