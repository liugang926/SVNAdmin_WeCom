<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This preflight check must be run from php-cli.\n");
    exit(1);
}

date_default_timezone_set('Asia/Shanghai');

$failures = 0;
$warnings = 0;

function line_out($level, $message)
{
    echo sprintf('[%s] %s', $level, $message) . PHP_EOL;
}

function ok($message)
{
    line_out('OK', $message);
}

function warn_check($condition, $message)
{
    global $warnings;
    if ($condition) {
        ok($message);
        return;
    }
    $warnings++;
    line_out('WARN', $message);
}

function fail_check($condition, $message)
{
    global $failures;
    if ($condition) {
        ok($message);
        return;
    }
    $failures++;
    line_out('FAIL', $message);
}

function resolve_paths()
{
    $projectRoot = dirname(__DIR__);
    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . '02.php',
        $projectRoot,
        '/var/www/html',
    ];

    foreach ($candidates as $appRoot) {
        if (is_file($appRoot . '/config/database.php') && is_file($appRoot . '/config/svn.php')) {
            return [$projectRoot, $appRoot];
        }
    }

    fail_check(false, 'Cannot locate SVNAdmin application root.');
    exit(1);
}

function sqlite_database_path(array $databaseConfig, array $svnConfig)
{
    $home = $svnConfig['home_path'] ?? '/home/svnadmin/';
    $file = $databaseConfig['database_file'] ?? ($home . 'svnadmin.db');
    return strpos($file, '%s') !== false ? sprintf($file, $home) : $file;
}

function connect_database(array $databaseConfig, array $svnConfig)
{
    $type = strtolower($databaseConfig['database_type'] ?? 'sqlite');

    if ($type === 'sqlite') {
        $dbFile = sqlite_database_path($databaseConfig, $svnConfig);
        fail_check(is_file($dbFile) && filesize($dbFile) > 0, "SQLite database exists: {$dbFile}");
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        ok("Connected to MySQL database: {$db}");
        return [$pdo, 'mysql', null];
    }

    fail_check(false, "Unsupported database type: {$type}");
    exit(1);
}

function quote_name($name, $dbType)
{
    return $dbType === 'mysql' ? "`{$name}`" : "\"{$name}\"";
}

function table_exists(PDO $pdo, $dbType, $table)
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

function column_exists(PDO $pdo, $dbType, $table, $column)
{
    if (!table_exists($pdo, $dbType, $table)) {
        return false;
    }

    if ($dbType === 'sqlite') {
        $stmt = $pdo->query('PRAGMA table_info(' . quote_name($table, $dbType) . ')');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strcasecmp($row['name'], $column) === 0) {
                return true;
            }
        }
        return false;
    }

    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . quote_name($table, $dbType) . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function check_columns(PDO $pdo, $dbType, $table, array $columns)
{
    fail_check(table_exists($pdo, $dbType, $table), "Table exists: {$table}");
    foreach ($columns as $column) {
        fail_check(column_exists($pdo, $dbType, $table, $column), "Column exists: {$table}.{$column}");
    }
}

function is_executable_file($path)
{
    return is_file($path) && (DIRECTORY_SEPARATOR === '\\' || is_executable($path));
}

