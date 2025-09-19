# SVNAdmin 生产环境升级指南

## 概述

本指南帮助您将现有的 SVNAdmin 生产环境升级到最新版本，包含：
- 企业微信集成功能
- 仓库权限管理bug修复
- 表格列宽调整功能
- 性能优化和稳定性改进

## 升级前准备

### 1. 环境要求
- CentOS 7
- Docker 和 Docker Compose
- PHP 7.2+ (容器内已包含)
- SQLite 数据库 (保持现有配置)

### 2. 备份现有数据
```bash
# 停止现有服务
docker-compose down

# 备份数据目录
sudo cp -r /path/to/your/svnadmin/data /backup/svnadmin-data-$(date +%Y%m%d)

# 备份数据库文件
sudo cp /path/to/your/svnadmin/database.db /backup/database-$(date +%Y%m%d).db

# 备份配置文件
sudo cp -r /path/to/your/svnadmin/config /backup/svnadmin-config-$(date +%Y%m%d)
```

## 升级步骤

### 第一步：下载最新代码
```bash
# 备份现有项目
sudo mv /path/to/your/svnadmin /backup/svnadmin-old-$(date +%Y%m%d)

# 下载最新代码
git clone https://github.com/witersen/SvnAdminV2.0.git /path/to/your/svnadmin
cd /path/to/your/svnadmin

# 或者如果您有本地修改的代码包，解压到目标目录
```

### 第二步：恢复数据和配置
```bash
# 恢复数据目录
sudo cp -r /backup/svnadmin-data-YYYYMMDD/* /path/to/your/svnadmin/data/

# 恢复数据库文件
sudo cp /backup/database-YYYYMMDD.db /path/to/your/svnadmin/02.php/templete/database/

# 恢复原有配置文件
sudo cp -r /backup/svnadmin-config-YYYYMMDD/* /path/to/your/svnadmin/02.php/config/
```

### 第三步：数据库升级
```bash
# 执行数据库迁移脚本
cd /path/to/your/svnadmin
sudo docker run --rm -v $(pwd):/app -w /app php:7.4-cli php 04.update/wecom-integration/database_migration.php

# 或者手动执行 SQL 脚本（如果需要）
sudo docker run --rm -v $(pwd):/app -w /app php:7.4-cli php -r "
include '02.php/config/config.php';
\$pdo = new PDO('sqlite:02.php/templete/database/sqlite/database.db');
\$sql = file_get_contents('02.php/templete/database/sqlite/wecom_tables.sql');
\$pdo->exec(\$sql);
echo 'Database migration completed successfully.';
"
```

### 第四步：配置企业微信（可选）
如果需要启用企业微信集成功能：

```bash
# 复制配置模板
sudo cp 02.php/config/wecom.php.template 02.php/config/wecom.php

# 编辑配置文件
sudo nano 02.php/config/wecom.php

# 或使用配置向导
sudo docker run --rm -it -v $(pwd):/app -w /app php:7.4-cli php 02.php/server/wecom_setup_wizard.php
```

### 第五步：更新 Docker 配置
```bash
# 使用原有的 docker-compose.yml 或更新为新版本
# 如果使用新的企业微信集成版本：
sudo cp 03.cicd/docker-compose.wecom.yml docker-compose.yml

# 创建环境配置文件
sudo cp 03.cicd/env.wecom.example .env
sudo nano .env  # 根据实际情况修改配置
```

### 第六步：重新构建和启动服务
```bash
# 构建新镜像
sudo docker-compose build

# 启动服务
sudo docker-compose up -d

# 检查服务状态
sudo docker-compose ps
sudo docker-compose logs -f
```

## 验证升级

### 1. 基本功能验证
```bash
# 检查 Web 界面访问
curl -I http://localhost

# 检查 SVN 服务
svn info svn://localhost:3690/your-repo
```

### 2. 新功能验证
- 登录 Web 管理界面
- 检查用户管理页面的表格列宽调整功能
- 测试仓库重命名功能，确认权限保持正确
- 如果配置了企业微信，检查企业微信管理页面

