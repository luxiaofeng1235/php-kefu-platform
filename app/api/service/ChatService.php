<?php
/*
 *
 * 会话流式对话服务封装
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ChatService.php
 */
namespace app\api\service;

use InvalidArgumentException;

/**
 * 百炼大模型 DashScope 对话服务
 */
class ChatService
{
    /**
     * @note 构建 DashScope 流式请求体
     * @param array $payload 前端提交的原始参数
     * @return array 返回透传后的 payload
     */
    public static function buildStreamPayload(array $payload): array
    {
        $config = config('dashscope');
        $model = (string)($config['default_model'] ?? 'qwen3-omni-flash');
        if ($model === '') {
            $model = 'qwen3-omni-flash';
        }
        //拼装消息
        $messages = self::extractMessages($payload);

        $voice = (string)($config['default_audio_voice'] ?? 'Cherry');
        $audioFormat = (string)($config['default_audio_format'] ?? 'wav');

        return [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'modalities' => ['text', 'audio'],
            'audio' => [
                'voice' => $voice,
                'format' => $audioFormat,
            ],
            'stream_options' => [
                'include_usage' => true,
            ],
        ];
    }

    /**
     * @note 读取前端透传的消息数组，不做结构改写
     * @param array $payload
     * @return array
     */
    private static function extractMessages(array $payload): array
    {
        $messagesRaw = null;
        // 兼容 form-data 与 JSON 请求，优先读取 messages，其次 message，最后尝试顺序数组
        if (array_key_exists('messages', $payload)) {
            $messagesRaw = $payload['messages'];
        } elseif (array_key_exists('message', $payload)) {
            $messagesRaw = $payload['message'];
        } elseif (self::isSequentialArray($payload)) {
            $messagesRaw = $payload;
        } else {
            throw new InvalidArgumentException('消息格式必须为数组，包含 role 和 content 字段');
        }

        if (is_string($messagesRaw)) {
            // form-data 字段可能是 JSON 数组字符串，也可能是单条文本
            $decoded = json_decode($messagesRaw, true);
            if (is_array($decoded)) {
                $messages = $decoded;
            } else {
                $trimmed = trim($messagesRaw);
                if ($trimmed === '') {
                    throw new InvalidArgumentException('消息内容不能为空');
                }
                $messages = [[
                    'role' => 'user',
                    'content' => $trimmed,
                ]];
            }
        } elseif (is_array($messagesRaw)) {
            $messages = $messagesRaw;
        } else {
            throw new InvalidArgumentException('消息格式必须为数组，包含 role 和 content 字段');
        }

        if (!self::isSequentialArray($messages)) {
            $messages = array_values($messages);
        }

        if ($messages === []) {
            throw new InvalidArgumentException('消息数组不能为空');
        }

        foreach ($messages as $index => $item) {
            if (!is_array($item) || !isset($item['role'], $item['content'])) {
                throw new InvalidArgumentException(sprintf('messages.%d 必须包含 role 和 content', $index));
            }
        }

        return $messages;
    }

    /**
     * @note 判断数组是否为顺序数组
     * @param array $array
     * @return bool
     */
    private static function isSequentialArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}
