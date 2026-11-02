<?php

namespace app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use support\Model;

class SearchAds extends Model
{
    protected $table = 'search_ads';
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