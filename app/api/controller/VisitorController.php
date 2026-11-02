<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file VisitorController.php
 * @description  在线访客接口
 */

namespace app\api\controller;

use app\api\service\CustomerService;
use app\common\server\JsonServer;
use support\Request;
use support\Response;

class VisitorController
{
    // ...

    /**
     * @note 标记访客消息为已读（清除当前客服的未读计数）
     */
    public function read(Request $request): Response
    {
        $result = CustomerService::markVisitorAsRead($request->all());

        if ($result['success'] === false) {
            return JsonServer::error($result['error'] ?? '操作失败')->withCors('POST, OPTIONS');
        }

        return JsonServer::success('已标记为已读', $result['data'])->withCors('POST, OPTIONS');
    }

    /**
     * @note 获取客户端真实IP（供前端连接WebSocket时使用）
     */
    public function getClientIp(Request $request): Response
    {
        // 使用统一的 get_client_ip() 函数获取真实IP（传入 Request 对象）
        $ip = get_client_ip($request);
        $userAgent = $request->header('user-agent', '');

        return JsonServer::success('OK', [
            'ip' => $ip,
            'user_agent' => $userAgent,
        ])->withCors();
    }

    /**
     * @note 延长访客等待时间
     */
    public function extend(Request $request): Response
    {
        $result = CustomerService::extendVisitorWaitTime($request->all());
        if ($result['success'] === false) {
            return JsonServer::error($result['error'] ?? '延长失败')->withCors('POST, OPTIONS');
        }

        return JsonServer::success('已延长等待时间', $result['data'])->withCors('POST, OPTIONS');
    }


    /**
     * @note 获取历史访客列表（支持客服专属未读数）
     * @description 如果传入 admin_id，则返回该客服的专属未读数；否则返回通用列表
     */
    public function getVisitorList(Request $request): Response
    {
        //获取在线列表（已重构为支持 admin_id 参数，自动注入专属未读数）
        $sessionMap = CustomerService::getOnlineSessionList($request->all());
        return JsonServer::success('OK', [
            'total' => count($sessionMap),
            'items' => array_values($sessionMap), // 确保返回纯数组而非关联数组
        ])->withCors();
    }

    /**
     * @note 查询客服挂起状态
     */
    public function holdStatus(Request $request): Response
    {
        //获取后台传的admin_id用户状态
        $adminId = (int)$request->get('admin_id', 0);
        $result = CustomerService::getAdminSessionSnapshot($adminId);
        if ($result['success'] === false) {
            return JsonServer::error($result['error'] ?? '查询失败')->withCors();
        }
        return JsonServer::success('OK', $result['data'])->withCors();
    }

    /**
     * @note 更新客服挂起状态
     */
    public function hold(Request $request): Response
    {
        //更新挂起操作状态
        $result = CustomerService::updateAdminHoldState($request->all());
        if ($result['success'] === false) {
            return JsonServer::error($result['error'] ?? '状态更新失败')->withCors('POST, OPTIONS');
        }

        return JsonServer::success('状态已更新', $result['data'])->withCors('POST, OPTIONS');
    }


    /**
     * @note 获取聊天系统配置1
     */
    public function config(Request $request): Response
    {
        $config = config('business.chat', []);
        return JsonServer::success('客服系统 配置信息', $config)->withCors();
    }

    /**
     * @note 获取指定客服已接入的访客列表（以数据库为准）
     * @description 前端可通过此接口获取客服当前已接入的访客列表，避免依赖WebSocket状态同步
     */
    public function served(Request $request): Response
    {
        $adminId = (int)$request->get('admin_id', 0);
        $result = CustomerService::getServedVisitorList($adminId);

        if ($result['success'] === false) {
            return JsonServer::error($result['error'] ?? '查询失败')->withCors();
        }

        return JsonServer::success('OK', $result['data'])->withCors();
    }
}
