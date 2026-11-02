<?php
/*
 *
 * 统一的AI工业服务信息
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file SectorService.php
 */

namespace app\api\service;

use app\common\basic\BaseService;
use app\common\enum\FileEnum;
use app\model\ActivityVideo;
use app\model\DesignIndustrial;
use app\model\DesignIntegration;
use app\model\DesignPartnerBanner;
use app\model\DesignPlaform;
use app\model\DesignSetting;
use app\model\DesignThreeDimensional;
use app\model\DesignVisual;
use app\model\DesignVr;
use app\model\DesignWebsite;
use support\Cache;

/**
 * 统一的AI工业服务业务
 */
class SectorService extends BaseService
{
    /**
     * @note 获取 VR 场景分页列表
     * @param array $params 查询参数，包含分页信息
     * @return array 返回分页信息列表
     */
    public static function getVrList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);
        $query = DesignVr::query();
        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        //处理文件类型等字段信息
        $list['list'] = self::withFileTypeForList(is_array($list['list']) ? $list['list'] : []);
        return $list;
    }

    /**
     * @note 获取三维可视化分页列表
     * @param array $params 查询参数
     * @return array 返回分页信息列表
     */
    public static function getVisualList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = DesignVisual::query();

        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        //处理文件类型等字段信息
        $list['list'] = self::withFileTypeForList(is_array($list['list']) ? $list['list'] : []);
        return $list;
    }

    /**
     * @note 获取首页的单视频列表的显示
     * @param array $params 查询参数
     * @return array 返回分页信息列表
     */
    public static function getSingleVideoList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = ActivityVideo::query();
        $type = trim($params['type'] ?? '');
        if (!empty($type)) {
            $type = addslashes($type); //过滤一下
            $query->where('type', $type);
        }
        $list = self::buildPaginationResult($query, $page, $limit);
        if (!empty($list['list'])) {
            //按照对应的key转换数据
            $mapList = double_array_exchange_by_field($list['list'], 'type');
            //按照对应的key进行排序
            $config = config('business');
            $pathList = $config['channel_list'] ?? [];
            $videoList = [];
            if (!empty($pathList)) {
                //这里需要对指定的key来进行处理输出返回按照 1-X来输出，就算后台没有配置也需要显示
                $now_time = date('Y-m-d H:i:s', time());
                $index = 0;
                foreach ($pathList as $val) {
                    $index++;
                    if (isset($mapList[$val['value']])) {
                        //获取对应的获取出来的数据
                        $data = $mapList[$val['value']] ?? [];
                        if ($data['id'] != 0) {
                            $data['id'] = $index;
                        }
                        $videoList[] = $data;
                    } else {
                        $videoList[] = [
                            'id' => $index,//默认为0
                            'type' => $val['value'],
                            'url' => '', //默认的URL为空
                            'create_time' => $now_time,
                            'update_time' => $now_time,
                            'file_type' => FileEnum::VIDEO_TYPE,//默认是视频的格式
                        ];
                    }
                }
            }
            $videoList = array_values($videoList);
            return $videoList;
        }
        return [];
    }

    /**
     * @note 获取三维场景分页列表
     * @param array $params 查询参数
     * @return array 返回分页信息列表
     */
    public static function getThreeDimensionalList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = DesignThreeDimensional::query();

        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        //处理文件类型等字段信息
        $list['list'] = self::withFileTypeForList(is_array($list['list']) ? $list['list'] : []);
        return $list;
    }

    /**
     * @note 获取 AI 定制化平台分页列表
     * @param array $params 查询参数
     * @return array 返回分页信息列表
     */
    public static function getPlatformList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = DesignPlaform::query();

        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        //处理文件类型等字段信息
        $list['list'] = self::withFileTypeForList(is_array($list['list']) ? $list['list'] : []);
        return $list;
    }


    /**
     * @note 获取首页配置信息
     * @param array $params 接受参数（预留扩展）
     * @return array 返回设置详情，未配置时为空数组
     */
    public static function getIndexSetting(array $params): array
    {
        //查询首页设置
        $record = DesignSetting::query()->first();
        if (!$record) {
            return [];
        }
        if (is_object($record) && method_exists($record, 'toArray')) {
            return $record->toArray();
        }

        return is_array($record) ? $record : [];
    }

    /**
     * @note 获取 AI 工业园区详情
     * @param array $params 查询参数（预留扩展）
     * @return array 返回数据详情
     */
    public static function getIndustrial(array $params): array
    {
        $record = DesignIndustrial::query()->orderBy('id', 'desc')->first();

        if (!$record) {
            return [];
        }

        if (is_object($record) && method_exists($record, 'toArray')) {
            return self::withFileType($record->toArray());
        }


        return self::withFileType(is_array($record) ? $record : []);
    }

    /**
     * @note 获取合作伙伴横幅配置
     * @param array $params 查询参数（预留扩展）
     * @return array 返回横幅数据
     */
    public static function getPartnerBanner(array $params): array
    {
        $record = DesignPartnerBanner::query()->orderBy('id', 'desc')->first();

        if (!$record) {
            return [];
        }

        if (is_object($record) && method_exists($record, 'toArray')) {
            return self::withFileType($record->toArray());
        }

        return self::withFileType(is_array($record) ? $record : []);
    }

    /**
     * @note 判断搜索类型是否受支持
     * @param string $type 类型标识
     * @return bool true 表示支持
     */
    public static function supportsSearchType(string $type): bool
    {
        $type = strtolower(trim($type));
        if ($type === '' || $type === 'all') {
            return true;
        }

        return array_key_exists($type, self::designSearchSources());
    }

    /**
     * @note 综合搜索设计资源
     * @param array $params 查询参数
     * @return array 返回分页列表
     */
    public static function searchDesignResources(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $keyword = trim((string)($params['keyword'] ?? ''));
        $type = strtolower(trim((string)($params['type'] ?? 'all')));
        $channelType = array_key_exists('channel_type', $params)
            ? (int)$params['channel_type']
            : null;

        $sources = self::designSearchSources();
        $targetTypes = $type === 'all' ? array_keys($sources) : [$type];

        $results = [];

        foreach ($targetTypes as $sourceKey) {
            if (!array_key_exists($sourceKey, $sources)) {
                continue;
            }

            /** @var \support\Model $modelClass */
            $modelClass = $sources[$sourceKey];
            $query = $modelClass::query();

            if ($keyword !== '') {
                $query->where('name', 'like', '%' . $keyword . '%');
            }

            if ($sourceKey === 'website' && $type === 'website' && $channelType !== null) {
                $query->where('type', $channelType);
            }

            $rows = $query->orderBy('id', 'desc')->get();

            if (is_object($rows) && method_exists($rows, 'toArray')) {
                $rows = $rows->toArray();
            }

            if (!is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $row = (array)$row;
                }

                $rowWithType = self::withFileType($row);

                $results[] = [
                    'id' => $rowWithType['id'] ?? null,
                    'name' => $rowWithType['name'] ?? '',
                    'source' => $sourceKey,
                    'created_at' => $rowWithType['created_at'] ?? null,
                    'data' => $rowWithType,
                    'file_type' => $rowWithType['file_type'],
                ];
            }
        }

        usort($results, static function (array $a, array $b): int {
            $aTime = isset($a['created_at']) && $a['created_at'] !== null ? strtotime((string)$a['created_at']) : 0;
            $bTime = isset($b['created_at']) && $b['created_at'] !== null ? strtotime((string)$b['created_at']) : 0;

            if ($aTime === $bTime) {
                return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
            }

            return $bTime <=> $aTime;
        });

        $total = count($results);
        $offset = ($page - 1) * $limit;
        $paged = $offset > 0 ? array_slice($results, $offset, $limit) : array_slice($results, 0, $limit);
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'list' => $paged,
        ];
    }

    /**
     * @note 获取虚拟一体化-单条数据信息
     * @date 2025/10/21
     * @return array|object |object
     */
    public static function getIntegration()
    {
        // 获取缓存键
        $cacheKey = self::getIntegrationCacheKey();
        // 从缓存中获取判断
        $cachedJson = Cache::get($cacheKey);
        if ($cachedJson) {
            return json_decode($cachedJson, true);
        }

        // 查询数据库
        $record = DesignIntegration::orderBy('id', 'desc')->first();

        if (!$record) {
            $result = [];
        } elseif (is_object($record) && method_exists($record, 'toArray')) {
            $result = self::withFileType($record->toArray());
        } else {
            $result = self::withFileType(is_array($record) ? $record : []);
        }

        // 写入缓存，5分钟过期
        Cache::set($cacheKey, json_encode($result), 300);

        return $result;
    }

    /**
     * @note 获取虚拟一体化缓存键
     * @return string 缓存键名
     */
    private static function getIntegrationCacheKey(): string
    {
        return 'design_integration:latest';
    }

    /**
     * @note 清除虚拟一体化缓存
     * @return bool 清除是否成功
     */
    public static function clearIntegrationCache(): bool
    {
        $cacheKey = self::getIntegrationCacheKey();
        return Cache::del($cacheKey);
    }


    /**
     * @note 获取三维站点分页列表
     * @param array $params 查询参数，需包含 type 字段
     * @return array 返回分页信息列表
     */
    public static function getWebsiteList(array $params): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = DesignWebsite::query();
        if (array_key_exists('type', $params) && $params['type'] !== '' && $params['type'] !== null) {
            $query->where('type', (int)$params['type']);
        }
        //构建查询
        $list = self::buildPaginationResult($query, $page, $limit);
        //处理文件类型等字段信息
        $list['list'] = self::withFileTypeForList(is_array($list['list']) ? $list['list'] : []);
        return $list;

    }


    /**
     * @note 返回搜索来源映射
     * @return array<string, string> 类型与模型类名映射
     */
    private static function designSearchSources(): array
    {
        return [
            'vr' => DesignVr::class,//vr场景
            'website' => DesignWebsite::class,//三维网站/小程序/app
            'visual' => DesignVisual::class,//三维可视化
            'integration' => DesignIntegration::class,//虚拟一体化
            'three-dimensional' => DesignThreeDimensional::class,//三维场景
            'platform' => DesignPlaform::class,//AI平台
            'industrial' => DesignIndustrial::class,//AI工业园区
        ];
    }

    /**
     * @note 为列表中的每项补充文件类型
     * @param array $list 原始数据列表
     * @return array 带有 file_type 字段的列表
     */
    private static function withFileTypeForList(array $list): array
    {
        foreach ($list as $index => $row) {
            if (!is_array($row)) {
                $row = (array)$row;
            }
            $list[$index] = self::withFileType($row);
        }

        return $list;
    }

    /**
     * @note 为单条记录补充文件类型
     * @param array $record 原始记录
     * @return array 带有 file_type 字段的记录
     */
    private static function withFileType(array $record): array
    {
        if ($record === []) {
            return $record;
        }

        $record['file_type'] = self::detectFileType($record);
        return $record;
    }

    /**
     * @note 根据记录内容判断文件类型
     * @param array $row 数据记录
     * @return int 文件类型常量
     */
    private static function detectFileType(array $row): int
    {
        $path = self::resolveMediaPath($row);
        if ($path === '') {
            return FileEnum::IMAGE_TYPE;
        }
        //解析URL的配置的路径信息
        $ext = strtolower((string)pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        }
        //校验是否为视频的扩展
        if (self::isVideoExtension($ext)) {
            return FileEnum::VIDEO_TYPE;
        }

        return FileEnum::IMAGE_TYPE;
    }

    /**
     * @note 解析记录中的媒体路径
     * @param array $row 数据记录
     * @return string 媒体路径
     */
    private static function resolveMediaPath(array $row): string
    {
        $candidates = ['url', 'image'];
        foreach ($candidates as $field) {
            if (!empty($row[$field])) {
                return (string)$row[$field];
            }
        }

        return '';
    }

    /**
     * @note 判断扩展名是否为视频类型
     * @param string $extension 扩展名
     * @return bool true 表示视频类型
     */
    private static function isVideoExtension(string $extension): bool
    {
        if ($extension === '') {
            return false;
        }

        static $videoExtensions = [
            'mp4', 'mov', 'm4v', 'avi', 'wmv', 'flv', 'mkv', 'webm',
            'mpg', 'mpeg', '3gp', 'ts',
        ];

        return in_array($extension, $videoExtensions, true);
    }
}
