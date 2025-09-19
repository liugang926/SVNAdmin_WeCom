/*
 企业微信集成功能数据库表结构 - MySQL 版本
 
 Author: witersen
 Description: 企业微信集成所需的数据表结构
 Date: 2025-08-29
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for wecom_config
-- ----------------------------
DROP TABLE IF EXISTS `wecom_config`;
CREATE TABLE `wecom_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `corp_id` varchar(255) NOT NULL DEFAULT '' COMMENT '企业微信企业ID',
  `corp_secret` varchar(255) NOT NULL DEFAULT '' COMMENT '企业微信应用密钥',
  `agent_id` varchar(255) NOT NULL DEFAULT '' COMMENT '企业微信应用ID',
  `access_token` varchar(512) DEFAULT '' COMMENT '访问令牌',
  `token_expires_at` int(11) DEFAULT 0 COMMENT '令牌过期时间戳',
  `sync_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用同步',
  `sync_interval` int(11) NOT NULL DEFAULT 3600 COMMENT '同步间隔（秒）',
  `last_sync_time` datetime DEFAULT NULL COMMENT '最后同步时间',
  `last_full_sync_time` datetime DEFAULT NULL COMMENT '最后全量同步时间',
  `notification_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用通知',
  `default_chat_id` varchar(255) DEFAULT '' COMMENT '默认通知群ID',
  `config_data` json DEFAULT NULL COMMENT '扩展配置数据',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信配置表';

-- ----------------------------
-- Records of wecom_config
-- ----------------------------
INSERT INTO `wecom_config` (`id`, `sync_enabled`, `notification_enabled`) VALUES (1, 0, 0);

-- ----------------------------
-- Table structure for wecom_departments
-- ----------------------------
DROP TABLE IF EXISTS `wecom_departments`;
CREATE TABLE `wecom_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wecom_dept_id` varchar(255) NOT NULL COMMENT '企业微信部门ID',
  `dept_name` varchar(255) NOT NULL COMMENT '部门名称',
  `dept_name_en` varchar(255) DEFAULT '' COMMENT '部门英文名称',
  `parent_id` varchar(255) DEFAULT '0' COMMENT '父部门ID',
  `dept_order` int(11) DEFAULT 0 COMMENT '部门排序',
  `svn_group_name` varchar(255) DEFAULT '' COMMENT '对应的SVN用户组名',
  `svn_group_id` int(11) DEFAULT 0 COMMENT '对应的SVN用户组ID',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否激活',
  `sync_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '同步状态',
  `last_sync_time` datetime DEFAULT NULL COMMENT '最后同步时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wecom_dept_id` (`wecom_dept_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信部门表';

-- ----------------------------
-- Table structure for wecom_users
-- ----------------------------
DROP TABLE IF EXISTS `wecom_users`;
CREATE TABLE `wecom_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wecom_user_id` varchar(255) NOT NULL COMMENT '企业微信用户ID',
  `svn_username` varchar(255) DEFAULT '' COMMENT '对应的SVN用户名',
  `svn_user_id` int(11) DEFAULT 0 COMMENT '对应的SVN用户ID',
  `real_name` varchar(255) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `name_en` varchar(255) DEFAULT '' COMMENT '英文名',
  `mobile` varchar(20) DEFAULT '' COMMENT '手机号',
  `email` varchar(255) DEFAULT '' COMMENT '邮箱',
  `gender` varchar(10) DEFAULT '' COMMENT '性别',
  `avatar` varchar(500) DEFAULT '' COMMENT '头像URL',
  `status` tinyint(1) DEFAULT 1 COMMENT '账号状态',
  `department_ids` json DEFAULT NULL COMMENT '所属部门ID列表',
  `position` varchar(255) DEFAULT '' COMMENT '职位',
  `is_leader_in_dept` json DEFAULT NULL COMMENT '在部门中是否为领导',
  `direct_leader` json DEFAULT NULL COMMENT '直属领导',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否激活',
  `match_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '匹配状态',
  `match_field` varchar(50) DEFAULT '' COMMENT '匹配字段',
  `match_value` varchar(255) DEFAULT '' COMMENT '匹配值',
  `last_sync_time` datetime DEFAULT NULL COMMENT '最后同步时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wecom_user_id` (`wecom_user_id`),
  KEY `idx_svn_username` (`svn_username`),
  KEY `idx_match_status` (`is_active`, `match_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信用户表';

-- ----------------------------
-- Table structure for wecom_notification_rules
-- ----------------------------
DROP TABLE IF EXISTS `wecom_notification_rules`;
CREATE TABLE `wecom_notification_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(255) NOT NULL COMMENT '规则名称',
  `rule_description` varchar(500) DEFAULT '' COMMENT '规则描述',
  `repo_path` varchar(500) DEFAULT '*' COMMENT '仓库路径',
  `repo_pattern` varchar(500) DEFAULT '' COMMENT '仓库路径模式',
  `events` json NOT NULL COMMENT '监听的事件类型',
  `chat_id` varchar(255) NOT NULL COMMENT '企业微信群ID',
  `chat_type` varchar(20) NOT NULL DEFAULT 'group' COMMENT '聊天类型',
  `message_template` text DEFAULT NULL COMMENT '消息模板',
  `message_format` varchar(20) NOT NULL DEFAULT 'markdown' COMMENT '消息格式',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT '优先级',
  `rate_limit_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用频率限制',
  `max_messages_per_minute` int(11) NOT NULL DEFAULT 20 COMMENT '每分钟最大消息数',
  `merge_similar` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否合并相似消息',
  `conditions` json DEFAULT NULL COMMENT '附加条件',
  `created_by` varchar(255) DEFAULT '' COMMENT '创建者',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_is_enabled` (`is_enabled`),
  KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信通知规则表';

-- ----------------------------
-- Table structure for wecom_notification_logs
-- ----------------------------
DROP TABLE IF EXISTS `wecom_notification_logs`;
CREATE TABLE `wecom_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(11) NOT NULL COMMENT '规则ID',
  `repo_name` varchar(255) NOT NULL COMMENT '仓库名称',
  `event_type` varchar(50) NOT NULL COMMENT '事件类型',
  `author` varchar(255) NOT NULL COMMENT '操作者',
  `message` text DEFAULT NULL COMMENT '提交信息',
  `files_changed` text DEFAULT NULL COMMENT '变更文件',
  `chat_id` varchar(255) NOT NULL COMMENT '群聊ID',
  `notification_content` text NOT NULL COMMENT '通知内容',
  `send_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '发送状态',
  `response_data` text DEFAULT NULL COMMENT '响应数据',
  `error_message` text DEFAULT NULL COMMENT '错误信息',
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT '重试次数',
  `sent_at` datetime DEFAULT NULL COMMENT '发送时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_rule_id` (`rule_id`),
  KEY `idx_send_status` (`send_status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_notification_logs_rule` FOREIGN KEY (`rule_id`) REFERENCES `wecom_notification_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信通知日志表';

-- ----------------------------
-- Table structure for wecom_sync_logs
-- ----------------------------
DROP TABLE IF EXISTS `wecom_sync_logs`;
CREATE TABLE `wecom_sync_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_type` varchar(20) NOT NULL COMMENT '同步类型',
  `sync_status` varchar(20) NOT NULL DEFAULT 'running' COMMENT '同步状态',
  `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `duration` int(11) DEFAULT 0 COMMENT '持续时间（秒）',
  `departments_total` int(11) DEFAULT 0 COMMENT '部门总数',
  `departments_synced` int(11) DEFAULT 0 COMMENT '已同步部门数',
  `users_total` int(11) DEFAULT 0 COMMENT '用户总数',
  `users_synced` int(11) DEFAULT 0 COMMENT '已同步用户数',
  `users_matched` int(11) DEFAULT 0 COMMENT '已匹配用户数',
  `errors_count` int(11) DEFAULT 0 COMMENT '错误数量',
  `error_details` text DEFAULT NULL COMMENT '错误详情',
  `summary` text DEFAULT NULL COMMENT '同步摘要',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_sync_type_status` (`sync_type`, `sync_status`),
  KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信同步日志表';

-- ----------------------------
-- Table structure for wecom_api_logs
-- ----------------------------
DROP TABLE IF EXISTS `wecom_api_logs`;
CREATE TABLE `wecom_api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_method` varchar(50) NOT NULL COMMENT 'API方法',
  `api_url` varchar(500) NOT NULL COMMENT 'API地址',
  `request_data` text DEFAULT NULL COMMENT '请求数据',
  `response_code` int(11) DEFAULT 0 COMMENT '响应状态码',
  `response_data` text DEFAULT NULL COMMENT '响应数据',
  `response_time` int(11) DEFAULT 0 COMMENT '响应时间（毫秒）',
  `error_message` text DEFAULT NULL COMMENT '错误信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_api_method` (`api_method`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信API调用日志表';

-- ----------------------------
-- Table structure for wecom_notification_queue
-- ----------------------------
DROP TABLE IF EXISTS `wecom_notification_queue`;
CREATE TABLE `wecom_notification_queue` (
  `queue_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(50) NOT NULL COMMENT '通知类型',
  `event_data` text NOT NULL COMMENT '事件数据',
  `webhook_url` text DEFAULT NULL COMMENT 'Webhook URL',
  `message_template` text DEFAULT NULL COMMENT '消息模板',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending, processing, completed, failed',
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT '重试次数',
  `max_retries` int(11) NOT NULL DEFAULT 3 COMMENT '最大重试次数',
  `next_retry_time` datetime DEFAULT NULL COMMENT '下次重试时间',
  `last_error` text DEFAULT NULL COMMENT '最后错误信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`queue_id`),
  KEY `idx_status` (`status`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_next_retry_time` (`next_retry_time`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信通知队列表';

SET FOREIGN_KEY_CHECKS = 1;
