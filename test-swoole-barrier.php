<?php
/**
 * 并发请求百度的 Swoole 协程示例（使用 WaitGroup 做“屏障”效果）
 *
 * 用法：
 *   php test-swoole-barrier.php "关键词1" "关键词2"
 * 若未传参，则会使用默认的关键字数组做并发测试。
 */
declare(strict_types=1);

use Swoole\Coroutine as Co;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;

if (!extension_loaded('swoole')) {
    fwrite(STDERR, "Swoole 扩展未启用，无法运行此脚本" . PHP_EOL);
    exit(1);
}

// 启用所有可用的 Hook，保证标准阻塞 IO 也能被协程调度
Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

$keywords = $argv;
array_shift($keywords); // 去掉脚本名
if ($keywords === []) {
    $keywords = [
        'swoole6 协程屏障',
        'swoole coroutine barrier 示例',
        'php 并发请求百度',
    ];
}

echo '🔍 即将并发请求：' . implode(' | ', $keywords) . PHP_EOL;

$start = microtime(true);

Co\run(function () use ($keywords) {
    $wg = new WaitGroup();
    $results = [];

    foreach ($keywords as $index => $keyword) {
        $wg->add();
        go(function () use ($keyword, $index, &$results, $wg) {
            Co::defer(fn() => $wg->done());

            $client = new Client('www.baidu.com', 443, true);
            $client->set([
                'timeout' => 5,
                'ssl_host_name' => 'www.baidu.com',
            ]);
            $client->setHeaders([
                'Host' => 'www.baidu.com',
                'User-Agent' => 'Swoole-Barrier-Demo/6.0',
                'Accept' => '*/*',
                'Connection' => 'close',
            ]);

            $path = '/s?wd=' . rawurlencode($keyword);
            $success = $client->get($path);

            if ($success === false) {
                $results[$index] = [
                    'keyword' => $keyword,
                    'error' => sprintf('请求失败，errCode=%d', $client->errCode),
                ];
            } else {
                $results[$index] = [
                    'keyword' => $keyword,
                    'status' => $client->statusCode,
                    'length' => strlen($client->body ?? ''),
                ];
            }

            $client->close();
        });
    }

    // 等待所有协程完成，超时 10 秒
    $wg->wait(10.0);

    foreach ($results as $info) {
        if (isset($info['error'])) {
            printf("❌ [%s] %s\n", $info['keyword'], $info['error']);
        } else {
            printf("✅ [%s] status=%d body=%d bytes\n",
                $info['keyword'],
                $info['status'],
                $info['length']
            );
        }
    }
});

$duration = microtime(true) - $start;
echo sprintf("⏱️ 总耗时：%.3f 秒%s", $duration, PHP_EOL);
