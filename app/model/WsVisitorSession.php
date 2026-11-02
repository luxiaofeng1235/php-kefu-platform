<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file WsVisitorSession.php
 * @description 访客会话记录模型
 */

namespace app\model;

use support\Model;

/**
 * WebSocket 访客会话记录模型
 *
 * @property int $id 会话主键ID
 * @property string $visitor_token 访客唯一标识token
 * @property string|null $ip 访客IP地址
 * @property string|null $location 地理位置(省·市)
 * @property string|null $user_agent 浏览器User Agent
 * @property string $connected_at 本次连接时间
 * @property string|null $disconnected_at 断开连接时间
 * @property int $is_online 是否在线（1=在线 0=已离线）
 * @property string|null $first_message_at 首次发消息时间
 * @property string|null $last_message_at 最后消息时间
 * @property string|null $last_message_scene 最后消息场景(ai_assist等)
 * @property int $message_count 本次会话消息总数
 * @property int|null $served_by_admin 接入的客服ID
 * @property string|null $served_at 客服接入时间
 * @property string $created_at 记录创建时间
 * @property string $updated_at 记录更新时间
 */
class WsVisitorSession extends Model
{
    /**
     * 表名（框架会自动加 wa_ 前缀，实际表名为 wa_ws_visitor_session）
     */
    protected $table = 'ws_visitor_session';

    /**
     * 主键
     */
    protected $primaryKey = 'id';

    /**
     * 启用自动时间戳
     */
    public $timestamps = true;

    /**
     * 时间戳字段名
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'client_id',
        'visitor_token',
        'nickname',
        'ip',
        'location',
        'user_agent',
        'connected_at',
        'disconnected_at',
        'is_online',
        'first_message_at',
        'last_message_at',
        'last_activity_at',
        'extended_until',
        'last_message_scene',
        'message_count',
        'visit_times',
        'served_by_admin',
        'served_at',
    ];


    /**
     * 字段类型转换
     */
    protected $casts = [
        'id' => 'integer',
        'is_online' => 'integer',
        'message_count' => 'integer',
        'visit_times' => 'integer',
        'served_by_admin' => 'integer',
    ];

    /**
     * @note 获取所有历史访客会话（在线+离线），最小闭环：不做过滤开关，直接按固定排序返回
     *       返回以 token 为 key 的 WsVisitorSession 实例数组（与历史用法兼容）。
     * @return array<string, self>
     */
    public static function getAllVisitorSession(array $options = []): array
    {
        $tokens = [];
        if (!empty($options['tokens']) && is_array($options['tokens'])) {
            $tokens = array_values(array_filter(array_unique(array_map('strval', $options['tokens']))));
        }

        return self::getOnlineSessions($tokens);
    }

