<?php
/*
 *
 * Copyright (c) 2025 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file Admins.php
 * @description  后台账户模型-主要处理客服账号
 */
namespace app\model;

use support\Model;

class Admins extends  Model {

    protected  $table = 'admins';

    protected $primaryKey='id';

    public $timestamps = false;
}