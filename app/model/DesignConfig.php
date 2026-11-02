<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignConfig.php
 * @description  AI的一些基础设置
 */

namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

class DesignConfig extends Model
{
    protected $table = 'design_config';

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