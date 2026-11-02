<?php

namespace plugin\admin\app\model;

use plugin\admin\app\model\Base;

/**
 * @property integer $id ID(主键)
 * @property integer $type 业务类型
 * @property string $name 名称
 * @property string $url 视频地址
 * @property integer $price 价格
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class DesignWebsite extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_design_website';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    
    
}
