<?php

define('BASE_PATH', dirname(dirname(__FILE__)));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
require_once BASE_PATH . '/app/service/WeComConfig.php';
require_once BASE_PATH . '/app/controller/base/Base.php';
require_once BASE_PATH . '/app/controller/WeComAdmin.php';

use Medoo\Medoo;
use app\service\WeComConfig;
use app\controller\WeComAdmin;

echo "=== 测试 GetSystemStatus 方法 ===" . PHP_EOL;

try {
    // 初始化控制器
    $controller = new WeComAdmin([]);
    
    // 捕获输出
    ob_start();
    $controller->GetSystemStatus();
    $output = ob_get_clean();
    
    echo "GetSystemStatus 输出:" . PHP_EOL;
    echo $output . PHP_EOL;
    
    // 解析JSON响应
    $response = json_decode($output, true);
    if ($response) {
        echo "解析后的响应:" . PHP_EOL;
        echo "状态码: " . $response['code'] . PHP_EOL;
        echo "状态: " . $response['status'] . PHP_EOL;
        echo "消息: " . $response['message'] . PHP_EOL;
        
        if (isset($response['data'])) {
            $data = $response['data'];
            echo "配置数据:" . PHP_EOL;
            if (isset($data['config'])) {
                echo "  is_configured: " . ($data['config']['is_configured'] ? 'true' : 'false') . PHP_EOL;
                echo "  tables_exist: " . ($data['config']['tables_exist'] ? 'true' : 'false') . PHP_EOL;
            }
            if (isset($data['services'])) {
                echo "服务状态:" . PHP_EOL;
                echo "  api_service: " . ($data['services']['api_service'] ? 'true' : 'false') . PHP_EOL;
                echo "  sync_service: " . ($data['services']['sync_service'] ? 'true' : 'false') . PHP_EOL;
                echo "  notification_service: " . ($data['services']['notification_service'] ? 'true' : 'false') . PHP_EOL;
            }
        }
    } else {
        echo "无法解析JSON响应" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "测试异常: " . $e->getMessage() . PHP_EOL;
    echo "错误堆栈: " . $e->getTraceAsString() . PHP_EOL;
}
