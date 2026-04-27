-- SVNAdmin Clean Database Schema
-- This creates all necessary tables without any sensitive data

-- 用户表
CREATE TABLE IF NOT EXISTS "svn_users" (
    "svn_user_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "svn_user_name" TEXT NOT NULL,
    "svn_user_pass" TEXT NOT NULL,
    "svn_user_status" INTEGER NOT NULL DEFAULT 1,
    "svn_user_last_login" TEXT DEFAULT '',
    "svn_user_note" TEXT DEFAULT '',
    "svn_user_real_name" TEXT DEFAULT '',
    "svn_user_display_name" TEXT DEFAULT '',
    "svn_user_external_id" TEXT DEFAULT '',
    "svn_user_dn" TEXT DEFAULT '',
    "svn_user_source" TEXT DEFAULT 'manual',
    "svn_user_sync_time" TEXT DEFAULT '',
    "svn_user_mail" TEXT DEFAULT ''
);

-- 分组表
CREATE TABLE IF NOT EXISTS "svn_groups" (
    "svn_group_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "svn_group_name" TEXT NOT NULL,
    "svn_group_note" TEXT DEFAULT '',
    "include_user_count" INTEGER DEFAULT 0,
    "include_group_count" INTEGER DEFAULT 0,
    "include_aliase_count" INTEGER DEFAULT 0,
    "svn_group_display_name" TEXT DEFAULT '',
    "svn_group_source" TEXT DEFAULT 'manual',
    "svn_group_external_id" TEXT DEFAULT '',
    "svn_group_dn" TEXT DEFAULT '',
    "svn_group_sync_time" TEXT DEFAULT ''
);

-- 仓库表
CREATE TABLE IF NOT EXISTS "svn_reps" (
    "rep_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "rep_name" TEXT NOT NULL,
    "rep_size" TEXT DEFAULT '0',
    "rep_note" TEXT DEFAULT '',
    "rep_rev" INTEGER DEFAULT 0
);

-- 仓库路径权限表
CREATE TABLE IF NOT EXISTS "svn_rep_paths" (
    "svn_rep_path_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "rep_name" TEXT NOT NULL,
    "svn_rep_path" TEXT NOT NULL,
    "svn_pri_path_id" INTEGER NOT NULL
);

-- 权限路径表
CREATE TABLE IF NOT EXISTS "svn_pri_paths" (
    "svn_pri_path_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "svn_object_type" TEXT NOT NULL,
    "svn_object_name" TEXT NOT NULL,
    "svn_object_pri" TEXT NOT NULL
);

-- 管理员表
CREATE TABLE IF NOT EXISTS "svn_admins" (
    "svn_admin_id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "svn_admin_name" TEXT NOT NULL,
    "svn_admin_pass" TEXT NOT NULL,
    "svn_admin_role" INTEGER NOT NULL DEFAULT 2,
    "svn_admin_status" INTEGER NOT NULL DEFAULT 1,
    "svn_admin_note" TEXT DEFAULT ''
);

-- 企业微信配置表
CREATE TABLE IF NOT EXISTS "wecom_config" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "corp_id" TEXT DEFAULT '',
    "agent_id" TEXT DEFAULT '',
    "secret" TEXT DEFAULT '',
    "contact_secret" TEXT DEFAULT '',
    "access_token" TEXT DEFAULT '',
    "contact_access_token" TEXT DEFAULT '',
    "token_expires_at" INTEGER DEFAULT 0,
    "contact_token_expires_at" INTEGER DEFAULT 0,
    "sync_enabled" INTEGER DEFAULT 0,
    "sync_interval" INTEGER DEFAULT 3600,
    "last_sync_time" TEXT DEFAULT '',
    "notification_enabled" INTEGER DEFAULT 0,
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信部门表
CREATE TABLE IF NOT EXISTS "wecom_departments" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "wecom_dept_id" INTEGER NOT NULL,
    "dept_name" TEXT NOT NULL,
    "parent_id" INTEGER DEFAULT 0,
    "dept_order" INTEGER DEFAULT 0,
    "svn_group_id" INTEGER DEFAULT 0,
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信用户表
CREATE TABLE IF NOT EXISTS "wecom_users" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "wecom_user_id" TEXT NOT NULL,
    "svn_username" TEXT DEFAULT '',
    "svn_user_id" INTEGER DEFAULT 0,
    "real_name" TEXT NOT NULL DEFAULT '',
    "name_en" TEXT DEFAULT '',
    "mobile" TEXT DEFAULT '',
    "email" TEXT DEFAULT '',
    "gender" TEXT DEFAULT '',
    "avatar" TEXT DEFAULT '',
    "status" INTEGER DEFAULT 1,
    "department_ids" TEXT DEFAULT '[]',
    "position" TEXT DEFAULT '',
    "is_leader_in_dept" TEXT DEFAULT '[]',
    "direct_leader" TEXT DEFAULT '[]',
    "is_active" INTEGER NOT NULL DEFAULT 1,
    "match_status" TEXT NOT NULL DEFAULT 'pending',
    "match_field" TEXT DEFAULT '',
    "match_value" TEXT DEFAULT '',
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信同步日志表
CREATE TABLE IF NOT EXISTS "wecom_sync_logs" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "sync_type" TEXT NOT NULL,
    "sync_status" TEXT NOT NULL,
    "start_time" TEXT DEFAULT CURRENT_TIMESTAMP,
    "end_time" TEXT DEFAULT '',
    "dept_total" INTEGER DEFAULT 0,
    "dept_synced" INTEGER DEFAULT 0,
    "user_total" INTEGER DEFAULT 0,
    "user_synced" INTEGER DEFAULT 0,
    "error_count" INTEGER DEFAULT 0,
    "summary" TEXT DEFAULT '',
    "error_details" TEXT DEFAULT '',
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "level" TEXT DEFAULT 'info',
    "message" TEXT DEFAULT '',
    "context" TEXT DEFAULT ''
);

