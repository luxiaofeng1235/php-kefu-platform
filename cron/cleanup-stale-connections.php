<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file cleanup-stale-connections.php
 * @description  在线链接管理清理过期的访客的session链接数据
 * 清理 MySQL 中的僵尸在线状态（客服和访客）- 使用原生 mysql_class
 * 替代 cleanup-stale-connections.php 解决 Model 加载报错问题
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../support/bootstrap.php';
// 确保加载 mysql_class (虽然 bootstrap 可能加载了，但显式加载更安全)
require_once __DIR__ . '/../support/mysql_class.php';

use GatewayWorker\Lib\Gateway;

// 初始化数据库连接
$db = new mysql_class();
$db_master = $db->db_master;

// 超时时间：300秒（5分钟）
$timeout_seconds = 300;
// 计算超时阈值
$threshold_timestamp = time() - $timeout_seconds;
$threshold_str = date('Y-m-d H:i:s', $threshold_timestamp);

echo "[清理任务] 开始清理僵尸连接 (使用 mysql_class) \r\n";
echo "[配置] 超时时间: {$timeout_seconds}秒 (阈值: {$threshold_str}) \r\n";

$cleanedCount = 0;
$skippedCount = 0;
$gatewayCheckAvailable = true;
$gatewayCheckError = null;

// ==========================================
// 1. 检测并清理/续期僵尸访客
// ==========================================

// SQL: 查找在线且超时的访客
// 对应 WsVisitorSession 查询逻辑
$sql_visitors = "SELECT visitor_token, client_id, last_activity_at FROM wa_ws_visitor_session 
                 WHERE is_online = 1 
                 AND (last_activity_at IS NULL OR last_activity_at < '{$threshold_str}')";

$staleVisitors = $db->fetchAll($sql_visitors, $db_master);
if (!$staleVisitors) {
    $staleVisitors = [];
}


if ($staleVisitors && is_array($staleVisitors)) {
    echo "[检测] 发现 " . count($staleVisitors) . " 个数据库超时访客，开始二次校验... \r\n";

    foreach ($staleVisitors as $session) {
        $token = $session['visitor_token'];
        $clientId = $session['client_id'];
        $lastActivityAt = $session['last_activity_at'];

        // client_id 为空，直接清理
        if (empty($clientId)) {
            markVisitorOffline($db, $token, $threshold_str);
            echo "  → [清理] 发现无效 client_id，已强制标记离线: token={$token} \r\n";
            continue;
        }

        // 检查 Gateway TCP 连接
        if ($gatewayCheckAvailable === false) {
            $skippedCount++;
            echo "  → [跳过] Gateway 校验不可用，避免误清理: token={$token}, client_id={$clientId} \r\n";
            continue;
        }

        $isTcpAlive = false;
        try {
            // Gateway::isOnline 需要 GatewayWorker 环境
            $isTcpAlive = Gateway::isOnline($clientId);
        } catch (\Throwable $e) {
            // 可能是 Register 服务连不上、配置未加载、网络抖动等；此时无法得出在线/离线结论，必须避免误清理
            $gatewayCheckAvailable = false;
            $gatewayCheckError = $e->getMessage();
            $skippedCount++;
            echo "  → [跳过] Gateway 检查失败，无法判定在线状态，已暂停后续 Gateway 校验并跳过清理: {$gatewayCheckError} (client_id={$clientId}) \r\n";
            continue;
        }

        if ($isTcpAlive) {
            // A. TCP 存活 -> 假死（可能是浏览器后台挂起），续期
            echo "  → [续期] 连接 {$clientId} (token={$token}) TCP存活但DB超时，自动续期 \r\n";
            updateVisitorActivity($db, $token, $threshold_str);
        } else {
            // B. TCP 断开 -> 真死，清理
            echo "  → [清理] 连接 {$clientId} (token={$token}) TCP已断开且DB超时。执行清理... \r\n";

            // 尝试关闭 Gateway 连接 (以防万一)
            try {
                Gateway::closeClient($clientId);
            } catch (\Throwable $e) {
                // 忽略错误
            }

            markVisitorOffline($db, $token, $threshold_str);
            $cleanedCount++;
        }
    }
} else {
    echo "[检测] 没有发现超时访客 \r\n";
}

