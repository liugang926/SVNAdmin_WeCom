# SVNAdmin 企业级二次开发项目

基于 [SvnAdminV2.0](https://github.com/witersen/SvnAdminV2.0) 的企业级SVN管理系统二次开发版本，集成企业微信通知、优化中文支持，并提供完整的Docker化部署方案。

## 📋 项目概述

SVNAdmin 是一个功能完善的 Subversion 管理系统，提供 Web 界面来管理 SVN 仓库、用户权限和通知功能。本项目在原版基础上进行了以下重要改进：

### ✨ 主要特性

- **🌐 Web 管理界面**：基于 Vue.js 2 + View UI 4 的现代化界面
- **👥 用户管理**：支持本地用户和 LDAP 集成
- **📁 仓库管理**：SVN 仓库的创建、删除、备份和权限管理
- **🔔 企业微信集成**：自动通知提交、权限变更等操作
- **🌏 中文支持优化**：完整支持中文仓库名和路径
- **📊 统计分析**：仓库使用情况和用户活动统计
- **🔐 LDAP 认证**：企业级用户认证集成
- **🐳 Docker 化部署**：一键部署和扩展

### 🚀 二次开发改进

1. **企业微信通知系统**
   - SVN 提交通知
   - 权限变更通知
   - 用户操作审计

2. **中文支持优化**
   - 标准 UTF-8 locale 配置
   - 中文仓库名完整支持
   - Unicode 路径处理优化

3. **容器化部署优化**
   - 前端预构建集成
   - 多阶段构建优化
   - 配置持久化支持

## 🏗️ 项目架构

```
SVNAdmin/
├── 00.static/          # 静态资源
├── 01.web/            # Vue.js 前端项目
│   ├── src/           # 源代码
│   ├── package.json   # 前端依赖
│   └── webpack配置    # 构建配置
├── 02.php/            # PHP 后端项目
│   ├── app/           # 应用层
│   ├── config/        # 配置文件
│   ├── templete/      # 模板文件
│   └── server/        # 服务层
├── 03.cicd/           # CI/CD 和容器化
│   └── svnadmin_docker/
│       ├── start.sh           # 容器启动脚本
│       ├── hook_manager.sh    # 钩子管理器
│       └── db_auto_init.sh    # 数据库自动初始化
├── 04.update/         # 数据库迁移和更新
│   └── wecom-integration/     # 企业微信集成
├── data/              # 初始化数据
├── config/            # 运行时配置
└── Dockerfile.optimized       # 优化版容器构建文件
```

## 🛠️ 技术栈

### 前端技术
- **Vue.js 2.5.16**：渐进式JavaScript框架
- **Vue Router 3.5.2**：官方路由管理器
- **View UI 4.7.0**：企业级UI组件库
- **Webpack 2.7.0**：模块打包工具
- **Axios 0.25.0**：HTTP客户端
- **Less 2.7.1**：CSS预处理器

### 后端技术
- **PHP 7.2+**：服务端脚本语言
- **SQLite**：轻量级数据库
- **Apache HTTP Server**：Web服务器
- **mod_dav_svn**：Apache SVN 模块
- **Subversion 1.14.3**：版本控制系统

### 基础设施
- **CentOS 7**：操作系统基础镜像
- **Docker**：容器化平台
- **LDAP**：企业用户认证
- **企业微信API**：消息通知集成

## 🚀 快速开始

### 环境要求

- Docker 20.10+
- Docker Compose 1.29+
- 8GB+ 可用内存
- 10GB+ 可用磁盘空间

### 一键部署

1. **克隆项目**
```bash
git clone <repository-url>
cd SVNAdmin
```

2. **构建镜像**
```bash
docker build -f Dockerfile.optimized -t svnadmin:latest .
```

3. **启动服务**
```bash
docker run -d \
  --name svnadmin \
  -p 80:80 \
  -p 3690:3690 \
  -v svnadmin_data:/home/svnadmin \
  -v svnadmin_www:/var/www/html \
  svnadmin:latest
```

4. **访问系统**
- Web界面：http://localhost
- 默认账号：admin/admin

### 使用 Docker Compose

```bash
docker-compose -f docker-compose.optimized.yml up -d
```

## 🔧 配置说明

### 基础配置

系统启动后，通过Web界面进行初始配置：

1. **系统设置**
   - SVN路径配置
   - Apache配置
   - 数据库设置

2. **用户认证**
   - 本地用户管理
   - LDAP集成配置

3. **企业微信配置**
   - Webhook URL
   - 通知规则设置

### 环境变量

| 变量名 | 描述 | 默认值 |
|--------|------|--------|
| `TZ` | 时区设置 | `Asia/Shanghai` |
| `LANG` | 系统语言 | `en_US.UTF-8` |
| `LC_ALL` | 本地化设置 | `en_US.UTF-8` |

### 数据持久化

重要数据目录挂载：

```bash
-v /path/to/svn/repos:/home/svnadmin/rep          # SVN仓库
-v /path/to/config:/home/svnadmin/config          # 配置文件
-v /path/to/logs:/home/svnadmin/logs              # 日志文件
-v /path/to/backup:/home/svnadmin/backup          # 备份文件
```

## 🔍 故障排查

### 常用诊断脚本

项目提供了多个诊断工具：

```bash
# 容器内部诊断
./container_debug.sh

# SVN访问测试
./debug_svn_access.sh

# 中文仓库测试
./test_chinese_repo.sh

# SVN配置检查
./check_svn_config.sh
```

### 常见问题

#### 1. 中文仓库名无法访问

**症状**：英文仓库正常，中文仓库返回404或乱码

**解决方案**：
- 确认locale设置为 `en_US.UTF-8`
- 检查Apache SVN配置中的UTF-8支持
- 重启容器确保配置生效

#### 2. LDAP认证失败

**症状**：LDAP用户无法登录

**解决方案**：
- 检查LDAP服务器连接
- 验证LDAP配置参数
- 查看Apache错误日志

#### 3. 企业微信通知失败

**症状**：SVN操作无通知推送

**解决方案**：
- 检查企业微信Webhook URL
- 验证网络连接和防火墙
- 查看通知发送日志

## 📊 监控和日志

### 日志位置

- **Apache日志**：`/var/log/httpd/`
- **SVN服务日志**：`/home/svnadmin/logs/svnserve.log`
- **应用日志**：`/var/www/html/logs/`
- **企业微信通知日志**：`/var/www/html/logs/wecom_*.log`

### 健康检查

```bash
# 检查服务状态
docker exec svnadmin ps aux | grep -E 'httpd|svnserve'

# 检查端口监听
docker exec svnadmin netstat -tlnp | grep -E ':80|:3690'

# 测试Web访问
curl -I http://localhost/

# 测试SVN访问
curl -I http://localhost/svn/
```

## 🚀 开发指南

### 前端开发

```bash
cd 01.web
npm install
npm run dev        # 开发模式
npm run build      # 生产构建
```

### 后端开发

```bash
cd 02.php
# 修改PHP代码后重启Apache
docker exec svnadmin httpd -k graceful
```

### 企业微信集成开发

企业微信通知功能位于：
- 配置：`04.update/wecom-integration/`
- 实现：`02.php/app/service/WeCom.php`
- 钩子：`03.cicd/svnadmin_docker/hook_manager.sh`

## 📈 性能优化

### 前端优化

- Webpack生产构建优化
- 静态资源压缩
- 浏览器缓存策略

### 后端优化

- PHP-FPM配置调优
- Apache模块优化
- SQLite查询优化

### 系统优化

- 容器资源限制
- 磁盘I/O优化
- 网络配置优化

## 🔐 安全考虑

### 安全特性

- HTTPS支持（需配置SSL证书）
- LDAP集成的企业认证
- 权限细粒度控制
- 操作审计日志

### 安全建议

1. **定期更新**：及时更新系统和依赖
2. **访问控制**：限制管理员权限
3. **网络隔离**：使用防火墙限制访问
4. **备份策略**：定期备份重要数据
5. **监控告警**：设置异常行为监控

## 🤝 贡献指南

### 开发流程

1. Fork 项目
2. 创建功能分支
3. 提交代码更改
4. 创建 Pull Request

### 代码规范

- **前端**：遵循 Vue.js 官方风格指南
- **后端**：遵循 PSR-12 PHP 编码标准
- **容器**：遵循 Dockerfile 最佳实践

## 📄 许可证

本项目基于原开源项目进行二次开发，遵循相关开源协议。

## 🙋 支持和反馈

### 技术支持

- 问题反馈：微信公众号：大刘讲IT
- 技术讨论：原项目作者QQ群633108141
- 邮件支持：709840110@qq.com

### 更新日志

增加企业微信通讯录集成及企业微信通知功能

---

**注意**：本项目为企业内部二次开发版本，包含了针对特定业务场景的定制化功能。使用前请确保了解相关配置要求和安全考虑。另外，本项目纯AI工具开发验证项目，部分代码存在冗余未清理，但方法未引用，使用时可根据自己的需求调整清除。
