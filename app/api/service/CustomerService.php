<?php
/*
 *
 * Copyright (c) 2025 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file CustomerService.php
 * @description 在线客服处理的主要server
 */

namespace app\api\service;

use app\common\server\websocket\AdminLookupServer;
use app\common\server\websocket\ChatPresenceServer;
use app\model\WsAdminVisitorRelation;
use app\model\WsChatHistory;
use app\model\WsVisitorSession;
use support\Cache;

class CustomerService
{
    /**
     * @note 获取客服账号列表
     * @date 2025/10/30
     * @return array<int, array<string, mixed>>
     */
    public static function getCustromerAccount(): array
    {
        $list = AdminLookupServer::getKefuList(['id', 'username', 'nickname']);
        // 默认处理下客服的id
        if (!empty($list) && is_array($list)) {
            foreach ($list as &$val) {
                // 默认按照这个返回客服的id
                $val['admin_kefu_id'] = isset($val['id']) ? (int)$val['id'] : 0;
                unset($val['id']);
            }
            unset($val);
        }
        return $list ?? [];
    }

    /**
     * @note 获取历史的访客记录-不指定查询是否在线，给一个默认的在线状态即可
     * @date 2025/11/4
     * @return array|\think\response\Json
     */
    public static function historyVistorList()
    {
        //获取访客的关联记录信息
        $list = WsVisitorSession::getAllVisitorSession();
        return $list ?? [];
    }

    /**
     * @note 查询客服的挂起状态与关联访客
     * @date 2025/10/31
     * @param int $adminId 客服ID
     * @return array{success: bool, error: string|null, data: array<string, mixed>}
     */
    public static function getAdminSessionSnapshot(int $adminId): array
    {
        if ($adminId <= 0) {

            return [
                'success' => false,
                'error' => 'admin_id 参数无效',
                'data' => [],
            ];
        }

        $admin = AdminLookupServer::findById($adminId);
        if ($admin === null) {
            return [
                'success' => false,
                'error' => '客服不存在或已禁用',
                'data' => [],
            ];
        }

        if ((int)($admin->is_kefu ?? 0) !== 1) {
            return [
                'success' => false,
                'error' => '不是客服，请先联系管理员设置',
                'data' => [],
            ];
        }

        $visitorTokens = ChatPresenceServer::getVisitorsByAdmin($adminId);

        return [
            'success' => true,
            'error' => null,
            'data' => [
                'admin_id' => $adminId,
                'hold_status' => empty($visitorTokens) ? 0 : 1,
                'visitor_tokens' => $visitorTokens,
                'active_visitor' => $visitorTokens[0] ?? '',
            ],
        ];
    }