    /**
     * @note 获取当前在线或近期活跃/连接的访客会话列表 (优化后)
     * @param array $tokens 访客token列表（可选，为空则返回所有符合条件的会话）
     * @return array 以 token 为 key 的数组，按 visit_times 正序排序（首次访问在前）
     */
    public static function getOnlineSessions(array $tokens = [])
    {
        // 确保 select 的字段顺序一致
        $columns = [
            'visitor_token',
            'nickname',
            'is_online',
            'connected_at',
            'disconnected_at',
            'client_id',
            'ip',
            'location',
            'served_at',
            'user_agent',
            'first_message_at',
            'last_message_at',
            'last_activity_at',
            'extended_until',
            'last_message_scene',
            'served_by_admin',
            'message_count',
            'visit_times',
        ];

        // 获取当前时间戳和7天前的日期字符串
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        // 基础查询构建器（包含共同的 select 字段和 whereIn 过滤）
        $baseQuery = function () use ($columns, $tokens) {
            $query = self::query()->select($columns);
            if (!empty($tokens)) {
                $query->whereIn('visitor_token', $tokens);
            }
            return $query;
        };

        // --- 子查询 1: 在线访客 ---
        // 条件: is_online = 1
        $queryOnline = $baseQuery()->where('is_online', 1);

        // --- 子查询 2: 离线访客，但过去 7 天内有活跃记录 ---
        // 条件: is_online != 1 AND last_activity_at > 7天前
        $queryRecentActivity = $baseQuery()
            ->where('is_online', '!=', 1)
            ->whereNotNull('last_activity_at') // last_activity_at 必须有值才能比较
            ->where('last_activity_at', '>', $sevenDaysAgo);

        // --- 子查询 3: 离线访客，7天内没有活跃记录，但过去 7 天内有连接记录 ---
        // 条件: is_online != 1 AND (last_activity_at IS NULL OR last_activity_at <= 7天前) AND connected_at > 7天前
        $queryRecentConnected = $baseQuery()
            ->where('is_online', '!=', 1)
            ->whereNotNull('connected_at') // connected_at 必须有值才能比较
            ->where(function ($query) use ($sevenDaysAgo) {
                $query->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<=', $sevenDaysAgo);
            })
            ->where('connected_at', '>', $sevenDaysAgo);

        // --- 使用 UNION ALL 合并所有子查询 ---
        // 注意：unionAll 默认不会去重，因此需要确保子查询的条件是互斥的，以避免重复记录。
        // 当前的 WHERE 逻辑已设计为互斥，保证性能。
        $finalQuery = $queryOnline
            ->unionAll($queryRecentActivity)
            ->unionAll($queryRecentConnected);

        // Eloquent 的 unionAll 返回的是一个 Illuminate\Database\Query\Builder 实例
        // 需要在最终的查询对象上应用排序和限制
        $finalQuery = $finalQuery->orderBy('visit_times', 'asc')
            ->orderBy('connected_at', 'desc');

        if (empty($tokens)) {
            $finalQuery->limit(10000);
        }

        // 使用 cursor/lazy 逐条遍历，避免一次性加载大量记录
        // 注意：unionAll 返回的 QueryBuilder 不直接支持 Eloquent Model 的 lazy/cursor 方法。
        // 需要先 get() 再手动 Hydrate 或者直接用 DB::raw()。
        // 但如果直接是 QueryBuilder，则其 lazy/cursor 方法是可用的。
        // 如果最终返回的是 Collection，直接遍历即可。
        $sessions = $finalQuery->get(); // 这里执行查询并获取 Collection

        $result = [];
        foreach ($sessions as $session) {
            // 将纯粹的 stdClass 转换为 WsVisitorSession 模型实例
            // 确保后续业务逻辑能正常访问模型属性和方法
            //$modelInstance = (new self())->newFromBuilder((array) $session);
            $result[$session->visitor_token] = $session;
        }
        return $result;
    }

    /**
     * @note 获取访客的历史访问次数（统计所有会话记录）
     * @param string $token 访客token
     * @return int 访问次数
     */
    public static function getVisitCount(string $token): int
    {
        return self::where('visitor_token', $token)->count();
    }

    /**
     * @note 批量获取访客的访问次数
     * @param array $tokens 访客token列表
     * @return array 以 token 为 key，访问次数为 value 的数组
     */
    public static function getVisitCounts(array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $counts = self::selectRaw('visitor_token, COUNT(*) as visit_count')
            ->whereIn('visitor_token', $tokens)
            ->groupBy('visitor_token')
            ->get();

        $result = [];
        foreach ($counts as $row) {
            $result[$row->visitor_token] = (int)$row->visit_count;
        }

        return $result;
    }

    /**
     * @note 获取需要警示的访客（超过指定秒数未接入）
     * @param int $seconds 超时秒数，默认15秒
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsAttention(int $seconds = 15)
    {
        $threshold = date('Y-m-d H:i:s', time() - $seconds);

        return self::where('is_online', 1)
            ->whereNotNull('first_message_at')
            ->where('first_message_at', '<=', $threshold)
            ->whereNull('served_by_admin')
            ->orderBy('first_message_at', 'asc')
            ->get();
    }

    /**
     * @note 获取指定访客的当前会话
     * @param string $token 访客token
     * @return WsVisitorSession|null
     */
    public static function getCurrentSession(string $token): ?self
    {
        return self::where('visitor_token', $token)
            ->where('is_online', 1)
            ->first();
    }

    /**
     * @note 更新最后消息时间并增加计数
     * @param string $token 访客token
     * @param string $scene 消息场景（如 'ai_assist'）
     * @return bool
     */
    public static function updateLastMessage(string $token, string $scene = ''): bool
    {
        $session = self::getCurrentSession($token);

        if (!$session) {
            return false;
        }

        // 如果是首次发消息，记录首次时间
        if (empty($session->first_message_at)) {
            $session->first_message_at = date('Y-m-d H:i:s');
        }

        $session->last_message_at = date('Y-m-d H:i:s');
        $session->last_message_scene = $scene;
        $session->message_count = ($session->message_count ?? 0) + 1;

        return $session->save();
    }

