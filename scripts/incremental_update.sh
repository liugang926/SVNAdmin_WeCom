#!/bin/bash

# SVNAdmin 增量更新脚本
# 用于将新功能和bug修复增量更新到现有生产环境

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否为 root 用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要 root 权限运行"
        exit 1
    fi
}

# 获取配置
get_config() {
    echo "请输入您的 SVNAdmin 安装路径 (默认: /opt/svnadmin):"
    read -r SVNADMIN_PATH
    SVNADMIN_PATH=${SVNADMIN_PATH:-/opt/svnadmin}
    
    if [ ! -d "$SVNADMIN_PATH" ]; then
        log_error "SVNAdmin 安装路径不存在: $SVNADMIN_PATH"
        exit 1
    fi
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="$SVNADMIN_PATH/backup-$TIMESTAMP"
    
    log_info "安装路径: $SVNADMIN_PATH"
    log_info "备份目录: $BACKUP_DIR"
}

# 选择更新内容
select_updates() {
    echo ""
    echo "请选择要更新的内容:"
    echo "1) 仅更新Bug修复 (推荐先做)"
    echo "2) 仅更新企业微信功能"
    echo "3) 更新所有内容 (Bug修复 + 企业微信)"
    echo "4) 自定义选择"
    read -r choice
    
    case $choice in
        1)
            UPDATE_BUGFIX=true
            UPDATE_WECOM=false
            ;;
        2)
            UPDATE_BUGFIX=false
            UPDATE_WECOM=true
            ;;
        3)
            UPDATE_BUGFIX=true
            UPDATE_WECOM=true
            ;;
        4)
            echo "是否更新Bug修复? (y/n):"
            read -r bugfix_choice
            UPDATE_BUGFIX=$([[ "$bugfix_choice" == "y" || "$bugfix_choice" == "Y" ]] && echo true || echo false)
            
            echo "是否更新企业微信功能? (y/n):"
            read -r wecom_choice
            UPDATE_WECOM=$([[ "$wecom_choice" == "y" || "$wecom_choice" == "Y" ]] && echo true || echo false)
            ;;
        *)
            log_error "无效选择"
            exit 1
            ;;
    esac
    
    log_info "更新Bug修复: $UPDATE_BUGFIX"
    log_info "更新企业微信: $UPDATE_WECOM"
}

# 创建备份
create_backup() {
    log_info "创建备份..."
    mkdir -p "$BACKUP_DIR"
    
    # 备份要修改的核心文件
    if [ "$UPDATE_BUGFIX" = true ]; then
        log_info "备份Bug修复相关文件..."
        
        # 备份后端文件
        if [ -f "$SVNADMIN_PATH/02.php/extension/Witersen/SVNAdmin.php" ]; then
            mkdir -p "$BACKUP_DIR/02.php/extension/Witersen"
            cp "$SVNADMIN_PATH/02.php/extension/Witersen/SVNAdmin.php" "$BACKUP_DIR/02.php/extension/Witersen/"
        fi
        
        if [ -f "$SVNADMIN_PATH/02.php/app/service/Svnrep.php" ]; then
            mkdir -p "$BACKUP_DIR/02.php/app/service"
            cp "$SVNADMIN_PATH/02.php/app/service/Svnrep.php" "$BACKUP_DIR/02.php/app/service/"
        fi
        
        # 备份前端文件
        if [ -d "$SVNADMIN_PATH/01.web/src/views/repositoryUser" ]; then
            mkdir -p "$BACKUP_DIR/01.web/src/views"
            cp -r "$SVNADMIN_PATH/01.web/src/views/repositoryUser" "$BACKUP_DIR/01.web/src/views/"
        fi
        
        if [ -d "$SVNADMIN_PATH/01.web/src/views/repositoryInfo" ]; then
            mkdir -p "$BACKUP_DIR/01.web/src/views"
            cp -r "$SVNADMIN_PATH/01.web/src/views/repositoryInfo" "$BACKUP_DIR/01.web/src/views/"
        fi
    fi
    
    # 备份企业微信相关文件（如果存在）
    if [ "$UPDATE_WECOM" = true ]; then
        log_info "备份企业微信相关文件..."
        
        if [ -f "$SVNADMIN_PATH/02.php/server/svnadmind.php" ]; then
            mkdir -p "$BACKUP_DIR/02.php/server"
            cp "$SVNADMIN_PATH/02.php/server/svnadmind.php" "$BACKUP_DIR/02.php/server/"
        fi
        
        if [ -f "$SVNADMIN_PATH/02.php/config/daemon.php" ]; then
            mkdir -p "$BACKUP_DIR/02.php/config"
            cp "$SVNADMIN_PATH/02.php/config/daemon.php" "$BACKUP_DIR/02.php/config/"
        fi
    fi
    
    log_success "备份完成: $BACKUP_DIR"
}

