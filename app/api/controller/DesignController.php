<?php
/*
 *
 * 三维管理控制器
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignController.php
 */

namespace app\api\controller;

use app\api\service\SectorService;
use app\common\server\JsonServer;
use support\Request;
use support\RequestHelper;
use support\Response;

/**
 * 三维设计控制器
 */
class DesignController
{


    /**
     * @note 获取首页基础设置
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */

    public function indexSetting(Request $request)
    {
        $params = RequestHelper::collectParams($request);
        //获取首页的设置的配置信息
        $info = SectorService::getIndexSetting($params);
        return JsonServer::success('首页设置', $info);
    }


    /**
     * @note 获取 VR 列表（分页）
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function index(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getVrList($params);

        return JsonServer::success('VR 列表', $result);
    }

    /**
     * @note 获取三维可视化列表（分页）
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function visual(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getVisualList($params);

        return JsonServer::success('三维可视化列表', $result);
    }

    /**
     * @note 获取三维场景列表（分页）
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function threeDimensional(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getThreeDimensionalList($params);

        return JsonServer::success('三维场景列表', $result);
    }

    /**
     * @note 获取 AI 定制化平台列表（分页）
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function platform(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getPlatformList($params);

        return JsonServer::success('AI 定制化平台列表', $result);
    }

    /**
     * @note 获取 AI 工业园区详情
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function industrial(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getIndustrial($params);

        return JsonServer::success('AI 工业园区', $result);
    }

    /**
     * @note 获取合作伙伴横幅配置
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function partnerBanner(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $result = SectorService::getPartnerBanner($params);

        return JsonServer::success('合作伙伴横幅', $result);
    }

    /**
     * @note 综合搜索设计资源
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function search(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        $keyword = $params['keyword'] ?? '';
        $keyword = addslashes($keyword);
        if (!$keyword) {
            return JsonServer::error('请输入搜索内容');
        }
        //指定输入类型
        $type = strtolower((string)($params['type'] ?? 'all'));
        if (!SectorService::supportsSearchType($type)) {
            return JsonServer::error('不支持的搜索类型');
        }

        if ($type === 'website') {
            if (!array_key_exists('channel_type', $params)) {
                return JsonServer::error('参数不全：缺少 channel_type');
            }
            $channelType = (int)$params['channel_type'];
            if (!in_array($channelType, [1, 2, 3], true)) {
                return JsonServer::error('channel_type 取值范围为 1(网站)/2(小程序)/3(APP)');
            }
            $params['channel_type'] = $channelType;
        } else {
            unset($params['channel_type']);
        }

        $params['type'] = $type;
        //执行搜索结果
        $result = SectorService::searchDesignResources($params);

        return JsonServer::success('搜索结果', $result);
    }

    /**
     * @note 获取三维站点列表（按类型分页）
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function website(Request $request): Response
    {
        $params = RequestHelper::collectParams($request);
        if (!array_key_exists('type', $params)) {
            return JsonServer::error('参数不全：缺少 type');
        }

        $result = SectorService::getWebsiteList($params);

        return JsonServer::success('站点列表', $result);
    }

    /**
     * @note 虚拟一体化
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function integration(Request $request): Response
    {
        $info = SectorService::getIntegration();
        return JsonServer::success('虚拟一体化', $info);
    }

    /**
     * @note 兼容旧入口，统一输出 VR 列表
     * @param Request $request 请求实例
     * @return Response 返回统一 JSON 响应
     */
    public function getValueByType(Request $request): Response
    {
        return $this->website($request);
    }
}
