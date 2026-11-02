<?php
/*
 *
 * 请求辅助类
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file RequestHelper.php
 */

namespace support;

/**
 * 请求辅助类
 * 提供请求相关的通用辅助方法
 */
class RequestHelper
{
    /**
     * @note 收集请求参数
     * @param Request $request 请求实例
     * @return array 参数数组
     */
    public static function collectParams(Request $request): array
    {
        $params = $request->get();
        return is_array($params) ? $params : [];
    }
}
