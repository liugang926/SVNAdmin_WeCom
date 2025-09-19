<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
use app\service\Logs as ServiceLogs;

/**
 * 企业微信同步状态管理器
 * 
 * 负责管理同步任务的状态，包括：
 * - 同步任务的创建、更新、停止
 * - 同步进度跟踪
 * - 同步日志记录
 * - 同步统计信息管理
 */
class WeComSyncStatus extends Base
{
    /**
     * 配置管理器
     * @var WeComConfigManager
     */
    private $configManager;

    /**
     * 日志服务
     * @var ServiceLogs
     */
    private $ServiceLogs;

    /**
     * 同步统计信息
     * @var array
     */
    private $syncStats;

    /**
     * 构造函数
     */
    public function __construct($parm = [])
    {
        try {
            parent::__construct($parm);
        } catch (\Exception $e) {
            $this->initializeManually($parm);
        }

        try {
            $this->ServiceLogs = new ServiceLogs($parm);
        } catch (\Exception $e) {
            $this->ServiceLogs = null;
        }

        $this->configManager = new WeComConfigManager($parm);
        $this->initializeSyncStats();
    }

    /**
     * 手动初始化（当Base类初始化失败时）
     */
    private function initializeManually($parm)
    {
        $this->token = isset($parm['token']) ? $parm['token'] : '';
        
        global $database;
        if ($database) {
            $this->database = $database;
        } else {
            try {
                require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
                require_once BASE_PATH . '/app/util/Config.php';

                // 确保配置已加载
                \Config::load(BASE_PATH . '/config/');

                $configDatabase = \Config::get('database');
                $configSvn = \Config::get('svn');
                if (array_key_exists('database_file', $configDatabase)) {
                    $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
                }
                
                $this->database = new \Medoo\Medoo($configDatabase);
            } catch (\Exception $e) {
                $this->database = null;
            }
        }
    }