function command_result($command)
{
    $output = [];
    $code = 0;
    @exec($command . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
}

[$projectRoot, $appRoot] = resolve_paths();
$databaseConfig = require $appRoot . '/config/database.php';
$svnConfig = require $appRoot . '/config/svn.php';
$binConfig = is_file($appRoot . '/config/bin.php') ? require $appRoot . '/config/bin.php' : [];

ok("Application root: {$appRoot}");

try {
    [$pdo, $dbType, $dbFile] = connect_database($databaseConfig, $svnConfig);

    foreach (['admin_users', 'svn_groups', 'svn_users', 'svn_reps'] as $table) {
        fail_check(table_exists($pdo, $dbType, $table), "Core table exists: {$table}");
    }

    check_columns($pdo, $dbType, 'svn_users', [
        'svn_user_name',
        'svn_user_real_name',
        'svn_user_display_name',
        'svn_user_source',
        'svn_user_mail',
    ]);

    check_columns($pdo, $dbType, 'svn_groups', [
        'svn_group_name',
        'svn_group_display_name',
        'svn_group_source',
        'svn_group_dn',
    ]);

    $wecomTables = [
        'wecom_config' => ['corp_id', 'corp_secret', 'agent_id', 'sync_enabled', 'notification_enabled', 'config_data'],
        'wecom_departments' => ['wecom_dept_id', 'dept_name', 'svn_group_name', 'sync_status'],
        'wecom_users' => ['wecom_user_id', 'svn_username', 'real_name', 'email', 'department_ids'],
        'wecom_notification_rules' => ['rule_name', 'repo_name', 'path_prefix', 'event_type', 'webhook_url', 'enable'],
        'wecom_notification_logs' => ['repo_name', 'event_type', 'send_status', 'error_message'],
        'wecom_sync_logs' => ['sync_type', 'sync_status', 'departments_total', 'users_total', 'errors_count'],
        'wecom_api_logs' => ['api_method', 'api_url', 'response_code', 'error_message'],
        'wecom_notification_queue' => ['notification_type', 'event_data', 'webhook_url', 'status'],
    ];

    foreach ($wecomTables as $table => $columns) {
        check_columns($pdo, $dbType, $table, $columns);
    }
} catch (Throwable $e) {
    fail_check(false, 'Database check failed: ' . $e->getMessage());
}

$pathsToCheck = [
    'home_path',
    'rep_base_path',
    'backup_base_path',
    'crond_base_path',
    'log_base_path',
    'recommend_hook_path',
];

foreach ($pathsToCheck as $key) {
    if (!isset($svnConfig[$key])) {
        continue;
    }
    fail_check(is_dir($svnConfig[$key]), "Directory exists: {$svnConfig[$key]}");
    warn_check(is_writable($svnConfig[$key]), "Directory writable: {$svnConfig[$key]}");
}

foreach (['svn_authz_file', 'svn_passwd_file', 'http_passwd_file', 'svn_conf_file'] as $key) {
    if (!isset($svnConfig[$key])) {
        continue;
    }
    fail_check(is_file($svnConfig[$key]), "Config file exists: {$svnConfig[$key]}");
    warn_check(is_writable($svnConfig[$key]), "Config file writable: {$svnConfig[$key]}");
}

foreach (['svn', 'svnadmin', 'svnlook', 'svnserve', 'htpasswd', 'svnauthz-validate'] as $key) {
    if (!isset($binConfig[$key])) {
        warn_check(false, "Binary path configured: {$key}");
        continue;
    }
    fail_check(is_executable_file($binConfig[$key]), "Binary executable: {$key} => {$binConfig[$key]}");
}

if (isset($svnConfig['svn_authz_file'], $binConfig['svnauthz-validate']) && is_file($svnConfig['svn_authz_file']) && is_executable_file($binConfig['svnauthz-validate'])) {
    [$code, $output] = command_result(escapeshellarg($binConfig['svnauthz-validate']) . ' ' . escapeshellarg($svnConfig['svn_authz_file']));
    fail_check($code === 0, 'authz syntax validates with svnauthz-validate' . ($code === 0 ? '' : ": {$output}"));
}

if (DIRECTORY_SEPARATOR !== '\\') {
    [$code] = command_result('pgrep -x svnserve');
    if ($code === 0) {
        ok('svnserve process is running.');
    } else {
        $warnings++;
        line_out('WARN', 'svnserve process is not running in this check context; run preflight inside the started container to verify the live service.');
    }
}

if ($warnings > 0 || $failures > 0) {
    echo PHP_EOL . "Preflight completed with {$failures} failure(s), {$warnings} warning(s)." . PHP_EOL;
} else {
    echo PHP_EOL . "Preflight completed successfully." . PHP_EOL;
}

exit($failures > 0 ? 1 : 0);
