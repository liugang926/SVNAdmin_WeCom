<?php
/**
 * 调试同步配置
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

echo "=== 调试同步配置 ===\n\n";

// 1. 检查wecom.php配置文件
echo "1. 检查wecom.php配置文件:\n";
$configFile = '/var/www/html/config/wecom.php';
if (file_exists($configFile)) {
    $wecomConfig = include $configFile;
    if ($wecomConfig && is_array($wecomConfig)) {
        echo "   ✓ 配置文件存在\n";
        echo "   基本配置:\n";
        echo "     - Corp ID: " . substr($wecomConfig['corp_id'], 0, 10) . "...\n";
        echo "     - Agent ID: " . $wecomConfig['agent_id'] . "\n";
        
        if (isset($wecomConfig['department_mapping'])) {
            echo "   部门映射配置:\n";
            echo "     - Root Department ID: " . ($wecomConfig['department_mapping']['root_department_id'] ?? '未设置') . "\n";
            echo "     - Strategy: " . ($wecomConfig['department_mapping']['strategy'] ?? '未设置') . "\n";
        } else {
            echo "   ⚠ 部门映射配置不存在\n";
        }
        
        if (isset($wecomConfig['sync'])) {
            echo "   同步配置:\n";
            echo "     - Auto Sync: " . ($wecomConfig['sync']['auto_sync'] ?? '未设置') . "\n";
            echo "     - Sync Interval: " . ($wecomConfig['sync']['sync_interval'] ?? '未设置') . "\n";
        } else {
            echo "   ⚠ 同步配置不存在\n";
        }
    } else {
        echo "   ✗ 配置变量不存在\n";
    }
} else {
    echo "   ✗ 配置文件不存在\n";
}

// 2. 检查数据库配置
echo "\n2. 检查数据库配置:\n";
$dbConfig = $database->get('wecom_config', '*', ['id' => 1]);
if ($dbConfig) {
    echo "   ✓ 数据库配置存在\n";
    echo "   - Corp ID: " . substr($dbConfig['corp_id'], 0, 10) . "...\n";
    echo "   - Agent ID: " . $dbConfig['agent_id'] . "\n";
    echo "   - Access Token: " . (empty($dbConfig['access_token']) ? '无' : '有') . "\n";
    echo "   - Config Data: " . (empty($dbConfig['config_data']) ? '无' : '有') . "\n";
    
    if (!empty($dbConfig['config_data'])) {
        $configData = json_decode($dbConfig['config_data'], true);
        if ($configData) {
            echo "   配置数据内容:\n";
            echo "     - Keys: " . implode(', ', array_keys($configData)) . "\n";
        }
    }
} else {
    echo "   ✗ 数据库配置不存在\n";
}

// 3. 模拟WeComSync的配置初始化
echo "\n3. 模拟WeComSync配置初始化:\n";
try {
    // 模拟WeComSync构造函数中的配置初始化逻辑
    if (file_exists($configFile)) {
        $wecomConfig = include $configFile;
        
        $syncConfig = [];
        
        // 构建syncConfig
        if (isset($wecomConfig['sync'])) {
            $syncConfig = array_merge($syncConfig, $wecomConfig['sync']);
        }
        
        if (isset($wecomConfig['department_mapping'])) {
            $syncConfig = array_merge($syncConfig, $wecomConfig['department_mapping']);
        }
        
        echo "   ✓ 配置初始化成功\n";
        echo "   SyncConfig内容:\n";
        foreach ($syncConfig as $key => $value) {
            if (is_array($value)) {
                echo "     - $key: " . json_encode($value) . "\n";
            } else {
                echo "     - $key: $value\n";
            }
        }
        
        // 检查关键配置项
        $rootDeptId = $syncConfig['root_department_id'] ?? null;
        echo "\n   关键配置检查:\n";
        echo "     - Root Department ID: " . ($rootDeptId ?? '未设置') . "\n";
        
        if ($rootDeptId) {
            echo "   ✓ Root Department ID已设置，应该能正常获取用户数据\n";
        } else {
            echo "   ⚠ Root Department ID未设置，可能导致用户数据获取失败\n";
        }
        
    } else {
        echo "   ✗ 配置文件不存在，无法初始化\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ 配置初始化失败: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";
?>
