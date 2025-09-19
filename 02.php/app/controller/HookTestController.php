<?php
/**
 * 钩子测试控制器
 * 
 * 提供钩子测试通知相关的API接口
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\controller;

class HookTestController
{
    /**
     * 处理通知规则创建后的完整流程
     * 
     * 用法: POST /api/hook-test/handle-rule-creation
     * 参数: rule_data (通知规则数据)
     */
    public function handleRuleCreation()
    {
        try {
            // 获取POST数据
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!isset($data['rule_data'])) {
                throw new \Exception('缺少规则数据');
            }
            
            $ruleData = $data['rule_data'];
            
            // 1. 创建通知规则
            require_once BASE_PATH . '/app/service/WeComNotification.php';
            $wecomNotification = new \app\service\WeComNotification(['hook_call' => false]);
            
            $createResult = $wecomNotification->createNotificationRule($ruleData);
            
            if ($createResult['status'] !== 1) {
                return $this->jsonResponse([
                    'status' => 0,
                    'message' => '规则创建失败: ' . $createResult['message'],
                    'data' => $createResult
                ]);
            }
            
            // 2. 处理后续操作（包括测试通知）
            require_once BASE_PATH . '/app/util/NotificationRuleHelper.php';
            $ruleHelper = new \app\util\NotificationRuleHelper();
            
            $postProcessResult = $ruleHelper->handleRuleCreationPostProcess($createResult);
            
            return $this->jsonResponse([
                'status' => 1,
                'message' => '规则创建和测试完成',
                'data' => $postProcessResult
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 0,
                'message' => '操作失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 手动触发规则测试
     * 
     * 用法: POST /api/hook-test/trigger-test
     * 参数: rule_id
     */
    public function triggerTest()
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!isset($data['rule_id'])) {
                throw new \Exception('缺少规则ID');
            }
            
            $ruleId = $data['rule_id'];
            
            require_once BASE_PATH . '/app/util/NotificationRuleHelper.php';
            $ruleHelper = new \app\util\NotificationRuleHelper();
            
            $result = $ruleHelper->triggerManualRuleTest($ruleId);
            
            return $this->jsonResponse([
                'status' => $result['status'],
                'message' => $result['message'],
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 0,
                'message' => '测试失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取规则测试历史
     * 
     * 用法: GET /api/hook-test/test-history?rule_id=123&limit=10
     */
    public function getTestHistory()
    {
        try {
            $ruleId = $_GET['rule_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 10);
            
            require_once BASE_PATH . '/app/util/HookTestNotifier.php';
            $testNotifier = new \app\util\HookTestNotifier();
            
            $history = $testNotifier->getTestNotificationHistory($ruleId, $limit);
            
            return $this->jsonResponse([
                'status' => 1,
                'message' => '获取成功',
                'data' => [
                    'history' => $history,
                    'count' => count($history)
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 0,
                'message' => '获取失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取规则状态报告
     * 
     * 用法: GET /api/hook-test/status-report?rule_id=123
     */
    public function getStatusReport()
    {
        try {
            $ruleId = $_GET['rule_id'] ?? null;
            
            if (!$ruleId) {
                throw new \Exception('缺少规则ID');
            }
            
            require_once BASE_PATH . '/app/util/NotificationRuleHelper.php';
            $ruleHelper = new \app\util\NotificationRuleHelper();
            
            $report = $ruleHelper->getRuleCreationStatusReport($ruleId);
            
            return $this->jsonResponse([
                'status' => $report['status'],
                'message' => $report['status'] ? '获取成功' : $report['message'],
                'data' => $report
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 0,
                'message' => '获取失败: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 返回JSON响应
     */
    private function jsonResponse($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 简单的路由处理
if (isset($_GET['action'])) {
    $controller = new HookTestController();
    $action = $_GET['action'];
    
    switch ($action) {
        case 'handleRuleCreation':
            $controller->handleRuleCreation();
            break;
            
        case 'triggerTest':
            $controller->triggerTest();
            break;
            
        case 'getTestHistory':
            $controller->getTestHistory();
            break;
            
        case 'getStatusReport':
            $controller->getStatusReport();
            break;
            
        default:
            echo json_encode([
                'status' => 0,
                'message' => '未知的操作: ' . $action
            ]);
    }
}
?>
