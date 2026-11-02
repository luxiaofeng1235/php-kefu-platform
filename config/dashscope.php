<?php
/*
 *
 * 达摩盘 DashScope 配置
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file dashscope.php
 */

return [
    'api_url' => getenv('DASHSCOPE_API_URL') ?: 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
    'api_key' => getenv('DASHSCOPE_API_KEY') ?: '',
    'default_model' => getenv('DASHSCOPE_DEFAULT_MODEL') ?: 'qwen3-omni-flash',
    'default_system_prompt' => getenv('DASHSCOPE_SYSTEM_PROMPT') ?: 'You are a helpful assistant.',
    'default_audio_voice' => getenv('DASHSCOPE_AUDIO_VOICE') ?: 'Cherry',
    'default_audio_format' => getenv('DASHSCOPE_AUDIO_FORMAT') ?: 'wav',
    'request_timeout' => (float)(getenv('DASHSCOPE_TIMEOUT') ?: 120),
    'connect_timeout' => (float)(getenv('DASHSCOPE_CONNECT_TIMEOUT') ?: 10),
    'stream_base' => getenv('CHAT_STREAM_BASE') ?: '',
    'verify_ssl' => (static function () {
        $value = getenv('DASHSCOPE_VERIFY_SSL');
        if ($value === false || $value === null || $value === '') {
            return true;
        }
        $normalized = strtolower((string)$value);
        return !in_array($normalized, ['0', 'false', 'off', 'no'], true);
    })(),
];
