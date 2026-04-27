<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This migration must be run from php-cli.\n");
    exit(1);
}

date_default_timezone_set('Asia/Shanghai');

const MIGRATION_VERSION = '20260425_wecom_ldap_upgrade';

function out($message)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function fail($message)
{
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function resolvePaths()
{
    $projectRoot = dirname(dirname(__DIR__));
    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . '02.php',
        $projectRoot,
        '/var/www/html',
    ];

    foreach ($candidates as $appRoot) {
        if (is_file($appRoot . '/config/database.php') && is_file($appRoot . '/config/svn.php')) {
            return [
                'projectRoot' => $projectRoot,
                'appRoot' => $appRoot,
            ];
        }
    }

    fail('Cannot locate SVNAdmin application root.');
}

function loadConfigs($appRoot)
{
    $database = require $appRoot . '/config/database.php';
    $svn = require $appRoot . '/config/svn.php';
    return [$database, $svn];
}

function sqliteDatabasePath($databaseConfig, $svnConfig)
{
    $home = $svnConfig['home_path'] ?? '/home/svnadmin/';
    $file = $databaseConfig['database_file'] ?? ($home . 'svnadmin.db');
    return strpos($file, '%s') !== false ? sprintf($file, $home) : $file;
}

function initializeSqliteDatabase($dbFile, $appRoot)
{
    if (is_file($dbFile) && filesize($dbFile) > 0) {
        return;
    }

    $dir = dirname($dbFile);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        fail("Cannot create database directory: {$dir}");
    }

    $seedDb = $appRoot . '/templete/database/sqlite/svnadmin.db';
    if (is_file($seedDb)) {
        copy($seedDb, $dbFile);
        out("Initialized SQLite database from seed: {$seedDb}");
        return;
    }

    touch($dbFile);
    out("Initialized empty SQLite database: {$dbFile}");
}

function connectDatabase($databaseConfig, $svnConfig, $appRoot)
{
    $type = strtolower($databaseConfig['database_type'] ?? 'sqlite');

    if ($type === 'sqlite') {
        $dbFile = sqliteDatabasePath($databaseConfig, $svnConfig);
        initializeSqliteDatabase($dbFile, $appRoot);
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        return [$pdo, 'sqlite', $dbFile];
    }

    if ($type === 'mysql') {
        $host = $databaseConfig['server'] ?? '127.0.0.1';
        $port = $databaseConfig['port'] ?? 3306;
        $db = $databaseConfig['database_name'] ?? '';
        $charset = $databaseConfig['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $pdo = new PDO($dsn, $databaseConfig['username'] ?? '', $databaseConfig['password'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return [$pdo, 'mysql', null];
    }

    fail("Unsupported database type: {$type}");
}

function quoteName($name, $dbType)
{
    return $dbType === 'mysql' ? "`{$name}`" : "\"{$name}\"";
}

function tableExists(PDO $pdo, $dbType, $table)
{
    if ($dbType === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $pdo, $dbType, $table, $column)
{
    if (!tableExists($pdo, $dbType, $table)) {
        return false;
    }

    if ($dbType === 'sqlite') {
        $stmt = $pdo->query('PRAGMA table_info(' . quoteName($table, $dbType) . ')');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strcasecmp($row['name'], $column) === 0) {
                return true;
            }
        }
        return false;
    }

    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . quoteName($table, $dbType) . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function addColumnIfMissing(PDO $pdo, $dbType, $table, $column, $definition)
{
    if (!tableExists($pdo, $dbType, $table)) {
        return;
    }

    if (!columnExists($pdo, $dbType, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . quoteName($table, $dbType) . ' ADD COLUMN ' . quoteName($column, $dbType) . ' ' . $definition);
        out("Added column {$table}.{$column}");
    }
}

function createTableIfMissing(PDO $pdo, $dbType, $table, $sqliteSql, $mysqlSql)
{
    if (!tableExists($pdo, $dbType, $table)) {
        $pdo->exec($dbType === 'mysql' ? $mysqlSql : $sqliteSql);
        out("Created table {$table}");
    }
}

function execQuiet(PDO $pdo, $sql)
{
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        out('Skipped SQL: ' . $e->getMessage());
    }
}

