<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ActivityController.php
 * @description   活动相关的配置，获取单视频和首页路由
 */

namespace app\api\controller;

use app\api\service\MarketingService;
use app\api\service\NewService;
use app\api\service\SectorService;
use app\common\server\JsonServer;
use RuntimeException;
use support\Request;
use support\RequestHelper;
use support\Response;

class ActivityController
{

    /**
     * @note 获取单视频
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function singleVideo(Request $request): Response
    {
        try {
            //请求的参数
            $param = RequestHelper::collectParams($request);
            //获取视频的列表
            $list = SectorService::getSingleVideoList($param);

        } catch (RuntimeException $exception) {
            return JsonServer::error('获取失败：' . $exception->getMessage());
        }

        return JsonServer::success('单视频列表', $list);
    }

    /**
     * @note 获取广告列表
     * @param
     * @date 2025/11/28
     * @return array|\think\response\Json
     */
    public function getAdsRecord(Request $request): Response
    {

        $params = RequestHelper::collectParams($request);
        //获取请求的广告列表参数
        $advertisement = MarketingService::getAdsRecommandList($params);
        if (empty($advertisement)) {
            $advertisement = [];
        }
        return JsonServer::success('广告列表', $advertisement);
    }


    /**
     * @note 获取首页路由
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function indexRouteConfig(Request $request): Response
    {
        $config = NewService::getGeneralRouter();
        return JsonServer::success('路由配置', $config);
    }
}