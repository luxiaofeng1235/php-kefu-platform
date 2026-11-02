<?php
/*
 *
 * AI 定制化模型
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignPlaform.php
 */
namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

/**
 * AI 定制化模型
 */
class DesignPlaform extends Model
{
    protected $table = 'design_platform';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * @note URL 字段补全域名
     * @return Attribute 返回拼装域名的 URL 属性
     */
    protected function url(): Attribute
    {
        return Attribute::make(static function ($value) {
            return appendDomainIfNeeded($value);
        });
    }
}
