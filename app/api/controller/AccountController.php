<?php
/*
 *
 * 联系我们
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ContactController.php
 */

namespace app\api\controller;

use app\api\service\CustomerService;
use app\common\server\JsonServer;
use support\Request;
use support\Response;

class AccountController
{

    /**
     * @note 获取客服列表
     */
    public function getAccountList(Request $request): Response
    {
        //获取客服账号
        $result = CustomerService::getCustromerAccount();
        return JsonServer::success('联系人列表', $result);
    }


}
