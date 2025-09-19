<?php
/**
 * 测试停止同步API功能
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含必要的文件
require_once '/var/www/html/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 测试停止同步API功能 ===\n\n";

// 1. 检查当前同步任务状态
echo "1. 检查当前同步任务状态:\n";
$runningTasks = $database->select('wecom_sync_logs', '*', [
    'sync_status' => 'running',
    'ORDER' => ['id' => 'DESC']
]);

if (empty($runningTasks)) {
    echo "   ✓ 当前没有运行中的同步任务\n";
    
    // 创建一个测试任务
    echo "\n2. 创建测试同步任务:\n";
    $database->insert('wecom_sync_logs', [
        'sync_type' => 'full',
        'sync_status' => 'running',
        'start_time' => date('Y-m-d H:i:s'),
        'summary' => '测试同步任务'
    ]);
    
    $testTaskId = $database->id();
    echo "   ✓ 创建测试任务，ID: $testTaskId\n";
    
} else {
    echo "   发现运行中的任务:\n";
    foreach ($runningTasks as $task) {
        echo "     - ID: {$task['id']}, 类型: {$task['sync_type']}, 开始时间: {$task['start_time']}\n";
    }
}

// 3. 测试停止同步API
echo "\n3. 测试停止同步API:\n";

// 模拟API调用
$_GET['c'] = 'WeComAdmin';
$_GET['a'] = 'StopSync';
$_GET['t'] = 'web';

// 包含必要的文件
require_once '/var/www/html/app/controller/WeComAdmin.php';

try {
    // 创建控制器实例
    $controller = new \app\controller\WeComAdmin();
    
    // 捕获输出
    ob_start();
    $controller->StopSync();
    $output = ob_get_clean();
    
    echo "   API响应: $output\n";
    
    // 解析JSON响应
    $response = json_decode($output, true);
    if ($response && isset($response['status'])) {
        if ($response['status'] == 1) {
            echo "   ✓ 停止同步成功\n";
            if (isset($response['data']['stopped_task'])) {
                $stoppedTask = $response['data']['stopped_task'];
                echo "     停止的任务: ID {$stoppedTask['id']}, 类型: {$stoppedTask['sync_type']}\n";
            }
        } else {
            echo "   ⚠ 停止同步失败: {$response['message']}\n";
        }
    } else {
        echo "   ✗ API响应格式异常\n";
    }
    
} catch (\Exception $e) {
    echo "   ✗ API调用异常: " . $e->getMessage() . "\n";
}

// 4. 验证任务状态是否已更新
echo "\n4. 验证任务状态更新:\n";
$updatedTasks = $database->select('wecom_sync_logs', '*', [
    'sync_status' => 'cancelled',
    'ORDER' => ['id' => 'DESC'],
    'LIMIT' => 3
]);

if (!empty($updatedTasks)) {
    echo "   ✓ 发现已取消的任务:\n";
    foreach ($updatedTasks as $task) {
        echo "     - ID: {$task['id']}, 状态: {$task['sync_status']}, 结束时间: {$task['end_time']}\n";
        if (!empty($task['error_details'])) {
            echo "       取消原因: {$task['error_details']}\n";
        }
    }
} else {
    echo "   ⚠ 没有发现已取消的任务\n";
}

// 5. 检查是否还有运行中的任务
echo "\n5. 检查剩余运行中的任务:\n";
$remainingTasks = $database->select('wecom_sync_logs', '*', [
    'sync_status' => 'running'
]);

if (empty($remainingTasks)) {
    echo "   ✓ 没有剩余运行中的任务\n";
} else {
    echo "   ⚠ 仍有运行中的任务:\n";
    foreach ($remainingTasks as $task) {
        echo "     - ID: {$task['id']}, 类型: {$task['sync_type']}, 开始时间: {$task['start_time']}\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
