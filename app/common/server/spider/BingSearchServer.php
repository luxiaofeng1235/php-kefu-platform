<?php
/*
 *
 * Bing 搜索服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file BingSearchServer.php
 */

namespace app\common\server\spider;

use app\api\service\MarketingService;
use QL\QueryList;
use RuntimeException;

/**
 * Bing 搜索服务
 */
class BingSearchServer
{
    protected const BASE_URL = 'https://www.bing.com/search';
    protected const DEFAULT_TIMEOUT = 10;
    protected const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';
    protected const DEFAULT_LIMIT = 10;//默认10条
    protected const  DEFAULT_PAGE = 1;//默认分页

    /**
     * @note 使用 Bing 搜索指定关键词
     * @param array $params 请求参数集合
     * @return array{
     *     keyword:string,
     *     limit:int,
     *     count:int,
     *     results:array<int, array{title:string,url:string,description:string,image:string}>
     * }
     * @throws RuntimeException
     */
    public static function search(array $params): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '') {
            throw new RuntimeException('搜索关键词不能为空');
        }
        // 解析分页参数，保证不低于 1
        $pageNo = isset($params['pageNo']) ? $params['pageNo'] : self::DEFAULT_PAGE;
        if ($pageNo < 1) {
            throw  new RuntimeException('分页不能为负数');
        }
        // 先构建基础 URL，再结合分页工具生成实际采集地址
        $url = BingPagination::getPageUrl($pageNo, $keyword);
        // 统一整理调用方传入的自定义选项
        $normalizedOptions = self::normalizeOptions($params);
        // 拿到超时时间，未指定时使用默认值
        $timeout = $normalizedOptions['timeout'] ?? self::DEFAULT_TIMEOUT;
        unset($normalizedOptions['timeout']);
        // 选取请求的 UA，默认为内置 UA
        $userAgent = $normalizedOptions['user_agent'] ?? self::DEFAULT_USER_AGENT;
        // 拉取 HTML 内容供后续解析
        $html = self::getPageContent($url, $timeout, $userAgent);        //解析现有对象
        $results = $html === '' ? [] : self::extractResults($html);

        //整理取后台的配置
        $search_ad = MarketingService::getAdsRecommandList();//取后台的配置信息

        if (!$search_ad || empty($search_ad)) {
            $search_ad = [];
        }
        $newResult = $results ?? [];
        //只有第一页的时候显示广告
        if (!$pageNo || $pageNo == 1) {
            //把搜索的广告和对应的结果合并成一个新数组
            $newResult = array_merge_recursive($search_ad, $results);
        }

        return [
            'keyword' => $keyword,
            'count' => count($results),
            'results' => array_values($newResult),
        ];
    }

    /**
     * @note 根据关键词构建 Bing 搜索请求地址
     * @param string $keyword 搜索关键词
     * @param array $options 自定义参数
     * @return string
     */
    protected static function buildSearchUrl(string $keyword, array $options): string
    {
        $query = [
            'q' => $keyword,
        ];


        // 按需附加地域、偏移、时效等参数
        if (!empty($options['market'])) {
            $query['mkt'] = (string)$options['market'];
        }

        if (isset($options['offset'])) {
            $offset = max(0, (int)$options['offset']);
            if ($offset > 0) {
                $query['first'] = $offset + 1;
            }
        }

        if (!empty($options['freshness'])) {
            $query['freshness'] = (string)$options['freshness'];
        }

        return self::BASE_URL . '?' . http_build_query($query);
    }

    /**
     * @note 规范化搜索选项
     * @param array $options 请求参数集合
     * @return array
     */
    protected static function normalizeOptions(array $options): array
    {
        $normalized = [];

        if (isset($options['market'])) {
            // 只记录非空的 market 参数，避免多余请求变量
            $market = trim((string)$options['market']);
            if ($market !== '') {
                $normalized['market'] = $market;
            }
        }

        if (array_key_exists('offset', $options) && $options['offset'] !== '' && $options['offset'] !== null) {
            // 偏移量最小为 0，防止非法参数
            $offset = max(0, (int)$options['offset']);
            $normalized['offset'] = $offset;
        }

        if (isset($options['freshness'])) {
            $freshness = trim((string)$options['freshness']);
            if ($freshness !== '') {
                $normalized['freshness'] = $freshness;
            }
        }

        if (isset($options['timeout'])) {
            $timeout = (int)$options['timeout'];
            if ($timeout > 0) {
                $normalized['timeout'] = $timeout;
            }
        }

        if (isset($options['user_agent'])) {
            $userAgent = trim((string)$options['user_agent']);
            if ($userAgent !== '') {
                $normalized['user_agent'] = $userAgent;
            }
        }

        return $normalized;
    }

    /**
     * @note 规范化搜索结果数量限制
     * @param mixed $limit 原始限制参数
     * @return int
     */
    protected static function normalizeLimit(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return self::DEFAULT_LIMIT;
        }

        // 转为整数并过滤掉负数
        $limit = (int)$limit;
        if ($limit < 0) {
            return 0;
        }

        return $limit;
    }

    /**
     * @note 获取搜索结果页面 HTML
     * @param string $url 请求地址
     * @param int $timeout 超时时间
     * @param string $userAgent 请求 UA
     * @return string
     * @throws RuntimeException
     */
    protected static function getPageContent(string $url, int $timeout, string $userAgent): string
    {
        $effectiveTimeout = max(1, $timeout);
        $spiderConfig = (array)config('spider.bing', []);
        $extraHeaders = [];
        if (isset($spiderConfig['headers']) && is_array($spiderConfig['headers'])) {
            $extraHeaders = $spiderConfig['headers'];
        }
        if (!isset($extraHeaders['User-Agent'])) {
            $extraHeaders['User-Agent'] = $userAgent;
        }

        // 将关联数组转换为 cURL 需要的索引数组格式
        $curlHeaders = [];
        foreach ($extraHeaders as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL 初始化失败');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $effectiveTimeout,
                CURLOPT_CONNECTTIMEOUT => $effectiveTimeout,
                CURLOPT_FOLLOWLOCATION => true, // 允许重定向
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_SSL_VERIFYPEER => false, // 为避免 SSL 证书问题，在爬虫中通常禁用
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => '' // 允许 cURL 处理各种编码，如 gzip, br
            ]);

            $response = curl_exec($ch);
            $curlErrorNo = curl_errno($ch);
            $curlErrorMessage = curl_error($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($curlErrorNo !== 0) {
                throw new RuntimeException('cURL 请求失败: ' . $curlErrorMessage, $curlErrorNo);
            }

            if ($statusCode >= 400) {
                throw new RuntimeException('搜索请求返回异常状态码：' . $statusCode);
            }

            if ($response === false) {
                return '';
            }

            return (string)$response;
        } finally {
            curl_close($ch);
        }
    }

    /**
     * @note 提取搜索结果数据
     * @param string $html 页面 HTML
     * @return array<int, array{title:string,url:string,description:string,image:string}>
     */
    protected static function extractResults(string $html): array
    {
        if ($html === '') {
            return [];
        }
        // 记录已收录链接，避免重复
        $seen = [];
        // 根据 DOM 结构提取标题、链接、描述及图片等字段
        $collection = QueryList::html($html)
            ->rules([
                'title' => ['h2 > a', 'text'], //匹配标题
                'link' => ['h2 > a', 'href'], //链接地址
                'description' => ['div.b_caption p', 'text'], //搜索描述
                'image' => ['div.rms_iac', 'attr(data-src)'], // 修改图片选择规则
            ])
            ->range('li.b_algo')
            ->query()
            ->getData();
        $results = [];
        foreach ($collection->all() as $item) {
            if (!$item)
                continue;
            // 过滤空链接、非 HTTP 链接以及广告链接
            $url = trim((string)($item['link'] ?? ''));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            if (preg_match('#bing\.com/(?:aclk|ck/a)#i', $url)) {
                continue;
            }
            // 去重，避免重复结果
            if (isset($seen[$url])) {
                continue;
            }

            // 标题与描述为空时跳过该条
            $title = self::normalizeText((string)($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            //处理文本的特殊处理
            $description = self::normalizeText((string)($item['description'] ?? ''));
            if ($description === '') {
                //处理文本信息
                $description = self::normalizeText((string)($item['description'] ?? ''));
            }

            // 针对图片字段做多候选尝试，选出合法地址
            $imageCandidates = [
                (string)($item['image'] ?? ''),
            ];
            $image = '';
            foreach ($imageCandidates as $candidate) {
                $normalized = self::normalizeImageUrl($candidate);
                if ($normalized === '') {
                    continue;
                }
                if (!self::isMeaningfulImageUrl($normalized)) {
                    continue;
                }
                $image = $normalized;
                break;
            }

            $seen[$url] = true;
            $results[] = [
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'image' => $image,
            ];
        }

        // 释放 QueryList 内部资源，避免重复调用时内存累积
        QueryList::destructDocuments();

        return $results;
    }

    /**
     * @note 识别图片地址是否有效
     * @param string $url 图片地址
     * @return bool
     */
    protected static function isMeaningfulImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            return false;
        }

        if (preg_match('#/th/id/ODLS\.#i', $path)) {
            return false;
        }

        $query = $parts['query'] ?? '';
        if ($query !== '') {
            parse_str($query, $params);
            $width = isset($params['w']) ? (int)$params['w'] : 0;
            $height = isset($params['h']) ? (int)$params['h'] : 0;
            if (($width > 0 && $width <= 64) && ($height > 0 && $height <= 64)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @note 规范化图片地址
     * @param string $value 原始值
     * @return string
     */
    protected static function normalizeImageUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'data:') === 0) {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        if (str_starts_with($value, '/')) {
            return 'https://www.bing.com' . $value;
        }

        return '';
    }

    /**
     * @note 归一化文本内容
     * @param string $text 原始文本
     * @return string
     */
    protected static function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('html_entity_decode')) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = preg_replace('/[\r\n]+/u', ' ', $text);
        if ($text === null) {
            return '';
        }

        return trim($text);
    }
}
