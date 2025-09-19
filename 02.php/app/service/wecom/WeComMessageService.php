<?php
/**
 * 企业微信消息服务
 * 
 * 负责消息构建、发送和相关的业务逻辑
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service\wecom;

require_once BASE_PATH . '/app/util/wecom/WeComTemplateManager.php';
require_once BASE_PATH . '/app/util/wecom/WeComUserMapper.php';

class WeComMessageService
{
    /**
     * 企业微信API服务
     * @var object
     */
    private $wecomAPI;
    
    /**
     * 模板管理器
     * @var \app\util\wecom\WeComTemplateManager
     */
    private $templateManager;
    
    /**
     * 用户映射器
     * @var \app\util\wecom\WeComUserMapper
     */
    private $userMapper;
    
    /**
     * 数据库连接
     * @var object
     */
    private $database;
    
    /**
     * 日志服务
     * @var object
     */
    private $logger;
    
    /**
     * 通知配置
     * @var array
     */
    private $notificationConfig;
    
    /**
     * 构造函数
     * 
     * @param object $wecomAPI 企业微信API服务
     * @param object $database 数据库连接
     * @param object $logger 日志服务
     * @param array $wecomConfig 企业微信配置
     */
    public function __construct($wecomAPI, $database, $logger, $wecomConfig)
    {
        $this->wecomAPI = $wecomAPI;
        $this->database = $database;
        $this->logger = $logger;
        $this->notificationConfig = $wecomConfig['notification'] ?? [];
        
        // 初始化工具类
        $messageTemplates = $wecomConfig['message_templates'] ?? [];
        $this->templateManager = new \app\util\wecom\WeComTemplateManager($messageTemplates);
        $this->userMapper = new \app\util\wecom\WeComUserMapper($database, $logger);
    }
    
    /**
     * 构建消息内容
     *
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @param string $customTemplate 自定义模板
     * @return string 构建的消息内容
     */
    public function buildMessage($eventType, $eventData, $customTemplate = null)
    {
        // 转换SVN用户名为真实姓名
        if (isset($eventData['author'])) {
            $eventData['author'] = $this->userMapper->convertSvnUsernameToRealName($eventData['author']);
        }
        
        // 使用自定义模板或默认模板
        $template = $customTemplate ?: $this->templateManager->getDefaultTemplate($eventType);
        
        if (empty($template)) {
            $template = $this->templateManager->getGenericTemplate();
        }
        
        // 准备模板变量
        $variables = $this->templateManager->prepareTemplateVariables($eventType, $eventData);
        
        // 替换模板变量
        $message = $this->templateManager->replaceTemplateVariables($template, $variables);
        
        return $message;
    }
    
    /**
     * 根据多个规则发送通知
     *
     * @param array $rules 通知规则列表
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 发送结果
     */
    public function sendNotificationsByRules($rules, $eventType, $eventData)
    {
        $results = [];
        $sentCount = 0;
        
        foreach ($rules as $rule) {
            try {
                $result = $this->sendNotificationByRule($rule, $eventType, $eventData);
                $results[] = $result;
                
                if ($result['status'] === 1) {
                    $sentCount++;
                }
                
            } catch (\Exception $e) {
                $this->logError('通知发送失败', "规则ID: {$rule['id']}, 错误: " . $e->getMessage());
                $results[] = [
                    'rule_id' => $rule['id'],
                    'status' => 0,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        $this->logInfo('SVN操作通知发送完成', [
            'event_type' => $eventType,
            'rules_count' => count($rules),
            'sent_count' => $sentCount
        ]);
        
        return [
            'status' => 1,
            'message' => '通知发送完成',
            'sent_count' => $sentCount,
            'results' => $results
        ];
    }
    
    /**
     * 根据规则发送通知
     *
     * @param array $rule 通知规则
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 发送结果
     */
    public function sendNotificationByRule($rule, $eventType, $eventData)
    {
        $ruleId = $rule['id'];
        $webhookUrl = $rule['webhook_url'] ?? '';
        $messageTemplate = $rule['message_template'] ?? '';
        $notifyUserIds = $rule['notify_wecom_userids'] ?? '';
        $notifyDeptIds = $rule['notify_wecom_deptids'] ?? '';
        
        // 构建消息内容
        $message = $this->buildMessage($eventType, $eventData, $messageTemplate);
        
        $results = [];
        $successCount = 0;
        
        // 1. 发送 Webhook 消息（如果配置了 webhook_url）
        if (!empty($webhookUrl)) {
            try {
                // 检查频率限制
                if (!$this->checkRateLimit($webhookUrl)) {
                    throw new \Exception('Webhook发送频率超限，请稍后重试');
                }
                
                $result = $this->wecomAPI->sendGroupMarkdownMessage($webhookUrl, $message);
                $results['webhook'] = $result;
                $successCount++;
                
                $this->logInfo('Webhook消息发送成功', [
                    'rule_id' => $ruleId, 
                    'webhook_url' => substr($webhookUrl, 0, 50) . '...'
                ]);
            } catch (\Exception $e) {
                $results['webhook'] = ['status' => 0, 'message' => $e->getMessage()];
                $this->logError('Webhook消息发送失败', "规则ID: {$ruleId}, 错误: " . $e->getMessage());
            }
        }
        
        // 2. 发送应用消息给指定用户（如果配置了用户ID）
        if (!empty($notifyUserIds)) {
            try {
                $userIds = array_filter(explode(',', $notifyUserIds));
                if (!empty($userIds)) {
                    $result = $this->wecomAPI->sendMarkdownMessage($message, implode('|', $userIds));
                    $results['users'] = $result;
                    $successCount++;
                    
                    $this->logInfo('应用消息发送给用户成功', [
                        'rule_id' => $ruleId, 
                        'user_count' => count($userIds)
                    ]);
                }
            } catch (\Exception $e) {
                $results['users'] = ['status' => 0, 'message' => $e->getMessage()];
                $this->logError('应用消息发送给用户失败', "规则ID: {$ruleId}, 错误: " . $e->getMessage());
            }
        }
        
        // 3. 发送应用消息给指定部门（如果配置了部门ID）
        if (!empty($notifyDeptIds)) {
            try {
                $deptIds = array_filter(explode(',', $notifyDeptIds));
                if (!empty($deptIds)) {
                    $result = $this->wecomAPI->sendMarkdownMessage($message, '', implode('|', $deptIds));
                    $results['departments'] = $result;
                    $successCount++;
                    
                    $this->logInfo('应用消息发送给部门成功', [
                        'rule_id' => $ruleId, 
                        'dept_count' => count($deptIds)
                    ]);
                }
            } catch (\Exception $e) {
                $results['departments'] = ['status' => 0, 'message' => $e->getMessage()];
                $this->logError('应用消息发送给部门失败', "规则ID: {$ruleId}, 错误: " . $e->getMessage());
            }
        }
        
        // 记录通知日志
        $this->recordNotificationLog($ruleId, $eventType, $eventData['repo_name'] ?? '', $message, $results);
        
        // 如果没有配置任何通知目标，返回错误
        if (empty($webhookUrl) && empty($notifyUserIds) && empty($notifyDeptIds)) {
            return [
                'rule_id' => $ruleId,
                'status' => 0,
                'message' => '规则未配置任何通知目标（Webhook URL、用户或部门）',
                'results' => $results
            ];
        }
        
        return [
            'rule_id' => $ruleId,
            'status' => $successCount > 0 ? 1 : 0,
            'message' => $successCount > 0 ? "通知发送成功 ({$successCount}个目标)" : '所有通知发送失败',
            'results' => $results
        ];
    }
    
    /**
     * 测试通知发送
     *
     * @param string $webhookUrl Webhook URL
     * @param string $message 消息内容
     * @param string $notifyUserIds 用户ID列表
     * @param string $notifyDeptIds 部门ID列表
     * @return array 测试结果
     */
    public function testNotification($webhookUrl = '', $message = null, $notifyUserIds = '', $notifyDeptIds = '')
    {
        try {
            $testMessage = $message ?: "**测试通知**\n\n这是一条来自 SVNAdmin 的测试通知。\n\n时间: " . date('Y-m-d H:i:s');
            
            $results = [];
            $successCount = 0;
            
            // 1. 测试 Webhook 消息发送
            if (!empty($webhookUrl)) {
                try {
                    $result = $this->wecomAPI->sendGroupMarkdownMessage($webhookUrl, $testMessage);
                    $results['webhook'] = $result;
                    $successCount++;
                    
                    $this->logInfo('测试Webhook消息发送成功', [
                        'webhook_url' => substr($webhookUrl, 0, 50) . '...'
                    ]);
                } catch (\Exception $e) {
                    $results['webhook'] = ['status' => 0, 'message' => $e->getMessage()];
                    $this->logError('测试Webhook消息发送失败', $e->getMessage());
                }
            }
            
            // 2. 测试应用消息发送给用户
            if (!empty($notifyUserIds)) {
                try {
                    $userIds = array_filter(explode(',', $notifyUserIds));
                    if (!empty($userIds)) {
                        $result = $this->wecomAPI->sendMarkdownMessage($testMessage, implode('|', $userIds));
                        $results['users'] = $result;
                        $successCount++;
                        
                        $this->logInfo('测试应用消息发送给用户成功', [
                            'user_count' => count($userIds),
                            'user_ids' => implode(',', $userIds)
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['users'] = ['status' => 0, 'message' => $e->getMessage()];
                    $this->logError('测试应用消息发送给用户失败', $e->getMessage());
                }
            }
            
            // 3. 测试应用消息发送给部门
            if (!empty($notifyDeptIds)) {
                try {
                    $deptIds = array_filter(explode(',', $notifyDeptIds));
                    if (!empty($deptIds)) {
                        $result = $this->wecomAPI->sendMarkdownMessage($testMessage, '', implode('|', $deptIds));
                        $results['departments'] = $result;
                        $successCount++;
                        
                        $this->logInfo('测试应用消息发送给部门成功', [
                            'dept_count' => count($deptIds),
                            'dept_ids' => implode(',', $deptIds)
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['departments'] = ['status' => 0, 'message' => $e->getMessage()];
                    $this->logError('测试应用消息发送给部门失败', $e->getMessage());
                }
            }
            
            return [
                'status' => $successCount > 0 ? 1 : 0,
                'message' => $successCount > 0 ? "测试通知发送成功 ({$successCount}个目标)" : '所有测试通知发送失败',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            $this->logError('测试通知发送失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '测试通知发送失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 发送合并通知
     *
     * @param int $ruleId 规则ID
     * @param array $ruleEvents 规则事件数组
     * @return array 发送结果
     */
    public function sendMergedNotification($ruleId, $ruleEvents)
    {
        $rule = $ruleEvents['rule'];
        $events = $ruleEvents['events'];
        
        // 构建合并消息
        $message = $this->templateManager->buildMergedMessage($events);
        
        // 发送消息
        $result = $this->wecomAPI->sendGroupMarkdownMessage($rule['webhook_url'], $message);
        
        // 记录通知日志
        $this->recordNotificationLog($ruleId, 'merged', 'multiple', $message, $result);
        
        $this->logInfo('发送合并通知完成', [
            'rule_id' => $ruleId,
            'events_count' => count($events)
        ]);
        
        return [
            'rule_id' => $ruleId,
            'status' => 1,
            'message' => '合并通知发送成功',
            'events_count' => count($events),
            'result' => $result
        ];
    }
    
    /**
     * 检查发送频率限制
     *
     * @param string $webhookUrl Webhook URL
     * @return bool 是否允许发送
     */
    private function checkRateLimit($webhookUrl)
    {
        if (!($this->notificationConfig['rate_limit']['enabled'] ?? false)) {
            return true;
        }
        
        $maxMessages = $this->notificationConfig['rate_limit']['max_messages_per_minute'] ?? 20;
        $cacheKey = 'wecom_notification_rate_' . md5($webhookUrl);
        
        // 使用简单的文件缓存
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $currentTime = time();
            
            // 清理过期的记录（超过1分钟）
            $data = array_filter($data, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60;
            });
            
            if (count($data) >= $maxMessages) {
                return false;
            }
            
            $data[] = $currentTime;
        } else {
            $data = [time()];
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    /**
     * 记录通知日志
     *
     * @param int|null $ruleId 规则ID
     * @param string $eventType 事件类型
     * @param string $repoName 仓库名称
     * @param string $message 消息内容
     * @param array $result 发送结果
     * @return void
     */
    private function recordNotificationLog($ruleId, $eventType, $repoName, $message, $result)
    {
        try {
            // 判断发送状态 - 检查多种可能的成功标识
            $sendStatus = 'failed';
            if (is_array($result)) {
                // 检查 webhook 结果
                if (isset($result['webhook']['errcode']) && $result['webhook']['errcode'] === 0) {
                    $sendStatus = 'success';
                }
                // 检查用户消息结果
                elseif (isset($result['users']['errcode']) && $result['users']['errcode'] === 0) {
                    $sendStatus = 'success';
                }
                // 检查部门消息结果
                elseif (isset($result['departments']['errcode']) && $result['departments']['errcode'] === 0) {
                    $sendStatus = 'success';
                }
                // 检查直接的 errcode
                elseif (isset($result['errcode']) && $result['errcode'] === 0) {
                    $sendStatus = 'success';
                }
            }
            
            $this->database->insert('wecom_notification_logs', [
                'rule_id' => $ruleId,
                'repo_name' => $repoName,
                'event_type' => $eventType,
                'author' => '',
                'message' => '',
                'files_changed' => '',
                'chat_id' => '',
                'notification_content' => $message,
                'send_status' => $sendStatus,
                'response_data' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'error_message' => $sendStatus === 'failed' ? json_encode($result, JSON_UNESCAPED_UNICODE) : '',
                'retry_count' => 0,
                'sent_at' => $sendStatus === 'success' ? date('Y-m-d H:i:s') : '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            $this->logError('记录通知日志失败', $e->getMessage());
        }
    }
    
    /**
     * 记录信息日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    private function logInfo($message, $context = [])
    {
        if ($this->logger) {
            $logMessage = '[WeComMessageService] ' . $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            $this->logger->WriteLog($logMessage, 'wecom_notification');
        }
    }
    
    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param string $error 错误详情
     * @return void
     */
    private function logError($message, $error = '')
    {
        if ($this->logger) {
            $logMessage = '[WeComMessageService ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            $this->logger->WriteLog($logMessage, 'wecom_notification');
        }
    }
}
?>
