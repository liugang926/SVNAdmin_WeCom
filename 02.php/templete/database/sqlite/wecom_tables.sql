/*
 企业微信集成功能数据库表结构 - SQLite 版本
 
 Author: witersen
 Description: 企业微信集成所需的数据表结构
 Date: 2025-08-29
*/

PRAGMA foreign_keys = false;

-- ----------------------------
-- Table structure for wecom_config
-- ----------------------------
DROP TABLE IF EXISTS "wecom_config";
CREATE TABLE "wecom_config" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "corp_id" TEXT(255) NOT NULL DEFAULT '',
  "corp_secret" TEXT(255) NOT NULL DEFAULT '',
  "agent_id" TEXT(255) NOT NULL DEFAULT '',
  "access_token" TEXT(512) DEFAULT '',
  "token_expires_at" INTEGER DEFAULT 0,
  "sync_enabled" INTEGER NOT NULL DEFAULT 1,
  "sync_interval" INTEGER NOT NULL DEFAULT 3600,
  "last_sync_time" TEXT(45) DEFAULT '',
  "last_full_sync_time" TEXT(45) DEFAULT '',
  "notification_enabled" INTEGER NOT NULL DEFAULT 1,
  "default_chat_id" TEXT(255) DEFAULT '',
  "config_data" TEXT DEFAULT '{}',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- ----------------------------
-- Records of wecom_config
-- ----------------------------
INSERT INTO "wecom_config" ("id", "sync_enabled", "notification_enabled") VALUES (1, 0, 0);

-- ----------------------------
-- Table structure for wecom_departments
-- ----------------------------
DROP TABLE IF EXISTS "wecom_departments";
CREATE TABLE "wecom_departments" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "wecom_dept_id" TEXT(255) NOT NULL,
  "dept_name" TEXT(255) NOT NULL,
  "dept_name_en" TEXT(255) DEFAULT '',
  "parent_id" TEXT(255) DEFAULT '0',
  "dept_order" INTEGER DEFAULT 0,
  "svn_group_name" TEXT(255) DEFAULT '',
  "svn_group_id" INTEGER DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "sync_status" TEXT(20) NOT NULL DEFAULT 'pending',
  "last_sync_time" TEXT(45) DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  UNIQUE("wecom_dept_id")
);

-- ----------------------------
-- Table structure for wecom_users
-- ----------------------------
DROP TABLE IF EXISTS "wecom_users";
CREATE TABLE "wecom_users" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "wecom_user_id" TEXT(255) NOT NULL,
  "svn_username" TEXT(255) DEFAULT '',
  "svn_user_id" INTEGER DEFAULT 0,
  "real_name" TEXT(255) NOT NULL DEFAULT '',
  "name_en" TEXT(255) DEFAULT '',
  "mobile" TEXT(20) DEFAULT '',
  "email" TEXT(255) DEFAULT '',
  "gender" TEXT(10) DEFAULT '',
  "avatar" TEXT(500) DEFAULT '',
  "status" INTEGER DEFAULT 1,
  "department_ids" TEXT DEFAULT '[]',
  "position" TEXT(255) DEFAULT '',
  "is_leader_in_dept" TEXT DEFAULT '[]',
  "direct_leader" TEXT DEFAULT '[]',
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "match_status" TEXT(20) NOT NULL DEFAULT 'pending',
  "match_field" TEXT(50) DEFAULT '',
  "match_value" TEXT(255) DEFAULT '',
  "last_sync_time" TEXT(45) DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  UNIQUE("wecom_user_id")
);

-- ----------------------------
-- Table structure for wecom_notification_rules
-- ----------------------------
DROP TABLE IF EXISTS "wecom_notification_rules";
CREATE TABLE "wecom_notification_rules" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "rule_name" TEXT(255) NOT NULL,
  "rule_description" TEXT(500) DEFAULT '',
  "repo_path" TEXT(500) DEFAULT '*',
  "repo_pattern" TEXT(500) DEFAULT '',
  "events" TEXT NOT NULL DEFAULT '[]',
  "chat_id" TEXT(255) NOT NULL,
  "chat_type" TEXT(20) NOT NULL DEFAULT 'group',
  "message_template" TEXT DEFAULT '',
  "message_format" TEXT(20) NOT NULL DEFAULT 'markdown',
  "is_enabled" INTEGER NOT NULL DEFAULT 1,
  "priority" INTEGER NOT NULL DEFAULT 0,
  "rate_limit_enabled" INTEGER NOT NULL DEFAULT 1,
  "max_messages_per_minute" INTEGER NOT NULL DEFAULT 20,
  "merge_similar" INTEGER NOT NULL DEFAULT 1,
  "conditions" TEXT DEFAULT '{}',
  "created_by" TEXT(255) DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- ----------------------------
