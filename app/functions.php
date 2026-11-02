<?php
/**
 * Here is your custom functions.
 */

// app/functions.php
use ipip\db\Reader;
use support\Log;
use support\Request;

if (!function_exists('getLastSql')) {
    /**
     * 获取最后一次执行的 SQL 字符串
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder 查询构造器实例
     * @return string 格式化后的 SQL 语句
     */
    function getLastSql($builder)
    {
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();
        // 将 ? 替换为 %s，然后用 vsprintf 格式化
        return vsprintf(str_replace('?', '%s', $sql), $bindings);
    }
}

if (!function_exists('prependBaseUrlToImgSrc')) {
    /*
    *
    * @param string $html 原始 HTML 字符串
    * @param string $baseUrl 要添加的基础 URL（例如：https://example.com）
    * @return string 处理后的 HTML 字符串
    */
    function prependBaseUrlToImgSrc($html, $baseUrl)
    {
        // 确保 baseUrl 以 / 结尾（可选，根据你的需求调整）
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }

        // 使用正则匹配所有 img 标签的 src 属性
        $pattern = '/<img\s[^>]*src\s*=\s*["\']([^"\']+)["\'][^>]*>/i';

        $callback = function ($matches) use ($baseUrl) {
            $src = $matches[1];

            // 判断是否为绝对路径（http/https 或 // 开头）
            if (preg_match('#^(https?://|//)#i', $src)) {
                // 是绝对路径，不处理
                return $matches[0];
            }

            // 否则加上 baseUrl
            $newSrc = $baseUrl . ltrim($src, '/'); // 防止重复斜杠
            // 替换原 src 为新 src
            return str_replace($matches[1], htmlspecialchars($newSrc, ENT_QUOTES, 'UTF-8'), $matches[0]);
        };

        return preg_replace_callback($pattern, $callback, $html);
    }
}

//获取网站域名
if (!function_exists('getDomain')) {
    /**
     * 从请求对象中解析域名
     *
     * @param Request $request 当前请求实例
     * @return string 形如 https://example.com 的域名
     */
    function getDomain(Request $request)
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        // 域名和端口
        $host = $request->host();
        $domain = $scheme . '://' . $host;
        return $domain;
    }
}

if (!function_exists('resolveDomain')) {
    /**
     * @note 获取当前可用的域名
     *
     * 优先从 request 中解析，其次读取配置 app.app_url
     *
     * @return string 标准化后的域名，若不可用则返回空字符串
     */
    function resolveDomain(): string
    {
        $domain = '';
        if (function_exists('request')) {
            $req = request();
            if ($req instanceof Request) {
                $domain = getDomain($req);
            }
        }

        if ($domain === '') {
            $domain = rtrim((string)config('app.app_url', ''), '/');
        }

        return $domain === '' ? '' : rtrim($domain, '/');
    }
}

