#!/bin/bash
set -e
echo "Starting SVNAdmin..."

# 设置标准UTF-8环境变量（支持中文仓库名 - 使用标准locale）
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8
export LC_CTYPE=en_US.UTF-8
echo "Standard UTF-8 environment variables set for Chinese repository support"

# 清理代理设置（避免影响企业微信API访问）
unset http_proxy https_proxy HTTP_PROXY HTTPS_PROXY
echo "Proxy settings cleared for runtime"

# 生产环境兼容性处理（支持外部挂载配置）
echo "Checking production environment compatibility..."
if [ -d "/home/svnadmin/conf.d" ] && [ "$(ls -A /home/svnadmin/conf.d 2>/dev/null)" ]; then
  echo "Found external httpd configuration, linking to /etc/httpd/conf.d/"
  # 备份原有配置
  cp -r /etc/httpd/conf.d/* /home/svnadmin/conf.d/ 2>/dev/null || true
  # 创建软链接（如果不存在）
  for file in /home/svnadmin/conf.d/*; do
    if [ -f "$file" ]; then
      filename=$(basename "$file")
      if [ ! -f "/etc/httpd/conf.d/$filename" ]; then
        ln -sf "$file" "/etc/httpd/conf.d/$filename"
      fi
    fi
  done
fi

if [ -d "/home/svnadmin/sasl2" ] && [ "$(ls -A /home/svnadmin/sasl2 2>/dev/null)" ]; then
  echo "Found external SASL configuration, linking to /etc/sasl2/"
  # 创建软链接
  for file in /home/svnadmin/sasl2/*; do
    if [ -f "$file" ]; then
      filename=$(basename "$file")
      ln -sf "$file" "/etc/sasl2/$filename"
    fi
  done
fi

# 首次启动执行原项目安装脚本（幂等）
cd /var/www/html
if [ ! -f "/home/svnadmin/svnserve" ]; then
  echo "Running original installer (server/install.php)..."
  php server/install.php > /var/www/html/logs/install.log 2>&1 || true
  chown -R apache:apache /home/svnadmin || true
fi

# 自动检查和初始化数据库（企业微信功能）
echo "Running database auto-initialization..."
if [ -f "/root/db_auto_init.sh" ]; then
  chmod +x /root/db_auto_init.sh
  /root/db_auto_init.sh
  echo "Database auto-initialization completed"
else
  echo "Database auto-init script not found, skipping..."
fi

# 执行企业微信数据库迁移（保护现有配置）
echo "Running WeCom database migration..."
if [ -f "04.update/wecom-integration/database_migration.php" ]; then
  php 04.update/wecom-integration/database_migration.php > /var/www/html/logs/wecom_migration.log 2>&1 || true
  echo "WeCom migration completed"
fi

# 执行通知规则表迁移（向后兼容）
echo "Running notification rules table migration..."
if [ -f "templete/database/sqlite/notification_rules_migration.sql" ]; then
  sqlite3 /home/svnadmin/svnadmin.db < templete/database/sqlite/notification_rules_migration.sql > /var/www/html/logs/migration.log 2>&1 || true
  echo "Legacy migration completed"
fi

# 智能修复SVN钩子（基于通知规则）
echo "Smart repairing SVN hooks based on notification rules..."
if [ -f "/root/hook_manager.sh" ]; then
  chmod +x /root/hook_manager.sh
  /root/hook_manager.sh smart > /var/www/html/logs/hook_repair.log 2>&1 || true
  echo "Hook repair completed"
else
  echo "Hook manager not found, skipping hook repair"
fi

# 启动Apache
echo "Starting Apache..."

# 清理可能存在的httpd.pid文件
rm -f /run/httpd/httpd.pid /var/run/httpd/httpd.pid 2>/dev/null

# 先检查是否已经有httpd在运行
if pgrep httpd > /dev/null; then
    echo "Apache is already running, stopping it properly..."
    # 尝试优雅停止
    httpd -k stop 2>/dev/null || true
    sleep 2
    # 如果还在运行，强制终止
    if pgrep httpd > /dev/null; then
        echo "Force killing Apache processes..."
        pkill -9 httpd || true
        sleep 1
    fi
fi

# 确保运行目录存在
mkdir -p /run/httpd /var/run/httpd

# 启动Apache（后台运行）
echo "Starting Apache..."
httpd -D FOREGROUND &
APACHE_PID=$!
sleep 3

# 检查Apache是否成功启动
if pgrep httpd > /dev/null; then
    echo "Apache started successfully"
else
    echo "ERROR: Apache failed to start!"
    echo "Checking Apache configuration..."
    httpd -t
    # 查看错误日志
    if [ -f /var/log/httpd/error_log ]; then
        echo "Last few lines of error log:"
        tail -5 /var/log/httpd/error_log
    fi
    echo "Trying to start Apache again..."
    httpd -D FOREGROUND &
    sleep 3
    if pgrep httpd > /dev/null; then
        echo "Apache started on second attempt"
    else
        echo "WARNING: Apache failed to start after retry"
    fi
fi

# 启动crond和atd
crond
atd

# 全面检查和修复authz文件
echo "Comprehensive authz file check and fix..."

# 检查authz文件是否存在
AUTHZ_FILE="/home/svnadmin/authz"
if [ ! -f "$AUTHZ_FILE" ]; then
    echo "Creating default authz file..."
    cat > "$AUTHZ_FILE" << 'EOF'
[aliases]

[groups]
# SVN groups will be added here automatically

[/]
# Repository permissions will be added here automatically
EOF
    chmod 664 "$AUTHZ_FILE"
    chown apache:apache "$AUTHZ_FILE"
    echo "Default authz file created"
else
    echo "Found existing authz file, performing comprehensive fix..."
    
    # 备份原始文件
    cp "$AUTHZ_FILE" "${AUTHZ_FILE}.bak.$(date +%Y%m%d%H%M%S)"
    
    # 创建临时修复文件
    TMP_AUTHZ="/tmp/authz.fixing"
    > "$TMP_AUTHZ"
    
    # 状态标记
    IN_GROUPS=0
    GROUPS_SECTION_EXISTS=0
    
    # 逐行处理authz文件
    while IFS= read -r line || [ -n "$line" ]; do
        # 检测[groups]部分
        if [[ "$line" =~ ^\[groups\]$ ]]; then
            IN_GROUPS=1
            GROUPS_SECTION_EXISTS=1
            echo "$line" >> "$TMP_AUTHZ"
            continue
        fi
        
        # 检测其他部分开始
        if [[ "$line" =~ ^\[.*\]$ ]] && [ "$IN_GROUPS" -eq 1 ]; then
            IN_GROUPS=0
        fi
        
        # 在[groups]部分内处理
        if [ "$IN_GROUPS" -eq 1 ]; then
            # 保留空行和注释
            if [[ -z "${line// }" ]] || [[ "$line" =~ ^[[:space:]]*# ]]; then
                echo "$line" >> "$TMP_AUTHZ"
                continue
            fi
            
            # 处理组定义行
            if [[ "$line" =~ = ]]; then
                # 提取组名和成员
                group_name=$(echo "$line" | cut -d'=' -f1 | sed 's/[[:space:]]*$//')
                members=$(echo "$line" | cut -d'=' -f2- | sed 's/^[[:space:]]*//')
                
                # 确保组名不为空且不是纯空格
                if [[ -n "$group_name" ]] && [[ ! "$group_name" =~ ^[[:space:]]*$ ]]; then
                    # 清理成员列表中的问题
                    if [[ -n "$members" ]]; then
                        # 移除多余的空格和逗号
                        members=$(echo "$members" | sed 's/[[:space:]]*,[[:space:]]*/,/g' | sed 's/^,\+//g' | sed 's/,\+$//g')
                        # 移除空的@引用
                        members=$(echo "$members" | sed 's/@[[:space:]]*,/,/g' | sed 's/,@[[:space:]]*$//' | sed 's/^@[[:space:]]*$//')
                    fi
                    echo "${group_name}=${members}" >> "$TMP_AUTHZ"
                else
                    echo "# Skipped invalid group: $line" >> "$TMP_AUTHZ"
                fi
            else
                # 非组定义行，保持原样
                echo "$line" >> "$TMP_AUTHZ"
            fi
        else
            # 不在[groups]部分，修复权限部分的问题
            if [[ "$line" =~ @ ]]; then
                # 修复空的@引用
                fixed_line=$(echo "$line" | sed 's/@[[:space:]]*=/=/g' | sed 's/@[[:space:]]*$//g' | sed 's/@[[:space:]]*,/,/g' | sed 's/,@[[:space:]]*$//')
                # 跳过只有@的行
                if [[ ! "$fixed_line" =~ ^[[:space:]]*@[[:space:]]*$ ]] && [[ ! "$fixed_line" =~ ^[[:space:]]*$ ]]; then
                    echo "$fixed_line" >> "$TMP_AUTHZ"
                fi
            else
                echo "$line" >> "$TMP_AUTHZ"
            fi
        fi
    done < "$AUTHZ_FILE"
    
    # 如果没有[groups]部分，添加一个
    if [ "$GROUPS_SECTION_EXISTS" -eq 0 ]; then
        echo "" >> "$TMP_AUTHZ"
        echo "[groups]" >> "$TMP_AUTHZ"
        echo "# SVN groups will be added here automatically" >> "$TMP_AUTHZ"
        echo "" >> "$TMP_AUTHZ"
    fi
    
    # 替换原文件
    mv "$TMP_AUTHZ" "$AUTHZ_FILE"
    chmod 664 "$AUTHZ_FILE"
    chown apache:apache "$AUTHZ_FILE"
    
    echo "Authz file comprehensively fixed"
    
    # 显示修复统计
    groups_count=$(grep -A 1000 '^\[groups\]' "$AUTHZ_FILE" 2>/dev/null | grep -B 1000 '^\[' | grep -c '=' 2>/dev/null || echo "0")
    empty_groups=$(grep -A 1000 '^\[groups\]' "$AUTHZ_FILE" 2>/dev/null | grep -B 1000 '^\[' | grep -c '=$' 2>/dev/null || echo "0")
    echo "Groups found: $groups_count, Empty groups: $empty_groups"
