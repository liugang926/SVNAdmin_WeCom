<?php
echo "=== 测试用户添加到组后的计数更新 ===\n\n";

// 直接在容器环境中运行，使用绝对路径
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';
require_once '/var/www/html/app/service/base/Base.php';
require_once '/var/www/html/app/service/Logs.php';
require_once '/var/www/html/extension/Witersen/SVNAdmin.php';

use app\service\Logs;
use Medoo\Medoo;
use Witersen\SVNAdmin;

// 初始化数据库连接
$database = new Medoo($config['database']);
$logService = new Logs($database);
$svnAdmin = new SVNAdmin($config['svn_config']);

// 选择一个测试组和用户
$testGroup = $database->get('svn_groups', ['svn_group_id', 'svn_group_name', 'include_user_count'], [
    'svn_group_name' => '软件'
]);

$testUser = $database->get('svn_users', ['svn_user_id', 'svn_user_name'], [
    'svn_user_name' => 'testuser'
]);

if (!$testGroup) {
    echo "未找到测试组 '软件'\n";
    exit;
}

if (!$testUser) {
    echo "未找到测试用户 'testuser'，创建一个测试用户...\n";
    // 创建测试用户
    $database->insert('svn_users', [
        'svn_user_name' => 'testuser',
        'svn_user_pass' => password_hash('testpass', PASSWORD_DEFAULT),
        'svn_user_note' => '测试用户'
    ]);
    $testUser = [
        'svn_user_id' => $database->id(),
        'svn_user_name' => 'testuser'
    ];
    echo "创建测试用户成功: ID={$testUser['svn_user_id']}\n";
}

$groupId = $testGroup['svn_group_id'];
$groupName = $testGroup['svn_group_name'];
$userId = $testUser['svn_user_id'];
$userName = $testUser['svn_user_name'];

echo "测试组: {$groupName} (ID: {$groupId})\n";
echo "测试用户: {$userName} (ID: {$userId})\n";
echo "当前组用户计数: {$testGroup['include_user_count']}\n\n";

// 读取authz文件
$authzFilePath = $config['svn_authz_file'] ?? '/home/svnadmin/authz';
$authzContent = file_get_contents($authzFilePath);

// 直接解析authz文件获取当前实际用户数
function parseGroupUserCount($authzContent, $groupName) {
    $lines = explode("\n", $authzContent);
    $inGroupsSection = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) continue;
        
        if ($line === '[groups]') {
            $inGroupsSection = true;
            continue;
        }
        
        if (str_starts_with($line, '[')) {
            $inGroupsSection = false;
            continue;
        }
        
        if ($inGroupsSection) {
            if (preg_match('/^([A-Za-z0-9-_.一-龥]+)\s*=\s*(.*)$/u', $line, $matches)) {
                $currentGroupName = $matches[1];
                if ($currentGroupName === $groupName) {
                    $membersString = $matches[2];
                    $members = array_filter(array_map('trim', explode(',', $membersString)));
                    return count($members);
                }
            }
        }
    }
    
    return 0;
}

$currentActualCount = parseGroupUserCount($authzContent, $groupName);
echo "authz文件中当前实际用户数: {$currentActualCount}\n\n";

// 检查用户是否已经在组中
$isUserInGroup = false;
$lines = explode("\n", $authzContent);
$inGroupsSection = false;

foreach ($lines as $line) {
    $line = trim($line);
    
    if ($line === '[groups]') {
        $inGroupsSection = true;
        continue;
    }
    
    if (str_starts_with($line, '[')) {
        $inGroupsSection = false;
        continue;
    }
    
    if ($inGroupsSection) {
        if (preg_match('/^([A-Za-z0-9-_.一-龥]+)\s*=\s*(.*)$/u', $line, $matches)) {
            $currentGroupName = $matches[1];
            if ($currentGroupName === $groupName) {
                $membersString = $matches[2];
                $members = array_filter(array_map('trim', explode(',', $membersString)));
                $isUserInGroup = in_array($userName, $members);
                break;
            }
        }
    }
}

if ($isUserInGroup) {
    echo "用户 {$userName} 已经在组 {$groupName} 中，先移除...\n";
    $result = $svnAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'delete');
    if (is_string($result)) {
        file_put_contents($authzFilePath, $result);
        $authzContent = $result;
        echo "✓ 用户移除成功\n";
    } else {
        echo "❌ 用户移除失败: 错误码 {$result}\n";
        exit;
    }
} else {
    echo "用户 {$userName} 不在组 {$groupName} 中\n";
}

echo "\n--- 开始测试添加用户到组 ---\n";

// 添加用户到组
$result = $svnAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'add');

if (is_string($result)) {
    // 成功，写入更新后的authz文件
    file_put_contents($authzFilePath, $result);
    echo "✓ 用户添加到组成功\n";
    
    // 验证authz文件中的用户数
    $newActualCount = parseGroupUserCount($result, $groupName);
    echo "authz文件中新的用户数: {$newActualCount}\n";
    
    // 模拟WeComSync的updateGroupUserCount方法
    echo "\n--- 模拟updateGroupUserCount方法 ---\n";
    
    $database->update('svn_groups', [
        'include_user_count' => $newActualCount
    ], [
        'svn_group_id' => $groupId
    ]);
    
    // 验证数据库更新
    $updatedGroup = $database->get('svn_groups', ['include_user_count'], ['svn_group_id' => $groupId]);
    $dbCount = $updatedGroup['include_user_count'];
    
    echo "数据库中更新后的用户计数: {$dbCount}\n";
    
    if ($dbCount == $newActualCount) {
        echo "✅ 用户计数同步成功！\n";
    } else {
        echo "❌ 用户计数同步失败 (期望: {$newActualCount}, 实际: {$dbCount})\n";
    }
    
} else {
    echo "❌ 添加用户到组失败: 错误码 {$result}\n";
    $errorMessages = [
        720 => 'authz文件格式错误',
        901 => '不支持的对象类型',
        803 => '用户已存在于分组中'
    ];
    $errorMsg = $errorMessages[$result] ?? "未知错误码: $result";
    echo "错误信息: {$errorMsg}\n";
}

echo "\n=== 测试完成 ===\n";
