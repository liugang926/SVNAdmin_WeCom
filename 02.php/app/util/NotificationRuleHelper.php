<?php
/**
 * 通知规则辅助工具类
 * 
 * 提供通知规则创建后的后续处理功能，包括钩子测试通知等
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\util;

class NotificationRuleHelper
{
    private $ServiceLogs;
    
    /**
     * 构造函数
     */
    public function __construct($parm = [])
    {
        // 初始化日志服务
        require_once BASE_PATH . '/app/service/ServiceLogs.php';
        $this->ServiceLogs = new \app\service\ServiceLogs($parm);
    }
    
    /**
     * 处理通知规则创建后的操作
     * 
     * @param array $createResult WeComNotification::createNotificationRule 的返回结果
     * @return array 处理结果
     */
    public function handleRuleCreationPostProcess($createResult)
    {
        try {
            $this->logInfo('开始处理通知规则创建后操作', [
                'create_result' => $createResult
            ]);
            
            // 检查规则创建是否成功
            if ($createResult['status'] !== 1) {
                return [
                    'status' => 0,
                    'message' => '规则创建失败，跳过后续处理',
                    'original_result' => $createResult
                ];
            }
            
            $ruleId = $createResult['rule_id'] ?? null;
            if (!$ruleId) {
                return [
                    'status' => 0,
                    'message' => '无法获取规则ID，跳过后续处理',
                    'original_result' => $createResult
                ];
            }
            
            // 获取钩子安装结果
            $hookInstallResult = $createResult['hook_install_result'] ?? [];
            
            // 发送测试通知
            $testResult = $this->sendRuleCreationTestNotification($ruleId, $hookInstallResult);
            
            // 构建完整的返回结果
            $result = [
                'status' => 1,
                'message' => $this->buildCompleteMessage($createResult, $testResult),
                'rule_creation' => $createResult,
                'test_notification' => $testResult,
                'summary' => $this->buildSummary($createResult, $testResult)
            ];
            
            $this->logInfo('通知规则创建后操作完成', [
                'rule_id' => $ruleId,
                'test_success' => $testResult['status'] === 1
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('处理通知规则创建后操作失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '后续处理失败: ' . $e->getMessage(),
                'original_result' => $createResult,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 发送规则创建测试通知
     */
    private function sendRuleCreationTestNotification($ruleId, $hookInstallResult)
    {
        try {
            require_once BASE_PATH . '/app/util/HookTestNotifier.php';
            $testNotifier = new HookTestNotifier();
            
            return $testNotifier->sendRuleCreationTestNotification($ruleId, $hookInstallResult);
            
        } catch (\Exception $e) {
            $this->logError('发送测试通知失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '测试通知发送失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 构建完整的消息
     */
    private function buildCompleteMessage($createResult, $testResult)
    {
        $messages = [];
        
        // 规则创建消息
        $messages[] = $createResult['message'];
        
        // 测试通知消息
        if ($testResult['status'] === 1) {
            $testDetails = $testResult['test_result'] ?? [];
            if ($testDetails['success'] ?? false) {
                $sentCount = $testDetails['sent_count'] ?? 0;
                $messages[] = "✅ 测试通知发送成功 ({$sentCount} 条消息)";
            } else {
                $messages[] = "⚠️ 测试通知发送失败: " . ($testDetails['message'] ?? '未知错误');
            }
        } else {
            $messages[] = "❌ 测试通知处理失败: " . $testResult['message'];
        }
        
        return implode('；', $messages);
    }
    
    /**
     * 构建操作摘要
     */
    private function buildSummary($createResult, $testResult)
    {
        $summary = [
            'rule_created' => $createResult['status'] === 1,
            'hook_installed' => false,
            'test_notification_sent' => false,
            'test_notification_success' => false,
            'notifications_sent_count' => 0
        ];
        
        // 钩子安装状态
        $hookResult = $createResult['hook_install_result'] ?? [];
        $summary['hook_installed'] = ($hookResult['status'] ?? 0) === 1;
        
        // 测试通知状态
        if ($testResult['status'] === 1) {
            $summary['test_notification_sent'] = true;
            $testDetails = $testResult['test_result'] ?? [];
            $summary['test_notification_success'] = $testDetails['success'] ?? false;
            $summary['notifications_sent_count'] = $testDetails['sent_count'] ?? 0;
        }
        
        return $summary;
    }
    
    /**
     * 获取规则创建状态报告
     */
    public function getRuleCreationStatusReport($ruleId)
    {
        try {
            require_once BASE_PATH . '/app/util/HookTestNotifier.php';
            $testNotifier = new HookTestNotifier();
            
            // 获取测试通知历史
            $testHistory = $testNotifier->getTestNotificationHistory($ruleId, 5);
            
            return [
                'status' => 1,
                'rule_id' => $ruleId,
                'test_history' => $testHistory,
                'last_test' => !empty($testHistory) ? $testHistory[0] : null
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '获取状态报告失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 手动触发规则测试
     */
    public function triggerManualRuleTest($ruleId)
    {
        try {
            require_once BASE_PATH . '/app/util/HookTestNotifier.php';
            $testNotifier = new HookTestNotifier();
            
            return $testNotifier->triggerManualTest($ruleId);
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '手动测试失败: ' . $e->getMessage()
            ];
        }
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
