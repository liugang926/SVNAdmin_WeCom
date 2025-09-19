<?php
/**
 * 调试同步过程
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

echo "=== 同步过程调试 ===\n\n";

// 1. 检查最新同步日志的详细信息
echo "1. 检查最新同步日志:\n";
$latestLog = $database->get('wecom_sync_logs', '*', [
    'ORDER' => ['id' => 'DESC'],
    'LIMIT' => 1
]);

if ($latestLog) {
    echo "   同步ID: {$latestLog['id']}\n";
    echo "   状态: {$latestLog['sync_status']}\n";
    echo "   开始时间: {$latestLog['start_time']}\n";
    echo "   结束时间: {$latestLog['end_time']}\n";
    echo "   摘要: " . substr($latestLog['summary'], 0, 200) . "...\n";
    
    // 解析摘要中的统计信息
    if ($latestLog['summary']) {
        $summary = json_decode($latestLog['summary'], true);
        if ($summary) {
            echo "   统计信息:\n";
            echo "     - 部门: 总计{$summary['departments']['total']}, 创建{$summary['departments']['created']}, 更新{$summary['departments']['updated']}, 错误{$summary['departments']['errors']}\n";
            echo "     - 用户: 总计{$summary['users']['total']}, 创建{$summary['users']['created']}, 更新{$summary['users']['updated']}, 错误{$summary['users']['errors']}\n";
        }
    }
}

// 2. 检查wecom_users表的详细情况
echo "\n2. 检查wecom_users表:\n";
$userCount = $database->count('wecom_users');
echo "   记录数量: $userCount\n";

if ($userCount > 0) {
    $sampleUsers = $database->select('wecom_users', '*', ['LIMIT' => 3]);
    echo "   示例用户:\n";
    foreach ($sampleUsers as $user) {
        echo "     - {$user['real_name']} ({$user['wecom_user_id']}) - SVN ID: {$user['svn_user_id']}\n";
    }
} else {
    echo "   ⚠ 表为空，检查插入过程\n";
    
    // 检查表结构
    echo "   检查表结构:\n";
    $tableInfo = $database->query("PRAGMA table_info(wecom_users)")->fetchAll();
    foreach ($tableInfo as $column) {
        echo "     - {$column['name']} ({$column['type']})\n";
    }
}

// 3. 检查wecom_departments表
echo "\n3. 检查wecom_departments表:\n";
$deptCount = $database->count('wecom_departments');
echo "   记录数量: $deptCount\n";

if ($deptCount > 0) {
    $sampleDepts = $database->select('wecom_departments', '*', ['LIMIT' => 3]);
    echo "   示例部门:\n";
    foreach ($sampleDepts as $dept) {
        echo "     - {$dept['dept_name']} ({$dept['wecom_dept_id']}) - SVN组ID: {$dept['svn_group_id']}\n";
    }
}

// 4. 检查SVN用户的备注字段
echo "\n4. 检查SVN用户备注:\n";
$usersWithNotes = $database->select('svn_users', ['svn_user_name', 'svn_user_note'], [
    'svn_user_note[!]' => '',
    'LIMIT' => 5
]);

if (count($usersWithNotes) > 0) {
    echo "   有备注的用户:\n";
    foreach ($usersWithNotes as $user) {
        echo "     - {$user['svn_user_name']}: {$user['svn_user_note']}\n";
    }
} else {
    echo "   ⚠ 没有用户有备注\n";
    
    // 检查是否有企业微信相关的用户
    $wecomRelatedUsers = $database->select('svn_users', ['svn_user_name', 'svn_user_note'], [
        'svn_user_note[~]' => '企业微信',
        'LIMIT' => 5
    ]);
    
    if (count($wecomRelatedUsers) > 0) {
        echo "   企业微信相关用户:\n";
        foreach ($wecomRelatedUsers as $user) {
            echo "     - {$user['svn_user_name']}: {$user['svn_user_note']}\n";
        }
    }
}

// 5. 检查authz文件中的分组信息
echo "\n5. 检查authz文件:\n";
$authzFile = '/home/svnadmin/authz';
if (file_exists($authzFile)) {
    $authzContent = file_get_contents($authzFile);
    $lines = explode("\n", $authzContent);
    $groupCount = 0;
    $sampleGroups = [];
    
    foreach ($lines as $line) {
        if (preg_match('/^\[groups\]/', $line)) {
            continue;
        }
        if (preg_match('/^([^=]+)=(.+)$/', $line, $matches)) {
            $groupCount++;
            if (count($sampleGroups) < 3) {
                $sampleGroups[] = trim($matches[1]) . ' = ' . trim($matches[2]);
            }
        }
    }
    
    echo "   分组数量: $groupCount\n";
    if (count($sampleGroups) > 0) {
        echo "   示例分组:\n";
        foreach ($sampleGroups as $group) {
            echo "     - $group\n";
        }
    }
} else {
    echo "   ⚠ authz文件不存在\n";
}

echo "\n=== 调试完成 ===\n";
?>
