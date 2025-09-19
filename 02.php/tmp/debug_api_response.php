<?php
/**
 * 调试API响应
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

echo "=== 调试API响应 ===\n\n";

$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "数据库配置不存在\n";
    exit(1);
}

// 获取Access Token
echo "1. 获取Access Token:\n";
$tokenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$config['corp_id']}&corpsecret={$config['corp_secret']}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP状态码: $tokenHttpCode\n";
echo "   完整响应: $tokenResponse\n";

$tokenData = json_decode($tokenResponse, true);
if (!$tokenData || !isset($tokenData['access_token'])) {
    echo "   ✗ Token获取失败\n";
    if (isset($tokenData['errcode'])) {
        echo "   错误码: {$tokenData['errcode']}\n";
        echo "   错误信息: {$tokenData['errmsg']}\n";
    }
    exit(1);
}

$accessToken = $tokenData['access_token'];
echo "   ✓ Access Token: " . substr($accessToken, 0, 20) . "...\n";

// 获取部门列表
echo "\n2. 获取部门列表:\n";
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

echo "   HTTP状态码: $deptHttpCode\n";
echo "   完整响应: $deptResponse\n";

$deptData = json_decode($deptResponse, true);
if ($deptData) {
    if (isset($deptData['errcode']) && $deptData['errcode'] != 0) {
        echo "   ✗ API错误\n";
        echo "   错误码: {$deptData['errcode']}\n";
        echo "   错误信息: {$deptData['errmsg']}\n";
        
        // 常见错误码解释
        $errorCodes = [
            40001 => 'invalid credential - 无效的凭证',
            40014 => 'invalid access_token - 无效的access_token',
            42001 => 'access_token expired - access_token已过期',
            40003 => 'invalid openid - 无效的openid',
            60011 => 'no privilege to access/modify contact or party - 没有通讯录权限',
            60020 => 'not allow to access - 不允许访问',
        ];
        
        if (isset($errorCodes[$deptData['errcode']])) {
            echo "   解释: {$errorCodes[$deptData['errcode']]}\n";
        }
    } elseif (isset($deptData['department'])) {
        echo "   ✓ 部门获取成功，数量: " . count($deptData['department']) . "\n";
    } else {
        echo "   ⚠ 响应格式异常\n";
    }
} else {
    echo "   ✗ 响应解析失败\n";
}

// 检查应用权限
echo "\n3. 检查应用信息:\n";
$appUrl = "https://qyapi.weixin.qq.com/cgi-bin/agent/get?access_token=$accessToken&agentid={$config['agent_id']}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $appUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$appResponse = curl_exec($ch);
$appHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP状态码: $appHttpCode\n";
echo "   应用信息响应: " . substr($appResponse, 0, 500) . "...\n";

$appData = json_decode($appResponse, true);
if ($appData && isset($appData['errcode']) && $appData['errcode'] == 0) {
    echo "   ✓ 应用信息获取成功\n";
    if (isset($appData['privilege'])) {
        echo "   权限信息: " . json_encode($appData['privilege']) . "\n";
    }
} else {
    echo "   ✗ 应用信息获取失败\n";
    if (isset($appData['errcode'])) {
        echo "   错误码: {$appData['errcode']}\n";
        echo "   错误信息: {$appData['errmsg']}\n";
    }
}

echo "\n=== 调试完成 ===\n";
?>
