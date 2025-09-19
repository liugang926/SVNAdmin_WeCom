<?php
echo "=== 测试同步过程中的用户计数更新 ===\n\n";

// 模拟WeComSync中的updateGroupUserCount方法
function parseGroupUserCountFromAuthz($authzContent, $groupName) {
    try {
        $lines = explode("\n", $authzContent);
        $inGroupsSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            if ($line === '[groups]') {
                $inGroupsSection = true;
                continue;
            }
            
            if (strpos($line, '[') === 0) { // New section starts
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
        
        return 0; // 组不存在或没有成员
        
    } catch (Exception $e) {
        echo "解析authz文件失败: " . $e->getMessage() . "\n";
        return 0;
    }
}

// 连接数据库
$dbPath = '/home/svnadmin/svnadmin.db';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 读取authz文件
$authzFilePath = '/home/svnadmin/authz';
if (!file_exists($authzFilePath)) {
    echo "错误: authz文件不存在\n";
    exit;
}
$authzContent = file_get_contents($authzFilePath);

// 获取测试组
$testGroups = ['软件', '机械', '调试'];

echo "开始测试用户计数更新...\n\n";

foreach ($testGroups as $groupName) {
    echo "处理组: {$groupName}\n";
    
    // 获取组ID和当前数据库中的计数
    $stmt = $pdo->prepare("SELECT svn_group_id, include_user_count FROM svn_groups WHERE svn_group_name = ?");
    $stmt->execute([$groupName]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo "  ❌ 组不存在于数据库中\n";
        continue;
    }
    
    $groupId = $group['svn_group_id'];
    $dbCount = $group['include_user_count'];
    
    // 从authz文件解析实际用户数
    $actualCount = parseGroupUserCountFromAuthz($authzContent, $groupName);
    
    echo "  数据库中的计数: {$dbCount}\n";
    echo "  authz文件中的实际计数: {$actualCount}\n";
    
    if ($dbCount != $actualCount) {
        echo "  🔄 更新数据库计数...\n";
        
        // 更新数据库
        $updateStmt = $pdo->prepare("UPDATE svn_groups SET include_user_count = ? WHERE svn_group_id = ?");
        $updateStmt->execute([$actualCount, $groupId]);
        
        echo "  ✅ 更新成功: {$dbCount} -> {$actualCount}\n";
    } else {
        echo "  ✓ 计数已正确，无需更新\n";
    }
    
    echo "\n";
}

// 验证更新结果
echo "=== 验证更新结果 ===\n";
$stmt = $pdo->prepare("SELECT svn_group_name, include_user_count FROM svn_groups WHERE svn_group_name IN ('" . implode("','", $testGroups) . "') ORDER BY include_user_count DESC");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $result) {
    echo "{$result['svn_group_name']}: {$result['include_user_count']} 个用户\n";
}

echo "\n=== 测试完成 ===\n";
