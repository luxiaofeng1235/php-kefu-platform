<?php
/*
 *
 * WebSocket聊天服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file GatewayChatServer.php
 */

namespace app\common\server\websocket;

use app\model\WsAdminVisitorRelation;
use app\model\WsChatHistory;
use app\model\WsVisitorSession;
use GatewayWorker\Lib\Gateway;
use support\Cache;

// use support\Cache; // 已移除Redis

/**
 * WebSocket 聊天服务
 */
class GatewayChatServer
{
    /**
     * @note 预留的限速占位方法（已移除Redis，暂时禁用）
     * @param string $key 唯一标识（可用访客 token、IP 等）
     * @param int $limit 限制次数
     * @param int $ttl 统计时间窗口（秒）
     * @return bool 返回 true 表示达到限速，需要阻断本次处理
     */
    private static function hitRateLimit(string $key, int $limit = 30, int $ttl = 10): bool
    {
        if ($key === '' || $limit <= 0 || $ttl <= 0) {
            return false;
        }

        $cacheKey = 'ws:rate:' . $key;

        try {
            $redis = Cache::redis();
            $count = $redis->incr($cacheKey);
            if ($count === 1) {
                $redis->expire($cacheKey, $ttl);
            }
            return $count > $limit;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @note 处理连接握手
     * @param string $clientId 客户端标识
     * @param mixed $data 握手原始数据
     * @date 2025/03/11
     * @return void
     */
    public static function handleConnect(string $clientId, $data): void
    {
        // 握手参数通常通过 GET 提供，兼容数组或对象
        $query = is_array($data) ? (array)($data['get'] ?? []) : [];
        // role=admin 表示客服身份，否则按访客处理
        $role = strtolower((string)($query['role'] ?? 'visitor'));
        if (!in_array($role, ['admin', 'visitor'], true)) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '身份 role 参数无效',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }
        $token = trim((string)($query['token'] ?? ''));

        if ($token === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => 'token 参数缺失',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }
        //保存的session信息方便后期存储取出来去进行更新
        $sessionData = ['role' => $role, 'token' => $token, 'client_id' => $clientId];
        if ($role == 'admin') {
            $sessionData['admin_id'] = intval($query['admin_id'] ?? 0);
        }
        //缓存对应的key分那边进行存储，设置24小时过期，防止孤儿数据永久占用内存
        Cache::set(self::getSessionRedisKey($clientId), json_encode($sessionData), 86400);
        // 客服链接接入逻辑
        if ($role === 'admin') {
            self::handleAdminHandshake($clientId, $token, $query);
            return;
        }
        // 访客逻辑要求显式提供 token
        self::handleVisitorHandshake($clientId, $token, $data);
    }

    /**
     * @note 处理业务消息
     * @param string $clientId 发送方标识
     * @param string $message 原始消息
     * @date 2025/03/11
     * @return void
     */
    public static function handleMessage(string $clientId, string $message): void
    {
        // 消息主体仅支持 JSON，非 JSON 视为非法
        $data = json_decode($message, true);
        if (!is_array($data) || ($data['type'] ?? '') === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '消息格式不正确',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        if ($data['type'] === 'ping') {
            // 心跳消息直接原路返回 pong，并更新活跃时间
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'pong',
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE));

            // 更新客户端的最后活跃时间（用于清理僵尸连接）
            $session = Gateway::getSession($clientId);
            if (is_array($session) && isset($session['role'])) {
                //更新客服的最后活跃时间，这样方便进行记录，可以根据脚本来清理无效的client_id进行处理
                ChatPresenceServer::updateLastActiveTime($clientId, $session);
            }
            return;
        }

        if ($data['type'] === 'page_view') {
            // 访客页面浏览记录，转发给所有在线客服
            $session = Gateway::getSession($clientId);
            if (!is_array($session) || ($session['role'] ?? '') !== 'visitor') {
                return; // 只处理访客的页面浏览
            }
            //处理根据用户的token进行设置
            $visitorToken = (string)($session['token'] ?? '');
            if ($visitorToken === '') {
                return;
            }

            // 转发给所有在线客服
            $onlineAdminIds = ChatPresenceServer::getOnlineAdminIds();
            $payload = json_encode([
                'type' => 'visitor_page_view',
                'visitor_token' => $visitorToken,
                'page_path' => (string)($data['page_path'] ?? ''),
                'page_title' => (string)($data['page_title'] ?? ''),
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE);

            foreach ($onlineAdminIds as $adminId) {
                Gateway::sendToUid('admin:' . $adminId, $payload);
            }
            return;
        }

        if ($data['type'] === 'hold_visitor') {
            // 客服接入访客
            self::handleHoldVisitor($clientId, $data);
            return;
        }

        if ($data['type'] === 'release_visitor') {
            // 客服挂断访客
            self::handleReleaseVisitor($clientId, $data);
            return;
        }
        //判断是否可以对AI的消息进行介入，防止AI继续回复，用这个来切断
        if ($data['type'] === 'ai_status') {
            // 查询访客是否由AI服务（未被人工接入）
            self::handleAiStatus($clientId, $data);
            return;
        }

        self::dispatchMessage($clientId, $data);
    }

    /**
     * @note 处理连接断开
     * @param string $clientId 客户端标识
     * @date 2025/03/11
     * @return void
     */
    public static function handleClose(string $clientId): void
    {

        // session 里会保存握手时设置的身份信息1
        $session = Gateway::getSession($clientId);
        if (empty($session)) {
            //强制从websocket的session里取出来对应的信息
            $cacheSession = Cache::get(self::getSessionRedisKey($clientId));
            $cacheSession && $session = json_decode($cacheSession, true);
        }

        // 断线时同步移除 MySQL 中的在线记录
        ChatPresenceServer::cleanupSession($clientId, $session);
        $identifier = '用户 ' . $clientId;
        if (is_array($session)) {
            if (($session['role'] ?? '') === 'admin') {
                $nickname = (string)($session['nickname'] ?? '');
                $adminId = (int)($session['admin_id'] ?? 0);
                $identifier = $nickname !== ''
                    ? "客服 {$nickname}(ID={$adminId})"
                    : "客服 ID={$adminId}";
            } elseif (($session['role'] ?? '') === 'visitor') {
                $token = (string)($session['token'] ?? '');
                if ($token !== '') {
                    $identifier = "访客 token={$token}";
                }
            }
        }

        // 只通知所有在线客服（不通知其他访客）
        $onlineAdminIds = ChatPresenceServer::getOnlineAdminIds();
        $payload = json_encode([
            'type' => 'system',
            'message' => "{$identifier} 已断开连接",
            'client_id' => (string)$clientId,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        foreach ($onlineAdminIds as $adminId) {
            Gateway::sendToUid('admin:' . $adminId, $payload);
        }

        // 连接关闭后，清理Redis中的session缓存，防止数据泄漏
        Cache::del(self::getSessionRedisKey($clientId));
    }


    /**
     * @note 获取对应的Redis的key方便取
     * @param string $client_id 客户端标识
     * @date 2025/11/13
     * @return string
     */
    public static function getSessionRedisKey($client_id = ''): string
    {
        return sprintf("ws:session:v1:%s", $client_id);
    }

    /**
     * @note 识别客服握手并绑定身份
     * @param string $clientId 客户端标识
     * @param string $token 客服 token
     * @param array<string, mixed> $query 查询参数
     * @date 2025/03/11
     * @return void
     */
    private static function handleAdminHandshake(string $clientId, string $token, array $query): void
    {
        if (empty($token)) {
            // 客服必须携带 token，否则直接断开
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '客服 token 缺失',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }


        // 后台提供的客服 ID，必须为正整数
        $adminId = isset($query['admin_id']) ? (int)$query['admin_id'] : 0;
        if ($adminId <= 0) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '客服 ID 缺失',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        // 单终端限制：检测该客服是否已在线
        // 如果已有其他连接，拒绝本次登录（禁止顶号）
        $uid = self::buildAdminUid($adminId);
        $existingClientIds = Gateway::getClientIdByUid($uid);
        if (!empty($existingClientIds)) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '当前客服账号已在其他窗口登录，请关闭后再试',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        //验证签名是否有有效，如果无效直接拦截
        $jwt_token = buildAdminWebsocketToken($adminId, null, 32);
        if ($jwt_token !== $token) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '客服签名失败,请重新签名',
            ], JSON_UNESCAPED_UNICODE));
            gateway::closeClient($clientId);
            return;
        }

