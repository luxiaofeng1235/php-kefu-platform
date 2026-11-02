<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file BaseService.php
 * @description  service的基类,一些基础实现可以在这里面进行调用
 */

namespace app\common\basic;

use Illuminate\Database\Eloquent\Builder;

class BaseService
{

    const PAGENO = 1; //默认页码
    const LIMIT = 10;//默认最大分页数

    /**
     * @note 解析分页参数
     * @param array $params 请求参数
     * @return array 返回 [page, limit]
     */
    protected static function resolvePagination(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? self::PAGENO));
        $limit = max(1, (int)($params['page_size'] ?? self::LIMIT));

        return [$page, $limit];
    }

    /**
     * @note 转换对象为数组（支持 Eloquent Collection 和 Model）
     * @param mixed $data 待转换的数据（可能是对象、数组或其他类型）
     * @return array 转换后的数组
     */
    protected static function toArray($data): array
    {
        // 如果是对象且有 toArray 方法，调用转换
        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        // 如果已经是数组，直接返回
        if (is_array($data)) {
            return $data;
        }

        // 其他情况返回空数组
        return [];
    }

    /**
     * @note 构建查询返回结构，支持分页和查全部
     * @param Builder $query 查询构造器
     * @param int $page 当前页码（当 $usePagination 为 false 时此参数无效）
     * @param int $limit 每页数量（当 $usePagination 为 false 时此参数无效）
     * @param string $orderField 排序字段
     * @param string $sort 排序依据
     * @param bool $usePagination 是否使用分页和limit限制（默认true）。如果设为false，将查询所有匹配的数据并直接返回，忽略$page、$limit参数
     * @return array 返回结果（当$usePagination为true时返回分页结构，为false时直接返回列表数组）
     */
    public static function buildPaginationResult(Builder $query, int $page, int $limit, string $orderField = 'id', string $sort = 'desc', bool $usePagination = true): array
    {
        //如果不需要分页，直接返回所有数据
        if (!$usePagination) {
            $queryClone = clone $query;
            if (strtoupper($orderField) === 'RAND') {
                $queryClone->inRandomOrder();
            } else {
                $queryClone->orderBy($orderField, $sort);
            }
            $list = $queryClone->get();

            return self::toArray($list);
        }

        $total = (clone $query)->count();
        //默认查询总数
        if ($total === 0) {
            return [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'list' => [],
            ];
        }
        //查询分页
        $offset = ($page - 1) * $limit;
        $queryClone = clone $query;

        if (strtoupper($orderField) === 'RAND') {
            $queryClone->inRandomOrder();
        } else {
            $queryClone->orderBy($orderField, $sort);
        }

        $list = $queryClone
            ->offset($offset)
            ->limit($limit)
            ->get();
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'list' => self::toArray($list),
        ];
    }
}