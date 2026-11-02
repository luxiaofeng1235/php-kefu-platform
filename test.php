<?php
// 加载 Webman 启动文件（初始化环境）
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/support/bootstrap.php';

use app\api\service\ChatService;

//,{"role":"user","content":"橙汁含有维生素C"},{"role":"user","content":"好喝吗"}
$data['message'] = '[{"role":"user","content":"橙汁"}]';
$info = ChatService::buildStreamPayload($data);
dd($info);

$server = new \app\common\server\spider\BingSearchServer();
$param['keyword'] ='redis 使用';
$param['market'] = 'market';
$param['limit'] =10;
$result = $server::search($param);
echo  '<pre>';
var_dump($result);
echo '</pre>';
exit;