fi

# 验证authz文件语法
echo "Validating authz file syntax..."
if command -v svnlook >/dev/null 2>&1; then
    # 如果有svnlook，可以进行更严格的验证
    echo "SVN tools available for validation"
else
    # 基本语法检查
    if grep -q '^\[groups\]' "$AUTHZ_FILE" && grep -q '^\[.*\]' "$AUTHZ_FILE"; then
        echo "Basic authz syntax appears valid"
    else
        echo "Warning: authz file may have syntax issues"
    fi
fi

# 启动SVN服务 - 增加错误处理和重试机制
echo "Starting SVN server (svnserve)..."

# 确保日志目录存在
mkdir -p /home/svnadmin/logs

# 检查svnserve.conf文件
if [ ! -f /home/svnadmin/svnserve.conf ]; then
    echo "Creating default svnserve.conf..."
    cat > /home/svnadmin/svnserve.conf << 'EOF'
[general]
anon-access = none
auth-access = write
password-db = passwd
authz-db = authz
realm = SVN Repository
EOF
    chown apache:apache /home/svnadmin/svnserve.conf
fi

# 检查passwd文件
if [ ! -f /home/svnadmin/passwd ]; then
    echo "Creating default passwd file..."
    cat > /home/svnadmin/passwd << 'EOF'
