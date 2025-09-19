<?php
/**
 * 钩子测试通知工具类
 * 
 * 用于在创建通知规则后自动发送测试通知，验证钩子和通知功能是否正常工作
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\util;

class HookTestNotifier
{
    private $ServiceLogs;
    private $wecomNotification;
    private $database;
    
    /**
     * 构造函数
     */
    public function __construct($parm = [])
    {
        // 初始化日志服务
        require_once BASE_PATH . '/app/service/ServiceLogs.php';
        $this->ServiceLogs = new \app\service\ServiceLogs($parm);
        
        // 初始化数据库连接
        require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
        require_once BASE_PATH . '/app/util/Config.php';
        \Config::load(BASE_PATH . '/config/');
        $dbConfig = \Config::get('database');
        
        if (isset($dbConfig['database_file'])) {
            $dbConfig['database_file'] = sprintf($dbConfig['database_file'], '/home/svnadmin/');
        }
        
        $this->database = new \Medoo\Medoo($dbConfig);
    }
    
    /**
     * 为新创建的通知规则发送测试通知
     * 
     * @param int $ruleId 通知规则ID
     * @param array $hookInstallResult 钩子安装结果
     * @return array 测试结果
     */
    public function sendRuleCreationTestNotification($ruleId, $hookInstallResult = [])
    {
        try {
            $this->logInfo('开始发送钩子创建测试通知', ['rule_id' => $ruleId]);
            
            // 1. 获取通知规则详情
            $rule = $this->getNotificationRule($ruleId);
            if (!$rule) {
                throw new \Exception("通知规则不存在: ID {$ruleId}");
            }
            
            // 2. 初始化企业微信通知服务
            $this->initWeComNotification();
            
            // 3. 构建测试通知内容
            $testNotificationData = $this->buildTestNotificationData($rule, $hookInstallResult);
            
            // 4. 发送测试通知
            $sendResult = $this->sendTestNotification($rule, $testNotificationData);
            
            // 5. 记录测试结果
            $this->recordTestResult($ruleId, $sendResult);
            
            $this->logInfo('钩子创建测试通知完成', [
                'rule_id' => $ruleId,
                'success' => $sendResult['success'],
                'sent_count' => $sendResult['sent_count'] ?? 0
            ]);
            
            return [
                'status' => 1,
                'message' => '测试通知发送完成',
                'test_result' => $sendResult,
                'rule_info' => $rule
            ];
            
        } catch (\Exception $e) {
            $this->logError('钩子创建测试通知失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '测试通知发送失败: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * 获取通知规则详情
     */
    private function getNotificationRule($ruleId)
    {
        return $this->database->get('wecom_notification_rules', '*', ['id' => $ruleId]);
    }
    
    /**
     * 初始化企业微信通知服务
     */
    private function initWeComNotification()
    {
        try {
            require_once BASE_PATH . '/app/service/WeComNotification.php';
            $this->wecomNotification = new \app\service\WeComNotification(['hook_call' => false]);
        } catch (\Exception $e) {
            throw new \Exception('企业微信通知服务初始化失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 构建测试通知数据
     */
    private function buildTestNotificationData($rule, $hookInstallResult)
    {
        // 获取通知目标信息
        $targets = $this->parseNotificationTargets($rule);
        
        // 构建钩子安装状态信息
        $hookStatus = $this->formatHookInstallStatus($hookInstallResult);
        
        // 构建测试消息内容
        $message = $this->buildTestMessage($rule, $targets, $hookStatus);
        
        return [
            'repo_name' => $rule['repo_name'],
            'revision' => 'TEST',
            'author' => 'System',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'files' => '📋 这是一条钩子创建成功的测试通知',
            'path' => '/',
            'test_mode' => true,
            'rule_id' => $rule['id'],
            'rule_name' => $rule['rule_name']
        ];
    }
    
    /**
     * 解析通知目标
     */
    private function parseNotificationTargets($rule)
    {
        $targets = [
            'webhook_url' => !empty($rule['webhook_url']) ? $rule['webhook_url'] : null,
            'users' => [],
            'departments' => []
        ];
        
        // 解析用户ID
        if (!empty($rule['notify_wecom_userids'])) {
            $userIds = array_filter(explode(',', $rule['notify_wecom_userids']));
            foreach ($userIds as $userId) {
                $userInfo = $this->getUserInfo(trim($userId));
                $targets['users'][] = $userInfo;
            }
        }
        
        // 解析部门ID
        if (!empty($rule['notify_wecom_deptids'])) {
            $deptIds = array_filter(explode(',', $rule['notify_wecom_deptids']));
            foreach ($deptIds as $deptId) {
                $deptInfo = $this->getDepartmentInfo(trim($deptId));
                $targets['departments'][] = $deptInfo;
            }
        }
        
        return $targets;
    }
    
    /**
     * 获取用户信息
     */
    private function getUserInfo($userId)
    {
        $user = $this->database->get('wecom_users', ['userid', 'name', 'department'], ['userid' => $userId]);
        return $user ?: ['userid' => $userId, 'name' => '未知用户', 'department' => ''];
    }
    
    /**
     * 获取部门信息
     */
    private function getDepartmentInfo($deptId)
    {
        $dept = $this->database->get('wecom_departments', ['id', 'name', 'parentid'], ['id' => $deptId]);
        return $dept ?: ['id' => $deptId, 'name' => '未知部门', 'parentid' => 0];
    }
    
    /**
     * 格式化钩子安装状态
     */
    private function formatHookInstallStatus($hookInstallResult)
    {
        if (empty($hookInstallResult)) {
            return '⚠️ 钩子安装状态未知';
        }
        
        $status = $hookInstallResult['status'] ?? 0;
        $message = $hookInstallResult['message'] ?? '';
        
        if ($status === 1) {
            $installedRepos = $hookInstallResult['installed_repos'] ?? [];
            return '✅ 钩子安装成功' . (count($installedRepos) > 0 ? ' (仓库: ' . implode(', ', $installedRepos) . ')' : '');
        } else {
            $failedRepos = $hookInstallResult['failed_repos'] ?? [];
            $errorDetails = '';
            if (!empty($failedRepos)) {
                $errors = array_map(function($failed) {
                    return $failed['repo'] . ': ' . $failed['error'];
                }, $failedRepos);
                $errorDetails = ' (错误: ' . implode('; ', $errors) . ')';
            }
            return '❌ 钩子安装失败: ' . $message . $errorDetails;
        }
    }
    
    /**
     * 构建测试消息内容
     */
    private function buildTestMessage($rule, $targets, $hookStatus)
    {
        $lines = [];
        $lines[] = "🎉 **通知规则创建成功测试**";
        $lines[] = "";
        $lines[] = "📋 **规则信息**";
        $lines[] = "• 规则名称: {$rule['rule_name']}";
        $lines[] = "• 关联仓库: {$rule['repo_name']}";
        $lines[] = "• 事件类型: " . $this->formatEventType($rule['event_type']);
        $lines[] = "• 路径前缀: {$rule['path_prefix']}";
        $lines[] = "• 规则状态: " . ($rule['enable'] ? '✅ 启用' : '❌ 禁用');
        $lines[] = "";
        
        $lines[] = "🔧 **钩子状态**";
        $lines[] = "• " . $hookStatus;
        $lines[] = "";
        
        $lines[] = "📢 **通知目标**";
        
        if ($targets['webhook_url']) {
            $lines[] = "• Webhook: " . $this->maskUrl($targets['webhook_url']);
        }
        
        if (!empty($targets['users'])) {
            $userNames = array_map(function($user) {
                return $user['name'] . '(' . $user['userid'] . ')';
            }, $targets['users']);
            $lines[] = "• 用户: " . implode(', ', $userNames);
        }
        
        if (!empty($targets['departments'])) {
            $deptNames = array_map(function($dept) {
                return $dept['name'] . '(' . $dept['id'] . ')';
            }, $targets['departments']);
            $lines[] = "• 部门: " . implode(', ', $deptNames);
        }
        
        if (empty($targets['webhook_url']) && empty($targets['users']) && empty($targets['departments'])) {
            $lines[] = "• ⚠️ 未配置任何通知目标";
        }
        
        $lines[] = "";
        $lines[] = "⏰ 测试时间: " . date('Y-m-d H:i:s');
        $lines[] = "🏷️ 这是一条自动生成的测试通知，用于验证通知功能是否正常工作。";
        
        return implode("\n", $lines);
    }
    
    /**
     * 格式化事件类型
     */
    private function formatEventType($eventType)
    {
        $types = [
            'commit' => '提交代码',
            'update' => '更新代码',
            'add' => '添加文件',
            'delete' => '删除文件',
            'modify' => '修改文件'
        ];
        
        return $types[$eventType] ?? $eventType;
    }
    
    /**
     * 遮蔽URL敏感信息
     */
    private function maskUrl($url)
    {
        if (strlen($url) > 50) {
            return substr($url, 0, 30) . '...' . substr($url, -10);
        }
        return $url;
    }
    
    /**
     * 发送测试通知
     */
    private function sendTestNotification($rule, $testData)
    {
        try {
            // 使用现有的通知服务发送测试通知
            $result = $this->wecomNotification->sendSvnNotification('commit', $testData);
            
            return [
                'success' => $result['status'] === 1,
                'message' => $result['message'],
                'sent_count' => $result['sent_count'] ?? 0,
                'details' => $result
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '发送失败: ' . $e->getMessage(),
                'sent_count' => 0,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * 记录测试结果
     */
    private function recordTestResult($ruleId, $sendResult)
    {
        try {
            $this->database->insert('wecom_notification_logs', [
                'rule_id' => $ruleId,
                'event_type' => 'test',
                'repo_name' => 'TEST',
                'revision' => 'TEST',
                'author' => 'System',
                'message' => '钩子创建测试通知',
                'notification_targets' => json_encode([
                    'type' => 'rule_creation_test',
                    'result' => $sendResult
                ]),
                'send_status' => $sendResult['success'] ? 1 : 0,
                'send_result' => json_encode($sendResult),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('记录测试结果失败', $e->getMessage());
        }
    }
    
    /**
     * 获取测试通知历史
     */
    public function getTestNotificationHistory($ruleId = null, $limit = 10)
    {
        try {
            $where = ['event_type' => 'test'];
            if ($ruleId) {
                $where['rule_id'] = $ruleId;
            }
            
            return $this->database->select('wecom_notification_logs', [
                'id',
                'rule_id',
                'repo_name',
                'author',
                'message',
                'send_status',
                'send_result',
                'created_at'
            ], array_merge($where, [
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => $limit
            ]));
            
        } catch (\Exception $e) {
            $this->logError('获取测试通知历史失败', $e->getMessage());
            return [];
        }
    }
    
    /**
     * 手动触发测试通知
     */
    public function triggerManualTest($ruleId)
    {
        return $this->sendRuleCreationTestNotification($ruleId, [
            'status' => 1,
            'message' => '手动触发测试',
            'installed_repos' => ['手动测试']
        ]);
    }
    
    /**
     * 记录信息日志
     */
    private function logInfo($message, $data = [])
    {
        if ($this->ServiceLogs) {
            $this->ServiceLogs->writeLog('info', $message, $data);
        }
    }
    
    /**
     * 记录错误日志
     */
    private function logError($message, $error = '')
    {
        if ($this->ServiceLogs) {
            $this->ServiceLogs->writeLog('error', $message, ['error' => $error]);
        }
    }
}
?>
