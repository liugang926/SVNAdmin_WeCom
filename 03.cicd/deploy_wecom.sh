#!/bin/bash

# 企业微信集成 Docker 部署脚本
# 使用方法: ./deploy_wecom.sh [start|stop|restart|status|logs|install]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.wecom.yml"
ENV_FILE="$SCRIPT_DIR/.env"
ENV_EXAMPLE="$SCRIPT_DIR/env.wecom.example"

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

# 检查 Docker 和 Docker Compose
check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker 未安装，请先安装 Docker"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose 未安装，请先安装 Docker Compose"
        exit 1
    fi

    log_info "Docker 环境检查通过"
}

# 检查环境变量文件
check_env_file() {
    if [ ! -f "$ENV_FILE" ]; then
        log_warning "环境变量文件不存在，正在创建..."
        cp "$ENV_EXAMPLE" "$ENV_FILE"
        log_warning "请编辑 $ENV_FILE 文件，填写企业微信应用配置"
        log_warning "配置完成后重新运行部署脚本"
        exit 1
    fi
    log_info "环境变量文件检查通过"
}

# 安装企业微信集成
install_wecom() {
    log_info "开始安装企业微信集成..."
    
    # 检查环境
    check_docker
    check_env_file
    
    # 构建镜像
    log_info "构建 Docker 镜像..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" build
    
    # 启动服务
    log_info "启动服务..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d
    
    # 等待服务启动
    log_info "等待服务启动..."
    sleep 30
    
    # 检查服务状态
    if docker-compose -f "$COMPOSE_FILE" ps | grep -q "Up"; then
        log_success "企业微信集成安装成功！"
        log_info "访问地址: http://localhost"
        log_info "企业微信管理: http://localhost/01.web/#/wecom"
        log_info "运行 './deploy_wecom.sh status' 查看服务状态"
    else
        log_error "服务启动失败，请检查日志"
        docker-compose -f "$COMPOSE_FILE" logs
        exit 1
    fi
}

# 启动服务
start_services() {
    log_info "启动企业微信集成服务..."
    check_env_file
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d
    log_success "服务启动成功"
}

# 停止服务
stop_services() {
    log_info "停止企业微信集成服务..."
    docker-compose -f "$COMPOSE_FILE" down
    log_success "服务停止成功"
}

# 重启服务
restart_services() {
    log_info "重启企业微信集成服务..."
    stop_services
    sleep 5
    start_services
}

# 查看服务状态
show_status() {
    log_info "企业微信集成服务状态:"
    docker-compose -f "$COMPOSE_FILE" ps
    
    echo ""
    log_info "服务健康检查:"
    
    # 检查主服务
    if curl -s -f http://localhost/02.php/app/controller/WeComAdmin.php?action=GetSystemStatus > /dev/null 2>&1; then
        log_success "主服务: 运行正常"
    else
        log_error "主服务: 异常"
    fi
    
    # 检查企业微信 API
    if docker exec svnadmin-wecom php /var/www/html/02.php/server/wecom_install.php check > /dev/null 2>&1; then
        log_success "企业微信集成: 配置正常"
    else
        log_warning "企业微信集成: 需要配置"
    fi
}

# 查看日志
show_logs() {
    log_info "显示服务日志..."
    docker-compose -f "$COMPOSE_FILE" logs -f --tail=100
}

# 显示帮助信息
show_help() {
    echo "企业微信集成 Docker 部署脚本"
    echo ""
    echo "使用方法: $0 [命令]"
    echo ""
    echo "命令:"
    echo "  install   - 安装企业微信集成（首次部署）"
    echo "  start     - 启动服务"
    echo "  stop      - 停止服务"
    echo "  restart   - 重启服务"
    echo "  status    - 查看服务状态"
    echo "  logs      - 查看服务日志"
    echo "  help      - 显示帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 install   # 首次安装"
    echo "  $0 start     # 启动服务"
    echo "  $0 status    # 查看状态"
    echo ""
    echo "配置文件:"
    echo "  环境变量: $ENV_FILE"
    echo "  Docker Compose: $COMPOSE_FILE"
}

# 主函数
main() {
    case "${1:-help}" in
        install)
            install_wecom
            ;;
        start)
            start_services
            ;;
        stop)
            stop_services
            ;;
        restart)
            restart_services
            ;;
        status)
            show_status
            ;;
        logs)
            show_logs
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "未知命令: $1"
            show_help
            exit 1
            ;;
    esac
}

# 执行主函数
main "$@"
