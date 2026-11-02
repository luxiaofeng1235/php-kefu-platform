<?php
/*
 * 客服-访客关联模型（独立未读计数）
 */

namespace app\model;

use support\Model;
use support\Db;

class WsAdminVisitorRelation extends Model
{
    protected $table = 'ws_admin_visitor_relation';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'admin_id',
        'visitor_token',
        'unread_count',
        'last_read_at'
    ];

    /**
     * @note 增加指定客服对访客的未读计数
     * @param int $adminId 客服ID
     * @param string $token 访客Token
     * @return bool
     */
    public static function incrementUnread(int $adminId, string $token): bool
    {
        if ($adminId <= 0 || empty($token)) {
            return false;
        }

        // 使用 INSERT ... ON DUPLICATE KEY UPDATE 原子操作
        $sql = "INSERT INTO wa_ws_admin_visitor_relation (admin_id, visitor_token, unread_count, created_at, updated_at) 
                VALUES (?, ?, 1, NOW(), NOW()) 
                ON DUPLICATE KEY UPDATE unread_count = unread_count + 1, updated_at = NOW()";
        
        return Db::statement($sql, [$adminId, $token]);
    }

    /**
     * @note 批量增加未读计数（广播场景）
     * @param array $adminIds 客服ID数组
     * @param string $token 访客Token
     */
    public static function batchIncrementUnread(array $adminIds, string $token): void
    {
        if (empty($adminIds) || empty($token)) {
            return;
        }

        // 简单循环执行（数量不多时性能可接受），也可以优化为批量 INSERT
        foreach ($adminIds as $id) {
            self::incrementUnread((int)$id, $token);
        }
    }

    /**
     * @note 清除指定客服的未读计数
     * @param int $adminId 客服ID
     * @param string $token 访客Token
     * @return bool
     */
    public static function clearUnread(int $adminId, string $token): bool
    {
        return self::where('admin_id', $adminId)
            ->where('visitor_token', $token)
            ->update([
                'unread_count' => 0,
                'last_read_at' => date('Y-m-d H:i:s')
            ]) > 0;
    }

    /**
     * @note 获取指定客服的所有未读计数映射
     * @param int $adminId 客服ID
     * @return array [visitor_token => count]
     */
    public static function getUnreadMap(int $adminId): array
    {
        return self::where('admin_id', $adminId)
            ->where('unread_count', '>', 0)
            ->pluck('unread_count', 'visitor_token')
            ->toArray();
    }
}
