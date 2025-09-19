<?php
/*
 * WeComAPI Service Unit Tests
 * 
 * Tests for the WeChat Enterprise API integration service
 */

use PHPUnit\Framework\TestCase;

class WeComAPITest extends TestCase
{
    private $wecomAPI;
    private $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear test data
        clearTestData();
        
        // Initialize WeComAPI service
        $this->wecomAPI = new WeComAPI();
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
     * Test access token retrieval
     */
    public function testGetAccessToken()
    {
        // Mock successful token response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'access_token' => 'test_access_token_123',
                'expires_in' => 7200
            ]
        );

        $token = $this->wecomAPI->getAccessToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals('test_access_token_123', $token);
    }

    /**
     * Test access token caching
     */
    public function testAccessTokenCaching()
    {
        // Insert cached token
        insertTestData('wecom_config', [
            'id' => 1,
            'corp_id' => 'test_corp_id',
            'access_token' => 'cached_token_456',
            'token_expires_at' => time() + 3600,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $token = $this->wecomAPI->getAccessToken();
        
        $this->assertEquals('cached_token_456', $token);
    }

    /**
     * Test expired token refresh
     */
    public function testExpiredTokenRefresh()
    {
        // Insert expired token
        insertTestData('wecom_config', [
            'id' => 1,
            'corp_id' => 'test_corp_id',
            'access_token' => 'expired_token',
            'token_expires_at' => time() - 3600, // Expired 1 hour ago
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Mock new token response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'access_token' => 'new_token_789',
                'expires_in' => 7200
            ]
        );

        $token = $this->wecomAPI->getAccessToken();
        
        $this->assertEquals('new_token_789', $token);
    }

    /**
     * Test API error handling
     */
    public function testAPIErrorHandling()
    {
        // Mock error response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 40013,
                'errmsg' => 'invalid corpid'
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('WeChat API Error: invalid corpid (40013)');
        
        $this->wecomAPI->getAccessToken();
    }

    /**
     * Test department list retrieval
     */
    public function testGetDepartments()
    {
        // Mock access token
        insertTestData('wecom_config', [
            'id' => 1,
            'access_token' => 'valid_token',
            'token_expires_at' => time() + 3600
        ]);

        // Mock departments response
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
                    ]
                ]
            ]
        );

        $departments = $this->wecomAPI->getDepartments();
        
        $this->assertIsArray($departments);
        $this->assertCount(2, $departments);
        $this->assertEquals('公司', $departments[0]['name']);
        $this->assertEquals('技术部', $departments[1]['name']);
    }

    /**
     * Test department users retrieval
     */
    public function testGetDepartmentUsers()
    {
        // Mock access token
        insertTestData('wecom_config', [
            'id' => 1,
            'access_token' => 'valid_token',
            'token_expires_at' => time() + 3600
        ]);

        // Mock users response
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
                        'position' => '开发工程师',
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

        $users = $this->wecomAPI->getDepartmentUsers(2);
        
        $this->assertIsArray($users);
        $this->assertCount(2, $users);
        $this->assertEquals('zhangsan', $users[0]['userid']);
        $this->assertEquals('张三', $users[0]['name']);
    }

    /**
     * Test user detail retrieval
     */
    public function testGetUserDetail()
    {
        // Mock access token
        insertTestData('wecom_config', [
            'id' => 1,
            'access_token' => 'valid_token',
            'token_expires_at' => time() + 3600
        ]);

        // Mock user detail response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/get',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userid' => 'zhangsan',
                'name' => '张三',
                'department' => [1, 2],
                'position' => '开发工程师',
                'mobile' => '13800138000',
                'email' => 'zhangsan@company.com',
                'status' => 1,
                'enable' => 1,
                'avatar' => 'http://example.com/avatar.jpg',
                'telephone' => '021-12345678',
                'address' => '上海市浦东新区'
            ]
        );

        $user = $this->wecomAPI->getUserDetail('zhangsan');
        
        $this->assertIsArray($user);
        $this->assertEquals('zhangsan', $user['userid']);
        $this->assertEquals('张三', $user['name']);
        $this->assertEquals('开发工程师', $user['position']);
        $this->assertEquals('13800138000', $user['mobile']);
    }

    /**
     * Test message sending
     */
    public function testSendApplicationMessage()
    {
        // Mock access token
        insertTestData('wecom_config', [
            'id' => 1,
            'access_token' => 'valid_token',
            'token_expires_at' => time() + 3600
        ]);

        // Mock send message response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/message/send',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'invaliduser' => '',
                'invalidparty' => '',
                'invalidtag' => ''
            ]
        );

        $result = $this->wecomAPI->sendApplicationMessage(
            'zhangsan',
            'text',
            ['content' => 'Test message']
        );
        
        $this->assertTrue($result);
    }

    /**
     * Test webhook message sending
     */
    public function testSendWebhookMessage()
    {
        $webhookUrl = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=test_key';
        
        // Mock webhook response
        MockHttpResponse::setResponse($webhookUrl, [
            'errcode' => 0,
            'errmsg' => 'ok'
        ]);

        $result = $this->wecomAPI->sendWebhookMessage(
            $webhookUrl,
            'text',
            ['content' => 'Test webhook message']
        );
        
        $this->assertTrue($result);
    }

    /**
     * Test rate limiting
     */
    public function testRateLimiting()
    {
        // Mock rate limit response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 45009,
                'errmsg' => 'api freq out of limit'
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('WeChat API Error: api freq out of limit (45009)');
        
        $this->wecomAPI->getAccessToken();
    }

    /**
     * Test network timeout handling
     */
    public function testNetworkTimeout()
    {
        // Mock timeout response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            null // Simulate network timeout
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Network request failed');
        
        $this->wecomAPI->getAccessToken();
    }

    /**
     * Test API logging
     */
    public function testAPILogging()
    {
        // Mock successful response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'access_token' => 'test_token',
                'expires_in' => 7200
            ]
        );

        $this->wecomAPI->getAccessToken();
        
        // Check if API call was logged
        $logs = getTestData('wecom_api_logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('GET', $logs[0]['api_method']);
        $this->assertStringContains('gettoken', $logs[0]['api_url']);
        $this->assertEquals(200, $logs[0]['response_code']);
    }

    /**
     * Test configuration validation
     */
    public function testConfigurationValidation()
    {
        // Test with invalid configuration
        Config::set('wecom', [
            'enabled' => true,
            'corp_id' => '',
            'corp_secret' => '',
            'agent_id' => ''
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('WeChat configuration is incomplete');
        
        new WeComAPI();
    }

    /**
     * Test retry mechanism
     */
    public function testRetryMechanism()
    {
        // Mock temporary failure followed by success
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 40001, // Invalid access token (temporary error)
                'errmsg' => 'invalid credential'
            ]
        );

        // After retry, mock success
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'access_token' => 'retry_success_token',
                'expires_in' => 7200
            ]
        );

        // The API should retry and eventually succeed
        $token = $this->wecomAPI->getAccessToken();
        $this->assertEquals('retry_success_token', $token);
    }
}
