<?php
/*
 * Debug脚本模板
 * 使用方法: 复制此模板，重命名为 debug_[问题描述].php
 * 目的: 复现和调试具体问题
 * 创建时间: [日期]
 * 调试人员: [姓名]
 */

define('BASE_PATH', __DIR__ . '/..');

// 根据项目环境调整路径
require_once BASE_PATH . '/02.php/app/util/Config.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

echo "=== Debug: [问题描述] ===\n\n";

try {
    // 1. 初始化环境
    echo "1. 初始化环境:\n";
    echo "----------------------------\n";
    
    // 加载配置
    Config::load(BASE_PATH . '/02.php/config/');
    echo "   ✓ 配置文件加载完成\n";
    
    // 初始化数据库连接
    $configDatabase = Config::get('database');
    $configSvn = Config::get('svn');
    
    if (array_key_exists('database_file', $configDatabase)) {
        $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
    }
    
    $database = new Medoo($configDatabase);
    echo "   ✓ 数据库连接初始化完成\n";
    
    // 2. 环境状态检查
    echo "\n2. 环境状态检查:\n";
    echo "----------------------------\n";
    
    // 检查关键配置
    echo "   配置检查:\n";
    $configs = ['database', 'svn', 'wecom']; // 根据需要调整
    foreach ($configs as $configName) {
        try {
            $config = Config::get($configName);
            echo "   - {$configName}: ✓\n";
        } catch (Exception $e) {
            echo "   - {$configName}: ✗ ({$e->getMessage()})\n";
        }
    }
    
    // 检查数据库表
    echo "\n   数据库表检查:\n";
    $requiredTables = ['svn_users', 'svn_groups', 'svn_reps']; // 根据需要调整
    foreach ($requiredTables as $table) {
        if ($database->has($table)) {
            $count = $database->count($table);
            echo "   - {$table}: ✓ ({$count} 条记录)\n";
        } else {
            echo "   - {$table}: ✗ (表不存在)\n";
        }
    }
    
    // 3. 问题复现
    echo "\n3. 问题复现:\n";
    echo "----------------------------\n";
    
    // 准备测试数据
    echo "   准备测试数据:\n";
    // 在这里添加测试数据准备代码
    
    // 执行问题复现步骤
    echo "\n   执行复现步骤:\n";
    echo "   步骤1: [描述第一步操作]\n";
    // 添加第一步的代码
    
    echo "   步骤2: [描述第二步操作]\n";
    // 添加第二步的代码
    
    echo "   步骤3: [描述第三步操作]\n";
    // 添加第三步的代码
    
    // 4. 调试信息收集
    echo "\n4. 调试信息收集:\n";
    echo "----------------------------\n";
    
    // 收集相关变量状态
    echo "   变量状态:\n";
    // 添加变量状态检查代码
    
    // 收集数据库状态
    echo "\n   数据库状态:\n";
    // 添加数据库状态检查代码
    
    // 收集日志信息
    echo "\n   日志信息:\n";
    $logFiles = [
        BASE_PATH . '/02.php/logs/error.log',
        BASE_PATH . '/02.php/logs/wecom.log',
        BASE_PATH . '/02.php/logs/svn.log'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $recentLines = array_slice($lines, -10); // 最近10行
            echo "   - " . basename($logFile) . " (最近10行):\n";
            foreach ($recentLines as $line) {
                echo "     " . trim($line) . "\n";
            }
        } else {
            echo "   - " . basename($logFile) . ": 文件不存在\n";
        }
    }
    
    // 5. 问题分析
    echo "\n5. 问题分析:\n";
    echo "----------------------------\n";
    
    // 分析可能的原因
    echo "   可能原因分析:\n";
    // 添加原因分析代码
    
    // 6. 解决方案建议
    echo "\n6. 解决方案建议:\n";
    echo "----------------------------\n";
    echo "   基于调试结果，建议的解决方案:\n";
    echo "   1. [解决方案1]\n";
    echo "   2. [解决方案2]\n";
    echo "   3. [解决方案3]\n";
    
} catch (Exception $e) {
    echo "❌ Debug过程中发生异常:\n";
    echo "   错误信息: " . $e->getMessage() . "\n";
    echo "   错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   堆栈跟踪:\n";
    
    $trace = $e->getTraceAsString();
    $traceLines = explode("\n", $trace);
    foreach ($traceLines as $line) {
        echo "     " . $line . "\n";
    }
}

echo "\n=== Debug完成 ===\n";
echo "请根据调试结果制定具体的修复方案\n";

// 辅助函数
function debugVar($name, $value) {
    echo "   - {$name}: ";
    if (is_array($value)) {
        echo "Array(" . count($value) . ") " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
    } elseif (is_object($value)) {
        echo "Object(" . get_class($value) . ")\n";
    } elseif (is_bool($value)) {
        echo $value ? 'true' : 'false';
        echo "\n";
    } elseif (is_null($value)) {
        echo "null\n";
    } else {
        echo $value . "\n";
    }
}

function checkFilePermissions($path) {
    if (!file_exists($path)) {
        return "不存在";
    }
    
    $perms = fileperms($path);
    $info = '';
    
    // 文件类型
    if (($perms & 0xC000) == 0xC000) {
        $info = 's'; // Socket
    } elseif (($perms & 0xA000) == 0xA000) {
        $info = 'l'; // Symbolic Link
    } elseif (($perms & 0x8000) == 0x8000) {
        $info = '-'; // Regular
    } elseif (($perms & 0x6000) == 0x6000) {
        $info = 'b'; // Block special
    } elseif (($perms & 0x4000) == 0x4000) {
        $info = 'd'; // Directory
    } elseif (($perms & 0x2000) == 0x2000) {
        $info = 'c'; // Character special
    } elseif (($perms & 0x1000) == 0x1000) {
        $info = 'p'; // FIFO pipe
    } else {
        $info = 'u'; // Unknown
    }
    
    // 权限
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;
}
?>
