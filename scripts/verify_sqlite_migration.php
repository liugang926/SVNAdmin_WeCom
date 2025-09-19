<?php
/**
 * SQLite 数据库迁移验证脚本
 * 用于验证生产环境的 SQLite 数据库是否成功迁移
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 颜色输出函数
function colorOutput($text, $color = 'white') {
    $colors = [
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'white' => "\033[0m"
    ];
    
    return $colors[$color] . $text . $colors['white'];
}

function logInfo($message) {
    echo colorOutput("[INFO] ", 'blue') . $message . PHP_EOL;
}

function logSuccess($message) {
    echo colorOutput("[SUCCESS] ", 'green') . $message . PHP_EOL;
}

function logWarning($message) {
    echo colorOutput("[WARNING] ", 'yellow') . $message . PHP_EOL;
}

function logError($message) {
    echo colorOutput("[ERROR] ", 'red') . $message . PHP_EOL;
}

// 查找数据库文件
function findDatabaseFile() {
    $possiblePaths = [
        '02.php/templete/database/sqlite/database.db',
        '../02.php/templete/database/sqlite/database.db',
        './database.db',
        '/var/www/html/02.php/templete/database/sqlite/database.db'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return realpath($path);
        }
    }
    
    return null;
}

// 检查表是否存在
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// 检查列是否存在
function columnExists($pdo, $tableName, $columnName) {
    try {
        $stmt = $pdo->prepare("PRAGMA table_info($tableName)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            if ($column['name'] === $columnName) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// 主验证函数
function verifyMigration() {
    logInfo("开始验证 SQLite 数据库迁移...");
    
    // 查找数据库文件
    $dbPath = findDatabaseFile();
    if (!$dbPath) {
        logError("未找到数据库文件，请检查路径");
        return false;
    }
    
    logInfo("找到数据库文件: $dbPath");
    
    try {
        // 连接数据库
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        logSuccess("数据库连接成功");
        
        // 检查原有表
        $originalTables = ['svn_users', 'svn_groups', 'svn_reps'];
        foreach ($originalTables as $table) {
            if (tableExists($pdo, $table)) {
                logSuccess("原有表 $table 存在");
                
                // 获取记录数
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                logInfo("  - 记录数: $count");
            } else {
                logWarning("原有表 $table 不存在");
            }
        }
        
        // 检查新增的企业微信相关表
        $wecomTables = [
            'wecom_config',
            'wecom_departments', 
            'wecom_users',
            'wecom_notification_rules',
            'wecom_notification_logs',
            'wecom_sync_logs',
            'wecom_api_logs',
            'wecom_notification_queue'
        ];
        
        $missingTables = [];
        foreach ($wecomTables as $table) {
            if (tableExists($pdo, $table)) {
                logSuccess("企业微信表 $table 存在");
            } else {
                logWarning("企业微信表 $table 不存在");
                $missingTables[] = $table;
            }
        }
        
        // 检查 svn_users 表的新增列
        if (tableExists($pdo, 'svn_users')) {
            $newColumns = ['wecom_userid', 'wecom_name'];
            foreach ($newColumns as $column) {
                if (columnExists($pdo, 'svn_users', $column)) {
                    logSuccess("svn_users 表新增列 $column 存在");
                } else {
                    logWarning("svn_users 表新增列 $column 不存在");
                }
            }
        }
        
        // 生成迁移建议
        if (!empty($missingTables)) {
            logWarning("发现缺失的表，建议执行以下操作:");
            echo PHP_EOL;
            echo "1. 手动执行 SQL 脚本:" . PHP_EOL;
            echo "   php -r \"" . PHP_EOL;
            echo "   \$pdo = new PDO('sqlite:$dbPath');" . PHP_EOL;
            echo "   \$sql = file_get_contents('02.php/templete/database/sqlite/wecom_tables.sql');" . PHP_EOL;
            echo "   \$pdo->exec(\$sql);" . PHP_EOL;
            echo "   echo 'Migration completed';" . PHP_EOL;
            echo "   \"" . PHP_EOL;
            echo PHP_EOL;
            echo "2. 或运行迁移脚本:" . PHP_EOL;
            echo "   php 04.update/wecom-integration/database_migration.php" . PHP_EOL;
            echo PHP_EOL;
        }
        
        // 检查数据完整性
        logInfo("检查数据完整性...");
        
        // 检查用户数据
        if (tableExists($pdo, 'svn_users')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM svn_users");
            $userCount = $stmt->fetchColumn();
            logInfo("用户总数: $userCount");
            
            if ($userCount > 0) {
                // 检查是否有管理员用户
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM svn_users WHERE svn_user_name = 'admin'");
                $stmt->execute();
                $adminCount = $stmt->fetchColumn();
                
                if ($adminCount > 0) {
                    logSuccess("管理员账户存在");
                } else {
                    logWarning("未找到管理员账户");
                }
            }
        }
        
        // 检查仓库数据
        if (tableExists($pdo, 'svn_reps')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM svn_reps");
            $repCount = $stmt->fetchColumn();
            logInfo("仓库总数: $repCount");
        }
        
        logSuccess("数据库验证完成");
        return true;
        
    } catch (Exception $e) {
        logError("数据库验证失败: " . $e->getMessage());
        return false;
    }
}

// 显示使用帮助
function showHelp() {
    echo "SQLite 数据库迁移验证脚本" . PHP_EOL;
    echo PHP_EOL;
    echo "用法:" . PHP_EOL;
    echo "  php verify_sqlite_migration.php [选项]" . PHP_EOL;
    echo PHP_EOL;
    echo "选项:" . PHP_EOL;
    echo "  --help, -h    显示此帮助信息" . PHP_EOL;
    echo "  --db-path     指定数据库文件路径" . PHP_EOL;
    echo PHP_EOL;
    echo "示例:" . PHP_EOL;
    echo "  php verify_sqlite_migration.php" . PHP_EOL;
    echo "  php verify_sqlite_migration.php --db-path /path/to/database.db" . PHP_EOL;
}

// 处理命令行参数
if (isset($argv[1]) && in_array($argv[1], ['--help', '-h'])) {
    showHelp();
    exit(0);
}

// 运行验证
if (verifyMigration()) {
    logSuccess("验证通过，数据库迁移正常");
    exit(0);
} else {
    logError("验证失败，请检查数据库迁移");
    exit(1);
}
?>
