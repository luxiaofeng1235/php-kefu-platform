<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file WsAdminOnline.php
 * @description   客服在线状态管理（替代Redis心跳）
 */

namespace app\model;

use Carbon\Carbon;
use support\Model;

class WsAdminOnline extends Model
{
    /**
     * 表名（框架会自动加 wa_ 前缀，实际表名为 wa_ws_admin_online）
     */
    protected $table = 'ws_admin_online';

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
     * @note 获取当前时间（Carbon实例）
     * @return Carbon\Carbon
     */
    private static function now()
    {
        return Carbon::now();
    }

    /**
     * @note 获取N秒前的时间
     * @param int $seconds
     * @return string
     */
    private static function subSeconds(int $seconds): string
    {
        return Carbon::now()->subSeconds($seconds)->toDateTimeString();
    }

    /**
     * @note 获取N分钟前的时间
     * @param int $minutes
     * @return string
     */
    private static function subMinutes(int $minutes): string
    {
        return Carbon::now()->subMinutes($minutes)->toDateTimeString();
    }

    /**
     * @note 客服上线（创建或更新在线记录）
     * @param int $adminId 客服ID
     * @param string $clientId WebSocket客户端ID
     * @param string $token 认证token
     * @param string $nickname 客服昵称
     * @return bool
     */
    public static function markOnline(int $adminId, string $clientId, string $token, string $nickname): bool
    {
        if ($adminId <= 0 || $clientId === '' || $token === '') {
            return false;
        }

        $now = self::now()->toDateTimeString();
        static::query()->updateOrInsert(
            [
                'admin_id' => $adminId, //更新条件
            ],
            [
                'client_id' => $clientId, //客户端的唯一标识
                'admin_id' => $adminId, //客户Id
                'token' => $token, //token信息
                'nickname' => $nickname, //昵称
                'connected_at' => $now, //链接时间
                'last_activity_at' => $now, //最后的活跃时间
                'updated_at' => $now, //更新时间
                'is_online' => 1  // 明确设置为在线状态（会覆盖旧值）
            ]
        );

        return true;
    }

    /**
     * @note 客服下线
     * @param int $admin_id 客服ID
     * @param string $clientId 客户端ID（可选，用于防止多开时误下线）
     * @return int 影响的行数
     */
    public static function markOffline(int $admin_id, string $clientId = ''): int
    {
        if ($admin_id <= 0) {
            return 0;
        }
        $now = self::now()->toDateTimeString();
        
        $query = static::query()
            ->where('admin_id', $admin_id)
            ->where('is_online', 1);

        // 核心修复：只有当数据库里的 client_id 与当前断开的 client_id 一致时，才标记下线。
        // 这意味着如果用户打开了新窗口（数据库已更新为新ID），关闭旧窗口不会导致下线。
        if ($clientId !== '') {
            $query->where('client_id', $clientId);
        }

        return $query->update([
                'client_id' => null,
                'last_activity_at' => null,
                'is_online' => 0,
                'disconnected_at' => $now,
                'updated_at' => $now
            ]);
    }

    /**
     * @note 心跳更新（保持活跃）
     * @param string $clientId 客户端ID
     * @return int 影响的行数
     */
    public static function heartbeat(string $clientId): int
    {
        if ($clientId === '') {
            return 0;
        }
        $now = self::now()->toDateTimeString();
        return static::query()
            ->where('client_id', $clientId)
            ->where('is_online', 1)
            ->update([
                'last_activity_at' => $now,
                'updated_at' => $now
            ]);
    }

    /**
     * @note 检测并清理超时下线的客服
     * @param int $timeoutSeconds 超时时间（秒）
     * @return int 清理的行数
     */
    public static function cleanupIdle(int $timeoutSeconds = 300): int
    {
        $threshold = self::subSeconds($timeoutSeconds);
        $now = self::now()->toDateTimeString();
        return static::query()
            ->where('is_online', 1)
            ->where('last_activity_at', '<', $threshold)
            ->update([
                'client_id' => null,
                'last_activity_at' => null,
                'is_online' => 0,
                'disconnected_at' => $now,
                'updated_at' => $now
            ]);
    }

