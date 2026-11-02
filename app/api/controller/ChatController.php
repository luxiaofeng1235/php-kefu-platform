<?php
/*
 *
 * Chat 对话流式控制器
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ChatController.php
 */

namespace app\api\controller;

use app\api\service\ChatService;
use app\api\service\CustomerService;
use app\common\server\JsonServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

/**
 * Chat 流式对话控制器
 */
class ChatController
{
    /**
     * @note 校验请求并引导到 SSE 流处理进程
     * @param Request $request 当前请求对象
     * @return Response 返回 307 重定向或错误 JSON
     */
    public function stream(Request $request): Response
    {
        try {
            //解析传输格式内容 兼容json和form-data传输
            $payload = $this->extractRequestPayload($request);
            //传输加载并构建数据
            ChatService::buildStreamPayload($payload);
        } catch (InvalidArgumentException $exception) {
            return JsonServer::error($exception->getMessage());
        }

        $config = config('dashscope');
        $streamBase = (string)($config['stream_base'] ?? '');
        if ($streamBase === '') {
            $scheme = strtolower((string)$request->header('x-forwarded-proto', ''));
            if ($scheme === '') {
                $scheme = strtolower((string)$request->header('x-forwarded-scheme', ''));
            }
            if ($scheme === '') {
                $scheme = strtolower((string)$request->header('x-scheme', ''));
            }
            if ($scheme === '') {
                $scheme = 'http';
            }
            $host = (string)$request->header('x-forwarded-host', $request->header('host', ''));
            if ($host === '') {
                $host = '127.0.0.1';
            }

            if (strpos($host, ':') !== false) {
                $host = explode(':', $host)[0];
            }

            $streamBase = sprintf('%s://%s:8585', $scheme, $host);
        }

        $target = rtrim($streamBase, '/') . $request->path();

        return new Response(307, [
            'Location' => $target,
            'Cache-Control' => 'no-store',
        ], '');
    }

    /**
     * @note 解析流式接口请求参数，兼容 JSON 与表单提交
     * @param Request $request 当前请求对象
     * @return array 返回解析后的参数
     * @throws InvalidArgumentException 当请求数据缺失或格式不合法时抛出
     */
    private function extractRequestPayload(Request $request): array
    {
        $contentType = strtolower((string)$request->header('content-type', ''));
        if ($contentType !== '' && (
                str_contains($contentType, 'multipart/form-data')
                || str_contains($contentType, 'application/x-www-form-urlencoded')
            )) {
            $payload = $request->post();
            if (!is_array($payload) || $payload === []) {
                throw new InvalidArgumentException('缺少请求参数');
            }
            return $payload;
        }

        $rawBody = (string)$request->rawBody();
        if ($rawBody === '') {
            throw new InvalidArgumentException('请求体为空');
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('请求体不是合法的 JSON');
        }

        return $decoded;
    }

    /**
     * @notes 获取聊天记录
     */
    public function history(Request $request): Response
    {
        // 将所有请求参数（query 和 body）都传给 Service
        $result = CustomerService::getChatHistory($request->all());

        if ($result['error'] !== null) {
            return JsonServer::error($result['error']);
        }

        return JsonServer::success('获取成功', [
            'list' => $result['list'],
            'has_more' => $result['has_more']
        ])->withCors('POST, OPTIONS');
    }
}
