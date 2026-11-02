<?php
/*
 *
 * WebSocket在线状态服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ChatPresenceServer.php
 * @description   管理客服与访客的在线状态（全部改用MySQL，移除Redis）
 */

namespace app\common\server\websocket;

use app\api\service\CustomerService;
use app\model\WsAdminOnline;
use app\model\WsAdminVisitorRelation;
use app\model\WsVisitorSession;
use GatewayWorker\Lib\Gateway;
use support\Log;

/**
 * 管理客服与访客的在线状态（全MySQL实现）
 */
class ChatPresenceServer
{


    /**
     * @note 记录客服上线（改用MySQL）
     * @param string $clientId 设备Id
     * @param int $adminId 客服Id
     * @param string $token 认证token
     * @param string $nickname 用户昵称
     */
    public static function markAdminOnline(string $clientId, int $adminId, string $token, string $nickname): void
    {
        if ($adminId <= 0) {
            return;
        }


        // 使用MySQL记录在线状态
        WsAdminOnline::markOnline($adminId, $clientId, $token, $nickname);

        self::broadcastVisitorList();
        // 客服上线时推送其已接入的访客列表（初始化）
        self::broadcastServedList($adminId);
    }

    /**
     * @note 记录客服下线（改用MySQL）
     * @param string $clientId 设备Id
     * @param int $adminId 客服Id
     */
    public static function markAdminOffline(string $clientId, int $adminId): void
    {
        if ($adminId <= 0) {
            return;
        }

        // 使用MySQL标记客服下线（传入 clientId 以防止误删新窗口的在线状态）
        $affectedRows = WsAdminOnline::markOffline($adminId, $clientId);

        // 只有当真正下线成功时（即 affectedRows > 0），才解除该客服下所有访客的绑定关系
        if ($affectedRows > 0) {
            $visitorTokens = WsVisitorSession::getServedVisitorsByAdmin($adminId);
            // 解除所有访客与该客服的绑定关系，强制让served_by_admin清空，防止下次强制绑定
            foreach ($visitorTokens as $token) {
                if ($token) {
                    // 清理访客的 served_by_admin（关键操作）
                    WsVisitorSession::unmarkServed($token);
                    Log::info("[访客解绑] 访客 {$token} 已解除与客服 {$adminId} 的绑定");
                }
            }
        }

        self::broadcastVisitorList();
    }

    /**
     * @note 记录访客上线（改用MySQL）
     * @param string $clientId 设备Id
     * @param string $token 用户的唯一标识
     */
    public static function markVisitorOnline(string $clientId, string $token): void
    {
        if ($token === '') {
            return;
        }

        // 注意：createSession 已在 GatewayChatServer::handleVisitorInit 中调用（带 IP 参数）
        // 这里只负责广播访客列表给所有客服
        self::broadcastVisitorList();
    }

    /**
     * @note 记录访客下线（改用MySQL）
     * @param string $clientId 设备Id
     * @param string $token 生成的访问标识
     */
    public static function markVisitorOffline(string $clientId, string $token): void
    {
        if ($token === '') {
            return;
        }

        // 先获取该访客被哪个客服接入（在清除之前）
        $session = WsVisitorSession::getCurrentSession($token);
        $servedByAdminId = $session ? (int)($session->served_by_admin ?? 0) : 0;

        // 使用MySQL标记下线（会清除 served_by_admin）
        WsVisitorSession::markOffline($token);

        self::broadcastVisitorList();

        // 如果该访客之前被某个客服接入，通知该客服更新已接入列表
        if ($servedByAdminId > 0) {
            self::broadcastServedList($servedByAdminId);
            Log::info("[访客已离线] 访客 {$token} 已通知客服 {$servedByAdminId} 更新已接入列表");
        }

        Log::info("[访客已离线] 访客 {$token} 已从 MySQL 清理");
    }

    /**
     * @note 更新客户端最后活跃时间（心跳）
     * 客服：MySQL
     * 访客：MySQL
     * @param string $clientId 客户端ID
     * @param array<string, mixed> $session 会话信息
     */
    public static function updateLastActiveTime(string $clientId, array $session): void
    {
        $role = $session['role'] ?? '';

        if ($role === 'admin') {
            // 客服心跳：MySQL
            WsAdminOnline::heartbeat($clientId);
        } elseif ($role === 'visitor') {
            // 访客心跳：MySQL
            $token = (string)($session['token'] ?? '');
            if ($token === '') {
                return;
            }
            WsVisitorSession::updateLastActivity($token);
        }
    }

    /**
     * @note 建立访客与客服的关联（全MySQL）
     * 以访客表 served_by_admin 为准
     * @param string $token token信息
     * @param int $adminId 客服Id
     */
    public static function linkVisitorToAdmin(string $token, int $adminId): bool
    {
        if ($token === '' || $adminId <= 0) {
            return false;
        }

        // 访客：在MySQL中记录被哪个客服接入（核心操作）
        return WsVisitorSession::markServed($token, $adminId);
    }

