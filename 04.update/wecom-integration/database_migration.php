<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信集成功能数据库迁移脚本
 */

//限制工作模式
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode' . PHP_EOL);
}

//调试
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('PRC');

define('BASE_PATH', dirname(dirname(__DIR__)));

if (!file_exists(BASE_PATH . '/02.php/extension/Medoo-1.7.10/src/Medoo.php')) {
    echo sprintf('找不到文件[%s]确认当前是否处于项目根目录下%s', BASE_PATH . '/02.php/extension/Medoo-1.7.10/src/Medoo.php', PHP_EOL);
    exit;
}

require_once BASE_PATH . '/02.php/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

class WeComDatabaseMigration
{
    private $database;
    private $config;

    public function __construct()
    {
        // 加载数据库配置
        $this->config = require BASE_PATH . '/02.php/config/database.php';
        
        // 处理 SQLite 路径占位符
        if ($this->config['database_type'] === 'sqlite') {
            $svnConfig = require BASE_PATH . '/02.php/config/svn.php';
            $this->config['database_file'] = sprintf($this->config['database_file'], $svnConfig['home_path']);
        }

        $this->database = new Medoo($this->config);
        
        echo "企业微信集成功能数据库迁移开始..." . PHP_EOL;
        echo "数据库类型: " . $this->config['database_type'] . PHP_EOL;
    }

