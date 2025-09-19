#!/bin/bash

# 创建生产环境部署包脚本

set -e

# 颜色定义
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# 获取项目根目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
PACKAGE_NAME="svnadmin-wecom-upgrade-$TIMESTAMP"
PACKAGE_DIR="/tmp/$PACKAGE_NAME"

log_info "创建部署包: $PACKAGE_NAME"

# 创建临时目录
mkdir -p "$PACKAGE_DIR"

# 复制核心文件
log_info "复制项目文件..."

# 复制主要目录
cp -r "$PROJECT_ROOT/01.web" "$PACKAGE_DIR/"
cp -r "$PROJECT_ROOT/02.php" "$PACKAGE_DIR/"
cp -r "$PROJECT_ROOT/03.cicd" "$PACKAGE_DIR/"
cp -r "$PROJECT_ROOT/04.update" "$PACKAGE_DIR/"

# 复制文档
mkdir -p "$PACKAGE_DIR/docs"
cp -r "$PROJECT_ROOT/docs"/* "$PACKAGE_DIR/docs/" 2>/dev/null || true

# 复制脚本
mkdir -p "$PACKAGE_DIR/scripts"
cp -r "$PROJECT_ROOT/scripts"/* "$PACKAGE_DIR/scripts/" 2>/dev/null || true

# 复制根目录重要文件
cp "$PROJECT_ROOT/README.md" "$PACKAGE_DIR/" 2>/dev/null || true
cp "$PROJECT_ROOT/PROBLEM_FIXES_SUMMARY.md" "$PACKAGE_DIR/" 2>/dev/null || true

# 创建部署说明
cat > "$PACKAGE_DIR/DEPLOYMENT_README.md" << 'EOF'
# SVNAdmin 企业微信集成版 - 生产环境部署包

## 包含内容

本部署包包含以下新功能和修复：

### 新功能
1. **企业微信集成**
   - 组织架构同步
   - 用户账号匹配
   - 实时通知功能
   - 权限动态管理

2. **UI 改进**
   - 表格列宽可调整
   - 更好的用户体验

### Bug 修复
1. 仓库重命名时权限丢失问题
2. 表格列宽无法调整问题

## 快速部署

### 方法一：使用自动化脚本
```bash
# 上传部署包到服务器
scp svnadmin-wecom-upgrade-*.tar.gz root@your-server:/tmp/

# 登录服务器
ssh root@your-server

# 解压部署包
cd /tmp
tar -xzf svnadmin-wecom-upgrade-*.tar.gz
cd svnadmin-wecom-upgrade-*

# 运行升级脚本
chmod +x scripts/production_upgrade.sh
./scripts/production_upgrade.sh
```

### 方法二：手动部署
详细步骤请参考 `docs/PRODUCTION_UPGRADE_GUIDE.md`

## 重要提醒

1. **升级前务必备份**现有数据和配置
2. **测试环境验证**后再部署到生产环境
3. **企业微信功能**为可选，可以先不启用
4. 如有问题，可以快速回滚到备份版本

## 技术支持

- 详细文档：`docs/` 目录
- 升级指南：`docs/PRODUCTION_UPGRADE_GUIDE.md`
- 问题修复说明：`PROBLEM_FIXES_SUMMARY.md`
EOF

# 设置脚本执行权限
chmod +x "$PACKAGE_DIR/scripts"/*.sh 2>/dev/null || true

# 创建压缩包
log_info "创建压缩包..."
cd /tmp
tar -czf "$PACKAGE_NAME.tar.gz" "$PACKAGE_NAME"

# 移动到项目目录
mv "$PACKAGE_NAME.tar.gz" "$PROJECT_ROOT/"

# 清理临时目录
rm -rf "$PACKAGE_DIR"

log_success "部署包创建完成: $PROJECT_ROOT/$PACKAGE_NAME.tar.gz"

echo ""
echo "部署包信息:"
echo "  文件名: $PACKAGE_NAME.tar.gz"
echo "  大小: $(du -h "$PROJECT_ROOT/$PACKAGE_NAME.tar.gz" | cut -f1)"
echo "  位置: $PROJECT_ROOT/$PACKAGE_NAME.tar.gz"
echo ""
echo "使用方法:"
echo "1. 上传到生产服务器: scp $PACKAGE_NAME.tar.gz root@your-server:/tmp/"
echo "2. 解压: tar -xzf $PACKAGE_NAME.tar.gz"
echo "3. 运行升级脚本: cd $PACKAGE_NAME && chmod +x scripts/production_upgrade.sh && ./scripts/production_upgrade.sh"
