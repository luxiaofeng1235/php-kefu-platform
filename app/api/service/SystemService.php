<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file SystemService.php
 * @description  系统获取配置、首页设置的默认业务层
 */

namespace app\api\service;

use app\common\basic\BaseService;
use app\model\DesignConfig;

class SystemService extends BaseService
{

    /**
     * @note 获取AI培训的导航
     */
    public static function getAiBannerInfo()
    {
        //查询对应的放回的banner基础信息
        $info = DesignConfig::first();
        return self::toArray($info);
    }
}