function ensureSchemaMigrations(PDO $pdo, $dbType)
{
    createTableIfMissing(
        $pdo,
        $dbType,
        'schema_migrations',
        'CREATE TABLE "schema_migrations" ("version" TEXT NOT NULL PRIMARY KEY, "applied_at" TEXT NOT NULL)',
        'CREATE TABLE `schema_migrations` (`version` VARCHAR(128) NOT NULL PRIMARY KEY, `applied_at` DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function markMigration(PDO $pdo, $dbType)
{
    if ($dbType === 'sqlite') {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO "schema_migrations" ("version", "applied_at") VALUES (?, ?)');
    } else {
        $stmt = $pdo->prepare('INSERT IGNORE INTO `schema_migrations` (`version`, `applied_at`) VALUES (?, ?)');
    }
    $stmt->execute([MIGRATION_VERSION, date('Y-m-d H:i:s')]);
}

function backupRuntimeFiles($svnConfig, $dbFile)
{
    $home = $svnConfig['home_path'] ?? '/home/svnadmin/';
    $backupDir = rtrim($home, '/\\') . '/backup/upgrade-' . date('YmdHis');
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }

    $paths = array_filter([
        $dbFile,
        $svnConfig['svn_authz_file'] ?? null,
        $svnConfig['svn_passwd_file'] ?? null,
        $svnConfig['http_passwd_file'] ?? null,
        $svnConfig['svn_conf_file'] ?? null,
    ]);

    foreach ($paths as $path) {
        if (is_file($path)) {
            @copy($path, $backupDir . '/' . basename($path));
        }
    }

    out("Runtime backup directory: {$backupDir}");
}

function ensureOptionsTable(PDO $pdo, $dbType)
{
    createTableIfMissing(
        $pdo,
        $dbType,
        'options',
        'CREATE TABLE "options" ("option_id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "option_name" TEXT NOT NULL, "option_value" TEXT NOT NULL, "option_description" TEXT)',
        'CREATE TABLE `options` (`option_id` INT NOT NULL AUTO_INCREMENT, `option_name` VARCHAR(255) NOT NULL, `option_value` TEXT NOT NULL, `option_description` TEXT NULL, PRIMARY KEY (`option_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function defaultDataSource($userSource)
{
    return [
        'user_source' => $userSource,
        'group_source' => 'authz',
        'ldap' => [
            'ldap_host' => 'ldap://127.0.0.1/',
            'ldap_port' => 389,
            'ldap_version' => 3,
            'ldap_bind_dn' => '',
            'ldap_bind_password' => '',
            'user_base_dn' => '',
            'user_search_filter' => '',
            'user_attributes' => '',
            'user_real_name_attribute' => 'cn',
            'user_display_name_attribute' => 'displayName',
            'user_mail_attribute' => 'mail',
            'user_external_id_attribute' => 'uid',
            'user_dn_attribute' => 'dn',
            'group_base_dn' => '',
            'group_search_filter' => '',
            'group_attributes' => '',
            'group_display_name_attribute' => 'description',
            'group_external_id_attribute' => 'cn',
            'group_dn_attribute' => 'dn',
            'groups_to_user_attribute' => '',
            'groups_to_user_attribute_value' => '',
            'group_nested_enabled' => true,
            'group_nested_max_depth' => 10,
        ],
    ];
}

function mergeDatasource($value, $default)
{
    $data = @unserialize($value);
    if (!is_array($data)) {
        return $default;
    }

    $ldap = isset($data['ldap']) && is_array($data['ldap']) ? $data['ldap'] : [];
    $data = array_merge($default, $data);
    $data['ldap'] = array_merge($default['ldap'], $ldap);
    return $data;
}

function upsertOption(PDO $pdo, $dbType, $name, $default)
{
    $stmt = $pdo->prepare('SELECT option_value FROM ' . quoteName('options', $dbType) . ' WHERE option_name = ?');
    $stmt->execute([$name]);
    $oldValue = $stmt->fetchColumn();
    $newValue = serialize(mergeDatasource($oldValue === false ? '' : $oldValue, $default));

    if ($oldValue === false) {
        $insert = $pdo->prepare('INSERT INTO ' . quoteName('options', $dbType) . ' (option_name, option_value) VALUES (?, ?)');
        $insert->execute([$name, $newValue]);
        out("Inserted option {$name}");
    } elseif ($oldValue !== $newValue) {
        $update = $pdo->prepare('UPDATE ' . quoteName('options', $dbType) . ' SET option_value = ? WHERE option_name = ?');
        $update->execute([$newValue, $name]);
        out("Updated option {$name}");
    }
}

function ensureCoreColumns(PDO $pdo, $dbType)
{
    $text = $dbType === 'mysql' ? "VARCHAR(255) NOT NULL DEFAULT ''" : "TEXT DEFAULT ''";
    $textNullable = $dbType === 'mysql' ? "TEXT NULL" : "TEXT DEFAULT ''";

    foreach ([
        'svn_user_real_name' => $text,
        'svn_user_display_name' => $text,
        'svn_user_external_id' => $text,
        'svn_user_dn' => $textNullable,
        'svn_user_source' => $text,
        'svn_user_sync_time' => $text,
        'svn_user_mail' => $text,
    ] as $column => $definition) {
        addColumnIfMissing($pdo, $dbType, 'svn_users', $column, $definition);
    }

    foreach ([
        'svn_group_display_name' => $text,
        'svn_group_source' => $text,
        'svn_group_external_id' => $text,
        'svn_group_dn' => $textNullable,
        'svn_group_sync_time' => $text,
    ] as $column => $definition) {
        addColumnIfMissing($pdo, $dbType, 'svn_groups', $column, $definition);
    }

    execQuiet($pdo, 'UPDATE ' . quoteName('svn_users', $dbType) . " SET svn_user_source = 'manual' WHERE svn_user_source IS NULL OR svn_user_source = ''");
    execQuiet($pdo, 'UPDATE ' . quoteName('svn_users', $dbType) . ' SET svn_user_display_name = svn_user_name WHERE svn_user_display_name IS NULL OR svn_user_display_name = ""');
    execQuiet($pdo, 'UPDATE ' . quoteName('svn_groups', $dbType) . " SET svn_group_source = 'manual' WHERE svn_group_source IS NULL OR svn_group_source = ''");
    execQuiet($pdo, 'UPDATE ' . quoteName('svn_groups', $dbType) . ' SET svn_group_display_name = svn_group_name WHERE svn_group_display_name IS NULL OR svn_group_display_name = ""');
}

function ensureWeComTables(PDO $pdo, $dbType)
{
    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_config',
        'CREATE TABLE "wecom_config" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "corp_id" TEXT DEFAULT "", "corp_secret" TEXT DEFAULT "", "agent_id" TEXT DEFAULT "", "access_token" TEXT DEFAULT "", "token_expires_at" INTEGER DEFAULT 0, "sync_enabled" INTEGER DEFAULT 0, "sync_interval" INTEGER DEFAULT 3600, "last_sync_time" TEXT DEFAULT "", "last_full_sync_time" TEXT DEFAULT "", "notification_enabled" INTEGER DEFAULT 0, "default_chat_id" TEXT DEFAULT "", "config_data" TEXT DEFAULT "{}", "created_at" TEXT DEFAULT "", "updated_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_config` (`id` INT NOT NULL AUTO_INCREMENT, `corp_id` VARCHAR(255) NOT NULL DEFAULT "", `corp_secret` VARCHAR(255) NOT NULL DEFAULT "", `agent_id` VARCHAR(255) NOT NULL DEFAULT "", `access_token` VARCHAR(512) DEFAULT "", `token_expires_at` INT DEFAULT 0, `sync_enabled` TINYINT(1) NOT NULL DEFAULT 0, `sync_interval` INT NOT NULL DEFAULT 3600, `last_sync_time` DATETIME NULL, `last_full_sync_time` DATETIME NULL, `notification_enabled` TINYINT(1) NOT NULL DEFAULT 0, `default_chat_id` VARCHAR(255) DEFAULT "", `config_data` TEXT NULL, `created_at` DATETIME NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_departments',
        'CREATE TABLE "wecom_departments" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "wecom_dept_id" TEXT NOT NULL, "dept_name" TEXT NOT NULL, "dept_name_en" TEXT DEFAULT "", "parent_id" TEXT DEFAULT "0", "dept_order" INTEGER DEFAULT 0, "svn_group_name" TEXT DEFAULT "", "svn_group_id" INTEGER DEFAULT 0, "is_active" INTEGER DEFAULT 1, "sync_status" TEXT DEFAULT "pending", "last_sync_time" TEXT DEFAULT "", "created_at" TEXT DEFAULT "", "updated_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_departments` (`id` INT NOT NULL AUTO_INCREMENT, `wecom_dept_id` VARCHAR(255) NOT NULL, `dept_name` VARCHAR(255) NOT NULL, `dept_name_en` VARCHAR(255) DEFAULT "", `parent_id` VARCHAR(255) DEFAULT "0", `dept_order` INT DEFAULT 0, `svn_group_name` VARCHAR(255) DEFAULT "", `svn_group_id` INT DEFAULT 0, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `sync_status` VARCHAR(20) NOT NULL DEFAULT "pending", `last_sync_time` DATETIME NULL, `created_at` DATETIME NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_users',
        'CREATE TABLE "wecom_users" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "wecom_user_id" TEXT NOT NULL, "svn_username" TEXT DEFAULT "", "svn_user_id" INTEGER DEFAULT 0, "real_name" TEXT DEFAULT "", "name_en" TEXT DEFAULT "", "mobile" TEXT DEFAULT "", "email" TEXT DEFAULT "", "gender" TEXT DEFAULT "", "avatar" TEXT DEFAULT "", "status" INTEGER DEFAULT 1, "department_ids" TEXT DEFAULT "[]", "position" TEXT DEFAULT "", "is_leader_in_dept" TEXT DEFAULT "[]", "direct_leader" TEXT DEFAULT "[]", "is_active" INTEGER DEFAULT 1, "match_status" TEXT DEFAULT "pending", "match_field" TEXT DEFAULT "", "match_value" TEXT DEFAULT "", "last_sync_time" TEXT DEFAULT "", "created_at" TEXT DEFAULT "", "updated_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_users` (`id` INT NOT NULL AUTO_INCREMENT, `wecom_user_id` VARCHAR(255) NOT NULL, `svn_username` VARCHAR(255) DEFAULT "", `svn_user_id` INT DEFAULT 0, `real_name` VARCHAR(255) DEFAULT "", `name_en` VARCHAR(255) DEFAULT "", `mobile` VARCHAR(20) DEFAULT "", `email` VARCHAR(255) DEFAULT "", `gender` VARCHAR(10) DEFAULT "", `avatar` VARCHAR(500) DEFAULT "", `status` TINYINT(1) DEFAULT 1, `department_ids` TEXT NULL, `position` VARCHAR(255) DEFAULT "", `is_leader_in_dept` TEXT NULL, `direct_leader` TEXT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `match_status` VARCHAR(20) NOT NULL DEFAULT "pending", `match_field` VARCHAR(50) DEFAULT "", `match_value` VARCHAR(255) DEFAULT "", `last_sync_time` DATETIME NULL, `created_at` DATETIME NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_notification_rules',
        'CREATE TABLE "wecom_notification_rules" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "rule_name" TEXT NOT NULL DEFAULT "", "repo_name" TEXT DEFAULT "*", "path_prefix" TEXT DEFAULT "/", "event_type" TEXT NOT NULL DEFAULT "", "webhook_url" TEXT DEFAULT "", "message_template" TEXT DEFAULT "", "notify_wecom_userids" TEXT DEFAULT "", "notify_wecom_deptids" TEXT DEFAULT "", "enable" INTEGER DEFAULT 1, "created_at" TEXT DEFAULT "", "updated_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_notification_rules` (`id` INT NOT NULL AUTO_INCREMENT, `rule_name` VARCHAR(255) NOT NULL DEFAULT "", `repo_name` VARCHAR(500) DEFAULT "*", `path_prefix` VARCHAR(500) DEFAULT "/", `event_type` VARCHAR(255) NOT NULL DEFAULT "", `webhook_url` TEXT NULL, `message_template` TEXT NULL, `notify_wecom_userids` TEXT NULL, `notify_wecom_deptids` TEXT NULL, `enable` TINYINT(1) NOT NULL DEFAULT 1, `created_at` DATETIME NULL, `updated_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_notification_logs',
        'CREATE TABLE "wecom_notification_logs" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "rule_id" INTEGER DEFAULT 0, "repo_name" TEXT DEFAULT "", "event_type" TEXT DEFAULT "", "author" TEXT DEFAULT "", "message" TEXT DEFAULT "", "files_changed" TEXT DEFAULT "", "chat_id" TEXT DEFAULT "", "notification_content" TEXT DEFAULT "", "send_status" TEXT DEFAULT "pending", "response_data" TEXT DEFAULT "", "error_message" TEXT DEFAULT "", "retry_count" INTEGER DEFAULT 0, "sent_at" TEXT DEFAULT "", "created_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_notification_logs` (`id` INT NOT NULL AUTO_INCREMENT, `rule_id` INT DEFAULT 0, `repo_name` VARCHAR(255) DEFAULT "", `event_type` VARCHAR(50) DEFAULT "", `author` VARCHAR(255) DEFAULT "", `message` TEXT NULL, `files_changed` TEXT NULL, `chat_id` VARCHAR(255) DEFAULT "", `notification_content` TEXT NULL, `send_status` VARCHAR(20) NOT NULL DEFAULT "pending", `response_data` TEXT NULL, `error_message` TEXT NULL, `retry_count` INT DEFAULT 0, `sent_at` DATETIME NULL, `created_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_sync_logs',
        'CREATE TABLE "wecom_sync_logs" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "sync_type" TEXT NOT NULL, "sync_status" TEXT DEFAULT "running", "start_time" TEXT DEFAULT "", "end_time" TEXT DEFAULT "", "duration" INTEGER DEFAULT 0, "departments_total" INTEGER DEFAULT 0, "departments_synced" INTEGER DEFAULT 0, "users_total" INTEGER DEFAULT 0, "users_synced" INTEGER DEFAULT 0, "users_matched" INTEGER DEFAULT 0, "errors_count" INTEGER DEFAULT 0, "error_details" TEXT DEFAULT "", "summary" TEXT DEFAULT "", "created_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_sync_logs` (`id` INT NOT NULL AUTO_INCREMENT, `sync_type` VARCHAR(20) NOT NULL, `sync_status` VARCHAR(20) NOT NULL DEFAULT "running", `start_time` DATETIME NULL, `end_time` DATETIME NULL, `duration` INT DEFAULT 0, `departments_total` INT DEFAULT 0, `departments_synced` INT DEFAULT 0, `users_total` INT DEFAULT 0, `users_synced` INT DEFAULT 0, `users_matched` INT DEFAULT 0, `errors_count` INT DEFAULT 0, `error_details` TEXT NULL, `summary` TEXT NULL, `created_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_api_logs',
        'CREATE TABLE "wecom_api_logs" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "api_method" TEXT NOT NULL DEFAULT "", "api_url" TEXT NOT NULL DEFAULT "", "request_data" TEXT DEFAULT "", "response_code" INTEGER DEFAULT 0, "response_data" TEXT DEFAULT "", "response_time" INTEGER DEFAULT 0, "error_message" TEXT DEFAULT "", "created_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_api_logs` (`id` INT NOT NULL AUTO_INCREMENT, `api_method` VARCHAR(50) NOT NULL DEFAULT "", `api_url` VARCHAR(500) NOT NULL DEFAULT "", `request_data` TEXT NULL, `response_code` INT DEFAULT 0, `response_data` TEXT NULL, `response_time` INT DEFAULT 0, `error_message` TEXT NULL, `created_at` DATETIME NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    createTableIfMissing(
        $pdo,
        $dbType,
        'wecom_notification_queue',
        'CREATE TABLE "wecom_notification_queue" ("queue_id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "notification_type" TEXT NOT NULL DEFAULT "", "event_data" TEXT DEFAULT "", "webhook_url" TEXT DEFAULT "", "message_template" TEXT DEFAULT "", "status" TEXT DEFAULT "pending", "retry_count" INTEGER DEFAULT 0, "max_retries" INTEGER DEFAULT 3, "next_retry_time" TEXT DEFAULT "", "last_error" TEXT DEFAULT "", "created_at" TEXT DEFAULT "", "updated_at" TEXT DEFAULT "", "completed_at" TEXT DEFAULT "")',
        'CREATE TABLE `wecom_notification_queue` (`queue_id` INT NOT NULL AUTO_INCREMENT, `notification_type` VARCHAR(50) NOT NULL DEFAULT "", `event_data` TEXT NULL, `webhook_url` TEXT NULL, `message_template` TEXT NULL, `status` VARCHAR(20) NOT NULL DEFAULT "pending", `retry_count` INT DEFAULT 0, `max_retries` INT DEFAULT 3, `next_retry_time` DATETIME NULL, `last_error` TEXT NULL, `created_at` DATETIME NULL, `updated_at` DATETIME NULL, `completed_at` DATETIME NULL, PRIMARY KEY (`queue_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureWeComColumns(PDO $pdo, $dbType)
{
    $text = $dbType === 'mysql' ? 'TEXT NULL' : 'TEXT DEFAULT ""';
    $shortText = $dbType === 'mysql' ? 'VARCHAR(255) NOT NULL DEFAULT ""' : 'TEXT DEFAULT ""';
    $int = $dbType === 'mysql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0';
    $tiny = $dbType === 'mysql' ? 'TINYINT(1) DEFAULT 0' : 'INTEGER DEFAULT 0';

    $columns = [
        'wecom_config' => [
            'corp_id' => $shortText, 'corp_secret' => $shortText, 'agent_id' => $shortText,
            'access_token' => $text, 'token_expires_at' => $int, 'sync_enabled' => $tiny,
            'sync_interval' => $int, 'last_sync_time' => $text, 'last_full_sync_time' => $text,
            'notification_enabled' => $tiny, 'default_chat_id' => $shortText, 'config_data' => $text,
            'created_at' => $text, 'updated_at' => $text,
        ],
        'wecom_departments' => [
            'wecom_dept_id' => $shortText, 'dept_name' => $shortText, 'dept_name_en' => $shortText,
            'parent_id' => $shortText, 'dept_order' => $int, 'svn_group_name' => $shortText,
            'svn_group_id' => $int, 'is_active' => $tiny, 'sync_status' => $shortText,
            'last_sync_time' => $text, 'created_at' => $text, 'updated_at' => $text,
        ],
        'wecom_users' => [
            'wecom_user_id' => $shortText, 'svn_username' => $shortText, 'svn_user_id' => $int,
            'real_name' => $shortText, 'name_en' => $shortText, 'mobile' => $shortText,
            'email' => $shortText, 'gender' => $shortText, 'avatar' => $text, 'status' => $tiny,
            'department_ids' => $text, 'position' => $shortText, 'is_leader_in_dept' => $text,
            'direct_leader' => $text, 'is_active' => $tiny, 'match_status' => $shortText,
            'match_field' => $shortText, 'match_value' => $shortText, 'last_sync_time' => $text,
            'created_at' => $text, 'updated_at' => $text,
        ],
        'wecom_notification_rules' => [
            'rule_name' => $shortText, 'repo_name' => $shortText, 'path_prefix' => $shortText,
            'event_type' => $shortText, 'webhook_url' => $text, 'message_template' => $text,
            'notify_wecom_userids' => $text, 'notify_wecom_deptids' => $text,
            'enable' => $tiny, 'created_at' => $text, 'updated_at' => $text,
        ],
        'wecom_notification_logs' => [
            'rule_id' => $int, 'repo_name' => $shortText, 'event_type' => $shortText,
            'author' => $shortText, 'message' => $text, 'files_changed' => $text,
            'chat_id' => $shortText, 'notification_content' => $text, 'send_status' => $shortText,
            'response_data' => $text, 'error_message' => $text, 'retry_count' => $int,
            'sent_at' => $text, 'created_at' => $text,
        ],
        'wecom_sync_logs' => [
            'sync_type' => $shortText, 'sync_status' => $shortText, 'start_time' => $text,
            'end_time' => $text, 'duration' => $int, 'departments_total' => $int,
            'departments_synced' => $int, 'users_total' => $int, 'users_synced' => $int,
            'users_matched' => $int, 'errors_count' => $int, 'error_details' => $text,
            'summary' => $text, 'created_at' => $text,
        ],
        'wecom_api_logs' => [
            'api_method' => $shortText, 'api_url' => $text, 'request_data' => $text,
            'response_code' => $int, 'response_data' => $text, 'response_time' => $int,
            'error_message' => $text, 'created_at' => $text,
        ],
        'wecom_notification_queue' => [
            'notification_type' => $shortText, 'event_data' => $text, 'webhook_url' => $text,
            'message_template' => $text, 'status' => $shortText, 'retry_count' => $int,
            'max_retries' => $int, 'next_retry_time' => $text, 'last_error' => $text,
            'created_at' => $text, 'updated_at' => $text, 'completed_at' => $text,
        ],
    ];

    foreach ($columns as $table => $tableColumns) {
        foreach ($tableColumns as $column => $definition) {
            addColumnIfMissing($pdo, $dbType, $table, $column, $definition);
        }
    }
}

function normalizeJsonEvents($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return implode(',', array_filter(array_map('strval', $decoded)));
    }

    return $value;
}

function migrateLegacyWeComData(PDO $pdo, $dbType)
{
    if (tableExists($pdo, $dbType, 'wecom_config')) {
        if (columnExists($pdo, $dbType, 'wecom_config', 'secret') && columnExists($pdo, $dbType, 'wecom_config', 'corp_secret')) {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_config', $dbType) . ' SET corp_secret = secret WHERE (corp_secret IS NULL OR corp_secret = "") AND secret IS NOT NULL AND secret != ""');
        }
        execQuiet($pdo, 'UPDATE ' . quoteName('wecom_config', $dbType) . ' SET config_data = "{}" WHERE config_data IS NULL OR config_data = ""');
        execQuiet($pdo, 'UPDATE ' . quoteName('wecom_config', $dbType) . ' SET created_at = "' . date('Y-m-d H:i:s') . '" WHERE created_at IS NULL OR created_at = ""');
        execQuiet($pdo, 'UPDATE ' . quoteName('wecom_config', $dbType) . ' SET updated_at = "' . date('Y-m-d H:i:s') . '" WHERE updated_at IS NULL OR updated_at = ""');

        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . quoteName('wecom_config', $dbType))->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO ' . quoteName('wecom_config', $dbType) . ' (id, sync_enabled, notification_enabled, sync_interval, config_data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([1, 0, 0, 3600, '{}', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        }
    }

    if (tableExists($pdo, $dbType, 'wecom_notification_rules')) {
        if (columnExists($pdo, $dbType, 'wecom_notification_rules', 'repo_path')) {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET repo_name = repo_path WHERE (repo_name IS NULL OR repo_name = "") AND repo_path IS NOT NULL AND repo_path != ""');
        }
        if (columnExists($pdo, $dbType, 'wecom_notification_rules', 'is_enabled')) {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET enable = is_enabled WHERE is_enabled IS NOT NULL');
        }
        if (columnExists($pdo, $dbType, 'wecom_notification_rules', 'chat_id')) {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET webhook_url = chat_id WHERE (webhook_url IS NULL OR webhook_url = "") AND chat_id LIKE "http%"');
        }
        execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET path_prefix = "/" WHERE path_prefix IS NULL OR path_prefix = ""');
        execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET repo_name = "*" WHERE repo_name IS NULL OR repo_name = ""');
        if ($dbType === 'mysql') {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . " SET rule_name = CONCAT('notification-rule-', id) WHERE rule_name IS NULL OR rule_name = ''");
        } else {
            execQuiet($pdo, 'UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET rule_name = "notification-rule-" || id WHERE rule_name IS NULL OR rule_name = ""');
        }

        if (columnExists($pdo, $dbType, 'wecom_notification_rules', 'events')) {
            $select = $pdo->query('SELECT id, events, event_type FROM ' . quoteName('wecom_notification_rules', $dbType));
            $update = $pdo->prepare('UPDATE ' . quoteName('wecom_notification_rules', $dbType) . ' SET event_type = ? WHERE id = ?');
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (trim((string)$row['event_type']) === '') {
                    $eventType = normalizeJsonEvents($row['events']);
                    if ($eventType !== '') {
                        $update->execute([$eventType, $row['id']]);
                    }
                }
            }
        }
    }

    if (tableExists($pdo, $dbType, 'wecom_sync_logs')) {
        $maps = [
            'dept_total' => 'departments_total',
            'dept_synced' => 'departments_synced',
            'user_total' => 'users_total',
            'user_synced' => 'users_synced',
            'error_count' => 'errors_count',
        ];
        foreach ($maps as $old => $new) {
            if (columnExists($pdo, $dbType, 'wecom_sync_logs', $old)) {
                execQuiet($pdo, 'UPDATE ' . quoteName('wecom_sync_logs', $dbType) . " SET {$new} = {$old} WHERE {$new} = 0 AND {$old} IS NOT NULL");
            }
        }
    }

    if (tableExists($pdo, $dbType, 'wecom_api_logs')) {
        $maps = [
            'api_name' => 'api_method',
            'request_url' => 'api_url',
            'request_params' => 'request_data',
            'error_msg' => 'error_message',
            'execution_time' => 'response_time',
        ];
        foreach ($maps as $old => $new) {
            if (columnExists($pdo, $dbType, 'wecom_api_logs', $old)) {
                execQuiet($pdo, 'UPDATE ' . quoteName('wecom_api_logs', $dbType) . " SET {$new} = {$old} WHERE ({$new} IS NULL OR {$new} = '') AND {$old} IS NOT NULL");
            }
        }
    }
}

function ensureIndexes(PDO $pdo, $dbType)
{
    if ($dbType === 'sqlite') {
        $sql = [
            'CREATE INDEX IF NOT EXISTS "idx_svn_users_name" ON "svn_users" ("svn_user_name")',
            'CREATE INDEX IF NOT EXISTS "idx_svn_groups_name" ON "svn_groups" ("svn_group_name")',
            'CREATE INDEX IF NOT EXISTS "idx_wecom_users_svn" ON "wecom_users" ("svn_username", "svn_user_id")',
            'CREATE INDEX IF NOT EXISTS "idx_wecom_sync_logs_type_status" ON "wecom_sync_logs" ("sync_type", "sync_status")',
            'CREATE INDEX IF NOT EXISTS "idx_wecom_notification_rules_enabled" ON "wecom_notification_rules" ("enable")',
        ];
    } else {
        $sql = [
            'CREATE INDEX idx_svn_users_name ON svn_users (svn_user_name)',
            'CREATE INDEX idx_svn_groups_name ON svn_groups (svn_group_name)',
            'CREATE INDEX idx_wecom_users_svn ON wecom_users (svn_username, svn_user_id)',
            'CREATE INDEX idx_wecom_sync_logs_type_status ON wecom_sync_logs (sync_type, sync_status)',
            'CREATE INDEX idx_wecom_notification_rules_enabled ON wecom_notification_rules (enable)',
        ];
    }

    foreach ($sql as $statement) {
        execQuiet($pdo, $statement);
    }
}

try {
    $paths = resolvePaths();
    [$databaseConfig, $svnConfig] = loadConfigs($paths['appRoot']);
    [$pdo, $dbType, $dbFile] = connectDatabase($databaseConfig, $svnConfig, $paths['appRoot']);

    out("Application root: {$paths['appRoot']}");
    out("Database type: {$dbType}");

    backupRuntimeFiles($svnConfig, $dbFile);
    ensureSchemaMigrations($pdo, $dbType);
    ensureOptionsTable($pdo, $dbType);
    ensureCoreColumns($pdo, $dbType);
    ensureWeComTables($pdo, $dbType);
    ensureWeComColumns($pdo, $dbType);
    migrateLegacyWeComData($pdo, $dbType);
    ensureIndexes($pdo, $dbType);

    upsertOption($pdo, $dbType, '24_svn_datasource', defaultDataSource('passwd'));
    upsertOption($pdo, $dbType, '24_http_datasource', defaultDataSource('httpPasswd'));

    markMigration($pdo, $dbType);

    out('Migration completed successfully.');
    exit(0);
} catch (Throwable $e) {
    fail($e->getMessage());
}
