<?php
/**
 * 企业微信统计服务
 * 
 * 负责通知统计、数据分析、日志清理等功能
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service\wecom;

class WeComStatsService
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
     * 构造函数
     * 
     * @param object $database 数据库连接
     * @param object $logger 日志服务
     */
    public function __construct($database, $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }
    
    /**
     * 获取通知统计信息
     *
     * @param array $filters 过滤条件
     * @return array 统计信息
     */
    public function getNotificationStats($filters = [])
    {
        try {
            $conditions = [];
            
            // 构建查询条件
            if (!empty($filters['start_date'])) {
                $conditions['created_at[>=]'] = $filters['start_date'] . ' 00:00:00';
            }
            
            if (!empty($filters['end_date'])) {
                $conditions['created_at[<=]'] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($filters['event_type'])) {
                $conditions['event_type'] = $filters['event_type'];
            }
            
            if (!empty($filters['repo_name'])) {
                $conditions['repo_name'] = $filters['repo_name'];
            }
            
            // 获取总数统计
            $totalCount = $this->database->count('wecom_notification_logs', $conditions);
            
            // 获取成功发送统计
            $successConditions = array_merge($conditions, ['send_status' => 'success']);
            $successCount = $this->database->count('wecom_notification_logs', $successConditions);
            
            // 获取失败发送统计
            $failedConditions = array_merge($conditions, ['send_status' => 'failed']);
            $failedCount = $this->database->count('wecom_notification_logs', $failedConditions);
            
            // 获取今日发送统计
            $today = date('Y-m-d');
            $todayConditions = [
                'created_at[>=]' => $today . ' 00:00:00',
                'created_at[<=]' => $today . ' 23:59:59'
            ];
            $todaySent = $this->database->count('wecom_notification_logs', $todayConditions);
            
            // 获取总规则数
            $totalRules = $this->database->count('wecom_notification_rules', ['enable' => 1]);
            
            // 按事件类型统计
            $eventTypeStats = $this->database->query(
                "SELECT event_type, COUNT(*) as count, 
                        SUM(CASE WHEN send_status = 'success' THEN 1 ELSE 0 END) as success_count
                 FROM wecom_notification_logs 
                 WHERE " . $this->buildWhereClause($conditions) . "
                 GROUP BY event_type"
            )->fetchAll();
            
            // 按仓库统计
            $repoStats = $this->database->query(
                "SELECT repo_name, COUNT(*) as count,
                        SUM(CASE WHEN send_status = 'success' THEN 1 ELSE 0 END) as success_count
                 FROM wecom_notification_logs 
                 WHERE " . $this->buildWhereClause($conditions) . "
                 GROUP BY repo_name 
                 ORDER BY count DESC 
                 LIMIT 10"
            )->fetchAll();
            
            return [
                'status' => 1,
                'message' => '获取通知统计成功',
                'data' => [
                    'total_count' => $totalRules,  // 总规则数
                    'today_sent' => $todaySent,    // 今日发送数
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'success_rate' => $totalCount > 0 ? round($successCount / $totalCount * 100, 2) : 0,
                    'event_type_stats' => $eventTypeStats,
                    'repo_stats' => $repoStats
                ]
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取通知统计失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取通知统计失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 构建 WHERE 子句
     *
     * @param array $conditions 条件数组
     * @return string WHERE子句
     */
    private function buildWhereClause($conditions)
    {
        if (empty($conditions)) {
            return '1=1';
        }
        
        $clauses = [];
        foreach ($conditions as $field => $value) {
            if (strpos($field, '[') !== false) {
                // 处理操作符
                $operator = '';
                if (strpos($field, '[>=]') !== false) {
                    $field = str_replace('[>=]', '', $field);
                    $operator = '>=';
                } elseif (strpos($field, '[<=]') !== false) {
                    $field = str_replace('[<=]', '', $field);
                    $operator = '<=';
                }
                $clauses[] = "{$field} {$operator} '{$value}'";
            } else {
                $clauses[] = "{$field} = '{$value}'";
            }
        }
        
        return implode(' AND ', $clauses);
    }
    
    /**
     * 清理过期的通知日志
     *
     * @param int $daysToKeep 保留天数
     * @return array 清理结果
     */
    public function cleanupNotificationLogs($daysToKeep = 30)
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            
            $deletedCount = $this->database->delete('wecom_notification_logs', [
                'created_at[<]' => $cutoffDate
            ]);
            
            $this->logInfo('清理通知日志完成', [
                'days_to_keep' => $daysToKeep,
                'deleted_count' => $deletedCount
            ]);
            
            return [
                'status' => 1,
                'message' => '通知日志清理完成',
                'deleted_count' => $deletedCount
            ];
            
        } catch (\Exception $e) {
            $this->logError('清理通知日志失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '清理通知日志失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取发送趋势统计
     *
     * @param int $days 统计天数
     * @return array 趋势数据
     */
    public function getSendTrends($days = 7)
    {
        try {
            $trends = [];
            
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                
                $dayStats = [
                    'date' => $date,
                    'total' => $this->database->count('wecom_notification_logs', [
                        'created_at[>=]' => $date . ' 00:00:00',
                        'created_at[<=]' => $date . ' 23:59:59'
                    ]),
                    'success' => $this->database->count('wecom_notification_logs', [
                        'created_at[>=]' => $date . ' 00:00:00',
                        'created_at[<=]' => $date . ' 23:59:59',
                        'send_status' => 'success'
                    ]),
                    'failed' => $this->database->count('wecom_notification_logs', [
                        'created_at[>=]' => $date . ' 00:00:00',
                        'created_at[<=]' => $date . ' 23:59:59',
                        'send_status' => 'failed'
                    ])
                ];
                
                $dayStats['success_rate'] = $dayStats['total'] > 0 ? 
                    round($dayStats['success'] / $dayStats['total'] * 100, 2) : 0;
                
                $trends[] = $dayStats;
            }
            
            return [
                'status' => 1,
                'message' => '获取发送趋势成功',
                'data' => $trends
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取发送趋势失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取发送趋势失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取热门仓库统计
     *
     * @param int $limit 返回数量限制
     * @return array 热门仓库
     */
    public function getPopularRepositories($limit = 10)
    {
        try {
            $repos = $this->database->query(
                "SELECT repo_name, 
                        COUNT(*) as total_notifications,
                        SUM(CASE WHEN send_status = 'success' THEN 1 ELSE 0 END) as success_notifications,
                        COUNT(DISTINCT DATE(created_at)) as active_days
                 FROM wecom_notification_logs 
                 WHERE repo_name != '' AND repo_name != 'multiple'
                 GROUP BY repo_name 
                 ORDER BY total_notifications DESC 
                 LIMIT {$limit}"
            )->fetchAll();
            
            foreach ($repos as &$repo) {
                $repo['success_rate'] = $repo['total_notifications'] > 0 ? 
                    round($repo['success_notifications'] / $repo['total_notifications'] * 100, 2) : 0;
            }
            
            return [
                'status' => 1,
                'message' => '获取热门仓库成功',
                'data' => $repos
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取热门仓库失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取热门仓库失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取活跃用户统计
     *
     * @param int $limit 返回数量限制
     * @return array 活跃用户
     */
    public function getActiveUsers($limit = 10)
    {
        try {
            $users = $this->database->query(
                "SELECT author, 
                        COUNT(*) as total_commits,
                        COUNT(DISTINCT repo_name) as repos_count,
                        COUNT(DISTINCT DATE(created_at)) as active_days,
                        MAX(created_at) as last_activity
                 FROM wecom_notification_logs 
                 WHERE author != '' AND event_type = 'commit'
                 GROUP BY author 
                 ORDER BY total_commits DESC 
                 LIMIT {$limit}"
            )->fetchAll();
            
            return [
                'status' => 1,
                'message' => '获取活跃用户成功',
                'data' => $users
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取活跃用户失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取活跃用户失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取系统健康状态
     *
     * @return array 健康状态
     */
    public function getSystemHealth()
    {
        try {
            $now = date('Y-m-d H:i:s');
            $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
            
            // 最近1小时的统计
            $recentStats = [
                'last_hour_total' => $this->database->count('wecom_notification_logs', [
                    'created_at[>=]' => $oneHourAgo
                ]),
                'last_hour_success' => $this->database->count('wecom_notification_logs', [
                    'created_at[>=]' => $oneHourAgo,
                    'send_status' => 'success'
                ]),
                'last_day_total' => $this->database->count('wecom_notification_logs', [
                    'created_at[>=]' => $oneDayAgo
                ]),
                'last_day_success' => $this->database->count('wecom_notification_logs', [
                    'created_at[>=]' => $oneDayAgo,
                    'send_status' => 'success'
                ])
            ];
            
            // 计算成功率
            $recentStats['last_hour_success_rate'] = $recentStats['last_hour_total'] > 0 ? 
                round($recentStats['last_hour_success'] / $recentStats['last_hour_total'] * 100, 2) : 0;
            
            $recentStats['last_day_success_rate'] = $recentStats['last_day_total'] > 0 ? 
                round($recentStats['last_day_success'] / $recentStats['last_day_total'] * 100, 2) : 0;
            
            // 系统状态评估
            $health = 'healthy';
            if ($recentStats['last_hour_success_rate'] < 80) {
                $health = 'warning';
            }
            if ($recentStats['last_hour_success_rate'] < 50) {
                $health = 'critical';
            }
            
            return [
                'status' => 1,
                'message' => '获取系统健康状态成功',
                'data' => array_merge($recentStats, [
                    'health_status' => $health,
                    'check_time' => $now
                ])
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取系统健康状态失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取系统健康状态失败: ' . $e->getMessage()
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
            $this->logger->writeLog('info', '[WeComStatsService] ' . $message, $context);
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
            $this->logger->writeLog('error', '[WeComStatsService] ' . $message, ['error' => $error]);
        }
    }
}
?>
