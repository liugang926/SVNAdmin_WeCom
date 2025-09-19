<?php
$dbPath = '/home/svnadmin/svnadmin.db';
if (!file_exists($dbPath)) {
    echo "db_missing\n";
    exit(0);
}

$db = new SQLite3($dbPath);
$db->busyTimeout(5000);

// 清理卡住的任务（ID=3）
$result = $db->exec("UPDATE wecom_sync_logs SET sync_status='timeout', end_time=datetime('now','localtime'), summary='手动清理超时任务' WHERE id=3");

if ($result) {
    echo "Task 3 cleared successfully\n";
} else {
    echo "Failed to clear task: " . $db->lastErrorMsg() . "\n";
}

// 显示最新状态
$res = $db->query("SELECT id, sync_type, sync_status, start_time, end_time FROM wecom_sync_logs ORDER BY id DESC LIMIT 5");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
}
