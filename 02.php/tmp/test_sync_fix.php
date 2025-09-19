<?php
/**
 * 测试修复后的同步功能
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

echo "=== 企业微信同步功能测试 ===\n\n";

// 1. 检查配置
echo "1. 检查企业微信配置:\n";
$config = $database->get('wecom_config', '*', ['id' => 1]);
if ($config) {
    echo "   ✓ 配置存在\n";
    echo "   - Corp ID: " . substr($config['corp_id'], 0, 10) . "...\n";
    echo "   - Agent ID: " . $config['agent_id'] . "\n";
} else {
    echo "   ✗ 配置不存在\n";
    exit(1);
}

// 2. 检查同步日志表
echo "\n2. 检查同步日志:\n";
$logs = $database->select('wecom_sync_logs', '*', [
    'ORDER' => ['id' => 'DESC'],
    'LIMIT' => 3
]);
echo "   最近3条日志:\n";
foreach ($logs as $log) {
    echo "   - {$log['sync_type']} | {$log['sync_status']} | {$log['start_time']}\n";
}

// 3. 检查部门表
echo "\n3. 检查部门数据:\n";
$deptCount = $database->count('wecom_departments');
echo "   企业微信部门数量: {$deptCount}\n";

$svnGroupCount = $database->count('svn_groups');
echo "   SVN分组数量: {$svnGroupCount}\n";

// 4. 检查用户表
echo "\n4. 检查用户数据:\n";
$userCount = $database->count('wecom_users');
echo "   企业微信用户数量: {$userCount}\n";

$svnUserCount = $database->count('svn_users');
echo "   SVN用户数量: {$svnUserCount}\n";

// 5. 检查用户备注
echo "\n5. 检查用户备注:\n";
$usersWithNotes = $database->select('svn_users', ['svn_user_name', 'svn_user_note'], [
    'svn_user_note[!]' => '',
    'LIMIT' => 5
]);
if (count($usersWithNotes) > 0) {
    echo "   有备注的用户示例:\n";
    foreach ($usersWithNotes as $user) {
        echo "   - {$user['svn_user_name']}: {$user['svn_user_note']}\n";
    }
} else {
    echo "   ✗ 没有用户有备注信息\n";
}

echo "\n=== 测试完成 ===\n";
?>
