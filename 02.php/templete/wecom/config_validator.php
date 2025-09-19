<?php

/**
 * 企业微信集成配置验证工具
 * 
 * 用于验证配置文件的完整性和正确性
 * 
 * 使用方法：
 * php 02.php/templete/wecom/config_validator.php [config_file]
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

class WeComConfigValidator {
    private $config = [];
    private $configPath = '';
    private $errors = [];
    private $warnings = [];
    private $info = [];
    
    public function __construct($configPath = null) {
        $this->configPath = $configPath ?: '02.php/config/wecom.php';
    }
    
    /**
     * 运行配置验证
     */
    public function validate() {
        $this->printHeader();
        
        // 加载配置
        if (!$this->loadConfig()) {
            return false;
        }
        
        // 执行验证
        $this->validateBasicConfig();
        $this->validateApiConfig();
        $this->validateSyncConfig();
        $this->validateDepartmentMapping();
        $this->validateUserMatching();
        $this->validatePermissionMapping();
        $this->validateNotificationConfig();
        $this->validateCacheConfig();
        $this->validateLoggingConfig();
        $this->validateSecurityConfig();
        $this->validateAdvancedConfig();
        
        // 测试连接
        $this->testApiConnection();
        
        // 打印结果
        $this->printResults();
        
        return empty($this->errors);
    }
    
    /**
     * 加载配置文件
     */
    private function loadConfig() {
        if (!file_exists($this->configPath)) {
            $this->addError("配置文件不存在: {$this->configPath}");
            return false;
        }
        
        try {
            $this->config = require $this->configPath;
            $this->addInfo("配置文件加载成功: {$this->configPath}");
            return true;
        } catch (Exception $e) {
            $this->addError("配置文件加载失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 验证基础配置
     */
    private function validateBasicConfig() {
        $this->section("基础配置验证");
        
        // 必填字段
        $requiredFields = [
            'corp_id' => '企业 ID',
            'corp_secret' => '应用密钥',
            'agent_id' => '应用 ID'
        ];
        
        foreach ($requiredFields as $field => $name) {
            if (empty($this->config[$field]) || 
                $this->config[$field] === "your_{$field}_here") {
                $this->addError("{$name} 未配置或使用默认值");
            } else {
                $this->addInfo("{$name} 配置正确");
            }
        }
        
        // 版本信息
        if (isset($this->config['version'])) {
            $this->addInfo("配置版本: " . $this->config['version']);
        }
        
        // 配置时间
        if (isset($this->config['created_at']) && !empty($this->config['created_at'])) {
            $this->addInfo("配置创建时间: " . $this->config['created_at']);
        }
        
        if (isset($this->config['updated_at']) && !empty($this->config['updated_at'])) {
            $this->addInfo("配置更新时间: " . $this->config['updated_at']);
        }
    }
    
    /**
     * 验证 API 配置
     */
    private function validateApiConfig() {
        $this->section("API 配置验证");
        
        // API 基础地址
        $apiUrl = $this->config['api_base_url'] ?? 'https://qyapi.weixin.qq.com';
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $this->addError("API 基础地址格式错误: {$apiUrl}");
        } else {
            $this->addInfo("API 基础地址: {$apiUrl}");
        }
        
        // 超时配置
        $timeout = $this->config['api_timeout'] ?? 30;
        if ($timeout < 5 || $timeout > 120) {
            $this->addWarning("API 超时时间建议设置在 5-120 秒之间，当前: {$timeout}");
        } else {
            $this->addInfo("API 超时时间: {$timeout} 秒");
        }
        
        // 重试配置
        $retryCount = $this->config['api_retry_count'] ?? 3;
        if ($retryCount < 0 || $retryCount > 10) {
            $this->addWarning("API 重试次数建议设置在 0-10 之间，当前: {$retryCount}");
        } else {
            $this->addInfo("API 重试次数: {$retryCount}");
        }
    }
    
    /**
     * 验证同步配置
     */
    private function validateSyncConfig() {
        if (!($this->config['sync_enabled'] ?? true)) {
            $this->addInfo("数据同步功能已禁用");
            return;
        }
        
        $this->section("同步配置验证");
        
        $syncConfig = $this->config['sync'] ?? [];
        
        // 同步间隔
        $intervals = [
            'department_interval' => '部门同步间隔',
            'user_interval' => '用户同步间隔',
            'permission_interval' => '权限同步间隔'
        ];
        
        foreach ($intervals as $key => $name) {
            $interval = $syncConfig[$key] ?? 3600;
            if ($interval < 300) {
                $this->addWarning("{$name} 过短可能影响性能，当前: {$interval} 秒");
            } elseif ($interval > 86400) {
                $this->addWarning("{$name} 过长可能影响数据及时性，当前: {$interval} 秒");
            } else {
                $this->addInfo("{$name}: {$interval} 秒");
            }
        }
        
        // 批量处理大小
        $batchSize = $syncConfig['batch_size'] ?? 100;
        if ($batchSize < 10 || $batchSize > 1000) {
            $this->addWarning("批量处理大小建议设置在 10-1000 之间，当前: {$batchSize}");
        } else {
            $this->addInfo("批量处理大小: {$batchSize}");
        }
    }
    
    /**
     * 验证部门映射配置
     */
    private function validateDepartmentMapping() {
        if (!($this->config['sync_enabled'] ?? true)) {
            return;
        }
        
        $this->section("部门映射配置验证");
        
        $mapping = $this->config['department_mapping'] ?? [];
        
        // 用户组前缀
        $prefix = $mapping['group_name_prefix'] ?? 'wecom_';
        if (empty($prefix)) {
            $this->addWarning("用户组前缀为空，可能导致命名冲突");
        } else {
            $this->addInfo("用户组前缀: {$prefix}");
        }
        
        // 命名格式
        $format = $mapping['group_name_format'] ?? 'lowercase';
        $validFormats = ['lowercase', 'uppercase', 'original'];
        if (!in_array($format, $validFormats)) {
            $this->addError("无效的命名格式: {$format}");
        } else {
            $this->addInfo("命名格式: {$format}");
        }
        
        // 层级深度
        $maxDepth = $mapping['max_depth'] ?? 10;
        if ($maxDepth < 1 || $maxDepth > 20) {
            $this->addWarning("最大层级深度建议设置在 1-20 之间，当前: {$maxDepth}");
        } else {
            $this->addInfo("最大层级深度: {$maxDepth}");
        }
    }
    
    /**
     * 验证用户匹配配置
     */
    private function validateUserMatching() {
        if (!($this->config['sync_enabled'] ?? true)) {
            return;
        }
        
        $this->section("用户匹配配置验证");
        
        $matching = $this->config['user_matching'] ?? [];
        
        // 匹配策略
        $strategies = $matching['strategies'] ?? ['userid', 'email'];
        $validStrategies = ['userid', 'email', 'mobile', 'name'];
        
        foreach ($strategies as $strategy) {
            if (!in_array($strategy, $validStrategies)) {
                $this->addError("无效的匹配策略: {$strategy}");
            }
        }
        
        if (empty($strategies)) {
            $this->addError("至少需要配置一种匹配策略");
        } else {
            $this->addInfo("匹配策略: " . implode(', ', $strategies));
        }
        
        // 模糊匹配阈值
        if ($matching['fuzzy_match'] ?? false) {
            $threshold = $matching['fuzzy_threshold'] ?? 0.8;
            if ($threshold < 0.1 || $threshold > 1.0) {
                $this->addError("模糊匹配阈值必须在 0.1-1.0 之间，当前: {$threshold}");
            } else {
                $this->addInfo("模糊匹配阈值: {$threshold}");
            }
        }
        
        // 密码策略
        if ($matching['auto_create_svn_users'] ?? false) {
            $minLength = $matching['password_policy']['min_length'] ?? 6;
            if ($minLength < 4) {
                $this->addWarning("密码最小长度过短，建议至少 6 位");
            } else {
                $this->addInfo("密码最小长度: {$minLength}");
            }
        }
    }
    
    /**
     * 验证权限映射配置
     */
    private function validatePermissionMapping() {
        if (!($this->config['sync_enabled'] ?? true)) {
            return;
        }
        
        $this->section("权限映射配置验证");
        
        $permission = $this->config['permission_mapping'] ?? [];
        
        // 默认权限
        $defaultPerm = $permission['default_permission'] ?? 'r';
        $validPerms = ['', 'r', 'rw'];
        if (!in_array($defaultPerm, $validPerms)) {
            $this->addError("无效的默认权限: {$defaultPerm}");
        } else {
            $this->addInfo("默认权限: " . ($defaultPerm ?: '无权限'));
        }
        
        // 管理员配置
        $adminDepts = $permission['admin_departments'] ?? [];
        if (!empty($adminDepts)) {
            $this->addInfo("管理员部门: " . implode(', ', $adminDepts));
        }
        
        $adminUsers = $permission['admin_users'] ?? [];
        if (!empty($adminUsers)) {
            $this->addInfo("管理员用户: " . implode(', ', $adminUsers));
        }
        
        // 权限合并策略
        $mergeStrategy = $permission['merge_permissions'] ?? 'union';
        $validStrategies = ['union', 'intersection'];
        if (!in_array($mergeStrategy, $validStrategies)) {
            $this->addError("无效的权限合并策略: {$mergeStrategy}");
        } else {
            $this->addInfo("权限合并策略: {$mergeStrategy}");
        }
    }
    
    /**
     * 验证通知配置
     */
    private function validateNotificationConfig() {
        if (!($this->config['notification_enabled'] ?? true)) {
            $this->addInfo("消息通知功能已禁用");
            return;
        }
        
        $this->section("通知配置验证");
        
        $notification = $this->config['notification'] ?? [];
        
        // 默认 Webhook
        $webhook = $notification['default_webhook'] ?? '';
        if (!empty($webhook)) {
            if (!filter_var($webhook, FILTER_VALIDATE_URL)) {
                $this->addError("默认 Webhook 地址格式错误: {$webhook}");
            } else {
                $this->addInfo("默认 Webhook 已配置");
            }
        } else {
            $this->addWarning("未配置默认 Webhook 地址");
        }
        
        // 队列配置
        if ($notification['queue_enabled'] ?? false) {
            $queueConfig = $notification['queue'] ?? [];
            
            $maxSize = $queueConfig['max_size'] ?? 1000;
            if ($maxSize < 100 || $maxSize > 10000) {
                $this->addWarning("队列最大大小建议设置在 100-10000 之间，当前: {$maxSize}");
            } else {
                $this->addInfo("队列最大大小: {$maxSize}");
            }
            
            $batchSize = $queueConfig['batch_size'] ?? 50;
            if ($batchSize < 1 || $batchSize > 500) {
                $this->addWarning("批量处理大小建议设置在 1-500 之间，当前: {$batchSize}");
            } else {
                $this->addInfo("批量处理大小: {$batchSize}");
            }
        }
        
        // 频率限制
        if ($notification['rate_limit']['enabled'] ?? false) {
            $maxPerMinute = $notification['rate_limit']['max_per_minute'] ?? 60;
            $maxPerHour = $notification['rate_limit']['max_per_hour'] ?? 1000;
            
            $this->addInfo("频率限制: {$maxPerMinute}/分钟, {$maxPerHour}/小时");
        }
    }
    
    /**
     * 验证缓存配置
     */
    private function validateCacheConfig() {
        $cache = $this->config['cache'] ?? [];
        
        if (!($cache['enabled'] ?? false)) {
            $this->addInfo("缓存功能已禁用");
            return;
        }
        
        $this->section("缓存配置验证");
        
        // 缓存驱动
        $driver = $cache['driver'] ?? 'file';
        $validDrivers = ['file', 'redis', 'memcached'];
        if (!in_array($driver, $validDrivers)) {
            $this->addError("无效的缓存驱动: {$driver}");
        } else {
            $this->addInfo("缓存驱动: {$driver}");
        }
        
        // Redis 配置验证
        if ($driver === 'redis') {
            $redisConfig = $cache['redis'] ?? [];
            $host = $redisConfig['host'] ?? '127.0.0.1';
            $port = $redisConfig['port'] ?? 6379;
            
            $this->addInfo("Redis 配置: {$host}:{$port}");
            
            // 测试 Redis 连接
            if (extension_loaded('redis')) {
                try {
                    $redis = new Redis();
                    $connected = $redis->connect($host, $port, 5);
                    if ($connected) {
                        $this->addInfo("Redis 连接测试成功");
                        $redis->close();
                    } else {
                        $this->addWarning("Redis 连接测试失败");
                    }
                } catch (Exception $e) {
                    $this->addWarning("Redis 连接测试异常: " . $e->getMessage());
                }
            } else {
                $this->addWarning("Redis 扩展未安装");
            }
        }
        
        // TTL 配置
        $ttl = $cache['ttl'] ?? 3600;
        if ($ttl < 60 || $ttl > 86400) {
            $this->addWarning("缓存 TTL 建议设置在 60-86400 秒之间，当前: {$ttl}");
        } else {
            $this->addInfo("缓存 TTL: {$ttl} 秒");
        }
    }
    
    /**
     * 验证日志配置
     */
    private function validateLoggingConfig() {
        $logging = $this->config['logging'] ?? [];
        
        if (!($logging['enabled'] ?? true)) {
            $this->addWarning("日志功能已禁用，建议启用以便故障排查");
            return;
        }
        
        $this->section("日志配置验证");
        
        // 日志级别
        $level = $logging['level'] ?? 'INFO';
        $validLevels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        if (!in_array($level, $validLevels)) {
            $this->addError("无效的日志级别: {$level}");
        } else {
            $this->addInfo("日志级别: {$level}");
        }
        
        // 日志文件
        $files = $logging['files'] ?? [];
        foreach ($files as $type => $file) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                $this->addWarning("日志目录不存在: {$dir}");
            } elseif (!is_writable($dir)) {
                $this->addError("日志目录不可写: {$dir}");
            } else {
                $this->addInfo("{$type} 日志文件: {$file}");
            }
        }
        
        // 日志轮转配置
        $maxFiles = $logging['max_files'] ?? 30;
        if ($maxFiles < 1 || $maxFiles > 365) {
            $this->addWarning("最大日志文件数建议设置在 1-365 之间，当前: {$maxFiles}");
        } else {
            $this->addInfo("最大日志文件数: {$maxFiles}");
        }
    }
    
    /**
     * 验证安全配置
     */
    private function validateSecurityConfig() {
        $this->section("安全配置验证");
        
        $security = $this->config['security'] ?? [];
        
        // SSL 验证
        $verifySSL = $security['verify_ssl'] ?? true;
        if (!$verifySSL) {
            $this->addWarning("SSL 证书验证已禁用，生产环境建议启用");
        } else {
            $this->addInfo("SSL 证书验证已启用");
        }
        
        // IP 白名单
        $whitelist = $security['ip_whitelist'] ?? [];
        if (empty($whitelist)) {
            $this->addInfo("未配置 IP 白名单（允许所有 IP 访问）");
        } else {
            $this->addInfo("IP 白名单: " . implode(', ', $whitelist));
        }
        
        // 数据加密
        $encrypt = $security['encrypt_sensitive_data'] ?? false;
        if ($encrypt) {
            $key = $security['encryption_key'] ?? '';
            if (empty($key)) {
                $this->addError("启用数据加密但未配置加密密钥");
            } elseif (strlen($key) < 16) {
                $this->addError("加密密钥长度不足，建议至少 16 位");
            } else {
                $this->addInfo("数据加密已启用");
            }
        }
    }
    
    /**
     * 验证高级配置
     */
    private function validateAdvancedConfig() {
        $this->section("高级配置验证");
        
        $advanced = $this->config['advanced'] ?? [];
        
        // 连接池大小
        $poolSize = $advanced['connection_pool_size'] ?? 10;
        if ($poolSize < 1 || $poolSize > 100) {
            $this->addWarning("连接池大小建议设置在 1-100 之间，当前: {$poolSize}");
        } else {
            $this->addInfo("连接池大小: {$poolSize}");
        }
        
        // 并发请求数
        $maxConcurrent = $advanced['max_concurrent_requests'] ?? 5;
        if ($maxConcurrent < 1 || $maxConcurrent > 50) {
            $this->addWarning("最大并发请求数建议设置在 1-50 之间，当前: {$maxConcurrent}");
        } else {
            $this->addInfo("最大并发请求数: {$maxConcurrent}");
        }
        
        // 实验性功能
        $experimental = $advanced['experimental_features'] ?? [];
        $enabledFeatures = array_filter($experimental);
        if (!empty($enabledFeatures)) {
            $this->addWarning("启用了实验性功能: " . implode(', ', array_keys($enabledFeatures)));
        }
    }
    
    /**
     * 测试 API 连接
     */
    private function testApiConnection() {
        $this->section("API 连接测试");
        
        // 检查必要的配置
        if (empty($this->config['corp_id']) || 
            empty($this->config['corp_secret']) || 
            empty($this->config['agent_id'])) {
            $this->addWarning("缺少必要配置，跳过 API 连接测试");
            return;
        }
        
        try {
            // 模拟 API 调用测试
            $apiUrl = $this->config['api_base_url'] ?? 'https://qyapi.weixin.qq.com';
            $timeout = $this->config['api_timeout'] ?? 30;
            
            // 测试网络连通性
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config['security']['verify_ssl'] ?? true);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($result === false || !empty($error)) {
                $this->addError("网络连接测试失败: {$error}");
            } elseif ($httpCode >= 200 && $httpCode < 400) {
                $this->addInfo("网络连接测试成功");
            } else {
                $this->addWarning("网络连接异常，HTTP 状态码: {$httpCode}");
            }
            
        } catch (Exception $e) {
            $this->addError("API 连接测试异常: " . $e->getMessage());
        }
    }
    
    /**
     * 打印验证结果
     */
    private function printResults() {
        echo "\n" . Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n";
        echo Colors::WHITE . "验证结果摘要" . Colors::NC . "\n";
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n\n";
        
        // 统计信息
        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);
        $infoCount = count($this->info);
        
        echo "配置文件: " . Colors::CYAN . $this->configPath . Colors::NC . "\n";
        echo "错误: " . Colors::RED . $errorCount . Colors::NC . " | ";
        echo "警告: " . Colors::YELLOW . $warningCount . Colors::NC . " | ";
        echo "信息: " . Colors::BLUE . $infoCount . Colors::NC . "\n\n";
        
        // 打印错误
        if (!empty($this->errors)) {
            echo Colors::RED . "错误:" . Colors::NC . "\n";
            foreach ($this->errors as $error) {
                echo Colors::RED . "  ✗ {$error}" . Colors::NC . "\n";
            }
            echo "\n";
        }
        
        // 打印警告
        if (!empty($this->warnings)) {
            echo Colors::YELLOW . "警告:" . Colors::NC . "\n";
            foreach ($this->warnings as $warning) {
                echo Colors::YELLOW . "  ⚠ {$warning}" . Colors::NC . "\n";
            }
            echo "\n";
        }
        
        // 总体结果
        if (empty($this->errors)) {
            echo Colors::GREEN . "✓ 配置验证通过！" . Colors::NC . "\n";
            if (!empty($this->warnings)) {
                echo Colors::YELLOW . "  但存在一些警告，建议检查优化" . Colors::NC . "\n";
            }
        } else {
            echo Colors::RED . "✗ 配置验证失败，请修复错误后重试" . Colors::NC . "\n";
        }
        
        echo "\n";
    }
    
    // ==================== 工具方法 ====================
    
    private function printHeader() {
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n";
        echo Colors::WHITE . "        企业微信集成配置验证工具" . Colors::NC . "\n";
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n\n";
    }
    
    private function section($title) {
        echo Colors::YELLOW . "--- {$title} ---" . Colors::NC . "\n";
    }
    
    private function addError($message) {
        $this->errors[] = $message;
        echo Colors::RED . "  ✗ {$message}" . Colors::NC . "\n";
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
        echo Colors::YELLOW . "  ⚠ {$message}" . Colors::NC . "\n";
    }
    
    private function addInfo($message) {
        $this->info[] = $message;
        echo Colors::BLUE . "  ℹ {$message}" . Colors::NC . "\n";
    }
}

// 运行配置验证
if (php_sapi_name() === 'cli') {
    $configPath = $argv[1] ?? null;
    $validator = new WeComConfigValidator($configPath);
    $success = $validator->validate();
    exit($success ? 0 : 1);
} else {
    echo "此脚本只能在命令行模式下运行\n";
    exit(1);
}
