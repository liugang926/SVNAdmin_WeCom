-- 创建与后端代码匹配的通知规则表
-- 这个表结构匹配 WeComNotification.php 服务中期望的字段名

DROP TABLE IF EXISTS "wecom_notification_rules_new";
CREATE TABLE "wecom_notification_rules_new" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "rule_name" TEXT(255) NOT NULL,
  "repo_name" TEXT(500) DEFAULT '*',
  "path_prefix" TEXT(500) DEFAULT '/',
  "event_type" TEXT(255) NOT NULL,
  "webhook_url" TEXT(500) DEFAULT '',
  "message_template" TEXT DEFAULT '',
  "notify_wecom_userids" TEXT DEFAULT '',
  "notify_wecom_deptids" TEXT DEFAULT '',
  "enable" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime')),
  "updated_at" TEXT(45) NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 如果存在旧表，先备份数据（如果有的话）
-- INSERT INTO "wecom_notification_rules_new" (rule_name, repo_name, event_type, enable)
-- SELECT rule_name, repo_path, events, is_enabled FROM "wecom_notification_rules";

-- 删除旧表并重命名新表
DROP TABLE IF EXISTS "wecom_notification_rules";
ALTER TABLE "wecom_notification_rules_new" RENAME TO "wecom_notification_rules";

-- 创建索引
CREATE INDEX "idx_wecom_notification_rules_enabled" ON "wecom_notification_rules" ("enable");
CREATE INDEX "idx_wecom_notification_rules_repo" ON "wecom_notification_rules" ("repo_name");
CREATE INDEX "idx_wecom_notification_rules_event" ON "wecom_notification_rules" ("event_type");
