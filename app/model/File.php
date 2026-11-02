<?php

namespace app\model;

use support\Model;

/**
 * 文件管理模型
 */
class File extends Model
{
    protected $table = 'file';


    public $timestamps = false;

    /**
     * 写入一条文件记录
     */
    public static function record(array $payload): int
    {
        $model = new static();
        $model->name = $payload['name'] ?? '';
        $model->cid = (int)($payload['cid'] ?? 0);
        $model->type = (int)($payload['type'] ?? 0);
        $model->uri = $payload['uri'] ?? '';
        $model->shop_id = (int)($payload['shop_id'] ?? 0);
        $model->user_id = (int)($payload['user_id'] ?? 0);
        $model->create_time = (int)($payload['create_time'] ?? time());
        $model->del = 0;
        $model->save();
        return (int)$model->getKey();
    }
}
