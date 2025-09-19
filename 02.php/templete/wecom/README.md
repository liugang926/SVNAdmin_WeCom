# 企业微信集成配置模板

## 📋 概述

本目录包含企业微信集成功能的配置模板和相关工具，帮助用户快速配置和验证企业微信集成功能。

## 📁 文件说明

### 配置模板

- **`config_template.php`**: 完整的配置模板文件
  - 包含所有可配置选项
  - 详细的配置说明和示例
  - 分类组织的配置项
  - 最佳实践建议

### 配置工具

- **`setup_wizard_advanced.php`**: 高级配置向导
  - 交互式配置生成
  - 基于模板的配置创建
  - 配置验证和优化建议
  - 支持增量配置

- **`config_validator.php`**: 配置验证工具
  - 配置完整性检查
  - 配置正确性验证
  - API 连接测试
  - 详细的错误报告

## 🚀 快速开始

### 方法一：使用配置向导（推荐）

```bash
# 运行高级配置向导
php 02.php/templete/wecom/setup_wizard_advanced.php
```

配置向导将引导您完成以下步骤：
1. 基础配置（企业微信应用信息）
2. 同步配置（同步间隔和选项）
3. 部门映射配置
4. 用户匹配配置
5. 权限映射配置
6. 通知配置
7. 高级配置（缓存、日志、安全等）

### 方法二：手动配置

```bash
# 1. 复制配置模板
cp 02.php/templete/wecom/config_template.php 02.php/config/wecom.php

# 2. 编辑配置文件
nano 02.php/config/wecom.php

# 3. 验证配置
php 02.php/templete/wecom/config_validator.php
```

## 🔧 配置说明

### 必填配置项

以下配置项是必须填写的：

```php
'corp_id' => 'your_corp_id_here',           // 企业 ID
'corp_secret' => 'your_corp_secret_here',   // 应用密钥
'agent_id' => 'your_agent_id_here',         // 应用 ID
```

### 重要配置项

#### 功能开关

```php
'sync_enabled' => true,                     // 是否启用数据同步
'notification_enabled' => true,             // 是否启用消息通知
'debug' => false,                           // 是否启用调试模式
```

#### 同步配置

```php
'sync' => [
    'department_interval' => 3600,          // 部门同步间隔（秒）
    'user_interval' => 1800,                // 用户同步间隔（秒）
    'permission_interval' => 3600,          // 权限同步间隔（秒）
    'batch_size' => 100,                    // 批量处理大小
],
```

#### 通知配置

```php
'notification' => [
    'default_webhook' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx',
    'queue_enabled' => true,                // 启用消息队列
    'batch_processing' => true,             // 启用批量处理
],
```

### 高级配置

#### 缓存配置

```php
'cache' => [
    'enabled' => true,                      // 启用缓存
    'driver' => 'file',                     // 缓存驱动：file, redis
    'ttl' => 3600,                          // 缓存时间（秒）
],
```

#### 日志配置

```php
'logging' => [
    'enabled' => true,                      // 启用日志
    'level' => 'INFO',                      // 日志级别
    'max_files' => 30,                      // 最大日志文件数
],
```

#### 安全配置

```php
'security' => [
    'verify_ssl' => true,                   // 验证 SSL 证书
    'ip_whitelist' => [],                   // IP 白名单
    'encrypt_sensitive_data' => false,      // 加密敏感数据
],
```

## 🛠️ 工具使用

### 配置向导

高级配置向导提供交互式配置体验：

```bash
php 02.php/templete/wecom/setup_wizard_advanced.php
```

**功能特性：**
- 分步骤配置引导
- 配置项验证和建议
- 自动生成配置文件
- 配置备份和恢复
- 彩色终端输出

**使用场景：**
- 首次配置
- 配置更新
- 配置迁移
- 批量部署

### 配置验证

配置验证工具确保配置的正确性：

```bash
# 验证默认配置文件
php 02.php/templete/wecom/config_validator.php

# 验证指定配置文件
php 02.php/templete/wecom/config_validator.php /path/to/config.php
```