if (!function_exists('appendDomainIfNeeded')) {
    /**
     * @note 为资源路径补全域名
     *
     * @param mixed $value 原始资源路径
     * @param string|null $domain 指定域名，不传则自动解析
     * @return string 补全后的资源地址
     */
    function appendDomainIfNeeded($value, ?string $domain = null): string
    {
        $path = (string)$value;
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $domain = $domain ?? resolveDomain();
        if ($domain === '') {
            return $path;
        }
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('queueCookieForRequest')) {
    /**
     * @note 将 Cookie 写入当前请求连接的响应头
     * @param Request $request 当前请求对象
     * @param string $name Cookie 名称
     * @param string $value Cookie 值
     * @param int $maxAge Cookie 生命周期（秒）
     * @param string $path Cookie 路径
     * @param string $domain Cookie 域名
     * @param bool $secure 是否仅 HTTPS 传输
     * @param bool $httpOnly 是否禁止客户端脚本访问
     * @param string $sameSite SameSite 策略，默认为空
     * @return void
     */
    function queueCookieForRequest(
        Request $request,
        string  $name,
        string  $value,
        int     $maxAge,
        string  $path,
        string  $domain,
        bool    $secure,
        bool    $httpOnly,
        string  $sameSite = ''
    ): void
    {
        $connection = $request->connection;
        if ($connection === null) {
            return;
        }

        $expiresAt = gmdate('D, d-M-Y H:i:s T', time() + $maxAge);

        $segments = [];
        $segments[] = $name . '=' . rawurlencode($value);
        if ($domain !== '') {
            $segments[] = 'Domain=' . $domain;
        }
        if ($maxAge > 0) {
            $segments[] = 'Max-Age=' . $maxAge;
            $segments[] = 'Expires=' . $expiresAt;
        }
        if ($path !== '') {
            $segments[] = 'Path=' . $path;
        }
        if ($secure) {
            $segments[] = 'Secure';
        }
        if ($httpOnly) {
            $segments[] = 'HttpOnly';
        }
        if ($sameSite !== '') {
            $segments[] = 'SameSite=' . $sameSite;
        }

        $cookieHeader = implode('; ', $segments);
        if (!isset($connection->headers['Set-Cookie']) || !is_array($connection->headers['Set-Cookie'])) {
            $connection->headers['Set-Cookie'] = [];
        }

        $connection->headers['Set-Cookie'][] = $cookieHeader;
    }
}

if (!function_exists('replaceBlankString')) {
    /**
     * @note 替换字符过滤空行
     *
     * @param mixed $value 输入字符
     * @return string 去除过滤厚的字符串
     */
    function replaceBlankString($string): string
    {
        if (!$string) return '';
        return trim(preg_replace('/\s+/u', ' ', strip_tags($string)));
    }
}

if (!function_exists('getReqVisitorId')) {
    /**
     * @note 获取访问的唯一 Cookie
     * @param Request $request 当前请求对象
     * @return string 返回 64 位访客标识
     */
    function getReqVisitorId(Request $request): string
    {
        $cookieName = 'websocket_admin'; // 专用 Cookie 名，避免冲突
        $cookieMaxAge = 10 * 365 * 86400; // 10年有效期

        // 检测是否 HTTPS（兼容反向代理）
        $forwardedProto = strtolower((string)$request->header('x-forwarded-proto', ''));
        if ($forwardedProto === '') {
            $forwardedProto = strtolower((string)$request->header('x-forwarded-scheme', ''));
        }
        if ($forwardedProto === '') {
            $forwardedProto = strtolower((string)$request->header('x-scheme', ''));
        }

        $isHttps = $forwardedProto === 'https';

        if (!$isHttps && $request->connection !== null && $request->connection->transport === 'ssl') {
            $isHttps = true;
        }

        if (!$isHttps) {
            $forwardedSsl = strtolower((string)$request->header('x-forwarded-ssl', ''));
            $frontEndHttps = strtolower((string)$request->header('front-end-https', ''));
            $httpsHeader = strtolower((string)$request->header('https', ''));
            if ($forwardedSsl === 'on' || $frontEndHttps === 'on' || $httpsHeader === 'on' || $httpsHeader === '1') {
                $isHttps = true;
            }
        }

        $secure = $isHttps;
        $httponly = true;
        $samesite = 'Lax';
        $path = '/';

        // 获取当前请求的 Host（不含 scheme）
        $host = $request->host();

        // 判断是否为本地开发环境
        if (strpos($host, '127.0.0.1') !== false ||
            strpos($host, 'localhost') !== false ||
            preg_match('/^192\.168\.\d{1,3}\.\d{1,3}$/', $host)) {
            $cookieDomain = ''; // 本地不设置 domain
        } else {
            $cookieDomain = '.jsss999.com'; // 生产环境跨子域
        }

        // 1. 尝试从请求中读取 Cookie
        $id = $request->cookie($cookieName);

        // 2. 如果 Cookie 存在且格式合法，刷新有效期并返回
        if ($id && preg_match('/^[a-f0-9]{64}$/', $id)) {
            queueCookieForRequest($request, $cookieName, $id, $cookieMaxAge, $path, $cookieDomain, $secure, $httponly, $samesite);
            return $id;
        }

        // 3. 否则生成新的 ID 并设置 Cookie
        $newId = bin2hex(random_bytes(32)); // 生成 64 位十六进制字符串
        queueCookieForRequest($request, $cookieName, $newId, $cookieMaxAge, $path, $cookieDomain, $secure, $httponly, $samesite);
        return $newId;
    }

}


if (!function_exists('double_array_exchange_by_field')) {
    /**
     * @note 转换数组按照key返回
     *
     * @param $trips object 转换的对象
     * @param [str] $[field] [<字段>]
     * @return object|array|bool
     */

    function double_array_exchange_by_field($trips, $field = '')
    {
        if (!$trips || !$field)
            return [];
        $itemArr = [];
        foreach ($trips as $v) {
            if (!$v) continue;
            //按照某个key来进行输出入
            $itemArr[$v[$field]] = $v;
        }
        unset($trips);
        return $itemArr;
    }
}

if (!function_exists('array_multisort_by_keys')) {
    /**
     * 多字段多维数组排序
     *
     * @param array &$array
     * @param array $sortRules 格式：['field1' => SORT_ASC, 'field2' => SORT_DESC, ...]
     * @return array |bool
     */
    function array_multisort_by_keys(array &$array, array $sortRules)
    {
        if (empty($sortRules)) {
            return;
        }
        $result = $array;
        $args = [];
        foreach ($sortRules as $key => $order) {
            $column = array_column($array, $key);
            $args[] = $column;
            $args[] = $order;
        }
        $args[] = &$array;
        call_user_func_array('array_multisort', $args);
        return $result;
    }
}


if (!function_exists('buildAdminWebsocketToken')) {
    /**
     * @note 基于后台的ID生成 WebSocket 鉴权令牌
     *
     * 当前实现采用 HMAC-SHA256 生成可预测的签名值，若未配置密钥则回退为简单哈希，
     * 便于后续在不改动调用端的情况下升级为强校验。
     *
     * @param int $adminId 管理员ID
     * @param string|null $secret 自定义密钥，优先级高于配置项
     * @param int $length 返回签名长度，默认64，传32可得到32位十六进制串
     * @return string 用于握手验证的签名串，ID 无效时返回空字符串
     */
    function buildAdminWebsocketToken(int $adminId, ?string $secret = null, int $length = 64): string
    {
        if ($adminId <= 0) {
            return '';
        }

        if ($secret === null) {
            // 默认从 app 配置中读取密钥
            $secret = (string)config('app.ws_admin_secret', '');
        }
        $secret = trim($secret);

        $payload = 'admin:' . $adminId;

        $raw = $secret === ''
            ? hash('sha256', $payload)
            : hash_hmac('sha256', $payload, $secret);

        if ($length >= strlen($raw)) {
            return $raw;
        }

        if ($length <= 0) {
            return '';
        }

        return substr($raw, 0, $length);
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * @note 获取客户端真实IP（考虑代理）
     * @param array|\support\Request|null $source 可选参数：
     *        - array: 服务器变量数组（用于 WebSocket 握手数据）
     *        - Request: webman Request 对象
     *        - null: 使用全局 $_SERVER
     * @return string IP地址
     */
    function get_client_ip($source = null): string
    {
        $server = null;

        // 判断参数类型
        if ($source instanceof \support\Request) {
            // 如果传入的是 Request 对象，使用 getRealIp() 方法
            return $source->getRealIp();
        } elseif (is_array($source)) {
            // 如果传入的是数组，直接使用
            $server = $source;
        } else {
            // 否则使用全局 $_SERVER
            $server = $_SERVER;
        }

        // 优先从 X-Forwarded-For 获取（取第一个IP）
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $server['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            // 验证 IP 合法性（防止伪造）
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 其次检查 X-Real-IP
        if (!empty($server['HTTP_X_REAL_IP']) && filter_var($server['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
            return $server['HTTP_X_REAL_IP'];
        }

        // 再检查 Client-IP
        if (!empty($server['HTTP_CLIENT_IP']) && filter_var($server['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            return $server['HTTP_CLIENT_IP'];
        }

        // 最后使用 REMOTE_ADDR
        return $server['REMOTE_ADDR'] ?? 'unknown';
    }
}

if (!function_exists('getRandomString')) {
    /**
     * @note 获取随机的字符串
     * @param int $length 升成的长度
     * @return string 随机串
     */
    function getRandomString(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($chars);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, $len - 1)];
        }
        return $result;
    }
}
if (!function_exists('getCityNameByIp')) {
    /**
     * @note 从 ipdb 读取城市名称，缺省返回北京市
     */
    function getCityNameByIp(string $ip): string
    {
        $UNKNOWN_CITY_PLACEHOLDER = '未知城市';

        if ($ip === '') {
            return '';
        }
        static $reader = null;
        if ($reader === null) {
            $dbPath = base_path('public/resource/ipv4_china.ipdb');
            if (!is_file($dbPath)) {
                Log::warning("ipdb file missing: {$dbPath}");
                return $UNKNOWN_CITY_PLACEHOLDER;
            }
            try {
                $reader = new Reader($dbPath);
            } catch (\Throwable $e) {
                Log::error('ipdb init failed: ' . $e->getMessage());
                return $UNKNOWN_CITY_PLACEHOLDER;
            }
        }

        try {
            $info = $reader->findMap($ip, 'CN');
        } catch (\Throwable $e) {
            Log::warning('ipdb lookup failed: ' . $e->getMessage());
            // 异常时返回通用缺省值
            return $UNKNOWN_CITY_PLACEHOLDER;
        }

        $cityName = (string)($info['city_name'] ?? '');
        $regionName = (string)($info['region_name'] ?? '');

        // 优先返回 "省·市" 格式
        if ($regionName !== '' && $cityName !== '' && $regionName !== $cityName) {
            return sprintf('%s·%s', $regionName, $cityName);
        } elseif ($cityName !== '') {
            return $cityName;
        } elseif ($regionName !== '') {
            return $regionName;
        }

        return $UNKNOWN_CITY_PLACEHOLDER;
    }
}

if (!function_exists('getRandomString')) {
    /**
     * @note 获取随机的字符串
     * @param int $length 升成的长度
     * @return string 随机串
     */
    function getRandomString(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($chars);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, $len - 1)];
        }
        return $result;
    }
}

/**
 * 安全地清理 HTML，仅保留指定标签，并移除所有属性（防 XSS）
 *
 * @param string $str 输入字符串
 * @param array $allowed_tags 允许的标签列表（不带尖括号，如 ['p', 'br', 'strong']）
 * @return string 清理后的字符串
 */
function safeStripTags($str, $allowed_tags = ['p', 'br', 'strong', 'em', 'u', 'b', 'i'])
{
    if (empty($str)) {
        return '';
    }

    // 1. 先用 strip_tags 移除不允许的标签（只保留白名单）
    $allowed = '<' . implode('><', $allowed_tags) . '>';
    $cleaned = strip_tags($str, $allowed);

    // 2. 移除所有标签内的属性（如 onclick, style, src 等）
    // 匹配 <tag ...> 中的 "..." 或 '...' 或无引号属性，并替换为 <tag>
    $cleaned = preg_replace('/<([a-z][a-z0-9]*)[^>]*>/i', '<$1>', $cleaned);

    // 3. 可选：压缩多余空白（可选）
    // $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

    return $cleaned;
}


