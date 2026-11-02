<?php
// 禁用输出缓冲，确保实时输出111
if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // 禁用 Nginx 缓冲（如果使用 Nginx）

// 设置 API 密钥和参数
$api_key = 'xxx'; // ⚠️ 注意：不要在生产环境硬编码密钥！
$url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';

$data = [
    'model' => 'qwen-max',
    'stream' => true,
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => '你都能执行什么任务']
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // 关键：不返回整个结果
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// 实时输出 chunk
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    // DashScope 流式响应是 SSE 格式：data: {...}\n\n
    echo $chunk;
    flush(); // 强制输出到浏览器
    ob_flush(); // 清空 PHP 输出缓冲
    return strlen($chunk);
});

// 执行请求
curl_exec($ch);

if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
}

curl_close($ch);
?>