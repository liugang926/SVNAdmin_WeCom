<?php
/**
 * 调试authz文件解析问题
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

// 初始化SVNAdmin
$svnAdmin = new SVNAdmin();

echo "=== 调试authz文件解析 ===\n\n";

try {
    // 1. 读取authz文件
    $authzFile = '/home/svnadmin/authz';
    $authzContent = file_get_contents($authzFile);
    
    echo "authz文件大小: " . strlen($authzContent) . " 字节\n";
    echo "authz文件行数: " . count(explode("\n", $authzContent)) . "\n\n";
    
    // 2. 显示authz文件前20行
    echo "authz文件前20行:\n";
    $lines = explode("\n", $authzContent);
    for ($i = 0; $i < min(20, count($lines)); $i++) {
        echo sprintf("%3d: %s\n", $i + 1, $lines[$i]);
    }
    echo "\n";
    
    // 3. 测试GetGroupInfo方法
    echo "调用GetGroupInfo方法...\n";
    $groupInfo = $svnAdmin->GetGroupInfo($authzContent);
    
    echo "GetGroupInfo返回类型: " . gettype($groupInfo) . "\n";
    
    if (is_array($groupInfo)) {
        echo "找到 " . count($groupInfo) . " 个组\n";
        
        // 显示前10个组的信息
        $count = 0;
        foreach ($groupInfo as $groupName => $members) {
            if ($count < 10) {
                $memberCount = is_array($members) ? count($members) : 0;
                $memberList = is_array($members) ? implode(', ', $members) : (string)$members;
                echo sprintf("  %-20s: %d 个成员 [%s]\n", $groupName, $memberCount, $memberList);
                $count++;
            }
        }
        
        if (count($groupInfo) > 10) {
            echo "  ... 还有 " . (count($groupInfo) - 10) . " 个组\n";
        }
        
    } else {
        echo "GetGroupInfo返回错误码: $groupInfo\n";
        
        // 错误码含义
        $errorMessages = [
            612 => '[groups]部分不存在',
            720 => '指定的分组不存在或格式错误'
        ];
        $errorMsg = $errorMessages[$groupInfo] ?? "未知错误码";
        echo "错误含义: $errorMsg\n";
    }
    
    // 4. 测试获取特定组的信息
    echo "\n测试获取特定组信息:\n";
    $testGroups = ['财务中心', 'IT部', '总经办'];
    
    foreach ($testGroups as $groupName) {
        $result = $svnAdmin->GetGroupInfo($authzContent, $groupName);
        echo "组 '$groupName': ";
        
        if (is_array($result)) {
            if (isset($result[$groupName])) {
                $members = $result[$groupName];
                $memberCount = is_array($members) ? count($members) : 0;
                $memberList = is_array($members) ? implode(', ', $members) : (string)$members;
                echo "$memberCount 个成员 [$memberList]\n";
            } else {
                echo "未找到\n";
            }
        } else {
            echo "错误码: $result\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
