<?php
/**
 * 调试企业微信用户获取问题
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含必要的文件
require_once '/var/www/html/extension/Medoo-1.7.10/src/Medoo.php';
require_once '/var/www/html/app/service/base/Base.php';
require_once '/var/www/html/app/service/WeComAPI.php';

use Medoo\Medoo;
use app\service\WeComAPI;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 企业微信用户获取调试 ===\n\n";

// 1. 检查配置
$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "配置不存在\n";
    exit(1);
}

// 2. 初始化WeComAPI服务
$wecomAPI = new WeComAPI([]);

echo "1. 测试获取Access Token:\n";
try {
    // 直接调用获取token的方法
    $reflection = new ReflectionClass($wecomAPI);
    $method = $reflection->getMethod('refreshAccessToken');
    $method->setAccessible(true);
    $result = $method->invoke($wecomAPI);
    
    if ($result) {
        echo "   ✓ Access Token获取成功\n";
    } else {
        echo "   ✗ Access Token获取失败\n";
    }
} catch (Exception $e) {
    echo "   ✗ 获取Token异常: " . $e->getMessage() . "\n";
}

echo "\n2. 测试获取部门列表:\n";
try {
    $departments = $wecomAPI->getDepartments(1);
    echo "   部门数量: " . count($departments) . "\n";
    if (count($departments) > 0) {
        echo "   第一个部门: " . $departments[0]['name'] . " (ID: " . $departments[0]['id'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ✗ 获取部门异常: " . $e->getMessage() . "\n";
}

echo "\n3. 测试获取用户列表:\n";
try {
    $departments = $wecomAPI->getDepartments(1);
    if (count($departments) > 0) {
        $firstDept = $departments[0];
        echo "   测试部门: " . $firstDept['name'] . " (ID: " . $firstDept['id'] . ")\n";
        
        $users = $wecomAPI->getDepartmentUsersDetail($firstDept['id'], false);
        echo "   用户数量: " . count($users) . "\n";
        
        if (count($users) > 0) {
            echo "   第一个用户: " . $users[0]['name'] . " (ID: " . $users[0]['userid'] . ")\n";
        } else {
            echo "   ⚠ 该部门没有用户\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ 获取用户异常: " . $e->getMessage() . "\n";
}

echo "\n4. 测试获取全量组织架构:\n";
try {
    $orgData = $wecomAPI->getFullOrganization(1);
    echo "   部门数量: " . count($orgData['departments']) . "\n";
    echo "   用户数量: " . count($orgData['users']) . "\n";
    
    if (count($orgData['users']) > 0) {
        echo "   第一个用户: " . $orgData['users'][0]['name'] . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ 获取组织架构异常: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";
?>