    /**
     * @note 标记客服接入
     * @param string $token 访客token
     * @param int $adminId 客服ID
     * @return bool
     */
    public static function markServed(string $token, int $adminId): bool
    {
        $now = date('Y-m-d H:i:s');
        // 原子性抢占：仅当未被其他客服接入（或已是当前客服）时更新
        return self::where('visitor_token', $token)
                ->where('is_online', 1)
                ->where(function ($query) use ($adminId) {
                    $query->whereNull('served_by_admin')
                        ->orWhere('served_by_admin', $adminId);
                })
                ->update([
                    'served_by_admin' => $adminId,
                    'served_at' => $now,
                    'last_activity_at' => $now,  // 接入时初始化倒计时起点
                ]) > 0;
    }

    /**
     * @note 取消客服接入（挂断）
     * @param string $token 访客token
     * @return bool
     */
    public static function unmarkServed(string $token): bool
    {
        return self::where('visitor_token', $token)
                ->where('is_online', 1)
                ->update([
                    'served_by_admin' => null, //接入的客服的Id清理成null
                    'served_at' => null, //客服接入时间
                    'first_message_at' => null,  // 挂断时重置首次消息时间，避免误触发15秒警示
                ]) > 0;
    }

    /**
     * @note 标记会话离线
     * @param string $token 访客token
     * @return bool
     */
    public static function markOffline(string $token): bool
    {
        return self::where('visitor_token', $token)
                ->where('is_online', 1)
                ->update([
                    'client_id' => null,           // 移除客户端ID，方便下次重新计算
                    'is_online' => 0,              // 下线标记为0
                    'disconnected_at' => date('Y-m-d H:i:s'), // 断线时间
                    'served_by_admin' => null,     // 清除接入的客服ID
                    'served_at' => null,           // 清除接入时间
                    'last_activity_at' => null,    // 清除最后活动时间
                    'extended_until' => null,      // 清除延长时间
                ]) > 0;
    }

