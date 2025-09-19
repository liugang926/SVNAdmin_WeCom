<?php

define('BASE_PATH', dirname(dirname(__FILE__)));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/app/service/WeComConfig.php';

use Medoo\Medoo;
use app\service\WeComConfig;

echo "=== 测试 WeComConfig 服务 ===" . PHP_EOL;

try {
    $config = WeComConfig::getConfig();
    echo "WeComConfig::getConfig() 结果:" . PHP_EOL;
    echo "corp_id: " . (isset($config['corp_id']) ? $config['corp_id'] : 'null') . PHP_EOL;
    echo "agent_id: " . (isset($config['agent_id']) ? $config['agent_id'] : 'null') . PHP_EOL;
    echo "enabled: " . (isset($config['enabled']) && $config['enabled'] ? 'true' : 'false') . PHP_EOL;
    
    // 检查配置状态
    $isConfigured = !empty($config['corp_id']) && !empty($config['agent_id']);
    echo "is_configured: " . ($isConfigured ? 'true' : 'false') . PHP_EOL;
    
} catch (Exception $e) {
    echo "WeComConfig 错误: " . $e->getMessage() . PHP_EOL;
    echo "错误堆栈: " . $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "=== 测试数据库连接 ===" . PHP_EOL;

try {
    global $database;
    if ($database) {
        echo "数据库连接: 成功" . PHP_EOL;
        
        // 检查wecom_config表
        $configRecord = $database->get('wecom_config', '*', [
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => 1
        ]);
        
        if ($configRecord) {
            echo "数据库配置记录存在:" . PHP_EOL;
            echo "corp_id: " . $configRecord['corp_id'] . PHP_EOL;
            echo "agent_id: " . $configRecord['agent_id'] . PHP_EOL;
        } else {
            echo "数据库中没有配置记录" . PHP_EOL;
        }
    } else {
        echo "数据库连接: 失败" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "数据库错误: " . $e->getMessage() . PHP_EOL;
}
