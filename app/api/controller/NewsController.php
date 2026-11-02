<?php
/*
 *
 * Copyright (c) 2025 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file NewsController.php
 * @description 活动资讯
 */

namespace app\api\controller;

use app\api\service\NewService;
use app\common\server\JsonServer;
use support\Request;
use support\Response;


class NewsController
{

    /**
     * @note 活动资讯列表，限制每页七条数据
     */
    public function activityList(Request $request): Response
    {

        //获取活动资讯
        $params = $request->get();
        $actLists = NewService::getList($params);
        if (empty($actLists)) {
            $actLists = [];
        }
        return JsonServer::success('活动资讯', $actLists);
    }

    public function test(Request $request): Response
    {
        $data['ip'] = $request->getRealIp();
        $data['testenv'] = getenv('WEBMAN_LISTEN');
        $data['datewayurl'] = getenv('WS_GATEWAY_URL');
        return JsonServer::success('测试test', $data);
    }
}