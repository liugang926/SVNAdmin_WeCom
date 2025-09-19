#!/bin/bash
# 创建干净的SVNAdmin数据库

echo "Creating clean SVNAdmin database..."

# 删除旧数据库（如果存在）
if [ -f "svnadmin.db" ]; then
    echo "Removing old database..."
    rm -f svnadmin.db
fi

# 创建新的数据库
echo "Creating new database with clean schema..."
sqlite3 svnadmin.db < init_clean_db.sql

if [ $? -eq 0 ]; then
    echo "Database created successfully!"
    # 设置正确的权限
    chmod 666 svnadmin.db
    echo "Database permissions set."
else
    echo "Error creating database!"
    exit 1
fi

echo "Clean database is ready."
