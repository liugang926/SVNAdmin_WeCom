<?php
/*
 * WeComNotification Service Unit Tests
 * 
 * Tests for the WeChat Enterprise notification service
 */

use PHPUnit\Framework\TestCase;

class WeComNotificationTest extends TestCase
{
    private $wecomNotification;
    private $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear test data
        clearTestData();
        
        // Initialize WeComNotification service
        $this->wecomNotification = new WeComNotification();
        $this->testDatabase = getTestDatabase();
        
        // Clear mock responses
        MockHttpResponse::clearResponses();
    }

    protected function tearDown(): void
    {
        clearTestData();
        MockHttpResponse::clearResponses();
        parent::tearDown();
    }

    /**
     * Test SVN commit notification
     */
    public function testSendSvnCommitNotification()
    {
        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Test Commit Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=test_key',
            'is_enabled' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=test_key',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '123',
            'author' => 'zhangsan',
            'message' => 'Test commit message',
            'changed_paths' => [
                '/trunk/src/main.php',
                '/trunk/config/app.php'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['sent_count']);
        $this->assertEmpty($result['errors']);
        
        // Verify notification was logged
        $logs = getTestData('wecom_notification_logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('commit', $logs[0]['event_type']);
        $this->assertEquals('success', $logs[0]['send_status']);
    }

    /**
     * Test SVN delete notification
     */
    public function testSendSvnDeleteNotification()
    {
        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Test Delete Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'delete',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=test_key',
            'is_enabled' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=test_key',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '124',
            'author' => 'lisi',
            'message' => 'Delete old files',
            'deleted_paths' => [
                '/trunk/old/deprecated.php',
                '/trunk/temp/cache.tmp'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $result = $this->wecomNotification->sendSvnNotification('delete', $eventData);
        
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['sent_count']);
    }

    /**
     * Test notification rule filtering
     */
    public function testNotificationRuleFiltering()
    {
        // Setup multiple notification rules
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Repo A Rule',
            'repo_name' => 'repo_a',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=repo_a',
            'is_enabled' => 1
        ]);
        
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Repo B Rule',
            'repo_name' => 'repo_b',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=repo_b',
            'is_enabled' => 1
        ]);
        
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Disabled Rule',
            'repo_name' => 'repo_a',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=disabled',
            'is_enabled' => 0
        ]);

        // Mock webhook response for repo_a only
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=repo_a',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $eventData = [
            'repository' => 'repo_a',
            'revision' => '125',
            'author' => 'test_user',
            'message' => 'Test commit'
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        // Should only send to repo_a rule (enabled), not repo_b or disabled rule
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['sent_count']);
    }

    /**
     * Test path prefix filtering
     */
    public function testPathPrefixFiltering()
    {
        // Setup rule with path prefix
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Source Code Rule',
            'repo_name' => 'test_repo',
            'path_prefix' => '/trunk/src/',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=src_only',
            'is_enabled' => 1
        ]);

        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=src_only',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        // Test with matching path
        $eventData = [
            'repository' => 'test_repo',
            'revision' => '126',
            'author' => 'developer',
            'message' => 'Update source code',
            'changed_paths' => [
                '/trunk/src/main.php',
                '/trunk/src/utils.php'
            ]
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        $this->assertEquals(1, $result['sent_count']);

        // Test with non-matching path
        $eventData['changed_paths'] = [
            '/trunk/docs/readme.txt',
            '/trunk/config/settings.ini'
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * Test custom message template
     */
    public function testCustomMessageTemplate()
    {
        // Setup rule with custom template
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Custom Template Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=custom',
            'message_template' => '🔧 代码提交通知\n仓库：{repository}\n作者：{author}\n版本：{revision}\n说明：{message}',
            'is_enabled' => 1
        ]);

        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=custom',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '127',
            'author' => 'designer',
            'message' => 'Update UI components'
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['sent_count']);
    }

    /**
     * Test notification failure handling
     */
    public function testNotificationFailureHandling()
    {
        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Failing Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=fail',
            'is_enabled' => 1
        ]);

        // Mock webhook failure response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=fail',
            [
                'errcode' => 93000,
                'errmsg' => 'invalid webhook url'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '128',
            'author' => 'test_user',
            'message' => 'Test commit'
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        $this->assertEquals(0, $result['status']);
        $this->assertEquals(0, $result['sent_count']);
        $this->assertCount(1, $result['errors']);
        
        // Verify error was logged
        $logs = getTestData('wecom_notification_logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('failed', $logs[0]['send_status']);
        $this->assertStringContains('invalid webhook url', $logs[0]['error_message']);
    }

    /**
     * Test batch notification
     */
    public function testBatchNotification()
    {
        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Batch Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=batch',
            'is_enabled' => 1
        ]);

        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=batch',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $events = [
            [
                'repository' => 'test_repo',
                'revision' => '129',
                'author' => 'user1',
                'message' => 'First commit'
            ],
            [
                'repository' => 'test_repo',
                'revision' => '130',
                'author' => 'user2',
                'message' => 'Second commit'
            ]
        ];

        $result = $this->wecomNotification->processBatchNotifications($events);
        
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['processed_count']);
    }

    /**
     * Test sync completion notification
     */
    public function testSyncCompletionNotification()
    {
        // Mock webhook response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=sync',
            [
                'errcode' => 0,
                'errmsg' => 'ok'
            ]
        );

        $syncData = [
            'sync_type' => 'full',
            'departments_synced' => 5,
            'users_synced' => 20,
            'duration' => 30,
            'status' => 'completed'
        ];

        $result = $this->wecomNotification->sendSyncNotification(
            $syncData,
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=sync'
        );
        
        $this->assertTrue($result);
    }

    /**
     * Test notification rate limiting
     */
    public function testNotificationRateLimiting()
    {
        // Setup notification rule
        insertTestData('wecom_notification_rules', [
            'rule_name' => 'Rate Limited Rule',
            'repo_name' => 'test_repo',
            'event_type' => 'commit',
            'webhook_url' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=rate_limit',
            'is_enabled' => 1
        ]);

        // Mock rate limit response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=rate_limit',
            [
                'errcode' => 45009,
                'errmsg' => 'api freq out of limit'
            ]
        );

        $eventData = [
            'repository' => 'test_repo',
            'revision' => '131',
            'author' => 'test_user',
            'message' => 'Test commit'
        ];

        $result = $this->wecomNotification->sendSvnNotification('commit', $eventData);
        
        $this->assertEquals(0, $result['status']);
        $this->assertStringContains('rate limit', strtolower($result['message']));
    }

    /**
     * Test notification statistics
     */
    public function testNotificationStatistics()
    {
        // Insert test notification logs
        insertTestData('wecom_notification_logs', [
            'event_type' => 'commit',
            'send_status' => 'success',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        insertTestData('wecom_notification_logs', [
            'event_type' => 'commit',
            'send_status' => 'failed',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        insertTestData('wecom_notification_logs', [
            'event_type' => 'delete',
            'send_status' => 'success',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $stats = $this->wecomNotification->getNotificationStats();
        
        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['success']);
        $this->assertEquals(1, $stats['failed']);
    }

    /**
     * Test notification log cleanup
     */
    public function testNotificationLogCleanup()
    {
        // Insert old notification logs
        insertTestData('wecom_notification_logs', [
            'event_type' => 'commit',
            'send_status' => 'success',
            'created_at' => date('Y-m-d H:i:s', strtotime('-40 days'))
        ]);
        
        insertTestData('wecom_notification_logs', [
            'event_type' => 'commit',
            'send_status' => 'success',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);

        $result = $this->wecomNotification->cleanupNotificationLogs(30);
        
        $this->assertGreaterThan(0, $result['cleaned_count']);
        
        // Verify old logs were deleted
        $remainingLogs = getTestData('wecom_notification_logs');
        $this->assertCount(1, $remainingLogs);
    }

    /**
     * Test message formatting
     */
    public function testMessageFormatting()
    {
        $eventData = [
            'repository' => 'test_repo',
            'revision' => '132',
            'author' => 'formatter_test',
            'message' => 'Test message formatting',
            'changed_paths' => [
                '/trunk/src/formatter.php',
                '/trunk/tests/FormatterTest.php'
            ]
        ];

        $template = 'Repository: {repository}\nRevision: {revision}\nAuthor: {author}\nMessage: {message}\nFiles: {file_count}';
        
        $formattedMessage = $this->wecomNotification->formatMessage($template, $eventData);
        
        $this->assertStringContains('Repository: test_repo', $formattedMessage);
        $this->assertStringContains('Revision: 132', $formattedMessage);
        $this->assertStringContains('Author: formatter_test', $formattedMessage);
        $this->assertStringContains('Files: 2', $formattedMessage);
    }
}
