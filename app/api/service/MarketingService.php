<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file MarketingService.php
 * @description  营销的业务逻辑层
 */

namespace app\api\service;


use app\common\basic\BaseService;
use app\model\SearchAds;
use plugin\admin\app\model\Option;
use support\Db;

class MarketingService extends BaseService
{
    const C = '1 as image';

    /**
     * @note 随机获取广告的列表数据
     * @param array $params 接收参数
     * @date 2025/11/28
     * @return array|object
     */
    public static function getAdsRecommandList($params = [])
    {
        // 1. 读取后台全局配置
        $optionValue = Option::where('name', 'search_ad_config')->value('value');
        $config = json_decode((string)$optionValue, true) ?? [];

        // 判断全局开关
        if (isset($config['is_enabled_global']) && (int)$config['is_enabled_global'] === 0) {
            return []; // 如果全局开关关闭，直接返回空数组
        }

        // 默认配置
        $defaultRandomCount = $config['random_count'] ?? 1;
        // 2. 确定 limit 和 randomCount


        // 限制上限

        if (isset($params['random_count'])) {
            $randomCount = (int)$params['random_count'];
        } else {
            $randomCount = (int)$defaultRandomCount;
        }

        // 限制逻辑
        if ($randomCount < 1) $randomCount = 1;

        // 3. 关键词处理
        $keyword = trim($params['keyword'] ?? '');
        if (mb_strlen($keyword) > 50) {
            $keyword = mb_substr($keyword, 0, 50);
        }

        $query = SearchAds::query();

        // 4. 指定返回字段并别名
        $query->select([
            'title',
            'url',
            'content as description',
            DB::raw('"" as image')
        ]);
        //搜索关键词兼容
        if (!empty($keyword)) {
            $query->where('title', 'like', "%{$keyword}%");
        }
        //查询启用状态为1的
        $query->where('is_enabled', 1);

        // 6. 调用通用方法 (指定 'RAND' 排序)
        $page = 1; // 推荐列表默认第一页
        $result = self::buildPaginationResult($query, $page, $randomCount, 'RAND');
        return $result['list'] ?? [];
    }
}