<?php
/*
 *
 * 文章服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ArticleService.php
 */
namespace app\api\service;

use app\model\Article;
use app\model\File as FileModel;
use Throwable;

/**
 * 文章业务服务
 */
class ArticleService
{
    /**
     * 获取文章详情
     * @param int $id 文章ID
     * @return array|null 返回文章详情数组，未找到时返回 null
     */
    public static function getDetail(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $detail = Article::getArticleInfo($id);
        if (!$detail) {
            return null;
        }

        if (is_object($detail) && method_exists($detail, 'toArray')) {
            return $detail->toArray();
        }

        return is_array($detail) ? $detail : null;
    }

    /**
     * 获取文章分页列表
     * @param int $page 页码（从 1 开始）
     * @param int $limit 每页数量
     * @return array 包含分页信息与列表数据
     */
    public static function getList(int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $query = Article::query()->where('status', 1);
        $total = (clone $query)->count();

        if ($total === 0) {
            return [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'list' => [],
            ];
        }

        $offset = ($page - 1) * $limit;
        $list = $query
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->select(['id', 'title', 'created_at'])
            ->get();

        if (is_object($list) && method_exists($list, 'toArray')) {
            $list = $list->toArray();
        }

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'list' => is_array($list) ? $list : [],
        ];
    }

    /**
     * 创建文件记录
     * @param array $payload 文件信息载荷
     * @return int 成功时返回记录主键，失败返回 0
     */
    public static function createFileRecord(array $payload): int
    {
        try {
            return FileModel::record($payload);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
