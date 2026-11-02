<?php
/*
 *
 * AI 工业园区模型
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignIndustrial.php
 */
namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

/**
 * AI 工业园区模型
 */
class DesignIndustrial extends Model
{
    protected $table = 'design_industrial';

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