### 3. 数据完整性验证
```bash
# 检查用户数据
sudo docker exec -it svnadmin-container php -r "
include '/var/www/html/02.php/config/config.php';
\$pdo = new PDO('sqlite:/var/www/html/02.php/templete/database/sqlite/database.db');
\$stmt = \$pdo->query('SELECT COUNT(*) FROM svn_users');
echo 'Total users: ' . \$stmt->fetchColumn() . PHP_EOL;
"

# 检查仓库数据
sudo docker exec -it svnadmin-container php -r "
include '/var/www/html/02.php/config/config.php';
\$pdo = new PDO('sqlite:/var/www/html/02.php/templete/database/sqlite/database.db');
\$stmt = \$pdo->query('SELECT COUNT(*) FROM svn_reps');
echo 'Total repositories: ' . \$stmt->fetchColumn() . PHP_EOL;
"
```

## 回滚方案

如果升级后出现问题，可以快速回滚：

```bash
# 停止新服务
sudo docker-compose down

# 恢复旧版本
sudo mv /path/to/your/svnadmin /path/to/your/svnadmin-failed
sudo mv /backup/svnadmin-old-YYYYMMDD /path/to/your/svnadmin

# 恢复数据
sudo cp -r /backup/svnadmin-data-YYYYMMDD/* /path/to/your/svnadmin/data/
sudo cp /backup/database-YYYYMMDD.db /path/to/your/svnadmin/02.php/templete/database/

# 重启旧服务
cd /path/to/your/svnadmin
sudo docker-compose up -d
```

## 企业微信功能配置（可选）

### 基本配置
```php
// 02.php/config/wecom.php
return [
    'api' => [
        'corp_id' => 'your_corp_id',
        'corp_secret' => 'your_corp_secret',
        'agent_id' => 'your_agent_id',
    ],
    'sync' => [
        'enabled' => true,
        'auto_sync_interval' => 3600, // 1小时同步一次
    ],
    'notification' => [
        'enabled' => true,
    ],
];
```

### 启用同步服务
```bash
# 手动执行一次完整同步
sudo docker exec -it svnadmin-container php /var/www/html/02.php/server/wecom_install.php install

# 检查同步状态
sudo docker exec -it svnadmin-container php -r "
include '/var/www/html/02.php/config/config.php';
include '/var/www/html/02.php/app/service/WeComSync.php';
\$sync = new WeComSync();
\$status = \$sync->getSyncStatus();
print_r(\$status);
"
```

## 故障排除

### 常见问题

1. **数据库连接错误**
   ```bash
   # 检查数据库文件权限
   sudo ls -la 02.php/templete/database/sqlite/
   sudo chown -R www-data:www-data 02.php/templete/database/
   ```

2. **企业微信 API 调用失败**
   ```bash
   # 检查网络连接
   sudo docker exec -it svnadmin-container curl -I https://qyapi.weixin.qq.com
   
   # 检查配置文件
   sudo docker exec -it svnadmin-container php 02.php/templete/wecom/config_validator.php
   ```

3. **权限问题**
   ```bash
   # 修复文件权限
   sudo chown -R www-data:www-data /path/to/your/svnadmin/
   sudo chmod -R 755 /path/to/your/svnadmin/
   sudo chmod -R 777 /path/to/your/svnadmin/02.php/templete/database/
   sudo chmod -R 777 /path/to/your/svnadmin/logs/
   ```

## 性能优化建议

1. **启用 Redis 缓存**（可选）
   ```yaml
   # 在 docker-compose.yml 中添加 Redis 服务
   redis:
     image: redis:7-alpine
     restart: unless-stopped
     ports:
       - "6379:6379"
   ```

2. **定期清理日志**
   ```bash
   # 添加到 crontab
   0 2 * * * find /path/to/your/svnadmin/logs -name "*.log" -mtime +30 -delete
   ```

## 支持和维护

- 查看日志：`sudo docker-compose logs -f`
- 重启服务：`sudo docker-compose restart`
- 更新镜像：`sudo docker-compose pull && sudo docker-compose up -d`
- 备份脚本：建议设置定期备份任务

## 联系支持

如果在升级过程中遇到问题，请：
1. 查看 `logs/` 目录下的错误日志
2. 检查 Docker 容器状态：`sudo docker-compose ps`
3. 提供详细的错误信息和环境配置
