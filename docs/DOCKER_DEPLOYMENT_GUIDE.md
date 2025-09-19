# SVNAdmin Docker 部署指南

## 📋 概述

本文档提供了 SVNAdmin V2.5.10 的完整 Docker 部署方案，包括容器构建、启动和管理的详细说明。

## 🏗️ 项目结构

```
SVNAdmin/
├── 01.web/                    # 前端 Vue.js 应用
├── 02.php/                    # 后端 PHP 应用
├── 03.cicd/                   # CI/CD 和 Docker 配置
├── Dockerfile.local           # 本地开发 Dockerfile
├── docker-compose.local.yml   # 本地开发 Docker Compose
└── docs/                      # 文档目录
```

## 🔧 系统要求

### 主机要求
- **操作系统**: Windows 10/11, macOS, Linux
- **Docker**: 20.10+ 
- **Docker Compose**: 2.0+
- **内存**: 最少 2GB 可用内存
- **磁盘**: 最少 5GB 可用空间

### 网络端口
- **8080**: Web 管理界面 (HTTP)
- **3690**: SVN 协议端口

## 🚀 快速开始

### 1. 克隆项目
```bash
git clone <repository-url>
cd SVNAdmin
```

### 2. 构建容器
```bash
# 停止并清理现有容器
docker-compose -f docker-compose.local.yml down --volumes --remove-orphans

# 构建新容器（无缓存）
docker-compose -f docker-compose.local.yml build --no-cache
```

### 3. 启动服务
```bash
# 后台启动容器
docker-compose -f docker-compose.local.yml up -d

# 查看启动日志
docker logs svnadmin-local
```

### 4. 访问系统
- **Web 界面**: http://localhost:8080
- **默认登录**: 
  - 用户名: `admin`
  - 密码: `admin`
  - 角色: `管理员`

## 📦 容器技术栈

### 基础镜像
- **OS**: CentOS 7.9.2009
- **Web服务器**: Apache HTTP Server 2.4.6
- **PHP**: 7.2.34 (Remi 仓库)
- **数据库**: SQLite (默认) / MySQL (可选)
- **版本控制**: Subversion 1.7.14

### PHP 扩展
```
php-cli, php-common, php-json, php-pdo, php-mysqlnd
php-gd, php-mbstring, php-xml, php-curl, php-process
php-bcmath, php-ldap, php-openssl
```

### 系统工具
```
wget, unzip, curl, which, cronie, at
cyrus-sasl, cyrus-sasl-lib, cyrus-sasl-plain
mod_dav_svn, mod_ldap
```

## 🛠️ 容器管理命令

### 基本操作
```bash
# 查看容器状态
docker-compose -f docker-compose.local.yml ps

# 查看实时日志
docker logs -f svnadmin-local

# 进入容器
docker exec -it svnadmin-local bash

# 重启容器
docker-compose -f docker-compose.local.yml restart

# 停止容器
docker-compose -f docker-compose.local.yml stop

# 完全清理
docker-compose -f docker-compose.local.yml down --volumes --remove-orphans
```

### 服务管理
```bash
# 检查 SVN 守护进程状态
docker exec svnadmin-local ps aux | grep svnadmind

# 检查 Apache 状态
docker exec svnadmin-local ps aux | grep httpd

# 查看端口监听
docker exec svnadmin-local netstat -tlnp
```

## 📁 数据持久化

### 容器内重要目录
```
/var/www/html/          # Web 应用根目录
/home/svnadmin/         # SVN 数据目录
├── rep/               # SVN 仓库存储
├── backup/            # 备份目录
├── logs/              # 日志目录
├── authz              # 权限配置文件
├── passwd             # 用户密码文件
└── svnserve.conf      # SVN 服务配置
```

### 数据卷挂载（生产环境推荐）
```yaml
volumes:
  - ./data:/home/svnadmin
  - ./logs:/var/www/html/logs
```

## 🔍 故障排除

### 常见问题

#### 1. 容器启动失败
```bash
# 查看详细错误日志
docker logs svnadmin-local

# 检查端口占用
netstat -tlnp | grep -E "(8080|3690)"

# 重新构建容器
docker-compose -f docker-compose.local.yml build --no-cache
```