    /**
     * @note 解除访客与客服的关联（全MySQL）
     * 以访客表 served_by_admin 为准
     * @param string $token token信息
     * @param int $adminId 客服Id
     */
    public static function unlinkVisitorFromAdmin(string $token, int $adminId): bool
    {
        if ($token === '' || $adminId <= 0) {
            return false;
        }

        // 访客：在MySQL中标记为未接入（核心操作）
        return WsVisitorSession::unmarkServed($token);
    }

    /**
     * @note 清理断线会话（全MySQL）
     * @param string $clientId 客户端ID
     * @param array $session 会话信息
     */
    public static function cleanupSession(string $clientId, $session): void
    {
        if (is_array($session)) {
            if (($session['role'] ?? '') === 'admin') {
                //清理客服
                self::markAdminOffline($clientId, (int)($session['admin_id'] ?? 0));
                return;
            }

            if (($session['role'] ?? '') === 'visitor') {
                //清理访客
                self::markVisitorOffline($clientId, (string)($session['token'] ?? ''));
            }
            return;
        }
    }

    /**
     * @note 在线客服 ID 列表（MySQL查询）
     * @return array
     */
    public static function getOnlineAdminIds(): array
    {
        $admins = WsAdminOnline::getOnlineAdmins(300);
        return array_column($admins, 'admin_id');
    }

    /**
     * @note 在线访客 token 列表（MySQL查询）
     * @return array
     */
    public static function getOnlineVisitorTokens(): array
    {
        return WsVisitorSession::where('is_online', 1)->pluck('visitor_token')->toArray();
    }

    /**
     * @note 获取当前客服下的访客（MySQL查询，以访客表wa_ws_visitor_session为准）
     * @return array
     */
    public static function getVisitorsByAdmin(int $adminId): array
    {

        if ($adminId <= 0) {
            return [];
        }
        // 以访客表为准查询，避免数据不一致
        return WsVisitorSession::getServedVisitorsByAdmin($adminId);
    }

    /**
     * @note 推送客服接入状态给相关客户端
     * @param int $adminId 客服Id
     * @param int $holdStatus 接入状态（0/1）
     * @param string $visitorToken 访客的唯一标识
     */
    public static function broadcastHoldStatus(int $adminId, int $holdStatus, string $visitorToken = ''): void
    {
        if ($adminId <= 0) {
            return;
        }

        $admin = WsAdminOnline::findByAdminId($adminId);
        if (!$admin) {
            return;
        }

        // 查询客服昵称
        $nickname = $admin->nickname ?? '';
        if ($nickname === '') {
            $model = AdminLookupServer::findById($adminId);
            if ($model !== null) {
                $nickname = (string)($model->nickname ?? '');
            }
        }

        $payload = json_encode([
            'type' => 'hold_sync',
            'admin_id' => $adminId,
            'admin_nickname' => $nickname,
            'hold_status' => $holdStatus > 0 ? 1 : 0,
            'visitor_token' => $visitorToken,
        ], JSON_UNESCAPED_UNICODE);

        // 发送给该客服的所有客户端
        $clients = WsAdminOnline::where('admin_id', $adminId)
            ->where('is_online', 1)
            ->pluck('client_id')
            ->toArray();

        foreach ($clients as $clientId) {
            Gateway::sendToClient($clientId, $payload);
        }

        if ($visitorToken === '') {
            return;
        }

        // 同时发送给访客
        $visitorSessions = WsVisitorSession::where('visitor_token', $visitorToken)
            ->where('is_online', 1)
            ->get(['client_id']);

        foreach ($visitorSessions as $session) {
            Gateway::sendToClient($session->client_id, $payload);
        }
    }

