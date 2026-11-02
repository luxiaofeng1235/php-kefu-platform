<?php

// ///////////////////////////////////////////////////
// Copyright(c) 2021,jiujie
// 日 期：2025/8/12
// 作　者：卢晓峰
// E-mail :51372539@qq.com
// 文件名 :SpiderEnum.php
// 创建时间:14:38
// 编 码：UTF-8
// 摘 要: 爬虫采集的一些必要的enum配置
// ///////////////////////////////////////////////////
namespace app\common\enum;
class SpiderEnum
{

    const STATUS_OPEN = 1;
    const STATUS_CLOSE = 0;

    // Cookie模板 - 用于生成动态Cookie
    const COOKIE_TEMPLATE = [
        'city' => 'su',
        '58home' => 'su',
        '58tj_uuid' => null, // 动态生成
        'new_uv' => '1',
        'utm_source' => '',
        'spm' => '',
        'init_refer' => '',
        'als' => '0',
        'id58' => null, // 动态生成
        'f' => 'n',
        'commontopbar_new_city_info' => '5%7C%E8%8B%8F%E5%B7%9E%7Csu',
        'new_session' => '0',
        'commontopbar_ipcity' => 'su%7C%E8%8B%8F%E5%B7%9E%7C0',
        'wmda_uuid' => null, // 动态生成
        'wmda_new_uuid' => '1',
        'sessionid' => null, // 动态生成
        'fzq_h' => null, // 动态生成
        'cid' => '5',
        'wmda_visited_projects' => '%3B2385390625025%3B' . null, // 动态时间戳
        'JSESSIONID' => null, // 动态生成
        'zhaopin_cid' => '5',
        'fzq_js_infodetailweb' => null // 动态生成
    ];

    //定义2025年主流浏览器User-Agent池
    const USER_AGENT = [
        // 🔥 2025年主流Chrome版本 (桌面端 - 权重60%)
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',

        // 🔥 2025年Edge版本 (权重15%)
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',

        // 🔥 2025年Firefox版本 (权重15%)
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0',

        // 🔥 2025年Safari版本 (权重5%)
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Safari/605.1.15',

        // 🔥 2025年移动端 (权重5% - 降低比例，主要面向桌面端)
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36',
    ];

