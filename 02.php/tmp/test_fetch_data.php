<?php
/**
 * 测试fetchWeComData方法
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

echo "=== 测试fetchWeComData方法 ===\n\n";

// 1. 模拟WeComSync的配置加载
echo "1. 加载配置:\n";
$wecomConfig = require '/var/www/html/config/wecom.php';
if ($wecomConfig && is_array($wecomConfig)) {
    echo "   ✓ 配置加载成功\n";
    
    // 构建syncConfig
    $dept = $wecomConfig['department_mapping'] ?? [];
    $sync = $wecomConfig['sync'] ?? [];
    $syncConfig = [
        'enable' => true,
        'department_root_id' => $dept['root_department_id'] ?? 1,
        'sync_interval' => $sync['interval'] ?? 3600,
    ];
    
    echo "   SyncConfig:\n";
    foreach ($syncConfig as $key => $value) {
        echo "     - $key: $value\n";
    }
} else {
    echo "   ✗ 配置加载失败\n";
    exit(1);
}

// 2. 模拟fetchWeComData方法
echo "\n2. 模拟fetchWeComData方法:\n";

$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "   ✗ 数据库配置不存在\n";
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
    echo "   ✗ 获取Token失败\n";
    exit(1);
}

$accessToken = $tokenData['access_token'];
echo "   ✓ Access Token获取成功\n";

// 获取部门列表
$rootDepartmentId = $syncConfig['department_root_id'];
echo "   使用Root Department ID: $rootDepartmentId\n";

$deptUrl = "https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token=$accessToken";
if ($rootDepartmentId && $rootDepartmentId != 1) {
    $deptUrl .= "&id=$rootDepartmentId";
}

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
    echo "   ✗ 获取部门失败: " . json_encode($deptData) . "\n";
    exit(1);
}

$departments = $deptData['department'];
echo "   ✓ 部门获取成功，数量: " . count($departments) . "\n";

// 模拟getFullOrganization的用户获取逻辑
$organizationData = [
    'departments' => $departments,
    'users' => [],
    'department_users' => []
];

$totalUsers = 0;
$processedDepts = 0;

echo "\n3. 获取用户数据:\n";
foreach ($departments as $department) {
    $processedDepts++;
    
    // 只处理前3个部门作为测试
    if ($processedDepts > 3) {
        echo "   (为了测试，只处理前3个部门)\n";
        break;
    }
    
    echo "   处理部门: {$department['name']} (ID: {$department['id']})\n";
    
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
            
            echo "     用户数量: " . count($users) . "\n";
            $totalUsers += count($users);
            
            // 合并到总用户列表（去重）
            foreach ($users as $user) {
                $organizationData['users'][$user['userid']] = $user;
            }
            
            // 显示前1个用户
            if (count($users) > 0) {
                $user = $users[0];
                echo "       示例: {$user['name']} ({$user['userid']})\n";
            }
        } else {
            echo "     ✗ 获取用户失败: " . json_encode($userData) . "\n";
        }
        
        // 避免请求过于频繁
        usleep(200000); // 0.2秒
        
    } catch (\Exception $e) {
        echo "     ✗ 部门用户获取异常: " . $e->getMessage() . "\n";
    }
}

// 转换用户数组为索引数组
$organizationData['users'] = array_values($organizationData['users']);

echo "\n=== 最终结果 ===\n";
echo "部门总数: " . count($organizationData['departments']) . "\n";
echo "用户总数: " . count($organizationData['users']) . "\n";
echo "处理的部门数: $processedDepts\n";
echo "发现的用户数: $totalUsers\n";

if (count($organizationData['users']) > 0) {
    echo "\n✓ 用户数据获取成功！\n";
    echo "前2个用户:\n";
    for ($i = 0; $i < min(2, count($organizationData['users'])); $i++) {
        $user = $organizationData['users'][$i];
        echo "  - {$user['name']} ({$user['userid']}) - 部门: " . json_encode($user['department']) . "\n";
    }
} else {
    echo "\n✗ 没有获取到用户数据！\n";
}

echo "\n=== 测试完成 ===\n";
?>
