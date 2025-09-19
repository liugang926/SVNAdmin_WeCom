<?php
/**
 * 测试部门映射功能
 */

// 设置基础路径
define('BASE_PATH', '/var/www/html');

// 引入必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

try {
    echo "=== 测试部门映射功能 ===\n";
    
    // 获取所有部门信息
    $allDepartments = $database->select('wecom_departments', [
        'wecom_dept_id',
        'dept_name'
    ], [
        'LIMIT' => 10
    ]);
    
    echo "部门信息:\n";
    foreach ($allDepartments as $dept) {
        echo "  ID: {$dept['wecom_dept_id']} => 名称: {$dept['dept_name']}\n";
    }
    
    // 创建部门ID到名称的映射
    $departmentMap = [];
    foreach ($allDepartments as $dept) {
        $departmentMap[$dept['wecom_dept_id']] = $dept['dept_name'];
    }
    
    echo "\n部门映射数组:\n";
    print_r($departmentMap);
    
    // 测试用户的部门信息
    echo "\n=== 测试用户部门信息 ===\n";
    $users = $database->select('wecom_users', [
        'wecom_user_id',
        'real_name', 
        'department_ids'
    ], [
        'LIMIT' => 5
    ]);
    
    foreach ($users as $user) {
        echo "\n用户: {$user['real_name']} ({$user['wecom_user_id']})\n";
        echo "  部门IDs: {$user['department_ids']}\n";
        
        if ($user['department_ids']) {
            $deptIds = explode(',', $user['department_ids']);
            echo "  所属部门:\n";
            foreach ($deptIds as $deptId) {
                $trimmedId = trim($deptId);
                $deptName = $departmentMap[$trimmedId] ?? "未知部门({$trimmedId})";
                echo "    - {$deptName} (ID: {$trimmedId})\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
