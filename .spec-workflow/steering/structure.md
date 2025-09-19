# Project Structure

## Directory Organization

```
SVNAdmin/                           # 项目根目录
├── 00.static/                      # 静态资源文件
│   ├── css/                        # 样式文件
│   ├── js/                         # JavaScript 文件
│   ├── images/                     # 图片资源
│   └── fonts/                      # 字体文件
├── 01.web/                         # Web 前端界面
│   ├── index.php                   # 主入口文件
│   ├── login.php                   # 登录页面
│   ├── dashboard.php               # 仪表板
│   ├── repository/                 # 仓库管理页面
│   ├── user/                       # 用户管理页面
│   ├── group/                      # 组管理页面
│   ├── wecom/                      # 企业微信集成页面 [新增]
│   │   ├── sync.php                # 同步管理界面
│   │   ├── notification.php        # 通知规则配置
│   │   └── mapping.php             # 部门映射管理
│   └── common/                     # 公共页面组件
├── 02.php/                         # PHP 后端核心代码
│   ├── config/                     # 配置文件
│   │   ├── database.php            # 数据库配置
│   │   ├── svn.php                 # SVN 配置
│   │   └── wecom.php               # 企业微信配置 [新增]
│   ├── class/                      # 核心类库
│   │   ├── SVNAdmin.php            # SVN 管理核心类
│   │   ├── Database.php            # 数据库操作类
│   │   ├── User.php                # 用户管理类
│   │   ├── Group.php               # 组管理类
│   │   ├── Repository.php          # 仓库管理类
│   │   ├── WeComAPI.php            # 企业微信 API 类 [新增]
│   │   ├── WeComSync.php           # 企业微信同步类 [新增]
│   │   └── WeComNotification.php   # 企业微信通知类 [新增]
│   ├── api/                        # API 接口
│   │   ├── svn.php                 # SVN 操作 API
│   │   ├── user.php                # 用户管理 API
│   │   ├── group.php               # 组管理 API
│   │   └── wecom.php               # 企业微信 API [新增]
│   └── utils/                      # 工具函数
│       ├── common.php              # 通用工具函数
│       ├── auth.php                # 认证相关函数
│       └── wecom_utils.php         # 企业微信工具函数 [新增]
├── 03.cicd/                        # CI/CD 相关文件
│   ├── Dockerfile                  # Docker 构建文件
│   ├── docker-compose.yml          # Docker Compose 配置
│   └── deploy.sh                   # 部署脚本
├── 04.update/                      # 更新和迁移脚本
│   ├── database/                   # 数据库迁移脚本
│   └── config/                     # 配置更新脚本
├── server/                         # 服务端守护进程
│   ├── svnadmind.php               # 主守护进程
│   ├── install.php                 # 安装脚本
│   ├── wecom_daemon.php            # 企业微信同步守护进程 [新增]
│   └── notification_daemon.php     # 通知发送守护进程 [新增]
├── templete/                       # 模板文件
│   ├── database/                   # 数据库模板
│   ├── initStruct/                 # 仓库初始化结构模板
│   └── wecom/                      # 企业微信相关模板 [新增]
├── hooks/                          # SVN 钩子脚本模板
│   └── wecom_notify/               # 企业微信通知钩子 [新增]
└── logs/                           # 日志文件
    ├── svnadmin.log                # 系统日志
    ├── wecom_sync.log              # 企业微信同步日志 [新增]
    └── notification.log            # 通知发送日志 [新增]
```

## Naming Conventions

### Files
- **PHP 类文件**: `PascalCase.php` (例如: `WeComAPI.php`, `UserManager.php`)
- **PHP 页面文件**: `snake_case.php` (例如: `user_list.php`, `repo_settings.php`)
- **配置文件**: `snake_case.php` (例如: `database.php`, `wecom.php`)
- **JavaScript 文件**: `camelCase.js` (例如: `userManagement.js`, `repoStatus.js`)
- **CSS 文件**: `kebab-case.css` (例如: `main-style.css`, `wecom-integration.css`)

### Code
- **PHP 类名**: `PascalCase` (例如: `WeComSync`, `RepositoryManager`)
- **PHP 方法名**: `camelCase` (例如: `syncDepartments()`, `sendNotification()`)
- **PHP 常量**: `UPPER_SNAKE_CASE` (例如: `WECOM_API_BASE_URL`, `MAX_SYNC_RETRY`)
- **PHP 变量**: `snake_case` (例如: `$user_list`, `$wecom_config`)
- **JavaScript 变量**: `camelCase` (例如: `userList`, `wecomConfig`)

## Import Patterns

### Import Order
1. **PHP 系统库**: 内置 PHP 函数和类
2. **第三方依赖**: Composer 安装的包
3. **项目核心类**: 本项目的核心类库
4. **配置文件**: 配置和常量定义
5. **工具函数**: 辅助函数和工具类

### Module Organization
```php
// PHP 文件导入模式
<?php
// 1. 系统库 (如果需要)
require_once 'vendor/autoload.php';

// 2. 项目配置
require_once '../config/database.php';
require_once '../config/wecom.php';

// 3. 核心类库
require_once '../class/Database.php';
require_once '../class/WeComAPI.php';

// 4. 工具函数
require_once '../utils/common.php';
require_once '../utils/wecom_utils.php';
```

