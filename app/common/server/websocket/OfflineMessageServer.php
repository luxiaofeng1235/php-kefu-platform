<?php
/*
 *
 * WebSocket离线消息服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file OfflineMessageServer.php
 */
namespace app\common\server\websocket;

use app\model\WsOfflineMessage;
use Illuminate\Support\Carbon;

/**
 * WebSocket 离线消息服务
 */
class OfflineMessageServer
{
    public const STATUS_PENDING = 0;
    public const STATUS_SENT = 1;

    /**
     * @note 保存访客发送的离线消息
     * @param int $adminId 目标客服ID
     * @param string $visitorToken 访客标识
     * @param string $content 消息内容
     * @param int $messageType 消息类型
     * @param string $scene 会话场景
     * @param array<string, mixed> $extra 附加信息
     * @date 2025/03/11
     * @throws \InvalidArgumentException
     * @return array<string, mixed>
     */
    public static function storeVisitorMessage(
        int $adminId,
        string $visitorToken,
        string $content,
        int $messageType = 1,
        string $scene = '',
        array $extra = []
    ): array {
        $content = trim($content);
        if ($adminId <= 0 || $visitorToken === '' || $content === '') {
            throw new \InvalidArgumentException('离线消息参数不完整');
        }

        $messageId = WsOfflineMessage::record([
            'admin_id' => $adminId, //客服ID
            'visitor_token' => $visitorToken, //访客标识
            'scene' => $scene, //会话场景
            'content' => $content, //消息内容
            'message_type' => $messageType, //消息类型：1文本 2:图片 3：视频 4：音频
            'extra' => $extra, //扩展信息，附件等
            'status' => self::STATUS_PENDING, //状态：0未补发 1已补发
        ]);
        $record = WsOfflineMessage::query()->find($messageId);
        return $record ? self::formatRecord($record) : [];
    }

    /**
     * @note 拉取客服未补发的离线消息
     * @param int $adminId 客服ID
     * @param string|null $visitorToken 指定访客标识
     * @param int $limit 读取数量
     * @date 2025/03/11
     * @return array<int, array<string, mixed>>
     */
    public static function pullPendingByAdmin(int $adminId, ?string $visitorToken = null, int $limit = 50): array
    {
        if ($adminId <= 0) {
            return [];
        }

        $query = WsOfflineMessage::query()
            ->where('admin_id', $adminId)
            ->where('status', self::STATUS_PENDING)
            ->orderBy('id', 'asc');

        if ($visitorToken !== null && $visitorToken !== '') {
            $query->where('visitor_token', $visitorToken);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = [];
        foreach ($query->get() as $model) {
            $records[] = self::formatRecord($model);
        }

        return $records;
    }

    /**
     * @note 将离线消息标记为已补发
     * @param array<int, int|string> $messageIds 消息主键集合
     * @date 2025/03/11
     * @return int
     */
    public static function markAsSent(array $messageIds): int
    {
        $ids = [];
        foreach ($messageIds as $messageId) {
            $id = (int)$messageId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return 0;
        }

        return WsOfflineMessage::query()
            ->whereIn('id', $ids)
            ->update(['status' => self::STATUS_SENT]);
    }

    /**
     * @note 将模型格式化为输出数组
     * @param WsOfflineMessage $model 模型实例
     * @date 2025/03/11
     * @return array<string, mixed>
     */
    protected static function formatRecord(WsOfflineMessage $model): array
    {
        return [
            'message_id' => (string)$model->id,
            'admin_id' => (int)$model->admin_id,
            'visitor_token' => (string)$model->visitor_token,
            'scene' => (string)$model->scene,
            'content' => (string)$model->content,
            'message_type' => (int)$model->message_type,
            'extra' => self::decodeExtra($model->extra),
            'status' => (int)$model->status,
            'created_at' => self::formatTimestamp($model->created_at),
        ];
    }

    /**
     * @note 解析附加信息
     * @param mixed $value 存储值
     * @date 2025/03/11
     * @return array<string, mixed>
     */
    protected static function decodeExtra(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @note 统一格式化时间
     * @param mixed $timestamp 时间字段
     * @date 2025/03/11
     * @return string
     */
    protected static function formatTimestamp(mixed $timestamp): string
    {
        if ($timestamp instanceof Carbon) {
            return $timestamp->toDateTimeString();
        }

        if (is_string($timestamp) && $timestamp !== '') {
            return $timestamp;
        }

        return date('Y-m-d H:i:s');
    }
}
