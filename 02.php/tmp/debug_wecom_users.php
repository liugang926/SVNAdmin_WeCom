<?php
/*
 * 调试企业微信用户获取问题
 */

// 设置基础路径
define('BASE_PATH', '/var/www/html');

// 加载必要的文件
require_once BASE_PATH . '/app/service/base/Base.php';

use app\service\WeComAPI;

try {
    // 创建WeComAPI实例
    $wecomAPI = new WeComAPI([]);
    
    echo "=== 调试企业微信用户获取 ===\n";
    
    // 1. 测试获取部门列表
    echo "1. 获取部门列表...\n";
    $departments = $wecomAPI->getDepartments();
    echo "部门数量: " . count($departments) . "\n";
    
    if (count($departments) > 0) {
        echo "前3个部门:\n";
        for ($i = 0; $i < min(3, count($departments)); $i++) {
            $dept = $departments[$i];
            echo "  - ID: {$dept['id']}, 名称: {$dept['name']}\n";
        }
    }
    
    // 2. 测试获取第一个部门的用户
    if (count($departments) > 0) {
        $firstDept = $departments[0];
        echo "\n2. 获取部门 {$firstDept['name']} (ID: {$firstDept['id']}) 的用户...\n";
        
        try {
            $users = $wecomAPI->getDepartmentUsersDetail($firstDept['id'], false);
            echo "用户数量: " . count($users) . "\n";
            
            if (count($users) > 0) {
                echo "前3个用户:\n";
                for ($i = 0; $i < min(3, count($users)); $i++) {
                    $user = $users[$i];
                    echo "  - ID: {$user['userid']}, 姓名: {$user['name']}\n";
                }
            }
        } catch (Exception $e) {
            echo "获取用户失败: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. 测试getFullOrganization方法
    echo "\n3. 测试getFullOrganization方法...\n";
    try {
        $orgData = $wecomAPI->getFullOrganization();
        echo "组织架构数据:\n";
        echo "  - 部门数量: " . count($orgData['departments']) . "\n";
        echo "  - 用户数量: " . count($orgData['users']) . "\n";
        echo "  - 部门用户映射数量: " . count($orgData['department_users']) . "\n";
        
        if (count($orgData['users']) > 0) {
            echo "前3个用户:\n";
            for ($i = 0; $i < min(3, count($orgData['users'])); $i++) {
                $user = $orgData['users'][$i];
                echo "  - ID: {$user['userid']}, 姓名: {$user['name']}, 部门: " . json_encode($user['department'] ?? []) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "getFullOrganization失败: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
