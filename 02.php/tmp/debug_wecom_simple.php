<?php
/*
 * 简单调试企业微信用户获取问题
 */

// 设置基础路径
define('BASE_PATH', '/var/www/html');

// 直接测试企业微信API
echo "=== 调试企业微信用户获取 ===\n";

// 读取企业微信配置
$wecomConfig = require BASE_PATH . '/config/wecom.php';
echo "企业微信配置加载完成\n";

// 检查配置
echo "Corp ID: " . (empty($wecomConfig['corp_id']) ? '未设置' : '已设置') . "\n";
echo "Corp Secret: " . (empty($wecomConfig['corp_secret']) ? '未设置' : '已设置') . "\n";
echo "Agent ID: " . (empty($wecomConfig['agent_id']) ? '未设置' : '已设置') . "\n";

// 读取数据库中的配置
try {
    $configDatabase = require BASE_PATH . '/config/database.php';
    $configSvn = require BASE_PATH . '/config/svn.php';
    
    if (array_key_exists('database_file', $configDatabase)) {
        $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
    }
    
    $database = new \Medoo\Medoo($configDatabase);
    
    echo "\n数据库连接成功\n";
    
    // 检查企业微信配置表
    $wecomDbConfig = $database->get('wecom_config', '*', ['id' => 1]);
    if ($wecomDbConfig) {
        echo "数据库中的企业微信配置:\n";
        echo "  - Corp ID: " . (empty($wecomDbConfig['corp_id']) ? '未设置' : '已设置') . "\n";
        echo "  - Corp Secret: " . (empty($wecomDbConfig['corp_secret']) ? '未设置' : '已设置') . "\n";
        echo "  - Agent ID: " . (empty($wecomDbConfig['agent_id']) ? '未设置' : '已设置') . "\n";
        echo "  - 根部门ID: " . ($wecomDbConfig['department_root_id'] ?? '未设置') . "\n";
    } else {
        echo "数据库中没有企业微信配置\n";
    }
    
    // 检查部门数据
    $deptCount = $database->count('wecom_departments');
    echo "\n部门数据: {$deptCount} 个\n";
    
    if ($deptCount > 0) {
        $sampleDepts = $database->select('wecom_departments', ['wecom_dept_id', 'dept_name'], [], ['LIMIT' => 3]);
        echo "示例部门:\n";
        foreach ($sampleDepts as $dept) {
            echo "  - ID: {$dept['wecom_dept_id']}, 名称: {$dept['dept_name']}\n";
        }
    }
    
    // 检查用户数据
    $userCount = $database->count('wecom_users');
    echo "\n用户数据: {$userCount} 个\n";
    
} catch (Exception $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
}
