#!/bin/bash

# SVNAdmin 数据库自动初始化脚本
# 在容器启动时自动检查和补充缺失的数据库表和字段

DB_PATH="/home/svnadmin/svnadmin.db"
WECOM_SQL_PATH="/var/www/html/templete/database/sqlite/wecom_tables.sql"
LOG_FILE="/var/www/html/logs/db_init.log"

# 日志函数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 检查数据库文件是否存在
check_database_exists() {
    if [ ! -f "$DB_PATH" ]; then
        log "❌ 数据库文件不存在: $DB_PATH"
        return 1
    fi
    log "✅ 数据库文件存在: $DB_PATH"
    return 0
}

# 检查表是否存在
check_table_exists() {
    local table_name="$1"
    local result=$(sqlite3 "$DB_PATH" "SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name';" 2>/dev/null)
    if [ -z "$result" ]; then
        return 1  # 表不存在
    else
        return 0  # 表存在
    fi
}

# 检查字段是否存在
check_column_exists() {
    local table_name="$1"
    local column_name="$2"
    local result=$(sqlite3 "$DB_PATH" "PRAGMA table_info($table_name);" 2>/dev/null | grep -i "$column_name")
    if [ -z "$result" ]; then
        return 1  # 字段不存在
    else
        return 0  # 字段存在
    fi
}

# 执行SQL文件
execute_sql_file() {
    local sql_file="$1"
    local description="$2"
    
    if [ ! -f "$sql_file" ]; then
        log "⚠️  SQL文件不存在: $sql_file"
        return 1
    fi
    
    log "🔧 执行SQL: $description"
    if sqlite3 "$DB_PATH" ".read $sql_file" 2>/dev/null; then
        log "✅ SQL执行成功: $description"
        return 0
    else
        log "❌ SQL执行失败: $description"
        return 1
    fi
}

# 创建企业微信配置记录
create_wecom_config() {
    log "🔧 创建企业微信默认配置..."
    
    # 检查是否已有配置记录
    local existing_config=$(sqlite3 "$DB_PATH" "SELECT id FROM wecom_config LIMIT 1;" 2>/dev/null)
    
    if [ -z "$existing_config" ]; then
        # 创建默认配置记录
        sqlite3 "$DB_PATH" "
        INSERT INTO wecom_config (
            id, sync_enabled, notification_enabled, 
            config_data, created_at, updated_at
        ) VALUES (
            1, 0, 0, 
            '{\"department_mapping\":{\"group_name_prefix\":\"\",\"auto_create_groups\":true,\"sync_hierarchy\":true,\"root_department_id\":1}}',
            datetime('now', 'localtime'),
            datetime('now', 'localtime')
        );" 2>/dev/null
        
        if [ $? -eq 0 ]; then
            log "✅ 企业微信默认配置创建成功"
        else
            log "❌ 企业微信默认配置创建失败"
        fi
    else
        log "✅ 企业微信配置已存在，跳过创建"
        
        # 检查并更新config_data字段，确保group_name_prefix为空
        sqlite3 "$DB_PATH" "
        UPDATE wecom_config SET 
            config_data = '{\"department_mapping\":{\"group_name_prefix\":\"\",\"auto_create_groups\":true,\"sync_hierarchy\":true,\"root_department_id\":1}}',
            updated_at = datetime('now', 'localtime')
        WHERE id = 1 AND (config_data = '{}' OR config_data IS NULL OR config_data = '');" 2>/dev/null
        
        log "✅ 企业微信配置已更新（确保无前缀）"
    fi
}

