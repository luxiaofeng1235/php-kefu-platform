<?php
/*
 *
 * Copyright (c) 2025 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file NewService.php
 * @description 资讯的业务server层
 */

namespace app\api\service;

use app\common\basic\BaseService;
use app\model\Article;

class NewService extends BaseService
{

    const PAGE_SIZE = 7;

    /**
     * @note 获取资讯列表
     * @param int $page 页码（从 1 开始）
     * @param int $limit 每页数量
     * @return array 包含分页信息与列表数据
     */
    public static function getList(array $params): array
    {
        if (!isset($params['page_size'])) {
            $params['page_size'] = self::PAGE_SIZE;
        }
        [$page, $limit] = self::resolvePagination($params);
        $query = Article::query();
        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        // 遍历时将 stdClass 对象转换为数组
        if ($list['list']) {
            foreach ($list['list'] as $k => $v) {
                // 将对象转换为数组
                $item = (array)$v;
                // 正则替换上传图片的 URL
                if (!empty($item['content'])) {
                    $base_url = resolveDomain(); // 获取对应的 URL
                    $content = prependBaseUrlToImgSrc($item['content'], $base_url); // 正则批量替换 src 为当前的 URL 拼接
                    $item['content'] = $content;
                }
                $list['list'][$k] = $item;
            }
        }
        return $list;
    }

    /**
     * @note 获取通用的首页的跳转配置和路由
     * @return array|\think\response\Json
     */
    public static function getGeneralRouter(): array
    {
        $config = config('business');
        $pathList = $config['channel_list'] ?? [];
        return $pathList;
    }
}

