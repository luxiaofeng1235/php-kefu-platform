<?php
/**
 * 动态获取百度的header的Cookie的拼装
 * 解析出带后缀的图片URL
 */

namespace app\common\server\spider;

use RuntimeException;

class BaiduSpirderGeneral
{
    protected const H_PS_PSSID_SEEDS = [
        60273, 63141, 64976, 65247, 65315, 65616, 65656, 65677, 65670,
        65713, 65757, 65729, 65393, 65759, 65777, 65805, 65866, 65846,
        65915, 65361, 65925, 65922, 66012, 66087, 66112, 66190, 66204,
        66245, 66337, 66427, 66492, 66516, 66544, 66601, 66710, 66789, 66823,
    ];

    protected const H_WISE_SIDS_SEEDS = [
        110085, 652268, 653268, 656456, 651759, 658259, 660924, 665191, 667430,
        667681, 668893, 669389, 669527, 669680, 669859, 667053, 670037, 670217,
        670231, 670253, 670501, 668657, 670900, 670935, 671095, 671335, 671434,
        671441, 671488, 670305, 671613, 671755, 671769, 671792, 672009, 672024,
        672044, 672041, 671993, 672158, 672265, 672369, 672394, 672232, 672362,
        672438, 672317, 672524, 672547, 669418, 672457, 672455, 672449, 672510,
        672404, 672630, 672778, 672813, 672814, 672876, 672957, 672997, 673025,
        673255, 673297, 673292, 673316, 673282, 673335, 673353, 673361, 673286,
        673395, 673418, 672928, 673474, 673483, 673270, 673172, 673576, 673592,
        673569, 673635, 673648, 673644, 673647,
    ];

    protected const BDRCVFR_SUFFIX = [
        'C0p6oIjvx-c',
        'JnErfANFE8m',
        'x8n0Z8xB3',
        'nHh3W0D43',
        'lp5Nkx1A1',
        'TMIl9GOTmH',
    ];

    /**
     * @note 获取百度的 cookie 串，默认缓存一次避免同一进程内频繁变更
     * @param bool $fresh 是否强制生成新 cookie
     * @return string
     */
    public static function baiduCookie(bool $fresh = false): string
    {
        static $cache = '';

        if ($fresh || $cache === '') {
            // 动态拼装所有 Cookie 字段
            $cache = self::buildDynamicCookie();
        }

        return $cache;
    }

    /**
     * @note 动态生成百度 Cookie
     * @return string
     */
    protected static function buildDynamicCookie(): string
    {
        $timestamp = time();
        $bid = self::randomHex(32);
        $bdid = self::randomHex(32);
        $bdrcvfrKey = self::BDRCVFR_SUFFIX[array_rand(self::BDRCVFR_SUFFIX)];
        $segments = [
            'BIDUPSID' => $bid,
            'PSTM' => (string)$timestamp,
            'BD_UPN' => self::buildBdUpnValue(),
            'BAIDUID' => sprintf('%s:FG=1', $bdid),
            'MAWEBCUID' => 'web_' . self::randomAlphaNumeric(44),
            'BDORZ' => 'B490B5EBF6F3CD402E515D22BCDA1598',
            'BAIDUID_BFESS' => sprintf('%s:FG=1', $bdid),
            'BA_HECTOR' => self::buildHectorValue(),
            'delPer' => '0',
            'BD_CK_SAM' => '1',
            'ZFY' => self::buildZfyValue(),
            'BD_HOME' => '1',
            sprintf('BDRCVFR[%s]', $bdrcvfrKey) => self::randomAlphaNumeric(20, true),
            'BAIDUID_REF' => sprintf('%s:FG=1', $bdid),
            'H_WISE_SIDS_BFESS' => self::buildWiseSids(),
            'PSCBD' => sprintf('16%%3A1%%3A%d', random_int(1, 6)),
            'SE_LAUNCH' => sprintf('5%%3A%d_16%%3A%d%%3A%d', $timestamp, random_int(10000000, 99999999), random_int(1, 5)),
            'PSINO' => (string)random_int(0, 7),
            'ab_sr' => self::buildAbSrValue(),
            'H_PS_PSSID' => self::buildPssidValue(),
            'H_WISE_SIDS' => self::buildWiseSids(),
        ];

        $pairs = [];
        foreach ($segments as $key => $value) {
            $pairs[] = sprintf('%s=%s', $key, $value);
        }

        return implode('; ', $pairs);
    }

