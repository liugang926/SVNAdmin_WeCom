<?php
/**
 * 企业微信批量处理服务
 * 
 * 负责批量通知事件的处理、合并、过滤等功能
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service\wecom;

class WeComBatchProcessor
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
     * 通知配置
     * @var array
     */
    private $notificationConfig;
    
    /**
     * 规则服务
     * @var WeComRuleService
     */
    private $ruleService;
    
    /**
     * 消息服务
     * @var WeComMessageService
     */
    private $messageService;
    
    /**
     * 模板管理器
     * @var \app\util\wecom\WeComTemplateManager
     */
    private $templateManager;
    
    /**
     * 构造函数
     * 
     * @param object $database 数据库连接
     * @param object $logger 日志服务
     * @param array $notificationConfig 通知配置
     * @param WeComRuleService $ruleService 规则服务
     * @param WeComMessageService $messageService 消息服务
     * @param \app\util\wecom\WeComTemplateManager $templateManager 模板管理器
     */
    public function __construct($database, $logger, $notificationConfig, $ruleService, $messageService, $templateManager)
    {
        $this->database = $database;
        $this->logger = $logger;
        $this->notificationConfig = $notificationConfig;
        $this->ruleService = $ruleService;
        $this->messageService = $messageService;
        $this->templateManager = $templateManager;
    }
    
    /**
     * 批量处理通知事件
     *
     * @param array $events 事件列表
     * @return array 处理结果
     */
    public function processBatchNotifications($events)
    {
        $this->logInfo('开始批量处理通知事件', ['events_count' => count($events)]);
        
        $results = [];
        $processedCount = 0;
        $mergedCount = 0;
        
        try {
            // 按规则分组事件
            $groupedEvents = $this->groupEventsByRule($events);
            
            foreach ($groupedEvents as $ruleId => $ruleEvents) {
                try {
                    // 检查是否需要合并消息
                    if ($this->shouldMergeMessages($ruleEvents)) {
                        $result = $this->sendMergedNotification($ruleId, $ruleEvents);
                        $mergedCount++;
                    } else {
                        // 逐个发送通知
                        foreach ($ruleEvents as $event) {
                            $result = $this->messageService->sendSvnNotification($event['event_type'], $event['data']);
                            $results[] = $result;
                            $processedCount++;
                        }
                    }
                    
                } catch (\Exception $e) {
                    $this->logError('批量处理通知失败', "规则ID: {$ruleId}, 错误: " . $e->getMessage());
                }
            }
            
            $this->logInfo('批量通知处理完成', [
                'processed_count' => $processedCount,
                'merged_count' => $mergedCount
            ]);
            
            return [
                'status' => 1,
                'message' => '批量通知处理完成',
                'processed_count' => $processedCount,
                'merged_count' => $mergedCount,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            $this->logError('批量处理通知失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '批量处理失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 按规则分组事件
     *
     * @param array $events 事件列表
     * @return array 分组后的事件
     */
    private function groupEventsByRule($events)
    {
        $grouped = [];
        
        foreach ($events as $event) {
            $rules = $this->ruleService->getApplicableRules($event['event_type'], $event['data']);
            
            foreach ($rules as $rule) {
                $ruleId = $rule['id'];
                
                if (!isset($grouped[$ruleId])) {
                    $grouped[$ruleId] = [
                        'rule' => $rule,
                        'events' => []
                    ];
                }
                
                $grouped[$ruleId]['events'][] = $event;
            }
        }
        
        return $grouped;
    }
    
    /**
     * 判断是否应该合并消息
     *
     * @param array $ruleEvents 规则事件
     * @return bool 是否合并
     */
    private function shouldMergeMessages($ruleEvents)
    {
        if (!($this->notificationConfig['rate_limit']['merge_similar_messages'] ?? false)) {
            return false;
        }
        
        $events = $ruleEvents['events'];
        
        // 如果事件数量少于2个，不需要合并
        if (count($events) < 2) {
            return false;
        }
        
        // 检查事件是否在短时间内发生
        $timeWindow = 300; // 5分钟
        $firstEventTime = strtotime($events[0]['timestamp'] ?? 'now');
        $lastEventTime = strtotime($events[count($events) - 1]['timestamp'] ?? 'now');
        
        if (($lastEventTime - $firstEventTime) > $timeWindow) {
            return false;
        }
        
        // 检查事件类型是否相似
        $eventTypes = array_unique(array_column($events, 'event_type'));
        
        return count($eventTypes) <= 2; // 最多2种不同的事件类型才合并
    }
    
    /**
     * 发送合并通知
     *
     * @param int $ruleId 规则ID
     * @param array $ruleEvents 规则事件
     * @return array 发送结果
     */
    private function sendMergedNotification($ruleId, $ruleEvents)
    {
        $rule = $ruleEvents['rule'];
        $events = $ruleEvents['events'];
        
        // 构建合并消息
        $message = $this->templateManager->buildMergedMessage($events);
        
        // 发送消息
        $result = $this->messageService->sendGroupMarkdownMessage($rule['webhook_url'], $message);
        
        // 记录通知日志
        $this->messageService->recordNotificationLog($ruleId, 'merged', 'multiple', $message, $result);
        
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
     * 智能过滤事件
     *
     * @param array $events 事件列表
     * @param array $filters 过滤条件
     * @return array 过滤后的事件
     */
    public function filterEvents($events, $filters = [])
    {
        $filteredEvents = [];
        
        foreach ($events as $event) {
            if ($this->shouldProcessEvent($event, $filters)) {
                $filteredEvents[] = $event;
            }
        }
        
        $this->logInfo('事件过滤完成', [
            'original_count' => count($events),
            'filtered_count' => count($filteredEvents)
        ]);
        
        return $filteredEvents;
    }
    
    /**
     * 判断是否应该处理事件
     *
     * @param array $event 事件
     * @param array $filters 过滤条件
     * @return bool 是否处理
     */
    private function shouldProcessEvent($event, $filters)
    {
        // 检查事件类型过滤
        if (!empty($filters['event_types'])) {
            if (!in_array($event['event_type'], $filters['event_types'])) {
                return false;
            }
        }
        
        // 检查仓库过滤
        if (!empty($filters['repositories'])) {
            $repoName = $event['data']['repo_name'] ?? '';
            if (!in_array($repoName, $filters['repositories'])) {
                return false;
            }
        }
        
        // 检查用户过滤
        if (!empty($filters['authors'])) {
            $author = $event['data']['author'] ?? '';
            if (!in_array($author, $filters['authors'])) {
                return false;
            }
        }
        
        // 检查路径过滤
        if (!empty($filters['path_patterns'])) {
            $path = $event['data']['path'] ?? '/';
            $matched = false;
            
            foreach ($filters['path_patterns'] as $pattern) {
                if (fnmatch($pattern, $path)) {
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                return false;
            }
        }
        
        // 检查时间过滤
        if (!empty($filters['time_range'])) {
            $eventTime = strtotime($event['timestamp'] ?? 'now');
            $startTime = strtotime($filters['time_range']['start'] ?? '1970-01-01');
            $endTime = strtotime($filters['time_range']['end'] ?? '2099-12-31');
            
            if ($eventTime < $startTime || $eventTime > $endTime) {
                return false;
            }
        }
        
        return true;
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
            $this->logger->writeLog('info', '[WeComBatchProcessor] ' . $message, $context);
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
            $this->logger->writeLog('error', '[WeComBatchProcessor] ' . $message, ['error' => $error]);
        }
    }
}
?>
