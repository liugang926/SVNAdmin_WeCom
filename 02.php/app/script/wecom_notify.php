<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信通知处理脚本
 * 
 * 用法：
 * php wecom_notify.php <event_type> <repo_name> [additional_params...]
 * 
 * 示例：
 * php wecom_notify.php commit myrepo 123 author "commit message"
 * php wecom_notify.php sync full
 */

// 限制工作模式为CLI
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode' . PHP_EOL);
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('PRC');

// 获取脚本目录
$scriptDir = dirname(__FILE__);
$baseDir = dirname(dirname($scriptDir));

// 定义基础路径
define('BASE_PATH', $baseDir);

// 检查基础路径
if (!file_exists(BASE_PATH . '/02.php/app/service/base/Base.php')) {
    echo "Error: SVNAdmin base path not found: " . BASE_PATH . PHP_EOL;
    exit(1);
}

// 加载自动加载器
require_once BASE_PATH . '/02.php/app/service/base/Base.php';
require_once BASE_PATH . '/02.php/app/util/WeComNotificationClient.php';

/**
 * 显示使用帮助
 */
function showUsage() {
    echo "企业微信通知处理脚本" . PHP_EOL;
    echo PHP_EOL;
    echo "用法:" . PHP_EOL;
    echo "  php wecom_notify.php <event_type> <repo_name> [params...]" . PHP_EOL;
    echo PHP_EOL;
    echo "事件类型:" . PHP_EOL;
    echo "  commit <repo_name> <revision> <author> <message> [files...]" . PHP_EOL;
    echo "  update <repo_name> <revision> <author> [files...]" . PHP_EOL;
    echo "  delete <repo_name> <path> <author>" . PHP_EOL;
    echo "  sync <sync_type> [result_file]" . PHP_EOL;
    echo "  test <webhook_url> [message]" . PHP_EOL;
    echo PHP_EOL;
    echo "示例:" . PHP_EOL;
    echo "  php wecom_notify.php commit myrepo 123 john 'Fix bug'" . PHP_EOL;
    echo "  php wecom_notify.php sync full /tmp/sync_result.json" . PHP_EOL;
    echo "  php wecom_notify.php test 'https://qyapi.weixin.qq.com/...'" . PHP_EOL;
}

/**
 * 记录日志
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] [$level] $message" . PHP_EOL;
}

/**
 * 处理提交事件
 */
