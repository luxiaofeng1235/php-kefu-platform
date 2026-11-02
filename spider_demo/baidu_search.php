<?php
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
require_once dirname(__FILE__, 2) . '/support/bootstrap.php';

use app\common\server\spider\BaiduSearchServer;

$keyword = isset($argv[1])  ? trim($argv[1]) : '';
if(!$keyword){
      echo json_encode([
        'error' => '请输入搜索的内容',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
 
$pageNo = 1;
$limit = 10;
try {
    $result = BaiduSearchServer::search([
        'keyword' => $keyword,
        'pageNo' => $pageNo,
        'limit' => $limit,
    ]);
    echo "<pre>";
    var_dump($result);
    exit;

} catch (Throwable $exception) {
     echo json_encode([
        'error' => 'baidu_search_failed',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

 
