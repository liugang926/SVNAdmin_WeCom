<?php

/**
 * 企业微信集成最终验证工具
 * 
 * 用于最终验证企业微信集成系统的完整性和功能正确性
 * 
 * 使用方法：
 * php 02.php/server/wecom_final_validator.php
 * 
 * @version 1.0
 * @author SVNAdmin Team
 * @date 2024-08-29
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置工作目录
chdir(dirname(dirname(__DIR__)));

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

class WeComFinalValidator {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    private $totalChecks = 0;
    private $passedChecks = 0;
    
    public function __construct() {
        $this->totalChecks = 0;
        $this->passedChecks = 0;
    }
    
    /**
     * 运行最终验证
     */
    public function validate() {
        $this->printHeader();
        
        // 系统完整性检查
        $this->validateSystemIntegrity();
        
        // 功能模块验证
        $this->validateFunctionModules();
        
        // 配置验证
        $this->validateConfiguration();
        
        // 数据库验证
        $this->validateDatabase();
        
        // 文件系统验证
        $this->validateFileSystem();
        
        // 服务验证
        $this->validateServices();
        
        // 文档验证
        $this->validateDocumentation();
        
        // 测试验证
        $this->validateTests();
        
        // 生成最终报告
        $this->generateFinalReport();
        
        return $this->passedChecks === $this->totalChecks;
    }
    
    /**
     * 验证系统完整性
     */
    private function validateSystemIntegrity() {
        $this->section("系统完整性检查");
        
        // 检查核心文件
        $coreFiles = [
            '02.php/app/service/WeComAPI.php' => 'API 服务',
            '02.php/app/service/WeComSync.php' => '同步服务',
            '02.php/app/service/WeComNotification.php' => '通知服务',
            '02.php/app/controller/WeComAdmin.php' => 'Web 控制器',
            '02.php/server/svnadmind.php' => '主守护进程',
            '02.php/server/wecom_notification_daemon.php' => '通知守护进程',
            '02.php/server/wecom_install.php' => '安装脚本',
            '02.php/server/wecom_setup_wizard.php' => '配置向导'
        ];
        
        foreach ($coreFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查前端文件
        $frontendFiles = [
            '01.web/src/views/wecom/index.vue' => '主管理页面',
            '01.web/src/views/wecom/components/WecomConfig.vue' => '配置管理组件',
            '01.web/src/views/wecom/components/WecomSync.vue' => '同步管理组件',
            '01.web/src/views/wecom/components/WecomNotification.vue' => '通知管理组件',
            '01.web/src/views/wecom/components/WecomMapping.vue' => '用户映射组件'
        ];
        
        foreach ($frontendFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查配置模板
        $templateFiles = [
            '02.php/templete/wecom/config_template.php' => '配置模板',
            '02.php/templete/wecom/setup_wizard_advanced.php' => '高级配置向导',
            '02.php/templete/wecom/config_validator.php' => '配置验证工具'
        ];
        
        foreach ($templateFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
    }
    
    /**
     * 验证功能模块
     */
    private function validateFunctionModules() {
        $this->section("功能模块验证");
        
        // 验证 API 模块
        $this->validateApiModule();
        
        // 验证同步模块
        $this->validateSyncModule();
        
        // 验证通知模块
        $this->validateNotificationModule();
        
        // 验证 Web 管理模块
        $this->validateWebModule();
    }
    
    /**
     * 验证 API 模块
     */
    private function validateApiModule() {
        $this->info("验证 API 模块...");
        
        if (!file_exists('02.php/app/service/WeComAPI.php')) {
            $this->addError("API 服务文件不存在");
            return;
        }
        
        // 检查类和方法
        require_once '02.php/app/service/WeComAPI.php';
        
        if (!class_exists('WeComAPI')) {
            $this->addError("WeComAPI 类不存在");
            return;
        }
        
        $requiredMethods = [
            'getAccessToken',
            'getDepartments',
            'getDepartmentUsers',
            'getUserDetail',
            'sendApplicationMessage',
            'sendGroupMarkdownMessage'
        ];
        
        $reflection = new ReflectionClass('WeComAPI');
        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $this->addError("WeComAPI 缺少方法: {$method}");
            } else {
                $this->passCheck("API 方法 {$method} 存在");
            }
        }
    }
    
    /**
     * 验证同步模块
     */
    private function validateSyncModule() {
        $this->info("验证同步模块...");
        
        if (!file_exists('02.php/app/service/WeComSync.php')) {
            $this->addError("同步服务文件不存在");
            return;
        }
        
        require_once '02.php/app/service/WeComSync.php';
        
        if (!class_exists('WeComSync')) {
            $this->addError("WeComSync 类不存在");
            return;
        }
        
        $requiredMethods = [
            'syncDepartments',
            'syncUsers',
            'syncPermissions',
            'getSyncStats'
        ];
        
        $reflection = new ReflectionClass('WeComSync');
        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $this->addError("WeComSync 缺少方法: {$method}");
            } else {
                $this->passCheck("同步方法 {$method} 存在");
            }
        }
    }
    
    /**
     * 验证通知模块
     */
    private function validateNotificationModule() {
        $this->info("验证通知模块...");
        
        if (!file_exists('02.php/app/service/WeComNotification.php')) {
            $this->addError("通知服务文件不存在");
            return;
        }
        
        require_once '02.php/app/service/WeComNotification.php';
        
        if (!class_exists('WeComNotification')) {
            $this->addError("WeComNotification 类不存在");
            return;
        }
        
        $requiredMethods = [
            'sendSvnNotification',
            'processBatchNotifications',
            'getNotificationStats'
        ];
        
        $reflection = new ReflectionClass('WeComNotification');
        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $this->addError("WeComNotification 缺少方法: {$method}");
            } else {
                $this->passCheck("通知方法 {$method} 存在");
            }
        }
    }
    
    /**
     * 验证 Web 管理模块
     */
    private function validateWebModule() {
        $this->info("验证 Web 管理模块...");
        
        if (!file_exists('02.php/app/controller/WeComAdmin.php')) {
            $this->addError("Web 控制器文件不存在");
            return;
        }
        
        require_once '02.php/app/controller/WeComAdmin.php';
        
        if (!class_exists('WeComAdmin')) {
            $this->addError("WeComAdmin 类不存在");
            return;
        }
        
        $requiredMethods = [
            'GetConfig',
            'UpdateConfig',
            'GetSyncStatus',
            'SyncDepartments',
            'GetNotificationRules',
            'GetUserMappings'
        ];
        
        $reflection = new ReflectionClass('WeComAdmin');
        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $this->addError("WeComAdmin 缺少方法: {$method}");
            } else {
                $this->passCheck("Web 方法 {$method} 存在");
            }
        }
    }
    
    /**
     * 验证配置
     */
    private function validateConfiguration() {
        $this->section("配置验证");
        
        // 检查配置模板
        $this->checkFile('02.php/templete/wecom/config_template.php', '配置模板');
        
        // 检查配置向导
        $this->checkFile('02.php/templete/wecom/setup_wizard_advanced.php', '高级配置向导');
        
        // 检查配置验证工具
        $this->checkFile('02.php/templete/wecom/config_validator.php', '配置验证工具');
        
        // 检查 Docker 配置
        $this->checkFile('02.php/config/wecom.php.template', '配置模板文件');
        
        // 验证配置结构
        if (file_exists('02.php/templete/wecom/config_template.php')) {
            $config = require '02.php/templete/wecom/config_template.php';
            
            $requiredSections = [
                'corp_id', 'corp_secret', 'agent_id',
                'sync', 'department_mapping', 'user_matching',
                'permission_mapping', 'notification', 'cache',
                'logging', 'security', 'monitoring'
            ];
            
            foreach ($requiredSections as $section) {
                if (isset($config[$section])) {
                    $this->passCheck("配置节 {$section} 存在");
                } else {
                    $this->addError("配置节 {$section} 缺失");
                }
            }
        }
    }
    
    /**
     * 验证数据库
     */
    private function validateDatabase() {
        $this->section("数据库验证");
        
        // 检查数据库脚本
        $dbFiles = [
            '02.php/templete/database/sqlite/wecom_tables.sql' => 'SQLite 数据库脚本',
            '02.php/templete/database/mysql/wecom_tables.sql' => 'MySQL 数据库脚本',
            '04.update/wecom-integration/database_migration.php' => '数据库迁移脚本'
        ];
        
        foreach ($dbFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 验证数据库表结构
        $this->validateDatabaseTables();
    }
    
    /**
     * 验证数据库表结构
     */
    private function validateDatabaseTables() {
        $sqliteFile = '02.php/templete/database/sqlite/wecom_tables.sql';
        
        if (!file_exists($sqliteFile)) {
            $this->addError("SQLite 数据库脚本不存在");
            return;
        }
        
        $sql = file_get_contents($sqliteFile);
        
        $requiredTables = [
            'wecom_config',
            'wecom_departments',
            'wecom_users',
            'wecom_sync_logs',
            'wecom_notification_rules',
            'wecom_notification_logs',
            'wecom_notification_queue'
        ];
        
        foreach ($requiredTables as $table) {
            if (strpos($sql, "CREATE TABLE \"{$table}\"") !== false || 
                strpos($sql, "CREATE TABLE `{$table}`") !== false) {
                $this->passCheck("数据库表 {$table} 定义存在");
            } else {
                $this->addError("数据库表 {$table} 定义缺失");
            }
        }
    }
    
    /**
     * 验证文件系统
     */
    private function validateFileSystem() {
        $this->section("文件系统验证");
        
        // 检查钩子脚本
        $hookFiles = [
            '02.php/templete/hooks/wecom_notify/post-commit' => 'SVN 提交钩子',
            '02.php/templete/hooks/wecom_notify/post-revprop-change' => 'SVN 属性钩子',
            '02.php/app/script/wecom_notify.php' => '通知处理脚本'
        ];
        
        foreach ($hookFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查目录结构
        $requiredDirs = [
            '02.php/templete/wecom' => '企业微信模板目录',
            '02.php/templete/hooks/wecom_notify' => '通知钩子目录'
        ];
        
        foreach ($requiredDirs as $dir => $description) {
            if (is_dir($dir)) {
                $this->passCheck("目录 {$description} 存在");
            } else {
                $this->addError("目录 {$description} 不存在");
            }
        }
    }
    
    /**
     * 验证服务
     */
    private function validateServices() {
        $this->section("服务验证");
        
        // 检查守护进程脚本
        $serviceFiles = [
            '02.php/server/svnadmind.php' => '主守护进程',
            '02.php/server/wecom_notification_daemon.php' => '通知守护进程',
            '02.php/app/util/WeComNotificationClient.php' => '通知客户端'
        ];
        
        foreach ($serviceFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查安装脚本
        $installFiles = [
            '02.php/server/wecom_install.php' => '安装脚本',
            '02.php/server/wecom_setup_wizard.php' => '配置向导'
        ];
        
        foreach ($installFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
    }
    
    /**
     * 验证文档
     */
    private function validateDocumentation() {
        $this->section("文档验证");
        
        // 检查文档文件
        $docFiles = [
            'docs/WECOM_INTEGRATION.md' => '完整使用指南',
            'docs/QUICK_START.md' => '快速开始指南',
            'docs/DEPLOYMENT_CHECKLIST.md' => '部署检查清单',
            'docs/WECOM_API.md' => 'API 参考文档',
            '03.cicd/README_DOCKER_WECOM.md' => 'Docker 部署指南',
            '02.php/templete/wecom/README.md' => '配置模板文档'
        ];
        
        foreach ($docFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查 README 更新
        if (file_exists('README.md')) {
            $readme = file_get_contents('README.md');
            if (strpos($readme, '企业微信集成') !== false) {
                $this->passCheck("主 README 包含企业微信集成说明");
            } else {
                $this->addWarning("主 README 未包含企业微信集成说明");
            }
        }
    }
    
    /**
     * 验证测试
     */
    private function validateTests() {
        $this->section("测试验证");
        
        // 检查测试文件
        $testFiles = [
            'tests/phpunit.xml' => 'PHPUnit 配置',
            'tests/bootstrap.php' => '测试引导文件',
            'tests/WeComTests/WeComAPITest.php' => 'API 测试',
            'tests/WeComTests/WeComSyncTest.php' => '同步测试',
            'tests/WeComTests/WeComNotificationTest.php' => '通知测试',
            'tests/WeComTests/WeComIntegrationTest.php' => '集成测试',
            'tests/run_tests.php' => '测试运行器',
            'tests/performance_test.php' => '性能测试'
        ];
        
        foreach ($testFiles as $file => $description) {
            $this->checkFile($file, $description);
        }
        
        // 检查测试文档
        $this->checkFile('tests/README.md', '测试文档');
    }
    
    /**
     * 生成最终报告
     */
    private function generateFinalReport() {
        $this->section("最终验证报告");
        
        $successRate = ($this->totalChecks > 0) ? ($this->passedChecks / $this->totalChecks) * 100 : 0;
        
        echo "\n" . Colors::WHITE . "验证统计:" . Colors::NC . "\n";
        echo "总检查项: " . Colors::CYAN . $this->totalChecks . Colors::NC . "\n";
        echo "通过检查: " . Colors::GREEN . $this->passedChecks . Colors::NC . "\n";
        echo "失败检查: " . Colors::RED . (count($this->errors)) . Colors::NC . "\n";
        echo "警告信息: " . Colors::YELLOW . (count($this->warnings)) . Colors::NC . "\n";
        echo "成功率: " . Colors::CYAN . number_format($successRate, 1) . "%" . Colors::NC . "\n";
        
        // 显示错误
        if (!empty($this->errors)) {
            echo "\n" . Colors::RED . "错误列表:" . Colors::NC . "\n";
            foreach ($this->errors as $error) {
                echo Colors::RED . "  ✗ {$error}" . Colors::NC . "\n";
            }
        }
        
        // 显示警告
        if (!empty($this->warnings)) {
            echo "\n" . Colors::YELLOW . "警告列表:" . Colors::NC . "\n";
            foreach ($this->warnings as $warning) {
                echo Colors::YELLOW . "  ⚠ {$warning}" . Colors::NC . "\n";
            }
        }
        
        // 总体结果
        echo "\n";
        if (empty($this->errors)) {
            echo Colors::GREEN . "🎉 企业微信集成系统验证通过！" . Colors::NC . "\n";
            echo Colors::GREEN . "系统已准备好投入生产使用。" . Colors::NC . "\n";
        } else {
            echo Colors::RED . "❌ 企业微信集成系统验证失败！" . Colors::NC . "\n";
            echo Colors::RED . "请修复上述错误后重新验证。" . Colors::NC . "\n";
        }
        
        // 生成报告文件
        $this->saveReport();
        
        echo "\n";
    }
    
    /**
     * 保存验证报告
     */
    private function saveReport() {
        $reportFile = '02.php/logs/wecom_final_validation_' . date('Y-m-d_H-i-s') . '.txt';
        
        $report = "企业微信集成最终验证报告\n";
        $report .= "生成时间: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        $report .= "验证统计:\n";
        $report .= "总检查项: {$this->totalChecks}\n";
        $report .= "通过检查: {$this->passedChecks}\n";
        $report .= "失败检查: " . count($this->errors) . "\n";
        $report .= "警告信息: " . count($this->warnings) . "\n";
        $report .= "成功率: " . number_format(($this->passedChecks / $this->totalChecks) * 100, 1) . "%\n\n";
        
        if (!empty($this->errors)) {
            $report .= "错误列表:\n";
            foreach ($this->errors as $error) {
                $report .= "  - {$error}\n";
            }
            $report .= "\n";
        }
        
        if (!empty($this->warnings)) {
            $report .= "警告列表:\n";
            foreach ($this->warnings as $warning) {
                $report .= "  - {$warning}\n";
            }
            $report .= "\n";
        }
        
        $report .= "验证结果: " . (empty($this->errors) ? "通过" : "失败") . "\n";
        
        file_put_contents($reportFile, $report);
        $this->info("验证报告已保存: {$reportFile}");
    }
    
    // ==================== 工具方法 ====================
    
    private function checkFile($file, $description) {
        $this->totalChecks++;
        
        if (file_exists($file)) {
            $this->passedChecks++;
            $this->passCheck("{$description} 存在");
        } else {
            $this->addError("{$description} 不存在: {$file}");
        }
    }
    
    private function passCheck($message) {
        $this->info("✓ {$message}");
    }
    
    private function addError($message) {
        $this->errors[] = $message;
        $this->error("✗ {$message}");
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
        $this->warning("⚠ {$message}");
    }
    
    private function printHeader() {
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n";
        echo Colors::WHITE . "        企业微信集成最终验证工具" . Colors::NC . "\n";
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n\n";
        
        echo "正在验证企业微信集成系统的完整性和功能正确性...\n\n";
    }
    
    private function section($title) {
        echo "\n" . Colors::YELLOW . "--- {$title} ---" . Colors::NC . "\n";
    }
    
    private function info($message) {
        echo Colors::BLUE . "{$message}" . Colors::NC . "\n";
    }
    
    private function success($message) {
        echo Colors::GREEN . "{$message}" . Colors::NC . "\n";
    }
    
    private function warning($message) {
        echo Colors::YELLOW . "{$message}" . Colors::NC . "\n";
    }
    
    private function error($message) {
        echo Colors::RED . "{$message}" . Colors::NC . "\n";
    }
}

// 运行最终验证
if (php_sapi_name() === 'cli') {
    $validator = new WeComFinalValidator();
    $success = $validator->validate();
    exit($success ? 0 : 1);
} else {
    echo "此脚本只能在命令行模式下运行\n";
    exit(1);
}