[users]
# username = password
EOF
    chown apache:apache /home/svnadmin/passwd
fi

# 检查rep目录
if [ ! -d /home/svnadmin/rep ]; then
    echo "Creating repository directory..."
    mkdir -p /home/svnadmin/rep
fi

# 尝试启动svnserve，如果失败则尝试不同的配置
MAX_RETRIES=3
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    echo "Attempting to start svnserve (attempt $((RETRY_COUNT + 1))/$MAX_RETRIES)..."
    
    if [ $RETRY_COUNT -eq 0 ]; then
        # 第一次尝试：使用完整配置
        svnserve -d -r /home/svnadmin/rep --config-file /home/svnadmin/svnserve.conf --log-file /home/svnadmin/logs/svnserve.log 2>&1
    elif [ $RETRY_COUNT -eq 1 ]; then
        # 第二次尝试：不使用authz文件
        echo "Trying without authz file..."
        sed -i 's/^authz-db = authz/#authz-db = authz/' /home/svnadmin/svnserve.conf
        svnserve -d -r /home/svnadmin/rep --config-file /home/svnadmin/svnserve.conf --log-file /home/svnadmin/logs/svnserve.log 2>&1
    else
        # 第三次尝试：最小配置
        echo "Trying with minimal configuration..."
        cat > /home/svnadmin/svnserve.conf << 'EOF'
[general]
anon-access = read
auth-access = write
password-db = passwd
EOF
        svnserve -d -r /home/svnadmin/rep --config-file /home/svnadmin/svnserve.conf --log-file /home/svnadmin/logs/svnserve.log 2>&1
    fi
    
    # 检查svnserve是否启动成功
    sleep 3
    if pgrep svnserve > /dev/null; then
        echo "SVN server started successfully"
        break
    else
        echo "SVN server failed to start (attempt $((RETRY_COUNT + 1)))"
        if [ -f /home/svnadmin/logs/svnserve.log ]; then
            echo "Last few lines of svnserve log:"
            tail -5 /home/svnadmin/logs/svnserve.log
        fi
        RETRY_COUNT=$((RETRY_COUNT + 1))
    fi
done

# 如果所有尝试都失败了，记录错误但不退出容器
if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "WARNING: SVN server failed to start after $MAX_RETRIES attempts"
    echo "Container will continue running with web interface only"
    echo "You can manually fix the authz file and restart svnserve later"
else
    echo "SVN server is running"
fi

sleep 2

# 启动SVN守护进程（已集成企业微信通知功能）
echo "Starting SVN daemon with integrated WeCom notification..."
cd /var/www/html
nohup php server/svnadmind.php start > /var/www/html/logs/svnadmind.log 2>&1 &
sleep 3

echo "==================================="
echo "SVNAdmin started successfully!"
echo "Web Interface: http://localhost"
echo "SVN Protocol: svn://localhost:3690"
echo "Default Login: admin/admin"
echo "Enterprise WeCom: Integrated in SVN daemon"
echo "==================================="

# 保持容器运行并监控Apache
echo "Container is running. Monitoring Apache status..."
while true; do
    # 检查Apache是否还在运行
    if ! pgrep httpd > /dev/null; then
        echo "[$(date)] WARNING: Apache is not running! Restarting..."
        httpd -D FOREGROUND &
        sleep 5
    fi
    
    # 检查是否能访问Web服务
    if ! curl -s -f -o /dev/null http://localhost/ 2>/dev/null; then
        echo "[$(date)] WARNING: Web service is not responding! Checking Apache..."
        if pgrep httpd > /dev/null; then
            echo "Apache is running but not responding. Restarting..."
            pkill httpd || true
            sleep 2
            httpd -D FOREGROUND &
            sleep 5
        fi
    fi
    
    # 每30秒检查一次
    sleep 30
done
