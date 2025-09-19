<?php
/*
 * Bug分析模板
 * 使用方法: 复制此模板，重命名为 analyze_[问题描述].php
 * 创建时间: [日期]
 * 分析人员: [姓名]
 * 优先级: [高/中/低]
 */

define('BASE_PATH', __DIR__ . '/..');

echo "=== Bug分析: [问题描述] ===\n\n";

// 1. 问题描述
echo "🚨 **问题描述**\n";
echo "- 问题现象: [详细描述问题现象]\n";
echo "- 影响范围: [全局/模块/局部]\n";
echo "- 出现频率: [总是/经常/偶尔/罕见]\n";
echo "- 严重程度: [严重/中等/轻微]\n\n";

// 2. 环境信息
echo "🔧 **环境信息**\n";
echo "- 操作系统: \n";
echo "- PHP版本: \n";
echo "- 数据库类型: \n";
echo "- Web服务器: \n";
echo "- 浏览器: \n";
echo "- SVN版本: \n\n";

// 3. 复现步骤
echo "🔄 **复现步骤**\n";
echo "1. [第一步操作]\n";
echo "2. [第二步操作]\n";
echo "3. [第三步操作]\n";
echo "4. [观察结果]\n\n";

// 4. 预期结果 vs 实际结果
echo "📊 **结果对比**\n";
echo "预期结果: [描述预期的正确行为]\n";
echo "实际结果: [描述实际观察到的错误行为]\n\n";

// 5. 环境检查
echo "🔍 **环境检查**\n";
try {
    // 检查PHP配置
    echo "PHP配置检查:\n";
    echo "- PHP版本: " . PHP_VERSION . "\n";
    echo "- 内存限制: " . ini_get('memory_limit') . "\n";
    echo "- 执行时间限制: " . ini_get('max_execution_time') . "\n";
    
    // 检查扩展
    $requiredExtensions = ['curl', 'json', 'pdo', 'mbstring'];
    echo "\nPHP扩展检查:\n";
    foreach ($requiredExtensions as $ext) {
        $status = extension_loaded($ext) ? '✓' : '✗';
        echo "- {$ext}: {$status}\n";
    }
    
    // 检查文件权限
    echo "\n文件权限检查:\n";
    $checkPaths = [
        BASE_PATH . '/02.php/logs/',
        BASE_PATH . '/02.php/config/',
        BASE_PATH . '/02.php/template/pid/'
    ];
    
    foreach ($checkPaths as $path) {
        if (file_exists($path)) {
            $writable = is_writable($path) ? '✓' : '✗';
            echo "- {$path}: {$writable}\n";
        } else {
            echo "- {$path}: 不存在\n";
        }
    }
    
} catch (Exception $e) {
    echo "环境检查异常: " . $e->getMessage() . "\n";
}

// 6. 数据检查
echo "\n📊 **数据检查**\n";
echo "// 添加具体的数据检查代码\n";
echo "// 例如：检查数据库连接、表结构、关键数据等\n\n";

// 7. 日志分析
echo "📝 **日志分析**\n";
echo "// 添加日志文件检查代码\n";
echo "// 例如：检查错误日志、访问日志、应用日志等\n\n";

// 8. 可能原因分析
echo "🎯 **可能原因分析**\n";
echo "1. **配置问题**:\n";
echo "   - 配置文件错误\n";
echo "   - 权限配置不当\n";
echo "   - 环境变量缺失\n\n";

echo "2. **数据问题**:\n";
echo "   - 数据不一致\n";
echo "   - 数据格式错误\n";
echo "   - 数据缺失\n\n";

echo "3. **逻辑问题**:\n";
echo "   - 业务逻辑错误\n";
echo "   - 边界条件处理不当\n";
echo "   - 异常处理缺失\n\n";

echo "4. **环境问题**:\n";
echo "   - 系统资源不足\n";
echo "   - 网络连接问题\n";
echo "   - 第三方服务异常\n\n";

// 9. 下一步行动
echo "📋 **下一步行动**\n";
echo "1. [ ] 创建debug脚本进行深入调试\n";
echo "2. [ ] 准备测试环境和测试数据\n";
echo "3. [ ] 设计修复方案\n";
echo "4. [ ] 编写修复代码\n";
echo "5. [ ] 创建验证测试\n\n";

// 10. 相关资源
echo "📚 **相关资源**\n";
echo "- 相关文档: \n";
echo "- 类似问题: \n";
echo "- 参考资料: \n\n";

echo "=== 分析完成 ===\n";
echo "请根据分析结果创建对应的debug脚本和修复方案\n";
?>