    /**
     * 初始化同步统计信息
     */
    private function initializeSyncStats()
    {
        $this->syncStats = [
            'start_time' => '',
            'end_time' => '',
            'total_departments' => 0,
            'processed_departments' => 0,
            'created_departments' => 0,
            'updated_departments' => 0,
            'skipped_departments' => 0,
            'total_users' => 0,
            'processed_users' => 0,
            'created_users' => 0,
            'updated_users' => 0,
            'skipped_users' => 0,
            'disabled_users' => 0,
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * 开始同步任务
     *
     * @param string $syncType 同步类型 (full/incremental)
     * @return int 同步任务ID
     */
    public function startSyncTask($syncType = 'full')
    {
        $this->syncStats['start_time'] = date('Y-m-d H:i:s');
        
        try {
            $this->database->insert('wecom_sync_logs', [
                'sync_type' => $syncType,
                'sync_status' => 'running',
                'summary' => '同步开始',
                'start_time' => $this->syncStats['start_time'],
                'end_time' => '',
                'error_details' => '',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $taskId = $this->database->id();
            
            $this->logInfo('同步任务开始', [
                'task_id' => $taskId,
                'sync_type' => $syncType,
                'start_time' => $this->syncStats['start_time']
            ]);

            return $taskId;

        } catch (\Exception $e) {
            $this->logError('创建同步任务失败', $e->getMessage());
            throw new \Exception('创建同步任务失败: ' . $e->getMessage());
        }
    }

    /**
     * 完成同步任务
     *
     * @param int $taskId 任务ID
     * @return bool
     */
    public function completeSyncTask($taskId = null)
    {
        $this->syncStats['end_time'] = date('Y-m-d H:i:s');
        
        try {
            // 如果没有指定taskId，查找最新的运行中任务
            if (!$taskId) {
                $runningTask = $this->database->get('wecom_sync_logs', 'id', [
                    'sync_status' => 'running',
                    'ORDER' => ['id' => 'DESC']
                ]);
                $taskId = $runningTask;
            }

            if (!$taskId) {
                throw new \Exception('未找到运行中的同步任务');
            }

            // 更新任务状态
            $this->database->update('wecom_sync_logs', [
                'sync_status' => 'completed',
                'summary' => json_encode($this->syncStats, JSON_UNESCAPED_UNICODE),
                'end_time' => $this->syncStats['end_time']
            ], ['id' => $taskId]);

            $this->logInfo('同步任务完成', [
                'task_id' => $taskId,
                'stats' => $this->syncStats
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('完成同步任务失败', $e->getMessage());
            return false;
        }
    }

    /**
     * 停止同步任务（失败）
     *
     * @param string $errorMessage 错误信息
     * @param int $taskId 任务ID
     * @return bool
     */
    public function stopSyncTask($errorMessage, $taskId = null)
    {
        $this->syncStats['end_time'] = date('Y-m-d H:i:s');
        
        try {
            // 如果没有指定taskId，查找最新的运行中任务
            if (!$taskId) {
                $runningTask = $this->database->get('wecom_sync_logs', 'id', [
                    'sync_status' => 'running',
                    'ORDER' => ['id' => 'DESC']
                ]);
                $taskId = $runningTask;
            }

            if (!$taskId) {
                throw new \Exception('未找到运行中的同步任务');
            }

            // 更新任务状态为失败
            $this->database->update('wecom_sync_logs', [
                'sync_status' => 'failed',
                'end_time' => $this->syncStats['end_time'],
                'error_details' => $errorMessage,
                'summary' => '同步过程中发生错误，任务自动停止'
            ], ['id' => $taskId]);

            $this->logInfo('同步任务因错误停止', [
                'task_id' => $taskId,
                'error' => $errorMessage
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('停止同步任务失败', $e->getMessage());
            return false;
        }
    }

    /**
     * 手动停止同步任务
     *
     * @return array
     */
    public function manualStopSync()
    {
        try {
            $runningTask = $this->database->get('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['id' => 'DESC']
            ]);

            if (!$runningTask) {
                return [
                    'status' => 0,
                    'message' => '没有正在运行的同步任务'
                ];
            }

            $this->database->update('wecom_sync_logs', [
                'sync_status' => 'stopped',
                'end_time' => date('Y-m-d H:i:s'),
                'summary' => '用户手动停止同步'
            ], [
                'id' => $runningTask['id']
            ]);

            $this->logInfo('用户手动停止同步任务', ['task_id' => $runningTask['id']]);

            return [
                'status' => 1,
                'message' => '同步任务已停止',
                'data' => [
                    'task_id' => $runningTask['id'],
                    'stop_time' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (\Exception $e) {
            $this->logError('手动停止同步失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '停止同步失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查同步状态
     *
     * @return array
     */
    public function checkSyncStatus()
    {
        try {
            $lastSync = $this->database->select('wecom_sync_logs', '*', [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 1
            ]);

            if (empty($lastSync)) {
                return [
                    'status' => 'never_synced',
                    'message' => '从未执行过同步',
                    'data' => null
                ];
            }

            $sync = $lastSync[0];
            $status = $sync['sync_status'];

            switch ($status) {
                case 'running':
                    return [
                        'status' => 'running',
                        'message' => '同步正在进行中',
                        'data' => $sync
                    ];

                case 'completed':
                    return [
                        'status' => 'completed',
                        'message' => '上次同步已完成',
                        'data' => $sync
                    ];

                case 'failed':
                    return [
                        'status' => 'failed',
                        'message' => '上次同步失败',
                        'data' => $sync
                    ];

                case 'stopped':
                    return [
                        'status' => 'stopped',
                        'message' => '上次同步被手动停止',
                        'data' => $sync
                    ];

                default:
                    return [
                        'status' => 'unknown',
                        'message' => '未知状态',
                        'data' => $sync
                    ];
            }

        } catch (\Exception $e) {
            $this->logError('检查同步状态失败', $e->getMessage());
            return [
                'status' => 'error',
                'message' => '检查状态失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 获取同步历史
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function getSyncHistory($limit = 10)
    {
        try {
            $history = $this->database->select('wecom_sync_logs', '*', [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => $limit
            ]);

            return [
                'status' => 1,
                'message' => '获取同步历史成功',
                'data' => $history
            ];

        } catch (\Exception $e) {
            $this->logError('获取同步历史失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取同步历史失败: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 更新同步统计信息
     *
     * @param array $stats 统计数据
     */
    public function updateSyncStats($stats)
    {
        $this->syncStats = array_merge($this->syncStats, $stats);
    }

    /**
     * 获取同步统计信息
     *
     * @return array
     */
    public function getSyncStats()
    {
        return $this->syncStats;
    }

    /**
     * 添加同步错误
     *
     * @param string $error 错误信息
     */
    public function addSyncError($error)
    {
        $this->syncStats['errors'][] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $error
        ];
    }

    /**
     * 添加同步警告
     *
     * @param string $warning 警告信息
     */
    public function addSyncWarning($warning)
    {
        $this->syncStats['warnings'][] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $warning
        ];
    }

    /**
     * 增加部门处理计数
     *
     * @param string $action 操作类型 (created/updated/skipped)
     */
    public function incrementDepartmentCount($action = 'processed')
    {
        if (isset($this->syncStats[$action . '_departments'])) {
            $this->syncStats[$action . '_departments']++;
        }
        $this->syncStats['processed_departments']++;
    }

    /**
     * 增加用户处理计数
     *
     * @param string $action 操作类型 (created/updated/skipped/disabled)
     */
    public function incrementUserCount($action = 'processed')
    {
        if (isset($this->syncStats[$action . '_users'])) {
            $this->syncStats[$action . '_users']++;
        }
        $this->syncStats['processed_users']++;
    }

    /**
     * 设置总数
     *
     * @param int $totalDepartments 总部门数
     * @param int $totalUsers 总用户数
     */
    public function setTotals($totalDepartments, $totalUsers)
    {
        $this->syncStats['total_departments'] = $totalDepartments;
        $this->syncStats['total_users'] = $totalUsers;
    }

    /**
     * 获取同步进度
     *
     * @return array
     */
    public function getSyncProgress()
    {
        $deptProgress = $this->syncStats['total_departments'] > 0 
            ? ($this->syncStats['processed_departments'] / $this->syncStats['total_departments']) * 100 
            : 0;

        $userProgress = $this->syncStats['total_users'] > 0 
            ? ($this->syncStats['processed_users'] / $this->syncStats['total_users']) * 100 
            : 0;

        return [
            'department_progress' => round($deptProgress, 2),
            'user_progress' => round($userProgress, 2),
            'overall_progress' => round(($deptProgress + $userProgress) / 2, 2),
            'stats' => $this->syncStats
        ];
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComSyncStatus] ' . $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync', $logMessage);
            }
        }
    }

    /**
     * 记录错误日志
     */
    private function logError($message, $error = '')
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComSyncStatus ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
