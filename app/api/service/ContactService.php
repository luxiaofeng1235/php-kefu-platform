<?php
/*
 *
 * 联系我们服务
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file ContactService.php
 */

namespace app\api\service;

use app\common\basic\BaseService;
use app\model\Contact;

/**
 * 联系我们业务服务
 */
class ContactService extends BaseService
{
    /**
     * 获取联系我们列表
     * @param Request $request 当前请求实例
     * @param array $params 查询参数（page/page_size/limit等）
     * @return array 包含分页信息与联系人列表
     */
    public static function getContactList(array $params = []): array
    {
        [$page, $limit] = self::resolvePagination($params);

        $query = Contact::query()->select(['id', 'username', 'avatar', 'mobile']);
        //构建生产的分页类型信息
        $list = self::buildPaginationResult($query, $page, $limit);
        return $list;
    }
}
