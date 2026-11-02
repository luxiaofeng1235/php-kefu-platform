<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file WsChatHistory.php
 * @date 2025/11/30
 * @description  聊天记录全量存储表
 */

namespace app\model;

use support\Model;

class WsChatHistory extends Model
{
    protected $table = 'ws_chat_history';
    protected $primaryKey = 'id';

    // SQL 中没有 updated_at 字段，禁用它
    const UPDATED_AT = null;

    // 允许批量赋值的字段
    protected $fillable = [
        'msg_id',
        'session_id',
        'direction',
        'visitor_token',
        'admin_id',
        'content',
        'message_type',
        'scene',
        'extra',
        'created_at'
    ];

    // 自动转换 JSON 字段
    protected $casts = [
        'extra' => 'array',
    ];

    /**
     * @note 保存聊天消息到历史记录
     * @param string $msgId 消息唯一ID
     * @param string $sessionId 会话ID（通常是 visitor_token）
     * @param int $direction 消息方向（1: 访客发给客服, 2: 客服发给访客）
     * @param string $visitorToken 访客Token
     * @param int $adminId 客服ID（访客广播消息可传0）
     * @param string $content 消息内容
     * @param int $messageType 消息类型（1:文本）
     * @param string|null $scene 消息场景（ai_assist=AI消息, null=普通消息）
     * @param array $extra 附加信息
     * @return self
     */
    public static function addMessage(
        string $msgId,
        string $sessionId,
        int    $direction,
        string $visitorToken,
        int    $adminId,
        string $content,
        int    $messageType = 1,
        ?string $scene = null,
        array  $extra = []
    ): self
    {
        return self::create([
            'msg_id' => $msgId,
            'session_id' => $sessionId,
            'direction' => $direction,
            'visitor_token' => $visitorToken,
            'admin_id' => $adminId,
            'content' => $content,
            'message_type' => $messageType,
            'scene' => $scene,
            'extra' => $extra,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}