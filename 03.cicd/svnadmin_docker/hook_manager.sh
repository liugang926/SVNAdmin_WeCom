#!/bin/bash
# SVN钩子管理脚本 - 确保容器重构后钩子不丢失

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOOKS_TEMPLATE_DIR="/var/www/html/templete/hooks"
SVN_REP_BASE="/home/svnadmin/rep"
LOG_FILE="/var/www/html/logs/hook_manager.log"

# 日志函数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 检查并创建目录
ensure_directory() {
    local dir="$1"
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        log_message "创建目录: $dir"
    fi
}

# 检查钩子模板是否存在且为最新版本
check_hook_template() {
    local template_file="$HOOKS_TEMPLATE_DIR/wecom_notify/post-commit"
    
    if [ ! -f "$template_file" ]; then
        log_message "错误: 钩子模板文件不存在: $template_file"
        return 1
    fi
    
    # 检查模板是否包含UTF-8环境设置（验证是否为最新版本）
    if ! grep -q "export LANG=en_US.utf8" "$template_file"; then
        log_message "警告: 钩子模板可能不是最新版本"
        return 1
    fi
    
    log_message "钩子模板检查通过: $template_file"
    return 0
}

# 扫描所有SVN仓库
scan_repositories() {
    local repos=()
    
    if [ ! -d "$SVN_REP_BASE" ]; then
        log_message "SVN仓库基础目录不存在: $SVN_REP_BASE"
        return 1
    fi
    
    for item in "$SVN_REP_BASE"/*; do
        if [ -d "$item" ]; then
            local repo_name=$(basename "$item")
            # 检查是否是有效的SVN仓库
            if [ -d "$item/hooks" ] && [ -d "$item/conf" ] && [ -d "$item/db" ]; then
                repos+=("$repo_name")
                log_message "发现SVN仓库: $repo_name"
            fi
        fi
    done
    
    echo "${repos[@]}"
}

# 检查仓库的钩子状态
check_repository_hooks() {
    local repo_name="$1"
    local repo_path="$SVN_REP_BASE/$repo_name"
    local hooks_dir="$repo_path/hooks"
    local post_commit="$hooks_dir/post-commit"
    
    log_message "检查仓库钩子: $repo_name"
    
    if [ ! -f "$post_commit" ]; then
        log_message "  缺少post-commit钩子: $repo_name"
        return 1
    fi
    
    if [ ! -x "$post_commit" ]; then
        log_message "  post-commit钩子不可执行: $repo_name"
        return 2
    fi
    
    # 检查钩子内容类型
    if grep -q "SVNAdmin Hook Dispatcher" "$post_commit"; then
        log_message "  使用调度器模式: $repo_name"
        
        # 检查企业微信子钩子
        local sub_hook="$hooks_dir/post-commit.d/50-wecom-notification.sh"
        if [ ! -f "$sub_hook" ]; then
            log_message "  缺少企业微信子钩子: $repo_name"
            return 3
        fi
        
        return 10  # 调度器模式正常
        
    elif grep -q "正确UTF-8环境的企业微信通知钩子" "$post_commit"; then
        log_message "  使用直接钩子模式: $repo_name"
        return 20  # 直接钩子模式正常
        
    else
        log_message "  未知钩子类型: $repo_name"
        return 4
    fi
}

# 为仓库安装/修复钩子
install_repository_hook() {
    local repo_name="$1"
    local repo_path="$SVN_REP_BASE/$repo_name"
    local hooks_dir="$repo_path/hooks"
    local post_commit="$hooks_dir/post-commit"
    local template_file="$HOOKS_TEMPLATE_DIR/wecom_notify/post-commit"
    
    log_message "为仓库安装钩子: $repo_name"
    
    # 备份现有钩子（如果存在）
    if [ -f "$post_commit" ]; then
        local backup_file="$post_commit.backup.$(date +%Y%m%d_%H%M%S)"
        cp "$post_commit" "$backup_file"
        log_message "  备份现有钩子: $backup_file"
    fi
    
    # 复制模板文件
    cp "$template_file" "$post_commit"
    
    # 修复行结束符（防止Windows/Linux兼容性问题）
    sed -i 's/\r$//' "$post_commit" 2>/dev/null || true
    
    # 设置权限
    chmod 755 "$post_commit"
    chown apache:apache "$post_commit" 2>/dev/null || true
    
    log_message "  直接API钩子安装完成: $repo_name"
    return 0
}

# 批量检查所有仓库的钩子
check_all_hooks() {
    log_message "=== 开始检查所有仓库钩子 ==="
    
    local repos=($(scan_repositories))
    local missing_hooks=()
    local broken_hooks=()
    local working_hooks=()
    
    for repo in "${repos[@]}"; do
        check_repository_hooks "$repo"
        local status=$?
        
        case $status in
            0)
                working_hooks+=("$repo")
                ;;
            1|2|3|4)
                missing_hooks+=("$repo")
                ;;
            10|20)
                working_hooks+=("$repo")
                ;;
            *)
                broken_hooks+=("$repo")
                ;;
        esac
    done
    
    log_message "检查结果:"
    log_message "  正常仓库: ${#working_hooks[@]} (${working_hooks[*]})"
    log_message "  缺少钩子: ${#missing_hooks[@]} (${missing_hooks[*]})"
    log_message "  异常钩子: ${#broken_hooks[@]} (${broken_hooks[*]})"
    
    # 返回需要修复的仓库列表
    echo "${missing_hooks[@]} ${broken_hooks[@]}"
}

# 批量修复所有仓库的钩子
repair_all_hooks() {
    log_message "=== 开始修复所有仓库钩子 ==="
    
    # 首先检查模板
    if ! check_hook_template; then
        log_message "错误: 钩子模板检查失败，无法继续修复"
        return 1
    fi
    
    local repos_to_fix=($(check_all_hooks))
    
    if [ ${#repos_to_fix[@]} -eq 0 ]; then
        log_message "所有仓库钩子状态正常，无需修复"
        return 0
    fi
    
    log_message "需要修复的仓库: ${repos_to_fix[*]}"
    
    local success_count=0
    local failed_count=0
    
    for repo in "${repos_to_fix[@]}"; do
        if install_repository_hook "$repo"; then
            ((success_count++))
        else
            ((failed_count++))
            log_message "  修复失败: $repo"
        fi
    done
    
    log_message "修复完成: 成功 $success_count, 失败 $failed_count"
    return $failed_count
}

# 从数据库获取启用的通知规则对应的仓库
get_enabled_repositories() {
    local db_file="/home/svnadmin/svnadmin.db"
    
    if [ ! -f "$db_file" ]; then
        log_message "数据库文件不存在: $db_file"
        return 1
    fi
    
    # 查询启用的通知规则
    local repos=$(sqlite3 "$db_file" "
        SELECT DISTINCT repo_name 
        FROM wecom_notification_rules 
        WHERE enable = 1
    " 2>/dev/null || echo "")
    
    if [ -n "$repos" ]; then
        log_message "从数据库获取到启用的仓库规则:"
        echo "$repos" | while read -r repo; do
            log_message "  - $repo"
        done
        echo "$repos"
    else
        log_message "数据库中没有启用的通知规则"
        return 1
    fi
}

# 智能修复：只修复有通知规则的仓库
smart_repair() {
    log_message "=== 开始智能修复（基于通知规则） ==="
    
    # 检查模板
    if ! check_hook_template; then
        log_message "错误: 钩子模板检查失败"
        return 1
    fi
    
    # 获取需要钩子的仓库列表
    local enabled_repos=$(get_enabled_repositories)
    
    if [ -z "$enabled_repos" ]; then
        log_message "没有启用的通知规则，跳过钩子修复"
        return 0
    fi
    
    local success_count=0
    local failed_count=0
    
    echo "$enabled_repos" | while read -r repo_name; do
        if [ "$repo_name" = "*" ]; then
            # 通配符规则：修复所有仓库
            log_message "发现通配符规则，修复所有仓库"
            repair_all_hooks
            return $?
        else
            # 具体仓库：检查并修复
            if [ -d "$SVN_REP_BASE/$repo_name" ]; then
                check_repository_hooks "$repo_name"
                local status=$?
                
                if [ $status -ne 10 ] && [ $status -ne 20 ]; then
                    log_message "修复仓库钩子: $repo_name"
                    if install_repository_hook "$repo_name"; then
                        ((success_count++))
                    else
                        ((failed_count++))
                    fi
                else
                    log_message "仓库钩子正常: $repo_name"
                fi
            else
                log_message "警告: 仓库不存在: $repo_name"
            fi
        fi
    done
    
    log_message "智能修复完成: 成功 $success_count, 失败 $failed_count"
    return $failed_count
}

# 主函数
main() {
    local action="${1:-check}"
    
    ensure_directory "$(dirname "$LOG_FILE")"
    log_message "钩子管理器启动，操作: $action"
    
    case "$action" in
        "check")
            check_all_hooks > /dev/null
            ;;
        "repair")
            repair_all_hooks
            ;;
        "smart")
            smart_repair
            ;;
        "template")
            check_hook_template
            ;;
        *)
            echo "用法: $0 {check|repair|smart|template}"
            echo "  check    - 检查所有仓库钩子状态"
            echo "  repair   - 修复所有仓库钩子"
            echo "  smart    - 智能修复（仅修复有通知规则的仓库）"
            echo "  template - 检查钩子模板"
            exit 1
            ;;
    esac
}

# 如果直接执行脚本
if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
    main "$@"
fi