# 更新Bug修复
update_bugfix() {
    if [ "$UPDATE_BUGFIX" != true ]; then
        return 0
    fi
    
    log_info "更新Bug修复文件..."
    
    # 检查源文件是否存在
    if [ ! -f "02.php/extension/Witersen/SVNAdmin.php" ]; then
        log_error "源文件不存在: 02.php/extension/Witersen/SVNAdmin.php"
        return 1
    fi
    
    # 更新后端文件
    log_info "更新后端修复文件..."
    cp "02.php/extension/Witersen/SVNAdmin.php" "$SVNADMIN_PATH/02.php/extension/Witersen/"
    cp "02.php/app/service/Svnrep.php" "$SVNADMIN_PATH/02.php/app/service/"
    
    # 创建前端组件目录
    mkdir -p "$SVNADMIN_PATH/01.web/src/components"
    
    # 更新前端文件
    log_info "更新前端组件文件..."
    cp "01.web/src/components/ResizableTable.vue" "$SVNADMIN_PATH/01.web/src/components/"
    cp "01.web/src/components/TableToolbar.vue" "$SVNADMIN_PATH/01.web/src/components/"
    cp "01.web/src/views/repositoryUser/index.vue" "$SVNADMIN_PATH/01.web/src/views/repositoryUser/"
    cp "01.web/src/views/repositoryInfo/index.vue" "$SVNADMIN_PATH/01.web/src/views/repositoryInfo/"
    
    log_success "Bug修复文件更新完成"
}