    /**
     * @note 更新客服挂起状态
     * @date 2025/10/31
     * @param array<string, mixed> $params 请求参数
     * @return array{success: bool, error: string|null, data: array<string, mixed>}
     */
    public static function updateAdminHoldState(array $params): array
    {
        $adminId = isset($params['admin_id']) ? (int)$params['admin_id'] : 0;
        $holdStatusRaw = $params['hold_status'] ?? null;
        $visitorTokenRaw = $params['visitor_token'] ?? '';

        $holdStatus = is_numeric($holdStatusRaw) ? (int)$holdStatusRaw : -1;
        $visitorToken = trim((string)$visitorTokenRaw);

        if ($adminId <= 0 || ($holdStatus !== 0 && $holdStatus !== 1)) {
            return [
                'success' => false,
                'error' => '参数不完整或格式不正确',
                'data' => [],
            ];
        }

        $admin = AdminLookupServer::findById($adminId);
        if ($admin === null) {
            return [
                'success' => false,
                'error' => '客服不存在或已禁用',
                'data' => [],
            ];
        }

        if ((int)($admin->is_kefu ?? 0) !== 1) {
            return [
                'success' => false,
                'error' => '不是客服，请先联系管理员设置',
                'data' => [],
            ];
        }

        if ($holdStatus === 1 && $visitorToken === '') {
            return [
                'success' => false,
                'error' => '接入访客时必须提供 visitor_token',
                'data' => [],
            ];
        }

        $token = $visitorToken;
        if ($holdStatus === 0 && $token === '') {
            $linked = ChatPresenceServer::getVisitorsByAdmin($adminId);
            $token = $linked[0] ?? '';
        }

        // 记录更新后的时间戳（用于返回给前端）
        $lastActivityAt = null;
        // 实际生效的状态（考虑是否还有其他访客）
        $effectiveHold = $holdStatus;

        // Redis 短锁，防止并发“秒杀式”重复接入同一访客
        $lockKey = '';
        $redis = null;
        $lockAcquired = false;

        try {
            if ($holdStatus === 1) {
                $lockKey = 'visitor:hold:' . $token;
                try {
                    $redis = Cache::redis();
                    // 设置 3 秒短锁，获取不到直接提示稍后再试
                    $lockAcquired = (bool)$redis->set($lockKey, (string)$adminId, ['nx', 'ex' => 3]);
                } catch (\Throwable $e) {
                    // Redis 异常不阻断流程，继续走 DB 原子更新
                    $redis = null;
                    $lockAcquired = true;
                }
                if ($lockAcquired === false) {
                    return [
                        'success' => false,
                        'error' => '该访客正在接入处理中，请稍后再试',
                        'data' => [],
                    ];
                }
            }

            if ($holdStatus === 1) {
                $session = WsVisitorSession::getCurrentSession($token);
                if ($session && $session->served_by_admin && $session->served_by_admin !== $adminId) {
                    return [
                        'success' => false,
                        'error' => "该访客已被客服 {$session->served_by_admin} 接入，无法重复接入",
                        'data' => [],
                    ];
                }

                if ($session === null) {
                    return [
                        'success' => false,
                        'error' => '访客不存在或已离线，无法接入',
                        'data' => [],
                    ];
                }

                $linked = ChatPresenceServer::linkVisitorToAdmin($token, $adminId);
                if ($linked === false) {
                    return [
                        'success' => false,
                        'error' => '访客不在线、会话不存在或已被其他客服接入，无法接入',
                        'data' => [],
                    ];
                }

                // 记录更新后的时间（用于返回前端与倒计时初始化）
                $lastActivityAt = date('Y-m-d H:i:s');
                WsVisitorSession::updateLastActivity($token);
                $effectiveHold = 1;
            } elseif ($holdStatus === 0) {
                if ($token === '') {
                    return [
                        'success' => false,
                        'error' => '挂断操作缺少 visitor_token',
                        'data' => [],
                    ];
                }

                // 挂断访客（保证数据库解除绑定）
                ChatPresenceServer::unlinkVisitorFromAdmin($token, $adminId);

                // 检查客服是否还有其他接入的访客，决定最终
                $remainingVisitors = ChatPresenceServer::getVisitorsByAdmin($adminId);
                $effectiveHold = empty($remainingVisitors) ? 0 : 1;
            }

            ChatPresenceServer::broadcastHoldStatus($adminId, $effectiveHold, $token);
            // 广播访客列表更新，让所有客服知道该访客的 served_by_admin 状态变化
            ChatPresenceServer::broadcastVisitorList();
            // 推送该客服已接入的访客列表（以数据库为准，前端可直接渲染）
            ChatPresenceServer::broadcastServedList($adminId);

            $responseData = [
                'admin_id' => $adminId,
                'hold_status' => $holdStatus,
                'visitor_token' => $token,
            ];

            // 如果是接入操作，返回更新后的时间戳
            if ($holdStatus === 1 && $lastActivityAt !== null) {
                $responseData['last_activity_at'] = $lastActivityAt;
            }

            return [
                'success' => true,
                'error' => null,
                'data' => array_merge($responseData, ['hold_status' => $effectiveHold]),
            ];
        } finally {
            if ($lockAcquired && $redis && $lockKey !== '') {
                try {
                    $redis->del($lockKey);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * @note 延长访客等待时间
     * @date 2025/11/11
     * @param array<string, mixed> $params 请求参数
     * @return array{success: bool, error: string|null, data: array<string, mixed>}
     */
    public static function extendVisitorWaitTime(array $params): array
    {
        $adminId = isset($params['admin_id']) ? (int)$params['admin_id'] : 0;
        $visitorToken = trim((string)($params['visitor_token'] ?? ''));
        // 从配置读取延长时间，不再接受前端传参
        $minutes = (int)config('business.chat.extend_wait_minutes');
        if (!$minutes || $minutes <= 0) {
            return [
                'success' => false,
                'error' => '参数配置 extend_wait_minutes缺失，请检查配置文件',
                'data' => [],
            ];
        }

        if ($adminId <= 0 || $visitorToken === '') {
            return [
                'success' => false,
                'error' => '参数不完整',
                'data' => [],
            ];
        }

        // 验证权限：必须是当前客服接入的访客
        $session = WsVisitorSession::getCurrentSession($visitorToken);
        if (!$session) {
            return [
                'success' => false,
                'error' => '访客不存在或已离线',
                'data' => [],
            ];
        }

        if ($session->served_by_admin !== $adminId) {
            return [
                'success' => false,
                'error' => '无权操作，该访客未被您接入',
                'data' => [],
            ];
        }

        // 延长等待时间
        $success = WsVisitorSession::extendWaitTime($visitorToken, $minutes);
        if (!$success) {
            return [
                'success' => false,
                'error' => '延长失败',
                'data' => [],
            ];
        }

        $extendedUntil = date('Y-m-d H:i:s', time() + $minutes * 60);

        return [
            'success' => true,
            'error' => null,
            'data' => [
                'admin_id' => $adminId,
                'visitor_token' => $visitorToken,
                'minutes' => $minutes,
                'extended_until' => $extendedUntil,
            ],
        ];
    }

    /**
     * @note 提供给 /api/visitor/online 的统一数据源（历史式：在线+离线）
     * @return array<int, array<string, mixed>>
     */
    public static function getAllVisitorSessionsAsItems(): array
    {
        // 直接透传模型数据，保持最小处理
        return array_values(WsVisitorSession::getAllVisitorSession());
    }

    /**
     * @note 获取指定客服已接入的访客列表（以数据库为准，前端可直接渲染）
     * @param int $adminId 客服ID
     * @return array{success: bool, error: string|null, data: array<string, mixed>}
     */
    public static function getServedVisitorList(int $adminId): array
    {
        if ($adminId <= 0) {
            return [
                'success' => false,
                'error' => 'admin_id 参数无效',
                'data' => [],
            ];
        }

        $admin = AdminLookupServer::findById($adminId);
        if ($admin === null) {
            return [
                'success' => false,
                'error' => '客服不存在或已禁用',
                'data' => [],
            ];
        }

        if ((int)($admin->is_kefu ?? 0) !== 1) {
            return [
                'success' => false,
                'error' => '不是客服，请先联系管理员设置',
                'data' => [],
            ];
        }

        // 查询该客服已接入的访客（以 served_by_admin 字段为准）
        //按照接入时长的顺序先后排列
        $sessions = WsVisitorSession::where('served_by_admin', $adminId)
            ->where('is_online', 1)
            ->orderBy('served_at', 'desc')
            ->get();

        $items = [];
        foreach ($sessions as $session) {
            $items[] = [
                'token' => (string)$session->visitor_token,
                'nickname' => $session->nickname ?? '',
                'is_online' => (int)($session->is_online ?? 0),
                'ip' => $session->ip ?? '',
                'location' => $session->location ?? '',
                'connected_at' => $session->connected_at,
                'served_at' => $session->served_at,
                'last_activity_at' => $session->last_activity_at,
                'extended_until' => $session->extended_until,
                'first_message_at' => $session->first_message_at,
                'last_message_at' => $session->last_message_at,
                'last_message_scene' => $session->last_message_scene,
                'message_count' => $session->message_count ?? 0,
                'visit_times' => $session->visit_times ?? 1,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'data' => [
                'admin_id' => $adminId,
                'total' => count($items),
                'items' => $items,
            ],
        ];
    }

    /**
     * @note 获取聊天记录历史
     * @param array $params 请求参数 (role, token, admin_id, visitor_token, limit, before_id)
     * @return array{error: string|null, list: array, has_more: bool}
     */
    public static function getChatHistory(array $params): array
    {
        $requestingRole = $params['role'] ?? 'visitor';
        $limit = (int)($params['limit'] ?? 20);
        $beforeId = (int)($params['before_id'] ?? 0);
        // 鉴权与目标确定逻辑全部收拢到这里
        $targetVisitorToken = '';
        if ($requestingRole === 'visitor') {

            $requestingVisitorToken = $params['token'] ?? '';
            // 访客只能查自己的，所以目标就是请求者
            if (empty($requestingVisitorToken)) {
                return ['error' => '访客身份验证失败', 'list' => [], 'has_more' => false];
            }

            $targetVisitorToken = $requestingVisitorToken;

        } elseif ($requestingRole === 'admin') {
            // 客服必须指定 visitor_token
            $targetVisitorToken = $params['visitor_token'] ?? '';
        } else {
            return ['error' => '无效的角色类型', 'list' => [], 'has_more' => false];
        }
        if (empty($targetVisitorToken)) {
            return ['error' => '未指定目标访客', 'list' => [], 'has_more' => false];
        }
        $query = WsChatHistory::query()
            ->where('session_id', $targetVisitorToken);
        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }
        $models = $query->orderBy('id', 'desc')
            ->limit($limit + 1) // 多取一条判断是否有更多
            ->get();
        $hasMore = false;
        if (count($models) > $limit) {
            $hasMore = true;
            $models->pop(); // 移除多取的那一条
        }
        $list = [];
        foreach ($models as $model) {
            $item = [
                'id' => $model->id,
                'msg_id' => $model->msg_id,
                'session_id' => $model->session_id,
                'direction' => $model->direction,
                'content' => $model->content,
                'type' => 'chat', // 默认 chat，可根据 message_type 细化
                'message_type' => $model->message_type,
                'extra' => $model->extra,
                'scene' => $model->scene,//消息场景 区分是AI还是普通消息
                'timestamp' => strtotime($model->created_at),
                'created_at' => (string)$model->created_at,
            ];
            if ($model->direction === 1) { // 访客发给客服
                $item['from'] = [
                    'role' => 'visitor',
                    'token' => $model->visitor_token
                ];
                $item['to'] = [
                    'role' => 'admin',
                    'admin_id' => $model->admin_id
                ];
            } else { // 客服发给访客
                $item['from'] = [
                    'role' => 'admin',
                    'admin_id' => $model->admin_id
                ];
                $item['to'] = [
                    'role' => 'visitor',
                    'token' => $model->visitor_token
                ];
            }
            $list[] = $item;
        }
        return ['error' => null, 'list' => array_reverse($list), 'has_more' => $hasMore];
    }

    /**
     * @note 获取当前的访客列表（不区分是否消在线）
     * @param mixed $request 请求参数
     * @date 2025/12/4
     * @return array|object
     */
    public static function getOnlineSessionList(mixed $request)
    {
        //获取传入后的admin_id
        $adminId = intval($request['admin_id'] ?? 0);
        // 直接从数据库查询所有的访客，不缺分是否在线
        $sessionMap = WsVisitorSession::getOnlineSessions();
        // 如果提供了 admin_id，获取该客服的专属未读数
        $unreadMap = [];
        if ($adminId > 0) {
            $unreadMap = WsAdminVisitorRelation::getUnreadMap($adminId);
        }
        if ($sessionMap !== []) {
            $nicknameCache = [];
            foreach ($sessionMap as $session) {

                // ... (原有逻辑) ...
                $adminIdStored = (int)($session->served_by_admin ?? 0);
                if ($adminIdStored > 0) { // 修正变量名冲突，原逻辑里也用了 $adminId
                    if (!array_key_exists($adminIdStored, $nicknameCache)) {
                        $model = AdminLookupServer::findById($adminIdStored);
                        $nicknameCache[$adminIdStored] = $model ? (string)($model->nickname ?? '') : '';
                    }
                    $session->served_by_admin_nickname = $nicknameCache[$adminIdStored];
                } else {
                    $session->served_by_admin_nickname = '';
                }

                $session->visitor_token = (string)$session->visitor_token;

                // 覆盖未读数
                if ($adminId > 0) {
                    $session->unread_count = $unreadMap[$session->visitor_token] ?? 0;
                }
            }
        }
        return $sessionMap;
    }

    /**
     * @note 标记访客消息为已读（清除指定客服的未读计数）
     * @param array<string, mixed> $params 请求参数
     * @return array{success: bool, error: string|null, data: array<string, mixed>}
     */
    public static function markVisitorAsRead(array $params): array
    {
        $visitorToken = trim((string)($params['visitor_token'] ?? ''));
        $adminId = isset($params['admin_id']) ? (int)$params['admin_id'] : 0;

        if ($visitorToken === '' || $adminId <= 0) {
            return [
                'success' => false,
                'error' => '参数不全',
                'data' => [],
            ];
        }

        // 验证客服是否存在
        $admin = AdminLookupServer::findById($adminId);
        if ($admin === null) {
            return [
                'success' => false,
                'error' => '客服不存在或已禁用',
                'data' => [],
            ];
        }

        if ((int)($admin->is_kefu ?? 0) !== 1) {
            return [
                'success' => false,
                'error' => '不是客服，请先联系管理员设置',
                'data' => [],
            ];
        }

        // 清除未读计数
        WsAdminVisitorRelation::clearUnread($adminId, $visitorToken);

        return [
            'success' => true,
            'error' => null,
            'data' => [
                'admin_id' => $adminId,
                'visitor_token' => $visitorToken,
            ],
        ];
    }
}
