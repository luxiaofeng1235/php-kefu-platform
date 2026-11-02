<?php
/*
 *
 * 文件上传功能，主要处理阿里云OSS上传以及视频的功能。
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file FileController.php
 */

namespace app\api\controller;

use app\common\server\JsonServer;
use app\common\server\UploadServer;
use support\Request;
use support\Response;

class FileController
{
    /**
     * @note 表单上传图片
     * @param Request $request 上传请求
     * @date 2025/03/09
     * @return \support\Response
     */
    public function formImage(Request $request)
    {
        return UploadServer::handleUpload($request, 'image', 'uploads/image/' . date('Ymd'));
    }

    /**
     * @note 表单上传视频
     * @param Request $request 上传请求
     * @date 2025/03/09
     * @return \support\Response
     */
    public function formVideo(Request $request)
    {
        return UploadServer::handleUpload($request, 'video', 'uploads/video/' . date('Ymd'));
    }

    /**
     * @note 表单上传图片或视频
     * @param Request $request 上传请求
     * @date 2025/03/09
     * @return \support\Response
     */
    public function formFile(Request $request)
    {
        return UploadServer::handleUpload($request, 'auto', 'uploads/files');
    }

    /**
     * @note 上传文件-普通上传
     * @param Request $request 上传请求
     * @date 2025/03/09
     * @return \support\Response
     */
    public function formNormal(Request $request): Response
    {
        $file = current($request->file());
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getUploadExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'webm', 'mkv', 'flv'])) {
                return JsonServer::error('仅支持 mp4 mov avi webm mkv flv 格式');
            }
            //处理路径
            $relative_path = 'upload/video/' . date('Ym');
            $real_path = base_path() . "/plugin/admin/public/$relative_path";
            if (!is_dir($real_path)) {
                mkdir($real_path, 0777, true);
            }

            $name = bin2hex(pack('Nn', time(), random_int(1, 65535)));
            $ext = $file->getUploadExtension(); // 再次获取，确保小写后使用

            // 保存视频文件（直接移动）
            $path = "$real_path/$name.$ext";
            if ($file->move($path)) {
                $data['url'] = "/app/admin/$relative_path/$name.$ext";
                return JsonServer::success('上传成功', $data);
            } else {
                return JsonServer::error('文件移动失败');
            }
        }
        return JsonServer::error('file not found');
    }
}