    /**
     * @note 添加接待的访客（支持1对多）
     * @param int $adminId 客服ID
     * @param string $visitorToken 访客token
     * @return bool 始终返回true，保留兼容
     * @deprecated 自 2025-11-13 废弃，不再使用 visitor_tokens 字段，以访客表 served_by_admin 为准
     */
    public static function addVisitor(int $adminId, string $visitorToken): bool
    {
        // 废弃方法，不再实际操作visitor_tokens，以访客表served_by_admin为准
        return true;
    }

    /**
     * @note 移除接待的访客
     * @param int $adminId 客服ID
     * @param string $visitorToken 访客token
     * @return bool 始终返回true，保留兼容
     * @deprecated 自 2025-11-13 废弃，不再使用 visitor_tokens 字段，以访客表 served_by_admin 为准
     */
    public static function removeVisitor(int $adminId, string $visitorToken): bool
    {
        // 废弃方法，不再实际操作visitor_tokens，以访客表served_by_admin为准
        return true;
    }

    /**
     * @note 获取当前在线客服列表
     * @param int $activeWithinSeconds 活跃时间范围（秒）
     * @return array
     */
    public static function getOnlineAdmins(int $activeWithinSeconds = 30): array
    {
        $threshold = self::subSeconds($activeWithinSeconds);
        return static::query()
            ->where('is_online', 1)
            ->where('last_activity_at', '>', $threshold)
            ->orderBy('last_activity_at', 'desc')
            ->get(['admin_id', 'client_id', 'nickname'])
            ->toArray();
    }

    /**
     * @note 获取指定客服当前接待的访客列表
     * @param int $adminId 客服ID
     * @return array 始终返回空数组，实际调用应改用 WsVisitorSession::getServedVisitorsByAdmin()
     * @deprecated 自 2025-11-13 废弃，不再使用 visitor_tokens 字段，以访客表 served_by_admin 为准
     */
    public static function getServingVisitors(int $adminId): array
    {
        // 废弃方法，以访客表 WsVisitorSession::getServedVisitorsByAdmin() 为准
        return [];
    }

    /**
     * @note 判断客服是否正在接待指定访客
     * @param int $adminId 客服ID
     * @param string $visitorToken 访客token
     * @return bool
     */
    public static function isServingVisitor(int $adminId, string $visitorToken): bool
    {
        $visitors = static::getServingVisitors($adminId);
        return in_array($visitorToken, $visitors);
    }

    /**
     * @note 根据client_id查询客服信息
     * @param string $clientId 客户端ID
     * @return static|null
     */
    public static function findByClientId(string $clientId): ?self
    {
        if ($clientId === '') {
            return null;
        }

        return static::query()
            ->where('client_id', $clientId)
            ->where('is_online', 1)
            ->first();
    }

    /**
     * @note 根据admin_id查询在线记录
     * @param int $adminId 客服ID
     * @return static|null
     */
    public static function findByAdminId(int $adminId): ?self
    {
        if ($adminId <= 0) {
            return null;
        }

        return static::query()
            ->where('admin_id', $adminId)
            ->where('is_online', 1)
            ->first();
    }

    /**
     * @note 查询指定时间段内的活跃客服
     * @param int $adminId 客服ID（可选，0表示所有）
     * @param int $minutes 过去N分钟
     * @return array
     */
    public static function getActiveAdmins(int $adminId = 0, int $minutes = 5): array
    {
        $threshold = self::subMinutes($minutes);
        $query = static::query()
            ->where('is_online', 1)
            ->where('last_activity_at', '>', $threshold);

        if ($adminId > 0) {
            $query->where('admin_id', $adminId);
        }

        return $query->get(['admin_id', 'client_id', 'nickname'])->toArray();
    }
}
