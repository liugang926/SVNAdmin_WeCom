<?php
/**
 * 修复缺失的SVN分组
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

echo "=== 修复缺失的SVN分组 ===\n\n";

try {
    // 1. 获取所有有svn_group_id但分组不存在的部门
    echo "1. 查找缺失的分组...\n";
    
    $departments = $database->select('wecom_departments', [
        'wecom_dept_id',
        'dept_name', 
        'svn_group_id'
    ], [
        'svn_group_id[!]' => null
    ]);
    
    $missingGroups = [];
    foreach ($departments as $dept) {
        $groupExists = $database->has('svn_groups', ['svn_group_id' => $dept['svn_group_id']]);
        if (!$groupExists) {
            $missingGroups[] = $dept;
        }
    }
    
    echo "   找到 " . count($missingGroups) . " 个缺失的分组\n\n";
    
    if (empty($missingGroups)) {
        echo "没有缺失的分组，退出。\n";
        exit(0);
    }
    
    // 2. 重新创建缺失的分组
    echo "2. 重新创建缺失的分组...\n";
    
    $created = 0;
    foreach ($missingGroups as $dept) {
        try {
            // 生成分组名（去掉前缀，保留原始部门名）
            $groupName = $dept['dept_name'];
            
            // 插入分组记录
            $database->insert('svn_groups', [
                'svn_group_id' => $dept['svn_group_id'], // 使用原有的ID
                'svn_group_name' => $groupName,
                'include_user_count' => 0,
                'include_group_count' => 0,
                'include_aliase_count' => 0,
                'svn_group_note' => "企业微信同步: {$dept['dept_name']} (ID: {$dept['wecom_dept_id']}) - " . date('Y-m-d H:i:s')
            ]);
            
            echo "   ✓ 创建分组: {$groupName} (ID: {$dept['svn_group_id']})\n";
            $created++;
            
        } catch (Exception $e) {
            echo "   ✗ 创建分组失败: {$dept['dept_name']} - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n成功创建 $created 个分组\n\n";
    
    // 3. 验证修复结果
    echo "3. 验证修复结果...\n";
    
    $totalGroups = $database->count('svn_groups');
    $totalDepts = $database->count('wecom_departments', ['svn_group_id[!]' => null]);
    $validMappings = 0;
    
    foreach ($departments as $dept) {
        $groupExists = $database->has('svn_groups', ['svn_group_id' => $dept['svn_group_id']]);
        if ($groupExists) {
            $validMappings++;
        }
    }
    
    echo "   SVN分组总数: $totalGroups\n";
    echo "   企业微信部门数: $totalDepts\n";
    echo "   有效映射数: $validMappings\n";
    
    if ($validMappings == $totalDepts) {
        echo "   ✓ 所有部门都已正确映射到SVN分组\n";
    } else {
        echo "   ✗ 还有 " . ($totalDepts - $validMappings) . " 个部门未正确映射\n";
    }
    
    echo "\n=== 修复完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
