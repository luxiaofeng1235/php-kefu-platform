<?php
/**
 * Bing 搜索抓取（基于 .b_algo 结构）
 * 提取字段：标题、描述、图片、链接
 * 环境要求：
 *   composer require jaeger/querylist
 */

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
require_once dirname(__FILE__, 2) . '/support/bootstrap.php';

use app\api\service\MarketingService;
use app\common\server\spider\BingSearchServer;
use app\model\WsVisitorSession;
use support\Cache;


$a = [1, 2, 3];
$b = [10, 20, 30];
$sum = array_map(fn($x, $y) => $x + $y, $a, $b);

echo "<pre>";
var_dump($sum);
echo "</pre>";
exit();

//$names = ['alice', 'bob', 'charlie'];
//$upperNames = array_map(function ($name) {
//    return strtoupper($name);
//}, $names); // ✅ 注意这里加了 , $names
//echo "<pre>";
//var_dump($upperNames);
//echo "</pre>";
//exit();

//测试短信验证
$mobile = '15637928033';
$newArr = substr_replace($mobile, '****', 3, 4);
echo "<pre>";
var_dump($newArr);
echo "</pre>";
exit();
$arr = substr($mobile, 3, 4);
echo "<pre>";
var_dump($arr);
echo "</pre>";
exit();
echo "<pre>";
var_dump($string);
echo "</pre>";


exit();

$array = [1, 6, 37, 8, 5];
$list = array_slice($array, 0, 3);
echo "<pre>";
var_dump($list);
echo "</pre>";
exit();

//处理获取的IP的地址信息
$ip = '223.74.201.164';
$res = getCityNameByIp($ip);
echo "<pre>";
var_dump($res);
echo "</pre>";
exit();


$search_data = BingSearchServer::search(['keyword' => '欧冠']);
$info = getCityNameByIp('127.0.0.1');
echo "<pre>";
var_dump($search_data);
var_dump($info);
echo "</pre>";
exit();

$list = MarketingService::getAdsRecommandList();
echo "<pre>";
var_dump($list);
echo "</pre>";
exit();


$res = WsVisitorSession::getOnlineSessions();
echo "<pre>";
var_dump($res);
echo "</pre>";
exit();

$res = BrowserService::getAccessKey();
echo "<pre>";
var_dump($res);
echo "</pre>";
exit();

$aa = WsVisitorSession::updateLastActivity('newvisitor123');
echo "<pre>";
var_dump($aa);
echo "</pre>";
exit();

$ip = getClientIp([]);
echo "<pre>";
var_dump($ip);
echo "</pre>";
exit();
$list = WsVisitorSession::getOnlineSessions();
echo "<pre>";
var_dump($list);
echo "</pre>";
exit();

echo "<pre>";
var_dump(getenv('HTTP_CHUNK_LISTEN'));
echo "</pre>";
exit();

$data = [
    ['name' => 'Alice', 'age' => 30, 'score' => 88],
    ['name' => 'Bob', 'age' => 25, 'score' => 92],
    ['name' => 'Charlie', 'age' => 30, 'score' => 85]
];
$scores = array_column($data, 'score');

array_multisort($scores, SORT_DESC, $data);
echo "<pre>";
var_dump($data);
echo "</pre>";
exit();


echo "<pre>";
var_dump($data);
echo "</pre>";
exit();

$key = 'data123';
Cache::set($key, 'red12345');
$a = Cache::get($key);
echo "当前的 key = 【{$key}】 \r\n";
echo "<pre>";
var_dump($a);
echo "</pre>";
exit();


$keyword = isset($argv[1]) ? trim($argv[1]) : null;
$page = isset($argv[2]) ? intval($argv[2]) : 1;
if (empty($keyword)) {
    echo "请输入keyword的搜索参数\r\n";
    exit;
}
$param['keyword'] = $keyword;
$param['pageNo'] = $page;
//搜索处理
$list = BingSearchServer::search($param);
echo "<pre>";
print_r($list);
echo "</pre>";
exit();