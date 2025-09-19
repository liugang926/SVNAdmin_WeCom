<?php
// Define BASE_PATH if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/html');
}

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/service/Logs.php';
require_once BASE_PATH . '/app/service/WeComSync.php';

use app\service\WeComSync;
use app\service\Logs;
use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo($config['database']);

// 初始化日志服务
$logService = new Logs($database);

echo "=== 测试修复后的WeComSync::updateGroupUserCount方法 ===\n\n";

// 创建WeComSync实例
$wecomSync = new WeComSync($database);

// 获取几个测试组
$testGroups = $database->select('svn_groups', ['svn_group_id', 'svn_group_name', 'include_user_count'], [
    'ORDER' => ['include_user_count DESC'],
    'LIMIT' => 5
]);

if (empty($testGroups)) {
    echo "未找到任何SVN组进行测试。\n";
    exit;
}

echo "找到 " . count($testGroups) . " 个测试组\n\n";

// 使用反射调用私有方法
$reflection = new ReflectionClass($wecomSync);
$updateMethod = $reflection->getMethod('updateGroupUserCount');
$updateMethod->setAccessible(true);

$parseMethod = $reflection->getMethod('parseGroupUserCountFromAuthz');
$parseMethod->setAccessible(true);

// 读取authz文件内容用于测试parseGroupUserCountFromAuthz方法
$authzFilePath = $config['svn_authz_file'] ?? '/home/svnadmin/authz';
if (!file_exists($authzFilePath)) {
    echo "错误: authz文件不存在于 {$authzFilePath}\n";
    exit;
}
$authzContent = file_get_contents($authzFilePath);

foreach ($testGroups as $group) {
    $groupId = $group['svn_group_id'];
    $groupName = $group['svn_group_name'];
    $oldCount = $group['include_user_count'];
    
    echo "测试组: {$groupName} (ID: {$groupId})\n";
    echo "  数据库中的旧计数: {$oldCount}\n";
    
    // 测试parseGroupUserCountFromAuthz方法
    try {
        $parsedCount = $parseMethod->invoke($wecomSync, $authzContent, $groupName);
        echo "  authz文件中的实际计数: {$parsedCount}\n";
    } catch (Exception $e) {
        echo "  解析authz文件失败: " . $e->getMessage() . "\n";
        continue;
    }
    
    // 测试updateGroupUserCount方法
    try {
        $updateMethod->invoke($wecomSync, $groupId);
        echo "  ✓ updateGroupUserCount方法调用成功\n";
        
        // 检查数据库是否更新
        $updatedGroup = $database->get('svn_groups', ['include_user_count'], ['svn_group_id' => $groupId]);
        $newCount = $updatedGroup['include_user_count'];
        echo "  更新后的计数: {$newCount}\n";
        
        if ($newCount == $parsedCount) {
            echo "  ✅ 计数更新正确\n";
        } else {
            echo "  ❌ 计数更新不正确 (期望: {$parsedCount}, 实际: {$newCount})\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ updateGroupUserCount方法调用失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== 测试完成 ===\n";
