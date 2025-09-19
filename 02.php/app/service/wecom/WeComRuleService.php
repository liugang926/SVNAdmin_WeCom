<?php
/**
 * 企业微信通知规则服务
 * 
 * 负责通知规则的创建、更新、删除、查询等管理功能
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service\wecom;

class WeComRuleService
{
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
     * 企业微信配置
     * @var array
     */
    private $wecomConfig;
    
    /**
     * 通知配置
     * @var array
     */
    private $notificationConfig;
    
    /**
     * 钩子服务
     * @var WeComHookService
     */
    private $hookService;
    
    /**
     * 构造函数
     * 
     * @param object $database 数据库连接
     * @param object $logger 日志服务
     * @param array $wecomConfig 企业微信配置
     * @param WeComHookService $hookService 钩子服务
     */
    public function __construct($database, $logger, $wecomConfig, $hookService = null)
    {
        $this->database = $database;
        $this->logger = $logger;
        $this->wecomConfig = $wecomConfig;
        $this->notificationConfig = $wecomConfig['notification'] ?? [];
        $this->hookService = $hookService;
    }
    
    /**
     * 创建通知规则
     *
     * @param array $ruleData 规则数据
     * @return array 创建结果
     */
    public function createNotificationRule($ruleData)
    {
        try {
            // 验证必要字段
            $requiredFields = ['repo_name', 'event_type'];
            foreach ($requiredFields as $field) {
                if (empty($ruleData[$field])) {
                    throw new \Exception("缺少必要字段: {$field}");
                }
            }
            
            // webhook_url 兼容为空：使用默认配置
            if (empty($ruleData['webhook_url'])) {
                $defaultWebhook = $this->notificationConfig['default_webhook_url'] ?? '';
                if (!empty($defaultWebhook)) {
                    $ruleData['webhook_url'] = $defaultWebhook;
                }
                // 如果没有默认配置也没有填写webhook_url，允许为空
                // 但需要确保至少配置了用户ID或部门ID作为通知目标
                if (empty($ruleData['webhook_url']) && 
                    empty($ruleData['notify_wecom_userids']) && 
                    empty($ruleData['notify_wecom_deptids'])) {
                    throw new \Exception('请至少配置一个通知目标：Webhook URL、企业微信用户或部门');
                }
            }
            
            // 设置默认值
            $ruleData['path_prefix'] = $ruleData['path_prefix'] ?? '/';
            $ruleData['message_template'] = $ruleData['message_template'] ?? '';
            $ruleData['enable'] = $ruleData['enable'] ?? 1;
            $ruleData['created_at'] = date('Y-m-d H:i:s');
            $ruleData['updated_at'] = date('Y-m-d H:i:s');
            
            // 插入规则
            $this->database->insert('wecom_notification_rules', $ruleData);
            $ruleId = $this->database->id();
            
            // 自动启用通知功能（如果尚未启用）
            $this->ensureNotificationEnabled();
            
            // 自动安装钩子到指定仓库
            $hookInstallResult = ['status' => 1, 'message' => '钩子服务未初始化'];
            if ($this->hookService) {
                $hookInstallResult = $this->hookService->installHookForRepository($ruleData['repo_name']);
            }
            
            $this->logInfo('创建通知规则成功', [
                'rule_id' => $ruleId,
                'repo_name' => $ruleData['repo_name'],
                'event_type' => $ruleData['event_type'],
                'hook_installed' => $hookInstallResult['status'] === 1
            ]);
            
            return [
                'status' => 1,
                'message' => '通知规则创建成功' . ($hookInstallResult['status'] === 1 ? '，钩子已自动安装' : '，钩子安装失败: ' . $hookInstallResult['message']),
                'rule_id' => $ruleId,
                'hook_install_result' => $hookInstallResult
            ];
            
        } catch (\Exception $e) {
            $this->logError('创建通知规则失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '创建通知规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 更新通知规则
     *
     * @param int $ruleId 规则ID
     * @param array $ruleData 规则数据
     * @return array 更新结果
     */
    public function updateNotificationRule($ruleId, $ruleData)
    {
        try {
            // 获取原规则信息
            $oldRule = $this->getNotificationRuleById($ruleId);
            if (!$oldRule) {
                throw new \Exception("通知规则不存在: ID {$ruleId}");
            }
            
            $ruleData['updated_at'] = date('Y-m-d H:i:s');
            
            // 更新规则
            $this->database->update('wecom_notification_rules', $ruleData, [
                'id' => $ruleId
            ]);
            
            // 处理钩子变化
            $hookResult = ['status' => 1, 'message' => '钩子服务未初始化', 'changes' => []];
            if ($this->hookService) {
                $hookResult = $this->handleHookChangesOnUpdate($oldRule, $ruleData);
            }
            
            $this->logInfo('更新通知规则成功', [
                'rule_id' => $ruleId,
                'old_repo' => $oldRule['repo_name'],
                'new_repo' => $ruleData['repo_name'] ?? $oldRule['repo_name'],
                'hook_changes' => $hookResult
            ]);
            
            return [
                'status' => 1,
                'message' => '通知规则更新成功' . ($hookResult['message'] ? '，' . $hookResult['message'] : ''),
                'hook_changes' => $hookResult
            ];
            
        } catch (\Exception $e) {
            $this->logError('更新通知规则失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '更新通知规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 删除通知规则
     *
     * @param int $ruleId 规则ID
     * @return array 删除结果
     */
    public function deleteNotificationRule($ruleId)
    {
        try {
            // 获取要删除的规则信息
            $rule = $this->getNotificationRuleById($ruleId);
            if (!$rule) {
                throw new \Exception("通知规则不存在: ID {$ruleId}");
            }
            
            // 删除规则
            $this->database->delete('wecom_notification_rules', [
                'id' => $ruleId
            ]);
            
            // 检查是否需要清理钩子
            $cleanupResult = ['status' => 1, 'message' => '钩子服务未初始化'];
            if ($this->hookService) {
                $cleanupResult = $this->hookService->cleanupOrphanedHooks($rule['repo_name']);
            }
            
            $this->logInfo('删除通知规则成功', [
                'rule_id' => $ruleId,
                'repo_name' => $rule['repo_name'],
                'hook_cleanup' => $cleanupResult
            ]);
            
            return [
                'status' => 1,
                'message' => '通知规则删除成功' . ($cleanupResult['message'] ? '，' . $cleanupResult['message'] : ''),
                'hook_cleanup' => $cleanupResult
            ];
            
        } catch (\Exception $e) {
            $this->logError('删除通知规则失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '删除通知规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取通知规则列表
     *
     * @param array $filters 过滤条件
     * @return array 规则列表
     */
    public function getNotificationRules($filters = [])
    {
        try {
            $conditions = [];
            
            if (!empty($filters['repo_name'])) {
                $conditions['repo_name'] = $filters['repo_name'];
            }
            
            if (!empty($filters['event_type'])) {
                // 支持包含查询：检查事件类型字段是否包含指定的事件类型
                $conditions['event_type[~]'] = $filters['event_type'];
            }
            
            if (isset($filters['enable'])) {
                $conditions['enable'] = $filters['enable'];
            }
            
            $rules = $this->database->select('wecom_notification_rules', '*', $conditions);
            
            // 转换数据类型，确保前端能正确识别
            foreach ($rules as &$rule) {
                $rule['id'] = (int)$rule['id'];
                $rule['enable'] = (int)$rule['enable'];
            }
            
            return [
                'status' => 1,
                'message' => '获取通知规则成功',
                'data' => $rules
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取通知规则失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取通知规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 根据ID获取单个通知规则
     *
     * @param int $ruleId 规则ID
     * @return array|null 规则数据
     */
    public function getNotificationRuleById($ruleId)
    {
        try {
            $rule = $this->database->get('wecom_notification_rules', '*', [
                'id' => $ruleId
            ]);
            
            return $rule ?: null;
            
        } catch (\Exception $e) {
            $this->logError('获取通知规则失败', $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取适用的通知规则
     *
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 适用的规则列表
     */
    public function getApplicableRules($eventType, $eventData)
    {
        try {
            $repoName = $eventData['repo_name'] ?? '';
            $path = $eventData['path'] ?? '/';
            
            // 查询匹配的通知规则
            $rules = $this->database->select('wecom_notification_rules', '*', [
                'AND' => [
                    'enable' => 1,
                    'OR' => [
                        'repo_name' => $repoName,
                        'repo_name' => '*' // 通配符规则
                    ]
                ]
            ]);
            
            // 进一步过滤事件类型匹配的规则
            $eventMatchedRules = [];
            foreach ($rules as $rule) {
                $ruleEvents = explode(',', $rule['event_type']);
                $ruleEvents = array_map('trim', $ruleEvents);
                if (in_array($eventType, $ruleEvents)) {
                    $eventMatchedRules[] = $rule;
                }
            }
            $rules = $eventMatchedRules;
            
            // 进一步过滤路径匹配的规则
            $applicableRules = [];
            foreach ($rules as $rule) {
                if ($this->isPathMatched($path, $rule['path_prefix'])) {
                    $applicableRules[] = $rule;
                }
            }
            
            return $applicableRules;
            
        } catch (\Exception $e) {
            $this->logError('获取通知规则失败', $e->getMessage());
            return [];
        }
    }
    
    /**
     * 检查路径是否匹配
     *
     * @param string $path 文件路径
     * @param string $pathPrefix 路径前缀
     * @return bool 是否匹配
     */
    public function isPathMatched($path, $pathPrefix)
    {
        if ($pathPrefix === '/' || empty($pathPrefix)) {
            return true;
        }
        
        return strpos($path, $pathPrefix) === 0;
    }
    
    /**
     * 检查是否有可用的通知目标
     * 
     * @return bool 是否有通知目标
     */
    public function hasNotificationTargets()
    {
        try {
            // 检查是否有启用的通知规则，且配置了通知目标
            $rulesWithTargets = $this->database->count('wecom_notification_rules', [
                'AND' => [
                    'enable' => 1,
                    'OR' => [
                        'webhook_url[!]' => '',
                        'notify_wecom_userids[!]' => '',
                        'notify_wecom_deptids[!]' => ''
                    ]
                ]
            ]);
            
            return $rulesWithTargets > 0;
            
        } catch (\Exception $e) {
            // 如果检查失败，默认允许（向后兼容）
            return true;
        }
    }
    
    /**
     * 确保通知功能已启用
     * 
     * @return void
     */
    public function ensureNotificationEnabled()
    {
        try {
            // 检查当前通知功能状态
            $config = $this->database->get('wecom_config', 'notification_enabled', ['id' => 1]);
            
            if ($config != 1) {
                // 启用通知功能
                $this->database->update('wecom_config', [
                    'notification_enabled' => 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => 1]);
                
                $this->logInfo('自动启用企业微信通知功能', [
                    'reason' => '创建通知规则时自动启用',
                    'previous_status' => $config
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('启用通知功能失败', $e->getMessage());
        }
    }
    
    /**
     * 处理更新时的钩子变化
     *
     * @param array $oldRule 旧规则
     * @param array $newRuleData 新规则数据
     * @return array 处理结果
     */
    private function handleHookChangesOnUpdate($oldRule, $newRuleData)
    {
        try {
            $messages = [];
            
            // 检查仓库名是否变更
            $oldRepo = $oldRule['repo_name'];
            $newRepo = $newRuleData['repo_name'] ?? $oldRepo;
            
            // 检查启用状态是否变更
            $oldEnabled = (bool)$oldRule['enable'];
            $newEnabled = isset($newRuleData['enable']) ? (bool)$newRuleData['enable'] : $oldEnabled;
            
            // 如果仓库名变更
            if ($oldRepo !== $newRepo) {
                // 为新仓库安装钩子
                if ($newEnabled) {
                    $installResult = $this->hookService->installHookForRepository($newRepo);
                    if ($installResult['status'] === 1) {
                        $messages[] = "已为新仓库 {$newRepo} 安装钩子";
                    } else {
                        $messages[] = "新仓库 {$newRepo} 钩子安装失败: " . $installResult['message'];
                    }
                }
                
                // 检查旧仓库是否需要清理钩子
                $cleanupResult = $this->hookService->cleanupOrphanedHooks($oldRepo);
                if ($cleanupResult['cleaned']) {
                    $messages[] = "已清理旧仓库 {$oldRepo} 的孤儿钩子";
                }
            }
            // 如果仓库名未变更，但启用状态变更
            elseif ($oldEnabled !== $newEnabled) {
                if ($newEnabled) {
                    // 启用规则，确保钩子存在
                    $installResult = $this->hookService->installHookForRepository($newRepo);
                    if ($installResult['status'] === 1 && !empty($installResult['installed_repos'])) {
                        $messages[] = "规则启用，已安装钩子";
                    }
                } else {
                    // 禁用规则，检查是否需要清理钩子
                    $cleanupResult = $this->hookService->cleanupOrphanedHooks($newRepo);
                    if ($cleanupResult['cleaned']) {
                        $messages[] = "规则禁用，已清理无用钩子";
                    }
                }
            }
            
            return [
                'status' => 1,
                'message' => implode('；', $messages),
                'changes' => $messages
            ];
            
        } catch (\Exception $e) {
            $this->logError('处理钩子变化失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '钩子处理失败: ' . $e->getMessage(),
                'changes' => []
            ];
        }
    }
    
    /**
     * 检查仓库是否还有启用的规则
     *
     * @param string $repoName 仓库名
     * @return bool 是否有启用的规则
     */
    public function hasActiveRulesForRepo($repoName)
    {
        try {
            // 检查具体仓库的启用规则
            $specificRules = $this->database->count('wecom_notification_rules', [
                'repo_name' => $repoName,
                'enable' => 1
            ]);
            
            // 检查通配符规则
            $wildcardRules = $this->database->count('wecom_notification_rules', [
                'repo_name' => '*',
                'enable' => 1
            ]);
            
            return ($specificRules > 0) || ($wildcardRules > 0);
            
        } catch (\Exception $e) {
            $this->logError('检查仓库规则失败', $e->getMessage());
            return true; // 出错时保守处理，不删除钩子
        }
    }
    
    /**
     * 批量启用/禁用规则
     *
     * @param array $ruleIds 规则ID数组
     * @param bool $enable 是否启用
     * @return array 操作结果
     */
    public function batchToggleRules($ruleIds, $enable)
    {
        try {
            if (empty($ruleIds)) {
                throw new \Exception('规则ID列表不能为空');
            }
            
            $affectedRows = $this->database->update('wecom_notification_rules', [
                'enable' => $enable ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'id' => $ruleIds
            ]);
            
            $this->logInfo('批量切换规则状态', [
                'rule_ids' => $ruleIds,
                'enable' => $enable,
                'affected_rows' => $affectedRows
            ]);
            
            return [
                'status' => 1,
                'message' => '批量操作成功',
                'affected_rows' => $affectedRows
            ];
            
        } catch (\Exception $e) {
            $this->logError('批量切换规则状态失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '批量操作失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取规则统计信息
     *
     * @return array 统计信息
     */
    public function getRuleStatistics()
    {
        try {
            $stats = [
                'total_rules' => $this->database->count('wecom_notification_rules'),
                'enabled_rules' => $this->database->count('wecom_notification_rules', ['enable' => 1]),
                'disabled_rules' => $this->database->count('wecom_notification_rules', ['enable' => 0]),
                'repos_with_rules' => 0,
                'event_type_distribution' => []
            ];
            
            // 统计有规则的仓库数量
            $reposWithRules = $this->database->query(
                "SELECT COUNT(DISTINCT repo_name) as count FROM wecom_notification_rules WHERE repo_name != '*'"
            )->fetch();
            $stats['repos_with_rules'] = $reposWithRules['count'] ?? 0;
            
            // 统计事件类型分布
            $eventTypes = $this->database->select('wecom_notification_rules', [
                'event_type',
                'enable'
            ]);
            
            $eventDistribution = [];
            foreach ($eventTypes as $rule) {
                $types = array_map('trim', explode(',', $rule['event_type']));
                foreach ($types as $type) {
                    if (!isset($eventDistribution[$type])) {
                        $eventDistribution[$type] = ['total' => 0, 'enabled' => 0];
                    }
                    $eventDistribution[$type]['total']++;
                    if ($rule['enable']) {
                        $eventDistribution[$type]['enabled']++;
                    }
                }
            }
            $stats['event_type_distribution'] = $eventDistribution;
            
            return [
                'status' => 1,
                'message' => '获取统计信息成功',
                'data' => $stats
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取规则统计失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取统计信息失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 记录信息日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    private function logInfo($message, $context = [])
    {
        if ($this->logger) {
            $this->logger->writeLog('info', '[WeComRuleService] ' . $message, $context);
        }
    }
    
    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param string $error 错误详情
     */
    private function logError($message, $error = '')
    {
        if ($this->logger) {
            $this->logger->writeLog('error', '[WeComRuleService] ' . $message, ['error' => $error]);
        }
    }
}
?>