    /**
     * @note 构建 H_PS_PSSID 字段
     * @return string
     */
    protected static function buildPssidValue(): string
    {
        $target = random_int(18, 24);
        $pool = self::expandNumericPool(
            self::H_PS_PSSID_SEEDS,
            60000,
            69000,
            $target + random_int(6, 10)
        );
        shuffle($pool);
        $chunks = array_slice($pool, 0, $target);
        return implode('_', $chunks);
    }

    /**
     * @note 构建 H_WISE_SIDS 列表
     * @return string
     */
    protected static function buildWiseSids(): string
    {
        $chunkSize = random_int(60, 90);
        $pool = self::expandNumericPool(
            self::H_WISE_SIDS_SEEDS,
            660000,
            675000,
            $chunkSize + random_int(15, 25)
        );
        shuffle($pool);
        return implode('_', array_slice($pool, 0, $chunkSize));
    }

    /**
     * @note 构建 BA_HECTOR 随机串
     * @return string
     */
    protected static function buildHectorValue(): string
    {
        $segments = [];
        for ($i = 0; $i < 5; $i++) {
            $segments[] = self::randomAlphaNumeric(random_int(6, 8));
        }
        return implode('', $segments);
    }

    /**
     * @note 构建 ZFY 字段
     * @return string
     */
    protected static function buildZfyValue(): string
    {
        return sprintf(
            '%s:%s:%s',
            self::randomAlphaNumeric(12, true),
            self::randomAlphaNumeric(24, true),
            self::randomAlphaNumeric(random_int(1, 3), true)
        );
    }

    /**
     * @note 构建 ab_sr 字段
     * @return string
     */
    protected static function buildAbSrValue(): string
    {
        $payload = base64_encode(random_bytes(random_int(80, 96)));
        return '1.0.1_' . rtrim(strtr($payload, '+/', '_-'), '=');
    }

    /**
     * @note 构建 BD_UPN 字段
     * @return string
     */
    protected static function buildBdUpnValue(): string
    {
        return (string)random_int(10000000, 99999999);
    }

    /**
     * @note 生成随机 16 进制字符串
     * @param int $length 目标长度
     * @return string
     */
    protected static function randomHex(int $length): string
    {
        if ($length <= 0) {
            throw new RuntimeException('Length must be positive');
        }
        $bytes = random_bytes((int)ceil($length / 2));
        return strtoupper(substr(bin2hex($bytes), 0, $length));
    }

    /**
     * @note 生成随机字母数字串
     * @param int $length 目标长度
     * @param bool $allowSymbols 是否允许 `_`、`-`
     * @return string
     */
    protected static function randomAlphaNumeric(int $length, bool $allowSymbols = false): string
    {
        if ($length <= 0) {
            throw new RuntimeException('Length must be positive');
        }

        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($allowSymbols) {
            $characters .= '_-';
        }

        $charLength = strlen($characters);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $charLength - 1)];
        }

        return $result;
    }

    /**
     * @note 基于种子扩展唯一数字池
     * @param array<int, int> $seeds
     * @param int $min
     * @param int $max
     * @param int $target
     * @return array<int, int>
     */
    protected static function expandNumericPool(array $seeds, int $min, int $max, int $target): array
    {
        $pool = $seeds;
        $tries = 0;
        while (count($pool) < $target && $tries < $target * 3) {
            $pool[] = random_int($min, $max);
            $tries++;
        }
        $pool = array_unique($pool);
        return array_values($pool);
    }
}
