<?php

namespace plugin\admin\app\model;

use plugin\admin\app\model\Base;

/**
 * @property integer $id 主键ID(主键)
 * @property string $owner_name 车主姓名
 * @property string $owner_phone 车主电话
 * @property string $license_plate 车牌号
 * @property string $ev_brand 电车品牌
 * @property string $ev_model 电车型号
 * @property string $owner_birthday 车主出生日期
 * @property string $developer_name 发展人姓名
 * @property string $developer_phone 发展人电话
 * @property string $side_photo_url 贴车贴侧面照URL/路径
 * @property string $side_photo_name 侧面照原始文件名
 * @property string $front_photo_url 带车牌正面照URL/路径
 * @property string $front_photo_name 正面照原始文件名
 * @property string $create_time 登记时间
 * @property string $update_time 更新时间
 */
class CarOwnerRegister extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_car_owner_register';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    
    
}