**验证内容：**
- 配置文件语法
- 必填项检查
- 配置值有效性
- API 连接测试
- 性能优化建议

**输出格式：**
- 彩色终端输出
- 分类错误报告
- 详细建议说明
- 退出码支持

## 📊 配置分类

### 基础配置
- 企业微信应用信息
- API 连接配置
- 功能开关

### 同步配置
- 同步间隔设置
- 批量处理配置
- 错误处理策略

### 映射配置
- 部门到用户组映射
- 用户匹配策略
- 权限映射规则

### 通知配置
- Webhook 配置
- 消息模板设置
- 队列和批量处理

### 性能配置
- 缓存策略
- 连接池设置
- 并发控制

### 安全配置
- SSL 验证
- 访问控制
- 数据加密

### 监控配置
- 日志设置
- 健康检查
- 指标收集

## 🔍 故障排除

### 常见问题

**Q: 配置向导运行失败？**
```bash
# 检查 PHP 版本和扩展
php -v
php -m | grep -E "(curl|json|openssl)"

# 检查文件权限
ls -la 02.php/config/
```

**A: 确保 PHP 7.2+ 并安装必要扩展，配置目录可写。**

**Q: 配置验证失败？**
```bash
# 查看详细错误信息
php 02.php/templete/wecom/config_validator.php 2>&1
```

**A: 根据错误信息修复配置项，常见问题包括必填项缺失、格式错误等。**

**Q: API 连接测试失败？**
```bash
# 测试网络连接
curl -I https://qyapi.weixin.qq.com

# 检查防火墙和代理设置
```

**A: 确保网络可访问企业微信 API，检查防火墙和代理配置。**

### 调试技巧

1. **启用调试模式**
   ```php
   'debug' => true,
   'log_level' => 'DEBUG',
   ```

2. **查看详细日志**
   ```bash
   tail -f 02.php/logs/wecom_*.log
   ```

3. **使用配置验证**
   ```bash
   php 02.php/templete/wecom/config_validator.php
   ```

4. **测试 API 连接**
   ```bash
   php -r "
   require_once '02.php/app/service/WeComAPI.php';
   \$api = new WeComAPI();
   \$token = \$api->getAccessToken();
   echo 'Token: ' . \$token . PHP_EOL;
   "
   ```

## 📈 最佳实践

### 配置管理

1. **版本控制**
   - 配置文件加入版本控制
   - 使用配置模板避免敏感信息泄露
   - 定期备份配置文件

2. **环境分离**
   - 开发、测试、生产环境使用不同配置
   - 使用环境变量管理敏感信息
   - 配置文件权限控制

3. **配置验证**
   - 部署前运行配置验证
   - 定期检查配置有效性
   - 监控配置变更

### 性能优化

1. **缓存策略**
   - 启用 API 响应缓存
   - 合理设置缓存时间
   - 使用 Redis 提升性能

2. **同步优化**
   - 根据数据量调整同步间隔
   - 使用批量处理提升效率
   - 避免频繁的全量同步

3. **通知优化**
   - 启用消息队列
   - 使用批量通知
   - 设置合理的频率限制

### 安全加固

1. **访问控制**
   - 配置 IP 白名单
   - 启用 SSL 证书验证
   - 限制管理员权限

2. **数据保护**
   - 加密敏感配置信息
   - 定期轮换密钥
   - 过滤日志中的敏感信息

3. **监控告警**
   - 启用系统监控
   - 配置异常告警
   - 定期安全审计

## 📞 技术支持

### 获取帮助

1. **查看完整文档**: [../../docs/WECOM_INTEGRATION.md](../../docs/WECOM_INTEGRATION.md)
2. **API 参考**: [../../docs/WECOM_API.md](../../docs/WECOM_API.md)
3. **快速开始**: [../../docs/QUICK_START.md](../../docs/QUICK_START.md)

### 联系方式

- **问题反馈**: 通过 GitHub Issues 提交问题
- **功能建议**: 通过 GitHub Discussions 讨论
- **技术交流**: 加入 QQ 群 633108141

---

*配置模板文档 | 最后更新: 2024年8月29日*
