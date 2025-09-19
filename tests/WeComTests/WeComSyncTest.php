<?php
/*
 * WeComSync Service Unit Tests
 * 
 * Tests for the WeChat Enterprise data synchronization service
 */

use PHPUnit\Framework\TestCase;

class WeComSyncTest extends TestCase
{
    private $wecomSync;
    private $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear test data
        clearTestData();
        
        // Initialize WeComSync service
        $this->wecomSync = new WeComSync();
        $this->testDatabase = getTestDatabase();
        
        // Clear mock responses
        MockHttpResponse::clearResponses();
        
        // Setup mock access token
        insertTestData('wecom_config', [
            'id' => 1,
            'access_token' => 'test_token',
            'token_expires_at' => time() + 3600
        ]);
    }

    protected function tearDown(): void
    {
        clearTestData();
        MockHttpResponse::clearResponses();
        parent::tearDown();
    }

    /**
     * Test department synchronization
     */
    public function testSyncDepartments()
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

        $result = $this->wecomSync->syncDepartments();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['success_count']);
        $this->assertEquals(0, $result['error_count']);
        
        // Verify departments were saved to database
        $departments = getTestData('wecom_departments');
        $this->assertCount(3, $departments);
        
        // Check specific department data
        $techDept = array_filter($departments, function($dept) {
            return $dept['wecom_dept_id'] == '2';
        });
        $techDept = array_values($techDept)[0];
        
        $this->assertEquals('技术部', $techDept['dept_name']);
        $this->assertEquals('1', $techDept['parent_id']);
        $this->assertEquals('wecom_technology', $techDept['svn_group_name']);
    }

    /**
     * Test department update during sync
     */
    public function testSyncDepartmentsUpdate()
    {
        // Insert existing department
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '旧技术部',
            'parent_id' => '1',
            'svn_group_name' => 'old_tech',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Mock updated departments API response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
                    [
                        'id' => 2,
                        'name' => '新技术部',
                        'name_en' => 'New Technology',
                        'parentid' => 1,
                        'order' => 2
                    ]
                ]
            ]
        );

        $result = $this->wecomSync->syncDepartments();
        
        $this->assertTrue($result['success']);
        
        // Verify department was updated
        $departments = getTestData('wecom_departments', ['wecom_dept_id' => '2']);
        $this->assertCount(1, $departments);
        $this->assertEquals('新技术部', $departments[0]['dept_name']);
    }

    /**
     * Test user synchronization
     */
    public function testSyncUsers()
    {
        // Setup test departments
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '1',
            'dept_name' => '公司',
            'parent_id' => '0',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'parent_id' => '1',
            'is_active' => 1
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

        $result = $this->wecomSync->syncUsers();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['success_count']);
        $this->assertEquals(0, $result['error_count']);
        
        // Verify users were saved to database
        $users = getTestData('wecom_users');
        $this->assertCount(2, $users);
        
        // Check specific user data
        $zhangsan = array_filter($users, function($user) {
            return $user['wecom_userid'] == 'zhangsan';
        });
        $zhangsan = array_values($zhangsan)[0];
        
        $this->assertEquals('张三', $zhangsan['wecom_name']);
        $this->assertEquals('zhangsan@company.com', $zhangsan['wecom_email']);
        $this->assertEquals('13800138000', $zhangsan['wecom_mobile']);
    }

    /**
     * Test user matching by email
     */
    public function testUserMatchingByEmail()
    {
        // Create existing SVN user with matching email
        insertTestData('svn_users', [
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
                        'department' => [1],
                        'email' => 'zhangsan@company.com',
                        'status' => 1,
                        'enable' => 1
                    ]
                ]
            ]
        );

        $result = $this->wecomSync->syncUsers();
        
        $this->assertTrue($result['success']);
        
        // Verify user was matched
        $users = getTestData('wecom_users', ['wecom_userid' => 'zhangsan']);
        $this->assertCount(1, $users);
        $this->assertEquals('matched', $users[0]['match_status']);
        $this->assertEquals('zhang.san', $users[0]['svn_username']);
    }

    /**
     * Test permission synchronization
     */
    public function testSyncPermissions()
    {
        // Setup test data
        insertTestData('wecom_departments', [
            'id' => 1,
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'svn_group_name' => 'wecom_tech',
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

        $result = $this->wecomSync->syncPermissions();
        
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['success_count']);
    }

    /**
     * Test sync with API errors
     */
    public function testSyncWithAPIErrors()
    {
        // Mock API error response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 40013,
                'errmsg' => 'invalid corpid'
            ]
        );

        $result = $this->wecomSync->syncDepartments();
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('invalid corpid', $result['error_message']);
    }

    /**
     * Test incremental sync
     */
    public function testIncrementalSync()
    {
        // Insert existing department with old timestamp
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'parent_id' => '1',
            'updated_at' => date('Y-m-d H:i:s', time() - 3600) // 1 hour ago
        ]);

        // Mock departments API response (no changes)
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
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

        $result = $this->wecomSync->syncDepartments(false); // Incremental sync
        
        $this->assertTrue($result['success']);
        // Should skip unchanged departments in incremental sync
        $this->assertEquals(0, $result['success_count']);
    }

    /**
     * Test full sync
     */
    public function testFullSync()
    {
        // Insert existing department
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '技术部',
            'parent_id' => '1',
            'updated_at' => date('Y-m-d H:i:s', time() - 3600)
        ]);

        // Mock departments API response
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/department/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'department' => [
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

        $result = $this->wecomSync->syncDepartments(true); // Full sync
        
        $this->assertTrue($result['success']);
        // Should process all departments in full sync
        $this->assertEquals(1, $result['success_count']);
    }

    /**
     * Test sync logging
     */
    public function testSyncLogging()
    {
        // Mock successful departments sync
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
                    ]
                ]
            ]
        );

        $this->wecomSync->syncDepartments();
        
        // Check if sync was logged
        $logs = getTestData('wecom_sync_logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('departments', $logs[0]['sync_type']);
        $this->assertEquals('completed', $logs[0]['sync_status']);
    }

    /**
     * Test department deactivation
     */
    public function testDepartmentDeactivation()
    {
        // Insert existing departments
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '1',
            'dept_name' => '公司',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_departments', [
            'wecom_dept_id' => '2',
            'dept_name' => '已删除部门',
            'is_active' => 1
        ]);

        // Mock API response with only one department (department 2 was deleted)
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
                    ]
                ]
            ]
        );

        $result = $this->wecomSync->syncDepartments();
        
        $this->assertTrue($result['success']);
        
        // Verify deleted department was deactivated
        $deletedDept = getTestData('wecom_departments', ['wecom_dept_id' => '2']);
        $this->assertCount(1, $deletedDept);
        $this->assertEquals(0, $deletedDept[0]['is_active']);
    }

    /**
     * Test user deactivation
     */
    public function testUserDeactivation()
    {
        // Insert existing users
        insertTestData('wecom_users', [
            'wecom_userid' => 'zhangsan',
            'wecom_name' => '张三',
            'is_active' => 1
        ]);
        
        insertTestData('wecom_users', [
            'wecom_userid' => 'deleted_user',
            'wecom_name' => '已删除用户',
            'is_active' => 1
        ]);

        // Mock API response with only one user
        MockHttpResponse::setResponse(
            'https://qyapi.weixin.qq.com/cgi-bin/user/list',
            [
                'errcode' => 0,
                'errmsg' => 'ok',
                'userlist' => [
                    [
                        'userid' => 'zhangsan',
                        'name' => '张三',
                        'department' => [1],
                        'status' => 1,
                        'enable' => 1
                    ]
                ]
            ]
        );

        $result = $this->wecomSync->syncUsers();
        
        $this->assertTrue($result['success']);
        
        // Verify deleted user was deactivated
        $deletedUser = getTestData('wecom_users', ['wecom_userid' => 'deleted_user']);
        $this->assertCount(1, $deletedUser);
        $this->assertEquals(0, $deletedUser[0]['is_active']);
    }
}