    // 定义基础HTTP头
    const BASE_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
    ];

    // 定义可选HTTP头
    const OPTIONAL_HEADERS = [
        'DNT' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
    ];

    /**
    * @note 获取客户端的user-agent信息
    *
    * @return  array
    */
    public  static  function getUserAgent()
    {
        return self::USER_AGENT;
    }

    /**
     * @note 获取随机User-Agent
     * @return string
     */
    public static function getRandomUserAgent()
    {
        $userAgents = self::USER_AGENT;
        return $userAgents[array_rand($userAgents)];
    }

    /**
     * @note 获取58同城Cookie - 通用入口方法
     * @param string $strategy 策略: 'pool'|'dynamic'|'smart'
     * @return string
     */
    public static function get58Cookie($strategy = 'smart')
    {
        switch ($strategy) {
            case 'pool':
                return self::getRandomCookie();
            case 'dynamic':
                return self::generateDynamicCookie();
            case 'smart':
            default:
                return self::getSmartCookie();
        }
    }

    /**
     * @note 智能Cookie选择策略 - 根据采集量自动选择
     * @return string
     */
    public static function getSmartCookie()
    {
        // 使用缓存避免频繁切换Cookie
        $cacheKey = 'spider_58_cookie_' . date('YmdH'); // 每小时一个Cookie
        $cachedCookie = \think\facade\Cache::get($cacheKey);
        
        if (!empty($cachedCookie)) {
            return $cachedCookie;
        }
        
        // 获取当前小时，低峰期(0-6时)使用Cookie池，其他时段混合策略
        $hour = (int)date('H');
        $isLowPeak = ($hour >= 0 && $hour <= 6);
        
        if ($isLowPeak) {
            // 凌晨时段：使用Cookie池，稳定性更好（适合每天凌晨60条的场景）
            $selectedCookie = self::getRandomCookie();
        } else {
            // 其他时段：混合策略，70%使用Cookie池，30%使用动态Cookie
            $usePool = (mt_rand(1, 100) <= 70);
            $selectedCookie = $usePool ? self::getRandomCookie() : self::generateDynamicCookie();
        }
        
        // 缓存1小时，避免同一小时内频繁切换
        \think\facade\Cache::set($cacheKey, $selectedCookie, 3600);
        
        return $selectedCookie;
    }

    /**
     * @note 获取随机Cookie（从Cookie池中选择）
     * @return string
     */
    public static function getRandomCookie()
    {
        $cookies = self::COOKIE_POOL;
        return $cookies[array_rand($cookies)];
    }

    /**
     * @note 生成动态Cookie
     * @return string
     */
    public static function generateDynamicCookie()
    {
        $template = self::COOKIE_TEMPLATE;
        $timestamp = time();
        
        // 生成动态值
        $template['58tj_uuid'] = self::generateUUID();
        $template['id58'] = self::generateBase64Id();
        $template['wmda_uuid'] = self::generateRandomHash(32);
        $template['sessionid'] = self::generateUUID();
        $template['fzq_h'] = self::generateFzqHash($timestamp);
        $template['wmda_visited_projects'] = '%3B2385390625025%3B' . ($timestamp * 1000 - rand(100000, 999999));
        $template['JSESSIONID'] = strtoupper(self::generateRandomHash(32));
        $template['fzq_js_infodetailweb'] = self::generateFzqJsHash($timestamp);
        
        // 拼接Cookie字符串
        $cookieParts = [];
        foreach ($template as $key => $value) {
            if ($value !== null && $value !== '') {
                $cookieParts[] = $key . '=' . $value;
            }
        }
        
        return implode('; ', $cookieParts);
    }

    /**
     * 生成UUID格式字符串
     */
    private static function generateUUID()
    {
        return sprintf('%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffffffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }

    /**
     * 生成Base64编码的ID
     */
    private static function generateBase64Id()
    {
        $randomBytes = '';
        for ($i = 0; $i < 16; $i++) {
            $randomBytes .= chr(mt_rand(65, 122)); // A-z范围
        }
        return base64_encode($randomBytes);
    }

    /**
     * 生成随机哈希字符串
     */
    private static function generateRandomHash($length)
    {
        $chars = '0123456789abcdef';
        $hash = '';
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, 15)];
        }
        return $hash;
    }

    /**
     * 生成fzq_h哈希值
     */
    private static function generateFzqHash($timestamp)
    {
        $hash1 = self::generateRandomHash(32);
        $ts = $timestamp * 1000 + rand(100, 999);
        $hash2 = self::generateRandomHash(32);
        $number = rand(10000000000000000000, 99999999999999999999);
        
        return $hash1 . '_' . $ts . '_' . $hash2 . '_' . $number;
    }

    /**
     * 生成fzq_js_infodetailweb哈希值
     */
    private static function generateFzqJsHash($timestamp)
    {
        $hash = self::generateRandomHash(32);
        $ts = $timestamp * 1000 + rand(200, 800);
        $suffix = rand(0, 9);
        
        return $hash . '_' . $ts . '_' . $suffix;
    }

    /**
     * @note 通用Cookie管理方法 - 供其他爬虫复用
     * @param string $site 站点标识 ('58'|'zhipin'|'lagou'等)
     * @param string $strategy 策略 ('pool'|'dynamic'|'smart')
     * @return string
     */
    public static function getCookie($site = '58', $strategy = 'smart')
    {
        switch ($site) {
            case '58':
                return self::get58Cookie($strategy);
            case 'zhipin':
                // 未来可扩展智联招聘Cookie
                return self::getZhipinCookie($strategy);
            case 'lagou':
                // 未来可扩展拉勾网Cookie
                return self::getLagouCookie($strategy);
            default:
                return self::get58Cookie($strategy);
        }
    }

    /**
     * @note 智联招聘Cookie获取（预留接口）
     * @param string $strategy
     * @return string
     */
    private static function getZhipinCookie($strategy = 'smart')
    {
        // TODO: 实现智联招聘Cookie策略
        return '';
    }

    /**
     * @note 拉勾网Cookie获取（预留接口）
     * @param string $strategy
     * @return string
     */
    private static function getLagouCookie($strategy = 'smart')
    {
        // TODO: 实现拉勾网Cookie策略
        return '';
    }

    /**
     * @note Cookie有效性检测
     * @param string $cookie
     * @param string $testUrl 测试URL
     * @return bool
     */
    public static function validateCookie($cookie, $testUrl = 'https://entdict.58.com/enterpriselibrary/enterpriselist')
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5, 'verify' => false]);
            $response = $client->request('GET', $testUrl, [
                'headers' => [
                    'Cookie' => $cookie,
                    'User-Agent' => self::getUserAgent()[0]
                ]
            ]);
            
            $content = $response->getBody()->getContents();
            $data = json_decode($content, true);
            
            // 检查响应是否包含有效数据
            return !empty($data['data']) && is_array($data['data']);
            
        } catch (\Exception $e) {
            return false;
        }
    }


}