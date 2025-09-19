<?php

use Medoo\Medoo;

/**
 * 企业微信通知处理器
 * 专门处理SVN提交的企业微信通知功能
 */
class WeComNotificationHandler
{
    private $configSvn;
    private $lastCleanupTime;

    public function __construct($configSvn)
    {
        $this->configSvn = $configSvn;
        $this->lastCleanupTime = 0;
        
        // 确保企业微信通知所需的目录存在
        $this->ensureDirectories();
    }

    /**
     * 处理企业微信通知（主入口方法）
     */
    public function process()
    {
        try {
            // 检查企业微信通知功能是否启用
            if (!$this->isNotificationEnabled()) {
                return;
            }
            
            // 处理钩子事件文件
            $this->processHookEventFiles();
            
            // 处理通知队列
            $this->processNotificationQueue();
            
            // 定期清理过期通知（每小时一次）
            if (time() - $this->lastCleanupTime >= 3600) {
                $this->cleanupExpiredNotifications();
                $this->lastCleanupTime = time();
            }
            
        } catch (\Exception $e) {
            $this->logError('企业微信通知处理异常: ' . $e->getMessage());
        }
    }

    /**
     * 确保企业微信通知所需的目录存在
     */
    private function ensureDirectories()
    {
        $directories = [
            '/tmp/svn_hooks',
            '/tmp/svn_hooks/processed'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
                @chmod($dir, 0755);
            }
        }
    }

    /**
     * 检查企业微信通知功能是否启用
     */
    private function isNotificationEnabled()
    {
        try {
            $configDatabase = Config::get('database');
            if (array_key_exists('database_file', $configDatabase)) {
                $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $this->configSvn['home_path']);
            }
            
            $database = new Medoo($configDatabase);
            $wecomConfig = $database->get('wecom_config', '*', ['id' => 1]);
            
            return $wecomConfig && ($wecomConfig['notification_enabled'] ?? false);
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 处理钩子事件文件
     */
    private function processHookEventFiles()
    {
        $hookDir = '/tmp/svn_hooks';
        $processedDir = '/tmp/svn_hooks/processed';
        
        if (!is_dir($hookDir)) {
            return;
        }
        
        $files = glob($hookDir . '/hook_*.json');
        
        if (empty($files)) {
            return;
        }
        
        $database = $this->getDatabase();
        if (!$database) {
            return;
        }
        
        foreach ($files as $file) {
            try {
                $jsonContent = @file_get_contents($file);
                if ($jsonContent === false) {
                    $this->logError("无法读取文件: $file");
                    continue;
                }
                
                $eventData = @json_decode($jsonContent, true);
                if ($eventData === null) {
                    $this->logError("JSON解析失败: $file");
                    $this->moveToProcessed($file, $processedDir, 'error_');
                    continue;
                }
                
                if (!isset($eventData['repo_name']) || !isset($eventData['revision'])) {
                    $this->logError("事件数据缺少必要字段: $file");
                    $this->moveToProcessed($file, $processedDir, 'invalid_');
                    continue;
                }
                
                // 构建队列数据
                $queueData = [
                    'notification_type' => 'svn_commit',
                    'event_data' => json_encode([
                        'repo_name' => $eventData['repo_name'],
                        'revision' => (int)$eventData['revision'],
                        'author' => trim($eventData['author'] ?? 'unknown'),
                        'commit_message' => trim($eventData['commit_message'] ?? ''),
                        'changed_files' => $eventData['changed_files'] ?? [],
                        'timestamp' => $eventData['timestamp'] ?? date('c')
                    ], JSON_UNESCAPED_UNICODE),
                    'webhook_url' => '',
                    'message_template' => '',
                    'status' => 'pending',
                    'retry_count' => 0,
                    'max_retries' => 3,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $database->insert('wecom_notification_queue', $queueData);
                $queueId = $database->id();
                
                if ($queueId) {
                    $this->logInfo("钩子事件已添加到队列，ID: {$queueId}，仓库: {$eventData['repo_name']}，版本: {$eventData['revision']}");
                    $this->moveToProcessed($file, $processedDir);
                }
                
            } catch (\Exception $e) {
                $this->logError("处理钩子事件文件失败 $file: " . $e->getMessage());
                $this->moveToProcessed($file, $processedDir, 'error_');
            }
        }
    }

    /**
     * 处理通知队列
     */
    private function processNotificationQueue()
    {
        try {
            $database = $this->getDatabase();
            if (!$database) {
                return;
            }
            
            // 获取待处理的通知（支持新的队列结构）
            $notifications = $database->select('wecom_notification_queue', '*', [
                'status' => 'pending',
                'ORDER' => ['created_at' => 'ASC'],
                'LIMIT' => 5
            ]);
            
            if (empty($notifications)) {
                return;
            }
            
            foreach ($notifications as $notification) {
                try {
                    $queueId = $notification['id'] ?? $notification['queue_id'];
                    
                    // 标记为处理中
                    $database->update('wecom_notification_queue', [
                        'status' => 'processing',
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['id' => $queueId]);
                    
                    // 构建事件数据（支持新旧两种队列结构）
                    $eventData = $this->buildEventDataFromQueue($notification);
                    if (!$eventData) {
                        throw new \Exception('事件数据构建失败');
                    }
                    
                    // 发送通知
                    $result = $this->sendNotification($eventData, $database);
                    
                    if ($result['success']) {
                        // 发送成功
                        $database->update('wecom_notification_queue', [
                            'status' => 'completed',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], ['id' => $queueId]);
                        
                        $this->logInfo("通知发送成功，队列ID: $queueId");
                    } else {
                        // 发送失败，处理重试
                        $this->handleNotificationFailure($notification, $result['error'], $database);
                    }
                    
                } catch (\Exception $e) {
                    $queueId = $notification['id'] ?? $notification['queue_id'];
                    $this->logError("处理通知队列项失败，队列ID: {$queueId}: " . $e->getMessage());
                    $this->handleNotificationFailure($notification, $e->getMessage(), $database);
                }
            }
            
        } catch (\Exception $e) {
            $this->logError("处理通知队列失败: " . $e->getMessage());
        }
    }

    /**
     * 构建事件数据（支持新旧队列结构）
     */
    private function buildEventDataFromQueue($notification)
    {
        try {
            // 新的轻量级队列结构
            if (isset($notification['repo_path']) && isset($notification['revision'])) {
                // 从repo_path提取仓库名
                $repoName = basename($notification['repo_path']);
                
                return [
                    'repo_name' => $repoName,
                    'revision' => $notification['revision'],
                    'author' => $notification['author'] ?? 'unknown',
                    'commit_message' => $notification['message'] ?? '',
                    'changed_files' => $this->parseChangedPaths($notification['changed_paths'] ?? ''),
                    'timestamp' => $notification['created_at'] ?? date('c')
                ];
            }
            
            // 旧的JSON事件数据结构
            if (isset($notification['event_data'])) {
                $eventData = @json_decode($notification['event_data'], true);
                if ($eventData) {
                    return $eventData;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logError('构建事件数据失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 解析changed_paths字符串为文件数组
     */
    private function parseChangedPaths($changedPaths)
    {
        if (empty($changedPaths)) {
            return [];
        }
        
        $lines = explode("\n", trim($changedPaths));
        $files = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // SVN changed格式: "A   /path/to/file" 或 "M   /path/to/file"
                if (preg_match('/^[AMDRC]\s+(.+)$/', $line, $matches)) {
                    $files[] = $matches[1];
                } else {
                    $files[] = $line;
                }
            }
        }
        
        return $files;
    }

    /**
     * 发送企业微信通知
     */
    private function sendNotification($eventData, $database)
    {
        try {
            // 获取通知规则
            $rules = $this->getNotificationRules($eventData, $database);
            
            if (empty($rules)) {
                return ['success' => false, 'error' => '没有适用的通知规则'];
            }
            
            $successCount = 0;
            $errors = [];
            
            foreach ($rules as $rule) {
                try {
                    // 构建消息内容
                    $message = $this->buildNotificationMessage($eventData, $rule);
                    
                    $ruleResults = [];
                    $ruleSuccessCount = 0;
                    
                    // 1. 发送 Webhook 消息（群机器人）
                    if (!empty($rule['webhook_url'])) {
                        $result = $this->sendToWecom($rule['webhook_url'], $message);
                        $ruleResults['webhook'] = $result;
                        if ($result['success']) {
                            $ruleSuccessCount++;
                        }
                    }
                    
                    // 2. 发送应用消息给指定用户
                    if (!empty($rule['notify_wecom_userids'])) {
                        $result = $this->sendApplicationMessageToUsers($message, $rule['notify_wecom_userids']);
                        $ruleResults['users'] = $result;
                        if ($result['success']) {
                            $ruleSuccessCount++;
                        }
                    }
                    
                    // 3. 发送应用消息给指定部门
                    if (!empty($rule['notify_wecom_deptids'])) {
                        $result = $this->sendApplicationMessageToDepts($message, $rule['notify_wecom_deptids']);
                        $ruleResults['departments'] = $result;
                        if ($result['success']) {
                            $ruleSuccessCount++;
                        }
                    }
                    
                    if ($ruleSuccessCount > 0) {
                        $successCount++;
                        
                        // 记录成功日志
                        $this->recordNotificationLog($rule['id'], $eventData, $message, 'success', json_encode($ruleResults), $database);
                        $this->logInfo("规则 {$rule['id']} 通知发送成功，成功目标数: $ruleSuccessCount");
                    } else {
                        $errorMsg = "规则 {$rule['id']} 所有通知发送失败";
                        $errors[] = $errorMsg;
                        
                        // 记录失败日志
                        $this->recordNotificationLog($rule['id'], $eventData, $message, 'failed', json_encode($ruleResults), $database);
                        $this->logError($errorMsg);
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "规则 {$rule['id']} 处理失败: " . $e->getMessage();
                }
            }
            
            return [
                'success' => $successCount > 0,
                'error' => empty($errors) ? '' : implode('; ', $errors)
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取通知规则
     */
    private function getNotificationRules($eventData, $database)
    {
        try {
            $repoName = $eventData['repo_name'] ?? '';
            
            // 查询匹配的通知规则
            $rules = $database->select('wecom_notification_rules', '*', [
                'AND' => [
                    'enable' => 1,
                    'OR' => [
                        'repo_name' => $repoName,
                        'repo_name' => '*'
                    ]
                ]
            ]);
            
            // 过滤事件类型
            $applicableRules = [];
            foreach ($rules as $rule) {
                $ruleEvents = array_map('trim', explode(',', $rule['event_type']));
                if (in_array('commit', $ruleEvents)) {
                    $applicableRules[] = $rule;
                }
            }
            
            return $applicableRules;
            
        } catch (\Exception $e) {
            $this->logError('获取通知规则失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 构建通知消息
     */
    private function buildNotificationMessage($eventData, $rule)
    {
        $template = $rule['message_template'] ?: $this->getDefaultTemplate();
        
        $variables = [
            '{repo_name}' => $eventData['repo_name'] ?? '',
            '{author}' => $eventData['author'] ?? '',
            '{revision}' => $eventData['revision'] ?? '',
            '{message}' => $eventData['commit_message'] ?? '',
            '{files}' => is_array($eventData['changed_files']) ? implode("\n", $eventData['changed_files']) : '',
            '{timestamp}' => date('Y-m-d H:i:s')
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    /**
     * 获取默认消息模板
     */
    private function getDefaultTemplate()
    {
        return "**SVN提交通知**\n\n" .
               "**仓库**: {repo_name}\n" .
               "**作者**: {author}\n" .
               "**版本**: {revision}\n" .
               "**消息**: {message}\n" .
               "**文件**: {files}\n" .
               "**时间**: {timestamp}";
    }

    /**
     * 发送到企业微信（Webhook群机器人）
     */
    private function sendToWecom($webhookUrl, $message)
    {
        try {
            if (empty($webhookUrl)) {
                return ['success' => false, 'error' => 'Webhook URL为空'];
            }
            
            $data = [
                'msgtype' => 'markdown',
                'markdown' => [
                    'content' => $message
                ]
            ];
            
            $postData = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            // 使用curl发送请求
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => "CURL错误: $error"];
            }
            
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP错误: $httpCode"];
            }
            
            $result = @json_decode($response, true);
            if ($result && isset($result['errcode']) && $result['errcode'] === 0) {
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => "企业微信返回错误: $response"];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 发送应用消息给指定用户
     */
    private function sendApplicationMessageToUsers($message, $userIds)
    {
        try {
            $userIdArray = array_filter(explode(',', $userIds));
            if (empty($userIdArray)) {
                return ['success' => false, 'error' => '用户ID列表为空'];
            }
            
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'error' => '获取访问令牌失败'];
            }
            
            $wecomConfig = $this->getWecomConfig();
            if (!$wecomConfig) {
                return ['success' => false, 'error' => '获取企业微信配置失败'];
            }
            
            $data = [
                'touser' => implode('|', $userIdArray),
                'msgtype' => 'markdown',
                'agentid' => $wecomConfig['agent_id'],
                'markdown' => [
                    'content' => $message
                ]
            ];
            
            $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $accessToken;
            $postData = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => "CURL错误: $error"];
            }
            
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP错误: $httpCode"];
            }
            
            $result = @json_decode($response, true);
            if ($result && isset($result['errcode']) && $result['errcode'] === 0) {
                $this->logInfo("应用消息发送给用户成功，用户数: " . count($userIdArray));
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => "企业微信返回错误: $response"];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 发送应用消息给指定部门
     */
    private function sendApplicationMessageToDepts($message, $deptIds)
    {
        try {
            $deptIdArray = array_filter(explode(',', $deptIds));
            if (empty($deptIdArray)) {
                return ['success' => false, 'error' => '部门ID列表为空'];
            }
            
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'error' => '获取访问令牌失败'];
            }
            
            $wecomConfig = $this->getWecomConfig();
            if (!$wecomConfig) {
                return ['success' => false, 'error' => '获取企业微信配置失败'];
            }
            
            $data = [
                'toparty' => implode('|', $deptIdArray),
                'msgtype' => 'markdown',
                'agentid' => $wecomConfig['agent_id'],
                'markdown' => [
                    'content' => $message
                ]
            ];
            
            $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $accessToken;
            $postData = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => "CURL错误: $error"];
            }
            
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP错误: $httpCode"];
            }
            
            $result = @json_decode($response, true);
            if ($result && isset($result['errcode']) && $result['errcode'] === 0) {
                $this->logInfo("应用消息发送给部门成功，部门数: " . count($deptIdArray));
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => "企业微信返回错误: $response"];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取企业微信访问令牌
     */
    private function getAccessToken()
    {
        try {
            $wecomConfig = $this->getWecomConfig();
            if (!$wecomConfig) {
                return false;
            }
            
            // 检查是否有有效的访问令牌
            if (!empty($wecomConfig['access_token']) && $wecomConfig['token_expires_at'] > time()) {
                return $wecomConfig['access_token'];
            }
            
            // 获取新的访问令牌
            $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$wecomConfig['corp_id']}&corpsecret={$wecomConfig['corp_secret']}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                $this->logError("获取访问令牌失败: HTTP $httpCode, $error");
                return false;
            }
            
            $result = @json_decode($response, true);
            if ($result && isset($result['errcode']) && $result['errcode'] === 0 && !empty($result['access_token'])) {
                // 更新数据库中的访问令牌
                $database = $this->getDatabase();
                if ($database) {
                    $database->update('wecom_config', [
                        'access_token' => $result['access_token'],
                        'token_expires_at' => time() + $result['expires_in'] - 300, // 提前5分钟过期
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['id' => 1]);
                }
                
                return $result['access_token'];
            } else {
                $this->logError("获取访问令牌失败: " . ($response ?: '无响应'));
                return false;
            }
            
        } catch (\Exception $e) {
            $this->logError("获取访问令牌异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取企业微信配置
     */
    private function getWecomConfig()
    {
        try {
            $database = $this->getDatabase();
            if (!$database) {
                return false;
            }
            
            return $database->get('wecom_config', '*', ['id' => 1]);
            
        } catch (\Exception $e) {
            $this->logError("获取企业微信配置失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录通知日志
     */
    private function recordNotificationLog($ruleId, $eventData, $message, $status, $response, $database)
    {
        try {
            $database->insert('wecom_notification_logs', [
                'rule_id' => $ruleId,
                'repo_name' => $eventData['repo_name'] ?? '',
                'event_type' => 'commit',
                'author' => $eventData['author'] ?? '',
                'message' => '',
                'files_changed' => '',
                'chat_id' => '',
                'notification_content' => $message,
                'send_status' => $status,
                'response_data' => is_string($response) ? $response : json_encode($response),
                'error_message' => $status === 'failed' ? $response : '',
                'retry_count' => 0,
                'sent_at' => $status === 'success' ? date('Y-m-d H:i:s') : '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('记录通知日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理通知失败
     */
    private function handleNotificationFailure($notification, $error, $database)
    {
        $queueId = $notification['id'] ?? $notification['queue_id'];
        $retryCount = ($notification['retry_count'] ?? 0) + 1;
        $maxRetries = $notification['max_retries'] ?? 3;
        
        if ($retryCount >= $maxRetries) {
            // 超过最大重试次数，标记为失败
            $database->update('wecom_notification_queue', [
                'status' => 'failed',
                'retry_count' => $retryCount,
                'last_error' => $error,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $queueId]);
            
            $this->logError("通知发送失败，已达最大重试次数，队列ID: {$queueId}");
        } else {
            // 重新标记为待处理，等待重试
            $database->update('wecom_notification_queue', [
                'status' => 'pending',
                'retry_count' => $retryCount,
                'last_error' => $error,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $queueId]);
            
            $this->logInfo("通知发送失败，将重试，队列ID: {$queueId}，重试次数: $retryCount");
        }
    }

    /**
     * 清理过期通知
     */
    private function cleanupExpiredNotifications()
    {
        try {
            $database = $this->getDatabase();
            if (!$database) {
                return;
            }
            
            // 清理7天前的已完成通知
            $result = $database->delete('wecom_notification_queue', [
                'status' => ['completed', 'failed'],
                'created_at[<]' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ]);
            
            $deletedCount = $result ? $result->rowCount() : 0;
            
            if ($deletedCount > 0) {
                $this->logInfo("清理了 $deletedCount 个过期通知记录");
            }
            
        } catch (\Exception $e) {
            $this->logError('清理过期通知失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取数据库连接
     */
    private function getDatabase()
    {
        try {
            $configDatabase = Config::get('database');
            if (array_key_exists('database_file', $configDatabase)) {
                $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $this->configSvn['home_path']);
            }
            
            return new Medoo($configDatabase);
        } catch (\Exception $e) {
            $this->logError('数据库连接失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 移动文件到processed目录
     */
    private function moveToProcessed($file, $processedDir, $prefix = '')
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $newPath = $processedDir . '/' . $prefix . $timestamp . '_' . basename($file);
            
            if (@rename($file, $newPath)) {
                $this->logInfo("文件已移动到: $newPath");
            } else {
                $this->logError("无法移动文件: $file");
                @unlink($file);
            }
        } catch (\Exception $e) {
            $this->logError("移动文件异常: " . $e->getMessage());
            @unlink($file);
        }
    }

    /**
     * 企业微信信息日志
     */
    private function logInfo($message)
    {
        $logFile = $this->configSvn['log_base_path'] . 'wecom_notification.log';
        $logMessage = '[' . date('Y-m-d H:i:s') . '] [INFO] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * 企业微信错误日志
     */
    private function logError($message)
    {
        $logFile = $this->configSvn['log_base_path'] . 'wecom_notification.log';
        $logMessage = '[' . date('Y-m-d H:i:s') . '] [ERROR] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
