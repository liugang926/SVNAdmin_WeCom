<?php
/**
 * 将数据库中的SVN组同步到authz文件
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义BASE_PATH
define('BASE_PATH', '/var/www/html');

// 包含必要的文件
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/extension/Witersen/SVNAdmin.php';

use Medoo\Medoo;
use Witersen\SVNAdmin;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

// 初始化SVNAdmin
$svnAdmin = new SVNAdmin();

echo "=== 同步数据库组到authz文件 ===\n\n";

try {
    // 1. 获取所有SVN组
    $groups = $database->select('svn_groups', ['svn_group_name'], [
        'ORDER' => 'svn_group_id'
    ]);
    
    echo "找到 " . count($groups) . " 个SVN组\n";
    
    // 2. 读取当前authz文件
    $authzFile = '/home/svnadmin/authz';
    $authzContent = file_get_contents($authzFile);
    echo "当前authz文件行数: " . count(explode("\n", $authzContent)) . "\n\n";
    
    // 3. 为每个组创建authz条目
    $created = 0;
    $skipped = 0;
    
    foreach ($groups as $group) {
        $groupName = $group['svn_group_name'];
        echo "处理组: $groupName ... ";
        
        try {
            $result = $svnAdmin->AddGroup($authzContent, $groupName);
            
            if (is_string($result)) {
                // 成功创建
                $authzContent = $result;
                echo "✓ 创建成功\n";
                $created++;
            } else {
                // 失败或已存在
                $errorMessages = [
                    820 => '组已存在',
                    612 => '[groups]部分不存在'
                ];
                $errorMsg = $errorMessages[$result] ?? "错误码: $result";
                echo "⚠ $errorMsg\n";
                $skipped++;
            }
        } catch (Exception $e) {
            echo "✗ 异常: " . $e->getMessage() . "\n";
            $skipped++;
        }
    }
    
    // 4. 写入更新后的authz文件
    echo "\n写入authz文件...\n";
    file_put_contents($authzFile, $authzContent);
    
    // 5. 验证结果
    $newAuthzContent = file_get_contents($authzFile);
    $newLines = count(explode("\n", $newAuthzContent));
    
    echo "\n=== 同步完成 ===\n";
    echo "处理组数: " . count($groups) . "\n";
    echo "创建成功: $created\n";
    echo "跳过/失败: $skipped\n";
    echo "authz文件新行数: $newLines\n";
    
    // 显示authz文件内容预览
    echo "\nauthz文件内容预览:\n";
    $lines = explode("\n", $newAuthzContent);
    $previewLines = array_slice($lines, 0, min(20, count($lines)));
    foreach ($previewLines as $i => $line) {
        echo sprintf("%3d: %s\n", $i + 1, $line);
    }
    
    if (count($lines) > 20) {
        echo "... (还有 " . (count($lines) - 20) . " 行)\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