# 检测是否为新环境（无业务数据）
is_new_environment() {
    local user_count=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM svn_users;" 2>/dev/null || echo "0")
    local repo_count=$(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM svn_reps;" 2>/dev/null || echo "0")
    
    if [ "$user_count" -eq 0 ] && [ "$repo_count" -eq 0 ]; then
        return 0  # 新环境
    else
        return 1  # 已有环境
    fi
}

# 主要的数据库检查和初始化逻辑
main() {
    log "=== SVNAdmin 数据库自动初始化开始 ==="
    
    # 检查数据库文件
    if ! check_database_exists; then
        log "❌ 数据库文件不存在，无法继续"
        return 1
    fi
    
    # 设置数据库权限
    chown apache:apache "$DB_PATH" 2>/dev/null || true
    chmod 664 "$DB_PATH" 2>/dev/null || true
    
    # 检测环境类型
    if is_new_environment; then
        log "🆕 检测到新环境，将创建干净的初始配置"
    else
        log "🔄 检测到已有环境，将保留现有数据并补充缺失项"
    fi
    
    # 检查企业微信相关表
    local wecom_tables=("wecom_config" "wecom_departments" "wecom_users" "wecom_notification_rules" "wecom_notification_logs" "wecom_sync_logs" "wecom_api_logs" "wecom_notification_queue")
    local missing_tables=()
    
    for table in "${wecom_tables[@]}"; do
        if ! check_table_exists "$table"; then
            missing_tables+=("$table")
            log "⚠️  缺失表: $table"
        else
            log "✅ 表存在: $table"
        fi
    done
    
    # 如果有缺失的表，执行创建脚本
    if [ ${#missing_tables[@]} -gt 0 ]; then
        log "🔧 发现 ${#missing_tables[@]} 个缺失的企业微信表，开始创建..."
        
        if execute_sql_file "$WECOM_SQL_PATH" "企业微信表结构"; then
            log "✅ 企业微信表创建完成"
            
            # 创建默认配置
            create_wecom_config
        else
            log "❌ 企业微信表创建失败"
        fi
    else
        log "✅ 所有企业微信表都存在"
        
        # 即使表存在，也检查配置
        create_wecom_config
    fi
    
    # 检查核心SVN表（防止数据库损坏）
    local core_tables=("admin_users" "svn_groups" "svn_users" "svn_reps")
    local missing_core_tables=()
    
    for table in "${core_tables[@]}"; do
        if ! check_table_exists "$table"; then
            missing_core_tables+=("$table")
            log "⚠️  缺失核心表: $table"
        fi
    done
    
    if [ ${#missing_core_tables[@]} -gt 0 ]; then
        log "❌ 发现 ${#missing_core_tables[@]} 个缺失的核心表，数据库可能损坏"
        log "建议检查数据库完整性或重新初始化"
    else
        log "✅ 所有核心表都存在"
    fi
    
    # 根据环境类型处理SVN配置文件
    log "🔧 检查SVN配置文件格式..."
    
    # 检查passwd文件
    if [ ! -f "/home/svnadmin/passwd" ]; then
        log "🔧 创建passwd文件..."
        echo '[users]' > /home/svnadmin/passwd
        if is_new_environment; then
            echo '# SVN users will be added here automatically' >> /home/svnadmin/passwd
        fi
        chown apache:apache /home/svnadmin/passwd 2>/dev/null || true
        chmod 664 /home/svnadmin/passwd 2>/dev/null || true
    elif ! grep -q "^\[users\]" "/home/svnadmin/passwd" 2>/dev/null; then
        log "🔧 修复passwd文件格式（保留现有数据）..."
        # 备份原文件
        cp /home/svnadmin/passwd /home/svnadmin/passwd.backup
        # 在文件开头添加[users]标识
        echo '[users]' > /home/svnadmin/passwd.tmp
        cat /home/svnadmin/passwd.backup >> /home/svnadmin/passwd.tmp
        mv /home/svnadmin/passwd.tmp /home/svnadmin/passwd
        chown apache:apache /home/svnadmin/passwd 2>/dev/null || true
        chmod 664 /home/svnadmin/passwd 2>/dev/null || true
    fi
    
    # 检查authz文件
    if [ ! -f "/home/svnadmin/authz" ]; then
        log "🔧 创建authz文件..."
        echo '[aliases]' > /home/svnadmin/authz
        echo '' >> /home/svnadmin/authz
        echo '[groups]' >> /home/svnadmin/authz
        if is_new_environment; then
            echo '# SVN groups will be added here automatically' >> /home/svnadmin/authz
        fi
        echo '' >> /home/svnadmin/authz
        echo '[/]' >> /home/svnadmin/authz
        if is_new_environment; then
            echo '# Repository permissions will be added here automatically' >> /home/svnadmin/authz
        fi
        chown apache:apache /home/svnadmin/authz 2>/dev/null || true
        chmod 664 /home/svnadmin/authz 2>/dev/null || true
    elif ! grep -q "^\[aliases\]" "/home/svnadmin/authz" 2>/dev/null; then
        log "🔧 修复authz文件格式（保留现有数据）..."
        # 备份原文件
        cp /home/svnadmin/authz /home/svnadmin/authz.backup
        # 在文件开头添加必要的标识
        echo '[aliases]' > /home/svnadmin/authz.tmp
        echo '' >> /home/svnadmin/authz.tmp
        echo '[groups]' >> /home/svnadmin/authz.tmp
        cat /home/svnadmin/authz.backup >> /home/svnadmin/authz.tmp
        mv /home/svnadmin/authz.tmp /home/svnadmin/authz
        chown apache:apache /home/svnadmin/authz 2>/dev/null || true
        chmod 664 /home/svnadmin/authz 2>/dev/null || true
    fi
    
    log "✅ SVN配置文件格式检查完成"

    # 最终权限设置
    chown apache:apache "$DB_PATH" 2>/dev/null || true
    chmod 664 "$DB_PATH" 2>/dev/null || true
    
    log "=== SVNAdmin 数据库自动初始化完成 ==="
    return 0
}

# 执行主函数
main "$@"
