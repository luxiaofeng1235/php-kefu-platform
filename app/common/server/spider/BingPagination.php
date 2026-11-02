<?php
namespace app\common\server\spider;
use InvalidArgumentException;

/**
 * Bing搜索分页参数生成工具
 * 功能：根据页码页码计算Bing搜索的分页参数（first、FORM、FPIG等），生成完整搜索链接
 */
class BingPagination {
    // 基础参数（从原始链接提取的固定参数）
    private static  $baseParams = [
        'q' => '', // 搜索关键词“欧冠联赛”的URL编码
        'rdr' => '2%2c1',
        'rdrig' => '3A9E9AE6C3844774B9A10E66E0462E0F',
        'ajaxserp' => '0',
        'setlang' => 'zh-Hans',
        'setmkt' => 'zh-CN',
        'cc' => 'CN',
    ];
    // 基础URL
    private static $baseUrl = 'https://cn.bing.com/search';
    // 初始FPIG参数（第一页的FPIG）
    private static $initialFPIG = 'CF4DCE5AA0544BEFA9302554325B4455';
    // 后续页追加的FPIG列表（根据你的链接规律补充）
    private  static $additionalFPIGs = [
        '9B8408B3FDAF4A34B2C4505FBA099CC4',   // 第二页及以后追加
        'D4715017C7DD4C378F5F8C708E9E351D'    // 第四页及以后追加
    ];

    /**
     * 生成指定页码的搜索链接
     * @param int $page 页码（从1开始）
     * @return string 完整的搜索链接
     */
    public static function getPageUrl($page,$keywrod='') {
        if ($page < 1 || empty($keywrod)) {
            throw new InvalidArgumentException("参数无效");
        }

        // 1. 计算first参数（根据你的链接规律：页码与first的对应关系）
        $first =self::calculateFirst($page);

        // 2. 计算FORM参数（PERE + 页码偏移，第一页为PERE，第二页PERE1，以此类推）
        $form = self::calculateForm($page);

        // 3. 计算FPIG参数（累加模式，每增加一页可能追加一个FPIG）
        $fpig = self::calculateFPIG($page);
        self::$baseParams['q'] = $keywrod;
        // 4. 组装所有参数
        $params = self::$baseParams;
        $params['first'] = $first;
        $params['FORM'] = $form;
        if (!empty($fpig)) {
            $params['FPIG'] = $fpig;
        }

        // 5. 拼接URL（参数编码并拼接）
        return self::$baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 计算first参数（当前页起始结果序号）
     * 根据你的链接规律：
     * 第1页：1
     * 第2页：4
     * 第3/4页：14
     * 第5页：34
     * （注：Bing的first值可能因搜索结果类型动态调整，此处按观测值映射）
     */
    private static function calculateFirst($page) {
        $firstMap = [
            1 => 1,
            2 => 4,
            3 => 14,
            4 => 14,
            5 => 34
        ];
        // 若页码超过观测值，按后续规律推测（示例：假设每页递增20）
        if (!isset($firstMap[$page])) {
            return 34 + ($page - 5) * 20;
        }
        return $firstMap[$page];
    }

    /**
     * 计算FORM参数
     * 规律：第1页PERE，第2页PERE1，第3页PERE，第4页PERE2，第5页PERE3...
     */
    private  static function calculateForm($page) {
        if ($page == 1 || $page == 3) {
            return 'PERE';
        }
        // 其他页码：PERE + (页码-2)（第2页PERE1=PERE+1，第4页PERE2=PERE+2，第5页PERE3=PERE+3）
        return 'PERE' . ($page - 2);
    }

    /**
     * 计算FPIG参数（累加模式）
     * 规律：第1页1个，第2页2个，第3页2个，第4页3个，第5页3个...
     */
    private static function calculateFPIG($page) {
        $fpigList = [self::$initialFPIG]; // 初始FPIG
        // 根据页码追加后续FPIG（取前N个）
        $addCount = 0;
        if ($page >= 2) $addCount = 1;
        if ($page >= 4) $addCount = 2;
        // 从additionalFPIGs中取前$addCount个追加
        for ($i = 0; $i < $addCount; $i++) {
            if (isset(self::$additionalFPIGs[$i])) {
                $fpigList[] = self::$additionalFPIGs[$i];
            }
        }
        // 用逗号拼接（Bing中多FPIG用逗号分隔，URL编码后为%2c）
        return implode(',', $fpigList);
    }
}
?>