    /**
     * @note 创建或更新访客会话（有则更新，无则创建）
     * @param string $token 访客token
     * @param string $ip IP地址
     * @param string $userAgent User Agent
     * @param string $clientId 客户端的Id
     * @return WsVisitorSession|null
     */
    public static function createSession(string $token, string $ip = '', string $userAgent = '', string $clientId = ''): ?self
    {
        try {
            // 查找该 token 的会话记录（一个 token 只有一条记录）
            $session = self::where('visitor_token', $token)->first();

            // 解析 IP 地理位置
            $location = '';
            if ($ip !== '') {
                $location = getCityNameByIp($ip);
                // 如果返回"未知城市"，则置空
                if ($location === '未知城市') {
                    $location = '';
                }
            }

            $now = date('Y-m-d H:i:s');
            if ($session) {
                // 已有记录：更新为在线状态，重置连接时间，累加访问次数
                $session->is_online = 1;
                $session->connected_at = $now;
                $session->disconnected_at = null;
                $session->visit_times = ($session->visit_times ?? 0) + 1;  // 每次连接+1
                $session->message_count = 0;  // 重置消息计数
                $session->last_activity_at = $now;  // 立即激活活跃时间，避免被误判为僵尸
                $session->extended_until = null;  // 清除延长时间

                // 【修复】只在客服已接入的情况下才重置 first_message_at
                // 如果访客发了消息但还没被接入，重新连接时保留 first_message_at，继续计时警告
                if (!empty($session->served_by_admin)) {
                    // 已被客服接入，重置首次消息时间
                    $session->first_message_at = null;
                }
                // 否则保留 first_message_at，访客刷新页面后继续显示超时警告

                $session->last_message_at = null;  // 重置最后消息时间（本次会话重新计算）
                $session->client_id = $clientId;         //重置客户端的Id（本地会话重新计算）
                if (empty($session->nickname)) {
                    $session->nickname = self::generateUniqueNickname();
                }
                // 更新 IP、location 和 User-Agent（如果提供了新值）
                if ($ip !== '') {
                    $session->ip = $ip;
                    $session->location = $location;
                }
                if ($userAgent !== '') {
                    $session->user_agent = $userAgent;
                }

                $session->save();
                return $session;
            } else {
                // 无记录：创建新会话（首次访问 visit_times=1）
                $res = self::create([
                    'client_id' => $clientId,
                    'visitor_token' => $token,
                    'nickname' => self::generateUniqueNickname(),
                    'ip' => $ip,
                    'location' => $location,
                    'user_agent' => $userAgent,
                    'connected_at' => $now,
                    'is_online' => 1,//更新在线状态为1
                    'message_count' => 0,
                    'visit_times' => 1, //记录访客的次数
                    'unread_count' => 0, // 初始化未读数
                    'last_activity_at' => $now, // 立即激活活跃时间
                    'extended_until' => null,
                ]);
                return $res;
            }
        } catch (\Throwable $e) {
            error_log("创建/更新会话失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @note 批量标记僵尸会话为离线（用于定时任务清理）
     * @param int $inactiveMinutes 非活跃分钟数，默认5分钟
     * @return int 影响的行数
     */
    public static function cleanupZombieSessions(int $inactiveMinutes = 5): int
    {
        $threshold = date('Y-m-d H:i:s', time() - $inactiveMinutes * 60);

        return self::where('is_online', 1)
            ->where('updated_at', '<', $threshold)
            ->update([
                'is_online' => 0,
                'disconnected_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @note 获取访客的历史会话列表
     * @param string $token 访客token
     * @param int $limit 限制数量
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistorySessions(string $token, int $limit = 10)
    {
        return self::where('visitor_token', $token)
            ->orderBy('connected_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * @note 计算未接入时长（秒）
     * @return int 未接入时长（秒），如果已接入或未发消息则返回0
     */
    public function getUnservedDuration(): int
    {
        if (!empty($this->served_by_admin) || empty($this->first_message_at)) {
            return 0;
        }

        $firstMessageTime = strtotime($this->first_message_at);
        return time() - $firstMessageTime;
    }

    /**
     * @note 计算停留时长（秒）
     * @return int 停留时长（秒）
     */
    public function getStayDuration(): int
    {
        $connectedTime = strtotime($this->connected_at);

        if ($this->is_online) {
            return time() - $connectedTime;
        }

        if (!empty($this->disconnected_at)) {
            $disconnectedTime = strtotime($this->disconnected_at);
            return $disconnectedTime - $connectedTime;
        }

        return 0;
    }

    /**
     * @note 判断是否需要警示（超过15秒未接入）
     * @param int $seconds 超时秒数，默认15秒
     * @return bool
     */
    public function needsAttention(int $seconds = 15): bool
    {
        return $this->getUnservedDuration() >= $seconds;
    }

    /**
     * @note 更新最后活动时间
     * @param string $token 访客token
     * @return bool
     */
    public static function updateLastActivity(string $token): bool
    {
        if ($token === '') {
            \support\Log::warning('[倒计时] updateLastActivity 失败：token 为空');
            return false;
        }

        $now = date('Y-m-d H:i:s');
        // 心跳/消息到达即视为“在线”，可自愈 is_online 被误标 0 的状态
        $affected = self::where('visitor_token', $token)
            ->update([
                'last_activity_at' => $now,
                'is_online' => 1,
                'disconnected_at' => null,
            ]);

        if ($affected > 0) {
            \support\Log::info("[倒计时] updateLastActivity 成功：token={$token}, last_activity_at={$now}, affected={$affected}");
        } else {
            \support\Log::warning("[倒计时] updateLastActivity 失败：token={$token}, 没有匹配的在线记录");
        }

        return $affected > 0;
    }

    /**
     * @note 获取超时的访客会话（用于自动挂断）
     * @param int $timeoutSeconds 超时秒数，默认使用配置值
     * @return array
     */
    public static function getIdleSessions(int $timeoutSeconds = null): array
    {
        // 如果未指定超时时间，使用配置的默认值
        if ($timeoutSeconds === null) {
            //默认是120秒
            $timeoutSeconds = (int)config('business.chat.visitor_idle_timeout', 120);
        }

        $threshold = date('Y-m-d H:i:s', time() - $timeoutSeconds);
        $now = date('Y-m-d H:i:s');

        return self::where('is_online', 1)
            ->whereNotNull('served_by_admin')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<', $threshold)
            ->where(function ($query) use ($now) {
                // 未延长 或 延长已过期
                $query->whereNull('extended_until')
                    ->orWhere('extended_until', '<', $now);
            })
            ->get()
            ->toArray();
    }

    /**
     * @note 延长等待时间（叠加到当前剩余倒计时）
     * @param string $token 访客token
     * @param int $minutes 延长分钟数，默认使用配置值
     * @return bool
     */
    public static function extendWaitTime(string $token, int $minutes = null): bool
    {
        if ($token === '') {
            return false;
        }

        // 如果未指定延长时间，使用配置的默认值
        if ($minutes === null) {
            $minutes = (int)config('business.chat.extend_wait_minutes', 2);
        }

        if ($minutes <= 0) {
            return false;
        }

        // 查询当前会话记录
        $session = self::where('visitor_token', $token)
            ->where('is_online', 1)
            ->whereNotNull('served_by_admin')
            ->first();

        if (!$session) {
            return false;
        }

        $now = time();
        $lastActivityAt = $session->last_activity_at ? strtotime($session->last_activity_at) : $now;
        $currentExtendedUntil = $session->extended_until ? strtotime($session->extended_until) : null;

        // 计算当前有效的起始时间点（如果已延长过，从延长时间开始）
        $effectiveStartTime = ($currentExtendedUntil && $currentExtendedUntil > $lastActivityAt)
            ? $currentExtendedUntil
            : $lastActivityAt;

        // 计算新的延长截止时间：在当前有效时间基础上叠加
        $timeout = (int)config('business.chat.visitor_idle_timeout', 120);
        $currentExpireTime = $effectiveStartTime + $timeout;

        // 叠加延长时间到当前的过期时间
        $newExtendedUntil = $currentExpireTime + $minutes * 60;

        // 将新的延长时间写入 extended_until
        $extendedUntilStr = date('Y-m-d H:i:s', $newExtendedUntil);

        return self::where('visitor_token', $token)
                ->where('is_online', 1)
                ->whereNotNull('served_by_admin')
                ->update(['extended_until' => $extendedUntilStr]) > 0;
    }

    /**
     * @note 标记僵尸访客为离线（用于清理脚本）
     * @param int $timeoutSeconds 超时秒数
     * @return int 清理的行数
     */
    public static function markZombieOffline(int $timeoutSeconds = 60): int
    {
        $threshold = date('Y-m-d H:i:s', time() - $timeoutSeconds);

        return self::where('is_online', 1)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', $threshold);
            })
            ->update([
                'client_id' => null,
                'is_online' => 0, //更新为0表示下线
                'disconnected_at' => date('Y-m-d H:i:s') //更新离线时间
            ]);
    }

    /**
     * @note 获取访客的闲置时长（秒）
     * @return int 闲置时长（秒），如果没有活动时间则返回0
     */
    public function getIdleDuration(): int
    {
        if (empty($this->last_activity_at)) {
            return 0;
        }

        $lastActivityTime = strtotime($this->last_activity_at);
        return time() - $lastActivityTime;
    }

    /**
     * @note 判断是否接近超时（剩余时间少于30秒）
     * @param int $timeoutSeconds 超时秒数，默认120秒
     * @return bool
     */
    public function isNearTimeout(int $timeoutSeconds = 120): bool
    {
        // 如果已延长且未过期，不算接近超时
        if (!empty($this->extended_until)) {
            $extendedTime = strtotime($this->extended_until);
            if ($extendedTime > time()) {
                return false;
            }
        }

        $idleDuration = $this->getIdleDuration();
        $remaining = $timeoutSeconds - $idleDuration;

        return $remaining > 0 && $remaining <= 30;
    }

    /**
     * @note 获取指定客服当前介入的访客列表（以访客表为准）
     * @param int $adminId 客服ID
     * @return array 访客token数组
     */
    public static function getServedVisitorsByAdmin(int $adminId): array
    {

        if ($adminId <= 0) {
            return [];
        }
        return static::query()
            ->where('served_by_admin', $adminId)
            ->where('is_online', 1)
            ->orderBy('served_at', 'desc')
            ->pluck('visitor_token')
            ->toArray();
    }

    /**
     * @note 增加客服未读消息计数
     * @param string $token 访客token
     * @return bool
     */
    public static function incrementUnread(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        return self::where('visitor_token', $token)
                ->increment('unread_count') > 0;
    }

    /**
     * @note 清除客服未读消息计数
     * @param string $token 访客token
     * @return bool
     */
    public static function clearUnread(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        return self::where('visitor_token', $token)
                ->update(['unread_count' => 0]) > 0;
    }

    /**
     * @note 生成唯一访客昵称（格式：访客XXXXXXXX，数字串）
     * @param int $length 数字长度
     * @return string
     */
    protected static function generateUniqueNickname(int $length = 8): string
    {
        $prefix = '访客';
        // 不查询数据库，直接生成高熵数字串，碰撞概率极低
        return $prefix . self::generateRandomDigits($length);
    }

    /**
     * @note 生成纯数字随机串
     * @param int $length 长度
     * @return string
     */
    protected static function generateRandomDigits(int $length = 8): string
    {
        $digits = '0123456789';
        $result = '';
        $maxIndex = strlen($digits) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $digits[random_int(0, $maxIndex)];
        }
        return $result;
    }
}
