<?php
/*
 * @Author: SVNAdmin WeChat Integration
 * 
 * @LastEditors: SVNAdmin WeChat Integration
 * 
 * @Description: 企业微信集成功能安装和配置脚本
 */

/**
 * 企业微信集成功能安装程序
 */

/**
 * 将工作模式限制在cli模式
 */
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode' . PHP_EOL);
}

/**
 * 开启错误信息
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('BASE_PATH', __DIR__ . '/..');

require_once BASE_PATH . '/app/util/Config.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

class WeComInstaller
{
    private $configSvn;
    private $configDatabase;
    private $database;
    private $installSteps = [
        'check_environment',
        'check_database',
        'create_tables',
        'create_config',
        'setup_hooks',
        'test_installation'
    ];

    public function __construct()
    {
        Config::load(BASE_PATH . '/config/');
        $this->configSvn = Config::get('svn');
        $this->configDatabase = Config::get('database');
        
        // 处理数据库文件路径
        if (array_key_exists('database_file', $this->configDatabase)) {
            $this->configDatabase['database_file'] = sprintf(
                $this->configDatabase['database_file'], 
                $this->configSvn['home_path']
            );
        }
    }

    /**
     * 运行安装程序
     */
    public function run($argv)
    {
        $this->printHeader();
        
        if (isset($argv[1])) {
            $action = $argv[1];
            switch ($action) {
                case 'install':
                    $this->install();
                    break;
                case 'uninstall':
                    $this->uninstall();
                    break;
                case 'check':
                    $this->checkInstallation();
                    break;
                case 'repair':
                    $this->repairInstallation();
                    break;
                default:
                    $this->showUsage();
                    break;
            }
        } else {
            $this->showUsage();
        }
    }

    /**
     * 显示程序头部信息
     */
    private function printHeader()
    {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo "  SVNAdmin 企业微信集成功能安装程序" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 显示使用帮助
     */
    private function showUsage()
    {
        echo "用法：php wecom_install.php [action]" . PHP_EOL;
        echo PHP_EOL;
        echo "可用操作：" . PHP_EOL;
        echo "  install   - 安装企业微信集成功能" . PHP_EOL;
        echo "  uninstall - 卸载企业微信集成功能" . PHP_EOL;
        echo "  check     - 检查安装状态" . PHP_EOL;
        echo "  repair    - 修复安装问题" . PHP_EOL;
        echo PHP_EOL;
        echo "示例：" . PHP_EOL;
        echo "  php wecom_install.php install" . PHP_EOL;
        echo "  php wecom_install.php check" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 执行安装
     */
    private function install()
    {
        echo "开始安装企业微信集成功能..." . PHP_EOL;
        echo PHP_EOL;

        foreach ($this->installSteps as $step) {
            echo "执行步骤: " . $this->getStepName($step) . "..." . PHP_EOL;
            
            $result = $this->$step();
            
            if ($result['success']) {
                echo "✓ " . $result['message'] . PHP_EOL;
            } else {
                echo "✗ " . $result['message'] . PHP_EOL;
                echo "安装失败，请检查错误信息并重试。" . PHP_EOL;
                exit(1);
            }
            echo PHP_EOL;
        }

        echo "========================================" . PHP_EOL;
        echo "企业微信集成功能安装完成！" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;
        echo "下一步操作：" . PHP_EOL;
        echo "1. 在企业微信管理后台创建应用并获取配置信息" . PHP_EOL;
        echo "2. 访问 SVNAdmin Web 界面的企业微信配置页面" . PHP_EOL;
        echo "3. 填写企业微信应用配置信息" . PHP_EOL;
        echo "4. 启动守护进程：php svnadmind.php start" . PHP_EOL;
        echo "5. 启动通知守护进程：php wecom_notification_daemon.php start" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 执行卸载
     */
    private function uninstall()
    {
        echo "开始卸载企业微信集成功能..." . PHP_EOL;
        echo PHP_EOL;

        // 确认操作
        echo "警告：此操作将删除所有企业微信相关的数据和配置！" . PHP_EOL;
        echo "是否继续？(y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'y' && trim($line) !== 'Y') {
            echo "操作已取消。" . PHP_EOL;
            exit(0);
        }

        // 停止守护进程
        $this->stopDaemons();

        // 删除数据库表
        $this->dropTables();

        // 删除配置文件
        $this->removeConfig();

        // 删除钩子脚本
        $this->removeHooks();

        echo "========================================" . PHP_EOL;
        echo "企业微信集成功能卸载完成！" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 检查安装状态
     */
    private function checkInstallation()
    {
        echo "检查企业微信集成功能安装状态..." . PHP_EOL;
        echo PHP_EOL;

        $checks = [
            'check_environment' => '环境检查',
            'check_database' => '数据库检查',
            'check_tables' => '数据表检查',
            'check_config' => '配置文件检查',
            'check_hooks' => '钩子脚本检查',
            'check_daemons' => '守护进程检查'
        ];

        $allPassed = true;

        foreach ($checks as $method => $name) {
            echo "检查 {$name}..." . PHP_EOL;
            
            if (method_exists($this, $method)) {
                $result = $this->$method();
                
                if ($result['success']) {
                    echo "✓ " . $result['message'] . PHP_EOL;
                } else {
                    echo "✗ " . $result['message'] . PHP_EOL;
                    $allPassed = false;
                }
            } else {
                echo "✗ 检查方法不存在" . PHP_EOL;
                $allPassed = false;
            }
            echo PHP_EOL;
        }

        if ($allPassed) {
            echo "========================================" . PHP_EOL;
            echo "企业微信集成功能安装正常！" . PHP_EOL;
            echo "========================================" . PHP_EOL;
        } else {
            echo "========================================" . PHP_EOL;
            echo "发现安装问题，请运行修复命令：" . PHP_EOL;
            echo "php wecom_install.php repair" . PHP_EOL;
            echo "========================================" . PHP_EOL;
        }
        echo PHP_EOL;
    }

    /**
     * 修复安装问题
     */
    private function repairInstallation()
    {
        echo "开始修复企业微信集成功能..." . PHP_EOL;
        echo PHP_EOL;

        // 重新执行安装步骤
        $this->install();
    }

    /**
     * 检查环境
     */
    private function check_environment()
    {
        // 检查 PHP 版本
        if (version_compare(PHP_VERSION, '7.2.0', '<')) {
            return [
                'success' => false,
                'message' => 'PHP 版本过低，需要 7.2.0 或更高版本'
            ];
        }

        // 检查必需的扩展
        $requiredExtensions = ['curl', 'json', 'mbstring', 'openssl'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        if (!empty($missingExtensions)) {
            return [
                'success' => false,
                'message' => '缺少必需的 PHP 扩展: ' . implode(', ', $missingExtensions)
            ];
        }

        // 检查目录权限
        $directories = [
            BASE_PATH . '/config',
            BASE_PATH . '/server',
            $this->configSvn['log_base_path']
        ];

        foreach ($directories as $dir) {
            if (!is_writable($dir)) {
                return [
                    'success' => false,
                    'message' => "目录不可写: {$dir}"
                ];
            }
        }

        return [
            'success' => true,
            'message' => '环境检查通过'
        ];
    }

    /**
     * 检查数据库
     */
    private function check_database()
    {
        try {
            $this->database = new Medoo($this->configDatabase);
            
            // 测试数据库连接
            $this->database->query('SELECT 1');
            
            return [
                'success' => true,
                'message' => '数据库连接正常'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '数据库连接失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 创建数据库表
     */
    private function create_tables()
    {
        try {
            // 确保数据库连接
            if (!$this->database) {
                $this->database = new Medoo($this->configDatabase);
            }

            // 根据数据库类型选择 SQL 文件
            $databaseType = $this->configDatabase['database_type'] ?? 'sqlite';
            $sqlFile = BASE_PATH . "/templete/database/{$databaseType}/wecom_tables.sql";

            if (!file_exists($sqlFile)) {
                return [
                    'success' => false,
                    'message' => "SQL 文件不存在: {$sqlFile}"
                ];
            }

            $sql = file_get_contents($sqlFile);
            
            // 分割 SQL 语句
            $statements = $this->splitSqlStatements($sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->database->query($statement);
                }
            }

            return [
                'success' => true,
                'message' => '数据库表创建成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '数据库表创建失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 创建配置文件
     */
    private function create_config()
    {
        $configFile = BASE_PATH . '/config/wecom.php';
        
        // 如果配置文件已存在，跳过
        if (file_exists($configFile)) {
            return [
                'success' => true,
                'message' => '配置文件已存在'
            ];
        }

        $configTemplate = BASE_PATH . '/config/wecom.php.template';
        
        // 如果有模板文件，复制模板
        if (file_exists($configTemplate)) {
            if (copy($configTemplate, $configFile)) {
                return [
                    'success' => true,
                    'message' => '配置文件创建成功'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '配置文件创建失败'
                ];
            }
        }

        // 创建默认配置文件
        $defaultConfig = $this->getDefaultConfig();
        
        if (file_put_contents($configFile, $defaultConfig)) {
            return [
                'success' => true,
                'message' => '默认配置文件创建成功'
            ];
        } else {
            return [
                'success' => false,
                'message' => '配置文件创建失败'
            ];
        }
    }

    /**
     * 设置钩子脚本
     */
    private function setup_hooks()
    {
        $hookTemplateDir = BASE_PATH . '/templete/hooks/wecom_notify';
        
        if (!is_dir($hookTemplateDir)) {
            return [
                'success' => false,
                'message' => '钩子模板目录不存在'
            ];
        }

        // 检查钩子模板文件
        $requiredFiles = ['post-commit', 'post-revprop-change', 'hookName', 'hookDescription'];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($hookTemplateDir . '/' . $file)) {
                return [
                    'success' => false,
                    'message' => "钩子模板文件不存在: {$file}"
                ];
            }
        }

        return [
            'success' => true,
            'message' => '钩子脚本检查完成'
        ];
    }

    /**
     * 测试安装
     */
    private function test_installation()
    {
        try {
            // 测试配置文件加载
            $wecomConfig = Config::get('wecom');
            
            if (!is_array($wecomConfig)) {
                return [
                    'success' => false,
                    'message' => '企业微信配置文件加载失败'
                ];
            }

            // 测试数据库表
            $tables = [
                'wecom_config',
                'wecom_departments',
                'wecom_users',
                'wecom_notification_rules',
                'wecom_notification_logs',
                'wecom_sync_logs',
                'wecom_api_logs',
                'wecom_notification_queue'
            ];

            foreach ($tables as $table) {
                try {
                    $this->database->select($table, ['*'], ['LIMIT' => 1]);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => "数据表测试失败: {$table}"
                    ];
                }
            }

            return [
                'success' => true,
                'message' => '安装测试通过'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '安装测试失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查数据表
     */
    private function check_tables()
    {
        try {
            if (!$this->database) {
                $this->database = new Medoo($this->configDatabase);
            }

            $tables = [
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

            foreach ($tables as $table) {
                try {
                    $this->database->select($table, ['*'], ['LIMIT' => 1]);
                } catch (\Exception $e) {
                    $missingTables[] = $table;
                }
            }

            if (!empty($missingTables)) {
                return [
                    'success' => false,
                    'message' => '缺少数据表: ' . implode(', ', $missingTables)
                ];
            }

            return [
                'success' => true,
                'message' => '所有数据表存在'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '数据表检查失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查配置文件
     */
    private function check_config()
    {
        $configFile = BASE_PATH . '/config/wecom.php';
        
        if (!file_exists($configFile)) {
            return [
                'success' => false,
                'message' => '企业微信配置文件不存在'
            ];
        }

        try {
            $config = Config::get('wecom');
            
            if (!is_array($config)) {
                return [
                    'success' => false,
                    'message' => '配置文件格式错误'
                ];
            }

            return [
                'success' => true,
                'message' => '配置文件正常'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '配置文件检查失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查钩子脚本
     */
    private function check_hooks()
    {
        $hookDir = BASE_PATH . '/templete/hooks/wecom_notify';
        
        if (!is_dir($hookDir)) {
            return [
                'success' => false,
                'message' => '钩子脚本目录不存在'
            ];
        }

        $requiredFiles = ['post-commit', 'post-revprop-change', 'hookName', 'hookDescription'];
        $missingFiles = [];

        foreach ($requiredFiles as $file) {
            if (!file_exists($hookDir . '/' . $file)) {
                $missingFiles[] = $file;
            }
        }

        if (!empty($missingFiles)) {
            return [
                'success' => false,
                'message' => '缺少钩子文件: ' . implode(', ', $missingFiles)
            ];
        }

        return [
            'success' => true,
            'message' => '钩子脚本完整'
        ];
    }

    /**
     * 检查守护进程
     */
    private function check_daemons()
    {
        $daemonFiles = [
            BASE_PATH . '/server/svnadmind.php',
            BASE_PATH . '/server/wecom_notification_daemon.php'
        ];

        $missingFiles = [];

        foreach ($daemonFiles as $file) {
            if (!file_exists($file)) {
                $missingFiles[] = basename($file);
            }
        }

        if (!empty($missingFiles)) {
            return [
                'success' => false,
                'message' => '缺少守护进程文件: ' . implode(', ', $missingFiles)
            ];
        }

        return [
            'success' => true,
            'message' => '守护进程文件完整'
        ];
    }

    /**
     * 停止守护进程
     */
    private function stopDaemons()
    {
        echo "停止守护进程..." . PHP_EOL;
        
        // 停止主守护进程
        $mainDaemon = BASE_PATH . '/server/svnadmind.php';
        if (file_exists($mainDaemon)) {
            shell_exec("php {$mainDaemon} stop 2>/dev/null");
        }

        // 停止通知守护进程
        $notificationDaemon = BASE_PATH . '/server/wecom_notification_daemon.php';
        if (file_exists($notificationDaemon)) {
            shell_exec("php {$notificationDaemon} stop 2>/dev/null");
        }

        echo "守护进程已停止" . PHP_EOL;
    }

    /**
     * 删除数据库表
     */
    private function dropTables()
    {
        echo "删除数据库表..." . PHP_EOL;
        
        try {
            if (!$this->database) {
                $this->database = new Medoo($this->configDatabase);
            }

            $tables = [
                'wecom_notification_queue',
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
                    $this->database->query("DROP TABLE IF EXISTS {$table}");
                    echo "已删除表: {$table}" . PHP_EOL;
                } catch (\Exception $e) {
                    echo "删除表失败: {$table} - " . $e->getMessage() . PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            echo "删除数据库表失败: " . $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * 删除配置文件
     */
    private function removeConfig()
    {
        echo "删除配置文件..." . PHP_EOL;
        
        $configFile = BASE_PATH . '/config/wecom.php';
        
        if (file_exists($configFile)) {
            if (unlink($configFile)) {
                echo "已删除配置文件: wecom.php" . PHP_EOL;
            } else {
                echo "删除配置文件失败: wecom.php" . PHP_EOL;
            }
        }
    }

    /**
     * 删除钩子脚本
     */
    private function removeHooks()
    {
        echo "删除钩子脚本..." . PHP_EOL;
        
        $hookDir = BASE_PATH . '/templete/hooks/wecom_notify';
        
        if (is_dir($hookDir)) {
            $this->removeDirectory($hookDir);
            echo "已删除钩子脚本目录" . PHP_EOL;
        }
    }

    /**
     * 递归删除目录
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    /**
     * 分割 SQL 语句
     */
    private function splitSqlStatements($sql)
    {
        // 移除注释
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // 分割语句
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($statement) {
            return !empty(trim($statement));
        });
    }

    /**
     * 获取步骤名称
     */
    private function getStepName($step)
    {
        $names = [
            'check_environment' => '环境检查',
            'check_database' => '数据库检查',
            'create_tables' => '创建数据表',
            'create_config' => '创建配置文件',
            'setup_hooks' => '设置钩子脚本',
            'test_installation' => '安装测试'
        ];

        return $names[$step] ?? $step;
    }

    /**
     * 获取默认配置
     */
    private function getDefaultConfig()
    {
        return '<?php
/*
 * 企业微信集成配置文件
 * 
 * 安装完成后请修改以下配置信息
 */

return [
    // 基础配置
    "enabled" => false,
    "corp_id" => "",
    "corp_secret" => "",
    "agent_id" => "",
    
    // API 配置
    "api_base_url" => "https://qyapi.weixin.qq.com",
    "token_cache_time" => 7200,
    "request_timeout" => 30,
    "max_retries" => 3,
    
    // 同步配置
    "sync_enabled" => true,
    "sync_departments" => true,
    "sync_users" => true,
    "sync_permissions" => true,
    "sync_interval" => 300,
    "full_sync_interval" => 86400,
    
    // 通知配置
    "notification_enabled" => true,
    "default_webhook_url" => "",
    "batch_notification" => true,
    "notification_timeout" => 10,
    
    // 日志配置
    "log_enabled" => true,
    "log_level" => "info",
    "log_retention_days" => 30,
    
    // 用户映射配置
    "user_mapping" => [
        "auto_create_svn_user" => false,
        "default_password" => "123456",
        "username_prefix" => "",
        "match_by_email" => true,
        "match_by_mobile" => true,
        "match_by_userid" => true
    ],
    
    // 部门映射配置
    "department_mapping" => [
        "auto_create_svn_group" => true,
        "group_prefix" => "",
        "sync_hierarchy" => true,
        "root_department_id" => 1
    ],
    
    // 权限映射配置
    "permission_mapping" => [
        "default_permission" => "r",
        "admin_permission" => "rw",
        "inherit_parent_permission" => true,
        "department_based_permission" => true
    ]
];
';
    }
}

// 运行安装程序
$installer = new WeComInstaller();
$installer->run($argv);
