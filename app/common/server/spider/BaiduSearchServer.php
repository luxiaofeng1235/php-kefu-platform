<?php
/*
 *
 * 百度搜索服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file BaiduSearchServer.php
 */
namespace app\common\server\spider;

use app\common\enum\SpiderEnum;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\TransferStats;
use QL\QueryList;
use RuntimeException;

/**
 * 百度搜索服务
 */
class BaiduSearchServer
{
    protected const BASE_URL = 'https://www.baidu.com/s';
    protected const MAX_RESULTS = 10;
    protected const DEFAULT_TIMEOUT = 10;
    protected const MAX_ATTEMPTS = 3;
    protected const REQUEST_INTERVAL = 1.5;
    protected const COOKIE_FILENAME = 'baidu_cookies.txt';

    /**
     * @note 调用百度搜索并解析结果
     * @param array $params {
     *     @type string $keyword 搜索关键词
     *     @type int $pageNo 搜索页码，默认 1
     *     @type int $limit 返回结果数量，默认 10
     *     @type int $timeout 请求超时时间（秒），默认 10
     * }
     * @return array{
     *     keyword:string,
     *     page:int,
     *     limit:int,
     *     count:int,
     *     results:array<int, array{title:string,link:string,description:string,image:?string}>
     * }
     */
    public static function search(array $params): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '') {
            throw new RuntimeException('搜索关键词不能为空');
        }

        $page = isset($params['pageNo']) ? (int)$params['pageNo'] : 1;
        if ($page < 1) {
            throw new RuntimeException('搜索页码不能小于 1');
        }

        $limit = isset($params['limit']) ? (int)$params['limit'] : self::MAX_RESULTS;
        if ($limit < 1) {
            throw new RuntimeException('搜索结果数量需大于 0');
        }
        $limit = min($limit, self::MAX_RESULTS);

        $timeout = isset($params['timeout']) ? (int)$params['timeout'] : self::DEFAULT_TIMEOUT;
        if ($timeout < 1) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        $url = self::buildSearchUrl($keyword, $page, $limit);
        $html = self::fetchBaiduHtml($url, $timeout);
        $results = self::parseResults($html, $limit);

        return [
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit,
            'count' => count($results),
            'results' => array_values($results),
        ];
    }

    /**
     * @note 构建百度搜索 URL
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return string
     */
    protected static function buildSearchUrl(string $keyword, int $page, int $limit): string
    {
        $offset = ($page - 1) * $limit;
        $query = [
            'ie' => 'utf-8',
            'wd' => $keyword,
            'pn' => $offset,
        ];

        return self::BASE_URL . '?' . http_build_query($query);
    }

    /**
     * @note 获取百度搜索 HTML 内容
     * @param string $url 请求地址
     * @param int $timeout 超时时间
     * @return string
     */
    protected static function fetchBaiduHtml(string $url, int $timeout): string
    {
        $lastError = 'unknown error';
        $lastCode = 0;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                self::refreshCookieSeed();
                usleep(random_int(2_000_000, 3_500_000));
            }

            $response = self::fetchUrl($url, $timeout);
            if ($response['success'] === false) {
                $lastError = $response['error'] ?? 'unknown error';
                $lastCode = $response['http_code'] ?? 0;
                continue;
            }

            $html = $response['body'] ?? '';
            if (mb_stripos($html, '百度安全验证') !== false || mb_stripos($html, 'verify.baidu.com') !== false) {
                $lastError = 'baidu_security_challenge';
                $lastCode = 200;
                continue;
            }

            return $html;
        }

        throw new RuntimeException(sprintf('百度搜索请求失败：%s (HTTP %d)', $lastError, $lastCode));
    }

    /**
     * @note 发送百度请求
     * @param string $url 请求地址
     * @param int $timeout 超时时间
     * @return array{success:bool, body?:string, error?:string, http_code?:int}
     */
    protected static function fetchUrl(string $url, int $timeout): array
    {
        static $lastRequestAt = 0.0;
        $now = microtime(true);
        if ($lastRequestAt > 0) {
            $interval = $now - $lastRequestAt;
            if ($interval < self::REQUEST_INTERVAL) {
                $delay = (self::REQUEST_INTERVAL - $interval) + (random_int(0, 50) / 1000);
                usleep((int)($delay * 1_000_000));
            }
        }
        $lastRequestAt = microtime(true);

        $userAgent = SpiderEnum::getRandomUserAgent();
        $cookieString = self::generateCookieString();
        $statusCode = null;
        $cookieJar = CookieJar::fromArray([], 'baidu.com');

        try {
            $queryList = QueryList::get($url, null, [
                'timeout' => $timeout,
                'connect_timeout' => 5,
                'allow_redirects' => true,
                'http_errors' => false,
                'verify' => false,
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Referer' => 'https://www.baidu.com/',
                    'Accept-Language' => 'zh-CN,zh;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'sec-ch-ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                    'sec-ch-ua-mobile' => '?0',
                    'sec-ch-ua-platform' => '"Windows"',
                    'Cookie' => $cookieString,
                ],
                'cookies' => $cookieJar,
                'on_stats' => static function (TransferStats $stats) use (&$statusCode): void {
                    $response = $stats->getResponse();
                    if ($response !== null) {
                        $statusCode = $response->getStatusCode();
                    }
                },
            ]);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'http_code' => $statusCode ?? 0,
            ];
        }

        $statusCode ??= 200;
        if ($statusCode >= 300 && $statusCode < 400) {
            return ['success' => false, 'error' => 'redirect detected, possibly blocked', 'http_code' => $statusCode];
        }

        return ['success' => true, 'body' => $queryList->getHtml(), 'http_code' => $statusCode];
    }

    /**
     * @note 解析 HTML 内容为搜索结果
     * @param string $html 搜索结果 HTML
     * @param int $limit 最大数量
     * @return array<int, array{title:string,link:string,description:string,image:?string}>
     */
    protected static function parseResults(string $html, int $limit): array
    {
        if ($html === '') {
            return [];
        }

        $results = [];
        QueryList::html($html)->find('#content_left > div')->map(static function ($node) use (&$results, $limit): void {
            if (count($results) >= $limit) {
                return;
            }

            $ql = QueryList::html($node->html());
            $title = self::cleanText($ql->find('h3 a')->text());
            $link = trim((string)$ql->find('h3 a')->attr('href'));

            if ($title === '' || $link === '') {
                return;
            }

            $description = self::extractDescription($ql);
            $image = trim((string)$ql->find('img')->attr('src'));
            $image = $image === '' ? null : $image;

            $results[] = [
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'image' => $image,
            ];
        })->all();

        return $results;
    }

    /**
     * @note 清理文本
     * @param string $string 原始文本
     * @return string
     */
    protected static function cleanText(string $string): string
    {
        $string = strip_tags($string);
        $string = preg_replace('/\s+/u', ' ', $string ?? '');
        return trim((string)$string);
    }

    /**
     * @note 提取描述信息
     * @param QueryList $ql QueryList 实例
     * @return string
     */
    protected static function extractDescription(QueryList $ql): string
    {
        $selectors = [
            '.summary-text_560AW',
            '.c-abstract',
            '.content-right_3KUgg p',
            '[class*="summary"]',
            '[class*="desc"]',
            '.news-desc',
            '.c-color[role="text"]',
            '.cosc-summary',
        ];

        foreach ($selectors as $selector) {
            $text = self::cleanText($ql->find($selector)->text());
            if ($text !== '') {
                return $text;
            }
        }

        return self::cleanText($ql->find('p')->text());
    }

    /**
     * @note 生成 Cookie 字符串
     * @param bool $fresh 是否强制刷新
     * @return string
     */
    protected static function generateCookieString(bool $fresh = false): string
    {
        $cookie = BaiduSpirderGeneral::baiduCookie($fresh);
        self::seedCookieFile($cookie);
        return $cookie;
    }

    /**
     * @note 刷新 Cookie
     * @return void
     */
    protected static function refreshCookieSeed(): void
    {
        self::generateCookieString(true);
    }

    /**
     * @note 维护 curl 使用的 cookie jar
     * @param string $cookie Cookie 字符串
     * @return void
     */
    protected static function seedCookieFile(string $cookie): void
    {
        $cookiePath = self::ensureCookieFile();

        $cookieLines = [];
        $pairs = explode(';', $cookie);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $cookieLines[] = sprintf(".baidu.com\tTRUE\t/\tFALSE\t0\t%s\t%s", $name, $value);
        }

        $content = "# Netscape HTTP Cookie File\n" . implode("\n", $cookieLines) . "\n";
        file_put_contents($cookiePath, $content, LOCK_EX);
    }

    /**
     * @note 确保 cookie 文件存在
     * @return string
     */
    protected static function ensureCookieFile(): string
    {
        $directory = runtime_path('spider');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建爬虫运行目录');
        }

        $path = $directory . DIRECTORY_SEPARATOR . self::COOKIE_FILENAME;
        if (!file_exists($path)) {
            file_put_contents($path, '', LOCK_EX);
            chmod($path, 0600);
        }

        return $path;
    }
}
