<?php

/**
 * 企业微信集成高级配置向导
 * 
 * 基于配置模板的交互式配置生成工具
 * 
 * 使用方法：
 * php 02.php/templete/wecom/setup_wizard_advanced.php
 * 
 * @version 1.0
 * @author SVNAdmin Team
 * @date 2024-08-29
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置工作目录
$baseDir = dirname(dirname(dirname(__DIR__)));
chdir($baseDir);

// 颜色定义
class Colors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const PURPLE = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[1;37m";
    const NC = "\033[0m"; // No Color
}

class WeComSetupWizard {
    private $config = [];
    private $templatePath = '02.php/templete/wecom/config_template.php';
    private $outputPath = '02.php/config/wecom.php';
    
    public function __construct() {
        $this->loadTemplate();
    }
    
    /**
     * 加载配置模板
     */
    private function loadTemplate() {
        if (!file_exists($this->templatePath)) {
            $this->error("配置模板文件不存在: {$this->templatePath}");
            exit(1);
        }
        
        $this->config = require $this->templatePath;
        $this->info("配置模板加载成功");
    }
    
    /**
     * 运行配置向导
     */
    public function run() {
        $this->printHeader();
        
        // 检查现有配置
        if (file_exists($this->outputPath)) {
            if (!$this->confirm("检测到现有配置文件，是否覆盖？")) {
                $this->info("配置向导已取消");
                return;
            }
            
            // 备份现有配置
            $backupPath = $this->outputPath . '.backup.' . date('YmdHis');
            copy($this->outputPath, $backupPath);
            $this->success("现有配置已备份到: {$backupPath}");
        }
        
        // 配置向导步骤
        $this->configureBasicSettings();
        $this->configureSync();
        $this->configureDepartmentMapping();
        $this->configureUserMatching();
        $this->configurePermissions();
        $this->configureNotifications();
        $this->configureAdvanced();
        
        // 生成配置文件
        $this->generateConfig();
        
        // 验证配置
        $this->validateConfig();
        
        $this->printSummary();
    }
    
    /**
     * 配置基础设置
     */
    private function configureBasicSettings() {
        $this->section("基础配置");
        
        $this->info("请输入企业微信应用信息（在企业微信管理后台获取）：");
        
        // 企业 ID
        $corpId = $this->prompt("企业 ID (CorpId)", $this->config['corp_id']);
        if (!empty($corpId) && $corpId !== 'your_corp_id_here') {
            $this->config['corp_id'] = $corpId;
        }
        
        // 应用密钥
        $corpSecret = $this->prompt("应用密钥 (Secret)", '', true);
        if (!empty($corpSecret) && $corpSecret !== 'your_corp_secret_here') {
            $this->config['corp_secret'] = $corpSecret;
        }
        
        // 应用 ID
        $agentId = $this->prompt("应用 ID (AgentId)", $this->config['agent_id']);
        if (!empty($agentId) && $agentId !== 'your_agent_id_here') {
            $this->config['agent_id'] = $agentId;
        }
        
        // 功能开关
        $this->config['sync_enabled'] = $this->confirm("是否启用数据同步？", $this->config['sync_enabled']);
        $this->config['notification_enabled'] = $this->confirm("是否启用消息通知？", $this->config['notification_enabled']);
        $this->config['debug'] = $this->confirm("是否启用调试模式？", $this->config['debug']);
    }
    
    /**
     * 配置同步设置
     */
    private function configureSync() {
        if (!$this->config['sync_enabled']) {
            return;
        }
        
        $this->section("同步配置");
        
        // 同步间隔
        $deptInterval = $this->promptInt("部门同步间隔（秒）", $this->config['sync']['department_interval']);
        $this->config['sync']['department_interval'] = $deptInterval;
        
        $userInterval = $this->promptInt("用户同步间隔（秒）", $this->config['sync']['user_interval']);
        $this->config['sync']['user_interval'] = $userInterval;
        
        $permInterval = $this->promptInt("权限同步间隔（秒）", $this->config['sync']['permission_interval']);
        $this->config['sync']['permission_interval'] = $permInterval;
        
        // 同步选项
        $this->config['sync']['auto_create_users'] = $this->confirm(
            "是否自动创建 SVN 用户？", 
            $this->config['sync']['auto_create_users']
        );
        
        $this->config['sync']['auto_update_users'] = $this->confirm(
            "是否自动更新用户信息？", 
            $this->config['sync']['auto_update_users']
        );
        
        $batchSize = $this->promptInt("批量处理大小", $this->config['sync']['batch_size']);
        $this->config['sync']['batch_size'] = $batchSize;
    }
    
    /**
     * 配置部门映射
     */
    private function configureDepartmentMapping() {
        if (!$this->config['sync_enabled']) {
            return;
        }
        
        $this->section("部门映射配置");
        
        // 用户组前缀
        $prefix = $this->prompt("SVN 用户组名前缀", $this->config['department_mapping']['group_name_prefix']);
        $this->config['department_mapping']['group_name_prefix'] = $prefix;
        
        // 命名格式
        $formats = ['lowercase', 'uppercase', 'original'];
        $format = $this->choice("用户组命名格式", $formats, $this->config['department_mapping']['group_name_format']);
        $this->config['department_mapping']['group_name_format'] = $format;
        
        // 自动创建用户组
        $this->config['department_mapping']['auto_create_groups'] = $this->confirm(
            "是否自动创建用户组？", 
            $this->config['department_mapping']['auto_create_groups']
        );
        
        // 保持层级关系
        $this->config['department_mapping']['preserve_hierarchy'] = $this->confirm(
            "是否保持部门层级关系？", 
            $this->config['department_mapping']['preserve_hierarchy']
        );
    }
    
    /**
     * 配置用户匹配
     */
    private function configureUserMatching() {
        if (!$this->config['sync_enabled']) {
            return;
        }
        
        $this->section("用户匹配配置");
        
        // 匹配策略
        $strategies = ['userid', 'email', 'mobile', 'name'];
        $this->info("请选择用户匹配策略（按优先级排序）：");
        
        $selectedStrategies = [];
        foreach ($strategies as $strategy) {
            if ($this->confirm("启用 {$strategy} 匹配？")) {
                $selectedStrategies[] = $strategy;
            }
        }
        
        if (!empty($selectedStrategies)) {
            $this->config['user_matching']['strategies'] = $selectedStrategies;
        }
        
        // 模糊匹配
        $this->config['user_matching']['fuzzy_match'] = $this->confirm(
            "是否启用模糊匹配？", 
            $this->config['user_matching']['fuzzy_match']
        );
        
        if ($this->config['user_matching']['fuzzy_match']) {
            $threshold = $this->promptFloat("模糊匹配阈值 (0-1)", $this->config['user_matching']['fuzzy_threshold']);
            $this->config['user_matching']['fuzzy_threshold'] = $threshold;
        }
        
        // 自动创建用户
        $this->config['user_matching']['auto_create_svn_users'] = $this->confirm(
            "是否自动创建 SVN 用户？", 
            $this->config['user_matching']['auto_create_svn_users']
        );
        
        if ($this->config['user_matching']['auto_create_svn_users']) {
            $defaultPassword = $this->prompt("新用户默认密码", $this->config['user_matching']['default_password']);
            $this->config['user_matching']['default_password'] = $defaultPassword;
        }
    }
    
    /**
     * 配置权限映射
     */
    private function configurePermissions() {
        if (!$this->config['sync_enabled']) {
            return;
        }
        
        $this->section("权限映射配置");
        
        // 默认权限
        $permissions = ['', 'r', 'rw'];
        $defaultPerm = $this->choice("默认权限", $permissions, $this->config['permission_mapping']['default_permission']);
        $this->config['permission_mapping']['default_permission'] = $defaultPerm;
        
        // 管理员部门
        $adminDepts = $this->prompt("管理员部门 ID（逗号分隔）", implode(',', $this->config['permission_mapping']['admin_departments']));
        if (!empty($adminDepts)) {
            $this->config['permission_mapping']['admin_departments'] = array_map('intval', explode(',', $adminDepts));
        }
        
        // 权限继承
        $this->config['permission_mapping']['inherit_permissions'] = $this->confirm(
            "是否启用权限继承？", 
            $this->config['permission_mapping']['inherit_permissions']
        );
    }
    
    /**
     * 配置通知设置
     */
    private function configureNotifications() {
        if (!$this->config['notification_enabled']) {
            return;
        }
        
        $this->section("通知配置");
        
        // 默认 Webhook
        $webhook = $this->prompt("默认 Webhook 地址", $this->config['notification']['default_webhook']);
        $this->config['notification']['default_webhook'] = $webhook;
        
        // 消息队列
        $this->config['notification']['queue_enabled'] = $this->confirm(
            "是否启用消息队列？", 
            $this->config['notification']['queue_enabled']
        );
        
        // 批量处理
        $this->config['notification']['batch_processing'] = $this->confirm(
            "是否启用批量处理？", 
            $this->config['notification']['batch_processing']
        );
        
        if ($this->config['notification']['queue_enabled']) {
            $queueSize = $this->promptInt("队列最大大小", $this->config['notification']['queue']['max_size']);
            $this->config['notification']['queue']['max_size'] = $queueSize;
            
            $batchSize = $this->promptInt("批量处理大小", $this->config['notification']['queue']['batch_size']);
            $this->config['notification']['queue']['batch_size'] = $batchSize;
        }
        
        // 频率限制
        $this->config['notification']['rate_limit']['enabled'] = $this->confirm(
            "是否启用频率限制？", 
            $this->config['notification']['rate_limit']['enabled']
        );
    }
    
    /**
     * 配置高级设置
     */
    private function configureAdvanced() {
        if (!$this->confirm("是否配置高级设置？")) {
            return;
        }
        
        $this->section("高级配置");
        
        // 缓存配置
        $this->config['cache']['enabled'] = $this->confirm(
            "是否启用缓存？", 
            $this->config['cache']['enabled']
        );
        
        if ($this->config['cache']['enabled']) {
            $drivers = ['file', 'redis'];
            $driver = $this->choice("缓存驱动", $drivers, $this->config['cache']['driver']);
            $this->config['cache']['driver'] = $driver;
            
            if ($driver === 'redis') {
                $host = $this->prompt("Redis 主机", $this->config['cache']['redis']['host']);
                $this->config['cache']['redis']['host'] = $host;
                
                $port = $this->promptInt("Redis 端口", $this->config['cache']['redis']['port']);
                $this->config['cache']['redis']['port'] = $port;
                
                $password = $this->prompt("Redis 密码", $this->config['cache']['redis']['password'], true);
                $this->config['cache']['redis']['password'] = $password;
            }
        }
        
        // 日志配置
        $levels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        $level = $this->choice("日志级别", $levels, $this->config['logging']['level']);
        $this->config['logging']['level'] = $level;
        
        $maxFiles = $this->promptInt("最大日志文件数", $this->config['logging']['max_files']);
        $this->config['logging']['max_files'] = $maxFiles;
        
        // 安全配置
        $this->config['security']['verify_ssl'] = $this->confirm(
            "是否验证 SSL 证书？", 
            $this->config['security']['verify_ssl']
        );
        
        // 监控配置
        $this->config['monitoring']['enabled'] = $this->confirm(
            "是否启用监控？", 
            $this->config['monitoring']['enabled']
        );
    }
    
    /**
     * 生成配置文件
     */
    private function generateConfig() {
        $this->section("生成配置文件");
        
        // 设置时间戳
        $this->config['created_at'] = date('Y-m-d H:i:s');
        $this->config['updated_at'] = date('Y-m-d H:i:s');
        $this->config['config_hash'] = md5(serialize($this->config));
        
        // 确保目录存在
        $configDir = dirname($this->outputPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        // 生成配置文件内容
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * 企业微信集成配置文件\n";
        $content .= " * \n";
        $content .= " * 此文件由配置向导自动生成\n";
        $content .= " * 生成时间: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * \n";
        $content .= " * 警告：请勿直接编辑此文件，使用配置向导或 Web 界面进行修改\n";
        $content .= " */\n\n";
        $content .= "return " . $this->arrayToString($this->config, 0) . ";\n";
        
        // 写入文件
        if (file_put_contents($this->outputPath, $content) === false) {
            $this->error("配置文件写入失败: {$this->outputPath}");
            exit(1);
        }
        
        $this->success("配置文件生成成功: {$this->outputPath}");
    }
    
    /**
     * 验证配置
     */
    private function validateConfig() {
        $this->section("验证配置");
        
        $errors = [];
        
        // 检查必填项
        if (empty($this->config['corp_id']) || $this->config['corp_id'] === 'your_corp_id_here') {
            $errors[] = "企业 ID 未配置";
        }
        
        if (empty($this->config['corp_secret']) || $this->config['corp_secret'] === 'your_corp_secret_here') {
            $errors[] = "应用密钥未配置";
        }
        
        if (empty($this->config['agent_id']) || $this->config['agent_id'] === 'your_agent_id_here') {
            $errors[] = "应用 ID 未配置";
        }
        
        // 检查通知配置
        if ($this->config['notification_enabled'] && empty($this->config['notification']['default_webhook'])) {
            $this->warning("未配置默认 Webhook 地址，需要在通知规则中单独配置");
        }
        
        if (!empty($errors)) {
            $this->error("配置验证失败：");
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
            
            if (!$this->confirm("是否继续？")) {
                exit(1);
            }
        } else {
            $this->success("配置验证通过");
        }
    }
    
    /**
     * 打印摘要
     */
    private function printSummary() {
        $this->section("配置摘要");
        
        echo Colors::WHITE . "企业微信集成配置完成！" . Colors::NC . "\n\n";
        
        echo "配置文件: " . Colors::CYAN . $this->outputPath . Colors::NC . "\n";
        echo "企业 ID: " . Colors::CYAN . $this->config['corp_id'] . Colors::NC . "\n";
        echo "应用 ID: " . Colors::CYAN . $this->config['agent_id'] . Colors::NC . "\n";
        echo "数据同步: " . ($this->config['sync_enabled'] ? Colors::GREEN . "启用" : Colors::RED . "禁用") . Colors::NC . "\n";
        echo "消息通知: " . ($this->config['notification_enabled'] ? Colors::GREEN . "启用" : Colors::RED . "禁用") . Colors::NC . "\n";
        echo "调试模式: " . ($this->config['debug'] ? Colors::YELLOW . "启用" : Colors::GREEN . "禁用") . Colors::NC . "\n";
        
        echo "\n" . Colors::WHITE . "下一步操作：" . Colors::NC . "\n";
        echo "1. 运行安装脚本: " . Colors::CYAN . "php 02.php/server/wecom_install.php install" . Colors::NC . "\n";
        echo "2. 启动守护进程: " . Colors::CYAN . "php 02.php/server/svnadmind.php start" . Colors::NC . "\n";
        echo "3. 启动通知守护进程: " . Colors::CYAN . "php 02.php/server/wecom_notification_daemon.php start" . Colors::NC . "\n";
        echo "4. 访问 Web 管理界面: " . Colors::CYAN . "http://your-domain/01.web/#/wecom" . Colors::NC . "\n";
        
        echo "\n" . Colors::GREEN . "配置向导完成！" . Colors::NC . "\n";
    }
    
    // ==================== 工具方法 ====================
    
    private function printHeader() {
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n";
        echo Colors::WHITE . "        企业微信集成高级配置向导" . Colors::NC . "\n";
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n\n";
        
        echo "此向导将帮助您配置企业微信集成功能的所有选项。\n";
        echo "您可以随时按 Ctrl+C 退出向导。\n\n";
    }
    
    private function section($title) {
        echo "\n" . Colors::YELLOW . "--- {$title} ---" . Colors::NC . "\n";
    }
    
    private function prompt($question, $default = '', $hidden = false) {
        $defaultText = $default ? " [{$default}]" : '';
        echo Colors::CYAN . "{$question}{$defaultText}: " . Colors::NC;
        
        if ($hidden) {
            // 隐藏输入（用于密码）
            system('stty -echo');
            $input = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        } else {
            $input = trim(fgets(STDIN));
        }
        
        return $input ?: $default;
    }
    
    private function promptInt($question, $default = 0) {
        $input = $this->prompt($question, $default);
        return is_numeric($input) ? intval($input) : $default;
    }
    
    private function promptFloat($question, $default = 0.0) {
        $input = $this->prompt($question, $default);
        return is_numeric($input) ? floatval($input) : $default;
    }
    
    private function confirm($question, $default = false) {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $input = strtolower($this->prompt("{$question} ({$defaultText})"));
        
        if (empty($input)) {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }
    
    private function choice($question, $options, $default = '') {
        echo Colors::CYAN . "{$question}:" . Colors::NC . "\n";
        
        foreach ($options as $i => $option) {
            $marker = ($option === $default) ? '*' : ' ';
            echo "  {$marker} " . ($i + 1) . ". {$option}\n";
        }
        
        $input = $this->prompt("请选择 (1-" . count($options) . ")", '');
        
        if (is_numeric($input) && $input >= 1 && $input <= count($options)) {
            return $options[$input - 1];
        }
        
        return $default;
    }
    
    private function info($message) {
        echo Colors::BLUE . "[INFO] {$message}" . Colors::NC . "\n";
    }
    
    private function success($message) {
        echo Colors::GREEN . "[SUCCESS] {$message}" . Colors::NC . "\n";
    }
    
    private function warning($message) {
        echo Colors::YELLOW . "[WARNING] {$message}" . Colors::NC . "\n";
    }
    
    private function error($message) {
        echo Colors::RED . "[ERROR] {$message}" . Colors::NC . "\n";
    }
    
    private function arrayToString($array, $indent = 0) {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";
        
        foreach ($array as $key => $value) {
            $result .= $spaces . '    ';
            
            if (is_string($key)) {
                $result .= "'{$key}' => ";
            }
            
            if (is_array($value)) {
                $result .= $this->arrayToString($value, $indent + 1);
            } elseif (is_string($value)) {
                $result .= "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result .= 'null';
            } else {
                $result .= $value;
            }
            
            $result .= ",\n";
        }
        
        $result .= $spaces . ']';
        return $result;
    }
}

// 运行配置向导
if (php_sapi_name() === 'cli') {
    $wizard = new WeComSetupWizard();
    $wizard->run();
} else {
    echo "此脚本只能在命令行模式下运行\n";
    exit(1);
}