        $nickname = isset($query['nickname']) ? (string)$query['nickname'] : '';
        $adminModel = AdminLookupServer::findById($adminId);
        if ($adminModel === null) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '客服信息不存在',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        if ((int)($adminModel->is_kefu ?? 0) !== 1) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '不是客服，请先联系管理员设置',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        if ($nickname === '') {
            $nickname = (string)($adminModel->nickname ?? '');
        }

        $session = [
            'role' => 'admin',
            'token' => $token,
            'admin_id' => $adminId,
            'nickname' => $nickname,
            'uid' => self::buildAdminUid($adminId),
        ];

        // 绑定 UID 便于后续按客服维度查找连接
        Gateway::bindUid($clientId, $session['uid']);
        Gateway::setSession($clientId, $session);

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'welcome',
            'client_id' => (string)$clientId,
            'message' => '连接成功',
        ], JSON_UNESCAPED_UNICODE));

        // 将客服纳入在线列表，后续广播与列表展示依赖该数据
        ChatPresenceServer::markAdminOnline($clientId, $adminId, $token, $nickname);
        $linkedVisitors = ChatPresenceServer::getVisitorsByAdmin($adminId);
        $activeVisitorToken = $linkedVisitors[0] ?? '';
        $holdStatus = $activeVisitorToken === '' ? 0 : 1;

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'identity',
            'role' => 'admin',
            'admin_id' => $adminId,
            'nickname' => $nickname,
            'token' => $token,
            'uid' => $session['uid'],
            'hold_status' => $holdStatus,
            'visitor_token' => $activeVisitorToken,
        ], JSON_UNESCAPED_UNICODE));

        ChatPresenceServer::broadcastHoldStatus($adminId, $holdStatus, $activeVisitorToken);
        self::pushPendingOfflineMessages($clientId, $adminId);
    }

    /**
     * @note 识别访客握手并绑定身份
     * @param string $clientId 客户端标识
     * @param string $token 访客 token
     * @param mixed $data 握手数据
     * @date 2025/03/11
     * @return void
     */
    private static function handleVisitorHandshake(string $clientId, string $token, $data = null): void
    {
        if ($token === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => 'token 参数缺失',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        // 从握手数据中获取 IP 和 User-Agent的处理，分别获取
        $server = is_array($data) ? (array)($data['server'] ?? []) : [];

        // 使用统一的 get_client_ip() 函数获取真实IP
        $ip = get_client_ip($server);
        $userAgent = (string)($server['HTTP_USER_AGENT'] ?? '');

        $uid = self::buildVisitorUid($token);

        // 单终端限制：若已有同访客连接，则拒绝新连接并提示关闭重复窗口
        $existingClientIds = Gateway::getClientIdByUid($uid);
        if (!empty($existingClientIds)) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '当前访客已在其他窗口使用，请关闭后再试',
            ], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);
            return;
        }

        $session = [
            'role' => 'visitor',
            'token' => $token,
            'uid' => $uid,
        ];

        // 先创建/更新访客会话记录（数据库持久化，记录IP和User-Agent）
        $sessionModel = WsVisitorSession::createSession($token, $ip, $userAgent, $clientId);
        $nickname = '';
        if ($sessionModel !== null && isset($sessionModel->nickname)) {
            $nickname = (string)$sessionModel->nickname;
        }

        // 将访客昵称写入会话，便于后续使用
        $session['nickname'] = $nickname;

        Gateway::bindUid($clientId, $uid);
        Gateway::setSession($clientId, $session);

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'welcome',
            'client_id' => (string)$clientId,
            'message' => '连接成功',
        ], JSON_UNESCAPED_UNICODE));

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'identity',
            'role' => 'visitor',
            'token' => $token,
            'uid' => $uid,
            'nickname' => $nickname,
        ], JSON_UNESCAPED_UNICODE));

        // 再记录访客在线状态并推送给客服（确保数据库已更新）
        ChatPresenceServer::markVisitorOnline($clientId, $token);
    }

    /**
     * @note 消息路由
     * @param string $clientId 客户端标识
     * @param array<string, mixed> $data 消息载体
     * @date 2025/03/11
     * @return void
     */
    private static function dispatchMessage(string $clientId, array $data): void
    {
        //默认用这个标识：say_to_one 用来发消息，后期有其他类型方便进行扩展
        if (($data['type'] ?? '') !== 'say_to_one') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '不支持的消息类型',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 根据握手时缓存的角色判断当前身份
        $session = Gateway::getSession($clientId);
        if (!is_array($session) || ($session['role'] ?? '') === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '会话状态缺失，请重新连接',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 文本内容为空直接拒绝
        $content = isset($data['content']) ? trim((string)$data['content']) : '';
        if ($content === '' || mb_strlen($content) > 2000) {
            //推送消息的对文本的推送校验处理
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '消息内容不能为空，且不能超过 2000 字符',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $scene = isset($data['scene']) ? trim((string)$data['scene']) : '';
        $messageType = isset($data['message_type']) ? (int)$data['message_type'] : 1;
        if ($messageType <= 0) {
            $messageType = 1;
        }
        $extra = isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [];

        if ($session['role'] === 'admin') {
            $adminId = (int)($session['admin_id'] ?? 0);
            if ($adminId <= 0) {
                Gateway::sendToClient($clientId, json_encode([
                    'type' => 'error',
                    'message' => '客服身份异常，请重新连接',
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 支持多访客接入：优先用实时关联关系判断，避免依赖单一标志
            $linkedVisitors = ChatPresenceServer::getVisitorsByAdmin($adminId);
            if ($linkedVisitors === [] || $linkedVisitors === null) {
                Gateway::sendToClient($clientId, json_encode([
                    'type' => 'error',
                    'message' => '当前未接入访客，请先接入后再发送消息',
                ], JSON_UNESCAPED_UNICODE));
                return;
            }

            // 客服消息需要携带访客 token
            $targetToken = isset($data['target_token']) ? trim((string)$data['target_token']) : '';
            if ($targetToken === '') {
                Gateway::sendToClient($clientId, json_encode([
                    'type' => 'error',
                    'message' => '缺少访客 token',
                ], JSON_UNESCAPED_UNICODE));
                return;
            }
            self::sendAdminMessage($clientId, $session, $targetToken, $content, $scene, $messageType, $extra);
            return;
        }

        if ($session['role'] === 'visitor') {
            // 访客消息支持指定客服，也支持广播
            $targetAdminId = isset($data['target_admin_id']) ? (int)$data['target_admin_id'] : 0;
            self::sendVisitorMessage($clientId, $session, $targetAdminId, $content, $scene, $messageType, $extra);
            return;
        }

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'error',
            'message' => '未知身份类型',
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @note 客服消息发送
     * @param string $clientId 客服标识
     * @param array<string, mixed> $session 会话数据
     * @param string $targetToken 目标访客
     * @param string $content 消息内容
     * @param string $scene 场景
     * @param int $messageType 消息类型
     * @param array<string, mixed> $extra 附加信息
     * @date 2025/03/11
     * @return void
     */
    private static function sendAdminMessage(
        string $clientId,
        array  $session,
        string $targetToken,
        string $content,
        string $scene,
        int    $messageType,
        array  $extra
    ): void
    {
        $msgId = uniqid() . '_' . time();
        $visitorUid = self::buildVisitorUid($targetToken);
        $targetClientIds = Gateway::getClientIdByUid($visitorUid);
        if (empty($targetClientIds)) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '访客当前不在线',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $payload = [
            'type' => 'chat',
            'scene' => $scene,
            'from' => [
                'role' => 'admin',
                'admin_id' => (int)($session['admin_id'] ?? 0),
                'nickname' => (string)($session['nickname'] ?? ''),
            ],
            'to' => [
                'role' => 'visitor',
                'token' => $targetToken,
            ],
            'content' => $content,
            'message_type' => $messageType,
            'extra' => $extra,
            'timestamp' => time(),
            'msg_id' => $msgId,
        ];

        Gateway::sendToUid($visitorUid, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['is_self'] = true;
        Gateway::sendToClient($clientId, json_encode($payload, JSON_UNESCAPED_UNICODE));

        // 保存聊天记录
        WsChatHistory::addMessage(
            $msgId,
            $targetToken, // session_id
            2, // 客服发给访客
            $targetToken,
            (int)($session['admin_id'] ?? 0),
            $content,
            $messageType,
            $scene,
            $extra
        );

        // 成功对话后刷新客服与访客的绑定关系
        ChatPresenceServer::linkVisitorToAdmin($targetToken, (int)($session['admin_id'] ?? 0));

        // 客服发送消息时，同时更新该访客的最后活动时间（重置2分钟倒计时）
        WsVisitorSession::updateLastActivity($targetToken);
    }

    /**
     * @note 访客消息发送
     * @param string $clientId 访客标识
     * @param array<string, mixed> $session 会话信息
     * @param int $targetAdminId 目标客服
     * @param string $content 内容
     * @param string $scene 场景
     * @param int $messageType 类型
     * @param array<string, mixed> $extra 附加信息
     * @date 2025/03/11
     * @return void
     */
    private static function sendVisitorMessage(
        string $clientId,
        array  $session,
        int    $targetAdminId,
        string $content,
        string $scene,
        int    $messageType,
        array  $extra
    ): void
    {
        // 更新访客消息时间戳（首次消息会设置 first_message_at）
        $visitorToken = (string)($session['token'] ?? '');
        if ($visitorToken !== '') {
            WsVisitorSession::updateLastMessage($visitorToken, $scene);
            // 同时更新最后活动时间（用于超时检测）
            WsVisitorSession::updateLastActivity($visitorToken);

            // 【关键修复】广播访客列表更新，让客服端能看到 first_message_at 的变化
            ChatPresenceServer::broadcastVisitorList();
        }

        if ($targetAdminId > 0) {
            self::sendVisitorMessageToSingleAdmin(
                $clientId,
                $session,
                $targetAdminId,
                $content,
                $scene,
                $messageType,
                $extra
            );
            return;
        }

        self::broadcastVisitorMessageToAdmins(
            $clientId,
            $session,
            $content,
            $scene,
            $messageType,
            $extra
        );
    }

    /**
     * @note 针对单一客服的访客消息发送
     */
    private static function sendVisitorMessageToSingleAdmin(
        string $clientId,
        array  $session,
        int    $targetAdminId,
        string $content,
        string $scene,
        int    $messageType,
        array  $extra
    ): void
    {
        $msgId = uniqid() . '_' . time();
        $visitorToken = (string)($session['token'] ?? '');

        // 检查访客是否已被该目标客服接入
        $visitorSession = WsVisitorSession::where('visitor_token', $visitorToken)->first();
        $servedByAdminId = $visitorSession ? (int)($visitorSession->served_by_admin ?? 0) : 0;

        // 只有当目标客服不是接入该访客的客服时，才累加未读数
        if ($servedByAdminId !== $targetAdminId) {
            WsAdminVisitorRelation::incrementUnread($targetAdminId, $visitorToken);
        }

        $adminUid = self::buildAdminUid($targetAdminId);
        $targetClientIds = Gateway::getClientIdByUid($adminUid);
        if (empty($targetClientIds)) {
            try {
                $record = OfflineMessageServer::storeVisitorMessage(
                    $targetAdminId,
                    (string)($session['token'] ?? ''),
                    $content,
                    $messageType,
                    $scene,
                    $extra
                );
                // 虽为离线留言，也需记录访客与客服的会话关系
                ChatPresenceServer::linkVisitorToAdmin((string)($session['token'] ?? ''), $targetAdminId);

                // 保存全量历史记录 (即使客服离线)
                WsChatHistory::addMessage(
                    $msgId,
                    (string)($session['token'] ?? ''), // session_id
                    1, // 访客发给客服
                    (string)($session['token'] ?? ''),
                    $targetAdminId,
                    $content,
                    $messageType,
                    $scene,
                    $extra
                );

                Gateway::sendToClient($clientId, json_encode([
                    'type' => 'offline',
                    'message' => '客服当前不在线，已为您留言',
                    'message_id' => $record['message_id'] ?? '',
                    'msg_id' => $msgId,
                    'scene' => $scene,
                    'timestamp' => time(),
                ], JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $exception) {
                Gateway::sendToClient($clientId, json_encode([
                    'type' => 'error',
                    'message' => '留言失败，请稍后重试',
                ], JSON_UNESCAPED_UNICODE));
            }
            return;
        }

        $payload = [
            'type' => 'chat',
            'scene' => $scene,
            'from' => [
                'role' => 'visitor',
                'token' => (string)($session['token'] ?? ''),
            ],
            'to' => [
                'role' => 'admin',
                'admin_id' => $targetAdminId,
            ],
            'content' => $content,
            'message_type' => $messageType,
            'extra' => $extra,
            'timestamp' => time(),
            'msg_id' => $msgId,
        ];

        Gateway::sendToUid($adminUid, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['is_self'] = true;
        Gateway::sendToClient($clientId, json_encode($payload, JSON_UNESCAPED_UNICODE));

        // 保存聊天记录
        WsChatHistory::addMessage(
            $msgId,
            (string)($session['token'] ?? ''), // session_id
            1, // 访客发给客服
            (string)($session['token'] ?? ''),
            $targetAdminId,
            $content,
            $messageType,
            $scene,
            $extra
        );

        // 访客与指定客服建立会话关系，便于客服端展示
        ChatPresenceServer::linkVisitorToAdmin((string)($session['token'] ?? ''), $targetAdminId);
    }


    /**
     * @note 广播访客消息给所有客服
     * @param  $clientId string 设备Id
     * @param array $session session信息
     * @param string $content 内容
     * @param string $scene 会话场景
     * @param int $messageType 消息类型 消息类型：1文本 2:图片 3：视频 4：音频
     * @param array $extra 扩展信息，附件等
     * @date 2025/10/31
     * @return array|\think\response\Json
     */
    private static function broadcastVisitorMessageToAdmins(
        string $clientId,
        array  $session,
        string $content,
        string $scene,
        int    $messageType,
        array  $extra
    ): void
    {
        $msgId = uniqid() . '_' . time();
        $visitorToken = (string)($session['token'] ?? '');

        // 遍历广播给所有的客服人员，进行发送广播消息
        $adminIds = AdminLookupServer::getKefuIds();
        if (empty($adminIds)) {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => '暂无可用客服，请稍后重试',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 检查访客是否已被接入
        $visitorSession = WsVisitorSession::where('visitor_token', $visitorToken)->first();
        $servedByAdminId = $visitorSession ? (int)($visitorSession->served_by_admin ?? 0) : 0;

        // 过滤掉已接入该访客的客服，其他客服仍需累加未读数
        if ($servedByAdminId > 0) {
            // 排除已接入的客服
            $adminIdsToIncrement = array_filter($adminIds, fn($id) => (int)$id !== $servedByAdminId);
            if (!empty($adminIdsToIncrement)) {
                WsAdminVisitorRelation::batchIncrementUnread($adminIdsToIncrement, $visitorToken);
            }
        } else {
            // 访客未被接入，所有客服都累加未读数
            WsAdminVisitorRelation::batchIncrementUnread($adminIds, $visitorToken);
        }

        $timestamp = time();
        $onlineAdminIds = [];
        $offlineMessageIds = [];

        foreach ($adminIds as $adminId) {
            $adminUid = self::buildAdminUid($adminId);
            $clientIds = Gateway::getClientIdByUid($adminUid);
            if (!empty($clientIds)) {
                $payload = [
                    // 固定 chat 事件类型，方便前端区分消息流
                    'type' => 'chat',
                    // 会话场景（如 default、order 等）由上层传入
                    'scene' => $scene,
                    // 标记为广播消息，客服端据此控制提示语与展示
                    'broadcast' => true,
                    // 来源访客角色与 token，token 用于补偿绑定
                    'from' => [
                        'role' => 'visitor',
                        'token' => (string)($session['token'] ?? ''),
                    ],
                    // 目标客服角色及 ID，便于多端同步路由
                    'to' => [
                        'role' => 'admin',
                        'admin_id' => $adminId,
                    ],
                    // 文本或富媒体内容体
                    'content' => $content,
                    // 消息类型：1 文本、2 图片、3 视频、4 音频
                    'message_type' => $messageType,
                    // 扩展字段（附件地址、元信息等）
                    'extra' => $extra,
                    // 统一时间戳，保证多端时间线一致
                    'timestamp' => $timestamp,
                    'msg_id' => $msgId,
                ];

                Gateway::sendToUid($adminUid, json_encode($payload, JSON_UNESCAPED_UNICODE));
                $onlineAdminIds[] = $adminId;
                // 删除错误的自动绑定逻辑：访客发消息不应导致自动接入
                // ChatPresenceServer::linkVisitorToAdmin((string)($session['token'] ?? ''), $adminId);
                continue;
            }

            try {
                $record = OfflineMessageServer::storeVisitorMessage(
                    $adminId,
                    (string)($session['token'] ?? ''),
                    $content,
                    $messageType,
                    $scene,
                    $extra
                );
                // 同样删除离线留言时的自动绑定逻辑
                // ChatPresenceServer::linkVisitorToAdmin((string)($session['token'] ?? ''), $adminId);
                if (!empty($record)) {
                    $offlineMessageIds[] = (string)($record['message_id'] ?? '');
                }
            } catch (\Throwable $exception) {
                // ignore write failure for individual admin
            }
        }

        if ($onlineAdminIds !== []) {
            $ackPayload = [
                // 回执同样使用 chat 事件，前端可以沿用消息渲染流程
                'type' => 'chat',
                // 保留原始会话场景，以便前端在同一上下文展示
                'scene' => $scene,
                // 标识当前是广播消息的发送确认
                'broadcast' => true,
                // 收集所有成功接收的客服 ID，方便访客端展示目标列表
                'targets' => array_values($onlineAdminIds),
                // 回执里仍携带访客身份信息
                'from' => [
                    'role' => 'visitor',
                    'token' => (string)($session['token'] ?? ''),
                ],
                // 将命中的客服 ID 拼接回执；单客服返回字符串，多个拼接成逗号串
                'to' => [
                    'role' => 'admin',
                    'admin_id' => count($onlineAdminIds) === 1
                        ? (string)$onlineAdminIds[0]
                        : implode(',', array_values($onlineAdminIds)),
                ],
                // 原样回传访客消息内容
                'content' => $content,
                // 原样回传消息类型，保持端侧解析一致
                'message_type' => $messageType,
                // 附带扩展信息，常用于图片/视频等附加数据
                'extra' => $extra,
                // 统一使用广播发送时生成的时间戳
                'timestamp' => $timestamp,
                'msg_id' => $msgId,
            ];

            if ($offlineMessageIds !== []) {
                $ackPayload['offline_message_ids'] = $offlineMessageIds;
            }

            $ackPayload['is_self'] = true;
            Gateway::sendToClient($clientId, json_encode($ackPayload, JSON_UNESCAPED_UNICODE));

            // 保存聊天记录 (广播消息只存一份，admin_id 设为 0)
            WsChatHistory::addMessage(
                $msgId,
                (string)($session['token'] ?? ''), // session_id
                1, // 访客发给客服
                (string)($session['token'] ?? ''),
                0, // 广播消息，不指定客服ID
                $content,
                $messageType,
                $scene,
                $extra
            );
            return;
        }

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'offline',
            'message' => '客服当前不在线，已为您留言',
            'message_id' => $offlineMessageIds[0] ?? '',
            'message_ids' => $offlineMessageIds,
            'msg_id' => $msgId,
            'scene' => $scene,
            'timestamp' => $timestamp,
        ], JSON_UNESCAPED_UNICODE));

        // 即使全部离线，也保存历史记录
        WsChatHistory::addMessage(
            $msgId,
            (string)($session['token'] ?? ''), // session_id
            1, // 访客发给客服
            (string)($session['token'] ?? ''),
            0, // 广播消息，没人在线也归为0
            $content,
            $messageType,
            $scene,
            $extra
        );
        return;
    }

    /**
     * @note 推送客服离线消息
     * @param string $clientId 客服标识
     * @param int $adminId 客服ID
     * @date 2025/03/11
     * @return void
     */
    private static function pushPendingOfflineMessages(string $clientId, int $adminId): void
    {
        $records = OfflineMessageServer::pullPendingByAdmin($adminId);
        if ($records === []) {
            return;
        }

        $deliveredIds = [];

        foreach ($records as $record) {
            $timestamp = isset($record['created_at']) ? strtotime((string)$record['created_at']) : false;
            if ($timestamp === false || $timestamp <= 0) {
                $timestamp = time();
            }

            $payload = [
                'type' => 'chat',
                'scene' => (string)($record['scene'] ?? ''),
                'offline' => true,
                'message_id' => (string)($record['message_id'] ?? ''),
                'from' => [
                    'role' => 'visitor',
                    'token' => (string)($record['visitor_token'] ?? ''),
                ],
                'to' => [
                    'role' => 'admin',
                    'admin_id' => $adminId,
                ],
                'content' => (string)($record['content'] ?? ''),
                'message_type' => (int)($record['message_type'] ?? 1),
                'extra' => $record['extra'] ?? [],
                'timestamp' => $timestamp,
                'created_at' => (string)($record['created_at'] ?? ''),
            ];

            Gateway::sendToClient($clientId, json_encode($payload, JSON_UNESCAPED_UNICODE));

            if (($record['message_id'] ?? '') !== '') {
                $deliveredIds[] = (int)$record['message_id'];
            }
        }

        if ($deliveredIds !== []) {
            OfflineMessageServer::markAsSent($deliveredIds);
        }
    }

    /**
     * @note 生成客服UID
     * @param int $adminId 客服ID
     * @date 2025/03/11
     * @return string
     */
    private static function buildAdminUid(int $adminId): string
    {
        return 'admin:' . $adminId;
    }

    /**
     * @note 生成访客UID
     * @param string $token 访客token
     * @date 2025/03/11
     * @return string
     */
    private static function buildVisitorUid(string $token): string
    {
        return 'visitor:' . $token;
    }

    /**
     * @note 处理客服接入访客（WebSocket事件）
     * @param string $clientId 客服连接标识
     * @param array<string, mixed> $data 消息数据
     * @date 2025/11/28
     * @return void
     */
    private static function handleHoldVisitor(string $clientId, array $data): void
    {
        // 验证会话身份
        $session = Gateway::getSession($clientId);
        if (!is_array($session) || ($session['role'] ?? '') !== 'admin') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'hold_result',
                'success' => false,
                'error' => '仅客服可接入访客',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $adminId = (int)($session['admin_id'] ?? 0);
        $visitorToken = trim((string)($data['visitor_token'] ?? ''));

        if ($visitorToken === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'hold_result',
                'success' => false,
                'error' => '访客 token 不能为空',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 调用原有的业务逻辑（完全复用，保证逻辑一致性）
        $result = \app\api\service\CustomerService::updateAdminHoldState([
            'admin_id' => $adminId,
            'hold_status' => 1,
            'visitor_token' => $visitorToken,
        ]);

        // 返回结果给客服
        Gateway::sendToClient($clientId, json_encode([
            'type' => 'hold_result',
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'data' => $result['data'] ?? [],
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @note 处理客服挂断访客（WebSocket事件）
     * @param string $clientId 客服连接标识
     * @param array<string, mixed> $data 消息数据
     * @date 2025/11/28
     * @return void
     */
    private static function handleReleaseVisitor(string $clientId, array $data): void
    {
        // 验证会话身份
        $session = Gateway::getSession($clientId);
        if (!is_array($session) || ($session['role'] ?? '') !== 'admin') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'release_result',
                'success' => false,
                'error' => '仅客服可挂断访客',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $adminId = (int)($session['admin_id'] ?? 0);
        $visitorToken = trim((string)($data['visitor_token'] ?? ''));

        if ($visitorToken === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'release_result',
                'success' => false,
                'error' => '访客 token 不能为空',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 调用原有的业务逻辑（完全复用，保证逻辑一致性）
        $result = \app\api\service\CustomerService::updateAdminHoldState([
            'admin_id' => $adminId,
            'hold_status' => 0,
            'visitor_token' => $visitorToken,
        ]);

        // 返回结果给客服
        Gateway::sendToClient($clientId, json_encode([
            'type' => 'release_result',
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'data' => $result['data'] ?? [],
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @note 查询访客AI服务状态（供AI客服系统判断是否继续回复）
     * @param string $clientId 连接标识
     * @param array<string, mixed> $data 消息数据
     * @date 2025/12/03
     * @return void
     */
    private static function handleAiStatus(string $clientId, array $data): void
    {
        // 获取请求的token，访客可查询自己的状态
        $session = Gateway::getSession($clientId);
        $visitorToken = trim((string)($data['token'] ?? ''));

        // 如果是访客且未传token，默认查询自己的状态
        if ($visitorToken === '' && is_array($session) && ($session['role'] ?? '') === 'visitor') {
            $visitorToken = (string)($session['token'] ?? '');
        }

        if ($visitorToken === '') {
            Gateway::sendToClient($clientId, json_encode([
                'type' => 'ai_status_result',
                'token' => '',
                'is_can' => false,
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 查询访客会话，判断是否已被接入
        $visitorSession = WsVisitorSession::where('visitor_token', $visitorToken)->first();
        $servedByAdmin = $visitorSession ? (int)($visitorSession->served_by_admin ?? 0) : 0;

        // served_by_admin > 0 表示已被人工接入，返回 false；否则返回 true（AI可继续服务）
        $isCan = ($servedByAdmin <= 0);

        Gateway::sendToClient($clientId, json_encode([
            'type' => 'ai_status_result',
            'token' => $visitorToken,
            'is_can' => $isCan,
        ], JSON_UNESCAPED_UNICODE));
    }

}
