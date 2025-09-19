# SVNAdmin 增量更新指南

## 概述

本指南用于将新开发的功能和bug修复**增量更新**到现有的生产环境，而不是完整迁移。

## 更新内容

### 1. Bug 修复
- **仓库重命名权限丢失问题**：修复了重命名仓库时权限配置丢失的bug
- **表格列宽调整问题**：添加了表格列宽可拖拽调整功能

### 2. 新功能（可选）
- **企业微信集成**：完整的企业微信组织架构同步和通知功能

## 需要更新的文件

### 核心Bug修复文件（必须更新）

#### 后端文件
```
02.php/extension/Witersen/SVNAdmin.php          # 修复仓库重命名权限问题
02.php/app/service/Svnrep.php                   # 修复仓库重命名权限问题
```

#### 前端文件
```
01.web/src/components/ResizableTable.vue        # 新增：可调整列宽的表格组件
01.web/src/components/TableToolbar.vue          # 新增：表格工具栏组件
01.web/src/views/repositoryUser/index.vue       # 更新：集成可调整列宽功能
01.web/src/views/repositoryInfo/index.vue       # 更新：集成可调整列宽功能
```

### 企业微信集成文件（可选更新）

#### 配置文件
```
02.php/config/wecom.php                         # 新增：企业微信配置文件
02.php/config/wecom.php.template                # 新增：配置模板
02.php/config/daemon.php                        # 更新：添加企业微信同步配置
```

#### 数据库文件
```
02.php/templete/database/sqlite/wecom_tables.sql    # 新增：SQLite企业微信表结构
02.php/templete/database/mysql/wecom_tables.sql     # 新增：MySQL企业微信表结构
04.update/wecom-integration/database_migration.php  # 新增：数据库迁移脚本
```

#### 核心服务文件
```
02.php/app/service/WeComAPI.php                 # 新增：企业微信API服务
02.php/app/service/WeComSync.php                # 新增：企业微信同步服务
02.php/app/service/WeComNotification.php        # 新增：企业微信通知服务
02.php/app/controller/WeComAdmin.php            # 新增：企业微信管理控制器
02.php/app/util/WeComNotificationClient.php     # 新增：通知客户端工具
```

#### 守护进程文件
```
02.php/server/svnadmind.php                     # 更新：添加企业微信同步进程
02.php/server/wecom_notification_daemon.php     # 新增：企业微信通知守护进程
02.php/server/wecom_install.php                 # 新增：企业微信安装脚本
```

#### 前端企业微信管理界面
```
01.web/src/views/wecom/index.vue                # 新增：企业微信主界面
01.web/src/views/wecom/components/WecomConfig.vue       # 新增：配置管理
01.web/src/views/wecom/components/WecomSync.vue         # 新增：同步管理
01.web/src/views/wecom/components/WecomNotification.vue # 新增：通知管理
01.web/src/views/wecom/components/WecomMapping.vue      # 新增：用户映射
01.web/src/views/wecom/components/WecomMonitor.vue      # 新增：监控界面
01.web/src/views/wecom/components/WecomHelp.vue         # 新增：帮助文档
```

#### SVN钩子脚本
```
02.php/templete/hooks/wecom_notify/              # 新增：企业微信通知钩子目录
02.php/app/script/wecom_notify.php              # 新增：通知处理脚本
```

## 增量更新步骤

### 第一步：仅更新Bug修复（推荐先做）

1. **备份关键文件**
```bash
# 备份要修改的文件
cp 02.php/extension/Witersen/SVNAdmin.php 02.php/extension/Witersen/SVNAdmin.php.backup
cp 02.php/app/service/Svnrep.php 02.php/app/service/Svnrep.php.backup
cp -r 01.web/src/views/repositoryUser 01.web/src/views/repositoryUser.backup
cp -r 01.web/src/views/repositoryInfo 01.web/src/views/repositoryInfo.backup
```

2. **更新后端修复文件**
```bash
# 上传并替换修复后的文件
scp 02.php/extension/Witersen/SVNAdmin.php root@your-server:/opt/svnadmin/02.php/extension/Witersen/
scp 02.php/app/service/Svnrep.php root@your-server:/opt/svnadmin/02.php/app/service/
```

