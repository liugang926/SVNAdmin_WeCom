<?php
echo "=== 批量更新现有备注格式 ===\n\n";

// 连接数据库
$dbPath = '/home/svnadmin/svnadmin.db';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$userUpdated = 0;
$groupUpdated = 0;

echo "1. 更新用户备注格式...\n";

// 获取所有包含"企业微信同步:"的用户备注
$stmt = $pdo->prepare("
    SELECT svn_user_id, svn_user_name, svn_user_note 
    FROM svn_users 
    WHERE svn_user_note LIKE '企业微信同步:%'
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  找到 " . count($users) . " 个需要更新的用户备注\n";

foreach ($users as $user) {
    $oldNote = $user['svn_user_note'];
    
    // 解析旧格式: "企业微信同步: 张三 (zhangsan) - 2024-01-01 12:00:00"
    if (preg_match('/^企业微信同步:\s*(.+)\s*-\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})$/', $oldNote, $matches)) {
        $nameAndId = trim($matches[1]);
        $dateTime = $matches[2];
        
        // 生成新格式: "张三 (zhangsan) - 2024-01-01 12:00:00 - 企业微信同步"
        $newNote = "{$nameAndId} - {$dateTime} - 企业微信同步";
        
        // 更新数据库
        $updateStmt = $pdo->prepare("UPDATE svn_users SET svn_user_note = ? WHERE svn_user_id = ?");
        $updateStmt->execute([$newNote, $user['svn_user_id']]);
        
        $userUpdated++;
        echo "  ✓ {$user['svn_user_name']}: 已更新\n";
    }
}

echo "\n2. 更新分组备注格式...\n";

// 获取所有包含"企业微信同步:"或"企业微信同步修复:"的分组备注
$stmt = $pdo->prepare("
    SELECT svn_group_id, svn_group_name, svn_group_note 
    FROM svn_groups 
    WHERE svn_group_note LIKE '企业微信同步:%' OR svn_group_note LIKE '企业微信同步修复:%'
");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  找到 " . count($groups) . " 个需要更新的分组备注\n";

foreach ($groups as $group) {
    $oldNote = $group['svn_group_note'];
    $newNote = '';
    
    // 解析旧格式1: "企业微信同步: 技术部 (ID: 123) - 2024-01-01 12:00:00"
    if (preg_match('/^企业微信同步:\s*(.+)\s*\(ID:\s*(\d+)\)\s*-\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})$/', $oldNote, $matches)) {
        $deptName = trim($matches[1]);
        $deptId = $matches[2];
        $dateTime = $matches[3];
        
        // 生成新格式: "技术部 (ID: 123) - 2024-01-01 12:00:00 - 企业微信同步"
        $newNote = "{$deptName} (ID: {$deptId}) - {$dateTime} - 企业微信同步";
    }
    // 解析旧格式2: "企业微信同步修复: 技术部 (ID: 123) - 2024-01-01 12:00:00"
    elseif (preg_match('/^企业微信同步修复:\s*(.+)\s*\(ID:\s*(\d+)\)\s*-\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})$/', $oldNote, $matches)) {
        $deptName = trim($matches[1]);
        $deptId = $matches[2];
        $dateTime = $matches[3];
        
        // 生成新格式: "技术部 (ID: 123) - 2024-01-01 12:00:00 - 企业微信同步"
        $newNote = "{$deptName} (ID: {$deptId}) - {$dateTime} - 企业微信同步";
    }
    
    if (!empty($newNote)) {
        // 更新数据库
        $updateStmt = $pdo->prepare("UPDATE svn_groups SET svn_group_note = ? WHERE svn_group_id = ?");
        $updateStmt->execute([$newNote, $group['svn_group_id']]);
        
        $groupUpdated++;
        echo "  ✓ {$group['svn_group_name']}: 已更新\n";
    } else {
        echo "  ⚠ {$group['svn_group_name']}: 格式无法识别，跳过\n";
    }
}

echo "\n=== 更新完成 ===\n";
echo "用户备注更新: {$userUpdated} 个\n";
echo "分组备注更新: {$groupUpdated} 个\n";

if ($userUpdated > 0 || $groupUpdated > 0) {
    echo "\n验证更新结果:\n";
    
    if ($userUpdated > 0) {
        echo "\n用户备注示例 (前5个):\n";
        $stmt = $pdo->prepare("
            SELECT svn_user_name, svn_user_note 
            FROM svn_users 
            WHERE svn_user_note LIKE '%企业微信同步' 
            ORDER BY svn_user_name 
            LIMIT 5
        ");
        $stmt->execute();
        $updatedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($updatedUsers as $user) {
            echo "  {$user['svn_user_name']}: {$user['svn_user_note']}\n";
        }
    }
    
    if ($groupUpdated > 0) {
        echo "\n分组备注示例 (前5个):\n";
        $stmt = $pdo->prepare("
            SELECT svn_group_name, svn_group_note 
            FROM svn_groups 
            WHERE svn_group_note LIKE '%企业微信同步' 
            ORDER BY svn_group_name 
            LIMIT 5
        ");
        $stmt->execute();
        $updatedGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($updatedGroups as $group) {
            echo "  {$group['svn_group_name']}: {$group['svn_group_note']}\n";
        }
    }
    
    echo "\n✅ 所有备注格式已更新，'企业微信'字样现在显示在最后面\n";
} else {
    echo "\n⚠️  没有找到需要更新的备注\n";
}

echo "\n=== 脚本完成 ===\n";
