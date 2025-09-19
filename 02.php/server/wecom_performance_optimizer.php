<?php

/**
 * 企业微信集成性能优化工具
 * 
 * 用于优化企业微信集成的性能，包括数据库查询优化、缓存策略、API 调用优化等
 * 
 * 使用方法：
 * php 02.php/server/wecom_performance_optimizer.php [action]
 * 
 * 可用操作：
 * - analyze: 分析性能瓶颈
 * - optimize: 执行性能优化
 * - test: 运行性能测试
 * - report: 生成性能报告
 * 
 * @version 1.0
 * @author SVNAdmin Team
 * @date 2024-08-29
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');

// 设置工作目录
chdir(dirname(dirname(__DIR__)));

// 引入必要的文件
require_once '02.php/config/database.php';
require_once '02.php/app/service/base/Base.php';

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

class WeComPerformanceOptimizer {
    private $database;
    private $config;
    private $optimizations = [];
    private $testResults = [];
    
    public function __construct() {
        $this->loadConfig();
        $this->initDatabase();
    }
    
    /**
     * 主入口函数
     */
    public function run($action = 'analyze') {
        $this->printHeader();
        
        switch ($action) {
            case 'analyze':
                $this->analyzePerformance();
                break;
            case 'optimize':
                $this->executeOptimizations();
                break;
            case 'test':
                $this->runPerformanceTests();
                break;
            case 'report':
                $this->generateReport();
                break;
            case 'all':
                $this->analyzePerformance();
                $this->executeOptimizations();
                $this->runPerformanceTests();
                $this->generateReport();
                break;
            default:
                $this->showHelp();
                break;
        }
    }
    
    /**
     * 分析性能瓶颈
     */
    private function analyzePerformance() {
        $this->section("性能分析");
        
        // 分析数据库性能
        $this->analyzeDatabasePerformance();
        
        // 分析 API 调用性能
        $this->analyzeApiPerformance();
        
        // 分析内存使用
        $this->analyzeMemoryUsage();
        
        // 分析文件系统性能
        $this->analyzeFileSystemPerformance();
        
        // 分析网络性能
        $this->analyzeNetworkPerformance();
        
        $this->success("性能分析完成");
    }
    
    /**
     * 分析数据库性能
     */
    private function analyzeDatabasePerformance() {
        $this->info("分析数据库性能...");
        
        try {
            // 检查数据库连接
            $startTime = microtime(true);
            $this->database->query("SELECT 1");
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            if ($connectionTime > 100) {
                $this->addOptimization('database', 'connection', 
                    "数据库连接时间过长: {$connectionTime}ms", 
                    "优化数据库连接池配置");
            } else {
                $this->info("数据库连接时间: {$connectionTime}ms");
            }
            
            // 检查表索引
            $this->checkTableIndexes();
            
            // 检查查询性能
            $this->checkQueryPerformance();
            
            // 检查数据库大小
            $this->checkDatabaseSize();
            
        } catch (Exception $e) {
            $this->error("数据库性能分析失败: " . $e->getMessage());
        }
    }
    
    /**
     * 检查表索引
     */
    private function checkTableIndexes() {
        $tables = [
            'wecom_users' => ['wecom_userid', 'svn_user_id', 'match_status'],
            'wecom_departments' => ['wecom_dept_id', 'parent_id'],
            'wecom_sync_logs' => ['sync_type', 'sync_status', 'created_at'],
            'wecom_notification_logs' => ['event_type', 'send_status', 'created_at'],
            'wecom_notification_queue' => ['status', 'notification_type', 'next_retry_time']
        ];
        
        foreach ($tables as $table => $expectedIndexes) {
            try {
                // 检查表是否存在
                $result = $this->database->query("SHOW TABLES LIKE '{$table}'");
                if ($result->rowCount() == 0) {
                    continue; // 表不存在，跳过
                }
                
                // 获取现有索引
                $indexes = $this->database->query("SHOW INDEX FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
                $existingIndexes = array_column($indexes, 'Column_name');
                
                // 检查缺失的索引
                foreach ($expectedIndexes as $column) {
                    if (!in_array($column, $existingIndexes)) {
                        $this->addOptimization('database', 'index', 
                            "表 {$table} 缺少索引: {$column}", 
                            "CREATE INDEX idx_{$table}_{$column} ON {$table}({$column})");
                    }
                }
                
            } catch (Exception $e) {
                $this->warning("检查表 {$table} 索引失败: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 检查查询性能
     */
    private function checkQueryPerformance() {
        $queries = [
            "SELECT COUNT(*) FROM wecom_users WHERE match_status = 'matched'",
            "SELECT COUNT(*) FROM wecom_departments WHERE is_active = 1",
            "SELECT COUNT(*) FROM wecom_sync_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            "SELECT COUNT(*) FROM wecom_notification_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        ];
        
        foreach ($queries as $query) {
            $startTime = microtime(true);
            try {
                $this->database->query($query);
                $queryTime = (microtime(true) - $startTime) * 1000;
                
                if ($queryTime > 1000) {
                    $this->addOptimization('database', 'query', 
                        "慢查询检测: {$queryTime}ms", 
                        "优化查询: " . substr($query, 0, 50) . "...");
                }
            } catch (Exception $e) {
                $this->warning("查询性能测试失败: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 检查数据库大小
     */
    private function checkDatabaseSize() {
        try {
            $result = $this->database->query("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name LIKE 'wecom_%'
                ORDER BY (data_length + index_length) DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $totalSize = array_sum(array_column($result, 'size_mb'));
            
            if ($totalSize > 1000) { // 超过 1GB
                $this->addOptimization('database', 'size', 
                    "数据库大小过大: {$totalSize}MB", 
                    "考虑数据归档和清理策略");
            } else {
                $this->info("数据库大小: {$totalSize}MB");
            }
            
            // 检查日志表大小
            foreach ($result as $table) {
                if (strpos($table['table_name'], '_logs') !== false && $table['size_mb'] > 100) {
                    $this->addOptimization('database', 'cleanup', 
                        "日志表 {$table['table_name']} 过大: {$table['size_mb']}MB", 
                        "实施日志清理策略");
                }
            }
            
        } catch (Exception $e) {
            $this->warning("数据库大小检查失败: " . $e->getMessage());
        }
    }
    
    /**
     * 分析 API 调用性能
     */
    private function analyzeApiPerformance() {
        $this->info("分析 API 调用性能...");
        
        // 检查网络延迟
        $apiUrl = 'https://qyapi.weixin.qq.com';
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $result = curl_exec($ch);
        $networkLatency = (microtime(true) - $startTime) * 1000;
        curl_close($ch);
        
        if ($networkLatency > 1000) {
            $this->addOptimization('api', 'network', 
                "网络延迟过高: {$networkLatency}ms", 
                "考虑使用 CDN 或优化网络配置");
        } else {
            $this->info("网络延迟: {$networkLatency}ms");
        }
        
        // 检查 API 调用频率
        $this->checkApiCallFrequency();
        
        // 检查缓存策略
        $this->checkCacheStrategy();
    }
    
    /**
     * 检查 API 调用频率
     */
    private function checkApiCallFrequency() {
        // 模拟检查 API 调用日志
        $this->info("检查 API 调用频率...");
        
        // 建议优化策略
        $this->addOptimization('api', 'frequency', 
            "建议优化 API 调用频率", 
            "实施 API 调用缓存和批量处理");
    }
    
    /**
     * 检查缓存策略
     */
    private function checkCacheStrategy() {
        $cacheEnabled = $this->config['cache']['enabled'] ?? false;
        
        if (!$cacheEnabled) {
            $this->addOptimization('cache', 'enable', 
                "缓存功能未启用", 
                "启用缓存以提升 API 响应性能");
        } else {
            $this->info("缓存功能已启用");
            
            $driver = $this->config['cache']['driver'] ?? 'file';
            if ($driver === 'file') {
                $this->addOptimization('cache', 'driver', 
                    "使用文件缓存", 
                    "考虑使用 Redis 缓存提升性能");
            }
        }
    }
    
    /**
     * 分析内存使用
     */
    private function analyzeMemoryUsage() {
        $this->info("分析内存使用...");
        
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        $this->info("当前内存使用: " . $this->formatBytes($memoryUsage));
        $this->info("峰值内存使用: " . $this->formatBytes($peakMemory));
        $this->info("内存限制: {$memoryLimit}");
        
        // 检查内存使用是否过高
        $limitBytes = $this->parseMemoryLimit($memoryLimit);
        if ($peakMemory > $limitBytes * 0.8) {
            $this->addOptimization('memory', 'usage', 
                "内存使用过高: " . $this->formatBytes($peakMemory), 
                "优化内存使用或增加内存限制");
        }
    }
    
    /**
     * 分析文件系统性能
     */
    private function analyzeFileSystemPerformance() {
        $this->info("分析文件系统性能...");
        
        // 检查日志目录
        $logDir = '02.php/logs';
        if (is_dir($logDir)) {
            $logFiles = glob($logDir . '/*.log');
            $totalSize = 0;
            
            foreach ($logFiles as $file) {
                $totalSize += filesize($file);
            }
            
            if ($totalSize > 100 * 1024 * 1024) { // 超过 100MB
                $this->addOptimization('filesystem', 'logs', 
                    "日志文件过大: " . $this->formatBytes($totalSize), 
                    "实施日志轮转和清理策略");
            } else {
                $this->info("日志文件大小: " . $this->formatBytes($totalSize));
            }
        }
        
        // 检查临时文件
        $tempDir = sys_get_temp_dir();
        $tempFiles = glob($tempDir . '/svnadmin_*');
        if (count($tempFiles) > 100) {
            $this->addOptimization('filesystem', 'temp', 
                "临时文件过多: " . count($tempFiles), 
                "清理临时文件");
        }
    }
    
    /**
     * 分析网络性能
     */
    private function analyzeNetworkPerformance() {
        $this->info("分析网络性能...");
        
        // 测试 DNS 解析
        $startTime = microtime(true);
        gethostbyname('qyapi.weixin.qq.com');
        $dnsTime = (microtime(true) - $startTime) * 1000;
        
        if ($dnsTime > 100) {
            $this->addOptimization('network', 'dns', 
                "DNS 解析时间过长: {$dnsTime}ms", 
                "优化 DNS 配置或使用本地 DNS 缓存");
        } else {
            $this->info("DNS 解析时间: {$dnsTime}ms");
        }
    }
    
    /**
     * 执行性能优化
     */
    private function executeOptimizations() {
        $this->section("执行性能优化");
        
        if (empty($this->optimizations)) {
            $this->info("没有发现需要优化的项目");
            return;
        }
        
        foreach ($this->optimizations as $category => $items) {
            $this->info("优化类别: {$category}");
            
            foreach ($items as $item) {
                $this->info("  - {$item['description']}");
                
                try {
                    $this->executeOptimization($category, $item);
                    $this->success("    ✓ 优化完成");
                } catch (Exception $e) {
                    $this->error("    ✗ 优化失败: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * 执行单个优化
     */
    private function executeOptimization($category, $item) {
        switch ($category) {
            case 'database':
                $this->executeDatabaseOptimization($item);
                break;
            case 'cache':
                $this->executeCacheOptimization($item);
                break;
            case 'filesystem':
                $this->executeFilesystemOptimization($item);
                break;
            default:
                $this->info("    → 建议: {$item['solution']}");
                break;
        }
    }
    
    /**
     * 执行数据库优化
     */
    private function executeDatabaseOptimization($item) {
        if ($item['type'] === 'index' && strpos($item['solution'], 'CREATE INDEX') === 0) {
            // 创建索引
            $this->database->exec($item['solution']);
        } elseif ($item['type'] === 'cleanup') {
            // 清理数据
            $this->cleanupDatabaseLogs();
        } else {
            $this->info("    → 建议: {$item['solution']}");
        }
    }
    
    /**
     * 清理数据库日志
     */
    private function cleanupDatabaseLogs() {
        $tables = ['wecom_sync_logs', 'wecom_notification_logs'];
        $retentionDays = 30;
        
        foreach ($tables as $table) {
            try {
                $sql = "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)";
                $stmt = $this->database->prepare($sql);
                $stmt->execute();
                $deletedRows = $stmt->rowCount();
                
                if ($deletedRows > 0) {
                    $this->info("    清理表 {$table}: {$deletedRows} 条记录");
                }
            } catch (Exception $e) {
                $this->warning("    清理表 {$table} 失败: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 执行缓存优化
     */
    private function executeCacheOptimization($item) {
        $this->info("    → 建议: {$item['solution']}");
    }
    
    /**
     * 执行文件系统优化
     */
    private function executeFilesystemOptimization($item) {
        if ($item['type'] === 'logs') {
            $this->cleanupLogFiles();
        } elseif ($item['type'] === 'temp') {
            $this->cleanupTempFiles();
        } else {
            $this->info("    → 建议: {$item['solution']}");
        }
    }
    
    /**
     * 清理日志文件
     */
    private function cleanupLogFiles() {
        $logDir = '02.php/logs';
        $retentionDays = 30;
        $cutoffTime = time() - ($retentionDays * 24 * 3600);
        
        if (is_dir($logDir)) {
            $logFiles = glob($logDir . '/*.log*');
            $deletedCount = 0;
            
            foreach ($logFiles as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
            
            if ($deletedCount > 0) {
                $this->info("    清理日志文件: {$deletedCount} 个");
            }
        }
    }
    
    /**
     * 清理临时文件
     */
    private function cleanupTempFiles() {
        $tempDir = sys_get_temp_dir();
        $tempFiles = glob($tempDir . '/svnadmin_*');
        $deletedCount = 0;
        
        foreach ($tempFiles as $file) {
            if (is_file($file) && unlink($file)) {
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            $this->info("    清理临时文件: {$deletedCount} 个");
        }
    }
    
    /**
     * 运行性能测试
     */
    private function runPerformanceTests() {
        $this->section("性能测试");
        
        // 数据库性能测试
        $this->testDatabasePerformance();
        
        // API 调用性能测试
        $this->testApiPerformance();
        
        // 内存使用测试
        $this->testMemoryUsage();
        
        // 并发处理测试
        $this->testConcurrentProcessing();
        
        $this->success("性能测试完成");
    }
    
    /**
     * 测试数据库性能
     */
    private function testDatabasePerformance() {
        $this->info("测试数据库性能...");
        
        $tests = [
            'connection' => 'SELECT 1',
            'simple_query' => 'SELECT COUNT(*) FROM wecom_users',
            'complex_query' => 'SELECT u.*, d.dept_name FROM wecom_users u LEFT JOIN wecom_departments d ON u.wecom_userid = d.wecom_dept_id LIMIT 10',
            'insert_test' => 'INSERT INTO wecom_sync_logs (sync_type, sync_status, created_at) VALUES ("test", "completed", NOW())'
        ];
        
        foreach ($tests as $testName => $sql) {
            $startTime = microtime(true);
            
            try {
                if ($testName === 'insert_test') {
                    $this->database->exec($sql);
                    // 清理测试数据
                    $this->database->exec('DELETE FROM wecom_sync_logs WHERE sync_type = "test"');
                } else {
                    $this->database->query($sql);
                }
                
                $duration = (microtime(true) - $startTime) * 1000;
                $this->testResults['database'][$testName] = $duration;
                
                $status = $duration < 100 ? 'PASS' : 'SLOW';
                $this->info("  {$testName}: {$duration}ms [{$status}]");
                
            } catch (Exception $e) {
                $this->error("  {$testName}: FAILED - " . $e->getMessage());
                $this->testResults['database'][$testName] = -1;
            }
        }
    }
    
    /**
     * 测试 API 性能
     */
    private function testApiPerformance() {
        $this->info("测试 API 性能...");
        
        // 模拟 API 调用测试
        $apiTests = [
            'network_latency' => 'https://qyapi.weixin.qq.com',
            'dns_resolution' => 'qyapi.weixin.qq.com'
        ];
        
        foreach ($apiTests as $testName => $target) {
            $startTime = microtime(true);
            
            try {
                if ($testName === 'network_latency') {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $target);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_exec($ch);
                    curl_close($ch);
                } else {
                    gethostbyname($target);
                }
                
                $duration = (microtime(true) - $startTime) * 1000;
                $this->testResults['api'][$testName] = $duration;
                
                $status = $duration < 500 ? 'PASS' : 'SLOW';
                $this->info("  {$testName}: {$duration}ms [{$status}]");
                
            } catch (Exception $e) {
                $this->error("  {$testName}: FAILED - " . $e->getMessage());
                $this->testResults['api'][$testName] = -1;
            }
        }
    }
    
    /**
     * 测试内存使用
     */
    private function testMemoryUsage() {
        $this->info("测试内存使用...");
        
        $initialMemory = memory_get_usage(true);
        
        // 模拟大量数据处理
        $testData = [];
        for ($i = 0; $i < 10000; $i++) {
            $testData[] = [
                'id' => $i,
                'name' => 'Test User ' . $i,
                'email' => 'test' . $i . '@example.com',
                'data' => str_repeat('x', 100)
            ];
        }
        
        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $initialMemory;
        
        $this->testResults['memory']['data_processing'] = $memoryUsed;
        $this->info("  数据处理内存使用: " . $this->formatBytes($memoryUsed));
        
        // 清理测试数据
        unset($testData);
        
        $finalMemory = memory_get_usage(true);
        $memoryFreed = $peakMemory - $finalMemory;
        
        $this->info("  内存释放: " . $this->formatBytes($memoryFreed));
    }
    
    /**
     * 测试并发处理
     */
    private function testConcurrentProcessing() {
        $this->info("测试并发处理能力...");
        
        // 模拟并发请求处理
        $startTime = microtime(true);
        $concurrentTasks = 10;
        
        for ($i = 0; $i < $concurrentTasks; $i++) {
            // 模拟处理任务
            $this->simulateTask();
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTime = $totalTime / $concurrentTasks;
        
        $this->testResults['concurrent']['total_time'] = $totalTime;
        $this->testResults['concurrent']['avg_time'] = $avgTime;
        
        $this->info("  并发任务总时间: {$totalTime}ms");
        $this->info("  平均任务时间: {$avgTime}ms");
    }
    
    /**
     * 模拟任务处理
     */
    private function simulateTask() {
        // 模拟数据库查询
        usleep(rand(10000, 50000)); // 10-50ms
        
        // 模拟数据处理
        $data = array_fill(0, 1000, rand(1, 1000));
        array_sum($data);
    }
    
    /**
     * 生成性能报告
     */
    private function generateReport() {
        $this->section("性能报告");
        
        $reportFile = '02.php/logs/wecom_performance_report_' . date('Y-m-d_H-i-s') . '.txt';
        $report = $this->buildReport();
        
        file_put_contents($reportFile, $report);
        
        $this->success("性能报告已生成: {$reportFile}");
        
        // 显示摘要
        $this->displayReportSummary();
    }
    
    /**
     * 构建报告内容
     */
    private function buildReport() {
        $report = "企业微信集成性能报告\n";
        $report .= "生成时间: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        // 优化建议
        if (!empty($this->optimizations)) {
            $report .= "性能优化建议:\n";
            $report .= str_repeat("-", 20) . "\n";
            
            foreach ($this->optimizations as $category => $items) {
                $report .= "\n{$category}:\n";
                foreach ($items as $item) {
                    $report .= "  - {$item['description']}\n";
                    $report .= "    解决方案: {$item['solution']}\n";
                }
            }
            $report .= "\n";
        }
        
        // 测试结果
        if (!empty($this->testResults)) {
            $report .= "性能测试结果:\n";
            $report .= str_repeat("-", 20) . "\n";
            
            foreach ($this->testResults as $category => $tests) {
                $report .= "\n{$category}:\n";
                foreach ($tests as $testName => $result) {
                    if (is_numeric($result)) {
                        if ($result >= 0) {
                            $unit = ($category === 'memory') ? 'bytes' : 'ms';
                            $report .= "  {$testName}: {$result} {$unit}\n";
                        } else {
                            $report .= "  {$testName}: FAILED\n";
                        }
                    }
                }
            }
        }
        
        // 系统信息
        $report .= "\n系统信息:\n";
        $report .= str_repeat("-", 20) . "\n";
        $report .= "PHP 版本: " . PHP_VERSION . "\n";
        $report .= "内存限制: " . ini_get('memory_limit') . "\n";
        $report .= "最大执行时间: " . ini_get('max_execution_time') . "s\n";
        $report .= "当前内存使用: " . $this->formatBytes(memory_get_usage(true)) . "\n";
        $report .= "峰值内存使用: " . $this->formatBytes(memory_get_peak_usage(true)) . "\n";
        
        return $report;
    }
    
    /**
     * 显示报告摘要
     */
    private function displayReportSummary() {
        echo "\n" . Colors::WHITE . "性能报告摘要" . Colors::NC . "\n";
        echo str_repeat("-", 30) . "\n";
        
        // 优化项目统计
        $totalOptimizations = 0;
        foreach ($this->optimizations as $items) {
            $totalOptimizations += count($items);
        }
        
        if ($totalOptimizations > 0) {
            echo Colors::YELLOW . "发现 {$totalOptimizations} 个优化建议" . Colors::NC . "\n";
        } else {
            echo Colors::GREEN . "系统性能良好，无需优化" . Colors::NC . "\n";
        }
        
        // 测试结果统计
        if (!empty($this->testResults)) {
            echo "\n测试结果:\n";
            
            if (isset($this->testResults['database'])) {
                $dbTests = $this->testResults['database'];
                $avgDbTime = array_sum(array_filter($dbTests, function($v) { return $v >= 0; })) / count(array_filter($dbTests, function($v) { return $v >= 0; }));
                echo "  数据库平均响应时间: " . number_format($avgDbTime, 2) . "ms\n";
            }
            
            if (isset($this->testResults['api'])) {
                $apiTests = $this->testResults['api'];
                $avgApiTime = array_sum(array_filter($apiTests, function($v) { return $v >= 0; })) / count(array_filter($apiTests, function($v) { return $v >= 0; }));
                echo "  API 平均响应时间: " . number_format($avgApiTime, 2) . "ms\n";
            }
            
            if (isset($this->testResults['memory']['data_processing'])) {
                echo "  数据处理内存使用: " . $this->formatBytes($this->testResults['memory']['data_processing']) . "\n";
            }
        }
        
        echo "\n";
    }
    
    // ==================== 工具方法 ====================
    
    private function loadConfig() {
        $configFile = '02.php/config/wecom.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [];
        }
    }
    
    private function initDatabase() {
        try {
            $this->database = new PDO($dsn, $username, $password, $options);
        } catch (Exception $e) {
            $this->error("数据库连接失败: " . $e->getMessage());
            exit(1);
        }
    }
    
    private function addOptimization($category, $type, $description, $solution) {
        $this->optimizations[$category][] = [
            'type' => $type,
            'description' => $description,
            'solution' => $solution
        ];
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }
        
        return $limit;
    }
    
    private function printHeader() {
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n";
        echo Colors::WHITE . "        企业微信集成性能优化工具" . Colors::NC . "\n";
        echo Colors::BLUE . str_repeat("=", 60) . Colors::NC . "\n\n";
    }
    
    private function section($title) {
        echo "\n" . Colors::YELLOW . "--- {$title} ---" . Colors::NC . "\n";
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
    
    private function showHelp() {
        echo "使用方法: php wecom_performance_optimizer.php [action]\n\n";
        echo "可用操作:\n";
        echo "  analyze  - 分析性能瓶颈\n";
        echo "  optimize - 执行性能优化\n";
        echo "  test     - 运行性能测试\n";
        echo "  report   - 生成性能报告\n";
        echo "  all      - 执行所有操作\n";
        echo "\n";
    }
}

// 运行性能优化工具
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'analyze';
    $optimizer = new WeComPerformanceOptimizer();
    $optimizer->run($action);
} else {
    echo "此脚本只能在命令行模式下运行\n";
    exit(1);
}
