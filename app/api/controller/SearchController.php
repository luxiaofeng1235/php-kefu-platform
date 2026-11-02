<?php
/*
 *
 * 搜索服务接口
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file SearchController.php
 */

namespace app\api\controller;

use app\common\server\JsonServer;
use app\common\server\spider\BingSearchServer;
use RuntimeException;
use support\Request;
use support\Response;

/**
 * 搜索服务接口
 */
class SearchController
{
    /**
     * @note Bing 搜索接口
     * @param Request $request 当前请求实例
     * @return Response
     */
    public function query(Request $request): Response
    {
        $params = (array)$request->get();
        try {
            //bing搜索服务主流程采集获取加工的的搜索条目数据
            $data = BingSearchServer::search($params);
        } catch (RuntimeException $exception) {
            return JsonServer::error('搜索失败：' . $exception->getMessage());
        }
        return JsonServer::success('搜索成功', $data);
    }
}
