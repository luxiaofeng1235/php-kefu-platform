<?php
/*
 *
 * 合作伙伴横幅模型
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignPartnerBanner.php
 */
namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

/**
 * 合作伙伴横幅模型
 */
class DesignPartnerBanner extends Model
{
    protected $table = 'design_partner_banner';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * @note 横幅图片补全域名
     * @return Attribute 返回域名拼装后的图片地址
     */
    protected function image(): Attribute
    {
        return Attribute::make(static function ($value) {
            return appendDomainIfNeeded($value);
        });
    }
}
