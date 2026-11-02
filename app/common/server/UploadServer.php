<?php
/*
 *
 * 文件上传服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file UploadServer.php
 */
namespace app\common\server;

use app\common\enum\FileEnum;
use app\model\File as FileModel;
use support\Request;
use Throwable;

class UploadServer
{
    protected const DOMAIN_MAP = [
        'image' => 'https://static.jsss999.com',
        'video' => 'https://static-nine-world.oss-cn-shanghai.aliyuncs.com',
    ];

    /**
     * @note 处理文件上传
     * @param Request $request 上传请求
     * @param string $category 预设分类
     * @param string $defaultDir 默认保存目录
     * @date 2025/03/09
     * @throws Throwable
     * @return \support\Response
     */
    public static function handleUpload(Request $request, string $category, string $defaultDir)
    {
        $file = current($request->file()) ?: $request->file('file');
        if (!$file || !$file->isValid()) {
            return JsonServer::success('未检测到上传文件');
        }

        $saveDir = trim((string)$request->post('save_dir', $defaultDir), '/');
        if ($saveDir === '') {
            $saveDir = 'uploads/files';
        }

        $type = (int)$request->post('type', 1);
        $storage = $type === 1 ? 'aliyun' : 'local';

        $resolvedCategory = $category === 'auto' ? self::resolveCategory($file) : $category;
        if ($category === 'auto' && !in_array($resolvedCategory, ['image', 'video'], true)) {
            return JsonServer::success('仅支持图片或视频上传');
        }

        try {
            $result = OssServer::upload($file, $saveDir, $storage);
            if ($result['storage'] === 'aliyun' && !OssServer::setAcl($result['uri'], 'public-read', $result['storage'])) {
                return JsonServer::success('上传失败: 无法设置文件访问权限');
            }
        } catch (Throwable $e) {
            return JsonServer::success('上传失败: ' . $e->getMessage());
        }

        $typeMap = [
            'image' => FileEnum::IMAGE_TYPE,
            'video' => FileEnum::VIDEO_TYPE,
            'file' => FileEnum::FILE_TYPE,
            'audio' => FileEnum::OTHER_TYPE,
        ];

        $recordType = $typeMap[$resolvedCategory] ?? FileEnum::OTHER_TYPE;
        $cid = (int)$request->post('cid', 0);
        $shopId = (int)$request->post('shop_id', 0);
        $userId = (int)$request->post('user_id', 0);

        try {
            FileModel::record([
                'name' => $result['info']['name'] ?? $file->getUploadName(),
                'cid' => $cid,
                'type' => $recordType,
                'uri' => $result['uri'],
                'shop_id' => $shopId,
                'user_id' => $userId,
                'create_time' => time(),
            ]);
        } catch (Throwable $e) {
            // 数据落库失败不影响上传主流程
        }

        $accessUrl = self::buildAccessUrl($result['uri'], $result['url'], $resolvedCategory, $result['storage']);
        //保持和后台一直
        return JsonServer::error('上传成功', [
            'storage' => $result['storage'],
            'category' => $resolvedCategory,
            'uri' => $result['uri'],
            'url' => $accessUrl,
            'name' => $result['info']['name'] ?? $file->getUploadName(),
            'mime' => $result['info']['mime'] ?? $file->getUploadMimeType(),
            'size' => $result['info']['size'] ?? $file->getSize(),
        ]);
    }

    /**
     * @note 根据文件信息判定类别
     * @param mixed $file 上传文件实例
     * @date 2025/03/09
     * @return string
     */
    protected static function resolveCategory(mixed $file): string
    {
        $mime = strtolower((string)$file->getUploadMimeType());
        $extension = strtolower((string)$file->getUploadExtension());

        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'], true)) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv', 'flv', 'm4v'], true)) {
            return 'video';
        }

        return 'file';
    }

    /**
     * @note 构建访问域名
     * @param string $uri OSS 对象路径
     * @param string $defaultUrl 默认访问地址
     * @param string $category 上传类型
     * @param string $storage 存储引擎
     * @date 2025/03/09
     * @return string
     */
    protected static function buildAccessUrl(string $uri, string $defaultUrl, string $category, string $storage): string
    {
        if ($storage !== 'aliyun') {
            return $defaultUrl;
        }

        $domain = self::DOMAIN_MAP[$category] ?? '';
        if ($domain === '') {
            return $defaultUrl;
        }

        $uri = ltrim($uri, '/');
        return rtrim($domain, '/') . '/' . $uri;
    }
}
