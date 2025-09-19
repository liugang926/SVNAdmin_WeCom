<?php
/**
 * 修复用户组关系和层级关系同步
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

echo "=== 修复用户组关系和层级关系 ===\n\n";

try {
    // 1. 检查当前状态
    echo "1. 检查当前状态...\n";
    
    $wecomUsers = $database->select('wecom_users', '*', ['svn_user_id[!]' => null]);
    $wecomDepts = $database->select('wecom_departments', '*', ['svn_group_id[!]' => null]);
    $svnUsers = $database->select('svn_users', '*');
    $svnGroups = $database->select('svn_groups', '*');
    
    echo "   企业微信用户: " . count($wecomUsers) . "\n";
    echo "   企业微信部门: " . count($wecomDepts) . "\n";
    echo "   SVN用户: " . count($svnUsers) . "\n";
    echo "   SVN分组: " . count($svnGroups) . "\n";
    
    // 2. 创建映射关系
    echo "\n2. 创建映射关系...\n";
    
    // 部门ID到SVN组ID的映射
    $deptToGroupMap = [];
    foreach ($wecomDepts as $dept) {
        $deptToGroupMap[$dept['wecom_dept_id']] = [
            'svn_group_id' => $dept['svn_group_id'],
            'dept_name' => $dept['dept_name'],
            'parent_id' => $dept['parent_id']
        ];
    }
    
    // SVN用户ID到用户名的映射
    $svnUserMap = [];
    foreach ($svnUsers as $user) {
        $svnUserMap[$user['svn_user_id']] = $user['svn_user_name'];
    }
    
    // SVN分组ID到分组名的映射
    $svnGroupMap = [];
    foreach ($svnGroups as $group) {
        $svnGroupMap[$group['svn_group_id']] = $group['svn_group_name'];
    }
    
    echo "   部门映射: " . count($deptToGroupMap) . "\n";
    echo "   用户映射: " . count($svnUserMap) . "\n";
    echo "   分组映射: " . count($svnGroupMap) . "\n";
    
    // 3. 分析用户组关系
    echo "\n3. 分析用户组关系...\n";
    
    $userGroupRelations = [];
    foreach ($wecomUsers as $user) {
        if (!$user['svn_user_id'] || !isset($svnUserMap[$user['svn_user_id']])) {
            continue;
        }
        
        $departmentIds = json_decode($user['department_ids'] ?? '[]', true);
        $targetGroups = [];
        
        foreach ($departmentIds as $deptId) {
            if (isset($deptToGroupMap[$deptId])) {
                $targetGroups[] = [
                    'svn_group_id' => $deptToGroupMap[$deptId]['svn_group_id'],
                    'svn_group_name' => $svnGroupMap[$deptToGroupMap[$deptId]['svn_group_id']] ?? 'Unknown',
                    'dept_name' => $deptToGroupMap[$deptId]['dept_name']
                ];
            }
        }
        
        if (!empty($targetGroups)) {
            $userGroupRelations[] = [
                'wecom_user_id' => $user['wecom_user_id'],
                'real_name' => $user['real_name'],
                'svn_user_id' => $user['svn_user_id'],
                'svn_user_name' => $svnUserMap[$user['svn_user_id']],
                'target_groups' => $targetGroups
            ];
        }
    }
    
    echo "   需要建立关系的用户: " . count($userGroupRelations) . "\n";
    
    // 4. 分析层级关系
    echo "\n4. 分析层级关系...\n";
    
    $hierarchyRelations = [];
    foreach ($wecomDepts as $dept) {
        if ($dept['parent_id'] && $dept['parent_id'] != 1) { // 排除根部门
            if (isset($deptToGroupMap[$dept['parent_id']])) {
                $hierarchyRelations[] = [
                    'child_dept_id' => $dept['wecom_dept_id'],
                    'child_dept_name' => $dept['dept_name'],
                    'child_group_id' => $dept['svn_group_id'],
                    'child_group_name' => $svnGroupMap[$dept['svn_group_id']] ?? 'Unknown',
                    'parent_dept_id' => $dept['parent_id'],
                    'parent_dept_name' => $deptToGroupMap[$dept['parent_id']]['dept_name'],
                    'parent_group_id' => $deptToGroupMap[$dept['parent_id']]['svn_group_id'],
                    'parent_group_name' => $svnGroupMap[$deptToGroupMap[$dept['parent_id']]['svn_group_id']] ?? 'Unknown'
                ];
            }
        }
    }
    
    echo "   需要建立的层级关系: " . count($hierarchyRelations) . "\n";
    
    // 5. 显示详细信息
    echo "\n5. 详细信息...\n";
    
    if (!empty($userGroupRelations)) {
        echo "\n   用户组关系示例（前5个）:\n";
        foreach (array_slice($userGroupRelations, 0, 5) as $relation) {
            echo "   - 用户: {$relation['real_name']} ({$relation['svn_user_name']})\n";
            foreach ($relation['target_groups'] as $group) {
                echo "     → 分组: {$group['svn_group_name']} (来自部门: {$group['dept_name']})\n";
            }
        }
    }
    
    if (!empty($hierarchyRelations)) {
        echo "\n   层级关系示例（前5个）:\n";
        foreach (array_slice($hierarchyRelations, 0, 5) as $relation) {
            echo "   - 子部门: {$relation['child_dept_name']} ({$relation['child_group_name']})\n";
            echo "     ↑ 父部门: {$relation['parent_dept_name']} ({$relation['parent_group_name']})\n";
        }
    }
    
    // 6. 提供解决方案
    echo "\n6. 解决方案建议...\n";
    echo "   ✓ 分组已创建完成\n";
    echo "   ⚠️  用户组关系需要通过SVNAdmin的UpdGroupMember方法建立\n";
    echo "   ⚠️  层级关系需要通过authz文件的组包含语法建立\n";
    echo "   ✓ 建议修复WeComSync.php中的addUserToGroup方法\n";
    echo "   ✓ 建议实现层级关系同步逻辑\n";
    
    // 7. 检查authz文件
    echo "\n7. 检查authz文件...\n";
    $authzPath = '/home/svnadmin/authz';
    if (file_exists($authzPath)) {
        $authzContent = file_get_contents($authzPath);
        $authzLines = explode("\n", $authzContent);
        $groupLines = array_filter($authzLines, function($line) {
            return strpos($line, '=') !== false && strpos($line, '@') === false;
        });
        echo "   authz文件存在，包含 " . count($groupLines) . " 个组定义行\n";
        
        // 显示前几个组定义
        $groupCount = 0;
        foreach ($authzLines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '@') === false && $groupCount < 3) {
                echo "   示例: " . trim($line) . "\n";
                $groupCount++;
            }
        }
    } else {
        echo "   ⚠️  authz文件不存在: $authzPath\n";
    }
    
    echo "\n=== 分析完成 ===\n";
    echo "\n下一步：修复WeComSync.php中的用户组关系管理逻辑\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
