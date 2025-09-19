<?php
/*
 * WeChat Integration Tests
 * 
 * End-to-end integration tests for the complete WeChat Enterprise integration system
 */

use PHPUnit\Framework\TestCase;

class WeComIntegrationTest extends TestCase
{
    private $wecomAPI;
    private $wecomSync;
    private $wecomNotification;
    private $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear test data
        clearTestData();
        
        // Initialize services
        $this->wecomAPI = new WeComAPI();
        $this->wecomSync = new WeComSync();
        $this->wecomNotification = new WeComNotification();
        $this->testDatabase = getTestDatabase();
        
        // Clear mock responses
        MockHttpResponse::clearResponses();
        
        // Setup basic configuration
        $this->setupTestConfiguration();
    }

    protected function tearDown(): void
    {
        clearTestData();
        MockHttpResponse::clearResponses();
        parent::tearDown();
    }

    /**
     * Setup test configuration
     */
    private function setupTestConfiguration()
    {
        // Setup WeChat config
        insertTestData('wecom_config', [
            'id' => 1,
            'corp_id' => 'test_corp_id',
            'corp_secret' => 'test_corp_secret',
            'agent_id' => 'test_agent_id',
            'access_token' => 'test_access_token',
            'token_expires_at' => time() + 3600,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Setup notification rules
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Integration Test Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=integration_test',
            'message_template' => '📝 代码提交\n仓库: {repository}\n作者: {author}\n版本: {revision}\n说明: {message}',
            'is_enabled' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Test complete department synchronization flow
     */
    public function testCompleteDepartmentSyncFlow()
    {
        // Mock departments API response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
                    [
                        'id' => 1,
                        'name' => '公司',
                        'name_en' => 'Company',
                        'parentid' => 0,
                        'order' => 1
                    ],
                    [
                        'id' => 2,
                        'name' => '技术部',
                        'name_en' => 'Technology',
                        'parentid' => 1,
                        'order' => 2
                    ],
                    [
                        'id' => 3,
                        'name' => '产品部',
                        'name_en' => 'Product',
                        'parentid' => 1,
                        'order' => 3
                    ]
                ]
            ]
        );

        // Execute department sync
        $result = $this->wecomSync->syncDepartments();
        
        // Verify sync result
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['success_count']);
        $this->assertEquals(0, $result['error_count']);
        
        // Verify departments were created in database
        $departments = getTestData('wecom_departments');
        $this->assertCount(3, $departments);
        
        // Verify department hierarchy
        $rootDept = array_filter($departments, function($dept) {
            return $dept['wecom_dept_id'] == '1';
        });
        $rootDept = array_values($rootDept)[0];
        $this->assertEquals('公司', $rootDept['dept_name']);
        $this->assertEquals('0', $rootDept['parent_id']);
        
        $techDept = array_filter($departments, function($dept) {
            return $dept['wecom_dept_id'] == '2';
        });
        $techDept = array_values($techDept)[0];
        $this->assertEquals('技术部', $techDept['dept_name']);
        $this->assertEquals('1', $techDept['parent_id']);
        $this->assertEquals('wecom_technology', $techDept['svn_group_name']);
        
        // Verify sync was logged
        $syncLogs = getTestData('wecom_sync_logs');
        $this->assertCount(1, $syncLogs);
        $this->assertEquals('departments', $syncLogs[0]['sync_type']);
        $this->assertEquals('completed', $syncLogs[0]['sync_status']);
    }

    /**
     * Test complete user synchronization and matching flow
     */
    public function testCompleteUserSyncAndMatchingFlow()
    {
        // Setup departments first
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '1',
            'dept_name' => '公司',
            'parent_id' => '0',
            'svn_group_name' => 'wecom_company',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'parent_id' => '1',
            'svn_group_name' => 'wecom_technology',
            'is_active' => 1
        ]);

        // Setup existing SVN users for matching
        insertTestData('svn_users', [
            'svn_user_id' => 1,
            'svn_user_name' => 'zhang.san',
            'svn_user_note' => '张三',
            'svn_user_email' => 'zhangsan@company.com',
            'svn_user_status' => 1
        ]);

        // Mock users API response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => [
                    [
                        'userid' => 'zhangsan',
                        'name' => '张三',
                        'department' => [1, 2],
                        'position' => '高级开发工程师',
                        'mobile' => '13800138000',
                        'email' => 'zhangsan@company.com',
                        'status' => 1,
                        'enable' => 1
                    ],
                    [
                        'userid' => 'lisi',
                        'name' => '李四',
                        'department' => [2],
                        'position' => '测试工程师',
                        'mobile' => '13800138001',
                        'email' => 'lisi@company.com',
                        'status' => 1,
                        'enable' => 1
                    ]
                ]
            ]
        );

        // Execute user sync
        $result = $this->wecomSync->syncUsers();
        
        // Verify sync result
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['success_count']);
        
        // Verify users were created/matched
        $users = getTestData('wecom_users');
        $this->assertCount(2, $users);
        
        // Verify user matching
        $zhangsan = array_filter($users, function($user) {
            return $user['wecom_userid'] == 'zhangsan';
        });
        $zhangsan = array_values($zhangsan)[0];
        $this->assertEquals('matched', $zhangsan['match_status']);
        $this->assertEquals('zhang.san', $zhangsan['svn_username']);
        $this->assertEquals(1, $zhangsan['svn_user_id']);
        
        $lisi = array_filter($users, function($user) {
            return $user['wecom_userid'] == 'lisi';
        });
        $lisi = array_values($lisi)[0];
        $this->assertEquals('unmatched', $lisi['match_status']);
        $this->assertNull($lisi['svn_username']);
    }

    /**
     * Test complete permission synchronization flow
     */
    public function testCompletePermissionSyncFlow()
    {
        // Setup test data
        insertTestData('wecom_departments', [
            'id' => 1,
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'parent_id' => '1',
            'svn_group_name' => 'wecom_technology',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_users', [
            'id' => 1,
            'wecom_userid' => 'zhangsan',
            'wecom_name' => '张三',
            'svn_username' => 'zhangsan',
            'svn_user_id' => 1,
            'match_status' => 'matched',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_users', [
            'id' => 2,
            'wecom_userid' => 'lisi',
            'wecom_name' => '李四',
            'svn_username' => 'lisi',
            'svn_user_id' => 2,
            'match_status' => 'matched',
            'is_active' => 1
        ]);

        // Execute permission sync
        $result = $this->wecomSync->syncPermissions();
        
        // Verify sync result
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['success_count']);
        
        // Verify permissions were logged
        $syncLogs = getTestData('wecom_sync_logs', ['sync_type' => 'permissions']);
        $this->assertCount(1, $syncLogs);
        $this->assertEquals('completed', $syncLogs[0]['sync_status']);
    }

    /**
     * Test complete SVN notification flow
     */
    public function testCompleteSVNNotificationFlow()
    {
        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=integration_test',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Prepare SVN commit event data
        $eventData = [
            'repository' => 'test_repo',
            'revision' => '1001',
            'author' => 'zhangsan',
            'message' => 'Integration test commit',
            'changed_paths' => [
                '/trunk/src/integration.php',
                '/trunk/tests/IntegrationTest.php'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Execute notification
        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        // Verify notification result
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['sent_count']);
        $this->assertEmpty($result['errors']);
        
        // Verify notification was logged
        $notificationLogs = getTestData('wecom_notification_logs');
        $this->assertCount(1, $notificationLogs);
        $this->assertEquals('commit', $notificationLogs[0]['event_type']);
        $this->assertEquals('success', $notificationLogs[0]['send_status']);
        $this->assertStringContains('integration_test', $notificationLogs[0]['webhook_url']);
    }

    /**
     * Test end-to-end sync and notification workflow
     */
    public function testEndToEndSyncAndNotificationWorkflow()
    {
        // Step 1: Mock department sync
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
                    [
                        'id' => 1,
                        'name' => '公司',
                        'parentid' => 0,
                        'order' => 1
                    ],
                    [
                        'id' => 2,
                        'name' => '开发团队',
                        'parentid' => 1,
                        'order' => 2
                    ]
                ]
            ]
        );

        // Step 2: Mock user sync
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => [
                    [
                        'userid' => 'developer1',
                        'name' => '开发者1',
                        'department' => [2],
                        'email' => 'dev1@company.com',
                        'status' => 1,
                        'enable' => 1
                    ]
                ]
            ]
        );

        // Step 3: Mock sync completion notification
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=sync_complete',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Execute complete sync workflow
        $deptResult = $this->wecomSync->syncDepartments();
        $this->assertTrue($deptResult['success']);
        
        $userResult = $this->wecomSync->syncUsers();
        $this->assertTrue($userResult['success']);
        
        $permResult = $this->wecomSync->syncPermissions();
        $this->assertTrue($permResult['success']);

        // Send sync completion notification
        $syncData = [
            'sync_type' => 'full',
            'departments_synced' => $deptResult['success_count'],
            'users_synced' => $userResult['success_count'],
            'permissions_synced' => $permResult['success_count'],
            'duration' => 45,
            'status' => 'completed'
        ];

        $notificationResult = $this->wecomNotification->sendSyncNotification(
            $syncData,
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=sync_complete'
        );
        
        $this->assertTrue($notificationResult);
        
        // Verify complete workflow was logged
        $syncLogs = getTestData('wecom_sync_logs');
        $this->assertGreaterThanOrEqual(3, count($syncLogs)); // departments, users, permissions
        
        // Verify all sync operations completed successfully
        foreach ($syncLogs as $log) {
            $this->assertEquals('completed', $log['sync_status']);
        }
    }

    /**
     * Test error handling in integration workflow
     */
    public function testErrorHandlingInIntegrationWorkflow()
    {
        // Mock API error for departments
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 40013,
                'errmsg' => 'invalid corpid'
            ]
        );

        // Execute sync with error
        $result = $this->wecomSync->syncDepartments();
        
        // Verify error handling
        $this->assertFalse($result['success']);
        $this->assertStringContains('invalid corpid', $result['error_message']);
        
        // Verify error was logged
        $syncLogs = getTestData('wecom_sync_logs');
        $this->assertCount(1, $syncLogs);
        $this->assertEquals('failed', $syncLogs[0]['sync_status']);
        $this->assertStringContains('invalid corpid', $syncLogs[0]['error_details']);
    }

    /**
     * Test notification failure and retry workflow
     */
    public function testNotificationFailureAndRetryWorkflow()
    {
        // Mock webhook failure
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=integration_test',
            [
                'errcode' => 93000,
                'errmsg' => 'invalid webhook url'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '1002',
            'author' => 'testuser',
            'message' => 'Test notification failure'
        ];

        // Execute notification (should fail)
        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        // Verify failure handling
        $this->assertEquals(0, $result['status']);
        $this->assertEquals(0, $result['sent_count']);
        $this->assertCount(1, $result['errors']);
        
        // Verify failure was logged
        $notificationLogs = getTestData('wecom_notification_logs');
        $this->assertCount(1, $notificationLogs);
        $this->assertEquals('failed', $notificationLogs[0]['send_status']);
        $this->assertStringContains('invalid webhook url', $notificationLogs[0]['error_message']);
    }

    /**
     * Test batch notification processing
     */
    public function testBatchNotificationProcessing()
    {
        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=integration_test',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Prepare batch events
        $events = [
            [
                'repository' => 'test_repo',
                'revision' => '1003',
                'author' => 'user1',
                'message' => 'First batch commit',
                'event_type' => 'commit'
            ],
            [
                'repository' => 'test_repo',
                'revision' => '1004',
                'author' => 'user2',
                'message' => 'Second batch commit',
                'event_type' => 'commit'
            ],
            [
                'repository' => 'test_repo',
                'revision' => '1005',
                'author' => 'user3',
                'message' => 'Third batch commit',
                'event_type' => 'commit'
            ]
        ];

        // Execute batch processing
        $result = $this->wecomNotification->processBatchNotifications($events);
        
        // Verify batch processing result
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);
        
        // Verify notifications were logged
        $notificationLogs = getTestData('wecom_notification_logs');
        $this->assertGreaterThanOrEqual(1, count($notificationLogs));
    }

    /**
     * Test data consistency across components
     */
    public function testDataConsistencyAcrossComponents()
    {
        // Setup initial data
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
                    [
                        'id' => 1,
                        'name' => '测试部门',
                        'parentid' => 0,
                        'order' => 1
                    ]
                ]
            ]
        );

        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => [
                    [
                        'userid' => 'testuser',
                        'name' => '测试用户',
                        'department' => [1],
                        'email' => 'test@company.com',
                        'status' => 1,
                        'enable' => 1
                    ]
                ]
            ]
        );

        // Execute sync operations
        $this->wecomSync->syncDepartments();
        $this->wecomSync->syncUsers();
        
        // Verify data consistency
        $departments = getTestData('wecom_departments');
        $users = getTestData('wecom_users');
        
        $this->assertCount(1, $departments);
        $this->assertCount(1, $users);
        
        // Verify department-user relationship
        $department = $departments[0];
        $user = $users[0];
        
        $this->assertEquals('1', $department['wecom_dept_id']);
        $this->assertEquals('测试部门', $department['dept_name']);
        $this->assertEquals('testuser', $user['wecom_userid']);
        $this->assertEquals('测试用户', $user['wecom_name']);
        
        // Verify timestamps are consistent
        $this->assertNotEmpty($department['created_at']);
        $this->assertNotEmpty($user['created_at']);
        $this->assertNotEmpty($department['updated_at']);
        $this->assertNotEmpty($user['updated_at']);
    }

    /**
     * Test system performance under load
     */
    public function testSystemPerformanceUnderLoad()
    {
        // Mock large dataset
        $departments = [];
        $users = [];
        
        // Generate 50 departments
        for ($i = 1; $i <= 50; $i++) {
            $departments[] = [
                'id' => $i,
                'name' => "部门{$i}",
                'parentid' => $i > 1 ? rand(1, $i-1) : 0,
                'order' => $i
            ];
        }
        
        // Generate 200 users
        for ($i = 1; $i <= 200; $i++) {
            $users[] = [
                'userid' => "user{$i}",
                'name' => "用户{$i}",
                'department' => [rand(1, 50)],
                'email' => "user{$i}@company.com",
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

        // Measure sync performance
        $startTime = microtime(true);
        
        $deptResult = $this->wecomSync->syncDepartments();
        $userResult = $this->wecomSync->syncUsers();
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Verify performance (should complete within reasonable time)
        $this->assertLessThan(30, $duration, 'Sync should complete within 30 seconds');
        
        // Verify all data was processed
        $this->assertTrue($deptResult['success']);
        $this->assertEquals(50, $deptResult['success_count']);
        
        $this->assertTrue($userResult['success']);
        $this->assertEquals(200, $userResult['success_count']);
        
        // Verify data was stored correctly
        $storedDepartments = getTestData('wecom_departments');
        $storedUsers = getTestData('wecom_users');
        
        $this->assertCount(50, $storedDepartments);
        $this->assertCount(200, $storedUsers);
    }
}
