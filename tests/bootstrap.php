<?php
/*
 * PHPUnit Bootstrap File for WeChat Integration Tests
 * 
 * This file sets up the testing environment for the WeChat integration module
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('PRC');

// Define base paths
define('BASE_PATH', dirname(__DIR__) . '/02.php');
define('TEST_BASE_PATH', __DIR__);

// Include required files
require_once BASE_PATH . '/app/util/Config.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';

// Auto-load service classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $classPath = str_replace('\\', '/', $class);
    
    // Try different base paths
    $basePaths = [
        BASE_PATH . '/app/service/',
        BASE_PATH . '/app/controller/',
        BASE_PATH . '/app/util/',
        TEST_BASE_PATH . '/mocks/',
    ];
    
    foreach ($basePaths as $basePath) {
        $file = $basePath . $classPath . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load test configuration
Config::load(BASE_PATH . '/config/');

// Override config for testing
$testConfig = [
    'database' => [
        'database_type' => 'sqlite',
        'database_file' => ':memory:',
        'logging' => false
    ],
    'wecom' => [
        'enabled' => true,
        'corp_id' => 'test_corp_id',
        'corp_secret' => 'test_corp_secret',
        'agent_id' => 'test_agent_id',
        'api_base_url' => 'https://qyapi.weixin.qq.com',
        'token_cache_time' => 7200,
        'request_timeout' => 30,
        'max_retries' => 3,
        'test_mode' => true
    ]
];

// Set test configurations
foreach ($testConfig as $key => $config) {
    Config::set($key, $config);
}

// Create test database tables
function createTestTables()
{
    try {
        $database = new Medoo\Medoo(Config::get('database'));
        
        // Create basic test tables
        $database->query("CREATE TABLE IF NOT EXISTS wecom_config (
            id INTEGER PRIMARY KEY,
            corp_id TEXT,
            corp_secret TEXT,
            agent_id TEXT,
            access_token TEXT,
            token_expires_at INTEGER,
            created_at TEXT,
            updated_at TEXT
        )");
        
        $database->query("CREATE TABLE IF NOT EXISTS wecom_departments (
            id INTEGER PRIMARY KEY,
            wecom_dept_id TEXT,
            dept_name TEXT,
            parent_id TEXT,
            svn_group_name TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT,
            updated_at TEXT
        )");
        
        $database->query("CREATE TABLE IF NOT EXISTS wecom_users (
            id INTEGER PRIMARY KEY,
            wecom_userid TEXT,
            wecom_name TEXT,
            wecom_email TEXT,
            wecom_mobile TEXT,
            svn_username TEXT,
            svn_user_id INTEGER,
            match_status TEXT DEFAULT 'unmatched',
            is_active INTEGER DEFAULT 1,
            created_at TEXT,
            updated_at TEXT
        )");
        
        $database->query("CREATE TABLE IF NOT EXISTS wecom_notification_logs (
            id INTEGER PRIMARY KEY,
            event_type TEXT,
            webhook_url TEXT,
            send_status TEXT,
            response_data TEXT,
            error_message TEXT,
            created_at TEXT
        )");
        
        $database->query("CREATE TABLE IF NOT EXISTS wecom_api_logs (
            id INTEGER PRIMARY KEY,
            api_method TEXT,
            api_url TEXT,
            request_data TEXT,
            response_code INTEGER,
            response_data TEXT,
            response_time INTEGER,
            error_message TEXT,
            created_at TEXT
        )");
        
        return $database;
    } catch (Exception $e) {
        echo "Failed to create test tables: " . $e->getMessage() . PHP_EOL;
        return null;
    }
}

// Initialize test database
$GLOBALS['test_database'] = createTestTables();

// Test helper functions
function getTestDatabase()
{
    return $GLOBALS['test_database'];
}

function clearTestData()
{
    $db = getTestDatabase();
    if ($db) {
        $db->query("DELETE FROM wecom_config");
        $db->query("DELETE FROM wecom_departments");
        $db->query("DELETE FROM wecom_users");
        $db->query("DELETE FROM wecom_notification_logs");
        $db->query("DELETE FROM wecom_api_logs");
    }
}

function insertTestData($table, $data)
{
    $db = getTestDatabase();
    if ($db) {
        return $db->insert($table, $data);
    }
    return false;
}

function getTestData($table, $where = [])
{
    $db = getTestDatabase();
    if ($db) {
        return $db->select($table, '*', $where);
    }
    return [];
}

// Mock HTTP responses for testing
class MockHttpResponse
{
    public static $responses = [];
    
    public static function setResponse($url, $response)
    {
        self::$responses[$url] = $response;
    }
    
    public static function getResponse($url)
    {
        return self::$responses[$url] ?? null;
    }
    
    public static function clearResponses()
    {
        self::$responses = [];
    }
}

echo "Test environment initialized successfully." . PHP_EOL;