# 更新企业微信功能
update_wecom() {
    if [ "$UPDATE_WECOM" != true ]; then
        return 0
    fi
    
    log_info "更新企业微信功能文件..."
    
    # 配置文件
    log_info "更新配置文件..."
    cp "02.php/config/wecom.php.template" "$SVNADMIN_PATH/02.php/config/" 2>/dev/null || true
    cp "02.php/config/daemon.php" "$SVNADMIN_PATH/02.php/config/" 2>/dev/null || true
    
    # 数据库文件
    log_info "更新数据库文件..."
    mkdir -p "$SVNADMIN_PATH/02.php/templete/database/sqlite"
    mkdir -p "$SVNADMIN_PATH/02.php/templete/database/mysql"
    cp "02.php/templete/database/sqlite/wecom_tables.sql" "$SVNADMIN_PATH/02.php/templete/database/sqlite/" 2>/dev/null || true
    cp "02.php/templete/database/mysql/wecom_tables.sql" "$SVNADMIN_PATH/02.php/templete/database/mysql/" 2>/dev/null || true
    
    # 迁移脚本
    mkdir -p "$SVNADMIN_PATH/04.update/wecom-integration"
    cp -r "04.update/wecom-integration"/* "$SVNADMIN_PATH/04.update/wecom-integration/" 2>/dev/null || true
    
    # 服务文件
    log_info "更新服务文件..."
    mkdir -p "$SVNADMIN_PATH/02.php/app/service"
    mkdir -p "$SVNADMIN_PATH/02.php/app/controller"
    mkdir -p "$SVNADMIN_PATH/02.php/app/util"
    
    cp "02.php/app/service/WeComAPI.php" "$SVNADMIN_PATH/02.php/app/service/" 2>/dev/null || true
    cp "02.php/app/service/WeComSync.php" "$SVNADMIN_PATH/02.php/app/service/" 2>/dev/null || true
    cp "02.php/app/service/WeComNotification.php" "$SVNADMIN_PATH/02.php/app/service/" 2>/dev/null || true
    cp "02.php/app/controller/WeComAdmin.php" "$SVNADMIN_PATH/02.php/app/controller/" 2>/dev/null || true
    cp "02.php/app/util/WeComNotificationClient.php" "$SVNADMIN_PATH/02.php/app/util/" 2>/dev/null || true
    
    # 守护进程文件
    log_info "更新守护进程文件..."
    cp "02.php/server/svnadmind.php" "$SVNADMIN_PATH/02.php/server/" 2>/dev/null || true
    cp "02.php/server/wecom_notification_daemon.php" "$SVNADMIN_PATH/02.php/server/" 2>/dev/null || true
    cp "02.php/server/wecom_install.php" "$SVNADMIN_PATH/02.php/server/" 2>/dev/null || true
    
    # 前端文件
    log_info "更新前端企业微信界面..."
    mkdir -p "$SVNADMIN_PATH/01.web/src/views"
    cp -r "01.web/src/views/wecom" "$SVNADMIN_PATH/01.web/src/views/" 2>/dev/null || true
    
    # 钩子脚本
    log_info "更新SVN钩子脚本..."
    mkdir -p "$SVNADMIN_PATH/02.php/templete/hooks"
    mkdir -p "$SVNADMIN_PATH/02.php/app/script"
    cp -r "02.php/templete/hooks/wecom_notify" "$SVNADMIN_PATH/02.php/templete/hooks/" 2>/dev/null || true
    cp "02.php/app/script/wecom_notify.php" "$SVNADMIN_PATH/02.php/app/script/" 2>/dev/null || true
    
    log_success "企业微信功能文件更新完成"
}

# 执行数据库迁移
migrate_database() {
    if [ "$UPDATE_WECOM" != true ]; then
        return 0
    fi
    
    log_info "执行数据库迁移..."
    
    cd "$SVNADMIN_PATH"
    
    # 获取容器名称
    CONTAINER_NAME=$(docker-compose ps -q | head -1)
    if [ -z "$CONTAINER_NAME" ]; then
        log_warning "未找到运行中的容器，尝试直接执行迁移..."
        if [ -f "04.update/wecom-integration/database_migration.php" ]; then
            docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php 04.update/wecom-integration/database_migration.php
        fi
    else
        log_info "在容器中执行数据库迁移..."
        docker exec "$CONTAINER_NAME" php /var/www/html/04.update/wecom-integration/database_migration.php
    fi
    
    log_success "数据库迁移完成"
}

# 重新构建前端
rebuild_frontend() {
    log_info "重新构建前端..."
    
    cd "$SVNADMIN_PATH/01.web"
    
    # 检查是否需要安装依赖
    if [ "$UPDATE_BUGFIX" = true ] || [ "$UPDATE_WECOM" = true ]; then
        log_info "检查前端依赖..."
        if command -v npm &> /dev/null; then
            npm install --production
            npm run build
        else
            log_warning "npm 未安装，跳过前端构建"
            log_warning "请手动执行: cd $SVNADMIN_PATH/01.web && npm install && npm run build"
        fi
    fi
    
    cd "$SVNADMIN_PATH"
}

# 重启服务
restart_services() {
    log_info "重启服务..."
    
    cd "$SVNADMIN_PATH"
    
    # 重启 Docker 容器
    if [ -f "docker-compose.yml" ]; then
        docker-compose restart
        sleep 5
        
        # 检查服务状态
        if docker-compose ps | grep -q "Up"; then
            log_success "服务重启成功"
        else
            log_error "服务重启失败，请检查日志"
            docker-compose logs --tail=20
        fi
    else
        log_warning "未找到 docker-compose.yml，请手动重启服务"
    fi
}

# 验证更新
verify_update() {
    log_info "验证更新结果..."
    
    cd "$SVNADMIN_PATH"
    
    # 检查Web服务
    if curl -s -I http://localhost | grep -q "200 OK"; then
        log_success "Web服务访问正常"
    else
        log_warning "Web服务可能未完全启动，请稍后检查"
    fi
    
    # 验证Bug修复
    if [ "$UPDATE_BUGFIX" = true ]; then
        log_info "验证Bug修复..."
        
        # 检查修复后的文件是否存在
        if [ -f "02.php/extension/Witersen/SVNAdmin.php" ] && [ -f "01.web/src/components/ResizableTable.vue" ]; then
            log_success "Bug修复文件更新成功"
        else
            log_warning "Bug修复文件可能更新不完整"
        fi
    fi
    
    # 验证企业微信功能
    if [ "$UPDATE_WECOM" = true ]; then
        log_info "验证企业微信功能..."
        
        # 检查关键文件是否存在
        if [ -f "02.php/app/service/WeComAPI.php" ] && [ -d "01.web/src/views/wecom" ]; then
            log_success "企业微信功能文件更新成功"
        else
            log_warning "企业微信功能文件可能更新不完整"
        fi
    fi
}

# 显示更新结果
show_result() {
    log_success "增量更新完成！"
    echo ""
    echo "更新内容:"
    if [ "$UPDATE_BUGFIX" = true ]; then
        echo "  ✓ Bug修复 (仓库重命名权限 + 表格列宽调整)"
    fi
    if [ "$UPDATE_WECOM" = true ]; then
        echo "  ✓ 企业微信集成功能"
    fi
    echo ""
    echo "访问信息:"
    echo "  Web管理界面: http://your-server-ip"
    echo "  SVN服务地址: svn://your-server-ip:3690"
    echo ""
    echo "验证建议:"
    if [ "$UPDATE_BUGFIX" = true ]; then
        echo "  1. 测试仓库重命名功能，检查权限是否保持"
        echo "  2. 测试用户管理和仓库管理页面的列宽调整功能"
    fi
    if [ "$UPDATE_WECOM" = true ]; then
        echo "  3. 访问企业微信管理页面，检查新功能是否正常"
    fi
    echo ""
    echo "备份位置: $BACKUP_DIR"
    echo ""
    echo "如需回滚，请运行:"
    echo "  ./rollback_update.sh $BACKUP_DIR"
}

# 生成回滚脚本
generate_rollback_script() {
    cat > "$SVNADMIN_PATH/rollback_update.sh" << EOF
#!/bin/bash
# 自动生成的回滚脚本

BACKUP_DIR="\$1"
SVNADMIN_PATH="$SVNADMIN_PATH"

if [ -z "\$BACKUP_DIR" ] || [ ! -d "\$BACKUP_DIR" ]; then
    echo "错误: 请提供有效的备份目录路径"
    echo "用法: \$0 <backup_directory>"
    exit 1
fi

echo "开始回滚更新..."
echo "备份目录: \$BACKUP_DIR"
echo "安装目录: \$SVNADMIN_PATH"

# 停止服务
cd "\$SVNADMIN_PATH"
docker-compose down

# 恢复文件
if [ -d "\$BACKUP_DIR" ]; then
    cp -r "\$BACKUP_DIR"/* "\$SVNADMIN_PATH/"
    echo "文件恢复完成"
fi

# 重新构建前端
cd "\$SVNADMIN_PATH/01.web"
npm run build

# 重启服务
cd "\$SVNADMIN_PATH"
docker-compose up -d

echo "回滚完成！"
EOF

    chmod +x "$SVNADMIN_PATH/rollback_update.sh"
    log_info "回滚脚本已生成: $SVNADMIN_PATH/rollback_update.sh"
}

# 主函数
main() {
    log_info "开始 SVNAdmin 增量更新..."
    
    check_root
    get_config
    select_updates
    
    echo ""
    echo "更新计划:"
    if [ "$UPDATE_BUGFIX" = true ]; then
        echo "  ✓ 更新Bug修复"
    fi
    if [ "$UPDATE_WECOM" = true ]; then
        echo "  ✓ 更新企业微信功能"
    fi
    echo ""
    echo "确认开始更新? (y/n):"
    read -r confirm
    
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        log_info "更新已取消"
        exit 0
    fi
    
    create_backup
    update_bugfix
    update_wecom
    migrate_database
    rebuild_frontend
    restart_services
    verify_update
    generate_rollback_script
    show_result
    
    log_success "增量更新流程全部完成！"
}

# 错误处理
trap 'log_error "更新过程中发生错误，请检查日志"; exit 1' ERR

# 运行主函数
main "$@"
