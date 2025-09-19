<?php
echo "=== 测试分组层级关系同步 ===\n\n";

// 连接数据库
$dbPath = '/home/svnadmin/svnadmin.db';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 查看企业微信部门层级关系
echo "1. 企业微信部门层级关系:\n";
$stmt = $pdo->prepare("
    SELECT 
        d.wecom_dept_id,
        d.dept_name,
        d.parent_id,
        p.dept_name as parent_name,
        g.svn_group_name
    FROM wecom_departments d
    LEFT JOIN wecom_departments p ON d.parent_id = p.wecom_dept_id
    LEFT JOIN svn_groups g ON d.svn_group_id = g.svn_group_id
    WHERE d.parent_id > 1
    ORDER BY d.parent_id, d.wecom_dept_id
    LIMIT 10
");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($departments as $dept) {
    echo "  {$dept['dept_name']} (ID: {$dept['wecom_dept_id']}) -> 父部门: {$dept['parent_name']} (ID: {$dept['parent_id']})\n";
    echo "    对应SVN组: {$dept['svn_group_name']}\n\n";
}

// 查看当前authz文件中的组定义
echo "2. 当前authz文件中的组定义 (前20行):\n";
$authzPath = '/home/svnadmin/authz';
$authzContent = file_get_contents($authzPath);
$lines = explode("\n", $authzContent);
$inGroups = false;
$groupLines = 0;

foreach ($lines as $line) {
    $line = trim($line);
    
    if ($line === '[groups]') {
        $inGroups = true;
        echo "  [groups]\n";
        continue;
    }
    
    if (strpos($line, '[') === 0 && $line !== '[groups]') {
        break;
    }
    
    if ($inGroups && !empty($line)) {
        echo "  {$line}\n";
        $groupLines++;
        if ($groupLines >= 20) {
            echo "  ...(更多组定义)\n";
            break;
        }
    }
}

// 检查是否有组包含其他组
echo "\n3. 检查组是否包含其他组:\n";
$lines = explode("\n", $authzContent);
$inGroups = false;
$foundGroupRefs = false;

foreach ($lines as $line) {
    $line = trim($line);
    
    if ($line === '[groups]') {
        $inGroups = true;
        continue;
    }
    
    if (strpos($line, '[') === 0 && $line !== '[groups]') {
        break;
    }
    
    if ($inGroups && !empty($line) && strpos($line, '@') !== false) {
        echo "  {$line}\n";
        $foundGroupRefs = true;
    }
}

if (!$foundGroupRefs) {
    echo "  ❌ 未找到任何组包含其他组的定义\n";
    echo "  这说明企业微信的部门层级关系还没有同步到authz文件中\n";
} else {
    echo "  ✅ 找到了组包含其他组的定义\n";
}

// 检查数据库中的组计数
echo "\n4. 数据库中的组计数信息:\n";
$stmt = $pdo->prepare("
    SELECT svn_group_name, include_user_count, include_group_count, include_aliase_count
    FROM svn_groups 
    WHERE include_group_count > 0 OR include_user_count > 0
    ORDER BY include_group_count DESC, include_user_count DESC
    LIMIT 10
");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($groups)) {
    echo "  ❌ 所有组的计数都为0\n";
} else {
    foreach ($groups as $group) {
        echo "  {$group['svn_group_name']}: 用户={$group['include_user_count']}, 组={$group['include_group_count']}, 别名={$group['include_aliase_count']}\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "如果看到组包含其他组的定义（@组名），说明层级关系同步正常\n";
echo "如果没有，需要触发一次企业微信同步来建立层级关系\n";