    /**
     * 执行迁移
     */
    public function migrate()
    {
        try {
            // 检查是否已经迁移过
            if ($this->checkMigrationStatus()) {
                echo "企业微信集成功能已经安装，跳过迁移。" . PHP_EOL;
                return true;
            }

            echo "开始创建企业微信相关数据表..." . PHP_EOL;

            // 创建企业微信相关表
            $this->createWeComTables();

            // 扩展现有用户表
            $this->extendUserTable();

            // 插入初始配置数据
            $this->insertInitialData();

            // 标记迁移完成
            $this->markMigrationComplete();

            echo "企业微信集成功能数据库迁移完成！" . PHP_EOL;
            return true;

        } catch (Exception $e) {
            echo "迁移失败: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * 检查迁移状态
     */
    private function checkMigrationStatus()
    {
        try {
            // 检查 wecom_config 表是否存在
            $result = $this->database->query("SELECT name FROM " . 
                ($this->config['database_type'] === 'sqlite' ? 'sqlite_master' : 'information_schema.tables') . 
                " WHERE " . 
                ($this->config['database_type'] === 'sqlite' ? "type='table' AND name='wecom_config'" : "table_name='wecom_config'")
            )->fetchAll();
            
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 创建企业微信相关表
     */
    private function createWeComTables()
    {
        if ($this->config['database_type'] === 'sqlite') {
            $this->createSQLiteTables();
        } else {
            $this->createMySQLTables();
        }
    }

    /**
     * 创建 SQLite 表
     */
    private function createSQLiteTables()
    {
        $sqlFile = BASE_PATH . '/02.php/templete/database/sqlite/wecom_tables.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("找不到 SQLite 表结构文件: " . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^(PRAGMA|\/\*|\-\-)/', $statement)) {
                try {
                    $this->database->query($statement);
                    echo "执行 SQL: " . substr($statement, 0, 50) . "..." . PHP_EOL;
                } catch (Exception $e) {
                    // 忽略已存在的表错误
                    if (!strpos($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * 创建 MySQL 表
     */
    private function createMySQLTables()
    {
        $sqlFile = BASE_PATH . '/02.php/templete/database/mysql/wecom_tables.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("找不到 MySQL 表结构文件: " . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^(SET|\/\*|\-\-)/', $statement)) {
                try {
                    $this->database->query($statement);
                    echo "执行 SQL: " . substr($statement, 0, 50) . "..." . PHP_EOL;
                } catch (Exception $e) {
                    // 忽略已存在的表错误
                    if (!strpos($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * 扩展现有用户表
     */
    private function extendUserTable()
    {
        echo "扩展 svn_users 表结构..." . PHP_EOL;

        try {
            if ($this->config['database_type'] === 'sqlite') {
                // SQLite 添加字段
                $this->database->query("ALTER TABLE svn_users ADD COLUMN wecom_user_id TEXT DEFAULT ''");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN real_name TEXT DEFAULT ''");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN department_info TEXT DEFAULT ''");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN last_wecom_sync TEXT DEFAULT ''");
                
                // 创建索引
                $this->database->query("CREATE INDEX IF NOT EXISTS idx_svn_users_wecom_id ON svn_users (wecom_user_id)");
                
            } else {
                // MySQL 添加字段
                $this->database->query("ALTER TABLE svn_users ADD COLUMN wecom_user_id VARCHAR(255) DEFAULT '' COMMENT '企业微信用户ID'");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN real_name VARCHAR(255) DEFAULT '' COMMENT '真实姓名'");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN department_info TEXT COMMENT '部门信息'");
                $this->database->query("ALTER TABLE svn_users ADD COLUMN last_wecom_sync DATETIME DEFAULT NULL COMMENT '最后企业微信同步时间'");
                
                // 创建索引
                $this->database->query("ALTER TABLE svn_users ADD INDEX idx_wecom_user_id (wecom_user_id)");
            }
            
            echo "svn_users 表扩展完成。" . PHP_EOL;
            
        } catch (Exception $e) {
            // 如果字段已存在，忽略错误
            if (strpos($e->getMessage(), 'duplicate column') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "字段已存在，跳过扩展。" . PHP_EOL;
            } else {
                throw $e;
            }
        }
    }

    /**
     * 插入初始配置数据
     */
    private function insertInitialData()
    {
        echo "插入初始配置数据..." . PHP_EOL;

        // 检查是否已有配置数据
        $existingConfig = $this->database->select('wecom_config', ['id', 'notification_enabled'], ['id' => 1]);
        
        if (empty($existingConfig)) {
            // 首次安装：默认禁用通知，需要用户手动配置
            $this->database->insert('wecom_config', [
                'id' => 1,
                'corp_id' => '',
                'corp_secret' => '',
                'agent_id' => '',
                'sync_enabled' => 0,
                'notification_enabled' => 0,
                'sync_interval' => 3600,
                'default_chat_id' => '',
                'config_data' => $this->config['database_type'] === 'sqlite' ? '{}' : json_encode([]),
                'created_at' => $this->config['database_type'] === 'sqlite' ? 
                    date('Y-m-d H:i:s') : null,
                'updated_at' => $this->config['database_type'] === 'sqlite' ? 
                    date('Y-m-d H:i:s') : null
            ]);
            echo "初始配置数据插入完成（通知功能默认禁用）。" . PHP_EOL;
        } else {
            // 配置已存在：保护现有的notification_enabled设置
            echo "配置数据已存在，保护现有设置（notification_enabled=" . ($existingConfig[0]['notification_enabled'] ?? '未知') . "）。" . PHP_EOL;
        }
    }

    /**
     * 标记迁移完成
     */
    private function markMigrationComplete()
    {
        // 在系统配置中记录迁移状态
        $migrationFile = BASE_PATH . '/02.php/config/.wecom_migration_complete';
        file_put_contents($migrationFile, date('Y-m-d H:i:s'));
        echo "迁移状态已记录。" . PHP_EOL;
    }

    /**
     * 回滚迁移（用于测试）
     */
    public function rollback()
    {
        echo "开始回滚企业微信集成功能..." . PHP_EOL;

        try {
            // 删除企业微信相关表
            $tables = [
                'wecom_api_logs',
                'wecom_sync_logs', 
                'wecom_notification_logs',
                'wecom_notification_rules',
                'wecom_users',
                'wecom_departments',
                'wecom_config'
            ];

            foreach ($tables as $table) {
                try {
                    $this->database->query("DROP TABLE IF EXISTS " . $table);
                    echo "删除表: " . $table . PHP_EOL;
                } catch (Exception $e) {
                    echo "删除表失败 " . $table . ": " . $e->getMessage() . PHP_EOL;
                }
            }

            // 删除扩展字段（注意：SQLite 不支持删除列，需要重建表）
            if ($this->config['database_type'] === 'mysql') {
                try {
                    $this->database->query("ALTER TABLE svn_users DROP COLUMN wecom_user_id");
                    $this->database->query("ALTER TABLE svn_users DROP COLUMN real_name");
                    $this->database->query("ALTER TABLE svn_users DROP COLUMN department_info");
                    $this->database->query("ALTER TABLE svn_users DROP COLUMN last_wecom_sync");
                    echo "删除 svn_users 表扩展字段完成。" . PHP_EOL;
                } catch (Exception $e) {
                    echo "删除扩展字段失败: " . $e->getMessage() . PHP_EOL;
                }
            } else {
                echo "SQLite 不支持删除列，请手动处理 svn_users 表。" . PHP_EOL;
            }

            // 删除迁移标记
            $migrationFile = BASE_PATH . '/02.php/config/.wecom_migration_complete';
            if (file_exists($migrationFile)) {
                unlink($migrationFile);
            }

            echo "企业微信集成功能回滚完成！" . PHP_EOL;
            return true;

        } catch (Exception $e) {
            echo "回滚失败: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }
}

// 执行迁移
if (isset($argv[1]) && $argv[1] === 'rollback') {
    echo "警告：这将删除所有企业微信集成相关的数据！" . PHP_EOL;
    echo "确认回滚请输入 'yes': ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) === 'yes') {
        $migration = new WeComDatabaseMigration();
        $migration->rollback();
    } else {
        echo "回滚已取消。" . PHP_EOL;
    }
    fclose($handle);
} else {
    $migration = new WeComDatabaseMigration();
    $result = $migration->migrate();
    exit($result ? 0 : 1);
}
