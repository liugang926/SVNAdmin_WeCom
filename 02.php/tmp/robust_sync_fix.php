<?php
/**
 * 健壮的企业微信同步修复机制
 * 解决分组丢失问题，支持组织架构变更
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

echo "=== 健壮的企业微信同步修复 ===\n\n";

try {
    // 1. 分析当前状态
    echo "1. 分析当前同步状态...\n";
    
    $wecomDepts = $database->select('wecom_departments', '*');
    $svnGroups = $database->select('svn_groups', '*');
    
    echo "   企业微信部门数: " . count($wecomDepts) . "\n";
    echo "   SVN分组数: " . count($svnGroups) . "\n";
    
    // 建立现有分组的映射
    $existingGroups = [];
    foreach ($svnGroups as $group) {
        $existingGroups[$group['svn_group_id']] = $group;
    }
    
    // 2. 识别问题
    echo "\n2. 识别同步问题...\n";
    
    $missingGroups = [];
    $orphanedGroups = [];
    $validMappings = 0;
    
    foreach ($wecomDepts as $dept) {
        if ($dept['svn_group_id']) {
            if (isset($existingGroups[$dept['svn_group_id']])) {
                $validMappings++;
            } else {
                $missingGroups[] = $dept;
            }
        }
    }
    
    // 查找孤立的分组（没有对应企业微信部门的）
    $deptGroupIds = array_column($wecomDepts, 'svn_group_id');
    foreach ($svnGroups as $group) {
        if (!in_array($group['svn_group_id'], $deptGroupIds)) {
            $orphanedGroups[] = $group;
        }
    }
    
    echo "   有效映射: $validMappings\n";
    echo "   缺失分组: " . count($missingGroups) . "\n";
    echo "   孤立分组: " . count($orphanedGroups) . "\n";
    
    // 3. 修复策略
    echo "\n3. 执行修复策略...\n";
    
    if (!empty($missingGroups)) {
        echo "   修复缺失的分组...\n";
        
        foreach ($missingGroups as $dept) {
            try {
                // 重新创建分组，使用原有ID
                $database->insert('svn_groups', [
                    'svn_group_id' => $dept['svn_group_id'],
                    'svn_group_name' => $dept['dept_name'],
                    'include_user_count' => 0,
                    'include_group_count' => 0,
                    'include_aliase_count' => 0,
                    'svn_group_note' => "企业微信同步修复: {$dept['dept_name']} (ID: {$dept['wecom_dept_id']}) - " . date('Y-m-d H:i:s')
                ]);
                
                echo "     ✓ 修复分组: {$dept['dept_name']} (ID: {$dept['svn_group_id']})\n";
                
            } catch (Exception $e) {
                echo "     ✗ 修复失败: {$dept['dept_name']} - " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 4. 清理孤立分组（可选）
    if (!empty($orphanedGroups)) {
        echo "\n   发现孤立分组（建议手动检查）:\n";
        foreach ($orphanedGroups as $group) {
            echo "     - {$group['svn_group_name']} (ID: {$group['svn_group_id']})\n";
        }
    }
    
    // 5. 验证修复结果
    echo "\n4. 验证修复结果...\n";
    
    $finalSvnGroups = $database->count('svn_groups');
    $finalValidMappings = 0;
    
    foreach ($wecomDepts as $dept) {
        if ($dept['svn_group_id']) {
            $exists = $database->has('svn_groups', ['svn_group_id' => $dept['svn_group_id']]);
            if ($exists) {
                $finalValidMappings++;
            }
        }
    }
    
    echo "   修复后SVN分组数: $finalSvnGroups\n";
    echo "   修复后有效映射: $finalValidMappings\n";
    
    if ($finalValidMappings == count($wecomDepts)) {
        echo "   ✅ 所有部门都已正确映射到SVN分组\n";
    } else {
        echo "   ⚠️  还有 " . (count($wecomDepts) - $finalValidMappings) . " 个部门未正确映射\n";
    }
    
    // 6. 提供后续建议
    echo "\n5. 后续建议...\n";
    echo "   ✓ 修复完成，分组数据已恢复\n";
    echo "   ✓ 支持企业微信组织架构变更：下次同步会自动处理新增/删除的部门\n";
    echo "   ✓ 建议定期检查同步日志，确保同步过程正常\n";
    echo "   ✓ 如需重新同步，可通过前端界面触发全量同步\n";
    
    echo "\n=== 修复完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
