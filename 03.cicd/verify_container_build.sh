#!/bin/bash
# 容器构建验证脚本 - 确保关键文件和配置正确

set -e

CONTAINER_NAME="${1:-svnadmin-test}"
LOG_FILE="container_build_verification.log"

# 日志函数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 检查容器是否运行
check_container_running() {
    if ! docker ps | grep -q "$CONTAINER_NAME"; then
        log_message "错误: 容器 $CONTAINER_NAME 未运行"
        return 1
    fi
    log_message "容器 $CONTAINER_NAME 正在运行"
    return 0
}

# 验证关键文件存在
verify_critical_files() {
    log_message "=== 验证关键文件 ==="
    
    local files=(
        "/var/www/html/templete/hooks/wecom_notify/post-commit"
        "/var/www/html/04.update/wecom-integration/database_migration.php"
        "/root/hook_manager.sh"
        "/root/start.sh"
        "/var/www/html/app/service/WeComNotification.php"
    )
    
    local missing_files=()
    
    for file in "${files[@]}"; do
        if docker exec "$CONTAINER_NAME" test -f "$file"; then
            log_message "✅ 文件存在: $file"
        else
            log_message "❌ 文件缺失: $file"
            missing_files+=("$file")
        fi
    done
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        log_message "错误: 发现 ${#missing_files[@]} 个关键文件缺失"
        return 1
    fi
    
    log_message "所有关键文件验证通过"
    return 0
}

# 验证钩子模板内容
verify_hook_template() {
    log_message "=== 验证钩子模板内容 ==="
    
    local template_file="/var/www/html/templete/hooks/wecom_notify/post-commit"
    
    # 检查UTF-8环境设置
    if docker exec "$CONTAINER_NAME" grep -q "export LANG=en_US.utf8" "$template_file"; then
        log_message "✅ 钩子模板包含UTF-8环境设置"
    else
        log_message "❌ 钩子模板缺少UTF-8环境设置"
        return 1
    fi
    
    # 检查数据库路径修复
    if docker exec "$CONTAINER_NAME" grep -q "sprintf.*database_file.*home/svnadmin" "$template_file"; then
        log_message "✅ 钩子模板包含数据库路径修复"
    else
        log_message "❌ 钩子模板缺少数据库路径修复"
        return 1
    fi
    
    # 检查是否为直接API调用模式
    if docker exec "$CONTAINER_NAME" grep -q "sendSvnNotification" "$template_file"; then
        log_message "✅ 钩子模板使用直接API调用模式"
    else
        log_message "❌ 钩子模板不是直接API调用模式"
        return 1
    fi
    
    log_message "钩子模板内容验证通过"
    return 0
}

# 验证数据库迁移脚本
verify_migration_script() {
    log_message "=== 验证数据库迁移脚本 ==="
    
    local migration_file="/var/www/html/04.update/wecom-integration/database_migration.php"
    
    # 检查是否保护现有配置
    if docker exec "$CONTAINER_NAME" grep -q "保护现有的notification_enabled设置" "$migration_file"; then
        log_message "✅ 迁移脚本包含配置保护逻辑"
    else
        log_message "❌ 迁移脚本缺少配置保护逻辑"
        return 1
    fi
    
    log_message "数据库迁移脚本验证通过"
    return 0
}

# 验证钩子管理器
verify_hook_manager() {
    log_message "=== 验证钩子管理器 ==="
    
    local hook_manager="/root/hook_manager.sh"
    
    # 检查文件权限
    if docker exec "$CONTAINER_NAME" test -x "$hook_manager"; then
        log_message "✅ 钩子管理器具有执行权限"
    else
        log_message "❌ 钩子管理器缺少执行权限"
        return 1
    fi
    
    # 测试钩子管理器功能
    if docker exec "$CONTAINER_NAME" "$hook_manager" template; then
        log_message "✅ 钩子管理器模板检查功能正常"
    else
        log_message "❌ 钩子管理器模板检查功能异常"
        return 1
    fi
    
    log_message "钩子管理器验证通过"
    return 0
}

