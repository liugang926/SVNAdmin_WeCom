<?php
/**
 * 直接解析authz文件并更新组用户计数
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义BASE_PATH
define('BASE_PATH', '/var/www/html');

// 包含必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 直接解析authz文件更新组用户计数 ===\n\n";

try {
    // 1. 读取authz文件
    $authzFile = '/home/svnadmin/authz';
    $authzContent = file_get_contents($authzFile);
    
    echo "authz文件大小: " . strlen($authzContent) . " 字节\n";
    
    // 2. 手动解析[groups]部分
    $lines = explode("\n", $authzContent);
    $inGroupsSection = false;
    $groupCounts = [];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        
        // 检查是否进入[groups]部分
        if ($line === '[groups]') {
            $inGroupsSection = true;
            continue;
        }
        
        // 检查是否离开[groups]部分
        if ($inGroupsSection && preg_match('/^\[.*\]$/', $line)) {
            $inGroupsSection = false;
            break;
        }
        
        // 解析组成员行
        if ($inGroupsSection && strpos($line, '=') !== false) {
            list($groupName, $members) = explode('=', $line, 2);
            $groupName = trim($groupName);
            $members = trim($members);
            
            if (!empty($groupName)) {
                if (empty($members)) {
                    $memberCount = 0;
                    $memberList = '';
                } else {
                    $memberArray = array_map('trim', explode(',', $members));
                    $memberArray = array_filter($memberArray); // 移除空元素
                    $memberCount = count($memberArray);
                    $memberList = implode(', ', $memberArray);
                }
                
                $groupCounts[$groupName] = [
                    'count' => $memberCount,
                    'members' => $memberList
                ];
            }
        }
    }
    
    echo "从authz文件解析到 " . count($groupCounts) . " 个组\n\n";
    
    // 3. 获取数据库中的组信息
    $dbGroups = $database->select('svn_groups', [
        'svn_group_id',
        'svn_group_name',
        'include_user_count'
    ]);
    
    echo "数据库中有 " . count($dbGroups) . " 个组\n\n";
    
    // 4. 更新数据库中的用户计数
    $updated = 0;
    $notFound = 0;
    $unchanged = 0;
    
    foreach ($dbGroups as $dbGroup) {
        $groupName = $dbGroup['svn_group_name'];
        $currentCount = $dbGroup['include_user_count'];
        
        if (isset($groupCounts[$groupName])) {
            $actualCount = $groupCounts[$groupName]['count'];
            $members = $groupCounts[$groupName]['members'];
            
            echo sprintf("%-25s: 数据库=%d, 实际=%d", $groupName, $currentCount, $actualCount);
            
            if ($currentCount != $actualCount) {
                // 更新数据库
                $result = $database->update('svn_groups', [
                    'include_user_count' => $actualCount
                ], [
                    'svn_group_id' => $dbGroup['svn_group_id']
                ]);
                
                if ($result->rowCount() > 0) {
                    echo " -> ✓ 已更新";
                    $updated++;
                } else {
                    echo " -> ✗ 更新失败";
                }
            } else {
                echo " -> 无需更新";
                $unchanged++;
            }
            
            // 显示成员（如果有）
            if ($actualCount > 0) {
                echo " [$members]";
            }
            echo "\n";
            
        } else {
            echo sprintf("%-25s: 在authz文件中未找到\n", $groupName);
            $notFound++;
        }
    }
    
    echo "\n=== 更新完成 ===\n";
    echo "处理组数: " . count($dbGroups) . "\n";
    echo "已更新: $updated\n";
    echo "无需更新: $unchanged\n";
    echo "未找到: $notFound\n";
    
    // 5. 显示用户数最多的组
    echo "\n用户数最多的前10个组:\n";
    $sortedGroups = $groupCounts;
    uasort($sortedGroups, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    $count = 0;
    foreach ($sortedGroups as $groupName => $info) {
        if ($count < 10 && $info['count'] > 0) {
            echo sprintf("  %-25s: %d 个用户 [%s]\n", 
                $groupName, 
                $info['count'], 
                substr($info['members'], 0, 50) . ($info['count'] > 3 ? '...' : '')
            );
            $count++;
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
