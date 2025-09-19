<?php
/**
 * 调试分组创建问题
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

echo "=== 调试分组创建问题 ===\n\n";

try {
    // 1. 检查当前分组状态
    echo "1. 当前分组状态:\n";
    $svnGroupsCount = $database->count('svn_groups');
    $wecomDeptCount = $database->count('wecom_departments');
    $wecomDeptWithGroupCount = $database->count('wecom_departments', ['svn_group_id[!]' => null]);
    
    echo "   SVN分组数量: $svnGroupsCount\n";
    echo "   企业微信部门数量: $wecomDeptCount\n";
    echo "   关联了SVN分组的部门数量: $wecomDeptWithGroupCount\n\n";
    
    // 2. 检查部门和分组ID的关联
    echo "2. 检查部门分组关联:\n";
    $departments = $database->select('wecom_departments', ['dept_name', 'svn_group_id'], [], ['LIMIT' => 5]);
    foreach ($departments as $dept) {
        $groupExists = $database->has('svn_groups', ['svn_group_id' => $dept['svn_group_id']]);
        $status = $groupExists ? "✓存在" : "✗不存在";
        echo "   部门: {$dept['dept_name']} -> 分组ID: {$dept['svn_group_id']} ($status)\n";
    }
    echo "\n";
    
    // 3. 尝试手动创建一个分组
    echo "3. 测试手动创建分组:\n";
    $testGroupName = '测试分组_' . date('His');
    
    $database->insert('svn_groups', [
        'svn_group_name' => $testGroupName,
        'include_user_count' => 0,
        'include_group_count' => 0,
        'include_aliase_count' => 0
    ]);
    
    $newGroupId = $database->id();
    echo "   创建分组成功: $testGroupName (ID: $newGroupId)\n";
    
    // 验证创建结果
    $createdGroup = $database->get('svn_groups', '*', ['svn_group_id' => $newGroupId]);
    if ($createdGroup) {
        echo "   验证成功: 分组已存在于数据库中\n";
    } else {
        echo "   验证失败: 分组未找到\n";
    }
    echo "\n";
    
    // 4. 检查同步配置
    echo "4. 检查同步配置:\n";
    $config = require BASE_PATH . '/config/wecom.php';
    $autoCreate = $config['department_mapping']['auto_create_groups'] ?? 'undefined';
    echo "   auto_create_groups: " . ($autoCreate ? 'true' : 'false') . "\n";
    
    // 5. 模拟WeComSync的分组创建过程
    echo "\n5. 模拟WeComSync分组创建过程:\n";
    
    // 创建WeComSync实例
    $syncService = new \app\service\WeComSync([]);
    
    // 使用反射访问私有方法
    $reflection = new ReflectionClass($syncService);
    $createGroupMethod = $reflection->getMethod('createSvnGroup');
    $createGroupMethod->setAccessible(true);
    
    $existingSvnGroups = [];
    $testGroupName2 = '模拟创建分组_' . date('His');
    
    try {
        $groupId = $createGroupMethod->invoke($syncService, $testGroupName2, $existingSvnGroups);
        echo "   模拟创建成功: $testGroupName2 (ID: $groupId)\n";
        
        // 验证
        $exists = $database->has('svn_groups', ['svn_group_id' => $groupId]);
        echo "   验证结果: " . ($exists ? "分组存在" : "分组不存在") . "\n";
        
    } catch (Exception $e) {
        echo "   模拟创建失败: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 调试完成 ===\n";
