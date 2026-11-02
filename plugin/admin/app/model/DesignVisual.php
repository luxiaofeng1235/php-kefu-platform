<?php

namespace plugin\admin\app\model;

use plugin\admin\app\model\Base;

/**
 * @property integer $id ID(主键)
 * @property string $name 名称
 * @property integer $price 价格
 * @property string $url URL地址
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class DesignVisual extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_design_visual';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    
    
}