3. **更新前端组件**
```bash
# 创建新组件目录
mkdir -p /opt/svnadmin/01.web/src/components

# 上传新组件
scp 01.web/src/components/ResizableTable.vue root@your-server:/opt/svnadmin/01.web/src/components/
scp 01.web/src/components/TableToolbar.vue root@your-server:/opt/svnadmin/01.web/src/components/

# 更新现有页面
scp 01.web/src/views/repositoryUser/index.vue root@your-server:/opt/svnadmin/01.web/src/views/repositoryUser/
scp 01.web/src/views/repositoryInfo/index.vue root@your-server:/opt/svnadmin/01.web/src/views/repositoryInfo/
```

4. **重新构建前端**
```bash
cd /opt/svnadmin/01.web
npm install  # 如果有新依赖
npm run build
```

5. **重启服务**
```bash
cd /opt/svnadmin
docker-compose restart
```

### 第二步：添加企业微信功能（可选）

1. **上传企业微信相关文件**
```bash
# 配置文件
scp 02.php/config/wecom.php.template root@your-server:/opt/svnadmin/02.php/config/

# 数据库文件
scp -r 02.php/templete/database/sqlite/wecom_tables.sql root@your-server:/opt/svnadmin/02.php/templete/database/sqlite/
scp -r 04.update root@your-server:/opt/svnadmin/

# 服务文件
scp -r 02.php/app/service/WeCom*.php root@your-server:/opt/svnadmin/02.php/app/service/
scp 02.php/app/controller/WeComAdmin.php root@your-server:/opt/svnadmin/02.php/app/controller/
scp 02.php/app/util/WeComNotificationClient.php root@your-server:/opt/svnadmin/02.php/app/util/

# 守护进程文件
scp 02.php/server/wecom_*.php root@your-server:/opt/svnadmin/02.php/server/

# 前端文件
scp -r 01.web/src/views/wecom root@your-server:/opt/svnadmin/01.web/src/views/

# 钩子脚本
scp -r 02.php/templete/hooks/wecom_notify root@your-server:/opt/svnadmin/02.php/templete/hooks/
scp 02.php/app/script/wecom_notify.php root@your-server:/opt/svnadmin/02.php/app/script/
```

2. **执行数据库迁移**
```bash
cd /opt/svnadmin
docker exec -it your-container-name php 04.update/wecom-integration/database_migration.php
```

3. **重新构建和重启**
```bash
cd /opt/svnadmin/01.web
npm run build

cd /opt/svnadmin
docker-compose restart
```

## 验证更新

### 验证Bug修复
1. **测试仓库重命名**：创建测试仓库，设置权限，重命名后检查权限是否保持
2. **测试表格列宽**：在用户管理和仓库管理页面测试列宽拖拽功能

### 验证企业微信功能（如果启用）
1. **访问企业微信管理页面**：检查新的管理界面是否正常显示
2. **测试配置功能**：尝试配置企业微信参数

## 回滚方案

### Bug修复回滚
```bash
# 恢复备份文件
cp 02.php/extension/Witersen/SVNAdmin.php.backup 02.php/extension/Witersen/SVNAdmin.php
cp 02.php/app/service/Svnrep.php.backup 02.php/app/service/Svnrep.php
cp -r 01.web/src/views/repositoryUser.backup 01.web/src/views/repositoryUser
cp -r 01.web/src/views/repositoryInfo.backup 01.web/src/views/repositoryInfo

# 重新构建
cd 01.web && npm run build
cd .. && docker-compose restart
```

### 企业微信功能回滚
```bash
# 删除新增文件
rm -rf 01.web/src/views/wecom
rm -f 02.php/app/service/WeCom*.php
rm -f 02.php/app/controller/WeComAdmin.php
rm -f 02.php/server/wecom_*.php
# ... 其他新增文件

# 重新构建
cd 01.web && npm run build
cd .. && docker-compose restart
```

## 注意事项

1. **分步更新**：建议先更新Bug修复，验证无误后再添加企业微信功能
2. **备份重要**：每次更新前都要备份要修改的文件
3. **测试验证**：每个步骤完成后都要进行功能验证
4. **权限设置**：确保上传的文件有正确的权限设置
5. **前端构建**：前端文件更新后必须重新构建

## 最小化更新方案

如果只想修复关键bug，最少只需要更新这4个文件：
```
02.php/extension/Witersen/SVNAdmin.php
02.php/app/service/Svnrep.php
01.web/src/components/ResizableTable.vue       (新增)
01.web/src/components/TableToolbar.vue         (新增)
01.web/src/views/repositoryUser/index.vue
01.web/src/views/repositoryInfo/index.vue
```

然后重新构建前端并重启服务即可。
