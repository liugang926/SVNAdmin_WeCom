#!/bin/bash

# SVNAdmin 生产环境升级脚本
# 适用于 CentOS 7 + Docker 环境

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
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

# 检查 Docker 是否安装
check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker 未安装，请先安装 Docker"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose 未安装，请先安装 Docker Compose"
        exit 1
    fi
    
    log_success "Docker 环境检查通过"
}

# 获取配置
get_config() {
    echo "请输入您的 SVNAdmin 安装路径 (默认: /opt/svnadmin):"
    read -r SVNADMIN_PATH
    SVNADMIN_PATH=${SVNADMIN_PATH:-/opt/svnadmin}
    
    echo "请输入备份目录路径 (默认: /backup):"
    read -r BACKUP_PATH
    BACKUP_PATH=${BACKUP_PATH:-/backup}
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    
    log_info "安装路径: $SVNADMIN_PATH"
    log_info "备份路径: $BACKUP_PATH"
    log_info "时间戳: $TIMESTAMP"
}

# 创建备份
create_backup() {
    log_info "开始创建备份..."
    
    # 创建备份目录
    mkdir -p "$BACKUP_PATH"
    
    # 停止现有服务
    if [ -d "$SVNADMIN_PATH" ]; then
        log_info "停止现有服务..."
        cd "$SVNADMIN_PATH"
        docker-compose down || true
        
        # 备份整个项目目录
        log_info "备份项目目录..."
        cp -r "$SVNADMIN_PATH" "$BACKUP_PATH/svnadmin-$TIMESTAMP"
        
        log_success "备份完成: $BACKUP_PATH/svnadmin-$TIMESTAMP"
    else
        log_warning "未找到现有安装，跳过备份"
    fi
}

# 下载最新代码
download_latest() {
    log_info "准备下载最新代码..."
    
    # 如果存在旧安装，重命名
    if [ -d "$SVNADMIN_PATH" ]; then
        mv "$SVNADMIN_PATH" "$SVNADMIN_PATH-old-$TIMESTAMP"
    fi
    
    # 这里假设您已经准备好了代码包
    echo "请选择代码来源:"
    echo "1) 从 Git 仓库克隆"
    echo "2) 从本地代码包解压"
    read -r choice
    
    case $choice in
        1)
            log_info "从 Git 仓库克隆..."
            git clone https://github.com/witersen/SvnAdminV2.0.git "$SVNADMIN_PATH"
            ;;
        2)
            echo "请输入代码包路径:"
            read -r code_package
            if [ -f "$code_package" ]; then
                log_info "解压代码包..."
                mkdir -p "$SVNADMIN_PATH"
                tar -xzf "$code_package" -C "$SVNADMIN_PATH" --strip-components=1
            else
                log_error "代码包不存在: $code_package"
                exit 1
            fi
            ;;
        *)
            log_error "无效选择"
            exit 1
            ;;
    esac
    
    log_success "代码下载完成"
}

