<?php
/*
 * Performance Test for WeChat Integration
 * 
 * This script tests the performance of the WeChat integration system
 * under various load conditions
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');

// Set working directory
chdir(__DIR__);

// Include bootstrap
require_once 'bootstrap.php';

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "  WeChat Integration Performance Test" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo PHP_EOL;

class PerformanceTest
{
    private $wecomAPI;
    private $wecomSync;
    private $wecomNotification;
    private $testDatabase;
    private $results = [];

    public function __construct()
    {
        $this->wecomAPI = new WeComAPI();
        $this->wecomSync = new WeComSync();
        $this->wecomNotification = new WeComNotification();
        $this->testDatabase = getTestDatabase();
    }

    /**
     * Run all performance tests
     */
    public function runAllTests()
    {
        echo "Starting performance tests..." . PHP_EOL;
        echo PHP_EOL;

        $this->testDepartmentSyncPerformance();
        $this->testUserSyncPerformance();
        $this->testNotificationPerformance();
        $this->testBatchNotificationPerformance();
        $this->testDatabasePerformance();
        $this->testMemoryUsage();

        $this->printResults();
    }

    /**
     * Test department synchronization performance
     */
    private function testDepartmentSyncPerformance()
    {
        echo "Testing department sync performance..." . PHP_EOL;
        
        clearTestData();
        MockHttpResponse::clearResponses();

        // Generate test departments (100 departments)
        $departments = [];
        for ($i = 1; $i <= 100; $i++) {
            $departments[] = [
                'id' => $i,
                'name' => "部门{$i}",
                'name_en' => "Department{$i}",
                'parentid' => $i > 1 ? rand(1, min($i-1, 10)) : 0,
                'order' => $i
            ];
        }

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => $departments
            ]
        );

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $this->wecomSync->syncDepartments();

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->results['department_sync'] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'items_processed' => $result['success_count'],
            'items_per_second' => $result['success_count'] / $duration,
            'success' => $result['success']
        ];

        echo "  Duration: " . number_format($duration, 3) . " seconds" . PHP_EOL;
        echo "  Memory used: " . $this->formatBytes($memoryUsed) . PHP_EOL;
        echo "  Items processed: " . $result['success_count'] . PHP_EOL;
        echo "  Items per second: " . number_format($result['success_count'] / $duration, 2) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test user synchronization performance
     */
    private function testUserSyncPerformance()
    {
        echo "Testing user sync performance..." . PHP_EOL;
        
        clearTestData();
        MockHttpResponse::clearResponses();

        // Setup departments first
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '1',
            'dept_name' => '公司',
            'parent_id' => '0',
            'is_active' => 1
        ]);

        // Generate test users (500 users)
        $users = [];
        for ($i = 1; $i <= 500; $i++) {
            $users[] = [
                'userid' => "user{$i}",
                'name' => "用户{$i}",
                'department' => [1],
                'position' => "职位{$i}",
                'mobile' => sprintf("138%08d", $i),
                'email' => "user{$i}@company.com",
                'status' => 1,
                'enable' => 1
            ];
        }

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => $users
            ]
        );

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $this->wecomSync->syncUsers();

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->results['user_sync'] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'items_processed' => $result['success_count'],
            'items_per_second' => $result['success_count'] / $duration,
            'success' => $result['success']
        ];

        echo "  Duration: " . number_format($duration, 3) . " seconds" . PHP_EOL;
        echo "  Memory used: " . $this->formatBytes($memoryUsed) . PHP_EOL;
        echo "  Items processed: " . $result['success_count'] . PHP_EOL;
        echo "  Items per second: " . number_format($result['success_count'] / $duration, 2) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test notification performance
     */
    private function testNotificationPerformance()
    {
        echo "Testing notification performance..." . PHP_EOL;
        
        clearTestData();
        MockHttpResponse::clearResponses();

        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Performance Test Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=perf_test',
            'is_enabled' => 1
        ]);

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=perf_test',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Test single notification performance
        $eventData = [
            'repository' => 'test_repo',
            'revision' => '1001',
            'author' => 'perftest',
            'message' => 'Performance test commit',
            'changed_paths' => ['/trunk/test.php']
        ];

        $iterations = 50;
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $successCount = 0;
        for ($i = 0; $i < $iterations; $i++) {
            $eventData['revision'] = '1001' + $i;
            $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
            if ($result['status'] == 1) {
                $successCount++;
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->results['notification'] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'items_processed' => $successCount,
            'items_per_second' => $successCount / $duration,
            'success' => $successCount == $iterations
        ];

        echo "  Duration: " . number_format($duration, 3) . " seconds" . PHP_EOL;
        echo "  Memory used: " . $this->formatBytes($memoryUsed) . PHP_EOL;
        echo "  Notifications sent: {$successCount}/{$iterations}" . PHP_EOL;
        echo "  Notifications per second: " . number_format($successCount / $duration, 2) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test batch notification performance
     */
    private function testBatchNotificationPerformance()
    {
        echo "Testing batch notification performance..." . PHP_EOL;
        
        clearTestData();
        MockHttpResponse::clearResponses();

        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Batch Performance Test Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=batch_perf',
            'is_enabled' => 1
        ]);

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=batch_perf',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Generate batch events
        $events = [];
        for ($i = 1; $i <= 100; $i++) {
            $events[] = [
                'repository' => 'test_repo',
                'revision' => '2000' + $i,
                'author' => "user{$i}",
                'message' => "Batch test commit {$i}",
                'event_type' => 'commit'
            ];
        }

        // Measure batch performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $this->wecomNotification->processBatchNotifications($events);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->results['batch_notification'] = [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'items_processed' => $result['processed_count'],
            'items_per_second' => $result['processed_count'] / $duration,
            'success' => $result['success']
        ];

        echo "  Duration: " . number_format($duration, 3) . " seconds" . PHP_EOL;
        echo "  Memory used: " . $this->formatBytes($memoryUsed) . PHP_EOL;
        echo "  Events processed: " . $result['processed_count'] . PHP_EOL;
        echo "  Events per second: " . number_format($result['processed_count'] / $duration, 2) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test database performance
     */
    private function testDatabasePerformance()
    {
        echo "Testing database performance..." . PHP_EOL;
        
        clearTestData();

        // Test insert performance
        $insertCount = 1000;
        $startTime = microtime(true);

        for ($i = 1; $i <= $insertCount; $i++) {
            insertTestData('wecom_users', [
                'wecom_userid' => "dbtest{$i}",
                'wecom_name' => "数据库测试用户{$i}",
                'wecom_email' => "dbtest{$i}@company.com",
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $insertTime = microtime(true) - $startTime;

        // Test select performance
        $startTime = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $users = getTestData('wecom_users', ['is_active' => 1]);
        }
        
        $selectTime = microtime(true) - $startTime;

        // Test update performance
        $startTime = microtime(true);
        
        for ($i = 1; $i <= 100; $i++) {
            $this->testDatabase->update('wecom_users', [
                'wecom_name' => "更新用户{$i}",
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'wecom_userid' => "dbtest{$i}"
            ]);
        }
        
        $updateTime = microtime(true) - $startTime;

        $this->results['database'] = [
            'insert_time' => $insertTime,
            'select_time' => $selectTime,
            'update_time' => $updateTime,
            'insert_per_second' => $insertCount / $insertTime,
            'select_per_second' => 100 / $selectTime,
            'update_per_second' => 100 / $updateTime
        ];

        echo "  Insert time ({$insertCount} records): " . number_format($insertTime, 3) . " seconds" . PHP_EOL;
        echo "  Select time (100 queries): " . number_format($selectTime, 3) . " seconds" . PHP_EOL;
        echo "  Update time (100 updates): " . number_format($updateTime, 3) . " seconds" . PHP_EOL;
        echo "  Insert rate: " . number_format($insertCount / $insertTime, 2) . " records/second" . PHP_EOL;
        echo "  Select rate: " . number_format(100 / $selectTime, 2) . " queries/second" . PHP_EOL;
        echo "  Update rate: " . number_format(100 / $updateTime, 2) . " updates/second" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test memory usage
     */
    private function testMemoryUsage()
    {
        echo "Testing memory usage..." . PHP_EOL;
        
        $initialMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        // Simulate heavy load
        clearTestData();
        MockHttpResponse::clearResponses();

        // Large dataset simulation
        $departments = [];
        for ($i = 1; $i <= 200; $i++) {
            $departments[] = [
                'id' => $i,
                'name' => "内存测试部门{$i}",
                'parentid' => $i > 1 ? rand(1, min($i-1, 20)) : 0,
                'order' => $i
            ];
        }

        $users = [];
        for ($i = 1; $i <= 1000; $i++) {
            $users[] = [
                'userid' => "memtest{$i}",
                'name' => "内存测试用户{$i}",
                'department' => [rand(1, 200)],
                'email' => "memtest{$i}@company.com",
                'status' => 1,
                'enable' => 1
            ];
        }

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => $departments
            ]
        );

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => $users
            ]
        );

        $beforeSyncMemory = memory_get_usage();

        // Execute sync
        $this->wecomSync->syncDepartments();
        $this->wecomSync->syncUsers();

        $afterSyncMemory = memory_get_usage();
        $finalPeakMemory = memory_get_peak_usage();

        $this->results['memory'] = [
            'initial_memory' => $initialMemory,
            'before_sync_memory' => $beforeSyncMemory,
            'after_sync_memory' => $afterSyncMemory,
            'peak_memory' => $finalPeakMemory,
            'sync_memory_used' => $afterSyncMemory - $beforeSyncMemory,
            'total_memory_used' => $afterSyncMemory - $initialMemory
        ];

        echo "  Initial memory: " . $this->formatBytes($initialMemory) . PHP_EOL;
        echo "  Before sync: " . $this->formatBytes($beforeSyncMemory) . PHP_EOL;
        echo "  After sync: " . $this->formatBytes($afterSyncMemory) . PHP_EOL;
        echo "  Peak memory: " . $this->formatBytes($finalPeakMemory) . PHP_EOL;
        echo "  Memory used by sync: " . $this->formatBytes($afterSyncMemory - $beforeSyncMemory) . PHP_EOL;
        echo "  Total memory used: " . $this->formatBytes($afterSyncMemory - $initialMemory) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Print performance test results
     */
    private function printResults()
    {
        echo "========================================" . PHP_EOL;
        echo "  Performance Test Results Summary" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;

        // Performance benchmarks
        $benchmarks = [
            'department_sync' => ['min_items_per_second' => 50, 'max_duration' => 5],
            'user_sync' => ['min_items_per_second' => 100, 'max_duration' => 10],
            'notification' => ['min_items_per_second' => 20, 'max_duration' => 5],
            'batch_notification' => ['min_items_per_second' => 50, 'max_duration' => 3]
        ];

        $allPassed = true;

        foreach ($this->results as $testName => $result) {
            echo "Test: " . ucfirst(str_replace('_', ' ', $testName)) . PHP_EOL;
            
            if (isset($result['duration'])) {
                echo "  Duration: " . number_format($result['duration'], 3) . "s" . PHP_EOL;
                echo "  Items/second: " . number_format($result['items_per_second'], 2) . PHP_EOL;
                
                if (isset($benchmarks[$testName])) {
                    $benchmark = $benchmarks[$testName];
                    $durationPassed = $result['duration'] <= $benchmark['max_duration'];
                    $ratePassed = $result['items_per_second'] >= $benchmark['min_items_per_second'];
                    
                    echo "  Duration benchmark: " . ($durationPassed ? "✓ PASS" : "✗ FAIL") . 
                         " (≤{$benchmark['max_duration']}s)" . PHP_EOL;
                    echo "  Rate benchmark: " . ($ratePassed ? "✓ PASS" : "✗ FAIL") . 
                         " (≥{$benchmark['min_items_per_second']}/s)" . PHP_EOL;
                    
                    if (!$durationPassed || !$ratePassed) {
                        $allPassed = false;
                    }
                }
            }
            
            if (isset($result['memory_used'])) {
                echo "  Memory used: " . $this->formatBytes($result['memory_used']) . PHP_EOL;
            }
            
            echo PHP_EOL;
        }

        // Overall result
        if ($allPassed) {
            echo "🎉 All performance benchmarks passed!" . PHP_EOL;
        } else {
            echo "⚠️  Some performance benchmarks failed. Consider optimization." . PHP_EOL;
        }

        echo PHP_EOL;
        echo "Note: Performance results may vary based on system specifications." . PHP_EOL;
        echo "These benchmarks are designed for typical server environments." . PHP_EOL;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Run performance tests
$performanceTest = new PerformanceTest();
$performanceTest->runAllTests();
