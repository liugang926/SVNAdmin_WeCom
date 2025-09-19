<?php
/**
 * 更新所有SVN组的用户计数
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义BASE_PATH
define('BASE_PATH', '/var/www/html');

// 包含必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/extension/Witersen/SVNAdmin.php';

use Medoo\Medoo;
use Witersen\SVNAdmin;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

// 初始化SVNAdmin
$svnAdmin = new SVNAdmin();

echo "=== 更新SVN组用户计数 ===\n\n";

try {
    // 1. 获取所有SVN组
    $groups = $database->select('svn_groups', [
        'svn_group_id',
        'svn_group_name',
        'include_user_count'
    ], [
        'ORDER' => 'svn_group_id'
    ]);
    
    echo "找到 " . count($groups) . " 个SVN组\n";
    
    // 2. 读取authz文件并获取所有组信息
    $authzFile = '/home/svnadmin/authz';
    $authzContent = file_get_contents($authzFile);
    
    // 获取所有组的成员信息
    $groupInfo = $svnAdmin->GetGroupInfo($authzContent);
    
    if (!is_array($groupInfo)) {
        throw new Exception("无法获取authz文件中的组信息，错误码: $groupInfo");
    }
    
    echo "authz文件中找到 " . count($groupInfo) . " 个组\n\n";
    
    // 3. 更新每个组的用户计数
    $updated = 0;
    $unchanged = 0;
    
    foreach ($groups as $group) {
        $groupName = $group['svn_group_name'];
        $currentCount = $group['include_user_count'];
        
        // 从authz文件中获取实际成员数
        $actualMembers = $groupInfo[$groupName] ?? [];
        $actualCount = is_array($actualMembers) ? count($actualMembers) : 0;
        
        echo sprintf("%-20s: 数据库=%d, 实际=%d", $groupName, $currentCount, $actualCount);
        
        if ($currentCount != $actualCount) {
            // 需要更新
            $result = $database->update('svn_groups', [
                'include_user_count' => $actualCount
            ], [
                'svn_group_id' => $group['svn_group_id']
            ]);
            
            if ($result->rowCount() > 0) {
                echo " -> ✓ 已更新\n";
                $updated++;
            } else {
                echo " -> ✗ 更新失败\n";
            }
        } else {
            echo " -> 无需更新\n";
            $unchanged++;
        }
        
        // 显示成员列表（如果有成员）
        if ($actualCount > 0) {
            $memberList = is_array($actualMembers) ? implode(', ', $actualMembers) : '';
            echo "    成员: $memberList\n";
        }
    }
    
    echo "\n=== 更新完成 ===\n";
    echo "处理组数: " . count($groups) . "\n";
    echo "已更新: $updated\n";
    echo "无需更新: $unchanged\n";
    
    // 4. 验证更新结果
    echo "\n验证更新结果:\n";
    $updatedGroups = $database->select('svn_groups', [
        'svn_group_name',
        'include_user_count'
    ], [
        'include_user_count[>]' => 0,
        'ORDER' => 'include_user_count DESC',
        'LIMIT' => 10
    ]);
    
    echo "用户数最多的前10个组:\n";
    foreach ($updatedGroups as $group) {
        echo sprintf("  %-20s: %d 个用户\n", $group['svn_group_name'], $group['include_user_count']);
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
