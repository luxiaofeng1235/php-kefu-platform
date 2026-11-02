<?php
/*
 *
 * 联系我们模型
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file Contact.php
 */
namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

/**
 * 联系我们模型
 */
class Contact extends Model
{
    protected $table = 'contact';

    protected $primaryKey = 'id';

    public $timestamps = false;


    /**
     * 头像字段补全域名
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(static function ($value) {
            return appendDomainIfNeeded($value);
        });
    }
}
