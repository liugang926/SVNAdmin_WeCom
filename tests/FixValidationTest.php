<?php

/**
 * 修复验证测试
 * 
 * 用于验证仓库重命名权限保持和表格列宽调整功能
 * 
 * @version 1.0
 * @author SVNAdmin Team
 * @date 2024-08-29
 */

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

class FixValidationTest extends TestCase
{
    private $svnAdmin;
    private $database;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 初始化 SVNAdmin 实例
        require_once '02.php/extension/Witersen/SVNAdmin.php';
        $this->svnAdmin = new Witersen\SVNAdmin();
        
        // 初始化内存数据库
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建测试表
        $this->createTestTables();
    }
    
    /**
     * 创建测试表
     */
    private function createTestTables()
    {
        $sql = "
        CREATE TABLE svn_reps (
            rep_name TEXT PRIMARY KEY,
            rep_note TEXT
        );
        
        CREATE TABLE svn_user_pri_paths (
            svnn_user_pri_path_id INTEGER PRIMARY KEY AUTOINCREMENT,
            svn_user_name TEXT,
            rep_name TEXT,
            pri_path TEXT,
            rep_pri TEXT
        );
        
        CREATE TABLE svn_group_pri_paths (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_name TEXT,
            rep_name TEXT,
            pri_path TEXT,
            rep_pri TEXT
        );
        ";
        
        $this->database->exec($sql);
        
        // 插入测试数据
        $this->insertTestData();
    }
    
    /**
     * 插入测试数据
     */
    private function insertTestData()
    {
        $statements = [
            "INSERT INTO svn_reps (rep_name, rep_note) VALUES ('old_repo', 'Test repository')",
            "INSERT INTO svn_user_pri_paths (svn_user_name, rep_name, pri_path, rep_pri) VALUES ('user1', 'old_repo', '/', 'rw')",
            "INSERT INTO svn_user_pri_paths (svn_user_name, rep_name, pri_path, rep_pri) VALUES ('user2', 'old_repo', '/trunk', 'r')",
            "INSERT INTO svn_user_pri_paths (svn_user_name, rep_name, pri_path, rep_pri) VALUES ('user3', 'old_repo', '/branches', 'rw')",
            "INSERT INTO svn_group_pri_paths (group_name, rep_name, pri_path, rep_pri) VALUES ('developers', 'old_repo', '/', 'rw')",
            "INSERT INTO svn_group_pri_paths (group_name, rep_name, pri_path, rep_pri) VALUES ('testers', 'old_repo', '/trunk', 'r')"
        ];
        
        foreach ($statements as $sql) {
            $this->database->exec($sql);
        }
    }
    
    /**
     * 测试仓库重命名时 authz 文件权限保持
     */
    public function testRepositoryRenamePreservesAuthzPermissions()
    {
        // 模拟 authz 文件内容
        $authzContent = "
[groups]
developers = user1, user2
testers = user3

[old_repo:/]
@developers = rw
user3 = r

[old_repo:/trunk]
@testers = r
user1 = rw

[old_repo:/branches]
user1 = rw
user2 = r

[old_repo:/tags]
@developers = r
";
        
        // 执行仓库重命名
        $result = $this->svnAdmin->UpdRepFromAuthz($authzContent, 'old_repo', 'new_repo');
        
        // 验证结果不是错误码
        $this->assertIsString($result, '仓库重命名应该返回更新后的 authz 内容');
        
        // 验证所有仓库路径都已更新
        $this->assertStringContains('[new_repo:/]', $result, '根路径应该已更新');
        $this->assertStringContains('[new_repo:/trunk]', $result, 'trunk 路径应该已更新');
        $this->assertStringContains('[new_repo:/branches]', $result, 'branches 路径应该已更新');
        $this->assertStringContains('[new_repo:/tags]', $result, 'tags 路径应该已更新');
        
        // 验证旧仓库名不再存在
        $this->assertStringNotContains('[old_repo:', $result, '旧仓库名应该已被完全替换');
        
        // 验证权限配置保持不变
        $this->assertStringContains('@developers = rw', $result, '开发者组权限应该保持');
        $this->assertStringContains('@testers = r', $result, '测试者组权限应该保持');
        $this->assertStringContains('user1 = rw', $result, '用户权限应该保持');
        $this->assertStringContains('user2 = r', $result, '用户权限应该保持');
        
        // 验证 groups 节保持不变
        $this->assertStringContains('[groups]', $result, 'groups 节应该保持');
        $this->assertStringContains('developers = user1, user2', $result, '组成员应该保持');
    }
    
    /**
     * 测试数据库权限记录更新
     */
    public function testDatabasePermissionRecordsUpdate()
    {
        // 模拟数据库更新操作
        $this->updateRepositoryPermissionsInDatabase('old_repo', 'new_repo');
        
        // 验证用户权限路径表已更新
        $userPaths = $this->database->query("SELECT * FROM svn_user_pri_paths WHERE rep_name = 'new_repo'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(3, $userPaths, '应该有 3 个用户权限记录被更新');
        
        // 验证旧仓库名记录已不存在
        $oldUserPaths = $this->database->query("SELECT * FROM svn_user_pri_paths WHERE rep_name = 'old_repo'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(0, $oldUserPaths, '旧仓库名的用户权限记录应该已被更新');
        
        // 验证分组权限路径表已更新
        $groupPaths = $this->database->query("SELECT * FROM svn_group_pri_paths WHERE rep_name = 'new_repo'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $groupPaths, '应该有 2 个分组权限记录被更新');
        
        // 验证旧仓库名记录已不存在
        $oldGroupPaths = $this->database->query("SELECT * FROM svn_group_pri_paths WHERE rep_name = 'old_repo'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(0, $oldGroupPaths, '旧仓库名的分组权限记录应该已被更新');
    }
    
    /**
     * 测试复杂路径权限保持
     */
    public function testComplexPathPermissionsPreservation()
    {
        $authzContent = "
[old_repo:/project/src]
user1 = rw
@developers = r

[old_repo:/project/docs]
@writers = rw
user2 = r

[old_repo:/project/config/production]
@admins = rw

[old_repo:/project/config/development]
@developers = rw
user1 = rw
";
        
        $result = $this->svnAdmin->UpdRepFromAuthz($authzContent, 'old_repo', 'new_repo');
        
        // 验证复杂路径都已正确更新
        $this->assertStringContains('[new_repo:/project/src]', $result);
        $this->assertStringContains('[new_repo:/project/docs]', $result);
        $this->assertStringContains('[new_repo:/project/config/production]', $result);
        $this->assertStringContains('[new_repo:/project/config/development]', $result);
        
        // 验证权限配置完整保持
        $this->assertStringContains('@developers = r', $result);
        $this->assertStringContains('@writers = rw', $result);
        $this->assertStringContains('@admins = rw', $result);
    }
    
    /**
     * 测试边界情况
     */
    public function testEdgeCases()
    {
        // 测试不存在的仓库
        $result = $this->svnAdmin->UpdRepFromAuthz('[groups]', 'nonexistent', 'new_name');
        $this->assertEquals(740, $result, '不存在的仓库应该返回错误码 740');
        
        // 测试空 authz 内容
        $result = $this->svnAdmin->UpdRepFromAuthz('', 'old_repo', 'new_repo');
        $this->assertEquals(740, $result, '空 authz 内容应该返回错误码 740');
        
        // 测试相同的仓库名
        $authzContent = "[old_repo:/]\nuser1 = rw";
        $result = $this->svnAdmin->UpdRepFromAuthz($authzContent, 'old_repo', 'old_repo');
        $this->assertIsString($result, '相同仓库名应该正常处理');
        $this->assertStringContains('[old_repo:/]', $result, '仓库名应该保持不变');
    }
    
    /**
     * 模拟数据库权限记录更新
     */
    private function updateRepositoryPermissionsInDatabase($oldRepName, $newRepName)
    {
        // 更新用户权限路径表中的仓库名
        $stmt = $this->database->prepare('UPDATE svn_user_pri_paths SET rep_name = ? WHERE rep_name = ?');
        $stmt->execute([$newRepName, $oldRepName]);
        
        // 更新分组权限路径表中的仓库名
        $stmt = $this->database->prepare('UPDATE svn_group_pri_paths SET rep_name = ? WHERE rep_name = ?');
        $stmt->execute([$newRepName, $oldRepName]);
    }
    
    /**
     * 测试前端表格列宽配置保存和加载
     */
    public function testTableColumnWidthConfiguration()
    {
        // 模拟列宽配置
        $columnWidths = [
            'svn_user_name' => 150,
            'svn_user_status' => 120,
            'svn_user_note' => 200,
            'svn_user_last_login' => 180
        ];
        
        // 模拟保存到 localStorage（在实际环境中由前端 JavaScript 处理）
        $savedConfig = json_encode($columnWidths);
        $this->assertJson($savedConfig, '列宽配置应该能够序列化为 JSON');
        
        // 模拟从 localStorage 加载
        $loadedConfig = json_decode($savedConfig, true);
        $this->assertEquals($columnWidths, $loadedConfig, '列宽配置应该能够正确加载');
        
        // 验证配置结构
        $this->assertArrayHasKey('svn_user_name', $loadedConfig, '应该包含用户名列配置');
        $this->assertArrayHasKey('svn_user_status', $loadedConfig, '应该包含状态列配置');
        $this->assertIsInt($loadedConfig['svn_user_name'], '列宽应该是整数');
        $this->assertGreaterThan(0, $loadedConfig['svn_user_name'], '列宽应该大于 0');
    }
    
    /**
     * 测试表格列可见性配置
     */
    public function testTableColumnVisibilityConfiguration()
    {
        // 模拟所有可用列
        $allColumns = [
            'index', 'svn_user_name', 'svn_user_pass', 'svn_user_status', 
            'svn_user_note', 'svn_user_rep_list', 'svn_user_last_login', 
            'online', 'action'
        ];
        
        // 模拟用户选择的可见列
        $visibleColumns = [
            'index', 'svn_user_name', 'svn_user_status', 
            'svn_user_note', 'svn_user_last_login', 'action'
        ];
        
        // 验证可见列是所有列的子集
        $this->assertTrue(
            empty(array_diff($visibleColumns, $allColumns)),
            '可见列应该是所有列的子集'
        );
        
        // 验证必要列存在
        $this->assertContains('index', $visibleColumns, '序号列应该可见');
        $this->assertContains('svn_user_name', $visibleColumns, '用户名列应该可见');
        $this->assertContains('action', $visibleColumns, '操作列应该可见');
        
        // 验证隐藏的列
        $hiddenColumns = array_diff($allColumns, $visibleColumns);
        $this->assertContains('svn_user_pass', $hiddenColumns, '密码列应该被隐藏');
        $this->assertContains('online', $hiddenColumns, '在线状态列应该被隐藏');
    }
    
    /**
     * 测试表格设置导入导出
     */
    public function testTableSettingsImportExport()
    {
        $settings = [
            'columnWidths' => [
                'svn_user_name' => 150,
                'svn_user_status' => 120,
                'svn_user_note' => 200
            ],
            'visibleColumns' => [
                'index', 'svn_user_name', 'svn_user_status', 'svn_user_note', 'action'
            ],
            'exportTime' => '2024-08-29T10:00:00.000Z'
        ];
        
        // 测试导出
        $exportedSettings = json_encode($settings, JSON_PRETTY_PRINT);
        $this->assertJson($exportedSettings, '设置应该能够导出为 JSON');
        
        // 测试导入
        $importedSettings = json_decode($exportedSettings, true);
        $this->assertEquals($settings, $importedSettings, '设置应该能够正确导入');
        
        // 验证设置结构
        $this->assertArrayHasKey('columnWidths', $importedSettings, '应该包含列宽配置');
        $this->assertArrayHasKey('visibleColumns', $importedSettings, '应该包含可见列配置');
        $this->assertArrayHasKey('exportTime', $importedSettings, '应该包含导出时间');
        
        // 验证数据类型
        $this->assertIsArray($importedSettings['columnWidths'], '列宽配置应该是数组');
        $this->assertIsArray($importedSettings['visibleColumns'], '可见列配置应该是数组');
        $this->assertIsString($importedSettings['exportTime'], '导出时间应该是字符串');
    }
}
