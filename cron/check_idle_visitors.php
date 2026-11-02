<?php
/**
 * 检查闲置访客并自动挂断 - 使用原生 mysql_class
 * 替代 check-idle-visitors.php 解决 Model 加载报错问题
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../support/bootstrap.php';
// 确保加载 mysql_class
require_once __DIR__ . '/../support/mysql_class.php';

use GatewayWorker\Lib\Gateway;

// 初始化数据库连接
$db = new mysql_class();
$db_master = $db->db_master;

// 配置超时时间（秒）- 默认 120 秒 (2分钟)
$idleTimeout = 120;

// 计算阈值
$threshold_timestamp = time() - $idleTimeout;
$threshold_str = date('Y-m-d H:i:s', $threshold_timestamp);

echo "[" . date('Y-m-d H:i:s') . "] 开始检查闲置访客 (mysql_class)（超时时间: {$idleTimeout}秒）\r\n";

// ==========================================
// 1. 查找需要处理的会话
// ==========================================
// 逻辑对应 WsVisitorSession::getIdleSessions()
// 条件：在线 + 已接入客服 + 有最后活动时间 + 超时 + (未延长或延长已过期)
$now = date('Y-m-d H:i:s');
$sql = "SELECT visitor_token, client_id, served_by_admin, last_activity_at, nickname 
        FROM wa_ws_visitor_session 
        WHERE is_online = 1 
        AND served_by_admin IS NOT NULL AND served_by_admin > 0 
        AND last_activity_at IS NOT NULL 
        AND last_activity_at < '{$threshold_str}' 
        AND (extended_until IS NULL OR extended_until < '{$now}')";

$idleSessions = $db->fetchAll($sql, $db_master);

if (empty($idleSessions) || !is_array($idleSessions)) {
    echo "  → 没有需要挂断的闲置会话\r\n";
    echo "[" . date('Y-m-d H:i:s') . "] 检查完成\r\n";
    exit(0);
}

echo "  → 发现 " . count($idleSessions) . " 个闲置会话需要挂断\r\n\r\n";

$hangupCount = 0;
$errorCount = 0;

foreach ($idleSessions as $session) {
    $adminId = (int)$session['served_by_admin'];
    $visitorToken = $session['visitor_token'];
    $clientId = $session['client_id'];
    $lastActivity = $session['last_activity_at'];

    if ($adminId <= 0 || empty($visitorToken)) {
        continue;
    }

    // ==========================================
    // 2. 检查 Gateway 存活状态 (防误判)
    // ==========================================
    $isTcpAlive = false;
    if (!empty($clientId)) {
        try {
            $isTcpAlive = Gateway::isOnline($clientId);
        } catch (\Throwable $e) {
            $isTcpAlive = false;
        }
    }

    if ($isTcpAlive) {
        echo "  [跳过] TCP 在线，访客 token=".$visitorToken." client_id=".$clientId."，不自动挂断\r\n";
        continue;
    }

    // ==========================================
    // 3. 执行挂断 (数据库更新)
    // ==========================================
    // 计算闲置时长
    $idleSeconds = time() - strtotime($lastActivity);
    echo "  [自动挂断] admin_id=".$adminId.", visitor_token=".$visitorToken.".\r\n";
    echo "    → 最后活动: ".$lastActivity." (闲置 ".$idleSeconds."秒)\r\n";

    // 更新数据库：移除接入客服、清除首次消息时间
    // 对应 WsVisitorSession::unmarkServed
    $update_sql = "UPDATE wa_ws_visitor_session SET 
                   served_by_admin = NULL, 
                   served_at = NULL, 
                   first_message_at = NULL 
                   WHERE visitor_token = '{$visitorToken}'";
    
    $res = $db->query($update_sql, $db_master);

    if ($res) {
        $hangupCount++;
        echo "    → 数据库更新成功\r\n";

        // ==========================================
        // 4. 广播通知 (Gateway)
        // ==========================================
        // 尝试获取客服信息以便广播
        $admin_sql = "SELECT nickname FROM wa_ws_admin_online WHERE admin_id = {$adminId} LIMIT 1";
        $admin_info = $db->fetch($admin_sql, $db_master);
        $admin_nickname = $admin_info['nickname'] ?? '客服';

        // 构造 hold_sync 消息 (通知客服端该访客已断开/挂起)
        $payload = json_encode([
            'type' => 'hold_sync',
            'admin_id' => $adminId,
            'admin_nickname' => $admin_nickname,
            'hold_status' => 0, // 0 表示取消接入
            'visitor_token' => $visitorToken,
        ], JSON_UNESCAPED_UNICODE);

        // 发送给该客服的所有在线客户端
        // 1. 查该客服的 client_id
        $clients_sql = "SELECT client_id FROM wa_ws_admin_online WHERE admin_id = {$adminId} AND is_online = 1";
        $clients = $db->fetchAll($clients_sql, $db_master);
        
        if ($clients) {
            foreach ($clients as $c) {
                if (!empty($c['client_id'])) {
                    try {
                        Gateway::sendToClient($c['client_id'], $payload);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
            echo "    → 广播通知已发送给客服\r\n";
        }
        
        // 理论上还应该广播 visitor_sync (全量列表)，但由于 SQL 复杂且开销大，
        // 这里暂时省略，依赖客服端自动轮询或下一次交互刷新。
        
    } else {
        echo "    → 数据库更新失败\r\n";
        $errorCount++;
    }
    
    echo "\r\n";
}

echo "[" . date('Y-m-d H:i:s') . "] 检查完成\r\n";
echo "  成功挂断: ".$hangupCount." 个\r\n";
if ($errorCount > 0) {
    echo "  失败: ".$errorCount." 个\r\n";
}