# 验证启动脚本
verify_startup_script() {
    log_message "=== 验证启动脚本 ==="
    
    local start_script="/root/start.sh"
    
    # 检查是否包含钩子修复逻辑
    if docker exec "$CONTAINER_NAME" grep -q "hook_manager.sh smart" "$start_script"; then
        log_message "✅ 启动脚本包含智能钩子修复"
    else
        log_message "❌ 启动脚本缺少智能钩子修复"
        return 1
    fi
    
    # 检查是否包含企业微信迁移
    if docker exec "$CONTAINER_NAME" grep -q "wecom-integration/database_migration.php" "$start_script"; then
        log_message "✅ 启动脚本包含企业微信迁移"
    else
        log_message "❌ 启动脚本缺少企业微信迁移"
        return 1
    fi
    
    log_message "启动脚本验证通过"
    return 0
}

# 验证日志目录
verify_log_directories() {
    log_message "=== 验证日志目录 ==="
    
    local log_dirs=(
        "/var/www/html/logs"
    )
    
    for dir in "${log_dirs[@]}"; do
        if docker exec "$CONTAINER_NAME" test -d "$dir"; then
            log_message "✅ 日志目录存在: $dir"
            
            # 检查权限
            local owner=$(docker exec "$CONTAINER_NAME" stat -c "%U:%G" "$dir")
            if [ "$owner" = "apache:apache" ]; then
                log_message "✅ 日志目录权限正确: $dir ($owner)"
            else
                log_message "⚠️  日志目录权限可能有问题: $dir ($owner)"
            fi
        else
            log_message "❌ 日志目录缺失: $dir"
            return 1
        fi
    done
    
    log_message "日志目录验证通过"
    return 0
}

# 模拟钩子修复测试
test_hook_repair() {
    log_message "=== 测试钩子修复功能 ==="
    
    # 运行智能修复
    if docker exec "$CONTAINER_NAME" /root/hook_manager.sh smart; then
        log_message "✅ 智能钩子修复执行成功"
    else
        log_message "❌ 智能钩子修复执行失败"
        return 1
    fi
    
    # 检查修复日志
    if docker exec "$CONTAINER_NAME" test -f "/var/www/html/logs/hook_manager.log"; then
        log_message "✅ 钩子修复日志文件已创建"
        
        # 显示最后几行日志
        log_message "钩子修复日志摘要:"
        docker exec "$CONTAINER_NAME" tail -5 "/var/www/html/logs/hook_manager.log" | while read line; do
            log_message "  $line"
        done
    else
        log_message "⚠️  钩子修复日志文件未找到"
    fi
    
    log_message "钩子修复功能测试完成"
    return 0
}

# 主验证流程
main() {
    log_message "开始容器构建验证: $CONTAINER_NAME"
    
    local failed_checks=0
    
    # 执行各项检查
    check_container_running || ((failed_checks++))
    verify_critical_files || ((failed_checks++))
    verify_hook_template || ((failed_checks++))
    verify_migration_script || ((failed_checks++))
    verify_hook_manager || ((failed_checks++))
    verify_startup_script || ((failed_checks++))
    verify_log_directories || ((failed_checks++))
    test_hook_repair || ((failed_checks++))
    
    # 总结
    log_message "=== 验证总结 ==="
    if [ $failed_checks -eq 0 ]; then
        log_message "🎉 所有验证项目通过！容器构建配置正确。"
        log_message "容器重构后不会丢失企业微信通知功能。"
        return 0
    else
        log_message "❌ 发现 $failed_checks 个问题，需要修复后重新构建容器。"
        return 1
    fi
}

# 显示使用说明
show_usage() {
    echo "用法: $0 [容器名称]"
    echo ""
    echo "验证容器构建配置，确保企业微信通知功能不会在重构后丢失"
    echo ""
    echo "参数:"
    echo "  容器名称    要验证的容器名称（默认: svnadmin-test）"
    echo ""
    echo "示例:"
    echo "  $0                    # 验证默认容器 svnadmin-test"
    echo "  $0 my-svnadmin        # 验证指定容器 my-svnadmin"
}

# 处理命令行参数
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    show_usage
    exit 0
fi

# 执行主函数
main "$@"