#### 2. "后台程序未启动"
```bash
# 检查 SVN 守护进程
docker exec svnadmin-local ps aux | grep svnadmind

# 手动启动守护进程
docker exec svnadmin-local bash -c "cd /var/www/html && php server/svnadmind.php start"
```

#### 3. 权限问题
```bash
# 修复文件权限
docker exec svnadmin-local chown -R apache:apache /home/svnadmin /var/www/html
```

#### 4. 前端资源加载失败
```bash
# 检查前端文件是否存在
docker exec svnadmin-local ls -la /var/www/html/ | grep -E "(index.html|main.*\.js|main.*\.css)"

# 重新构建前端
docker-compose -f docker-compose.local.yml build --no-cache
```

## 🔧 高级配置

### 环境变量
```bash
# 设置时区
TZ=Asia/Shanghai

# Apache 配置
APACHE_RUN_USER=www-data
APACHE_RUN_GROUP=www-data
```

### 自定义配置
```bash
# 修改 PHP 配置
docker exec svnadmin-local vi /etc/php.ini

# 修改 Apache 配置
docker exec svnadmin-local vi /etc/httpd/conf/httpd.conf

# 重启服务使配置生效
docker-compose -f docker-compose.local.yml restart
```

## 📊 性能监控

### 系统资源
```bash
# 查看容器资源使用
docker stats svnadmin-local

# 查看磁盘使用
docker exec svnadmin-local df -h

# 查看内存使用
docker exec svnadmin-local free -h
```

### 日志监控
```bash
# Apache 访问日志
docker exec svnadmin-local tail -f /var/log/httpd/access_log

# Apache 错误日志
docker exec svnadmin-local tail -f /var/log/httpd/error_log

# SVN 守护进程日志
docker exec svnadmin-local tail -f /var/www/html/logs/svnadmind.log

# 企业微信日志
docker exec svnadmin-local tail -f /var/www/html/logs/wecom.log
```

## 🔐 安全配置

### 防火墙设置
```bash
# 仅允许必要端口
ufw allow 8080/tcp
ufw allow 3690/tcp
```

### SSL/TLS 配置
```bash
# 生成自签名证书（开发环境）
docker exec svnadmin-local openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/apache-selfsigned.key \
  -out /etc/ssl/certs/apache-selfsigned.crt
```

## 🚀 生产环境部署

### 推荐配置
```yaml
version: '3.8'
services:
  svnadmin:
    build:
      context: .
      dockerfile: Dockerfile.local
    container_name: svnadmin-prod
    restart: unless-stopped
    ports:
      - "80:80"
      - "3690:3690"
    volumes:
      - /opt/svnadmin/data:/home/svnadmin
      - /opt/svnadmin/logs:/var/www/html/logs
    environment:
      - TZ=Asia/Shanghai
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api.php?c=Setting&a=GetVerifyOption&t=web"]
      interval: 30s
      timeout: 10s
      retries: 3
    networks:
      - svnadmin-network

networks:
  svnadmin-network:
    driver: bridge
```

### 备份策略
```bash
# 数据备份脚本
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker exec svnadmin-local tar -czf /tmp/svnadmin_backup_$DATE.tar.gz /home/svnadmin
docker cp svnadmin-local:/tmp/svnadmin_backup_$DATE.tar.gz ./backups/
```

## 📞 技术支持

### 获取帮助
- **项目文档**: [README.md](../README.md)
- **企业微信集成**: [WECOM_INTEGRATION.md](WECOM_INTEGRATION.md)
- **问题反馈**: GitHub Issues
- **QQ群**: 633108141

### 版本信息
- **SVNAdmin**: V2.5.10
- **构建日期**: 2025-09-04
- **Docker镜像**: svnadmin-svnadmin:latest

---

## 📝 更新日志

### v2.5.10 (2025-09-04)
- ✅ 修复 PHP 扩展缺失问题
- ✅ 优化容器启动脚本
- ✅ 完善 SVN 服务自动配置
- ✅ 增强企业微信集成功能
- ✅ 改进错误处理和日志记录

---

*本文档最后更新: 2025-09-04*