// ==========================================
// 2. 清理超时客服
// ==========================================

// SQL: 查找超时客服
// 对应 WsAdminOnline::cleanupIdle(300)
$sql_admins = "SELECT admin_id, client_id, nickname FROM wa_ws_admin_online 
               WHERE is_online = 1 
               AND last_activity_at < '{$threshold_str}'";

$staleAdmins = $db->fetchAll($sql_admins, $db_master);

if ($staleAdmins && is_array($staleAdmins)) {
    foreach ($staleAdmins as $admin) {
        $adminId = $admin['admin_id'];
        $nickname = $admin['nickname'];

        echo "[清理客服] 客服 {$nickname} (ID: {$adminId}) 超时下线 \r\n";

        // 1. 标记客服下线
        $now = date('Y-m-d H:i:s');
        $update_admin_sql = "UPDATE wa_ws_admin_online SET 
                             is_online = 0, 
                             client_id = NULL, 
                             last_activity_at = NULL, 
                             disconnected_at = '{$now}', 
                             updated_at = '{$now}' 
                             WHERE admin_id = {$adminId} AND is_online = 1 AND last_activity_at < '{$threshold_str}'";
        $db->query($update_admin_sql, $db_master);

        // 2. 解除该客服关联的访客 (避免访客一直显示被该离线客服接待)
        // 对应 ChatPresenceServer::markAdminOffline 中的逻辑
        // 注意：必须同时置空 first_message_at，否则会立即触发“超过15秒未接入”的警示 (对应 WsVisitorSession::unmarkServed)
        $clear_visitor_sql = "UPDATE wa_ws_visitor_session SET 
                              served_by_admin = NULL, 
                              served_at = NULL,
                              first_message_at = NULL
                              WHERE served_by_admin = {$adminId}";
        $db->query($clear_visitor_sql, $db_master);

        $cleanedCount++;
    }
} else {
    echo "[检测] 没有发现超时客服 \r\n";
}

echo "[清理任务] 清理完成，共清理 {$cleanedCount} 个僵尸连接 \r\n";
if ($skippedCount > 0) {
    echo "[清理任务] 本次跳过 {$skippedCount} 条记录（Gateway 校验不可用或失败时避免误清理） \r\n";
}
if ($gatewayCheckAvailable === false && $gatewayCheckError !== null) {
    echo "[警告] Gateway 校验已暂停：{$gatewayCheckError} \r\n";
}
exit();


// ==========================================
// 辅助函数
// ==========================================

/**
 * 标记访客离线
 * @param mysql_class $db
 * @param string $token
 * @param string $thresholdStr
 */
function markVisitorOffline($db, $token, $thresholdStr)
{
    if (empty($token)) return;
    $now = date('Y-m-d H:i:s');
    // 对应 WsVisitorSession::markOffline
    $sql = "UPDATE wa_ws_visitor_session SET 
            client_id = NULL, 
            is_online = 0, 
            disconnected_at = '{$now}', 
            served_by_admin = NULL, 
            served_at = NULL, 
            last_activity_at = NULL, 
            extended_until = NULL 
            WHERE visitor_token = '{$token}' AND is_online = 1 AND (last_activity_at IS NULL OR last_activity_at < '{$thresholdStr}')";
    $db->query($sql, $db->db_master);
}

/**
 * 更新访客活跃时间 (续期)
 * @param mysql_class $db
 * @param string $token
 * @param string $thresholdStr
 */
function updateVisitorActivity($db, $token, $thresholdStr)
{
    if (empty($token)) return;
    $now = date('Y-m-d H:i:s');
    // 对应 WsVisitorSession::updateLastActivity
    $sql = "UPDATE wa_ws_visitor_session SET 
            last_activity_at = '{$now}', 
            is_online = 1, 
            disconnected_at = NULL 
            WHERE visitor_token = '{$token}' AND is_online = 1 AND (last_activity_at IS NULL OR last_activity_at < '{$thresholdStr}')";
    $db->query($sql, $db->db_master);
}