# 恢复数据
restore_data() {
    if [ -d "$SVNADMIN_PATH-old-$TIMESTAMP" ]; then
        log_info "恢复数据和配置..."
        
        # 恢复数据目录
        if [ -d "$SVNADMIN_PATH-old-$TIMESTAMP/data" ]; then
            cp -r "$SVNADMIN_PATH-old-$TIMESTAMP/data" "$SVNADMIN_PATH/"
            log_success "数据目录恢复完成"
        fi
        
        # 恢复数据库文件
        if [ -f "$SVNADMIN_PATH-old-$TIMESTAMP/02.php/templete/database/sqlite/database.db" ]; then
            mkdir -p "$SVNADMIN_PATH/02.php/templete/database/sqlite"
            cp "$SVNADMIN_PATH-old-$TIMESTAMP/02.php/templete/database/sqlite/database.db" \
               "$SVNADMIN_PATH/02.php/templete/database/sqlite/"
            log_success "数据库文件恢复完成"
        fi
        
        # 恢复配置文件
        if [ -d "$SVNADMIN_PATH-old-$TIMESTAMP/02.php/config" ]; then
            cp -r "$SVNADMIN_PATH-old-$TIMESTAMP/02.php/config"/* "$SVNADMIN_PATH/02.php/config/" 2>/dev/null || true
            log_success "配置文件恢复完成"
        fi
        
        # 恢复 docker-compose.yml
        if [ -f "$SVNADMIN_PATH-old-$TIMESTAMP/docker-compose.yml" ]; then
            cp "$SVNADMIN_PATH-old-$TIMESTAMP/docker-compose.yml" "$SVNADMIN_PATH/"
            log_success "Docker Compose 配置恢复完成"
        fi
    fi
}

# 数据库迁移
migrate_database() {
    log_info "执行数据库迁移..."
    
    cd "$SVNADMIN_PATH"
    
    # 检查是否需要迁移
    if [ -f "04.update/wecom-integration/database_migration.php" ]; then
        log_info "执行企业微信集成数据库迁移..."
        docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php 04.update/wecom-integration/database_migration.php
        log_success "数据库迁移完成"
    else
        log_warning "未找到数据库迁移脚本，跳过迁移"
    fi
}

# 配置企业微信
configure_wecom() {
    log_info "配置企业微信集成..."
    
    echo "是否需要配置企业微信集成? (y/n, 默认: n):"
    read -r enable_wecom
    enable_wecom=${enable_wecom:-n}
    
    if [[ "$enable_wecom" == "y" || "$enable_wecom" == "Y" ]]; then
        cd "$SVNADMIN_PATH"
        
        # 复制配置模板
        if [ -f "02.php/config/wecom.php.template" ]; then
            cp "02.php/config/wecom.php.template" "02.php/config/wecom.php"
            log_info "企业微信配置模板已创建，请手动编辑 02.php/config/wecom.php"
            log_info "或运行配置向导: docker run --rm -it -v \$(pwd):/app -w /app php:7.4-cli php 02.php/server/wecom_setup_wizard.php"
        fi
        
        # 使用企业微信版本的 Docker Compose
        if [ -f "03.cicd/docker-compose.wecom.yml" ]; then
            cp "03.cicd/docker-compose.wecom.yml" "docker-compose.yml"
            log_success "已更新为企业微信集成版本的 Docker Compose 配置"
        fi
    fi
}

# 构建和启动服务
build_and_start() {
    log_info "构建和启动服务..."
    
    cd "$SVNADMIN_PATH"
    
    # 设置正确的权限
    chown -R www-data:www-data . 2>/dev/null || chown -R 33:33 .
    chmod -R 755 .
    chmod -R 777 02.php/templete/database/ 2>/dev/null || true
    chmod -R 777 logs/ 2>/dev/null || mkdir -p logs && chmod -R 777 logs/
    
    # 构建镜像
    log_info "构建 Docker 镜像..."
    docker-compose build
    
    # 启动服务
    log_info "启动服务..."
    docker-compose up -d
    
    # 等待服务启动
    sleep 10
    
    log_success "服务启动完成"
}

# 验证升级
verify_upgrade() {
    log_info "验证升级结果..."
    
    cd "$SVNADMIN_PATH"
    
    # 检查容器状态
    if docker-compose ps | grep -q "Up"; then
        log_success "Docker 容器运行正常"
    else
        log_error "Docker 容器启动失败"
        docker-compose logs
        return 1
    fi
    
    # 检查 Web 服务
    if curl -s -I http://localhost | grep -q "200 OK"; then
        log_success "Web 服务访问正常"
    else
        log_warning "Web 服务可能未完全启动，请稍后检查"
    fi
    
    # 检查数据库
    user_count=$(docker exec -i $(docker-compose ps -q svnadmin-wecom 2>/dev/null || docker-compose ps -q | head -1) \
        php -r "
        include '/var/www/html/02.php/config/config.php';
        try {
            \$pdo = new PDO('sqlite:/var/www/html/02.php/templete/database/sqlite/database.db');
            \$stmt = \$pdo->query('SELECT COUNT(*) FROM svn_users');
            echo \$stmt->fetchColumn();
        } catch (Exception \$e) {
            echo '0';
        }
        " 2>/dev/null || echo "0")
    
    if [[ "$user_count" -gt 0 ]]; then
        log_success "数据库数据完整，用户数量: $user_count"
    else
        log_warning "数据库可能存在问题，请手动检查"
    fi
}

# 显示升级结果
show_result() {
    log_success "升级完成！"
    echo ""
    echo "访问信息:"
    echo "  Web 管理界面: http://your-server-ip"
    echo "  SVN 服务地址: svn://your-server-ip:3690"
    echo ""
    echo "管理命令:"
    echo "  查看服务状态: cd $SVNADMIN_PATH && docker-compose ps"
    echo "  查看日志: cd $SVNADMIN_PATH && docker-compose logs -f"
    echo "  重启服务: cd $SVNADMIN_PATH && docker-compose restart"
    echo "  停止服务: cd $SVNADMIN_PATH && docker-compose down"
    echo ""
    echo "备份位置: $BACKUP_PATH/svnadmin-$TIMESTAMP"
    echo ""
    if [ -d "$SVNADMIN_PATH-old-$TIMESTAMP" ]; then
        echo "如需回滚，请运行:"
        echo "  cd $SVNADMIN_PATH && docker-compose down"
        echo "  rm -rf $SVNADMIN_PATH"
        echo "  mv $SVNADMIN_PATH-old-$TIMESTAMP $SVNADMIN_PATH"
        echo "  cd $SVNADMIN_PATH && docker-compose up -d"
    fi
}

# 主函数
main() {
    log_info "开始 SVNAdmin 生产环境升级..."
    
    check_root
    check_docker
    get_config
    
    echo ""
    echo "升级计划:"
    echo "1. 创建备份"
    echo "2. 下载最新代码"
    echo "3. 恢复数据和配置"
    echo "4. 执行数据库迁移"
    echo "5. 配置企业微信集成"
    echo "6. 构建和启动服务"
    echo "7. 验证升级结果"
    echo ""
    echo "确认开始升级? (y/n):"
    read -r confirm
    
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        log_info "升级已取消"
        exit 0
    fi
    
    create_backup
    download_latest
    restore_data
    migrate_database
    configure_wecom
    build_and_start
    verify_upgrade
    show_result
    
    log_success "升级流程全部完成！"
}

# 错误处理
trap 'log_error "升级过程中发生错误，请检查日志"; exit 1' ERR

# 运行主函数
main "$@"
