<?php
/**
 * 企业微信通知服务（兼容版本）
 * 
 * 保持向后兼容的简化版本，内部委托给新的服务架构
 * 
 * @author SVNAdmin Team
 * @version 2.0 (Refactored)
 * @deprecated 建议使用 WeComNotificationService 替代
 */

namespace app\service;

require_once BASE_PATH . '/app/service/WeComNotificationService.php';

class WeComNotification
{
    /**
     * 新的服务实例
     * @var WeComNotificationService
     */
    private $newService;
    
    /**
     * 构造函数
     * 
     * @param array $parm 参数
     */
    function __construct($parm = [])
    {
        // 直接使用新的服务实现
        $this->newService = new \app\service\WeComNotificationService($parm);
    }
    
    // ==================== 向后兼容的公共API ====================
    
    /**
     * 发送 SVN 操作通知
     *
     * @param string $eventType 事件类型
     * @param array $eventData 事件数据
     * @return array 发送结果
     */
    public function sendSvnNotification($eventType, $eventData)
    {
        return $this->newService->sendSvnNotification($eventType, $eventData);
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
        return $this->newService->sendSyncNotification($syncType, $syncResult);
    }
    
    /**
     * 创建通知规则
     *
     * @param array $ruleData 规则数据
     * @return array 创建结果
     */
    public function createNotificationRule($ruleData)
    {
        return $this->newService->createNotificationRule($ruleData);
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
        return $this->newService->updateNotificationRule($ruleId, $ruleData);
    }
    
    /**
     * 删除通知规则
     *
     * @param int $ruleId 规则ID
     * @return array 删除结果
     */
    public function deleteNotificationRule($ruleId)
    {
        return $this->newService->deleteNotificationRule($ruleId);
    }
    
    /**
     * 获取通知规则列表
     *
     * @param array $filters 过滤条件
     * @return array 规则列表
     */
    public function getNotificationRules($filters = [])
    {
        return $this->newService->getNotificationRules($filters);
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
        return $this->newService->testNotification($webhookUrl, $message, $notifyUserIds, $notifyDeptIds);
    }
    
    /**
     * 获取通知统计信息
     *
     * @param array $filters 过滤条件
     * @return array 统计信息
     */
    public function getNotificationStats($filters = [])
    {
        return $this->newService->getNotificationStats($filters);
    }
    
    /**
     * 清理过期的通知日志
     *
     * @param int $daysToKeep 保留天数
     * @return array 清理结果
     */
    public function cleanupNotificationLogs($daysToKeep = 30)
    {
        return $this->newService->cleanupNotificationLogs($daysToKeep);
    }
    
    // ==================== 高级功能（委托给新服务） ====================
    
    /**
     * 批量处理通知事件
     *
     * @param array $events 事件列表
     * @return array 处理结果
     */
    public function processBatchNotifications($events)
    {
        $batchProcessor = $this->newService->getService('batch');
        if ($batchProcessor) {
            return $batchProcessor->processBatchNotifications($events);
        }
        
        return [
            'status' => 0,
            'message' => '批量处理服务不可用'
        ];
    }
    
    /**
     * 智能过滤事件
     *
     * @param array $events 事件列表
     * @param array $filters 过滤条件
     * @return array 过滤后的事件
     */
    public function filterEvents($events, $filters = [])
    {
        $batchProcessor = $this->newService->getService('batch');
        if ($batchProcessor) {
            return $batchProcessor->filterEvents($events, $filters);
        }
        
        return $events; // 如果服务不可用，返回原始事件
    }
    
    // ==================== 兼容方法（通过适配器访问工具类） ====================
    
    /**
     * 获取模板管理器
     * 
     * @return \app\util\wecom\WeComTemplateManager
     */
    public function getTemplateManager()
    {
        return $this->newService->getService('template');
    }
    
    /**
     * 获取用户映射器
     * 
     * @return \app\util\wecom\WeComUserMapper
     */
    public function getUserMapper()
    {
        return $this->newService->getService('user');
    }
    
    /**
     * 获取默认模板（兼容方法）
     */
    public function getDefaultTemplate($eventType)
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->getDefaultTemplate($eventType) : '';
    }
    
    /**
     * 获取通用模板（兼容方法）
     */
    public function getGenericTemplate()
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->getGenericTemplate() : '';
    }
    
    /**
     * 准备模板变量（兼容方法）
     */
    public function prepareTemplateVariables($eventType, $eventData)
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->prepareTemplateVariables($eventType, $eventData) : [];
    }
    
    /**
     * 替换模板变量（兼容方法）
     */
    public function replaceTemplateVariables($template, $variables)
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->replaceTemplateVariables($template, $variables) : $template;
    }
    
    /**
     * 解析文件列表（兼容方法）
     */
    public function parseFileList($filesString)
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->parseFileList($filesString) : [];
    }
    
    /**
     * 格式化文件列表（兼容方法）
     */
    public function formatFileList($files)
    {
        $templateManager = $this->getTemplateManager();
        return $templateManager ? $templateManager->formatFileList($files) : '无';
    }
    
    /**
     * 转换SVN用户名为真实姓名（兼容方法）
     */
    public function convertSvnUsernameToRealName($svnUsername)
    {
        $userMapper = $this->getUserMapper();
        return $userMapper ? $userMapper->convertSvnUsernameToRealName($svnUsername) : $svnUsername;
    }
    
    /**
     * 获取用户信息（兼容方法）
     */
    public function getUserInfo($userId)
    {
        $userMapper = $this->getUserMapper();
        return $userMapper ? $userMapper->getUserInfo($userId) : ['userid' => $userId, 'name' => '未知用户'];
    }
    
    /**
     * 获取部门信息（兼容方法）
     */
    public function getDepartmentInfo($deptId)
    {
        $userMapper = $this->getUserMapper();
        return $userMapper ? $userMapper->getDepartmentInfo($deptId) : ['id' => $deptId, 'name' => '未知部门'];
    }
}
?>
