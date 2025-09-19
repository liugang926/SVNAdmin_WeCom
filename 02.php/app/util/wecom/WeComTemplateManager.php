<?php
/**
 * 企业微信模板管理器
 * 
 * 负责管理消息模板、钩子模板等所有模板相关功能
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\util\wecom;

class WeComTemplateManager
{
    /**
     * 消息模板配置
     * @var array
     */
    private $messageTemplates;
    
    /**
     * 构造函数
     * 
     * @param array $messageTemplates 消息模板配置
     */
    public function __construct($messageTemplates = [])
    {
        $this->messageTemplates = $messageTemplates;
    }
    
    /**
     * 获取默认消息模板
     *
     * @param string $eventType 事件类型
     * @return string 模板内容
     */
    public function getDefaultTemplate($eventType)
    {
        return $this->messageTemplates[$eventType]['content'] ?? '';
    }
    
    /**
     * 获取通用消息模板
     *
     * @return string 通用模板内容
     */
    public function getGenericTemplate()
    {
        return "**SVN 操作通知**\n\n" .
               "**仓库**: {repo_name}\n" .
               "**操作**: {event_type}\n" .
               "**用户**: {author}\n" .
               "**时间**: {timestamp}\n" .
               "**路径**: {path}\n\n" .
               "{message}";
    }
    
    /**
     * 替换模板变量
     *
     * @param string $template 模板内容
     * @param array $variables 变量数组
     * @return string 替换后的内容
     */
    public function replaceTemplateVariables($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * 准备模板变量
     *
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 模板变量数组
     */
    public function prepareTemplateVariables($eventType, $eventData)
    {
        $variables = [
            'event_type' => $eventType,
            'repo_name' => $eventData['repo_name'] ?? '',
            'author' => $eventData['author'] ?? '',
            'revision' => $eventData['revision'] ?? '',
            'message' => $eventData['message'] ?? '',
            'path' => $eventData['path'] ?? '/',
            'files' => $this->formatFileList($this->parseFileList($eventData['files'] ?? '')),
            'timestamp' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'SVNAdmin'
        ];
        
        // 添加事件特定的变量
        switch ($eventType) {
            case 'commit':
                $variables['commit_message'] = $eventData['message'] ?? '';
                $variables['files_count'] = count($this->parseFileList($eventData['files'] ?? ''));
                break;
                
            case 'delete':
                $variables['deleted_path'] = $eventData['path'] ?? '';
                break;
                
            case 'update':
                $variables['updated_files'] = $this->formatFileList($this->parseFileList($eventData['files'] ?? ''));
                break;
        }
        
        return $variables;
    }
    
    /**
     * 解析文件列表字符串为数组
     *
     * @param string $filesString SVN changed输出的文件列表字符串
     * @return array 文件列表数组
     */
    public function parseFileList($filesString)
    {
        if (empty($filesString)) {
            return [];
        }
        
        // 如果已经是数组，直接返回
        if (is_array($filesString)) {
            return $filesString;
        }
        
        // 按行分割文件列表
        $lines = explode("\n", trim($filesString));
        $files = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // SVN changed格式: "A   filename" 或 "M   filename"
                // 提取文件名部分（去掉前面的状态标识）
                if (preg_match('/^[AMDRC]\s+(.+)$/', $line, $matches)) {
                    $files[] = $matches[1];
                } else {
                    // 如果不匹配标准格式，直接使用整行
                    $files[] = $line;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * 格式化文件列表
     *
     * @param array $files 文件列表
     * @return string 格式化后的文件列表
     */
    public function formatFileList($files)
    {
        if (empty($files)) {
            return '无';
        }
        
        if (count($files) > 10) {
            $displayFiles = array_slice($files, 0, 10);
            $remaining = count($files) - 10;
            return implode("\n", $displayFiles) . "\n... 还有 {$remaining} 个文件";
        }
        
        return implode("\n", $files);
    }
    
    /**
     * 获取事件类型文本
     *
     * @param string $eventType 事件类型
     * @return string 事件类型文本
     */
    public function getEventTypeText($eventType)
    {
        $eventTypeMap = [
            'commit' => '提交',
            'update' => '更新',
            'delete' => '删除',
            'copy' => '复制',
            'move' => '移动',
            'create' => '创建'
        ];
        
        return $eventTypeMap[$eventType] ?? $eventType;
    }
    
    /**
     * 构建合并消息
     *
     * @param array $events 事件列表
     * @return string 合并消息内容
     */
    public function buildMergedMessage($events)
    {
        $eventsByType = [];
        $repoNames = [];
        $authors = [];
        
        // 按事件类型分组
        foreach ($events as $event) {
            $eventType = $event['event_type'];
            $repoName = $event['data']['repo_name'] ?? '';
            $author = $event['data']['author'] ?? '';
            
            if (!isset($eventsByType[$eventType])) {
                $eventsByType[$eventType] = [];
            }
            $eventsByType[$eventType][] = $event;
            
            if (!empty($repoName) && !in_array($repoName, $repoNames)) {
                $repoNames[] = $repoName;
            }
            if (!empty($author) && !in_array($author, $authors)) {
                $authors[] = $author;
            }
        }
        
        // 构建消息
        $message = "**SVN 批量操作通知**\n\n";
        $message .= "**时间范围**: " . date('Y-m-d H:i:s') . "\n";
        $message .= "**涉及仓库**: " . implode(', ', $repoNames) . "\n";
        $message .= "**操作用户**: " . implode(', ', $authors) . "\n\n";
        
        $message .= "**操作汇总**:\n";
        foreach ($eventsByType as $eventType => $typeEvents) {
            $count = count($typeEvents);
            $eventTypeText = $this->getEventTypeText($eventType);
            $message .= "- {$eventTypeText}: {$count} 次\n";
        }
        
        // 添加详细信息（最多显示前5个事件）
        $message .= "\n**详细信息**:\n";
        $displayEvents = array_slice($events, 0, 5);
        
        foreach ($displayEvents as $index => $event) {
            $eventTypeText = $this->getEventTypeText($event['event_type']);
            $repoName = $event['data']['repo_name'] ?? '';
            $author = $event['data']['author'] ?? '';
            $eventMessage = $event['data']['message'] ?? '';
            
            $message .= ($index + 1) . ". **{$eventTypeText}** - {$repoName} ({$author})\n";
            if (!empty($eventMessage)) {
                $message .= "   " . mb_substr($eventMessage, 0, 50) . (mb_strlen($eventMessage) > 50 ? '...' : '') . "\n";
            }
        }
        
        if (count($events) > 5) {
            $remaining = count($events) - 5;
            $message .= "   ... 还有 {$remaining} 个操作\n";
        }
        
        return $message;
    }
    
    /**
     * 构建同步消息
     *
     * @param string $syncType 同步类型
     * @param array $syncResult 同步结果
     * @return string 同步消息内容
     */
    public function buildSyncMessage($syncType, $syncResult)
    {
        $status = $syncResult['status'] ?? 0;
        $statusText = $status === 1 ? '✅ 成功' : '❌ 失败';
        $syncTypeText = $syncType === 'full' ? '全量同步' : '增量同步';
        
        $message = "**企业微信同步通知**\n\n";
        $message .= "**同步类型**: {$syncTypeText}\n";
        $message .= "**同步状态**: {$statusText}\n";
        $message .= "**同步时间**: " . date('Y-m-d H:i:s') . "\n\n";
        
        if ($status === 1 && isset($syncResult['data'])) {
            $stats = $syncResult['data'];
            $message .= "**同步统计**:\n";
            $message .= "- 部门: 创建 {$stats['departments']['created']}, 更新 {$stats['departments']['updated']}, 删除 {$stats['departments']['deleted']}\n";
            $message .= "- 用户: 创建 {$stats['users']['created']}, 更新 {$stats['users']['updated']}, 删除 {$stats['users']['deleted']}\n";
            $message .= "- 权限: 添加 {$stats['permissions']['added']}, 移除 {$stats['permissions']['removed']}\n";
            
            if (!empty($stats['errors'])) {
                $message .= "\n**错误**: " . count($stats['errors']) . " 个";
            }
            
            if (!empty($stats['warnings'])) {
                $message .= "\n**警告**: " . count($stats['warnings']) . " 个";
            }
        } else {
            $message .= "**错误信息**: " . ($syncResult['message'] ?? '未知错误');
        }
        
        return $message;
    }
    
    // ==================== SVN钩子模板相关 ====================
    
    /**
     * 生成调度器钩子内容
     *
     * @return string 调度器钩子内容
     */
    public function generateDispatcherContent()
    {
        $templateFile = __DIR__ . '/../../hook_dispatcher_template.sh';
        
        if (file_exists($templateFile)) {
            $template = file_get_contents($templateFile);
            // 替换占位符
            return str_replace('{{TIMESTAMP}}', date('Y-m-d H:i:s'), $template);
        }
        
        // 如果模板文件不存在，使用内嵌模板
        return $this->getEmbeddedDispatcherTemplate();
    }
    
    /**
     * 获取内嵌的调度器模板
     *
     * @return string 调度器模板内容
     */
    public function getEmbeddedDispatcherTemplate()
    {
        return '#!/bin/bash
# SVN Post-commit Hook Dispatcher
# Auto-generated by SVNAdmin - DO NOT EDIT MANUALLY
# Generated at: ' . date('Y-m-d H:i:s') . '

REPO="$1"
REV="$2"
HOOKS_DIR="$(dirname "$0")"
HOOKS_SUBDIR="$HOOKS_DIR/post-commit.d"

# 日志函数
log_message() {
    echo "[$(date \'+%Y-%m-%d %H:%M:%S\')] $1" >> "$HOOKS_DIR/post-commit.log"
}

log_message "=== Post-commit hook dispatcher started ==="
log_message "Repository: $REPO, Revision: $REV"

# 检查子钩子目录是否存在
if [ ! -d "$HOOKS_SUBDIR" ]; then
    log_message "Sub-hooks directory not found: $HOOKS_SUBDIR"
    exit 0
fi

# 统计执行结果
TOTAL_HOOKS=0
SUCCESS_HOOKS=0
FAILED_HOOKS=0

# 按文件名顺序执行所有可执行的子钩子
for hook in "$HOOKS_SUBDIR"/*; do
    if [ -f "$hook" ] && [ -x "$hook" ]; then
        HOOK_NAME=$(basename "$hook")
        TOTAL_HOOKS=$((TOTAL_HOOKS + 1))
        
        log_message "Executing hook: $HOOK_NAME"
        
        # 执行钩子并捕获输出
        if "$hook" "$REPO" "$REV" 2>&1 | while read line; do
            log_message "[$HOOK_NAME] $line"
        done; then
            SUCCESS_HOOKS=$((SUCCESS_HOOKS + 1))
            log_message "Hook $HOOK_NAME completed successfully"
        else
            FAILED_HOOKS=$((FAILED_HOOKS + 1))
            log_message "WARNING: Hook $HOOK_NAME failed with exit code $?"
        fi
    fi
done

log_message "=== Hook execution summary ==="
log_message "Total hooks: $TOTAL_HOOKS, Success: $SUCCESS_HOOKS, Failed: $FAILED_HOOKS"
log_message "=== Post-commit hook dispatcher finished ==="

exit 0';
    }
    
    /**
     * 获取直接API调用钩子内容
     *
     * @return string 钩子内容
     */
    public function getDirectApiHookContent()
    {
        // 读取正确的钩子模板文件
        $templateFile = BASE_PATH . '/templete/hooks/wecom_notify/post-commit';
        if (file_exists($templateFile)) {
            return file_get_contents($templateFile);
        }
        
        // 如果模板文件不存在，使用内嵌的直接API调用钩子
        return $this->getEmbeddedDirectApiHook();
    }
    
    /**
     * 获取内嵌的直接API调用钩子内容（备用）
     *
     * @return string 钩子内容
     */
    public function getEmbeddedDirectApiHook()
    {
        return '#!/bin/bash
# 正确UTF-8环境的企业微信通知钩子

REPO="$1"
REV="$2"

# 参数验证
[ -z "$REPO" ] || [ -z "$REV" ] && exit 0

# 设置正确的UTF-8环境变量（使用容器支持的locale）
export LANG=en_US.utf8
export LC_ALL=en_US.utf8
export LC_CTYPE=en_US.utf8

# 获取仓库名称
REPO_NAME=$(basename "$REPO")

# 在UTF-8环境下执行SVN命令
AUTHOR=$(svnlook author -r "$REV" "$REPO" 2>/dev/null || echo "unknown")
MESSAGE=$(svnlook log -r "$REV" "$REPO" 2>/dev/null || echo "")
CHANGED=$(svnlook changed -r "$REV" "$REPO" 2>/dev/null || echo "")

# 调用PHP脚本发送通知
/usr/bin/php -r "
// 强制设置UTF-8编码
mb_internal_encoding(\'UTF-8\');
ini_set(\'default_charset\', \'UTF-8\');

// 设置BASE_PATH
define(\'BASE_PATH\', \'/var/www/html\');

// 加载配置类
require_once BASE_PATH . \'/app/util/Config.php\';
Config::load(BASE_PATH . \'/config/\');

// 加载数据库配置并修复路径
\$dbConfig = Config::get(\'database\');
if (isset(\$dbConfig[\'database_file\'])) {
    \$dbConfig[\'database_file\'] = sprintf(\$dbConfig[\'database_file\'], \'/home/svnadmin/\');
}

// 加载Medoo
require_once BASE_PATH . \'/extension/Medoo-1.7.10/src/Medoo.php\';

try {
    // 加载现有的WeComNotification服务
    require_once BASE_PATH . \'/app/service/WeComNotification.php\';
    
    \$notification = new app\service\WeComNotification([\'hook_call\' => true]);
    
    // 构建事件数据
    \$eventData = [
        \'repo_name\' => \'$REPO_NAME\',
        \'revision\' => \'$REV\',
        \'author\' => \'$AUTHOR\',
        \'message\' => \'$MESSAGE\',
        \'timestamp\' => date(\'Y-m-d H:i:s\'),
        \'files\' => \'$CHANGED\',
        \'path\' => \'/\'
    ];
    
    // 发送通知
    \$result = \$notification->sendSvnNotification(\'commit\', \$eventData);
    
    if (\$result[\'status\'] === 1) {
        echo \'Notification sent successfully: \' . \$result[\'sent_count\'] . \' messages\' . PHP_EOL;
    } else {
        echo \'Notification failed: \' . \$result[\'message\'] . PHP_EOL;
    }
    
} catch (Exception \$e) {
    echo \'Error: \' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>&1

exit 0';
    }
}
?>
