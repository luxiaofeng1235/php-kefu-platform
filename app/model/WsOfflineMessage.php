<?php
/*
 *
 * WebSocket离线消息存储
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file WsOfflineMessage.php
 */
namespace app\model;

use support\Model;

/**
 * websocket离线消息存储表结构
 */
class WsOfflineMessage extends Model
{
    protected $table = 'ws_offline_message';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * @note 写入一条离线消息记录
     * @param array<string, mixed> $payload 数据
     * @date 2025/03/11
     * @return int
     */
    public static function record(array $payload): int
    {
        $model = new static();
        $model->admin_id = (int)($payload['admin_id'] ?? 0);
        $model->visitor_token = (string)($payload['visitor_token'] ?? '');
        $model->scene = (string)($payload['scene'] ?? '');
        $model->content = (string)($payload['content'] ?? '');
        $model->message_type = (int)($payload['message_type'] ?? 1);
        $extra = $payload['extra'] ?? null;
        $model->extra = empty($extra) ? null : json_encode($extra, JSON_UNESCAPED_UNICODE);
        $model->status = (int)($payload['status'] ?? 0);
        $model->save();

        return (int)$model->getKey();
    }

}
