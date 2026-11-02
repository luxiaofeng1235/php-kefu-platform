<?php
/*
 *
 * SSE 流式推送进程
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file HttpChunk.php
 */

namespace app\process;

use app\api\service\ChatService;
use InvalidArgumentException;
use JsonException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class HttpChunk
{
    public function onMessage(TcpConnection $connection, Request $request)
    {
        // Only handle CORS in local development environment, controlled by .env file
        $isLocalDev = getenv('APP_ENV') === 'local';

        // Handle preflight OPTIONS request for CORS only in local development
        if ($isLocalDev && $request->method() === 'OPTIONS') {
            $headers = [
                'Access-Control-Allow-Origin'      => $request->header('Origin', '*'),
                'Access-Control-Allow-Methods'     => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age'           => '86400',
            ];
            $connection->send(new Response(204, $headers));
            return;
        }


        // 添加调试日志
        error_log('HttpChunk::onMessage called');
        $postSnapshot = $request->post();
        if (!empty($postSnapshot)) {
            error_log('Request payload snapshot: ' . json_encode($postSnapshot, JSON_UNESCAPED_UNICODE));
        }
        error_log('DashScope config: ' . json_encode(config('dashscope'), JSON_UNESCAPED_UNICODE));

        
        try {
            $requestPayload = $this->extractRequestPayload($request);
            //构建 DashScope 的请求体
            $payload = ChatService::buildStreamPayload($requestPayload);
        } catch (InvalidArgumentException $exception) {
            $connection->send(new Response(400, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'code' => 0,
                'msg' => $exception->getMessage(),
                'data' => [],
            ], JSON_UNESCAPED_UNICODE)));
            return;
        }

        $config = (array)config('dashscope');
        $apiUrl = (string)($config['api_url'] ?? '');
        $apiKey = (string)($config['api_key'] ?? '');
        $timeout = (float)($config['request_timeout'] ?? 120);
        $connectTimeout = (float)($config['connect_timeout'] ?? 10);
        $verifySsl = (bool)($config['verify_ssl'] ?? true);

        if ($apiUrl === '' || $apiKey === '') {
            $connection->send(new Response(500, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'code' => 0,
                'msg' => 'DashScope 配置缺失',
                'data' => [],
            ], JSON_UNESCAPED_UNICODE)));
            return;
        }


        $corsHeaders = [
            'Content-Type' => 'text/event-stream',//流式输出
            'Cache-Control' => 'no-cache', //no_cacae
            'Connection' => 'keep-alive',//保持链接
            'X-Accel-Buffering' => 'no',//关闭buffer缓冲
        ];

        if ($isLocalDev) {
            $originHeader = (string)$request->header('origin', '');
            if ($originHeader !== '') {
                $corsHeaders['Access-Control-Allow-Origin'] = $originHeader;
                $corsHeaders['Access-Control-Allow-Credentials'] = 'true';
                $corsHeaders['Access-Control-Allow-Headers'] = (string)$request->header('access-control-request-headers', 'content-type,authorization');
            } else {
                $corsHeaders['Access-Control-Allow-Origin'] = '*';
            }
        }

        $connection->send(new Response(200, $corsHeaders));

        $connectionClosed = false;
        $curlHandle = null;
        $inStream = false;
        $closeCurlAfterExec = false;

        $sendDataLine = static function (string $line) use ($connection): void {
            if ($connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                return;
            }
            $connection->send("data:{$line}\n\n", true);
        };

        $cleanup = static function () use (&$curlHandle, &$connectionClosed, &$closeCurlAfterExec, &$inStream, $connection): void {
            $connectionClosed = true;
            if (!$inStream && (is_resource($curlHandle) || $curlHandle instanceof \CurlHandle)) {
                curl_close($curlHandle);
                $curlHandle = null;
                $closeCurlAfterExec = false;
            } else {
                $closeCurlAfterExec = true;
            }
            if ($connection->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $connection->close();
            }
        };

        $connection->onClose = static function () use (&$connectionClosed): void {
            $connectionClosed = true;
        };

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            error_log("JSON encoding failed");
            $sendDataLine('[ERROR] 请求序列化失败');
            $cleanup();
            return;
        }
        
        error_log("Request body: " . $body);

        $curlHandle = curl_init($apiUrl);
        if ($curlHandle === false) {
            $sendDataLine('[ERROR] 初始化 DashScope 请求失败');
            $cleanup();
            return;
        }

        $responseStatus = 0;
        $rawResponse = '';
        $forwardedBytes = false;

        curl_setopt_array($curlHandle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT => max(1, (int)$timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, (int)$connectTimeout),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $headerLine) use (&$responseStatus): int {
                $trimmed = trim($headerLine);
                if ($trimmed === '') {
                    return strlen($headerLine);
                }
                if (stripos($trimmed, 'HTTP/') === 0) {
                    $parts = explode(' ', $trimmed);
                    if (isset($parts[1])) {
                        $responseStatus = (int)$parts[1];
                    }
                }
                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use (&$rawResponse, &$connectionClosed, $connection, &$forwardedBytes): int {
                if ($connectionClosed || $connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                    return 0;
                }
                if (!$forwardedBytes && strlen($rawResponse) < 8192) {
                    $rawResponse .= $data;
                }
                $forwardedBytes = true;
                $connection->send($data, true);
                return strlen($data);
            },
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function () use (&$connectionClosed): int {
                return $connectionClosed ? 1 : 0;
            },
        ]);

        if (stripos($apiUrl, 'https://') === 0) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, $verifySsl);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        }

        $inStream = true;
        error_log("Before curl_exec");
        $execResult = curl_exec($curlHandle);
        error_log("After curl_exec, result: " . ($execResult ? 'true' : 'false') . ", errno: " . curl_errno($curlHandle) . ", error: " . curl_error($curlHandle));
        $inStream = false;
        $curlErrorNo = curl_errno($curlHandle);
        $curlErrorMessage = curl_error($curlHandle);

        if ($connectionClosed) {
            $cleanup();
            return;
        }

        if ($curlErrorNo !== 0 || $execResult === false) {
            error_log("Curl error occurred: " . $curlErrorMessage);
            $sendDataLine('[ERROR] DashScope 请求失败: ' . $curlErrorMessage);
            $cleanup();
            return;
        }

        $responseStatus = $responseStatus !== 0
            ? $responseStatus
            : (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        
        error_log("Response status: " . $responseStatus);
        error_log("Forwarded bytes: " . ($forwardedBytes ? 'true' : 'false'));
        if (!$forwardedBytes && $rawResponse !== '') {
            error_log("Raw response (truncated): " . $rawResponse);
        }

        if ($responseStatus >= 400 && !$forwardedBytes) {
            $message = 'DashScope 返回异常';
            $decodedError = json_decode($rawResponse, true);
            if (is_array($decodedError)) {
                $message = (string)($decodedError['error']['message'] ?? $decodedError['message'] ?? $message);
            } elseif (is_string($rawResponse) && $rawResponse !== '') {
                $message = $rawResponse;
            }

            $sendDataLine('[ERROR] ' . $message);
        }

        if ($closeCurlAfterExec && (is_resource($curlHandle) || $curlHandle instanceof \CurlHandle)) {
            curl_close($curlHandle);
            $curlHandle = null;
            $closeCurlAfterExec = false;
        } elseif (!$closeCurlAfterExec && (is_resource($curlHandle) || $curlHandle instanceof \CurlHandle)) {
            curl_close($curlHandle);
            $curlHandle = null;
        }
        $cleanup();
    }

    /**
     * @note 解析入口请求参数，兼容 JSON 与表单提交
     * @param Request $request 当前 Workerman 请求实例
     * @return array 返回解析后的参数数组
     * @throws InvalidArgumentException 当请求格式或内容不合法时抛出
     */
    private function extractRequestPayload(Request $request): array
    {
        // Handle GET request from EventSource
        if ($request->method() === 'GET') {
            $inputs = $request->get();
            if (empty($inputs['message'])) {
                throw new InvalidArgumentException('缺少 message 参数');
            }
            return $inputs;
        }

        // Handle POST request
        $contentType = strtolower((string)$request->header('content-type', ''));
        if (str_contains($contentType, 'application/json')) {
            $rawBody = (string)$request->rawBody();
            if ($rawBody === '') {
                throw new InvalidArgumentException('请求体为空');
            }
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('请求体不是合法的 JSON', 0, $exception);
            }
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('请求体不是合法的 JSON');
            }
            return $decoded;
        }

        if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $inputs = $request->post();
            if (!is_array($inputs) || $inputs === []) {
                throw new InvalidArgumentException('缺少请求参数');
            }
            return $inputs;
        }

        throw new InvalidArgumentException('不支持的 Content-Type 或缺少请求参数');
    }
}
