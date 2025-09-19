<?php
echo "=== 测试新的备注格式 ===\n\n";

// 连接数据库
$dbPath = '/home/svnadmin/svnadmin.db';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "1. 当前用户备注格式:\n";
$stmt = $pdo->prepare("
    SELECT svn_user_name, svn_user_note 
    FROM svn_users 
    WHERE svn_user_note LIKE '%企业微信%' 
    ORDER BY svn_user_name 
    LIMIT 10
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "  未找到包含企业微信字样的用户备注\n";
} else {
    foreach ($users as $user) {
        echo "  {$user['svn_user_name']}: {$user['svn_user_note']}\n";
    }
}

echo "\n2. 当前分组备注格式:\n";
$stmt = $pdo->prepare("
    SELECT svn_group_name, svn_group_note 
    FROM svn_groups 
    WHERE svn_group_note LIKE '%企业微信%' 
    ORDER BY svn_group_name 
    LIMIT 10
");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($groups)) {
    echo "  未找到包含企业微信字样的分组备注\n";
} else {
    foreach ($groups as $group) {
        echo "  {$group['svn_group_name']}: {$group['svn_group_note']}\n";
    }
}

echo "\n3. 模拟新的备注格式:\n";

// 模拟用户备注格式
$userName = "张三";
$userId = "zhangsan";
$currentTime = date('Y-m-d H:i:s');
$newUserNote = "{$userName} ({$userId}) - {$currentTime} - 企业微信同步";
echo "  用户备注新格式: {$newUserNote}\n";

// 模拟分组备注格式  
$departmentName = "技术部";
$departmentId = "123";
$newGroupNote = "{$departmentName} (ID: {$departmentId}) - {$currentTime} - 企业微信同步";
echo "  分组备注新格式: {$newGroupNote}\n";

echo "\n4. 格式对比:\n";
echo "  旧格式: 企业微信同步: 张三 (zhangsan) - 2024-01-01 12:00:00\n";
echo "  新格式: 张三 (zhangsan) - 2024-01-01 12:00:00 - 企业微信同步\n";
echo "\n  ✅ 新格式将'企业微信'字样移到了最后面\n";

echo "\n=== 测试完成 ===\n";
echo "要应用新格式，需要触发一次企业微信同步来更新现有的备注\n";
