<?php

namespace plugin\webman\gateway;

use app\common\server\websocket\GatewayChatServer;

/**
 * GatewayWorker 事件处理类
 * 所有 WebSocket 事件都委托给 GatewayChatServer 处理
 */
class Events
{
    /**
     * 当客户端连接时触发
     * @param string $client_id 客户端连接标识
     * @return void
     */
    public static function onConnect($client_id)
    {
        // 调用业务逻辑层处理连接
        // 统一封装握手数据结构，兼容 GatewayChatServer::handleConnect 对 ['get']、['server'] 的读取
        GatewayChatServer::handleConnect($client_id, [
            'get' => $_GET ?? [],
            'server' => $_SERVER ?? [],
        ]);
    }

    /**
     * 当收到客户端消息时触发
     * @param string $client_id 客户端连接标识
     * @param string $message 消息内容
     * @return void
     */
    public static function onMessage($client_id, $message)
    {
        // 调用业务逻辑层处理消息
        GatewayChatServer::handleMessage($client_id, $message);
    }

    /**
     * 当客户端断开连接时触发
     * @param string $client_id 客户端连接标识
     * @return void
     */
    public static function onClose($client_id)
    {
        // 调用业务逻辑层处理断开连接 <-- 关键修复!
        GatewayChatServer::handleClose($client_id);
    }
}
