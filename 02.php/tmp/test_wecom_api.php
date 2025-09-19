<?php
/*
 * 测试企业微信API用户获取
 */

// 直接测试企业微信API
echo "=== 测试企业微信API ===\n";

// 从数据库读取配置
$dbConfig = [
    'database_type' => 'sqlite',
    'database_file' => '/home/svnadmin/svnadmin.db'
];

try {
    $pdo = new PDO('sqlite:' . $dbConfig['database_file']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取企业微信配置
    $stmt = $pdo->prepare("SELECT corp_id, corp_secret, agent_id FROM wecom_config WHERE id = 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['corp_id']) || empty($config['corp_secret'])) {
        echo "企业微信配置不完整\n";
        exit(1);
    }
    
    echo "企业微信配置已获取\n";
    
    // 获取access_token
    $tokenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken";
    $tokenParams = [
        'corpid' => $config['corp_id'],
        'corpsecret' => $config['corp_secret']
    ];
    
    $tokenResponse = file_get_contents($tokenUrl . '?' . http_build_query($tokenParams));
    $tokenData = json_decode($tokenResponse, true);
    
    if ($tokenData['errcode'] !== 0) {
        echo "获取access_token失败: " . $tokenData['errmsg'] . " (错误码: " . $tokenData['errcode'] . ")\n";
        exit(1);
    }
    
    $accessToken = $tokenData['access_token'];
    echo "access_token获取成功\n";
    
    // 获取部门列表
    $deptUrl = "https://qyapi.weixin.qq.com/cgi-bin/department/list";
    $deptParams = ['access_token' => $accessToken];
    
    $deptResponse = file_get_contents($deptUrl . '?' . http_build_query($deptParams));
    $deptData = json_decode($deptResponse, true);
    
    if ($deptData['errcode'] !== 0) {
        echo "获取部门列表失败: " . $deptData['errmsg'] . " (错误码: " . $deptData['errcode'] . ")\n";
        exit(1);
    }
    
    $departments = $deptData['department'];
    echo "部门列表获取成功，共 " . count($departments) . " 个部门\n";
    
    // 测试获取第一个部门的用户
    if (count($departments) > 0) {
        $testDept = $departments[0];
        echo "测试部门: {$testDept['name']} (ID: {$testDept['id']})\n";
        
        $userUrl = "https://qyapi.weixin.qq.com/cgi-bin/user/list";
        $userParams = [
            'access_token' => $accessToken,
            'department_id' => $testDept['id'],
            'fetch_child' => 0
        ];
        
        $userResponse = file_get_contents($userUrl . '?' . http_build_query($userParams));
        $userData = json_decode($userResponse, true);
        
        if ($userData['errcode'] !== 0) {
            echo "获取用户列表失败: " . $userData['errmsg'] . " (错误码: " . $userData['errcode'] . ")\n";
        } else {
            $users = $userData['userlist'] ?? [];
            echo "用户列表获取成功，共 " . count($users) . " 个用户\n";
            
            if (count($users) > 0) {
                echo "前3个用户:\n";
                for ($i = 0; $i < min(3, count($users)); $i++) {
                    $user = $users[$i];
                    echo "  - ID: {$user['userid']}, 姓名: {$user['name']}\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
