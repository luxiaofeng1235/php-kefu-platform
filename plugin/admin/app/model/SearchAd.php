<?php

namespace plugin\admin\app\model;

use plugin\admin\app\model\Base;

/**
 * @property integer $id (主键)
 * @property string $title 广告标题
 * @property string $content 广告描述（可选）
 * @property string $url 跳转链接，需以 http/https 开头
 * @property integer $is_enabled 是否启用：1=启用，0=禁用
 * @property string $created_at 
 * @property string $updated_at
 */
class SearchAd extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_search_ads';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    
    
}
