<?php
/**
 * 测试组织架构数据获取
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含必要的文件
require_once '/var/www/html/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

// 初始化数据库连接
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
]);

echo "=== 测试组织架构数据获取 ===\n\n";

// 1. 模拟WeComAPI的getFullOrganization方法
$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "配置不存在\n";
    exit(1);
}

// 获取Access Token
$tokenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$config['corp_id']}&corpsecret={$config['corp_secret']}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!$tokenData || !isset($tokenData['access_token'])) {
    echo "获取Token失败\n";
    exit(1);
}

$accessToken = $tokenData['access_token'];
echo "✓ Access Token获取成功\n";

// 获取部门列表
$deptUrl = "https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token=$accessToken";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $deptUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$deptResponse = curl_exec($ch);
curl_close($ch);

$deptData = json_decode($deptResponse, true);
if (!$deptData || !isset($deptData['department'])) {
    echo "获取部门失败\n";
    exit(1);
}

$departments = $deptData['department'];
echo "✓ 部门获取成功，数量: " . count($departments) . "\n";

// 模拟getFullOrganization的用户获取逻辑
$organizationData = [
    'departments' => $departments,
    'users' => [],
    'department_users' => []
];

$totalUsers = 0;
$processedDepts = 0;

foreach ($departments as $department) {
    $processedDepts++;
    
    // 只处理前5个部门作为测试
    if ($processedDepts > 5) {
        echo "   (为了测试，只处理前5个部门)\n";
        break;
    }
    
    echo "处理部门: {$department['name']} (ID: {$department['id']})\n";
    
    try {
        $userUrl = "https://qyapi.weixin.qq.com/cgi-bin/user/list?access_token=$accessToken&department_id={$department['id']}&fetch_child=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        
        $userResponse = curl_exec($ch);
        curl_close($ch);
        
        $userData = json_decode($userResponse, true);
        if ($userData && isset($userData['userlist'])) {
            $users = $userData['userlist'];
            $organizationData['department_users'][$department['id']] = $users;
            
            echo "   用户数量: " . count($users) . "\n";
            $totalUsers += count($users);
            
            // 合并到总用户列表（去重）
            foreach ($users as $user) {
                $organizationData['users'][$user['userid']] = $user;
            }
            
            // 显示前2个用户
            if (count($users) > 0) {
                for ($i = 0; $i < min(2, count($users)); $i++) {
                    $user = $users[$i];
                    echo "     - {$user['name']} ({$user['userid']})\n";
                }
            }
        } else {
            echo "   获取用户失败: " . json_encode($userData) . "\n";
        }
        
        // 避免请求过于频繁
        usleep(200000); // 0.2秒
        
    } catch (\Exception $e) {
        echo "   部门用户获取异常: " . $e->getMessage() . "\n";
    }
}

// 转换用户数组为索引数组
$organizationData['users'] = array_values($organizationData['users']);

echo "\n=== 汇总结果 ===\n";
echo "部门总数: " . count($organizationData['departments']) . "\n";
echo "用户总数: " . count($organizationData['users']) . "\n";
echo "处理的部门数: $processedDepts\n";
echo "发现的用户数: $totalUsers\n";

if (count($organizationData['users']) > 0) {
    echo "\n前3个用户:\n";
    for ($i = 0; $i < min(3, count($organizationData['users'])); $i++) {
        $user = $organizationData['users'][$i];
        echo "  - {$user['name']} ({$user['userid']}) - 部门: " . json_encode($user['department']) . "\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
