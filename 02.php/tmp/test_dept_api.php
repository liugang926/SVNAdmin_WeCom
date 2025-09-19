<?php
/**
 * 测试部门API调用
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

echo "=== 测试部门API调用 ===\n\n";

$config = $database->get('wecom_config', '*', ['id' => 1]);
if (!$config) {
    echo "数据库配置不存在\n";
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

// 测试不同的部门API调用方式
echo "\n1. 测试不带id参数的部门列表API:\n";
$deptUrl1 = "https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token=$accessToken";
echo "   URL: $deptUrl1\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $deptUrl1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$response1 = curl_exec($ch);
curl_close($ch);

$data1 = json_decode($response1, true);
if ($data1 && isset($data1['department'])) {
    echo "   ✓ 成功，部门数量: " . count($data1['department']) . "\n";
    if (count($data1['department']) > 0) {
        echo "   前3个部门:\n";
        for ($i = 0; $i < min(3, count($data1['department'])); $i++) {
            $dept = $data1['department'][$i];
            echo "     - {$dept['name']} (ID: {$dept['id']}, 父级: {$dept['parentid']})\n";
        }
    }
} else {
    echo "   ✗ 失败: " . json_encode($data1) . "\n";
}

echo "\n2. 测试带id=1参数的部门列表API:\n";
$deptUrl2 = "https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token=$accessToken&id=1";
echo "   URL: $deptUrl2\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $deptUrl2);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_PROXY, '');
curl_setopt($ch, CURLOPT_NOPROXY, '*');

$response2 = curl_exec($ch);
curl_close($ch);

$data2 = json_decode($response2, true);
if ($data2 && isset($data2['department'])) {
    echo "   ✓ 成功，部门数量: " . count($data2['department']) . "\n";
    if (count($data2['department']) > 0) {
        echo "   前3个部门:\n";
        for ($i = 0; $i < min(3, count($data2['department'])); $i++) {
            $dept = $data2['department'][$i];
            echo "     - {$dept['name']} (ID: {$dept['id']}, 父级: {$dept['parentid']})\n";
        }
    }
} else {
    echo "   ✗ 失败: " . json_encode($data2) . "\n";
}

echo "\n3. 分析结果:\n";
if (isset($data1['department']) && isset($data2['department'])) {
    $count1 = count($data1['department']);
    $count2 = count($data2['department']);
    
    echo "   不带参数: $count1 个部门\n";
    echo "   带id=1参数: $count2 个部门\n";
    
    if ($count1 > $count2) {
        echo "   ✓ 建议使用不带参数的API调用\n";
    } elseif ($count2 > $count1) {
        echo "   ✓ 建议使用带id=1参数的API调用\n";
    } else {
        echo "   两种方式结果相同\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
