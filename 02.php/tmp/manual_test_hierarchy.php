<?php
echo "=== 手动测试分组层级关系 ===\n\n";

// 读取authz文件
$authzPath = '/home/svnadmin/authz';
$authzContent = file_get_contents($authzPath);

echo "1. 当前8848实验室组的定义:\n";
$lines = explode("\n", $authzContent);
$inGroups = false;

foreach ($lines as $line) {
    $line = trim($line);
    
    if ($line === '[groups]') {
        $inGroups = true;
        continue;
    }
    
    if (strpos($line, '[') === 0 && $line !== '[groups]') {
        break;
    }
    
    if ($inGroups && !empty($line) && strpos($line, '8848实验室=') === 0) {
        echo "  {$line}\n";
        break;
    }
}

echo "\n2. 手动添加机械组和软件组到8848实验室...\n";

// 手动修改authz文件，将机械组和软件组添加到8848实验室
$newAuthzContent = '';
$lines = explode("\n", $authzContent);
$inGroups = false;
$modified = false;

foreach ($lines as $line) {
    $originalLine = $line;
    $line = trim($line);
    
    if ($line === '[groups]') {
        $inGroups = true;
        $newAuthzContent .= $originalLine . "\n";
        continue;
    }
    
    if (strpos($line, '[') === 0 && $line !== '[groups]') {
        $inGroups = false;
        $newAuthzContent .= $originalLine . "\n";
        continue;
    }
    
    if ($inGroups && !empty($line) && strpos($line, '8848实验室=') === 0) {
        // 修改8848实验室的定义，添加子组
        $parts = explode('=', $line, 2);
        $groupName = $parts[0];
        $currentMembers = isset($parts[1]) ? trim($parts[1]) : '';
        
        // 添加机械组和软件组引用
        $newMembers = $currentMembers;
        if (!empty($newMembers)) {
            $newMembers .= ',@机械组,@软件组';
        } else {
            $newMembers = '@机械组,@软件组';
        }
        
        $newLine = $groupName . '=' . $newMembers;
        $newAuthzContent .= $newLine . "\n";
        $modified = true;
        
        echo "  修改前: {$line}\n";
        echo "  修改后: {$newLine}\n";
    } else {
        $newAuthzContent .= $originalLine . "\n";
    }
}

if ($modified) {
    // 写入修改后的authz文件
    file_put_contents($authzPath, $newAuthzContent);
    echo "\n✅ authz文件修改成功\n";
    
    echo "\n3. 验证修改结果:\n";
    $updatedContent = file_get_contents($authzPath);
    $lines = explode("\n", $updatedContent);
    $inGroups = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === '[groups]') {
            $inGroups = true;
            continue;
        }
        
        if (strpos($line, '[') === 0 && $line !== '[groups]') {
            break;
        }
        
        if ($inGroups && !empty($line) && strpos($line, '8848实验室=') === 0) {
            echo "  {$line}\n";
            
            // 检查是否包含@符号
            if (strpos($line, '@') !== false) {
                echo "  ✅ 成功添加了子组引用\n";
            } else {
                echo "  ❌ 没有找到子组引用\n";
            }
            break;
        }
    }
    
    echo "\n4. 手动更新数据库中的组计数:\n";
    
    // 连接数据库
    $dbPath = '/home/svnadmin/svnadmin.db';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 更新8848实验室的include_group_count
    $stmt = $pdo->prepare("UPDATE svn_groups SET include_group_count = 2 WHERE svn_group_name = '8848实验室'");
    $stmt->execute();
    
    echo "  ✅ 已更新8848实验室的include_group_count为2\n";
    
    // 验证更新结果
    $stmt = $pdo->prepare("SELECT svn_group_name, include_user_count, include_group_count FROM svn_groups WHERE svn_group_name = '8848实验室'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "  验证结果: {$result['svn_group_name']} - 用户数: {$result['include_user_count']}, 子组数: {$result['include_group_count']}\n";
    }
    
} else {
    echo "\n❌ 没有找到8848实验室组定义\n";
}

echo "\n=== 测试完成 ===\n";
echo "现在可以在SVN分组页面查看8848实验室是否显示包含2个分组\n";
