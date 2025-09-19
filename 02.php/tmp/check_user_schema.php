<?php
$dbPath = '/home/svnadmin/svnadmin.db';
if (!file_exists($dbPath)) {
    echo "db_missing\n";
    exit(0);
}

$db = new SQLite3($dbPath);
$db->busyTimeout(5000);

// 查看 svn_users 表结构
echo "=== SVN Users Table Schema ===\n";
$res = $db->query("PRAGMA table_info(svn_users)");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo "Field: {$row['name']}, Type: {$row['type']}, NotNull: {$row['notnull']}, Default: {$row['dflt_value']}\n";
}

echo "\n=== SVN Groups Table Schema ===\n";
$res = $db->query("PRAGMA table_info(svn_groups)");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo "Field: {$row['name']}, Type: {$row['type']}, NotNull: {$row['notnull']}, Default: {$row['dflt_value']}\n";
}

echo "\n=== Sample SVN User Records ===\n";
$res = $db->query("SELECT svn_user_id, svn_user_name, svn_user_note FROM svn_users LIMIT 5");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
}
