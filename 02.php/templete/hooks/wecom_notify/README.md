# 企业微信通知钩子脚本

本目录包含用于 SVN 仓库的企业微信通知钩子脚本，可以在 SVN 操作时自动发送通知到企业微信群。

## 🆕 最新版本特性

- ✅ **UTF-8编码支持** - 完美支持中文文件名和提交备注
- ✅ **用户名转换** - 自动将SVN账号转换为真实姓名
- ✅ **文件列表解析** - 正确显示提交的文件清单
- ✅ **错误处理优化** - 更好的错误处理和日志记录

## 文件说明

- `post-commit` - **最新版钩子脚本**（推荐使用）
- `post-commit-simple` - 简化版提交后钩子脚本
- `post-revprop-change` - 修订属性更改钩子脚本
- `hookName` - 钩子名称文件
- `hookDescription` - 钩子描述文件

## 🚀 自动部署方式（推荐）

### 通过SVNAdmin管理界面

1. 登录 SVNAdmin 管理界面
2. 进入"企业微信集成" → "通知规则管理"
3. 创建或编辑通知规则
4. 选择要启用通知的仓库
5. 点击"自动部署钩子"按钮

系统会自动：
- 将最新的钩子脚本部署到选定的仓库
- 设置正确的文件权限
- 备份现有的钩子文件（如果存在）

### 通过API接口

```bash
# 生成钩子代码
curl -X POST "http://your-domain/api/wecom/hook/generate" \
     -H "Content-Type: application/json" \
     -d '{"repo_name": "your_repo"}'

# 自动部署到所有相关仓库
curl -X POST "http://your-domain/api/wecom/hook/deploy" \
     -H "Content-Type: application/json" \
     -d '{"rule_id": 1, "force_overwrite": true}'
```

## 🔧 手动部署方式

### 1. 复制钩子脚本

```bash
# 进入 SVN 仓库目录
cd /path/to/your/svn/repository

# 复制最新的钩子脚本
cp /path/to/svnadmin/02.php/templete/hooks/wecom_notify/post-commit hooks/post-commit

# 设置执行权限
chmod +x hooks/post-commit
```

### 2. 验证部署

```bash
# 测试钩子脚本
./hooks/post-commit /path/to/repository 123

# 检查输出应该显示：
# Notification sent successfully: X messages
```

## 📋 配置要求

### 环境要求

- PHP 7.0+
- SQLite 或 MySQL 数据库
- 企业微信群机器人 Webhook URL
- UTF-8 locale 支持（容器中已配置）

### 数据库配置

钩子脚本会自动：
- 读取 SVNAdmin 的数据库配置
- 查找企业微信用户映射关系
- 记录通知发送日志

### 企业微信配置

1. **创建群机器人**
   - 在企业微信群中添加机器人
   - 获取 Webhook URL

2. **配置通知规则**
   - 设置仓库过滤条件
   - 配置消息模板
   - 指定接收人员

3. **用户映射**
   - 在"企业微信用户管理"中建立 SVN 账号与真实姓名的映射关系

## 🔍 功能特性

### UTF-8 编码支持

钩子脚本自动设置正确的环境变量：
```bash
export LANG=en_US.utf8
export LC_ALL=en_US.utf8
export LC_CTYPE=en_US.utf8
```

### 用户名转换

自动查询企业微信用户表，将 SVN 账号转换为真实姓名：
- 优先匹配 `svn_username` 字段
- 其次匹配 `wecom_user_id` 字段
- 找不到映射时显示原 SVN 账号

### 文件列表解析

智能解析 SVN changed 输出：
```
A   新增文件.txt
M   修改文件.php
D   删除文件.doc
```

转换为易读的文件列表显示在通知中。

### 错误处理

- 所有错误都不会影响 SVN 操作
- 详细的错误日志记录
- 自动重试机制

## 📊 监控和日志

### 通知日志

在 SVNAdmin 中查看：
- 通知发送状态
- 发送时间和接收人
- 错误信息和重试记录

### 钩子日志

```bash
# 查看钩子执行日志
tail -f /home/svnadmin/rep/REPO_NAME/hooks/post-commit.log

# 查看企业微信通知日志
tail -f /var/www/html/logs/wecom_notify.log
```

## 🔧 故障排除

### 常见问题

1. **中文乱码**
   - ✅ 已解决：最新版本自动处理 UTF-8 编码

2. **用户名显示为账号**
   - 检查企业微信用户表中的映射关系
   - 确保 `svn_username` 或 `wecom_user_id` 字段正确

3. **文件列表为空**
   - ✅ 已解决：最新版本正确解析文件列表

4. **通知发送失败**
   - 检查 Webhook URL 是否正确
   - 验证网络连接
   - 查看通知规则配置

### 调试模式

```bash
# 手动执行钩子查看详细输出
/path/to/repo/hooks/post-commit /path/to/repo REVISION_NUMBER

# 查看数据库中的通知记录
sqlite3 /home/svnadmin/svnadmin.db "SELECT * FROM wecom_notification_logs ORDER BY created_at DESC LIMIT 5;"
```

## 🔄 容器重构后的部署

当重新构建 Docker 容器时：

1. **自动部署**（推荐）
   - 钩子脚本已包含在容器镜像中
   - 通过管理界面重新部署到各仓库

2. **手动部署**
   ```bash
   # 运行部署脚本
   ./deploy_wecom_notification.sh
   ```

## 📚 更多信息

- [SVN 钩子脚本官方文档](https://svnbook.red-bean.com/en/1.7/svn.reposadmin.create.html#svn.reposadmin.create.hooks)
- [企业微信群机器人开发文档](https://developer.work.weixin.qq.com/document/path/91770)
- SVNAdmin 企业微信集成配置指南

---

**版本**: 2.0  
**更新时间**: 2025-09-08  
**兼容性**: SVNAdmin 容器版本