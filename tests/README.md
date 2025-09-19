# WeChat Integration Unit Tests

这个目录包含了企业微信集成功能的单元测试。

## 测试结构

```
tests/
├── bootstrap.php              # 测试引导文件
├── phpunit.xml               # PHPUnit 配置文件
├── run_tests.php             # 简单测试运行器
├── README.md                 # 本文件
└── WeComTests/               # 测试用例目录
    ├── WeComAPITest.php      # API 服务测试
    ├── WeComSyncTest.php     # 同步服务测试
    ├── WeComNotificationTest.php # 通知服务测试
    └── WeComIntegrationTest.php  # 集成测试
```

## 运行测试

### 方法一：使用 PHPUnit（推荐）

如果您已经安装了 PHPUnit：

```bash
# 进入测试目录
cd tests

# 运行所有测试
phpunit

# 运行特定测试套件
phpunit WeComTests/WeComAPITest.php

# 运行集成测试
phpunit WeComTests/WeComIntegrationTest.php

# 运行测试并生成覆盖率报告
phpunit --coverage-html coverage
```

### 方法二：使用简单测试运行器

如果您没有安装 PHPUnit，可以使用我们提供的简单测试运行器：

```bash
# 进入测试目录
cd tests

# 运行测试
php run_tests.php

# 运行性能测试
php performance_test.php
```

## 测试环境

测试使用内存中的 SQLite 数据库，不会影响您的实际数据。测试环境配置包括：

- 数据库：SQLite (内存模式)
- 企业微信配置：测试模式
- HTTP 请求：Mock 响应

## 测试覆盖范围

### WeComAPITest
- Access Token 获取和缓存
- API 错误处理
- 部门列表获取
- 用户信息获取
- 消息发送
- 速率限制处理
- 网络超时处理
- API 日志记录

### WeComSyncTest
- 部门同步
- 用户同步
- 权限同步
- 增量同步 vs 全量同步
- 数据更新和删除
- 用户匹配逻辑
- 同步日志记录

### WeComNotificationTest
- SVN 提交通知
- SVN 删除通知
- 通知规则过滤
- 路径前缀过滤
- 自定义消息模板
- 批量通知
- 通知失败处理
- 统计和日志清理

### WeComIntegrationTest
- 完整的部门同步流程
- 用户同步和匹配流程
- 权限同步流程
- 端到端通知工作流
- 错误处理集成测试
- 批量处理集成测试
- 数据一致性验证
- 系统性能负载测试

## Mock 系统

测试使用 Mock 系统来模拟企业微信 API 响应，避免实际的网络请求。您可以在测试中设置 Mock 响应：

```php
// 设置 Mock 响应
MockHttpResponse::setResponse(
    'https://qyapi.weixin.qq.com/cgi-bin/gettoken',
    [
        'errcode' => 0,
        'errmsg' => 'ok',
        'access_token' => 'test_token',
        'expires_in' => 7200
    ]
);
```

## 添加新测试

要添加新的测试用例：

1. 在 `WeComTests/` 目录下创建新的测试文件
2. 继承 `PHPUnit\Framework\TestCase`
3. 在 `setUp()` 方法中初始化测试环境
4. 在 `tearDown()` 方法中清理测试数据
5. 创建以 `test` 开头的测试方法

示例：

```php
<?php
use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        clearTestData();
        MockHttpResponse::clearResponses();
    }

    protected function tearDown(): void
    {
        clearTestData();
        MockHttpResponse::clearResponses();
        parent::tearDown();
    }

    public function testSomething()
    {
        // 您的测试代码
        $this->assertTrue(true);
    }
}
```

## 测试数据管理

测试提供了以下辅助函数来管理测试数据：

- `clearTestData()` - 清理所有测试数据
- `insertTestData($table, $data)` - 插入测试数据
- `getTestData($table, $where = [])` - 获取测试数据
- `getTestDatabase()` - 获取测试数据库实例

## 持续集成

这些测试可以集成到 CI/CD 流水线中：

```bash
# 在 CI 脚本中运行测试
cd tests && php run_tests.php
```

测试失败时会返回非零退出码，成功时返回零退出码。

## 故障排除

### 常见问题

1. **"PHPUnit is not available"**
   - 安装 PHPUnit：`composer require --dev phpunit/phpunit`
   - 或使用简单测试运行器：`php run_tests.php`

2. **"Test file not found"**
   - 确保您在 `tests` 目录中运行测试
   - 检查测试文件是否存在

3. **数据库连接错误**
   - 测试使用内存数据库，不需要外部数据库
   - 检查 SQLite 扩展是否已安装

4. **Mock 响应不生效**
   - 确保在测试开始前调用 `MockHttpResponse::clearResponses()`
   - 检查 Mock URL 是否与实际请求 URL 完全匹配

### 调试测试

要调试失败的测试：

1. 在测试方法中添加 `var_dump()` 或 `echo` 语句
2. 使用 `--verbose` 选项运行 PHPUnit
3. 检查测试数据库中的数据状态
4. 验证 Mock 响应设置是否正确

## 性能测试

除了功能测试外，我们还提供了性能测试来确保系统在负载下的表现：

### 运行性能测试

```bash
cd tests
php performance_test.php
```

### 性能测试内容

1. **部门同步性能**: 测试 100 个部门的同步速度
2. **用户同步性能**: 测试 500 个用户的同步速度
3. **通知性能**: 测试单个通知的发送速度
4. **批量通知性能**: 测试 100 个事件的批量处理速度
5. **数据库性能**: 测试数据库操作的性能
6. **内存使用**: 监控内存使用情况

### 性能基准

- 部门同步: ≥50 部门/秒, ≤5秒完成
- 用户同步: ≥100 用户/秒, ≤10秒完成
- 通知发送: ≥20 通知/秒, ≤5秒完成
- 批量通知: ≥50 事件/秒, ≤3秒完成

### 性能优化建议

如果性能测试未通过基准：

1. 检查数据库索引是否正确创建
2. 优化 API 调用频率和批量处理
3. 调整内存限制和 PHP 配置
4. 考虑使用缓存机制
5. 检查网络延迟和带宽

## 持续集成

测试可以集成到 CI/CD 流水线中：

```bash
# 功能测试
cd tests && php run_tests.php

# 性能测试（可选）
cd tests && php performance_test.php
```

## 贡献

欢迎为测试套件贡献新的测试用例！请确保：

1. 测试覆盖了新功能的各种场景
2. 测试是独立的，不依赖其他测试的状态
3. 使用描述性的测试方法名称
4. 添加适当的断言和错误消息
5. 更新本 README 文件（如有必要）
6. 为新功能添加相应的集成测试
7. 考虑性能影响并添加性能测试（如需要）
