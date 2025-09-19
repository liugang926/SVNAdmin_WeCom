<?php
echo "=== 触发分组层级关系同步 ===\n\n";

// 直接在容器环境中运行
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';
require_once '/var/www/html/app/service/base/Base.php';
require_once '/var/www/html/app/service/Logs.php';
require_once '/var/www/html/app/service/WeComSync.php';

use app\service\WeComSync;
use app\service\Logs;
use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo($config['database']);
$logService = new Logs($database);

echo "初始化WeComSync服务...\n";
$wecomSync = new WeComSync($database);

try {
    echo "开始执行分组层级关系同步...\n\n";
    
    // 获取企业微信部门数据
    $departments = $database->select('wecom_departments', '*');
    echo "找到 " . count($departments) . " 个企业微信部门\n";
    
    // 使用反射调用私有方法
    $reflection = new ReflectionClass($wecomSync);
    $syncMethod = $reflection->getMethod('syncGroupHierarchy');
    $syncMethod->setAccessible(true);
    
    // 执行分组层级关系同步
    $result = $syncMethod->invoke($wecomSync, $departments);
    
    echo "同步结果:\n";
    echo "  状态: " . ($result['status'] == 1 ? '成功' : '失败') . "\n";
    echo "  消息: {$result['message']}\n";
    echo "  处理数量: {$result['processed']}\n";
    echo "  更新数量: {$result['updated']}\n";
    echo "  错误数量: {$result['errors']}\n\n";
    
    if ($result['updated'] > 0) {
        echo "✅ 分组层级关系同步成功！\n";
        echo "现在检查authz文件和数据库更新情况...\n\n";
        
        // 检查authz文件中的组层级关系
        $authzPath = '/home/svnadmin/authz';
        $authzContent = file_get_contents($authzPath);
        $lines = explode("\n", $authzContent);
        $inGroups = false;
        $groupRefsFound = 0;
        
        echo "authz文件中包含其他组的组定义:\n";
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
                $groupRefsFound++;
            }
        }
        
        if ($groupRefsFound > 0) {
            echo "\n✅ 找到 {$groupRefsFound} 个组包含其他组的定义\n";
        } else {
            echo "\n❌ 未找到组包含其他组的定义\n";
        }
        
        // 检查数据库中的组计数更新
        echo "\n数据库中包含组数大于0的组:\n";
        $stmt = $database->pdo->prepare("
            SELECT svn_group_name, include_user_count, include_group_count 
            FROM svn_groups 
            WHERE include_group_count > 0 
            ORDER BY include_group_count DESC
        ");
        $stmt->execute();
        $groupsWithSubgroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($groupsWithSubgroups)) {
            echo "  ❌ 没有组的include_group_count大于0\n";
        } else {
            foreach ($groupsWithSubgroups as $group) {
                echo "  {$group['svn_group_name']}: 用户={$group['include_user_count']}, 子组={$group['include_group_count']}\n";
            }
        }
        
    } else {
        echo "⚠️  没有更新任何分组层级关系\n";
        echo "可能的原因:\n";
        echo "  1. 所有层级关系已经存在\n";
        echo "  2. 部门数据中缺少父子关系\n";
        echo "  3. SVN组映射不完整\n";
    }
    
} catch (Exception $e) {
    echo "❌ 同步失败: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}

echo "\n=== 同步完成 ===\n";
