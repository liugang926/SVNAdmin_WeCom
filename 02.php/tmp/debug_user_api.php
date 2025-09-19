<?php
/**
 * 调试企业微信用户API调用
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义BASE_PATH
define('BASE_PATH', '/var/www/html');

// 包含必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/app/service/WeComAPI.php';

use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 调试企业微信用户API调用 ===\n\n";

try {
    // 创建API服务实例
    $apiService = new \app\service\WeComAPI([]);
    
    echo "1. 获取访问令牌...\n";
    $token = $apiService->getAccessToken();
    if ($token) {
        echo "   ✓ 访问令牌获取成功: " . substr($token, 0, 20) . "...\n";
    } else {
        echo "   ✗ 访问令牌获取失败\n";
        exit(1);
    }
    
    echo "\n2. 获取部门列表...\n";
    $departments = $apiService->getDepartments();
    if ($departments && isset($departments['department'])) {
        echo "   ✓ 部门列表获取成功，共 " . count($departments['department']) . " 个部门\n";
        
        // 显示前3个部门
        foreach (array_slice($departments['department'], 0, 3) as $dept) {
            echo "     - {$dept['name']} (ID: {$dept['id']})\n";
        }
    } else {
        echo "   ✗ 部门列表获取失败\n";
        var_dump($departments);
    }
    
    echo "\n3. 获取用户列表（根部门递归）...\n";
    // 企业微信根部门ID=1可能无法直接查询，尝试递归获取所有子部门用户
    $allUsers = [];
    $rootDepartments = array_filter($departments, function($dept) {
        return $dept['parentid'] == 1; // 找到根部门下的直接子部门
    });
    
    echo "   找到 " . count($rootDepartments) . " 个根级子部门\n";
    
    foreach (array_slice($rootDepartments, 0, 3) as $dept) { // 只测试前3个部门
        try {
            echo "   测试部门: {$dept['name']} (ID: {$dept['id']})\n";
            $deptUsers = $apiService->getDepartmentUsersDetail($dept['id'], false);
            if ($deptUsers) {
                echo "     ✓ 获取到 " . count($deptUsers) . " 个用户\n";
                $allUsers = array_merge($allUsers, $deptUsers);
                
                // 显示前2个用户
                foreach (array_slice($deptUsers, 0, 2) as $user) {
                    echo "       - {$user['name']} (ID: {$user['userid']})\n";
                }
            } else {
                echo "     ✗ 该部门无用户\n";
            }
        } catch (\Exception $e) {
            echo "     ✗ 获取失败: " . $e->getMessage() . "\n";
        }
    }
    
    $users = $allUsers;
    if ($users) {
        echo "   ✓ 用户列表获取成功，共 " . count($users) . " 个用户\n";
        
        // 显示前3个用户
        foreach (array_slice($users, 0, 3) as $user) {
            echo "     - {$user['name']} (ID: {$user['userid']}, 部门: " . implode(',', $user['department']) . ")\n";
        }
    } else {
        echo "   ✗ 用户列表获取失败\n";
        echo "   响应内容: ";
        var_dump($users);
    }
    
    echo "\n4. 测试单个部门用户获取...\n";
    if ($departments && isset($departments[0])) {
        $firstDept = $departments[0];
        echo "   测试部门: {$firstDept['name']} (ID: {$firstDept['id']})\n";
        
        $deptUsers = $apiService->getDepartmentUsersDetail($firstDept['id'], false);
        if ($deptUsers) {
            echo "   ✓ 部门用户获取成功，共 " . count($deptUsers) . " 个用户\n";
            
            // 显示前3个用户
            foreach (array_slice($deptUsers, 0, 3) as $user) {
                echo "     - {$user['name']} (ID: {$user['userid']})\n";
            }
        } else {
            echo "   ✗ 部门用户获取失败\n";
            echo "   响应内容: ";
            var_dump($deptUsers);
        }
    }
    
} catch (\Exception $e) {
    echo "   ✗ API调用异常: " . $e->getMessage() . "\n";
    echo "   堆栈: " . $e->getTraceAsString() . "\n";
}

echo "\n=== 调试完成 ===\n";
