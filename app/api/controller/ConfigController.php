<?php

namespace app\api\controller;

use app\api\service\SystemService;
use app\common\server\JsonServer;
use support\Request;
use support\Response;

class ConfigController
{

    /**
     * @note 获取培训的后台设置1
     */
    public static function aiTrainBanner(Request $request): Response
    {
        $bannerInfo = SystemService::getAiBannerInfo();
        if (!$bannerInfo || empty($bannerInfo)) {
            $bannerInfo = [];
        }
        return JsonServer::success('AI培训banner', $bannerInfo);
    }
}