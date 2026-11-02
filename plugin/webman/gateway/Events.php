<?php
/*
 *
 * WebSocket 事件处理
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file Events.php
 */
namespace plugin\webman\gateway;

use app\common\server\websocket\GatewayChatServer;

/**
 * WebSocket 事件入口
 */
class Events
{
    /**
     * @note TCP 连接建立（尚未完成 WebSocket 握手）
     * @param string $clientId 客户端ID
     * @return void
     */
    public static function onConnect($clientId)
    {
        // 预留钩子，后续可在此处实现连接级别的限流、黑名单等策略。
    }

    /**
     * @note 连接建立
     * @param string $clientId 客户端ID
     * @param mixed $data 握手数据
     * @return void
     */
    public static function onWebSocketConnect($clientId, $data)
    {
        //在握手之后做鉴权和用户的role=admin等用户的判断提交等校验
        GatewayChatServer::handleConnect((string)$clientId, $data);
    }

    /**
     * @note 收到消息
     * @param string $clientId 客户端ID
     * @param string $message 原始消息
     * @return void
     */
    public static function onMessage($clientId, $message)
    {
        GatewayChatServer::handleMessage((string)$clientId, (string)$message);
    }

    /**
     * @note 连接关闭
     * @param string $clientId 客户端ID
     * @return void
     */
    public static function onClose($clientId)
    {
        GatewayChatServer::handleClose((string)$clientId);
    }
}
