<?php
$dbPath = '/home/svnadmin/svnadmin.db';
if (!file_exists($dbPath)) {
    echo "db_missing\n";
    exit(0);
}

$db = new SQLite3($dbPath);
$db->busyTimeout(5000);
$exists = (int)$db->querySingle("SELECT COUNT(1) FROM sqlite_master WHERE type='table' AND name='wecom_sync_logs'");
echo "exists=".$exists."\n";
if ($exists) {
    $res = $db->query("SELECT id, sync_type, sync_status, start_time, end_time FROM wecom_sync_logs ORDER BY id DESC LIMIT 10");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
    }
}

