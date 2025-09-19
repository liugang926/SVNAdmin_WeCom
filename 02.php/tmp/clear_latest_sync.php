<?php
$dbPath = '/home/svnadmin/svnadmin.db';
if (!file_exists($dbPath)) {
    echo "db_missing\n";
    exit(0);
}

$db = new SQLite3($dbPath);
$db->busyTimeout(5000);

// 获取最新的运行中任务
$latest = $db->querySingle("SELECT id FROM wecom_sync_logs WHERE sync_status='running' ORDER BY id DESC LIMIT 1");

if ($latest) {
    // 清理最新的卡住任务
    $result = $db->exec("UPDATE wecom_sync_logs SET sync_status='failed', end_time=datetime('now','localtime'), summary='同步过程中异常退出' WHERE id=$latest");
    
    if ($result) {
        echo "Task $latest cleared successfully\n";
    } else {
        echo "Failed to clear task: " . $db->lastErrorMsg() . "\n";
    }
} else {
    echo "No running tasks found\n";
}

// 显示最新状态
$res = $db->query("SELECT id, sync_type, sync_status, start_time, end_time FROM wecom_sync_logs ORDER BY id DESC LIMIT 3");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
}
