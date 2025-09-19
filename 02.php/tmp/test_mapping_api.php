<?php
/**
 * 测试GetMapping API返回的数据
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
    
    echo "=== 测试GetMapping API数据 ===\n";
    
    // 获取用户映射（使用修复后的查询）
    $users = $database->select('wecom_users', [
        '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
    ], [
        'wecom_users.wecom_user_id(wecom_userid)',
        'wecom_users.real_name(wecom_name)',
        'wecom_users.email(wecom_email)',
        'wecom_users.mobile(wecom_mobile)',
        'wecom_users.department_ids(wecom_department_ids)',
        'svn_users.svn_user_name',
        'svn_users.svn_user_note'
    ], [
        'LIMIT' => 5
    ]);
    
    echo "查询结果数量: " . count($users) . "\n";
    echo "前5个用户的映射数据:\n";
    
    foreach ($users as $index => $user) {
        echo "\n用户 " . ($index + 1) . ":\n";
        echo "  wecom_userid: " . ($user['wecom_userid'] ?? 'NULL') . "\n";
        echo "  wecom_name: " . ($user['wecom_name'] ?? 'NULL') . "\n";
        echo "  wecom_email: " . ($user['wecom_email'] ?? 'NULL') . "\n";
        echo "  svn_user_name: " . ($user['svn_user_name'] ?? 'NULL') . "\n";
        echo "  svn_user_note: " . ($user['svn_user_note'] ?? 'NULL') . "\n";
        echo "  映射状态: " . ($user['svn_user_name'] ? '已映射' : '未映射') . "\n";
    }
    
    // 测试原始数据库查询
    echo "\n=== 原始数据库查询测试 ===\n";
    $rawQuery = $database->query("
        SELECT w.wecom_user_id, w.real_name, w.svn_user_id, s.svn_user_name, s.svn_user_note
        FROM wecom_users w 
        LEFT JOIN svn_users s ON w.svn_user_id = s.svn_user_id 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rawQuery as $index => $row) {
        echo "\n原始查询用户 " . ($index + 1) . ":\n";
        echo "  wecom_user_id: " . ($row['wecom_user_id'] ?? 'NULL') . "\n";
        echo "  real_name: " . ($row['real_name'] ?? 'NULL') . "\n";
        echo "  svn_user_id: " . ($row['svn_user_id'] ?? 'NULL') . "\n";
        echo "  svn_user_name: " . ($row['svn_user_name'] ?? 'NULL') . "\n";
        echo "  映射状态: " . ($row['svn_user_name'] ? '已映射' : '未映射') . "\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
