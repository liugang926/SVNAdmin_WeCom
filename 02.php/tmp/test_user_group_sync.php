<?php
/**
 * 测试用户组关系同步
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

echo "=== 测试用户组关系同步 ===\n\n";

try {
    // 创建WeComSync实例
    $syncService = new WeComSync([]);
    
    // 1. 测试少量用户的组关系同步
    echo "1. 获取测试用户...\n";
    
    $testUsers = $database->select('wecom_users', '*', [
        'svn_user_id[!]' => null,
        'LIMIT' => 3
    ]);
    
    echo "   找到 " . count($testUsers) . " 个测试用户\n\n";
    
    foreach ($testUsers as $user) {
        echo "2. 处理用户: {$user['real_name']} ({$user['wecom_user_id']})\n";
        
        // 获取用户的部门
        $departmentIds = json_decode($user['department_ids'] ?? '[]', true);
        echo "   部门ID: " . implode(', ', $departmentIds) . "\n";
        
        // 获取对应的SVN组
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
        
        echo "   目标分组: " . count($targetGroups) . " 个\n";
        foreach ($targetGroups as $group) {
            echo "     - {$group['svn_group_name']} (来自: {$group['dept_name']})\n";
        }
        
        // 测试添加用户到第一个组
        if (!empty($targetGroups) && $user['svn_user_id']) {
            $firstGroup = $targetGroups[0];
            echo "   测试添加到分组: {$firstGroup['svn_group_name']}\n";
            
            try {
                // 使用反射调用私有方法
                $reflection = new ReflectionClass($syncService);
                $addUserMethod = $reflection->getMethod('addUserToGroup');
                $addUserMethod->setAccessible(true);
                
                $addUserMethod->invoke($syncService, $user['svn_user_id'], $firstGroup['svn_group_id']);
                echo "   ✓ 添加成功\n";
                
                // 验证结果
                $svnUser = $database->get('svn_users', ['svn_user_name'], ['svn_user_id' => $user['svn_user_id']]);
                if ($svnUser) {
                    echo "   验证用户: {$svnUser['svn_user_name']}\n";
                }
                
            } catch (Exception $e) {
                echo "   ✗ 添加失败: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    // 3. 检查authz文件
    echo "3. 检查authz文件更新...\n";
    $authzPath = '/home/svnadmin/authz';
    if (file_exists($authzPath)) {
        $authzContent = file_get_contents($authzPath);
        $authzLines = explode("\n", $authzContent);
        
        // 查找组定义
        $groupDefinitions = [];
        foreach ($authzLines as $line) {
            $line = trim($line);
            if (strpos($line, '=') !== false && strpos($line, '@') === false && !empty($line) && $line[0] !== '#') {
                $groupDefinitions[] = $line;
            }
        }
        
        echo "   authz文件包含 " . count($groupDefinitions) . " 个组定义\n";
        
        if (!empty($groupDefinitions)) {
            echo "   最新的组定义（前5个）:\n";
            foreach (array_slice($groupDefinitions, -5) as $def) {
                echo "     $def\n";
            }
        }
    } else {
        echo "   ⚠️  authz文件不存在\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