function handleCommitEvent($args) {
    if (count($args) < 4) {
        throw new Exception('Commit event requires: repo_name, revision, author, message');
    }
    
    $repoName = $args[0];
    $revision = $args[1];
    $author = $args[2];
    $message = $args[3];
    $files = array_slice($args, 4);
    
    $eventData = [
        'repo_name' => $repoName,
        'revision' => $revision,
        'author' => $author,
        'message' => $message,
        'files' => $files,
        'path' => '/',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return ['commit', $eventData];
}

/**
 * 处理更新事件
 */
function handleUpdateEvent($args) {
    if (count($args) < 3) {
        throw new Exception('Update event requires: repo_name, revision, author');
    }
    
    $repoName = $args[0];
    $revision = $args[1];
    $author = $args[2];
    $files = array_slice($args, 3);
    
    $eventData = [
        'repo_name' => $repoName,
        'revision' => $revision,
        'author' => $author,
        'files' => $files,
        'path' => '/',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return ['update', $eventData];
}

/**
 * 处理删除事件
 */
function handleDeleteEvent($args) {
    if (count($args) < 3) {
        throw new Exception('Delete event requires: repo_name, path, author');
    }
    
    $repoName = $args[0];
    $path = $args[1];
    $author = $args[2];
    
    $eventData = [
        'repo_name' => $repoName,
        'path' => $path,
        'author' => $author,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return ['delete', $eventData];
}

/**
 * 处理同步事件
 */
function handleSyncEvent($args) {
    if (count($args) < 1) {
        throw new Exception('Sync event requires: sync_type');
    }
    
    $syncType = $args[0];
    $resultFile = $args[1] ?? null;
    
    $syncResult = ['status' => 1, 'message' => 'Sync completed'];
    
    // 如果提供了结果文件，尝试读取
    if ($resultFile && file_exists($resultFile)) {
        $content = file_get_contents($resultFile);
        $decoded = json_decode($content, true);
        if ($decoded) {
            $syncResult = $decoded;
        }
    }
    
    return [$syncType, $syncResult];
}

/**
 * 处理测试事件
 */
function handleTestEvent($args) {
    if (count($args) < 1) {
        throw new Exception('Test event requires: webhook_url');
    }
    
    $webhookUrl = $args[0];
    $message = $args[1] ?? null;
    
    return [$webhookUrl, $message];
}

// 主程序开始
try {
    // 检查参数
    if ($argc < 2) {
        showUsage();
        exit(1);
    }
    
    $eventType = $argv[1];
    $args = array_slice($argv, 2);
    
    logMessage("Processing event: $eventType");
    
    // 检查企业微信配置
    $wecomConfig = require BASE_PATH . '/02.php/config/wecom.php';
    if (!($wecomConfig['enabled'] ?? false)) {
        logMessage('WeChat integration is disabled', 'WARN');
        exit(0);
    }
    
    // 根据事件类型处理
    switch ($eventType) {
        case 'commit':
        case 'update':
        case 'delete':
            // SVN 操作事件
            $handlerFunction = 'handle' . ucfirst($eventType) . 'Event';
            list($eventTypeProcessed, $eventData) = $handlerFunction($args);
            
            // 使用通知守护进程客户端
            $notificationClient = new WeComNotificationClient();
            
            // 发送通知到队列
            $result = $notificationClient->sendSvnCommitNotification($eventData, '');
            
            if ($result['success']) {
                logMessage("Notification queued successfully: {$result['data']}");
            } else {
                logMessage("Notification queue failed: {$result['error']}", 'ERROR');
                
                // 如果守护进程不可用，回退到直接发送
                logMessage("Falling back to direct notification", 'WARN');
                $notificationService = new app\service\WeComNotification();
                $directResult = $notificationService->sendSvnNotification($eventTypeProcessed, $eventData);
                
                if ($directResult['status'] === 1) {
                    logMessage("Direct notification sent successfully: {$directResult['sent_count']} messages");
                } else {
                    logMessage("Direct notification failed: {$directResult['message']}", 'ERROR');
                    exit(1);
                }
            }
            break;
            
        case 'sync':
            // 同步事件
            list($syncType, $syncResult) = handleSyncEvent($args);
            
            // 使用通知守护进程客户端
            $notificationClient = new WeComNotificationClient();
            
            // 发送同步通知到队列
            $result = $notificationClient->sendSyncCompleteNotification($syncResult, '');
            
            if ($result['success']) {
                logMessage("Sync notification queued successfully: {$result['data']}");
            } else {
                logMessage("Sync notification queue failed: {$result['error']}", 'ERROR');
                
                // 如果守护进程不可用，回退到直接发送
                logMessage("Falling back to direct sync notification", 'WARN');
                $notificationService = new app\service\WeComNotification();
                $directResult = $notificationService->sendSyncNotification($syncType, $syncResult);
                
                if ($directResult['status'] === 1) {
                    logMessage("Direct sync notification sent successfully");
                } else {
                    logMessage("Direct sync notification failed: {$directResult['message']}", 'ERROR');
                    exit(1);
                }
            }
            break;
            
        case 'test':
            // 测试事件
            list($webhookUrl, $message) = handleTestEvent($args);
            
            // 创建通知服务
            $notificationService = new app\service\WeComNotification();
            
            // 发送测试通知
            $result = $notificationService->testNotification($webhookUrl, $message);
            
            if ($result['status'] === 1) {
                logMessage("Test notification sent successfully");
            } else {
                logMessage("Test notification failed: {$result['message']}", 'ERROR');
                exit(1);
            }
            break;
            
        case 'help':
        case '--help':
        case '-h':
            showUsage();
            exit(0);
            
        default:
            logMessage("Unknown event type: $eventType", 'ERROR');
            showUsage();
            exit(1);
    }
    
    logMessage("Event processing completed successfully");
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage(), 'ERROR');
    exit(1);
}
?>
