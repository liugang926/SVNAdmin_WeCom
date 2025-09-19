<?php
/**
 * 测试单个用户的组关系同步
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
use app\service\WeComSync;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 测试单个用户组关系同步 ===\n\n";

try {
    // 1. 获取一个测试用户
    $testUser = $database->get('wecom_users', '*', [
        'svn_user_id[!]' => null,
        'LIMIT' => 1
    ]);
    
    if (!$testUser) {
        echo "没有找到测试用户\n";
        exit(1);
    }
    
    echo "测试用户: {$testUser['real_name']} ({$testUser['wecom_user_id']})\n";
    echo "SVN用户ID: {$testUser['svn_user_id']}\n";
    
    // 2. 获取用户的部门
    $departmentIds = json_decode($testUser['department_ids'] ?? '[]', true);
    echo "部门ID: " . implode(', ', $departmentIds) . "\n";
    
    // 3. 获取对应的SVN组
    $targetGroups = [];
    foreach ($departmentIds as $deptId) {
        $dept = $database->get('wecom_departments', '*', ['wecom_dept_id' => $deptId]);
        if ($dept && $dept['svn_group_id']) {
            $group = $database->get('svn_groups', '*', ['svn_group_id' => $dept['svn_group_id']]);
            if ($group) {
                $targetGroups[] = [
                    'dept_name' => $dept['dept_name'],
                    'svn_group_id' => $dept['svn_group_id'],
                    'svn_group_name' => $group['svn_group_name']
                ];
            }
        }
    }
    
    echo "目标分组: " . count($targetGroups) . " 个\n";
    foreach ($targetGroups as $group) {
        echo "  - {$group['svn_group_name']} (来自: {$group['dept_name']})\n";
    }
    
    if (empty($targetGroups)) {
        echo "没有目标分组，跳过测试\n";
        exit(0);
    }
    
    // 4. 检查authz文件当前状态
    echo "\n检查authz文件当前状态:\n";
    $authzContent = file_get_contents('/home/svnadmin/authz');
    echo "authz文件行数: " . count(explode("\n", $authzContent)) . "\n";
    
    // 5. 创建WeComSync实例并测试添加用户到组
    $syncService = new WeComSync([]);
    
    // 使用反射调用私有方法
    $reflection = new ReflectionClass($syncService);
    $addUserMethod = $reflection->getMethod('addUserToGroup');
    $addUserMethod->setAccessible(true);
    
    $firstGroup = $targetGroups[0];
    echo "\n测试添加用户到分组: {$firstGroup['svn_group_name']}\n";
    
    try {
        $addUserMethod->invoke($syncService, $testUser['svn_user_id'], $firstGroup['svn_group_id']);
        echo "✓ 添加成功\n";
        
        // 6. 验证authz文件更新
        echo "\n验证authz文件更新:\n";
        $newAuthzContent = file_get_contents('/home/svnadmin/authz');
        $newLines = count(explode("\n", $newAuthzContent));
        echo "authz文件新行数: $newLines\n";
        
        if ($newLines > 3) {
            echo "authz文件内容:\n";
            echo $newAuthzContent . "\n";
        } else {
            echo "authz文件没有更新\n";
        }
        
    } catch (Exception $e) {
        echo "✗ 添加失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
