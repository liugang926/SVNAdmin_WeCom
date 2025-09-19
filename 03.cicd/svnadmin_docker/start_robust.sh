#!/bin/bash
# This script will be executed when the container starts.

# 清除代理设置，避免影响企业微信API调用
echo "Clearing proxy settings for runtime..."
unset http_proxy
unset https_proxy
unset HTTP_PROXY
unset HTTPS_PROXY
unset no_proxy
unset NO_PROXY

# 检查生产环境兼容性
echo "Checking production environment compatibility..."

# 检查并创建必要的目录
[ ! -d "/home/svnadmin/logs" ] && mkdir -p /home/svnadmin/logs && chmod 755 /home/svnadmin/logs

# 检查并链接配置文件
if [ -d "/home/svnadmin/conf.d" ] && [ -d "/etc/httpd/conf.d" ]; then
    echo "Found external httpd configuration, linking to /etc/httpd/conf.d/"
    # 使用软链接而不是硬拷贝，保持配置同步
    for conf in /home/svnadmin/conf.d/*.conf; do
        if [ -f "$conf" ]; then
            conf_name=$(basename "$conf")
            ln -sf "$conf" "/etc/httpd/conf.d/$conf_name"
        fi
    done
fi

# 运行数据库自动初始化
echo "Running database auto-initialization..."
cd /var/www/html && php scripts/database_auto_init.php

# 迁移企业微信数据库
echo "Running WeCom database migration..."
cd /var/www/html && php scripts/database_wecom_migration.php

# 运行通知规则表迁移
echo "Running notification rules table migration..."
cd /var/www/html && php scripts/database_notification_rules_migration.php

# 修复旧版本容器升级后的权限问题
echo "Running legacy migration..."
cd /var/www/html && php scripts/database_wecom_legacy_migration.php

# 智能修复SVN钩子
echo "Smart repairing SVN hooks based on notification rules..."
cd /var/www/html && php scripts/smart_repair_svn_hooks.php

# 启动Apache
echo "Starting Apache..."
httpd -k start

# 检查Apache是否成功启动
sleep 3
if ! pgrep httpd > /dev/null; then
    echo "Apache failed to start, checking error log:"
    tail -20 /var/log/httpd/error_log
    exit 1
fi

# 启动PHP-FPM（如果需要）
if [ -f /usr/sbin/php-fpm ]; then
    echo "Starting PHP-FPM..."
    /usr/sbin/php-fpm
fi

# 启动crond和atd
crond
atd

# 修复authz文件中的空分组引用问题 - 更彻底的修复
echo "Thoroughly fixing authz file..."
if [ -f /home/svnadmin/authz ]; then
    # 备份原始文件
    cp /home/svnadmin/authz /home/svnadmin/authz.bak.$(date +%Y%m%d%H%M%S)
    
    # 创建临时文件
    TMP_AUTHZ="/tmp/authz.cleaned"
    > "$TMP_AUTHZ"
    
    # 状态标记
    IN_GROUPS=0
    
    # 逐行处理authz文件
    while IFS= read -r line; do
        # 检测[groups]部分
        if [[ "$line" =~ ^\[groups\]$ ]]; then
            IN_GROUPS=1
            echo "$line" >> "$TMP_AUTHZ"
            continue
        fi
        
        # 检测其他部分开始
        if [[ "$line" =~ ^\[.*\]$ ]] && [ "$IN_GROUPS" -eq 1 ]; then
            IN_GROUPS=0
        fi
        
        # 在[groups]部分内
        if [ "$IN_GROUPS" -eq 1 ]; then
            # 跳过空行和注释
            if [[ -z "${line// }" ]] || [[ "$line" =~ ^[[:space:]]*# ]]; then
                echo "$line" >> "$TMP_AUTHZ"
                continue
            fi
            
            # 处理组定义
            if [[ "$line" =~ = ]]; then
                group_name=$(echo "$line" | cut -d'=' -f1 | sed 's/[[:space:]]*$//')
                members=$(echo "$line" | cut -d'=' -f2- | sed 's/^[[:space:]]*//')
                
                # 确保组名不为空
                if [[ ! -z "$group_name" ]] && [[ ! "$group_name" =~ ^[[:space:]]+$ ]]; then
                    echo "${group_name}=${members}" >> "$TMP_AUTHZ"
                fi
            else
                echo "$line" >> "$TMP_AUTHZ"
            fi
        else
            # 不在[groups]部分，修复空的@引用
            fixed_line=$(echo "$line" | sed 's/@[[:space:]]*=/=/g' | sed 's/@[[:space:]]*$//g')
            # 跳过只有@的行
            if [[ ! "$fixed_line" =~ ^[[:space:]]*@[[:space:]]*$ ]]; then
                echo "$fixed_line" >> "$TMP_AUTHZ"
            fi
        fi
    done < /home/svnadmin/authz
    
    # 替换原文件
    mv "$TMP_AUTHZ" /home/svnadmin/authz
    
    echo "Authz file thoroughly fixed"
    
    # 显示修复后的统计
    echo "Groups count: $(grep -A 1000 '^\[groups\]' /home/svnadmin/authz | grep -B 1000 '^\[' | grep -c '=')"
    echo "Empty groups: $(grep -A 1000 '^\[groups\]' /home/svnadmin/authz | grep -B 1000 '^\[' | grep -c '=$')"
else
    echo "Warning: authz file not found at /home/svnadmin/authz"
fi

# 启动SVN服务 - 添加错误处理
echo "Starting SVN server (svnserve)..."
if svnserve -d -r /home/svnadmin/rep --config-file /home/svnadmin/svnserve.conf --log-file /home/svnadmin/logs/svnserve.log 2>&1; then
    echo "SVN server started successfully"
else
    echo "WARNING: SVN server failed to start, but continuing..."
    echo "Error details:"
    cat /home/svnadmin/logs/svnserve.log 2>/dev/null || echo "No log file found"
    
    # 尝试最小化启动svnserve（不使用authz）
    echo "Attempting to start svnserve without authz..."
    svnserve -d -r /home/svnadmin/rep --log-file /home/svnadmin/logs/svnserve.log 2>&1 || true
fi

sleep 2

# 启动SVN守护进程（已集成企业微信通知功能）
echo "Starting SVNAdmin..."

# 清除运行时的代理设置
echo "Proxy settings cleared for runtime"

# 保持容器运行 - 即使svnserve失败也继续
echo "Container is running. Monitoring logs..."
tail -f /var/log/httpd/access_log /var/log/httpd/error_log /home/svnadmin/logs/svnserve.log 2>/dev/null || tail -f /var/log/httpd/access_log /var/log/httpd/error_log
