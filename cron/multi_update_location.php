<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file multi_update_location.php
 * @description  批量更新表里的IP的归属地
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../support/bootstrap.php';
// 确保加载 mysql_class (虽然 bootstrap 可能加载了，但显式加载更安全)
require_once __DIR__ . '/../support/mysql_class.php';

$db = new mysql_class();
//更新对应的IP地址的所在地
$db_master = $db->db_master;
$sql = "select id,visitor_token,ip from wa_ws_visitor_session where location is null or  location =''";
$info = $db->fetchAll($sql, $db_master);
if (!$info) {
    $info = [];
}
echo "total Ip visitor :" . count($info) . PHP_EOL;
if (!empty($info)) {
    foreach ($info as $key => $v) {
        if (!$v['visitor_token'] || !$v['ip']) {
            continue;
        }
        $addressIp = trim($v['ip']);
        //获取对应的IP地址
        $location = getCityNameByIp($addressIp);
        $sql = "update wa_ws_visitor_session set location='{$location}' where visitor_token='{$v['visitor_token']}'";
        $query = $db->query($sql, $db_master);
        if ($query) {
            echo "index = {$key} \t ip = {$addressIp} \t location = {$location} \tsuccess \n ";
        } else {
            echo "index = {$key} \t ip = {$addressIp} \t location = {$location} \terror \n ";
        }
    }
} else {
    echo "no data \r\n";
}
echo "over\r\n";