-- 企业微信API日志表
CREATE TABLE IF NOT EXISTS "wecom_api_logs" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "api_name" TEXT NOT NULL,
    "request_method" TEXT NOT NULL,
    "request_url" TEXT NOT NULL,
    "request_params" TEXT DEFAULT '',
    "response_code" INTEGER DEFAULT 0,
    "response_data" TEXT DEFAULT '',
    "error_msg" TEXT DEFAULT '',
    "execution_time" REAL DEFAULT 0,
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信通知规则表
CREATE TABLE IF NOT EXISTS "wecom_notification_rules" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "rule_name" TEXT NOT NULL,
    "description" TEXT DEFAULT '',
    "is_enabled" INTEGER DEFAULT 1,
    "notification_type" TEXT NOT NULL DEFAULT 'wecom',
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信通知日志表
CREATE TABLE IF NOT EXISTS "wecom_notification_logs" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "notification_type" TEXT NOT NULL,
    "recipient" TEXT NOT NULL,
    "subject" TEXT DEFAULT '',
    "content" TEXT NOT NULL,
    "status" TEXT NOT NULL,
    "error_msg" TEXT DEFAULT '',
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 企业微信通知队列表
CREATE TABLE IF NOT EXISTS "wecom_notification_queue" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT,
    "notification_type" TEXT NOT NULL,
    "recipient" TEXT NOT NULL,
    "subject" TEXT DEFAULT '',
    "content" TEXT NOT NULL,
    "priority" INTEGER DEFAULT 0,
    "retry_count" INTEGER DEFAULT 0,
    "max_retries" INTEGER DEFAULT 3,
    "status" TEXT DEFAULT 'pending',
    "error_msg" TEXT DEFAULT '',
    "scheduled_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 插入默认管理员账户（密码需要在首次登录时修改）
INSERT INTO "svn_admins" ("svn_admin_name", "svn_admin_pass", "svn_admin_role", "svn_admin_status", "svn_admin_note") 
VALUES ('admin', 'd033e22ae348aeb5660fc2140aec35850c4da997', 1, 1, 'Default administrator account');

-- 插入默认企业微信配置
INSERT INTO "wecom_config" ("corp_id", "agent_id", "sync_enabled", "notification_enabled") 
VALUES ('', '', 0, 0);

-- 创建索引以提高查询性能
CREATE INDEX IF NOT EXISTS "idx_svn_users_name" ON "svn_users" ("svn_user_name");
CREATE INDEX IF NOT EXISTS "idx_svn_groups_name" ON "svn_groups" ("svn_group_name");
CREATE INDEX IF NOT EXISTS "idx_svn_reps_name" ON "svn_reps" ("rep_name");
CREATE INDEX IF NOT EXISTS "idx_wecom_departments_dept_id" ON "wecom_departments" ("wecom_dept_id");
CREATE INDEX IF NOT EXISTS "idx_wecom_users_user_id" ON "wecom_users" ("wecom_user_id");
CREATE INDEX IF NOT EXISTS "idx_wecom_sync_logs_created" ON "wecom_sync_logs" ("created_at");
