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

use app\api\service\ContactService;
use app\common\server\JsonServer;
use support\Request;
use support\Response;

class ContactController
{

    /**
     * @note 联系我们
     */
    public function getInfo(Request $request): Response
    {
        $params = $request->get();
        //获取联系人列表
        $result = ContactService::getContactList($params);
        return JsonServer::success('联系人列表', $result);
    }


}
