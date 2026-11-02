<?php
/*
 * 客服信息查询服务
 * Copyright (c) 2025 - Jiujie
 * @author xia
 * @version 2.1
 * @file AdminLookupServer.php
 */

namespace app\common\server\websocket;

use app\model\Admins;

/**
 * @note 集中维护客服账号的查询与状态操作
 */
class AdminLookupServer
{

    const IS_KEFU = 1;//是否为客服
    const  KEFU_STATUS = 0; //客服的默认状态 0：开启 1：关闭（后台可以设置）
    const  HOLD_ON = 1;//挂起操作
    const HOLD_OFF = 0; //挂断操作

    /**
     * @note 获取客服账号列表
     * @param array<int, string> $columns 需要返回的字段
     * @date 2025/10/30
     * @return array<int, array<string, mixed>>
     */
    public static function getKefuList(array $columns = ['id', 'username', 'nickname']): array
    {
        try {
            $query = Admins::query()
                ->where('is_kefu', self::IS_KEFU)
                ->where(function ($query) {
                    $query->where('status', self::KEFU_STATUS)
                        ->orWhereNull('status');
                })
                ->orderBy('id', 'asc');
            if ($columns !== []) {
                $query->select($columns);
            }
            $list = $query->get();
        } catch (\Throwable $exception) {
            return [];
        }

        if ($list && method_exists($list, 'toArray')) {
            return (array)$list->toArray();
        }

        return (array)$list;
    }

    /**
     * @note 获取客服账号ID集合
     * @date 2025/10/30
     * @return array<int, int>
     */
    public static function getKefuIds(): array
    {
        try {
            $ids = Admins::query()
                ->where('is_kefu', self::IS_KEFU)
                ->where(function ($query) {
                    $query->where('status', self::KEFU_STATUS)
                        ->orWhereNull('status');
                })
                ->pluck('id')
                ->all();
        } catch (\Throwable $exception) {
            return [];
        }

        $result = [];
        foreach ((array)$ids as $id) {
            $intId = (int)$id;
            if ($intId > 0) {
                $result[$intId] = $intId;
            }
        }

        return $result;
    }

    /**
     * @note 根据ID获取客服信息
     * @date 2025/10/30
     * @param int $adminId 客服ID
     * @return Admins|null
     */
    public static function findById(int $adminId): ?Admins
    {
        if ($adminId <= 0) {
            return null;
        }

        try {
            /** @var Admins|null $admin */
            $admin = Admins::query()
                ->where('id', $adminId)
                ->where(function ($query) {
                    $query->where('status', self::KEFU_STATUS)
                        ->orWhereNull('status');
                })
                ->first();
        } catch (\Throwable $exception) {
            return null;
        }

        return $admin instanceof Admins ? $admin : null;
    }

    // 挂起标志已废弃，多访客接入以访客表 served_by_admin 为准
}