    /**
     * @note 将在线访客列表推送给所有客服连接（个性化推送未读数）
     */
    public static function broadcastVisitorList(): void
    {
        // 1. 获取所有在线访客的基础数据
        $sessions = WsVisitorSession::getOnlineSessions();
        $baseItems = [];

        if ($sessions !== []) {
            $adminNicknameCache = [];
            $lookupAdminNickname = static function (int $adminId) use (&$adminNicknameCache): string {
                if ($adminId <= 0) {
                    return '';
                }
                if (array_key_exists($adminId, $adminNicknameCache)) {
                    return $adminNicknameCache[$adminId];
                }
                $model = AdminLookupServer::findById($adminId);
                $nickname = $model ? (string)($model->nickname ?? '') : '';
                $adminNicknameCache[$adminId] = $nickname;
                return $nickname;
            };

            foreach ($sessions as $token => $session) {
                $servedAdminId = (int)($session->served_by_admin ?? 0);
                $servedAdminNickname = $lookupAdminNickname($servedAdminId);
                $resolvedToken = $token ?: ($session->visitor_token ?? '');

                $baseItems[$resolvedToken] = [
                    'token' => (string)$resolvedToken,
                    'nickname' => $session->nickname ?? '未知',
                    'is_online' => (int)($session->is_online ?? 0),
                    'connected_at' => strtotime($session->connected_at),
                    'disconnected_at' => $session->disconnected_at,
                    'clients' => [],
                    'admins' => $session->served_by_admin ? [$session->served_by_admin] : [],
                    'ip' => $session->ip ?? '',
                    'location' => $session->location ?? '',
                    'user_agent' => $session->user_agent ?? '',
                    'visit_count' => $session->visit_times ?? 1,
                    'first_message_at' => $session->first_message_at,
                    'last_message_at' => $session->last_message_at,
                    'last_message_scene' => $session->last_message_scene,
                    'message_count' => $session->message_count ?? 0,
                    'served_by_admin' => $session->served_by_admin,
                    'served_by_admin_nickname' => $servedAdminNickname,
                    'served_at' => $session->served_at,
                    'last_activity_at' => $session->last_activity_at,
                    'extended_until' => $session->extended_until,
                    // 'unread_count' 将在循环中动态注入
                ];
            }
        }

        // 2. 获取所有在线客服及其客户端ID
        // 返回结构: [['admin_id' => 1, 'client_id' => 'xxx'], ...]
        $onlineAdmins = WsAdminOnline::getOnlineAdmins(300);

        // 按 admin_id 分组客户端
        $adminClientsMap = [];
        foreach ($onlineAdmins as $row) {
            $aid = (int)$row['admin_id'];
            $cid = (string)$row['client_id'];
            if (!isset($adminClientsMap[$aid])) {
                $adminClientsMap[$aid] = [];
            }
            $adminClientsMap[$aid][] = $cid;
        }

        // 3. 遍历每个客服，注入专属未读数并推送
        foreach ($adminClientsMap as $adminId => $clientIds) {
            // 获取该客服的所有未读记录 [visitor_token => count]
            $unreadMap = WsAdminVisitorRelation::getUnreadMap($adminId);

            // 组装该客服专属的 items
            $personalizedItems = [];
            if ($sessions !== []) {
                foreach ($baseItems as $token => $item) {
                    $item['unread_count'] = $unreadMap[$token] ?? 0;
                    $personalizedItems[] = $item;
                }
            }

            $payload = json_encode([
                'type' => 'visitor_sync',
                'total' => count($personalizedItems),
                'items' => $personalizedItems,
            ], JSON_UNESCAPED_UNICODE);

            // 发送给该客服的所有客户端
            foreach ($clientIds as $clientId) {
                Gateway::sendToClient($clientId, $payload);
            }
        }
    }


    /**
     * @note 获取用户的列表信息
     * @param int $adminId int 用户列表
     * @date 2025/11/13
     * @return array|bool
     */
    public static function getAdminInfo(int $adminId)
    {
        if (!$adminId) {
            return false;
        }
        $info = WsAdminOnline::where('admin_id', $adminId)->first();
        if (!empty($info)) {
            $info = $info->toArray();
            //兼容老数据
            if (!isset($info['last_active'])) {
                $info['last_active'] = strtotime($info['last_activity_at']);
            }
        }
        return $info ?? [];
    }

    /**
     * @note 推送客服已接入的访客列表（以数据库为准）
     * @description 接入/挂断操作后调用，向指定客服推送其已接入的访客列表
     * @param int $adminId 客服ID
     */
    public static function broadcastServedList(int $adminId): void
    {
        if ($adminId <= 0) {
            return;
        }

        // 复用 CustomerService 的查询方法，保持数据一致
        $result = CustomerService::getServedVisitorList($adminId);
        if ($result['success'] !== true) {
            Log::warning("[broadcastServedList] 查询失败: admin_id={$adminId}, error={$result['error']}");
            return;
        }

        $payload = json_encode([
            'type' => 'served_sync',
            'admin_id' => $adminId,
            'total' => $result['data']['total'] ?? 0,
            'items' => $result['data']['items'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        // 发送给该客服的所有客户端
        $clients = WsAdminOnline::where('admin_id', $adminId)
            ->where('is_online', 1)
            ->pluck('client_id')
            ->toArray();
        //定向给客服推送访客列表
        foreach ($clients as $clientId) {
            if (!$clientId) {
                continue;
            }
            Gateway::sendToClient($clientId, $payload);
        }

        Log::info("[broadcastServedList] 已推送: admin_id={$adminId}, total={$result['data']['total']}");
    }
}