-- Table structure for wecom_notification_logs
-- ----------------------------
DROP TABLE IF EXISTS "wecom_notification_logs";
CREATE TABLE "wecom_notification_logs" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "rule_id" INTEGER NOT NULL,
  "repo_name" TEXT(255) NOT NULL,
  "event_type" TEXT(50) NOT NULL,
  "author" TEXT(255) NOT NULL,
  "message" TEXT DEFAULT '',
  "files_changed" TEXT DEFAULT '',
  "chat_id" TEXT(255) NOT NULL,
  "notification_content" TEXT NOT NULL,
  "send_status" TEXT(20) NOT NULL DEFAULT 'pending',
  "response_data" TEXT DEFAULT '',
  "error_message" TEXT DEFAULT '',
  "retry_count" INTEGER NOT NULL DEFAULT 0,
  "sent_at" TEXT(45) DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  FOREIGN KEY ("rule_id") REFERENCES "wecom_notification_rules" ("id") ON DELETE CASCADE
);

-- ----------------------------
-- Table structure for wecom_sync_logs
-- ----------------------------
DROP TABLE IF EXISTS "wecom_sync_logs";
CREATE TABLE "wecom_sync_logs" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sync_type" TEXT(20) NOT NULL,
  "sync_status" TEXT(20) NOT NULL DEFAULT 'running',
  "start_time" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "end_time" TEXT(45) DEFAULT '',
  "duration" INTEGER DEFAULT 0,
  "departments_total" INTEGER DEFAULT 0,
  "departments_synced" INTEGER DEFAULT 0,
  "users_total" INTEGER DEFAULT 0,
  "users_synced" INTEGER DEFAULT 0,
  "users_matched" INTEGER DEFAULT 0,
  "errors_count" INTEGER DEFAULT 0,
  "error_details" TEXT DEFAULT '',
  "summary" TEXT DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- ----------------------------
-- Table structure for wecom_api_logs
-- ----------------------------
DROP TABLE IF EXISTS "wecom_api_logs";
CREATE TABLE "wecom_api_logs" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "api_method" TEXT(50) NOT NULL,
  "api_url" TEXT(500) NOT NULL,
  "request_data" TEXT DEFAULT '',
  "response_code" INTEGER DEFAULT 0,
  "response_data" TEXT DEFAULT '',
  "response_time" INTEGER DEFAULT 0,
  "error_message" TEXT DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- ----------------------------
-- Create indexes for better performance
-- ----------------------------
CREATE INDEX "idx_wecom_departments_parent" ON "wecom_departments" ("parent_id");
CREATE INDEX "idx_wecom_departments_active" ON "wecom_departments" ("is_active");
CREATE INDEX "idx_wecom_users_status" ON "wecom_users" ("is_active", "match_status");
CREATE INDEX "idx_wecom_users_svn" ON "wecom_users" ("svn_username", "svn_user_id");
CREATE INDEX "idx_wecom_notification_rules_enabled" ON "wecom_notification_rules" ("is_enabled");
CREATE INDEX "idx_wecom_notification_logs_status" ON "wecom_notification_logs" ("send_status");
CREATE INDEX "idx_wecom_notification_logs_created" ON "wecom_notification_logs" ("created_at");
CREATE INDEX "idx_wecom_sync_logs_type_status" ON "wecom_sync_logs" ("sync_type", "sync_status");
CREATE INDEX "idx_wecom_api_logs_created" ON "wecom_api_logs" ("created_at");

-- ----------------------------
-- Table structure for wecom_notification_queue
-- ----------------------------
DROP TABLE IF EXISTS "wecom_notification_queue";
CREATE TABLE "wecom_notification_queue" (
  "queue_id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "notification_type" TEXT(50) NOT NULL,
  "event_data" TEXT NOT NULL,
  "webhook_url" TEXT DEFAULT '',
  "message_template" TEXT DEFAULT '',
  "status" TEXT(20) NOT NULL DEFAULT 'pending',
  "retry_count" INTEGER NOT NULL DEFAULT 0,
  "max_retries" INTEGER NOT NULL DEFAULT 3,
  "next_retry_time" TEXT(45) DEFAULT '',
  "last_error" TEXT DEFAULT '',
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "completed_at" TEXT(45) DEFAULT ''
);

-- 为通知队列表创建索引
CREATE INDEX "idx_wecom_notification_queue_status" ON "wecom_notification_queue" ("status");
CREATE INDEX "idx_wecom_notification_queue_type" ON "wecom_notification_queue" ("notification_type");
CREATE INDEX "idx_wecom_notification_queue_retry_time" ON "wecom_notification_queue" ("next_retry_time");
CREATE INDEX "idx_wecom_notification_queue_created_at" ON "wecom_notification_queue" ("created_at");

PRAGMA foreign_keys = true;
