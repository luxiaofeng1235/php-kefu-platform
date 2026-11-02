<?php
/**
 * 测试清理脚本 - 创建测试数据并验证清理功能
 */

require_once __DIR__ . '/vendor/autoload.php';

// 确保环境变量已加载
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeMutable')) {
        Dotenv\Dotenv::createUnsafeMutable(__DIR__)->load();
    } else {
        Dotenv\Dotenv::createMutable(__DIR__)->load();
    }
}

require_once __DIR__ . '/support/bootstrap.php';

use app\model\WsVisitorSession;
use support\Db;

echo "========================================\n";
echo "🧪 清理脚本测试工具\n";
echo "========================================\n\n";

try {
    $pdo = Db::connection()->getPdo();
    echo "✅ 数据库连接成功\n\n";

    // ==================== 步骤1: 查看当前数据 ====================
    echo "📊 步骤1: 查看当前数据\n";
    echo "----------------------------------------\n";

    $stmt = $pdo->query("
        SELECT id, visitor_token, is_online, served_by_admin,
               connected_at, disconnected_at, last_activity_at
        FROM wa_ws_visitor_session
        ORDER BY id DESC
        LIMIT 10
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "  ⚠️ 数据库中暂无会话记录\n";
    } else {
        foreach ($rows as $row) {
            echo sprintf(
                "  ID=%d, Token=%s, Online=%d, ServedBy=%s, Connected=%s\n",
                $row['id'],
                substr($row['visitor_token'], 0, 30),
                $row['is_online'],
                $row['served_by_admin'] ?? 'NULL',
                $row['connected_at'] ?? 'NULL'
            );
        }
    }

    // ==================== 步骤2: 创建测试数据 ====================
    echo "\n📝 步骤2: 创建测试数据\n";
    echo "----------------------------------------\n";

    $testToken1 = 'test_zombie_' . time();
    $testToken2 = 'test_normal_' . time();

    // 测试数据1: 僵尸会话(is_online=1 但 served_by_admin 有值)
    $pdo->exec("
        INSERT INTO wa_ws_visitor_session
        (visitor_token, ip, is_online, served_by_admin, served_at, connected_at, created_at, updated_at)
        VALUES
        ('{$testToken1}', '127.0.0.1', 1, 999, NOW(), NOW(), NOW(), NOW())
    ");
    echo "  ✅ 创建测试数据1: 僵尸会话 (token={$testToken1}, is_online=1, served_by_admin=999)\n";

    // 测试数据2: 正常离线会话
    $pdo->exec("
        INSERT INTO wa_ws_visitor_session
        (visitor_token, ip, is_online, served_by_admin, connected_at, disconnected_at, created_at, updated_at)
        VALUES
        ('{$testToken2}', '127.0.0.1', 0, NULL, NOW(), NOW(), NOW(), NOW())
    ");
    echo "  ✅ 创建测试数据2: 正常离线会话 (token={$testToken2}, is_online=0)\n";

    // ==================== 步骤3: 模拟清理前的异常状态 ====================
    echo "\n🔍 步骤3: 检查异常数据\n";
    echo "----------------------------------------\n";

    // 查询 is_online=0 但 served_by_admin 不为 NULL 的记录
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wa_ws_visitor_session
        WHERE is_online = 0 AND served_by_admin IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  ⚠️ 离线但仍有 served_by_admin 的异常记录: {$result['count']} 条\n";

    // 查询 is_online=1 但 Redis 中不存在的记录(需要配合 Redis 检查)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wa_ws_visitor_session
        WHERE is_online = 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  📊 标记为在线的会话: {$result['count']} 条\n";

    // ==================== 步骤4: 测试 markOffline 方法 ====================
    echo "\n🧹 步骤4: 测试 markOffline() 清理方法\n";
    echo "----------------------------------------\n";

    echo "  🔧 对测试数据1执行 markOffline()...\n";
    $success = WsVisitorSession::markOffline($testToken1);

    if ($success) {
        echo "  ✅ markOffline() 执行成功\n";

        // 验证清理结果
        $stmt = $pdo->prepare("
            SELECT is_online, served_by_admin, served_at, last_activity_at, extended_until
            FROM wa_ws_visitor_session
            WHERE visitor_token = ?
        ");
        $stmt->execute([$testToken1]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo "  📋 清理后的数据:\n";
            echo "     - is_online: " . ($result['is_online'] ?? 'NULL') . " (应为 0)\n";
            echo "     - served_by_admin: " . ($result['served_by_admin'] ?? 'NULL') . " (应为 NULL)\n";
            echo "     - served_at: " . ($result['served_at'] ?? 'NULL') . " (应为 NULL)\n";
            echo "     - last_activity_at: " . ($result['last_activity_at'] ?? 'NULL') . " (应为 NULL)\n";
            echo "     - extended_until: " . ($result['extended_until'] ?? 'NULL') . " (应为 NULL)\n";

            // 验证是否正确清理
            $allCleared = (
                $result['is_online'] == 0 &&
                $result['served_by_admin'] === null &&
                $result['served_at'] === null &&
                $result['last_activity_at'] === null &&
                $result['extended_until'] === null
            );

            if ($allCleared) {
                echo "  ✅ 所有字段已正确清理!\n";
            } else {
                echo "  ❌ 部分字段未清理,请检查 markOffline() 方法\n";
            }
        }
    } else {
        echo "  ❌ markOffline() 执行失败\n";
    }

    // ==================== 步骤5: 清理测试数据 ====================
    echo "\n🗑️  步骤5: 清理测试数据\n";
    echo "----------------------------------------\n";

    $pdo->exec("DELETE FROM wa_ws_visitor_session WHERE visitor_token IN ('{$testToken1}', '{$testToken2}')");
    echo "  ✅ 测试数据已清理\n";

    // ==================== 步骤6: 查询实际异常数据 ====================
    echo "\n🔍 步骤6: 查询实际异常数据\n";
    echo "----------------------------------------\n";

    $stmt = $pdo->query("
        SELECT visitor_token, is_online, served_by_admin, served_at, disconnected_at
        FROM wa_ws_visitor_session
        WHERE is_online = 0 AND served_by_admin IS NOT NULL
        LIMIT 5
    ");
    $anomalies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($anomalies)) {
        echo "  ✅ 没有发现异常数据\n";
    } else {
        echo "  ⚠️ 发现 " . count($anomalies) . " 条异常数据:\n";
        foreach ($anomalies as $row) {
            echo sprintf(
                "     Token=%s, Online=%d, ServedBy=%s, DisconnectedAt=%s\n",
                substr($row['visitor_token'], 0, 30),
                $row['is_online'],
                $row['served_by_admin'],
                $row['disconnected_at'] ?? 'NULL'
            );
        }

        echo "\n  💡 建议: 运行以下SQL清理这些异常数据:\n";
        echo "     UPDATE wa_ws_visitor_session \n";
        echo "     SET served_by_admin = NULL, served_at = NULL, \n";
        echo "         last_activity_at = NULL, extended_until = NULL \n";
        echo "     WHERE is_online = 0 AND served_by_admin IS NOT NULL;\n";
    }

    echo "\n========================================\n";
    echo "✅ 测试完成!\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
