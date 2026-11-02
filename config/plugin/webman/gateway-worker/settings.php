<?php

use Webman\GatewayWorker\Gateway;
use Webman\GatewayWorker\BusinessWorker;
use Webman\GatewayWorker\Register;

$gatewayListen = getenv('WS_GATEWAY_URL') ?: 'websocket://0.0.0.0:7272';
$registerUrl = getenv('WS_REGISTER_URL') ?: '127.0.0.1:1236';
$lanIp = getenv('WS_lANIP') ?: (getenv('WS_lANIP') ?: '127.0.0.1');
$startPort = (int) (getenv('WS_START_PORT') ?: 2300);
$pingInterval = (int) (getenv('WS_PING_INTERVAL') ?: 25);
$pingData = getenv('WS_PING_DATA') ?: '{"type":"ping"}';
$gatewayCount = (int) (getenv('WS_GATEWAY_COUNT') ?: cpu_count());
$workerCount = (int) (getenv('WS_WORKER_COUNT') ?: (cpu_count() * 2));
$workerName = getenv('WS_WORKER_NAME') ?: 'ChatBusinessWorker';

if ($gatewayCount <= 0) {
    $gatewayCount = cpu_count();
}
if ($workerCount <= 0) {
    $workerCount = cpu_count() * 2;
}
if ($startPort <= 0) {
    $startPort = 2300;
}
if ($pingInterval <= 0) {
    $pingInterval = 25;
}

$registerHostPort = $registerUrl;
if (strpos($registerUrl, '://') !== false) {
    $parts = explode('://', $registerUrl, 2);
    $registerHostPort = $parts[1] ?? $registerUrl;
}

return [
    'gateway' => [
        'handler'     => Gateway::class,
        'listen'      => $gatewayListen,
        'count'       => $gatewayCount,
        'reloadable'  => false,
        'constructor' => ['config' => [
            'lanIp'                => $lanIp,
            'startPort'            => $startPort,
            'pingInterval'         => $pingInterval,
            'pingNotResponseLimit' => 2,  // 2次心跳无响应则断开连接（50秒）
            'pingData'             => $pingData,
            'registerAddress'      => $registerHostPort,
            'onConnect'            => function () {},
        ]],
    ],
    'worker' => [
        'handler'     => BusinessWorker::class,
        'count'       => $workerCount,
        'constructor' => ['config' => [
            'eventHandler'    => plugin\webman\gateway\Events::class,
            'name'            => $workerName,
            'registerAddress' => $registerHostPort,
        ]],
    ],
    'register' => [
        'handler'     => Register::class,
        'listen'      => strpos($registerUrl, 'text://') === 0 ? $registerUrl : 'text://' . $registerHostPort,
        'count'       => 1,
        'constructor' => [],
    ],
];
