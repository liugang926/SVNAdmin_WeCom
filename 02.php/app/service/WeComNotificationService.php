<?php
/**
 * 企业微信通知服务主入口
 * 
 * 统一管理所有企业微信通知相关功能的入口类
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service;

require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/util/wecom/WeComTemplateManager.php';
require_once BASE_PATH . '/app/util/wecom/WeComUserMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComMessageService.php';
require_once BASE_PATH . '/app/service/wecom/WeComRuleService.php';
require_once BASE_PATH . '/app/service/wecom/WeComHookService.php';
require_once BASE_PATH . '/app/service/wecom/WeComStatsService.php';
require_once BASE_PATH . '/app/service/wecom/WeComBatchProcessor.php';

use app\service\Logs as ServiceLogs;
use app\service\WeComAPI as ServiceWeComAPI;

class WeComNotificationService extends Base
{
    /**
     * 日志服务
     * @var ServiceLogs
     */
    private $ServiceLogs;
    
    /**
     * 企业微信API服务
     * @var ServiceWeComAPI
     */
    private $ServiceWeComAPI;
    
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
     * 消息服务
     * @var \app\service\wecom\WeComMessageService
     */
    private $messageService;
    
    /**
     * 规则服务
     * @var \app\service\wecom\WeComRuleService
     */
    private $ruleService;
    
    /**
     * 钩子服务
     * @var \app\service\wecom\WeComHookService
     */
    private $hookService;
    
    /**
     * 构造函数
     * 
     * @param array $parm 参数
     */
    function __construct($parm = [])
    {
        parent::__construct($parm);
        
        $this->ServiceLogs = new ServiceLogs($parm);
        
        // 加载企业微信配置
        require_once BASE_PATH . '/app/service/WeComConfig.php';
        $this->wecomConfig = \app\service\WeComConfig::getConfig();
        $this->notificationConfig = $this->wecomConfig['notification'] ?? [];
        
        // 检查企业微信基本配置是否启用
        $enabled = $this->wecomConfig['enabled'] ?? false;
        $isHookCall = isset($parm['hook_call']) && $parm['hook_call'] === true;
        
        if (!$enabled) {
            if ($isHookCall) {
                // 在钩子环境中，如果配置未启用，静默跳过而不抛出异常
                $this->logInfo('企业微信集成功能未启用，跳过通知发送');
                // 注意：不要直接return，需要继续初始化服务以保证兼容性
            } else {
                // 在Web界面中，提供详细的错误信息
                $errorDetails = $this->getEnableErrorDetails();
                throw new \Exception('企业微信集成功能未启用: ' . $errorDetails);
            }
        }
        
        // 初始化 WeComAPI（只有在启用时才初始化）
        if ($enabled) {
            try {
                $this->ServiceWeComAPI = new ServiceWeComAPI($parm);
            } catch (\Exception $e) {
                if ($isHookCall) {
                    // 钩子调用时，API初始化失败也不抛出异常
                    $this->logError('WeComAPI初始化失败，跳过通知发送', $e->getMessage());
                    $this->ServiceWeComAPI = null;
                } else {
                    // Web界面调用时，重新抛出异常
                    throw $e;
                }
            }
        } else {
            $this->ServiceWeComAPI = null;
        }
        
        // 初始化工具类和服务
        $this->initializeServices();
    }
    
    /**
     * 初始化所有服务
     */
    private function initializeServices()
    {
        // 初始化工具类
        $messageTemplates = $this->wecomConfig['message_templates'] ?? [];
        $this->templateManager = new \app\util\wecom\WeComTemplateManager($messageTemplates);
        $this->userMapper = new \app\util\wecom\WeComUserMapper($this->database, $this->ServiceLogs);
        
        // 初始化核心服务
        $this->messageService = new \app\service\wecom\WeComMessageService(
            $this->ServiceWeComAPI,
            $this->database,
            $this->ServiceLogs,
            $this->wecomConfig
        );
        
        $this->hookService = new \app\service\wecom\WeComHookService(
            $this->database,
            $this->ServiceLogs,
            $this->svnConfig ?? [],
            $this->templateManager
        );
        
        $this->ruleService = new \app\service\wecom\WeComRuleService(
            $this->database,
            $this->ServiceLogs,
            $this->wecomConfig,
            $this->hookService
        );
        
        // 初始化统计服务
        $this->statsService = new \app\service\wecom\WeComStatsService(
            $this->database,
            $this->ServiceLogs
        );
        
        // 初始化批处理服务
        $this->batchProcessor = new \app\service\wecom\WeComBatchProcessor(
            $this->database,
            $this->ServiceLogs,
            $this->notificationConfig,
            $this->ruleService,
            $this->messageService,
            $this->templateManager
        );
        
        // 设置服务间的依赖关系（避免循环依赖）
        $this->hookService->setRuleService($this->ruleService);
    }
    
    // ==================== 主要公共API方法 ====================
    
    /**
     * 发送 SVN 操作通知
     *
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 发送结果
     */
    public function sendSvnNotification($eventType, $eventData)
    {
        // 转换SVN用户名为真实姓名
        if (isset($eventData['author'])) {
            $eventData['author'] = $this->userMapper->convertSvnUsernameToRealName($eventData['author']);
        }
        
        $this->logInfo('开始发送SVN操作通知', [
            'event_type' => $eventType,
            'repo_name' => $eventData['repo_name'] ?? '',
            'author' => $eventData['author'] ?? ''
        ]);
        
        try {
            // 检查企业微信服务是否可用
            if (!$this->ServiceWeComAPI) {
                $this->logInfo('企业微信服务不可用，跳过通知发送');
                return [
                    'status' => 1,
                    'message' => '企业微信服务不可用',
                    'sent_count' => 0
                ];
            }
            
            // 检查是否有可用的通知目标
            if (!$this->ruleService->hasNotificationTargets()) {
                $this->logInfo('没有配置通知目标，跳过通知发送');
                return [
                    'status' => 1,
                    'message' => '没有配置通知目标',
                    'sent_count' => 0
                ];
            }
            
            // 获取适用的通知规则
            $rules = $this->ruleService->getApplicableRules($eventType, $eventData);
            
            if (empty($rules)) {
                $this->logInfo('没有找到适用的通知规则', [
                    'event_type' => $eventType,
                    'repo_name' => $eventData['repo_name'] ?? ''
                ]);
                return [
                    'status' => 1,
                    'message' => '没有适用的通知规则',
                    'sent_count' => 0
                ];
            }
            
            // 使用消息服务发送通知
            return $this->messageService->sendNotificationsByRules($rules, $eventType, $eventData);
            
        } catch (\Exception $e) {
            $this->logError('SVN操作通知发送失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => 'SVN操作通知发送失败: ' . $e->getMessage(),
                'sent_count' => 0
            ];
        }
    }
    
    /**
     * 发送同步状态通知
     *
     * @param string $syncType 同步类型
     * @param array $syncResult 同步结果
     * @return array 发送结果
     */
    public function sendSyncNotification($syncType, $syncResult)
    {
        $this->logInfo('开始发送同步状态通知', [
            'sync_type' => $syncType,
            'status' => $syncResult['status'] ?? 'unknown'
        ]);
        
        try {
            // 构建同步通知消息
            $message = $this->templateManager->buildSyncMessage($syncType, $syncResult);
            
            // 获取默认通知群
            $defaultChatId = $this->notificationConfig['default_webhook_url'] ?? '';
            
            if (empty($defaultChatId)) {
                $this->logInfo('未配置默认通知群，跳过同步通知');
                return [
                    'status' => 1,
                    'message' => '未配置默认通知群'
                ];
            }
            
            // 发送消息
            $result = $this->ServiceWeComAPI->sendGroupMarkdownMessage($defaultChatId, $message);
            
            // 记录通知日志
            $this->messageService->recordNotificationLog(null, 'sync', $syncType, $message, $result);
            
            $this->logInfo('同步状态通知发送完成');
            
            return [
                'status' => 1,
                'message' => '同步通知发送成功',
                'result' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logError('同步状态通知发送失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '同步通知发送失败: ' . $e->getMessage()
            ];
        }
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
            
            return $this->messageService->sendTestNotification($webhookUrl, $testMessage, $notifyUserIds, $notifyDeptIds);
            
        } catch (\Exception $e) {
            $this->logError('测试通知发送失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '测试通知发送失败: ' . $e->getMessage()
            ];
        }
    }
    
    // ==================== 规则管理API ====================
    
    /**
     * 创建通知规则
     *
     * @param array $ruleData 规则数据
     * @return array 创建结果
     */
    public function createNotificationRule($ruleData)
    {
        return $this->ruleService->createNotificationRule($ruleData);
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
        return $this->ruleService->updateNotificationRule($ruleId, $ruleData);
    }
    
    /**
     * 删除通知规则
     *
     * @param int $ruleId 规则ID
     * @return array 删除结果
     */
    public function deleteNotificationRule($ruleId)
    {
        return $this->ruleService->deleteNotificationRule($ruleId);
    }
    
    /**
     * 获取通知规则列表
     *
     * @param array $filters 过滤条件
     * @return array 规则列表
     */
    public function getNotificationRules($filters = [])
    {
        return $this->ruleService->getNotificationRules($filters);
    }
    
    // ==================== 统计和管理API ====================
    
    /**
     * 获取通知统计信息
     *
     * @param array $filters 过滤条件
     * @return array 统计信息
     */
    public function getNotificationStats($filters = [])
    {
        return $this->statsService->getNotificationStats($filters);
    }
    
    /**
     * 获取钩子状态报告
     *
     * @return array 状态报告
     */
    public function getHookStatusReport()
    {
        return $this->hookService->getHookStatusReport();
    }
    
    /**
     * 获取规则统计信息
     *
     * @return array 统计信息
     */
    public function getRuleStatistics()
    {
        return $this->ruleService->getRuleStatistics();
    }
    
    /**
     * 清理过期的通知日志
     *
     * @param int $daysToKeep 保留天数
     * @return array 清理结果
     */
    public function cleanupNotificationLogs($daysToKeep = 30)
    {
        return $this->messageService->cleanupNotificationLogs($daysToKeep);
    }
    
    // ==================== 工具方法 ====================
    
    /**
     * 获取启用失败的详细错误信息
     * 
     * @return string 错误详情
     */
    private function getEnableErrorDetails()
    {
        $details = [];
        
        // 检查基本配置
        if (empty($this->wecomConfig['corp_id'])) {
            $details[] = '缺少企业微信Corp ID';
        }
        if (empty($this->wecomConfig['corp_secret'])) {
            $details[] = '缺少企业微信Corp Secret';
        }
        if (empty($this->wecomConfig['agent_id'])) {
            $details[] = '缺少企业微信Agent ID';
        }
        
        // 检查通知目标
        if ($this->ruleService && !$this->ruleService->hasNotificationTargets()) {
            $details[] = '没有配置通知目标（Webhook URL、用户ID或部门ID）';
        }
        
        return empty($details) ? '配置检查失败' : implode(', ', $details);
    }
    
    /**
     * 获取服务实例（用于高级操作）
     * 
     * @param string $serviceName 服务名称
     * @return object|null 服务实例
     */
    public function getService($serviceName)
    {
        switch ($serviceName) {
            case 'message':
                return $this->messageService;
            case 'rule':
                return $this->ruleService;
            case 'hook':
                return $this->hookService;
            case 'template':
                return $this->templateManager;
            case 'user':
                return $this->userMapper;
            default:
                return null;
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
        if (isset($this->wecomConfig['notification_log']['enable']) && $this->wecomConfig['notification_log']['enable']) {
            $logMessage = '[WeComNotificationService] ' . $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom_notification');
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
        if (isset($this->wecomConfig['notification_log']['enable']) && $this->wecomConfig['notification_log']['enable']) {
            $logMessage = '[WeComNotificationService ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom_notification');
        }
    }
}
?>