## Code Structure Patterns

### PHP 类文件组织
```php
<?php
/**
 * 类文件标准结构
 */

// 1. 文件头注释和版权信息
// 2. 命名空间声明 (如果使用)
// 3. 依赖导入
// 4. 类常量定义
// 5. 属性声明
// 6. 构造函数
// 7. 公共方法
// 8. 受保护方法
// 9. 私有方法
// 10. 静态方法
```

### Web 页面文件组织
```php
<?php
// 1. 会话和认证检查
// 2. 配置和类库导入
// 3. 权限验证
// 4. 数据处理逻辑
// 5. HTML 模板输出
```

### API 接口文件组织
```php
<?php
// 1. CORS 和 HTTP 头设置
// 2. 认证和权限检查
// 3. 输入参数验证
// 4. 业务逻辑处理
// 5. JSON 响应输出
// 6. 错误处理
```

## Code Organization Principles

1. **单一职责**: 每个类和文件都有明确的单一职责
2. **模块化设计**: 企业微信功能作为独立模块，可选择性启用
3. **配置分离**: 所有配置信息集中管理，支持环境变量覆盖
4. **错误处理**: 统一的错误处理和日志记录机制
5. **向后兼容**: 新功能不影响现有 SVN 管理功能

## Module Boundaries

### 核心模块边界
- **SVN 核心**: 原有的 SVN 仓库管理功能，保持独立和稳定
- **用户管理**: 用户和组管理，扩展支持企业微信用户
- **企业微信集成**: 新增模块，包含同步、通知、API 交互
- **Web 界面**: 前端展示层，扩展企业微信相关页面
- **守护进程**: 后台服务，新增企业微信同步和通知服务

### 依赖关系
```
Web 界面 → API 层 → 业务逻辑层 → 数据访问层
    ↓         ↓         ↓           ↓
企业微信页面 → 企业微信API → 企业微信业务类 → 数据库/文件系统
```

### 模块隔离原则
- **企业微信模块可选**: 可以通过配置完全禁用企业微信功能
- **API 独立**: 企业微信 API 不依赖 SVN 核心功能
- **数据隔离**: 企业微信相关数据有独立的表结构
- **日志分离**: 不同模块使用独立的日志文件

## Code Size Guidelines

### 文件大小限制
- **PHP 类文件**: 最大 500 行，超过时考虑拆分
- **Web 页面文件**: 最大 300 行，复杂页面使用模板分离
- **API 接口文件**: 最大 200 行，单一接口职责
- **配置文件**: 最大 100 行，按功能模块分离

### 函数/方法大小
- **公共方法**: 最大 50 行，复杂逻辑拆分为私有方法
- **私有方法**: 最大 30 行，专注单一功能
- **API 处理函数**: 最大 40 行，包含完整的请求处理流程

### 复杂度控制
- **嵌套深度**: 最大 4 层，使用早期返回减少嵌套
- **方法参数**: 最大 5 个参数，复杂参数使用数组或对象
- **类属性**: 最大 15 个属性，考虑职责分离

## Enterprise WeChat Integration Structure

### 企业微信模块组织
```
wecom/                              # 企业微信集成模块
├── api/                            # API 交互层
│   ├── WeComAPI.php                # 企业微信 API 客户端
│   ├── ContactsAPI.php             # 通讯录 API
│   └── MessageAPI.php              # 消息推送 API
├── sync/                           # 同步服务层
│   ├── DepartmentSync.php          # 部门同步
│   ├── UserSync.php                # 用户同步
│   └── SyncManager.php             # 同步管理器
├── notification/                   # 通知服务层
│   ├── NotificationRule.php        # 通知规则引擎
│   ├── MessageBuilder.php          # 消息构建器
│   └── NotificationSender.php      # 通知发送器
└── mapping/                        # 映射管理层
    ├── DepartmentMapper.php        # 部门映射
    ├── UserMapper.php              # 用户映射
    └── PermissionMapper.php        # 权限映射
```

### 数据流设计
```
企业微信 API → 同步服务 → 数据映射 → SVN 权限系统
                ↓
            通知规则引擎 → 消息构建 → 企业微信推送
```

## Documentation Standards

### 代码注释规范
- **类注释**: 使用 PHPDoc 格式，包含类用途、作者、版本信息
- **方法注释**: 包含参数类型、返回值、异常说明
- **复杂逻辑**: 关键算法和业务逻辑必须有详细注释
- **企业微信集成**: 所有 API 调用和数据映射逻辑必须注释

### 文档文件
- **README.md**: 项目概述和快速开始指南
- **INSTALL.md**: 详细安装和配置说明
- **WECOM_INTEGRATION.md**: 企业微信集成专门文档
- **API.md**: API 接口文档
- **CHANGELOG.md**: 版本更新记录

### 配置文档
- 每个配置文件都有详细的参数说明注释
- 企业微信配置包含获取方式和权限要求说明
- 提供配置示例和常见问题解答