# 企业微信集成 API 文档

## 📋 目录

- [概述](#概述)
- [认证方式](#认证方式)
- [通用响应格式](#通用响应格式)
- [错误码说明](#错误码说明)
- [配置管理 API](#配置管理-api)
- [同步管理 API](#同步管理-api)
- [通知管理 API](#通知管理-api)
- [用户映射 API](#用户映射-api)
- [监控统计 API](#监控统计-api)
- [系统管理 API](#系统管理-api)
- [PHP 服务类 API](#php-服务类-api)
- [Webhook 接口](#webhook-接口)
- [SDK 示例](#sdk-示例)

## 概述

企业微信集成模块提供了完整的 REST API 接口，支持配置管理、数据同步、通知规则、用户映射等功能的程序化操作。

### API 基础信息

- **Base URL**: `http://your-domain/02.php/app/controller/WeComAdmin.php`
- **Content-Type**: `application/json`
- **字符编码**: UTF-8
- **请求方法**: GET, POST
- **认证方式**: Session 认证

### 版本信息

- **API 版本**: v1.0
- **最后更新**: 2024-08-29
- **兼容性**: SVNAdmin 2.5.10+

## 认证方式

### Session 认证

所有 API 请求需要先通过 SVNAdmin 的登录认证，获取有效的 Session。

```http
POST /02.php/app/controller/Login.php
Content-Type: application/x-www-form-urlencoded

svn_user_name=admin&svn_user_pass=admin
```

**响应示例:**
```json
{
    "status": 1,
    "message": "登录成功",
    "data": {
        "user_id": 1,
        "user_name": "admin",
        "session_id": "abc123..."
    }
}
```

### 权限要求

企业微信集成 API 需要管理员权限才能访问。

## 通用响应格式

### 成功响应

```json
{
    "status": 1,
    "message": "操作成功",
    "data": {
        // 具体数据内容
    }
}
```

### 错误响应

```json
{
    "status": 0,
    "message": "错误描述",
    "error_code": "WECOM_001",
    "data": null
}
```

### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| status | int | 状态码，1=成功，0=失败 |
| message | string | 响应消息 |
| error_code | string | 错误码（仅错误时返回） |
| data | object/array | 响应数据 |

## 错误码说明

### 通用错误码

| 错误码 | 说明 | 解决方案 |
|--------|------|----------|
| WECOM_001 | 配置文件不存在 | 运行安装脚本创建配置 |
| WECOM_002 | 企业微信 API 连接失败 | 检查网络和配置信息 |
| WECOM_003 | 访问令牌获取失败 | 检查 CorpId 和 Secret |
| WECOM_004 | 权限不足 | 检查企业微信应用权限 |
| WECOM_005 | 数据库操作失败 | 检查数据库连接和表结构 |
| WECOM_006 | 参数验证失败 | 检查请求参数格式 |
| WECOM_007 | 同步进程正在运行 | 等待当前同步完成 |
| WECOM_008 | 通知发送失败 | 检查 Webhook 地址 |
| WECOM_009 | 用户映射不存在 | 先创建用户映射关系 |
| WECOM_010 | 系统维护中 | 稍后重试 |

### 企业微信 API 错误码

| 错误码 | 说明 | 解决方案 |
|--------|------|----------|
| 40013 | 不合法的 CorpId | 检查企业 ID 配置 |
| 40014 | 不合法的 Secret | 检查应用密钥配置 |
| 42001 | access_token 超时 | 系统会自动刷新令牌 |
| 60011 | 部门不存在 | 检查部门 ID 是否正确 |
| 60111 | 用户不存在 | 检查用户 ID 是否正确 |

## 配置管理 API

### 获取配置信息

获取当前企业微信集成配置。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetConfig
```

**响应示例:**
```json
{
    "status": 1,
    "message": "获取配置成功",
    "data": {
        "corp_id": "ww1234567890abcdef",
        "agent_id": "1000001",
        "api_base_url": "https://qyapi.weixin.qq.com",
        "sync_enabled": true,
        "sync_interval": 3600,
        "notification_enabled": true,
        "last_sync_time": "2024-08-29 10:30:00",
        "token_expires_at": "2024-08-29 12:30:00"
    }
}
```

### 更新配置信息

更新企业微信集成配置。

```http
POST /02.php/app/controller/WeComAdmin.php?action=UpdateConfig
Content-Type: application/json

{
    "corp_id": "ww1234567890abcdef",
    "corp_secret": "your_corp_secret",
    "agent_id": "1000001",
    "sync_interval": 3600,
    "notification_enabled": true
}
```

**请求参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| corp_id | string | 是 | 企业 ID |
| corp_secret | string | 是 | 应用密钥 |
| agent_id | string | 是 | 应用 ID |
| sync_interval | int | 否 | 同步间隔（秒） |
| notification_enabled | bool | 否 | 是否启用通知 |

**响应示例:**
```json
{
    "status": 1,
    "message": "配置更新成功",
    "data": {
        "updated_fields": ["corp_id", "agent_id", "sync_interval"],
        "updated_at": "2024-08-29 10:35:00"
    }
}
```

### 测试 API 连接

测试企业微信 API 连接状态。

```http
POST /02.php/app/controller/WeComAdmin.php?action=TestConnection
```

**响应示例:**
```json
{
    "status": 1,
    "message": "连接测试成功",
    "data": {
        "connection_status": "success",
        "access_token": "valid",
        "api_response_time": 156,
        "corp_info": {
            "corp_name": "示例企业",
            "corp_type": "verified"
        }
    }
}
```

### 验证配置

验证配置信息的完整性和正确性。

```http
POST /02.php/app/controller/WeComAdmin.php?action=ValidateConfig
```

**响应示例:**
```json
{
    "status": 1,
    "message": "配置验证通过",
    "data": {
        "validation_result": "passed",
        "checked_items": [
            "corp_id_format",
            "corp_secret_format",
            "agent_id_format",
            "api_connectivity",
            "permissions"
        ],
        "warnings": []
    }
}
```

### 重置配置

重置配置为默认值。

```http
POST /02.php/app/controller/WeComAdmin.php?action=ResetConfig
```

**响应示例:**
```json
{
    "status": 1,
    "message": "配置重置成功",
    "data": {
        "reset_time": "2024-08-29 10:40:00",
        "backup_file": "wecom_config_backup_20240829104000.php"
    }
}
```

## 同步管理 API

### 获取同步状态

获取当前数据同步状态。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetSyncStatus
```

**响应示例:**
```json
{
    "status": 1,
    "message": "获取同步状态成功",
    "data": {
        "sync_status": "idle",
        "last_sync_time": "2024-08-29 09:00:00",
        "next_sync_time": "2024-08-29 10:00:00",
        "sync_progress": {
            "departments": {
                "status": "completed",
                "total": 15,
                "synced": 15,
                "errors": 0
            },
            "users": {
                "status": "completed", 
                "total": 128,
                "synced": 125,
                "errors": 3
            },
            "permissions": {
                "status": "completed",
                "total": 45,
                "synced": 45,
                "errors": 0
            }
        }
    }
}
```

### 触发部门同步

手动触发部门数据同步。

```http
POST /02.php/app/controller/WeComAdmin.php?action=SyncDepartments
```

**响应示例:**
```json
{
    "status": 1,
    "message": "部门同步已启动",
    "data": {
        "sync_id": "sync_dept_20240829103500",
        "started_at": "2024-08-29 10:35:00",
        "estimated_duration": 30
    }
}
```

### 触发用户同步

手动触发用户数据同步。

```http
POST /02.php/app/controller/WeComAdmin.php?action=SyncUsers
```

**请求参数（可选）:**
```json
{
    "department_id": 2,
    "force_update": true
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "用户同步已启动",
    "data": {
        "sync_id": "sync_user_20240829103600",
        "department_id": 2,
        "started_at": "2024-08-29 10:36:00",
        "estimated_duration": 60
    }
}
```

### 触发权限同步

手动触发权限数据同步。

```http
POST /02.php/app/controller/WeComAdmin.php?action=SyncPermissions
```

**响应示例:**
```json
{
    "status": 1,
    "message": "权限同步已启动",
    "data": {
        "sync_id": "sync_perm_20240829103700",
        "started_at": "2024-08-29 10:37:00",
        "estimated_duration": 45
    }
}
```

### 获取同步日志

获取同步操作日志。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetSyncLogs
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| sync_type | string | 否 | 同步类型：departments/users/permissions |
| start_date | string | 否 | 开始日期 (YYYY-MM-DD) |
| end_date | string | 否 | 结束日期 (YYYY-MM-DD) |
| limit | int | 否 | 返回条数，默认 50 |
| offset | int | 否 | 偏移量，默认 0 |

**响应示例:**
```json
{
    "status": 1,
    "message": "获取同步日志成功",
    "data": {
        "total": 156,
        "logs": [
            {
                "id": 1,
                "sync_type": "departments",
                "sync_status": "completed",
                "success_count": 15,
                "error_count": 0,
                "duration": 28,
                "started_at": "2024-08-29 09:00:00",
                "completed_at": "2024-08-29 09:00:28",
                "error_details": null
            },
            {
                "id": 2,
                "sync_type": "users",
                "sync_status": "completed",
                "success_count": 125,
                "error_count": 3,
                "duration": 65,
                "started_at": "2024-08-29 09:01:00",
                "completed_at": "2024-08-29 09:02:05",
                "error_details": "3 users failed to match"
            }
        ]
    }
}
```

### 停止同步

停止正在进行的同步操作。

```http
POST /02.php/app/controller/WeComAdmin.php?action=StopSync
```

**响应示例:**
```json
{
    "status": 1,
    "message": "同步已停止",
    "data": {
        "stopped_at": "2024-08-29 10:38:00",
        "partial_results": {
            "departments_synced": 8,
            "users_synced": 45
        }
    }
}
```

## 通知管理 API

### 获取通知规则列表

获取所有通知规则。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetNotificationRules
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| repo_name | string | 否 | 仓库名称过滤 |
| event_type | string | 否 | 事件类型过滤 |
| is_enabled | int | 否 | 启用状态：1=启用，0=禁用 |

**响应示例:**
```json
{
    "status": 1,
    "message": "获取通知规则成功",
    "data": {
        "total": 5,
        "rules": [
            {
                "id": 1,
                "rule_name": "技术部提交通知",
                "repo_name": "project_repo",
                "event_type": "commit",
                "webhook_url": "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx",
                "message_template": "📝 代码提交\\n仓库: {repository}\\n作者: {author}",
                "path_filter": "/trunk,/branches",
                "user_filter": "zhangsan,lisi",
                "is_enabled": 1,
                "created_at": "2024-08-29 08:00:00",
                "updated_at": "2024-08-29 09:00:00"
            }
        ]
    }
}
```

### 创建通知规则

创建新的通知规则。

```http
POST /02.php/app/controller/WeComAdmin.php?action=CreateNotificationRule
Content-Type: application/json

{
    "rule_name": "产品部提交通知",
    "repo_name": "product_repo",
    "event_type": "commit",
    "webhook_url": "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=yyy",
    "message_template": "🚀 产品更新\\n仓库: {repository}\\n作者: {author}\\n版本: {revision}",
    "path_filter": "/trunk/product",
    "user_filter": "",
    "is_enabled": 1
}
```

**请求参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| rule_name | string | 是 | 规则名称 |
| repo_name | string | 是 | 仓库名称，* 表示所有仓库 |
| event_type | string | 是 | 事件类型：commit/delete/revprop-change |
| webhook_url | string | 是 | Webhook 地址 |
| message_template | string | 是 | 消息模板 |
| path_filter | string | 否 | 路径过滤，逗号分隔 |
| user_filter | string | 否 | 用户过滤，逗号分隔 |
| is_enabled | int | 否 | 是否启用，默认 1 |

**响应示例:**
```json
{
    "status": 1,
    "message": "通知规则创建成功",
    "data": {
        "rule_id": 6,
        "rule_name": "产品部提交通知",
        "created_at": "2024-08-29 10:40:00"
    }
}
```

### 更新通知规则

更新现有通知规则。

```http
POST /02.php/app/controller/WeComAdmin.php?action=UpdateNotificationRule
Content-Type: application/json

{
    "rule_id": 6,
    "rule_name": "产品部提交通知（更新）",
    "is_enabled": 0
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "通知规则更新成功",
    "data": {
        "rule_id": 6,
        "updated_fields": ["rule_name", "is_enabled"],
        "updated_at": "2024-08-29 10:45:00"
    }
}
```

### 删除通知规则

删除指定的通知规则。

```http
POST /02.php/app/controller/WeComAdmin.php?action=DeleteNotificationRule
Content-Type: application/json

{
    "rule_id": 6
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "通知规则删除成功",
    "data": {
        "rule_id": 6,
        "deleted_at": "2024-08-29 10:50:00"
    }
}
```

### 发送测试通知

发送测试通知消息。

```http
POST /02.php/app/controller/WeComAdmin.php?action=SendTestNotification
Content-Type: application/json

{
    "rule_id": 1,
    "test_data": {
        "repository": "test_repo",
        "author": "testuser",
        "revision": "1001",
        "message": "测试提交消息"
    }
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "测试通知发送成功",
    "data": {
        "rule_id": 1,
        "webhook_url": "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx",
        "sent_at": "2024-08-29 10:55:00",
        "response_time": 234,
        "webhook_response": {
            "errcode": 0,
            "errmsg": "ok"
        }
    }
}
```

### 获取通知统计

获取通知发送统计信息。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetNotificationStats
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 否 | 开始日期 (YYYY-MM-DD) |
| end_date | string | 否 | 结束日期 (YYYY-MM-DD) |
| rule_id | int | 否 | 规则 ID 过滤 |

**响应示例:**
```json
{
    "status": 1,
    "message": "获取通知统计成功",
    "data": {
        "total_sent": 1256,
        "success_count": 1198,
        "failed_count": 58,
        "success_rate": 95.38,
        "by_event_type": {
            "commit": 1089,
            "delete": 156,
            "revprop-change": 11
        },
        "by_rule": [
            {
                "rule_id": 1,
                "rule_name": "技术部提交通知",
                "sent_count": 856,
                "success_count": 834,
                "failed_count": 22
            }
        ],
        "recent_failures": [
            {
                "rule_id": 2,
                "failed_at": "2024-08-29 09:30:00",
                "error_message": "webhook timeout"
            }
        ]
    }
}
```

## 用户映射 API

### 获取用户映射状态

获取企业微信用户与 SVN 用户的映射状态。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetUserMappings
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| match_status | string | 否 | 匹配状态：matched/unmatched/all |
| department_id | int | 否 | 部门 ID 过滤 |
| limit | int | 否 | 返回条数，默认 50 |
| offset | int | 否 | 偏移量，默认 0 |

**响应示例:**
```json
{
    "status": 1,
    "message": "获取用户映射成功",
    "data": {
        "total": 128,
        "matched": 95,
        "unmatched": 33,
        "mappings": [
            {
                "id": 1,
                "wecom_userid": "zhangsan",
                "wecom_name": "张三",
                "wecom_email": "zhangsan@company.com",
                "wecom_mobile": "13800138000",
                "svn_username": "zhang.san",
                "svn_user_id": 15,
                "match_status": "matched",
                "match_type": "email",
                "department_names": ["技术部", "研发中心"],
                "is_active": 1,
                "created_at": "2024-08-29 08:00:00",
                "updated_at": "2024-08-29 09:00:00"
            },
            {
                "id": 2,
                "wecom_userid": "lisi",
                "wecom_name": "李四",
                "wecom_email": "lisi@company.com",
                "wecom_mobile": "13800138001",
                "svn_username": null,
                "svn_user_id": null,
                "match_status": "unmatched",
                "match_type": null,
                "department_names": ["产品部"],
                "is_active": 1,
                "created_at": "2024-08-29 08:00:00",
                "updated_at": "2024-08-29 09:00:00"
            }
        ]
    }
}
```

### 创建用户映射

手动创建用户映射关系。

```http
POST /02.php/app/controller/WeComAdmin.php?action=CreateUserMapping
Content-Type: application/json

{
    "wecom_userid": "lisi",
    "svn_username": "li.si"
}
```

**请求参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| wecom_userid | string | 是 | 企业微信用户 ID |
| svn_username | string | 是 | SVN 用户名 |

**响应示例:**
```json
{
    "status": 1,
    "message": "用户映射创建成功",
    "data": {
        "mapping_id": 129,
        "wecom_userid": "lisi",
        "svn_username": "li.si",
        "created_at": "2024-08-29 11:00:00"
    }
}
```

### 删除用户映射

删除用户映射关系。

```http
POST /02.php/app/controller/WeComAdmin.php?action=DeleteUserMapping
Content-Type: application/json

{
    "mapping_id": 129
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "用户映射删除成功",
    "data": {
        "mapping_id": 129,
        "deleted_at": "2024-08-29 11:05:00"
    }
}
```

### 批量导入用户映射

批量导入用户映射关系。

```http
POST /02.php/app/controller/WeComAdmin.php?action=ImportUserMappings
Content-Type: application/json

{
    "mappings": [
        {
            "wecom_userid": "wangwu",
            "svn_username": "wang.wu"
        },
        {
            "wecom_userid": "zhaoliu",
            "svn_username": "zhao.liu"
        }
    ],
    "overwrite": false
}
```

**请求参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| mappings | array | 是 | 映射关系数组 |
| overwrite | bool | 否 | 是否覆盖已存在的映射 |

**响应示例:**
```json
{
    "status": 1,
    "message": "批量导入完成",
    "data": {
        "total": 2,
        "success": 2,
        "failed": 0,
        "imported_at": "2024-08-29 11:10:00",
        "results": [
            {
                "wecom_userid": "wangwu",
                "status": "success",
                "mapping_id": 130
            },
            {
                "wecom_userid": "zhaoliu",
                "status": "success",
                "mapping_id": 131
            }
        ]
    }
}
```

### 导出用户映射

导出用户映射关系。

```http
GET /02.php/app/controller/WeComAdmin.php?action=ExportUserMappings
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| format | string | 否 | 导出格式：json/csv，默认 json |
| match_status | string | 否 | 匹配状态过滤 |

**响应示例 (JSON 格式):**
```json
{
    "status": 1,
    "message": "导出成功",
    "data": {
        "export_time": "2024-08-29 11:15:00",
        "total_records": 128,
        "mappings": [
            {
                "wecom_userid": "zhangsan",
                "wecom_name": "张三",
                "svn_username": "zhang.san",
                "match_status": "matched"
            }
        ]
    }
}
```

## 监控统计 API

### 获取系统状态

获取企业微信集成系统状态。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetSystemStatus
```

**响应示例:**
```json
{
    "status": 1,
    "message": "获取系统状态成功",
    "data": {
        "overall_status": "healthy",
        "services": {
            "wecom_api": {
                "status": "online",
                "last_check": "2024-08-29 11:20:00",
                "response_time": 156
            },
            "sync_daemon": {
                "status": "running",
                "pid": 12345,
                "uptime": 86400,
                "last_sync": "2024-08-29 10:00:00"
            },
            "notification_daemon": {
                "status": "running",
                "pid": 12346,
                "uptime": 86400,
                "queue_size": 5
            }
        },
        "statistics": {
            "total_departments": 15,
            "total_users": 128,
            "matched_users": 95,
            "active_rules": 5,
            "notifications_today": 45
        },
        "health_checks": {
            "database": "ok",
            "config_file": "ok",
            "log_directory": "ok",
            "permissions": "ok"
        }
    }
}
```

### 获取性能指标

获取系统性能指标。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetPerformanceMetrics
```

**响应示例:**
```json
{
    "status": 1,
    "message": "获取性能指标成功",
    "data": {
        "sync_performance": {
            "avg_department_sync_time": 28.5,
            "avg_user_sync_time": 65.2,
            "avg_permission_sync_time": 42.1,
            "sync_success_rate": 98.5
        },
        "notification_performance": {
            "avg_send_time": 234,
            "send_success_rate": 95.8,
            "queue_processing_rate": 120
        },
        "api_performance": {
            "avg_response_time": 156,
            "api_success_rate": 99.2,
            "rate_limit_usage": 45.6
        },
        "resource_usage": {
            "memory_usage": "128MB",
            "cpu_usage": "5.2%",
            "disk_usage": "2.1GB"
        }
    }
}
```

### 获取错误日志

获取系统错误日志。

```http
GET /02.php/app/controller/WeComAdmin.php?action=GetErrorLogs
```

**查询参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| log_level | string | 否 | 日志级别：ERROR/WARN/INFO |
| start_date | string | 否 | 开始日期 |
| end_date | string | 否 | 结束日期 |
| limit | int | 否 | 返回条数 |

**响应示例:**
```json
{
    "status": 1,
    "message": "获取错误日志成功",
    "data": {
        "total": 23,
        "logs": [
            {
                "id": 1,
                "log_level": "ERROR",
                "message": "企业微信 API 调用失败",
                "details": "HTTP 500 Internal Server Error",
                "source": "WeComAPI::getDepartments",
                "created_at": "2024-08-29 09:15:00"
            },
            {
                "id": 2,
                "log_level": "WARN",
                "message": "用户同步部分失败",
                "details": "3 users failed to match",
                "source": "WeComSync::syncUsers",
                "created_at": "2024-08-29 09:01:30"
            }
        ]
    }
}
```

## 系统管理 API

### 清理日志

清理过期的日志文件。

```http
POST /02.php/app/controller/WeComAdmin.php?action=CleanupLogs
Content-Type: application/json

{
    "days_to_keep": 30,
    "log_types": ["sync", "notification", "error"]
}
```

**响应示例:**
```json
{
    "status": 1,
    "message": "日志清理完成",
    "data": {
        "cleaned_files": 15,
        "freed_space": "256MB",
        "cleanup_time": "2024-08-29 11:25:00"
    }
}
```

### 重启服务

重启企业微信集成服务。

```http
POST /02.php/app/controller/WeComAdmin.php?action=RestartServices
```

**响应示例:**
```json
{
    "status": 1,
    "message": "服务重启成功",
    "data": {
        "restarted_services": ["sync_daemon", "notification_daemon"],
        "restart_time": "2024-08-29 11:30:00"
    }
}
```

### 备份配置

备份当前配置。

```http
POST /02.php/app/controller/WeComAdmin.php?action=BackupConfig
```

**响应示例:**
```json
{
    "status": 1,
    "message": "配置备份成功",
    "data": {
        "backup_file": "wecom_config_backup_20240829113500.php",
        "backup_path": "/path/to/backups/",
        "backup_time": "2024-08-29 11:35:00"
    }
}
```

## PHP 服务类 API

### WeComAPI 类

企业微信 API 调用服务类。

```php
<?php
require_once '02.php/app/service/WeComAPI.php';

$api = new WeComAPI();

// 获取访问令牌
$token = $api->getAccessToken();

// 获取部门列表
$departments = $api->getDepartments();

// 获取部门用户
$users = $api->getDepartmentUsers($departmentId);

// 获取用户详情
$userDetail = $api->getUserDetail($userid);

// 发送应用消息
$result = $api->sendApplicationMessage($userid, $message);

// 发送群消息
$result = $api->sendGroupMarkdownMessage($webhookUrl, $markdown);
?>
```

#### 主要方法

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| getAccessToken() | 无 | string | 获取访问令牌 |
| getDepartments() | 无 | array | 获取部门列表 |
| getDepartmentUsers($deptId) | int | array | 获取部门用户 |
| getUserDetail($userid) | string | array | 获取用户详情 |
| sendApplicationMessage($userid, $message) | string, string | array | 发送应用消息 |
| sendGroupMarkdownMessage($webhook, $markdown) | string, string | array | 发送群消息 |

### WeComSync 类

数据同步服务类。

```php
<?php
require_once '02.php/app/service/WeComSync.php';

$sync = new WeComSync();

// 同步部门
$result = $sync->syncDepartments();

// 同步用户
$result = $sync->syncUsers();

// 同步权限
$result = $sync->syncPermissions();

// 获取同步统计
$stats = $sync->getSyncStats();
?>
```

#### 主要方法

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| syncDepartments() | 无 | array | 同步部门数据 |
| syncUsers($deptId = null) | int | array | 同步用户数据 |
| syncPermissions() | 无 | array | 同步权限数据 |
| getSyncStats() | 无 | array | 获取同步统计 |

### WeComNotification 类

通知服务类。

```php
<?php
require_once '02.php/app/service/WeComNotification.php';

$notification = new WeComNotification();

// 发送 SVN 通知
$result = $notification->sendSvnNotification($eventType, $eventData);

// 批量处理通知
$result = $notification->processBatchNotifications($events);

// 获取通知统计
$stats = $notification->getNotificationStats();
?>
```

#### 主要方法

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| sendSvnNotification($type, $data) | string, array | array | 发送 SVN 通知 |
| processBatchNotifications($events) | array | array | 批量处理通知 |
| getNotificationStats($filters) | array | array | 获取通知统计 |

## Webhook 接口

### SVN 事件 Webhook

SVN 钩子脚本调用的 Webhook 接口。

```http
POST /02.php/app/script/wecom_notify.php
Content-Type: application/x-www-form-urlencoded

event_type=commit&repository=/path/to/repo&revision=1001&author=zhangsan&message=test+commit
```

**请求参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| event_type | string | 是 | 事件类型：commit/delete/revprop-change |
| repository | string | 是 | 仓库路径 |
| revision | string | 是 | 版本号 |
| author | string | 是 | 作者 |
| message | string | 否 | 提交消息 |
| changed_paths | string | 否 | 变更路径，换行分隔 |

**响应示例:**
```json
{
    "status": "success",
    "message": "通知已发送",
    "sent_count": 2,
    "failed_count": 0
}
```

### 同步完成 Webhook

同步完成后的回调接口。

```http
POST /02.php/app/script/sync_callback.php
Content-Type: application/json

{
    "sync_type": "full",
    "departments_synced": 15,
    "users_synced": 128,
    "permissions_synced": 45,
    "duration": 120,
    "status": "completed"
}
```

## SDK 示例

### JavaScript SDK

```javascript
class WeComAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }

    async request(action, data = null) {
        const url = `${this.baseUrl}?action=${action}`;
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        return await response.json();
    }

    // 获取配置
    async getConfig() {
        return await this.request('GetConfig');
    }

    // 更新配置
    async updateConfig(config) {
        return await this.request('UpdateConfig', config);
    }

    // 获取同步状态
    async getSyncStatus() {
        return await this.request('GetSyncStatus');
    }

    // 触发同步
    async syncDepartments() {
        return await this.request('SyncDepartments');
    }

    // 获取通知规则
    async getNotificationRules() {
        return await this.request('GetNotificationRules');
    }
}

// 使用示例
const api = new WeComAPI('/02.php/app/controller/WeComAdmin.php');

// 获取配置
api.getConfig().then(result => {
    console.log('配置信息:', result.data);
});

// 触发同步
api.syncDepartments().then(result => {
    console.log('同步结果:', result.message);
});
```

### Python SDK

```python
import requests
import json

class WeComAPI:
    def __init__(self, base_url):
        self.base_url = base_url
        self.session = requests.Session()
    
    def request(self, action, data=None):
        url = f"{self.base_url}?action={action}"
        
        if data:
            response = self.session.post(url, json=data)
        else:
            response = self.session.get(url)
        
        return response.json()
    
    def get_config(self):
        """获取配置"""
        return self.request('GetConfig')
    
    def update_config(self, config):
        """更新配置"""
        return self.request('UpdateConfig', config)
    
    def get_sync_status(self):
        """获取同步状态"""
        return self.request('GetSyncStatus')
    
    def sync_departments(self):
        """触发部门同步"""
        return self.request('SyncDepartments')
    
    def get_notification_rules(self):
        """获取通知规则"""
        return self.request('GetNotificationRules')

# 使用示例
api = WeComAPI('http://your-domain/02.php/app/controller/WeComAdmin.php')

# 获取配置
config = api.get_config()
print('配置信息:', config['data'])

# 触发同步
result = api.sync_departments()
print('同步结果:', result['message'])
```

### PHP SDK

```php
<?php
class WeComAPIClient {
    private $baseUrl;
    private $session;
    
    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->session = curl_init();
        curl_setopt($this->session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->session, CURLOPT_HEADER, false);
    }
    
    public function request($action, $data = null) {
        $url = $this->baseUrl . '?action=' . $action;
        
        curl_setopt($this->session, CURLOPT_URL, $url);
        
        if ($data) {
            curl_setopt($this->session, CURLOPT_POST, true);
            curl_setopt($this->session, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($this->session, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        } else {
            curl_setopt($this->session, CURLOPT_POST, false);
        }
        
        $response = curl_exec($this->session);
        return json_decode($response, true);
    }
    
    public function getConfig() {
        return $this->request('GetConfig');
    }
    
    public function updateConfig($config) {
        return $this->request('UpdateConfig', $config);
    }
    
    public function getSyncStatus() {
        return $this->request('GetSyncStatus');
    }
    
    public function syncDepartments() {
        return $this->request('SyncDepartments');
    }
    
    public function __destruct() {
        curl_close($this->session);
    }
}

// 使用示例
$api = new WeComAPIClient('http://your-domain/02.php/app/controller/WeComAdmin.php');

// 获取配置
$config = $api->getConfig();
echo '配置信息: ' . json_encode($config['data'], JSON_PRETTY_PRINT);

// 触发同步
$result = $api->syncDepartments();
echo '同步结果: ' . $result['message'];
?>
```

---

## 📞 技术支持

### 获取帮助

1. **查看完整文档**: [WECOM_INTEGRATION.md](WECOM_INTEGRATION.md)
2. **快速开始指南**: [QUICK_START.md](QUICK_START.md)
3. **部署检查清单**: [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)

### 调试工具

```bash
# 测试 API 连接
curl -X POST "http://your-domain/02.php/app/controller/WeComAdmin.php?action=TestConnection"

# 获取系统状态
curl "http://your-domain/02.php/app/controller/WeComAdmin.php?action=GetSystemStatus"

# 查看错误日志
curl "http://your-domain/02.php/app/controller/WeComAdmin.php?action=GetErrorLogs"
```

### 常见问题

**Q: API 返回 403 错误？**
A: 检查是否已登录 SVNAdmin 系统，确保具有管理员权限。

**Q: 同步 API 调用失败？**
A: 检查企业微信配置是否正确，查看系统错误日志。

**Q: 通知 API 无响应？**
A: 检查通知守护进程是否运行，验证 Webhook 地址是否正确。

---

*API 文档版本: v1.0 | 最后更新: 2024年8月29日*
