<?php
/**
 * 同步用户组关系到authz文件
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

echo "=== 同步用户组关系到authz文件 ===\n\n";

try {
    // 1. 获取所有企业微信用户及其部门关系
    $wecomUsers = $database->select('wecom_users', [
        'wecom_user_id',
        'real_name', 
        'department_ids',
        'svn_user_id'
    ], [
        'svn_user_id[!]' => null  // 只处理已映射到SVN的用户
    ]);
    
    echo "找到 " . count($wecomUsers) . " 个已映射的企业微信用户\n";
    
    // 2. 获取SVN用户信息
    $svnUsers = $database->select('svn_users', [
        'svn_user_id',
        'svn_user_name'
    ]);
    
    $svnUserMap = [];
    foreach ($svnUsers as $user) {
        $svnUserMap[$user['svn_user_id']] = $user['svn_user_name'];
    }
    
    // 3. 获取部门到SVN组的映射
    $departments = $database->select('wecom_departments', [
        'wecom_dept_id',
        'dept_name',
        'svn_group_id'
    ], [
        'svn_group_id[!]' => null
    ]);
    
    $deptToGroupMap = [];
    foreach ($departments as $dept) {
        $deptToGroupMap[$dept['wecom_dept_id']] = $dept['svn_group_id'];
    }
    
    // 4. 获取SVN组信息
    $svnGroups = $database->select('svn_groups', [
        'svn_group_id',
        'svn_group_name'
    ]);
    
    $svnGroupMap = [];
    foreach ($svnGroups as $group) {
        $svnGroupMap[$group['svn_group_id']] = $group['svn_group_name'];
    }
    
    echo "部门映射: " . count($deptToGroupMap) . " 个\n";
    echo "SVN组: " . count($svnGroupMap) . " 个\n\n";
    
    // 5. 读取当前authz文件
    $authzFile = '/home/svnadmin/authz';
    $authzContent = file_get_contents($authzFile);
    
    // 6. 处理每个用户的组关系
    $processed = 0;
    $added = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($wecomUsers as $user) {
        $processed++;
        $userName = $svnUserMap[$user['svn_user_id']] ?? null;
        
        if (!$userName) {
            echo "跳过用户 {$user['real_name']}: SVN用户名未找到\n";
            $skipped++;
            continue;
        }
        
        // 解析用户的部门ID
        $departmentIds = json_decode($user['department_ids'] ?? '[]', true);
        if (empty($departmentIds)) {
            echo "跳过用户 {$user['real_name']} ($userName): 无部门信息\n";
            $skipped++;
            continue;
        }
        
        echo "处理用户: {$user['real_name']} ($userName) - 部门: " . implode(',', $departmentIds) . "\n";
        
        // 为每个部门添加用户到对应的组
        foreach ($departmentIds as $deptId) {
            $svnGroupId = $deptToGroupMap[$deptId] ?? null;
            if (!$svnGroupId) {
                echo "  ⚠ 部门 $deptId 没有对应的SVN组\n";
                continue;
            }
            
            $groupName = $svnGroupMap[$svnGroupId] ?? null;
            if (!$groupName) {
                echo "  ⚠ SVN组ID $svnGroupId 没有找到组名\n";
                continue;
            }
            
            echo "  添加到组: $groupName ... ";
            
            try {
                $result = $svnAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'add');
                
                if (is_string($result)) {
                    // 成功
                    $authzContent = $result;
                    echo "✓\n";
                    $added++;
                } else {
                    // 失败
                    $errorMessages = [
                        802 => '不能添加相同名称的分组',
                        803 => '用户已存在于分组中',
                        720 => 'authz文件格式错误',
                        901 => '不支持的对象类型'
                    ];
                    $errorMsg = $errorMessages[$result] ?? "错误码: $result";
                    
                    if ($result == 803) {
                        echo "⚠ 已存在\n";
                        $skipped++;
                    } else {
                        echo "✗ $errorMsg\n";
                        $errors++;
                    }
                }
            } catch (Exception $e) {
                echo "✗ 异常: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        // 每处理10个用户显示一次进度
        if ($processed % 10 == 0) {
            echo "  进度: $processed/" . count($wecomUsers) . "\n";
        }
    }
    
    // 7. 写入更新后的authz文件
    echo "\n写入authz文件...\n";
    file_put_contents($authzFile, $authzContent);
    
    // 8. 验证结果
    $newAuthzContent = file_get_contents($authzFile);
    $newLines = count(explode("\n", $newAuthzContent));
    
    echo "\n=== 同步完成 ===\n";
    echo "处理用户: $processed\n";
    echo "添加关系: $added\n";
    echo "跳过/已存在: $skipped\n";
    echo "错误: $errors\n";
    echo "authz文件行数: $newLines\n";
    
    // 显示几个组的成员示例
    echo "\n组成员示例:\n";
    $result = $svnAdmin->GetGroupInfo($authzContent);
    if (is_array($result)) {
        $count = 0;
        foreach ($result as $groupName => $members) {
            if (!empty($members) && $count < 5) {
                echo "  $groupName: " . implode(', ', $members) . "\n";
                $count++;
            }
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
?>
