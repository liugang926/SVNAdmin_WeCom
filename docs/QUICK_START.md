# 企业微信集成 - 快速开始指南

## 🚀 5分钟快速部署

### 前提条件

- ✅ SVNAdmin 2.5+ 已安装并运行
- ✅ 企业微信管理员权限
- ✅ PHP 7.2+ 环境

### 步骤 1: 获取企业微信应用信息

1. 登录 [企业微信管理后台](https://work.weixin.qq.com)
2. 进入 **应用管理** → **自建应用**
3. 创建新应用，记录以下信息：
   - **企业ID** (CorpId)
   - **应用密钥** (Secret)  
   - **应用ID** (AgentId)

### 步骤 2: 一键安装

```bash
# 进入 SVNAdmin 目录
cd /path/to/svnadmin

# 运行自动安装
php 02.php/server/wecom_install.php install

# 运行配置向导
php 02.php/server/wecom_setup_wizard.php
```

按向导提示输入企业微信应用信息。

### 步骤 3: 启动服务

```bash
# 启动主守护进程
php 02.php/server/svnadmind.php start

# 启动通知守护进程  
php 02.php/server/wecom_notification_daemon.php start
```

### 步骤 4: 配置通知 (可选)

1. 在企业微信群中添加机器人，获取 Webhook 地址
2. 访问 Web 管理界面: `http://your-domain/01.web/#/wecom`
3. 在"通知规则"页面添加通知规则

### 步骤 5: 执行首次同步

在 Web 界面的"数据同步"页面点击"立即同步"，或运行：

```bash
php -r "
require_once '02.php/app/service/WeComSync.php';
\$sync = new WeComSync();
\$sync->syncDepartments();
\$sync->syncUsers();
\$sync->syncPermissions();
"
```

## ✅ 验证安装

### 检查服务状态

```bash
# 检查进程
ps aux | grep -E "(svnadmind|wecom_notification)"

# 检查日志
tail -f 02.php/logs/wecom_*.log
```

### 测试功能

1. **同步测试**: 在 Web 界面查看同步状态
2. **通知测试**: 提交代码到 SVN，检查是否收到企业微信消息
3. **权限测试**: 验证用户权限是否正确分配

## 🔧 常用配置

### 修改同步间隔

编辑 `02.php/config/daemon.php`:

```php
'wecom_sync' => [
    'enabled' => true,
    'sync_interval' => 1800,  // 30分钟同步一次
]
```

### 自定义通知模板

在 Web 界面的通知规则中使用以下变量：

- `{repository}` - 仓库名
- `{author}` - 提交者
- `{revision}` - 版本号
- `{message}` - 提交说明

示例模板：
```
📝 代码提交
仓库: {repository}
作者: {author}
版本: {revision}
说明: {message}
```

## 🆘 遇到问题？

### 常见问题

**Q: API 连接失败？**
- 检查企业微信应用配置是否正确
- 确认网络可以访问 `qyapi.weixin.qq.com`

**Q: 用户同步失败？**
- 检查企业微信应用是否有通讯录权限
- 查看 `02.php/logs/wecom_sync.log` 日志

**Q: 没有收到通知？**
- 确认 SVN 钩子脚本已安装: `ls -la /path/to/repo/hooks/post-commit`
- 检查 Webhook 地址是否正确

### 获取帮助

1. 查看完整文档: [WECOM_INTEGRATION.md](WECOM_INTEGRATION.md)
2. 运行系统检查: `php 02.php/server/wecom_install.php check`
3. 查看错误日志: `tail -f 02.php/logs/wecom_*.log`

---

🎉 **恭喜！** 您已成功部署企业微信集成功能。现在可以享受自动化的组织架构同步和实时消息通知了！
