<?php
/**
 * 调试企业微信用户获取问题
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

echo "=== 企业微信用户获取调试 ===\n\n";

// 1. 检查配置
$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "配置不存在\n";
    exit(1);
}

echo "1. 企业微信配置:\n";
echo "   Corp ID: " . substr($config['corp_id'], 0, 10) . "...\n";
echo "   Agent ID: " . $config['agent_id'] . "\n";
echo "   Access Token: " . (empty($config['access_token']) ? '无' : '有') . "\n";

// 2. 直接调用企业微信API测试
echo "\n2. 直接测试企业微信API:\n";

// 获取Access Token
$tokenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$config['corp_id']}&corpsecret={$config['corp_secret']}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Token请求状态码: $httpCode\n";

if ($httpCode == 200 && $response) {
    $tokenData = json_decode($response, true);
    if ($tokenData && isset($tokenData['access_token'])) {
        echo "   ✓ Access Token获取成功\n";
        $accessToken = $tokenData['access_token'];
        
        // 测试获取部门列表
        echo "\n3. 测试获取部门列表:\n";
        $deptUrl = "https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token=$accessToken";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $deptUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        
        $deptResponse = curl_exec($ch);
        $deptHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "   部门请求状态码: $deptHttpCode\n";
        
        if ($deptHttpCode == 200 && $deptResponse) {
            $deptData = json_decode($deptResponse, true);
            if ($deptData && isset($deptData['department'])) {
                $departments = $deptData['department'];
                echo "   ✓ 部门获取成功，数量: " . count($departments) . "\n";
                
                // 测试获取第一个部门的用户
                if (count($departments) > 0) {
                    $firstDept = $departments[0];
                    echo "\n4. 测试获取用户列表 (部门: {$firstDept['name']}):\n";
                    
                    $userUrl = "https://qyapi.weixin.qq.com/cgi-bin/user/list?access_token=$accessToken&department_id={$firstDept['id']}&fetch_child=1";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $userUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_PROXY, '');
                    curl_setopt($ch, CURLOPT_NOPROXY, '*');
                    
                    $userResponse = curl_exec($ch);
                    $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    echo "   用户请求状态码: $userHttpCode\n";
                    echo "   用户响应: " . substr($userResponse, 0, 200) . "...\n";
                    
                    if ($userHttpCode == 200 && $userResponse) {
                        $userData = json_decode($userResponse, true);
                        if ($userData && isset($userData['userlist'])) {
                            echo "   ✓ 用户获取成功，数量: " . count($userData['userlist']) . "\n";
                            if (count($userData['userlist']) > 0) {
                                $firstUser = $userData['userlist'][0];
                                echo "   第一个用户: " . $firstUser['name'] . " (ID: " . $firstUser['userid'] . ")\n";
                            }
                        } else {
                            echo "   ✗ 用户数据格式错误: " . json_encode($userData) . "\n";
                        }
                    } else {
                        echo "   ✗ 用户请求失败\n";
                    }
                }
            } else {
                echo "   ✗ 部门数据格式错误: " . json_encode($deptData) . "\n";
            }
        } else {
            echo "   ✗ 部门请求失败\n";
        }
        
    } else {
        echo "   ✗ Token数据格式错误: " . json_encode($tokenData) . "\n";
    }
} else {
    echo "   ✗ Token请求失败\n";
    echo "   响应: $response\n";
}

echo "\n=== 调试完成 ===\n";
?>
