<?php
/**
 * 触发企业微信全量同步
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义BASE_PATH
define('BASE_PATH', '/var/www/html');

// 包含必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/app/service/WeComSync.php';

use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 触发企业微信全量同步 ===\n\n";

try {
    // 创建同步服务实例
    $syncService = new \app\service\WeComSync();
    
    echo "1. 开始执行全量同步...\n";
    $result = $syncService->fullSync();
    
    if ($result['status'] === 1) {
        echo "   ✓ 全量同步执行成功\n";
        echo "   消息: {$result['message']}\n";
        
        if (isset($result['data'])) {
            $data = $result['data'];
            echo "   同步统计:\n";
            if (isset($data['departments'])) {
                echo "     - 部门: 总计{$data['departments']['total']}, 创建{$data['departments']['created']}, 更新{$data['departments']['updated']}\n";
            }
            if (isset($data['users'])) {
                echo "     - 用户: 总计{$data['users']['total']}, 创建{$data['users']['created']}, 更新{$data['users']['updated']}\n";
            }
        }
    } else {
        echo "   ✗ 全量同步执行失败\n";
        echo "   错误: {$result['message']}\n";
    }
    
} catch (\Exception $e) {
    echo "   ✗ 同步异常: " . $e->getMessage() . "\n";
    echo "   堆栈: " . $e->getTraceAsString() . "\n";
}

// 检查同步结果
echo "\n2. 检查同步结果:\n";

// 检查wecom_users表
$userCount = $database->count('wecom_users');
echo "   企业微信用户数量: $userCount\n";

if ($userCount > 0) {
    // 显示前3个用户
    $users = $database->select('wecom_users', ['wecom_user_id', 'real_name', 'svn_username'], [
        'LIMIT' => 3
    ]);
    
    echo "   前3个用户:\n";
    foreach ($users as $user) {
        echo "     - {$user['real_name']} ({$user['wecom_user_id']}) -> SVN: {$user['svn_username']}\n";
    }
}

// 检查SVN用户备注
$svnUsersWithNotes = $database->select('svn_users', ['svn_user_name', 'svn_user_note'], [
    'svn_user_note[!]' => '',
    'svn_user_note[~]' => '企业微信同步%',
    'LIMIT' => 3
]);

echo "   SVN用户备注示例:\n";
if (!empty($svnUsersWithNotes)) {
    foreach ($svnUsersWithNotes as $user) {
        echo "     - {$user['svn_user_name']}: {$user['svn_user_note']}\n";
    }
} else {
    echo "     ⚠ 没有找到带企业微信同步备注的用户\n";
}

echo "\n=== 同步完成 ===\n";
?>
