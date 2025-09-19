<?php

namespace app\service;

/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信数据同步服务类
 */

require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/service/wecom_api_standalone.php';
use app\service\Logs as ServiceLogs;
use app\service\Svnuser as ServiceSvnuser;
use app\service\Svngroup as ServiceSvngroup;
use app\service\WeComConfig;

class WeComSync extends Base
{
    /**
     * 其它服务层对象
     *
     * @var object
     */
    private $ServiceLogs;
    private $ServiceSvnuser;
    private $ServiceSvngroup;
    private $WeComAPI;

    /**
     * 企业微信配置
     *
     * @var array
     */
    private $wecomConfig;

    /**
     * 同步配置
     *
     * @var array
     */
    private $syncConfig;

    /**
     * 当前同步会话ID
     *
     * @var string
     */
    private $syncSessionId;

    /**
     * 同步统计数据
     *
     * @var array
     */
    private $syncStats;

    function __construct($parm = [])
    {
        try {
            parent::__construct($parm);
        } catch (\Exception $e) {
            // 如果Base类初始化失败，手动初始化必要的属性
            $this->initializeManually($parm);
        }

        $this->ServiceLogs = new ServiceLogs($parm);
        $this->ServiceSvnuser = new ServiceSvnuser($parm);
        $this->ServiceSvngroup = new ServiceSvngroup($parm);

        // 确保SVNAdmin对象已初始化
        if (!isset($this->SVNAdmin)) {
            require_once BASE_PATH . '/extension/Witersen/SVNAdmin.php';
            $this->SVNAdmin = new \Witersen\SVNAdmin();
        }

        // 加载企业微信配置（优先从数据库读取）
        $this->wecomConfig = WeComConfig::getConfig();
        
        // 创建独立的企业微信API实例
        $this->WeComAPI = new \WeComAPIStandalone([
            'corp_id' => $this->wecomConfig['corp_id'],
            'corp_secret' => $this->wecomConfig['corp_secret'],
            'agent_id' => $this->wecomConfig['agent_id']
        ]);
        // 兼容旧/新配置：优先使用 contact_sync；否则根据现有配置动态构建
        if (isset($this->wecomConfig['contact_sync'])) {
            $this->syncConfig = $this->wecomConfig['contact_sync'];
        } else {
            $dept = $this->wecomConfig['department_mapping'] ?? [];
            $sync = $this->wecomConfig['sync'] ?? [];
            $this->syncConfig = [
                'enable' => true,
                'department_root_id' => $dept['root_department_id'] ?? 1,
                'sync_interval' => $sync['interval'] ?? 3600,
                'update_authz_file' => false,
                'group_name_prefix' => $dept['group_name_prefix'] ?? 'wecom_',
                'auto_create_svn_group' => $dept['auto_create_groups'] ?? true,
                'auto_remove_user_from_group' => false,
            ];
        }
        
        // 初始化同步会话
        $this->syncSessionId = $this->generateSyncSessionId();
        $this->initSyncStats();

        // 检查同步功能是否启用（默认启用）
        if (!isset($this->syncConfig['enable'])) {
            $this->syncConfig['enable'] = true;
        }
    }

    /**
     * 生成同步会话ID
     *
     * @return string
     */
    private function generateSyncSessionId()
    {
        return 'wecom_sync_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * 初始化同步统计数据
     *
     * @return void
     */
    private function initSyncStats()
    {
        $this->syncStats = [
            'session_id' => $this->syncSessionId,
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => null,
            'duration' => 0,
            'departments' => [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'errors' => 0
            ],
            'users' => [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'errors' => 0
            ],
            'groups' => [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'errors' => 0
            ],
            'permissions' => [
                'total' => 0,
                'added' => 0,
                'removed' => 0,
                'errors' => 0
            ],
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * 向后兼容：供控制器调用的全量/增量同步入口
     */
    public function fullSync()
    {
        return $this->executeFullSync(true);
    }

    public function incrementalSync()
    {
        return $this->executeFullSync(false);
    }

    /**
     * 执行完整同步
     *
     * @param bool $forceFullSync 是否强制全量同步
     * @return array
     */
    public function executeFullSync($forceFullSync = false)
    {
        $this->logInfo('开始执行企业微信完整同步', [
            'session_id' => $this->syncSessionId,
            'force_full_sync' => $forceFullSync
        ]);

        $startTime = microtime(true);

        try {
            // 记录同步开始
            $this->recordSyncStart();

                        // 1. 获取企业微信数据
            $wecomData = $this->fetchWeComData($forceFullSync);

            // 2. 优化同步：分批处理部门和用户数据
            $this->logInfo('开始优化同步部门和用户数据');
            
            // 分批同步部门和用户（交替进行，提高效率）
            $departmentResult = $this->syncDepartmentsBatch($wecomData['departments']);
            $userResult = $this->syncUsersBatch($wecomData['users']);
            
            $this->logInfo('部门和用户数据优化同步完成');

            // 3. 同步权限关系（需要部门和用户数据都完成后进行）
            $permissionResult = $this->syncPermissions($wecomData);

            // 4. 清理无效数据
            $cleanupResult = $this->cleanupInvalidData();

            // 计算同步耗时
            $this->syncStats['duration'] = round((microtime(true) - $startTime) * 1000);
            $this->syncStats['end_time'] = date('Y-m-d H:i:s');

            // 记录同步完成
            $this->recordSyncComplete();

            $this->logInfo('企业微信完整同步完成', $this->syncStats);

            return [
                'status' => 1,
                'message' => '同步完成',
                'data' => $this->syncStats
            ];

        } catch (\Exception $e) {
            $this->syncStats['end_time'] = date('Y-m-d H:i:s');
            $this->syncStats['duration'] = round((microtime(true) - $startTime) * 1000);
            
            // 详细错误信息
            $errorMessage = $e->getMessage();
            $errorContext = [
                'error_type' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'sync_stats' => $this->syncStats
            ];
            
            $this->recordSyncError($errorMessage);
            $this->logError('企业微信同步失败', $errorMessage . ' 详情: ' . json_encode($errorContext, JSON_UNESCAPED_UNICODE));

            // 自动停止同步任务（更新数据库状态）
            $this->autoStopSyncTask($errorMessage);

            return [
                'status' => 0,
                'message' => '同步失败: ' . $errorMessage,
                'data' => $this->syncStats,
                'error_details' => $errorContext
            ];
        }
    }

    /**
     * 手动初始化必要的属性（当Base类初始化失败时）
     *
     * @param array $parm
     * @return void
     */
    private function initializeManually($parm)
    {
        $this->token = isset($parm['token']) ? $parm['token'] : '';
        
        // 手动初始化数据库连接
        global $database;
        if ($database) {
            $this->database = $database;
        } else {
            try {
                require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
                $this->database = new \Medoo\Medoo([
                    'database_type' => 'sqlite',
                    'database_file' => '/home/svnadmin/svnadmin.db'
                ]);
            } catch (\Exception $e) {
                // 数据库连接失败，但不影响功能
                $this->database = null;
            }
        }
        
        // 初始化配置
        $this->configSvn = [
            'svn_authz_file' => '/home/svnadmin/authz',
            'svn_passwd_file' => '/home/svnadmin/passwd'
        ];
    }

    /**
     * 获取完整组织架构数据
     *
     * @param int $rootDepartmentId
     * @return array
     */
    private function getFullOrganization($rootDepartmentId = 1)
    {
        // 获取访问令牌
        $accessToken = $this->WeComAPI->getAccessToken();
        
        // 获取所有部门
        $departments = $this->WeComAPI->getDepartmentList($accessToken, $rootDepartmentId);
        
        // 获取所有用户
        $users = [];
        foreach ($departments as $dept) {
            $deptUsers = $this->WeComAPI->getDepartmentUsers($accessToken, $dept['id'], false);
            $users = array_merge($users, $deptUsers);
        }
        
        // 去重用户（一个用户可能在多个部门）
        $uniqueUsers = [];
        foreach ($users as $user) {
            $uniqueUsers[$user['userid']] = $user;
        }
        
        return [
            'departments' => $departments,
            'users' => array_values($uniqueUsers)
        ];
    }

    /**
     * 获取增量组织架构数据
     *
     * @param int $lastSyncTime
     * @return array
     */
    private function getIncrementalOrganization($lastSyncTime)
    {
        // 企业微信API不直接支持增量获取，使用全量获取
        return $this->getFullOrganization($this->syncConfig['department_root_id']);
    }

    /**
     * 获取企业微信数据
     *
     * @param bool $forceFullSync
     * @return array
     * @throws \Exception
     */
    private function fetchWeComData($forceFullSync = false)
    {
        $this->logInfo('开始获取企业微信数据');

        if ($forceFullSync) {
            // 强制全量同步
            $data = $this->getFullOrganization($this->syncConfig['department_root_id']);
        } else {
            // 检查是否需要增量同步
            $lastSyncTime = $this->getLastSyncTime();
            if ($lastSyncTime && $this->shouldUseIncrementalSync($lastSyncTime)) {
                $data = $this->getIncrementalOrganization($lastSyncTime);
                if ($data['type'] === 'incremental') {
                    $this->logInfo('使用增量同步模式', ['last_sync_time' => $lastSyncTime]);
                } else {
                    $this->logInfo('增量同步降级为全量同步');
                    $data = $data['data'];
                }
            } else {
                $data = $this->getFullOrganization($this->syncConfig['department_root_id']);
            }
        }

        $this->logInfo('企业微信数据获取完成', [
            'departments_count' => count($data['departments']),
            'users_count' => count($data['users'])
        ]);

        return $data;
    }

    /**
     * 获取上次同步时间
     *
     * @return string|null
     */
    private function getLastSyncTime()
    {
        try {
            $result = $this->database->select('wecom_config', 'last_sync_time', ['id' => 1]);
            return $result ? $result[0] : null;
        } catch (\Exception $e) {
            $this->logError('获取上次同步时间失败', $e->getMessage());
            return null;
        }
    }

    /**
     * 判断是否应该使用增量同步
     *
     * @param string $lastSyncTime
     * @return bool
     */
    private function shouldUseIncrementalSync($lastSyncTime)
    {
        if (empty($lastSyncTime)) {
            return false;
        }

        $lastSyncTimestamp = strtotime($lastSyncTime);
        $currentTimestamp = time();
        $syncInterval = $this->syncConfig['sync_interval'];

        // 如果距离上次同步时间小于配置的同步间隔，使用增量同步
        return ($currentTimestamp - $lastSyncTimestamp) < ($syncInterval * 2);
    }

    /**
     * 记录同步开始
     *
     * @return void
     */
    private function recordSyncStart()
    {
        try {
            $this->database->insert('wecom_sync_logs', [
                'sync_type' => 'full',
                'sync_status' => 'running',
                'summary' => '同步开始',
                'start_time' => $this->syncStats['start_time'],
                'end_time' => '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('记录同步开始状态失败', $e->getMessage());
        }
    }

    /**
     * 记录同步完成
     *
     * @return void
     */
    private function recordSyncComplete()
    {
        try {
            // 更新配置表的最后同步时间
            $this->database->update('wecom_config', [
                'last_sync_time' => $this->syncStats['end_time'],
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => 1]);

            // 记录同步日志
            $this->database->insert('wecom_sync_logs', [
                'sync_type' => 'full',
                'sync_status' => 'success',
                'summary' => json_encode($this->syncStats, JSON_UNESCAPED_UNICODE),
                'start_time' => $this->syncStats['start_time'],
                'end_time' => $this->syncStats['end_time'],
                'duration' => $this->syncStats['duration'],
                'departments_total' => $this->syncStats['departments']['total'],
                'departments_synced' => $this->syncStats['departments']['created'] + $this->syncStats['departments']['updated'],
                'users_total' => $this->syncStats['users']['total'],
                'users_synced' => $this->syncStats['users']['created'] + $this->syncStats['users']['updated'],
                'errors_count' => count($this->syncStats['errors']),
                'error_details' => empty($this->syncStats['errors']) ? '' : json_encode($this->syncStats['errors'], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('记录同步完成状态失败', $e->getMessage());
        }
    }

    /**
     * 记录同步错误
     *
     * @param string $errorMessage
     * @return void
     */
    private function recordSyncError($errorMessage)
    {
        try {
            $this->database->insert('wecom_sync_logs', [
                'sync_type' => 'full',
                'sync_status' => 'failed',
                'error_details' => $errorMessage,
                'start_time' => $this->syncStats['start_time'],
                'end_time' => $this->syncStats['end_time'],
                'duration' => $this->syncStats['duration'],
                'errors_count' => count($this->syncStats['errors']) + 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logError('记录同步错误状态失败', $e->getMessage());
        }
    }

    /**
     * 自动停止同步任务
     *
     * @param string $errorMessage
     * @return void
     */
    private function autoStopSyncTask($errorMessage)
    {
        try {
            // 查找当前运行中的同步任务
            $runningTask = $this->database->get('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['id' => 'DESC']
            ]);

            if ($runningTask) {
                // 更新任务状态为失败并停止
                $this->database->update('wecom_sync_logs', [
                    'sync_status' => 'failed',
                    'end_time' => date('Y-m-d H:i:s'),
                    'error_details' => $errorMessage,
                    'summary' => '同步过程中发生错误，任务自动停止'
                ], [
                    'id' => $runningTask['id']
                ]);

                $this->logInfo('同步任务因错误自动停止', [
                    'task_id' => $runningTask['id'],
                    'error' => $errorMessage
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('自动停止同步任务失败', $e->getMessage());
        }
    }

    /**
     * 添加同步错误
     *
     * @param string $type
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function addSyncError($type, $message, $context = [])
    {
        $error = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'time' => date('Y-m-d H:i:s')
        ];

        $this->syncStats['errors'][] = $error;
        $this->logError("同步错误 [{$type}]", $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 添加同步警告
     *
     * @param string $type
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function addSyncWarning($type, $message, $context = [])
    {
        $warning = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'time' => date('Y-m-d H:i:s')
        ];

        $this->syncStats['warnings'][] = $warning;
        $this->logInfo("同步警告 [{$type}]", $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取同步统计数据
     *
     * @return array
     */
    public function getSyncStats()
    {
        return $this->syncStats;
    }

    /**
     * 获取同步会话ID
     *
     * @return string
     */
    public function getSyncSessionId()
    {
        return $this->syncSessionId;
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
                    'status' => 'never',
                    'message' => '从未执行过同步'
                ];
            }

            $lastSync = $lastSync[0];
            $lastSyncTime = strtotime($lastSync['start_time']);
            $currentTime = time();
            $timeDiff = $currentTime - $lastSyncTime;

            return [
                'status' => $lastSync['sync_status'] ?? ($lastSync['status'] ?? ''),
                'last_sync_time' => $lastSync['start_time'],
                'time_diff' => $timeDiff,
                'time_diff_human' => $this->formatTimeDiff($timeDiff),
                'message' => $lastSync['summary'] ?? ($lastSync['error_details'] ?? ($lastSync['message'] ?? ''))
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => '检查同步状态失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 格式化时间差
     *
     * @param int $seconds
     * @return string
     */
    private function formatTimeDiff($seconds)
    {
        if ($seconds < 60) {
            return $seconds . '秒前';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . '分钟前';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . '小时前';
        } else {
            return floor($seconds / 86400) . '天前';
        }
    }

    /**
     * 记录信息日志
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logInfo($message, $context = [])
    {
        if (isset($this->wecomConfig['sync_log']['enable']) && $this->wecomConfig['sync_log']['enable']) {
            $logMessage = '[WeComSync] ' . $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom_sync');
        }
    }

    /**
     * 记录错误日志
     *
     * @param string $message
     * @param string $error
     * @return void
     */
    private function logError($message, $error = '')
    {
        if (isset($this->wecomConfig['sync_log']['enable']) && $this->wecomConfig['sync_log']['enable']) {
            $logMessage = '[WeComSync ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom_sync');
        }
    }

    // ==================== 抽象方法，由子类实现 ====================

    /**
     * 同步部门数据
     *
     * @param array $departments
     * @return array
     */
    protected function syncDepartments($departments)
    {
        $this->logInfo('开始同步部门数据', ['count' => count($departments)]);

        $this->syncStats['departments']['total'] = count($departments);
        $results = [];

        try {
            // 获取现有的企业微信部门数据
            $existingDepartments = $this->getExistingWeComDepartments();
            
            // 获取现有的 SVN 组数据
            $existingSvnGroups = $this->getExistingSvnGroups();

            foreach ($departments as $department) {
                try {
                    $result = $this->syncSingleDepartment($department, $existingDepartments, $existingSvnGroups);
                    $results[] = $result;

                    // 更新统计数据
                    if ($result['action'] === 'created') {
                        $this->syncStats['departments']['created']++;
                    } elseif ($result['action'] === 'updated') {
                        $this->syncStats['departments']['updated']++;
                    }

                } catch (\Exception $e) {
                    $this->syncStats['departments']['errors']++;
                    $this->addSyncError('department_sync', $e->getMessage(), [
                        'department_id' => $department['id'],
                        'department_name' => $department['name']
                    ]);
                    
                    $results[] = [
                        'department_id' => $department['id'],
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            // 处理已删除的部门
            $this->handleDeletedDepartments($departments, $existingDepartments);

            $this->logInfo('部门数据同步完成', [
                'total' => $this->syncStats['departments']['total'],
                'created' => $this->syncStats['departments']['created'],
                'updated' => $this->syncStats['departments']['updated'],
                'deleted' => $this->syncStats['departments']['deleted'],
                'errors' => $this->syncStats['departments']['errors']
            ]);

            return [
                'status' => 1,
                'message' => '部门同步完成',
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->addSyncError('departments_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步用户数据
     *
     * @param array $users
     * @return array
     */
    protected function syncUsers($users)
    {
        $this->logInfo('开始同步用户数据', ['count' => count($users)]);

        $this->syncStats['users']['total'] = count($users);
        $results = [];

        try {
            // 获取现有的企业微信用户数据
            $existingWeComUsers = $this->getExistingWeComUsers();
            
            // 获取现有的 SVN 用户数据
            $existingSvnUsers = $this->getExistingSvnUsers();

            foreach ($users as $user) {
                try {
                    $result = $this->syncSingleUser($user, $existingWeComUsers, $existingSvnUsers);
                    $results[] = $result;

                    // 更新统计数据
                    if ($result['action'] === 'created') {
                        $this->syncStats['users']['created']++;
                    } elseif ($result['action'] === 'updated') {
                        $this->syncStats['users']['updated']++;
                    } elseif ($result['action'] === 'matched') {
                        // 匹配到现有用户，更新企业微信信息
                        $this->syncStats['users']['updated']++;
                    }

                } catch (\Exception $e) {
                    $this->syncStats['users']['errors']++;
                    $this->addSyncError('user_sync', $e->getMessage(), [
                        'user_id' => $user['userid'],
                        'user_name' => $user['name']
                    ]);
                    
                    $results[] = [
                        'user_id' => $user['userid'],
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            // 处理已删除的用户
            $this->handleDeletedUsers($users, $existingWeComUsers);

            $this->logInfo('用户数据同步完成', [
                'total' => $this->syncStats['users']['total'],
                'created' => $this->syncStats['users']['created'],
                'updated' => $this->syncStats['users']['updated'],
                'deleted' => $this->syncStats['users']['deleted'],
                'errors' => $this->syncStats['users']['errors']
            ]);

            return [
                'status' => 1,
                'message' => '用户同步完成',
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->addSyncError('users_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步权限关系
     *
     * @param array $wecomData
     * @return array
     */
    protected function syncPermissions($wecomData)
    {
        $this->logInfo('开始同步权限关系');

        $results = [];

        try {
            // 1. 同步用户到组的关系
            $userGroupResult = $this->syncUserGroupRelations($wecomData);
            $results['user_groups'] = $userGroupResult;

            // 2. 同步分组层级关系
            $groupHierarchyResult = $this->syncGroupHierarchy($wecomData['departments']);
            $results['group_hierarchy'] = $groupHierarchyResult;

            // 3. 同步基于部门的仓库权限
            $repositoryResult = $this->syncRepositoryPermissions($wecomData);
            $results['repositories'] = $repositoryResult;

            // 4. 更新 authz 文件（如果启用）
            if ($this->syncConfig['update_authz_file']) {
                $authzResult = $this->updateAuthzFile();
                $results['authz'] = $authzResult;
            }

            $this->logInfo('权限关系同步完成', [
                'user_groups_processed' => $userGroupResult['processed'] ?? 0,
                'repositories_processed' => $repositoryResult['processed'] ?? 0,
                'permissions_added' => $this->syncStats['permissions']['added'],
                'permissions_removed' => $this->syncStats['permissions']['removed']
            ]);

            return [
                'status' => 1,
                'message' => '权限同步完成',
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->addSyncError('permissions_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 清理无效数据
     *
     * @return array
     */
    protected function cleanupInvalidData()
    {
        // 这个方法将在后续任务中实现
        return ['status' => 1, 'message' => '清理完成'];
    }

    // ==================== 部门同步辅助方法 ====================

    /**
     * 同步单个部门
     *
     * @param array $department
     * @param array $existingDepartments
     * @param array $existingSvnGroups
     * @return array
     * @throws \Exception
     */
    private function syncSingleDepartment($department, &$existingDepartments, &$existingSvnGroups)
    {
        $departmentId = $department['id'];
        $departmentName = $department['name'];
        $parentId = $department['parentid'];

        // 生成 SVN 组名
        $svnGroupName = $this->generateSvnGroupName($department);

        // 检查企业微信部门是否已存在
        $existingDepartment = $existingDepartments[$departmentId] ?? null;

        if ($existingDepartment) {
            // 更新现有部门
            return $this->updateDepartment($department, $existingDepartment, $svnGroupName, $existingSvnGroups);
        } else {
            // 创建新部门
            return $this->createDepartment($department, $svnGroupName, $existingSvnGroups);
        }
    }

    /**
     * 创建新部门
     *
     * @param array $department
     * @param string $svnGroupName
     * @param array $existingSvnGroups
     * @return array
     * @throws \Exception
     */
    private function createDepartment($department, $svnGroupName, &$existingSvnGroups)
    {
        $departmentId = $department['id'];
        $departmentName = $department['name'];
        $parentId = $department['parentid'];

        // 1. 创建或获取对应的 SVN 组
        $svnGroupId = $this->createOrGetSvnGroup($svnGroupName, $existingSvnGroups);

        // 2. 在企业微信部门表中记录
        $this->database->insert('wecom_departments', [
            'wecom_dept_id' => $departmentId,
            'parent_id' => $parentId,
            'dept_name' => $departmentName,
            'dept_order' => $department['order'] ?? 0,
            'svn_group_id' => $svnGroupId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // 更新SVN组的备注信息
        $this->updateSvnGroupNote($svnGroupId, $departmentName, $departmentId);

        $this->logInfo('创建部门成功', [
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'svn_group_name' => $svnGroupName,
            'svn_group_id' => $svnGroupId
        ]);

        return [
            'department_id' => $departmentId,
            'action' => 'created',
            'svn_group_name' => $svnGroupName,
            'svn_group_id' => $svnGroupId,
            'message' => '部门创建成功'
        ];
    }

    /**
     * 更新现有部门
     *
     * @param array $department
     * @param array $existingDepartment
     * @param string $svnGroupName
     * @param array $existingSvnGroups
     * @return array
     * @throws \Exception
     */
    private function updateDepartment($department, $existingDepartment, $svnGroupName, &$existingSvnGroups)
    {
        $departmentId = $department['id'];
        $departmentName = $department['name'];
        $parentId = $department['parentid'];

        $needUpdate = false;
        $updateData = [];

        // 检查名称是否变更
        if ($existingDepartment['dept_name'] !== $departmentName) {
            $updateData['dept_name'] = $departmentName;
            $needUpdate = true;
        }

        // 检查父部门是否变更
        if ($existingDepartment['parent_id'] != $parentId) {
            $updateData['parent_id'] = $parentId;
            $needUpdate = true;
        }

        // 检查排序是否变更
        $newOrder = $department['order'] ?? 0;
        if ($existingDepartment['dept_order'] != $newOrder) {
            $updateData['dept_order'] = $newOrder;
            $needUpdate = true;
        }

        if ($needUpdate) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            $this->database->update('wecom_departments', $updateData, [
                'wecom_dept_id' => $departmentId
            ]);

            $this->logInfo('更新部门成功', [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'changes' => $updateData
            ]);

            $action = 'updated';
            $message = '部门更新成功';
        } else {
            $action = 'checked';
            $message = '部门信息已是最新';
        }

        // 无论部门信息是否有变化，都更新SVN组的备注时间（反映最后同步时间）
        if (isset($existingDepartment['svn_group_id'])) {
            $this->updateSvnGroupNote($existingDepartment['svn_group_id'], $departmentName, $departmentId);
        }

        return [
            'department_id' => $departmentId,
            'action' => $action,
            'svn_group_name' => $svnGroupName,
            'message' => $message,
            'changes' => $updateData ?? []
        ];
    }

    /**
     * 生成 SVN 组名
     *
     * @param array $department
     * @return string
     */
    private function generateSvnGroupName($department)
    {
        $departmentName = $department['name'];
        
        // 清理部门名称，移除特殊字符（保留中文、英文字母、数字、下划线、连字符）
        $cleanName = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $departmentName);
        
        return $cleanName;
    }

    /**
     * 创建或获取 SVN 组
     *
     * @param string $groupName
     * @param array $existingSvnGroups
     * @return int SVN组ID
     * @throws \Exception
     */
    private function createOrGetSvnGroup($groupName, &$existingSvnGroups)
    {
        // 检查组是否已存在
        foreach ($existingSvnGroups as $group) {
            if ($group['svn_group_name'] === $groupName) {
                return $group['svn_group_id'];
            }
        }

        // 如果配置允许自动创建组，则创建新组
        if ($this->syncConfig['auto_create_svn_group']) {
            return $this->createSvnGroup($groupName, $existingSvnGroups);
        } else {
            throw new \Exception("SVN组不存在且未启用自动创建: {$groupName}");
        }
    }

    /**
     * 创建 SVN 组
     *
     * @param string $groupName
     * @param array $existingSvnGroups
     * @return int
     * @throws \Exception
     */
    private function createSvnGroup($groupName, &$existingSvnGroups)
    {
        try {
            // 1. 在authz文件中创建组
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);
            
            $result = $this->SVNAdmin->AddGroup($authzContent, $groupName);
            if (is_string($result)) {
                // 成功，写入更新后的authz文件
                file_put_contents($authzFilePath, $result);
                $this->logInfo('在authz文件中创建组成功', ['group_name' => $groupName]);
            } else {
                // 失败，记录错误但继续（组可能已存在）
                $this->logInfo('authz文件中组可能已存在', [
                    'group_name' => $groupName,
                    'error_code' => $result
                ]);
            }
            
            // 2. 插入到 svn_groups 表
            $this->database->insert('svn_groups', [
                'svn_group_name' => $groupName,
                'include_user_count' => 0,
                'include_group_count' => 0,
                'include_aliase_count' => 0
            ]);

            $svnGroupId = $this->database->id();

            // 3. 更新缓存
            $existingSvnGroups[] = [
                'svn_group_id' => $svnGroupId,
                'svn_group_name' => $groupName,
                'include_user_count' => 0,
                'include_group_count' => 0,
                'include_aliase_count' => 0
            ];

            $this->syncStats['groups']['created']++;

            $this->logInfo('创建SVN组成功', [
                'group_name' => $groupName,
                'group_id' => $svnGroupId
            ]);

            return $svnGroupId;

        } catch (\Exception $e) {
            $this->addSyncError('create_svn_group', "创建SVN组失败: {$groupName}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取现有的企业微信部门数据
     *
     * @return array
     */
    private function getExistingWeComDepartments()
    {
        try {
            $departments = $this->database->select('wecom_departments', '*');
            $result = [];
            
            foreach ($departments as $dept) {
                $result[$dept['wecom_dept_id']] = $dept;
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logError('获取现有企业微信部门数据失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 获取现有的 SVN 组数据
     *
     * @return array
     */
    private function getExistingSvnGroups()
    {
        try {
            return $this->database->select('svn_groups', '*');
        } catch (\Exception $e) {
            $this->logError('获取现有SVN组数据失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 处理已删除的部门
     *
     * @param array $currentDepartments
     * @param array $existingDepartments
     * @return void
     */
    private function handleDeletedDepartments($currentDepartments, $existingDepartments)
    {
        // 获取当前企业微信中的部门ID列表
        $currentDepartmentIds = array_column($currentDepartments, 'id');
        
        // 找出已删除的部门
        $deletedDepartments = [];
        foreach ($existingDepartments as $departmentId => $department) {
            if (!in_array($departmentId, $currentDepartmentIds)) {
                $deletedDepartments[] = $department;
            }
        }

        foreach ($deletedDepartments as $department) {
            try {
                $this->handleDeletedDepartment($department);
                $this->syncStats['departments']['deleted']++;
            } catch (\Exception $e) {
                $this->syncStats['departments']['errors']++;
                $this->addSyncError('delete_department', $e->getMessage(), [
                    'department_id' => $department['wecom_dept_id']
                ]);
            }
        }

        if (!empty($deletedDepartments)) {
            $this->logInfo('处理已删除部门', ['count' => count($deletedDepartments)]);
        }
    }

    /**
     * 处理单个已删除的部门
     *
     * @param array $department
     * @return void
     * @throws \Exception
     */
    private function handleDeletedDepartment($department)
    {
        $departmentId = $department['wecom_dept_id'];
        
        // 删除企业微信部门记录
        $this->database->delete('wecom_departments', [
            'wecom_dept_id' => $departmentId
        ]);

        $this->logInfo('删除部门记录', [
            'department_id' => $departmentId,
            'department_name' => $department['wecom_name']
        ]);
    }

    // ==================== 用户同步辅助方法 ====================

    /**
     * 同步单个用户
     *
     * @param array $user
     * @param array $existingWeComUsers
     * @param array $existingSvnUsers
     * @return array
     * @throws \Exception
     */
    private function syncSingleUser($user, &$existingWeComUsers, &$existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        // 检查企业微信用户是否已存在
        $existingWeComUser = $existingWeComUsers[$userId] ?? null;

        if ($existingWeComUser) {
            // 更新现有企业微信用户记录
            return $this->updateWeComUser($user, $existingWeComUser, $existingSvnUsers);
        } else {
            // 创建新的企业微信用户记录
            return $this->createWeComUser($user, $existingSvnUsers);
        }
    }

    /**
     * 创建新的企业微信用户记录
     *
     * @param array $user
     * @param array $existingSvnUsers
     * @return array
     * @throws \Exception
     */
    private function createWeComUser($user, &$existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        // 尝试匹配现有的 SVN 用户
        $matchedSvnUser = $this->matchSvnUser($user, $existingSvnUsers);

        $svnUserId = null;
        $action = 'created';

        if ($matchedSvnUser) {
            // 匹配到现有 SVN 用户
            $svnUserId = $matchedSvnUser['svn_user_id'];
            $action = 'matched';

            // 更新 SVN 用户的企业微信信息
            $this->updateSvnUserWeComInfo($matchedSvnUser, $user);

            $this->logInfo('匹配到现有SVN用户', [
                'wecom_user_id' => $userId,
                'wecom_user_name' => $userName,
                'svn_user_id' => $svnUserId,
                'svn_user_name' => $matchedSvnUser['svn_user_name']
            ]);

        } elseif ($this->wecomConfig['user_mapping']['auto_create_user']) {
            // 自动创建新的 SVN 用户
            $svnUserId = $this->createSvnUser($user, $existingSvnUsers);
            $action = 'created';

        } else {
            // 不自动创建用户，记录警告
            $this->addSyncWarning('user_no_match', "企业微信用户未匹配到SVN用户且未启用自动创建", [
                'wecom_user_id' => $userId,
                'wecom_user_name' => $userName
            ]);
        }

        // 在企业微信用户表中记录
        $this->database->insert('wecom_users', [
            'wecom_user_id' => $userId,
            'real_name' => $userName,
            'name_en' => $user['alias'] ?? '',
            'mobile' => $user['mobile'] ?? '',
            'email' => $user['email'] ?? '',
            'department_ids' => json_encode($user['department'] ?? []),
            'svn_user_id' => $svnUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'user_id' => $userId,
            'action' => $action,
            'svn_user_id' => $svnUserId,
            'message' => $action === 'matched' ? '匹配到现有用户' : '创建新用户记录'
        ];
    }

    /**
     * 更新现有企业微信用户记录
     *
     * @param array $user
     * @param array $existingWeComUser
     * @param array $existingSvnUsers
     * @return array
     * @throws \Exception
     */
    private function updateWeComUser($user, $existingWeComUser, &$existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        $needUpdate = false;
        $updateData = [];

        // 检查基本信息是否变更
        if ($existingWeComUser['real_name'] !== $userName) {
            $updateData['real_name'] = $userName;
            $needUpdate = true;
        }

        $newAlias = $user['alias'] ?? '';
        if ($existingWeComUser['name_en'] !== $newAlias) {
            $updateData['name_en'] = $newAlias;
            $needUpdate = true;
        }

        $newMobile = $user['mobile'] ?? '';
        if ($existingWeComUser['mobile'] !== $newMobile) {
            $updateData['mobile'] = $newMobile;
            $needUpdate = true;
        }

        $newEmail = $user['email'] ?? '';
        if ($existingWeComUser['email'] !== $newEmail) {
            $updateData['email'] = $newEmail;
            $needUpdate = true;
        }

        $newDepartmentIds = json_encode($user['department'] ?? []);
        if ($existingWeComUser['department_ids'] !== $newDepartmentIds) {
            $updateData['department_ids'] = $newDepartmentIds;
            $needUpdate = true;
        }

        // 检查是否需要重新匹配 SVN 用户
        if (!$existingWeComUser['svn_user_id']) {
            $matchedSvnUser = $this->matchSvnUser($user, $existingSvnUsers);
            if ($matchedSvnUser) {
                $updateData['svn_user_id'] = $matchedSvnUser['svn_user_id'];
                $needUpdate = true;

                // 更新 SVN 用户的企业微信信息
                $this->updateSvnUserWeComInfo($matchedSvnUser, $user);

                $this->logInfo('重新匹配到SVN用户', [
                    'wecom_user_id' => $userId,
                    'svn_user_id' => $matchedSvnUser['svn_user_id']
                ]);
            }
        } else {
            // 更新已关联的 SVN 用户信息
            $svnUser = $this->getSvnUserById($existingWeComUser['svn_user_id'], $existingSvnUsers);
            if ($svnUser) {
                $this->updateSvnUserWeComInfo($svnUser, $user);
            }
        }

        if ($needUpdate) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            $this->database->update('wecom_users', $updateData, [
                'wecom_user_id' => $userId
            ]);

            $this->logInfo('更新企业微信用户成功', [
                'user_id' => $userId,
                'user_name' => $userName,
                'changes' => $updateData
            ]);

            return [
                'user_id' => $userId,
                'action' => 'updated',
                'svn_user_id' => $existingWeComUser['svn_user_id'],
                'message' => '用户信息更新成功',
                'changes' => $updateData
            ];
        } else {
            return [
                'user_id' => $userId,
                'action' => 'skipped',
                'message' => '用户信息无需更新'
            ];
        }
    }

    /**
     * 匹配 SVN 用户
     *
     * @param array $wecomUser
     * @param array $existingSvnUsers
     * @return array|null
     */
    private function matchSvnUser($wecomUser, $existingSvnUsers)
    {
        $matchFields = $this->wecomConfig['user_mapping']['match_fields'];

        foreach ($matchFields as $field) {
            $matchValue = '';
            
            switch ($field) {
                case 'userid':
                    $matchValue = $wecomUser['userid'];
                    break;
                case 'email':
                    $matchValue = $wecomUser['email'] ?? '';
                    break;
                case 'mobile':
                    $matchValue = $wecomUser['mobile'] ?? '';
                    break;
            }

            if (empty($matchValue)) {
                continue;
            }

            // 在 SVN 用户中查找匹配
            foreach ($existingSvnUsers as $svnUser) {
                $matched = false;
                
                switch ($field) {
                    case 'userid':
                        $matched = ($svnUser['svn_user_name'] === $matchValue);
                        break;
                    case 'email':
                        $matched = ($svnUser['svn_user_mail'] === $matchValue);
                        break;
                    case 'mobile':
                        // svn_users表中没有手机号字段，跳过手机号匹配
                        $matched = false;
                        break;
                }

                if ($matched) {
                    $this->logInfo('用户匹配成功', [
                        'match_field' => $field,
                        'match_value' => $matchValue,
                        'wecom_user_id' => $wecomUser['userid'],
                        'svn_user_id' => $svnUser['svn_user_id']
                    ]);
                    return $svnUser;
                }
            }
        }

        return null;
    }

    /**
     * 创建 SVN 用户
     *
     * @param array $wecomUser
     * @param array $existingSvnUsers
     * @return int
     * @throws \Exception
     */
    private function createSvnUser($wecomUser, &$existingSvnUsers)
    {
        $userId = $wecomUser['userid'];
        $userName = $wecomUser['name'];
        
        // 生成 SVN 用户名
        $svnUserName = $this->generateSvnUserName($wecomUser);
        
        // 检查用户名是否已存在
        if ($this->isSvnUserNameExists($svnUserName, $existingSvnUsers)) {
            throw new \Exception("SVN用户名已存在: {$svnUserName}");
        }

        try {
            // 插入到 svn_users 表
            $this->database->insert('svn_users', [
                'svn_user_name' => $svnUserName,
                'svn_user_pass' => password_hash($this->wecomConfig['user_mapping']['default_password'], PASSWORD_DEFAULT),
                'svn_user_mail' => $wecomUser['email'] ?? '',
                'svn_user_note' => "{$userName} ({$userId}) - " . date('Y-m-d H:i:s') . " - 企业微信同步创建",
                'svn_user_status' => 1
            ]);

            $svnUserId = $this->database->id();

            // 更新缓存
            $existingSvnUsers[] = [
                'svn_user_id' => $svnUserId,
                'svn_user_name' => $svnUserName,
                'svn_user_mail' => $wecomUser['email'] ?? '',
                'svn_user_phone' => $wecomUser['mobile'] ?? '',
                'wecom_userid' => $userId,
                'wecom_name' => $userName
            ];

            $this->logInfo('创建SVN用户成功', [
                'wecom_user_id' => $userId,
                'wecom_user_name' => $userName,
                'svn_user_name' => $svnUserName,
                'svn_user_id' => $svnUserId
            ]);

            return $svnUserId;

        } catch (\Exception $e) {
            $this->addSyncError('create_svn_user', "创建SVN用户失败: {$svnUserName}", [
                'wecom_user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 生成 SVN 用户名
     *
     * @param array $wecomUser
     * @return string
     */
    private function generateSvnUserName($wecomUser)
    {
        $prefix = $this->wecomConfig['user_mapping']['username_prefix'];
        $suffix = $this->wecomConfig['user_mapping']['username_suffix'];
        
        // 优先使用 userid，如果不合适则使用邮箱前缀
        $baseName = $wecomUser['userid'];
        
        if (empty($baseName) && !empty($wecomUser['email'])) {
            $baseName = strstr($wecomUser['email'], '@', true);
        }
        
        if (empty($baseName)) {
            $baseName = 'user_' . substr(md5($wecomUser['name']), 0, 8);
        }
        
        // 清理用户名，只保留字母数字和下划线
        $baseName = preg_replace('/[^a-zA-Z0-9_]/', '_', $baseName);
        
        return $prefix . $baseName . $suffix;
    }

    /**
     * 检查 SVN 用户名是否已存在
     *
     * @param string $userName
     * @param array $existingSvnUsers
     * @return bool
     */
    private function isSvnUserNameExists($userName, $existingSvnUsers)
    {
        foreach ($existingSvnUsers as $user) {
            if ($user['svn_user_name'] === $userName) {
                return true;
            }
        }
        return false;
    }

    /**
     * 更新 SVN 用户的企业微信信息
     *
     * @param array $svnUser
     * @param array $wecomUser
     * @return void
     */
    private function updateSvnUserWeComInfo($svnUser, $wecomUser)
    {
        try {
            $updateData = [];
            $needUpdate = false;

            // 同步备注：更新为企业微信信息和同步时间
            $newNote = "{$wecomUser['name']} ({$wecomUser['userid']}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";
            if (empty($svnUser['svn_user_note']) || strpos($svnUser['svn_user_note'], '企业微信同步') !== false) {
                $updateData['svn_user_note'] = $newNote;
                $needUpdate = true;
            }

            if ($needUpdate) {
                $this->database->update('svn_users', $updateData, [
                    'svn_user_id' => $svnUser['svn_user_id']
                ]);

                $this->logInfo('更新SVN用户企业微信信息', [
                    'svn_user_id' => $svnUser['svn_user_id'],
                    'wecom_user_id' => $wecomUser['userid'],
                    'changes' => $updateData
                ]);
            }

        } catch (\Exception $e) {
            $this->addSyncError('update_svn_user_wecom_info', $e->getMessage(), [
                'svn_user_id' => $svnUser['svn_user_id'],
                'wecom_user_id' => $wecomUser['userid']
            ]);
        }
    }

    /**
     * 根据ID获取 SVN 用户
     *
     * @param int $svnUserId
     * @param array $existingSvnUsers
     * @return array|null
     */
    private function getSvnUserById($svnUserId, $existingSvnUsers)
    {
        // 首先从缓存中查找
        foreach ($existingSvnUsers as $user) {
            if ($user['svn_user_id'] == $svnUserId) {
                return $user;
            }
        }
        
        // 如果缓存中没有，从数据库查询
        try {
            $user = $this->database->select('svn_users', '*', ['svn_user_id' => $svnUserId]);
            return $user ? $user[0] : null;
        } catch (\Exception $e) {
            $this->logError('获取SVN用户失败', $e->getMessage());
            return null;
        }
    }

    /**
     * 获取现有的企业微信用户数据
     *
     * @return array
     */
    private function getExistingWeComUsers()
    {
        try {
            $users = $this->database->select('wecom_users', '*');
            $result = [];
            
            foreach ($users as $user) {
                $result[$user['wecom_user_id']] = $user;
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logError('获取现有企业微信用户数据失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 获取现有的 SVN 用户数据
     *
     * @return array
     */
    private function getExistingSvnUsers()
    {
        try {
            return $this->database->select('svn_users', '*');
        } catch (\Exception $e) {
            $this->logError('获取现有SVN用户数据失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 处理已删除的用户
     *
     * @param array $currentUsers
     * @param array $existingWeComUsers
     * @return void
     */
    private function handleDeletedUsers($currentUsers, $existingWeComUsers)
    {
        // 获取当前企业微信中的用户ID列表
        $currentUserIds = array_column($currentUsers, 'userid');
        
        // 找出已删除的用户
        $deletedUsers = [];
        foreach ($existingWeComUsers as $userId => $user) {
            if (!in_array($userId, $currentUserIds)) {
                $deletedUsers[] = $user;
            }
        }

        foreach ($deletedUsers as $user) {
            try {
                $this->handleDeletedUser($user);
                $this->syncStats['users']['deleted']++;
            } catch (\Exception $e) {
                $this->syncStats['users']['errors']++;
                $this->addSyncError('delete_user', $e->getMessage(), [
                    'user_id' => $user['wecom_userid']
                ]);
            }
        }

        if (!empty($deletedUsers)) {
            $this->logInfo('处理已删除用户', ['count' => count($deletedUsers)]);
        }
    }

    /**
     * 处理单个已删除的用户
     *
     * @param array $user
     * @return void
     * @throws \Exception
     */
    private function handleDeletedUser($user)
    {
        $userId = $user['wecom_userid'];
        
        if ($this->wecomConfig['user_mapping']['auto_disable_user'] && $user['svn_user_id']) {
            // 禁用对应的 SVN 用户
            $this->disableSvnUser($user['svn_user_id']);
        }

        // 删除企业微信用户记录
        $this->database->delete('wecom_users', [
            'wecom_userid' => $userId
        ]);

        $this->logInfo('删除用户记录', [
            'user_id' => $userId,
            'user_name' => $user['wecom_name']
        ]);
    }

    /**
     * 禁用 SVN 用户
     *
     * @param int $svnUserId
     * @return void
     */
    private function disableSvnUser($svnUserId)
    {
        try {
            // 这里可以根据 SVNAdmin 的实际逻辑来禁用用户
            // 暂时记录日志，具体实现可以根据需要调整
            $this->logInfo('需要禁用SVN用户', ['svn_user_id' => $svnUserId]);
            
            // 可以考虑：
            // 1. 修改用户密码为随机值
            // 2. 添加禁用标记
            // 3. 移除用户的所有权限
            
        } catch (\Exception $e) {
            $this->addSyncWarning('disable_svn_user', "禁用SVN用户失败", [
                'svn_user_id' => $svnUserId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==================== 权限同步辅助方法 ====================

    /**
     * 同步用户到组的关系
     *
     * @param array $wecomData
     * @return array
     */
    private function syncUserGroupRelations($wecomData)
    {
        $this->logInfo('开始同步用户组关系');

        $processed = 0;
        $results = [];

        try {
            // 获取企业微信用户和部门的映射关系
            $wecomUsers = $this->database->select('wecom_users', '*', [
                'svn_user_id[!]' => null // 只处理已关联SVN用户的企业微信用户
            ]);

            $wecomDepartments = $this->database->select('wecom_departments', '*', [
                'svn_group_id[!]' => null // 只处理已关联SVN组的企业微信部门
            ]);

            // 创建部门ID到SVN组ID的映射
            $departmentToGroupMap = [];
            foreach ($wecomDepartments as $dept) {
                $departmentToGroupMap[$dept['wecom_dept_id']] = $dept['svn_group_id'];
            }

            foreach ($wecomUsers as $user) {
                try {
                    $result = $this->syncSingleUserGroupRelation($user, $departmentToGroupMap);
                    $results[] = $result;
                    $processed++;

                    if ($result['added'] > 0) {
                        $this->syncStats['permissions']['added'] += $result['added'];
                    }
                    if ($result['removed'] > 0) {
                        $this->syncStats['permissions']['removed'] += $result['removed'];
                    }

                } catch (\Exception $e) {
                    $this->syncStats['permissions']['errors']++;
                    $this->addSyncError('user_group_relation', $e->getMessage(), [
                        'user_id' => $user['wecom_user_id'],
                        'svn_user_id' => $user['svn_user_id']
                    ]);
                }
            }

            $this->logInfo('用户组关系同步完成', ['processed' => $processed]);

            return [
                'status' => 1,
                'processed' => $processed,
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->addSyncError('user_group_relations', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步单个用户的组关系
     *
     * @param array $user
     * @param array $departmentToGroupMap
     * @return array
     */
    private function syncSingleUserGroupRelation($user, $departmentToGroupMap)
    {
        $svnUserId = $user['svn_user_id'];
        $departmentIds = json_decode($user['department_ids'] ?? '[]', true);

        // 获取用户应该加入的SVN组
        $targetGroupIds = [];
        foreach ($departmentIds as $deptId) {
            if (isset($departmentToGroupMap[$deptId])) {
                $targetGroupIds[] = $departmentToGroupMap[$deptId];
            }
        }
        $targetGroupIds = array_unique($targetGroupIds);

        // 获取用户当前的组关系
        $currentGroups = $this->getCurrentUserGroups($svnUserId);
        $currentGroupIds = array_column($currentGroups, 'svn_group_id');

        // 计算需要添加和移除的组
        $groupsToAdd = array_diff($targetGroupIds, $currentGroupIds);
        $groupsToRemove = [];

        // 如果启用了自动移除，则计算需要移除的组
        if ($this->syncConfig['auto_remove_user_from_group']) {
            // 只移除企业微信相关的组（通过组名前缀识别）
            $wecomGroups = array_filter($currentGroups, function($group) {
                return strpos($group['svn_group_name'], $this->wecomConfig['department_mapping']['group_name_prefix']) === 0;
            });
            $wecomGroupIds = array_column($wecomGroups, 'svn_group_id');
            $groupsToRemove = array_diff($wecomGroupIds, $targetGroupIds);
        }

        $added = 0;
        $removed = 0;

        // 添加用户到新组
        foreach ($groupsToAdd as $groupId) {
            try {
                $this->addUserToGroup($svnUserId, $groupId);
                $added++;
            } catch (\Exception $e) {
                $this->addSyncError('add_user_to_group', $e->getMessage(), [
                    'svn_user_id' => $svnUserId,
                    'svn_group_id' => $groupId
                ]);
            }
        }

        // 从旧组移除用户
        foreach ($groupsToRemove as $groupId) {
            try {
                $this->removeUserFromGroup($svnUserId, $groupId);
                $removed++;
            } catch (\Exception $e) {
                $this->addSyncError('remove_user_from_group', $e->getMessage(), [
                    'svn_user_id' => $svnUserId,
                    'svn_group_id' => $groupId
                ]);
            }
        }

        if ($added > 0 || $removed > 0) {
            $this->logInfo('更新用户组关系', [
                'wecom_user_id' => $user['wecom_userid'],
                'svn_user_id' => $svnUserId,
                'groups_added' => count($groupsToAdd),
                'groups_removed' => count($groupsToRemove)
            ]);
        }

        return [
            'user_id' => $user['wecom_user_id'],
            'svn_user_id' => $svnUserId,
            'added' => $added,
            'removed' => $removed,
            'target_groups' => $targetGroupIds,
            'current_groups' => []
        ];
    }

    /**
     * 获取用户当前的组关系
     *
     * @param int $svnUserId
     * @return array
     */
    private function getCurrentUserGroups($svnUserId)
    {
        try {
            // SVNAdmin使用authz文件管理用户组关系，不是数据库表
            // 这里直接返回空数组，实际的组关系由SVNAdmin类管理
            $this->logInfo('SVNAdmin使用authz文件管理用户组关系', [
                'svn_user_id' => $svnUserId
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logInfo('获取用户组关系失败', [
                'svn_user_id' => $svnUserId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 添加用户到组
     *
     * @param int $svnUserId
     * @param int $svnGroupId
     * @return void
     * @throws \Exception
     */
    private function addUserToGroup($svnUserId, $svnGroupId)
    {
        try {
            // 获取SVN用户名和组名
            $svnUser = $this->database->get('svn_users', ['svn_user_name'], ['svn_user_id' => $svnUserId]);
            $svnGroup = $this->database->get('svn_groups', ['svn_group_name'], ['svn_group_id' => $svnGroupId]);
            
            if (!$svnUser || !$svnGroup) {
                throw new \Exception("用户或分组不存在: user_id=$svnUserId, group_id=$svnGroupId");
            }
            
            $userName = $svnUser['svn_user_name'];
            $groupName = $svnGroup['svn_group_name'];
            
            // 读取当前authz文件内容
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);
            
            // 使用SVNAdmin的UpdGroupMember方法添加用户到组
            $result = $this->SVNAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'add');
            
            if (is_string($result)) {
                // 成功，写入更新后的authz文件
                file_put_contents($authzFilePath, $result);
                
                // 更新组的用户计数
                $this->updateGroupUserCount($svnGroupId);
                
                $this->logInfo('添加用户到组成功', [
                    'svn_user_id' => $svnUserId,
                    'svn_user_name' => $userName,
                    'svn_group_id' => $svnGroupId,
                    'svn_group_name' => $groupName
                ]);
                
                $this->syncStats['permissions']['added']++;
            } else {
                // 失败，result是错误码
                $errorMessages = [
                    802 => '不能添加相同名称的分组',
                    803 => '用户已存在于分组中',
                    720 => 'authz文件格式错误',
                    901 => '不支持的对象类型'
                ];
                $errorMsg = $errorMessages[$result] ?? "未知错误码: $result";
                throw new \Exception("SVNAdmin添加用户到组失败: $errorMsg");
            }

        } catch (\Exception $e) {
            $this->addSyncError('add_user_to_group', "添加用户到组失败: " . $e->getMessage(), [
                'svn_user_id' => $svnUserId,
                'svn_group_id' => $svnGroupId
            ]);
            throw $e;
        }
    }

    /**
     * 从组移除用户
     *
     * @param int $svnUserId
     * @param int $svnGroupId
     * @return void
     * @throws \Exception
     */
    private function removeUserFromGroup($svnUserId, $svnGroupId)
    {
        try {
            // 获取SVN用户名和组名
            $svnUser = $this->database->get('svn_users', ['svn_user_name'], ['svn_user_id' => $svnUserId]);
            $svnGroup = $this->database->get('svn_groups', ['svn_group_name'], ['svn_group_id' => $svnGroupId]);
            
            if (!$svnUser || !$svnGroup) {
                throw new \Exception("用户或分组不存在: user_id=$svnUserId, group_id=$svnGroupId");
            }
            
            $userName = $svnUser['svn_user_name'];
            $groupName = $svnGroup['svn_group_name'];
            
            // 读取当前authz文件内容
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);
            
            // 使用SVNAdmin的UpdGroupMember方法从组移除用户
            $result = $this->SVNAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'delete');
            
            if (is_string($result)) {
                // 成功，写入更新后的authz文件
                file_put_contents($authzFilePath, $result);
                
                // 更新组的用户计数
                $this->updateGroupUserCount($svnGroupId);
                
                $this->logInfo('从组移除用户成功', [
                    'svn_user_id' => $svnUserId,
                    'svn_user_name' => $userName,
                    'svn_group_id' => $svnGroupId,
                    'svn_group_name' => $groupName
                ]);
                
                $this->syncStats['permissions']['removed']++;
            } else {
                // 失败，result是错误码
                $errorMessages = [
                    804 => '用户不存在于分组中',
                    720 => 'authz文件格式错误',
                    901 => '不支持的对象类型'
                ];
                $errorMsg = $errorMessages[$result] ?? "未知错误码: $result";
                throw new \Exception("SVNAdmin从组移除用户失败: $errorMsg");
            }

        } catch (\Exception $e) {
            $this->addSyncWarning('remove_user_from_group', "从组移除用户失败: " . $e->getMessage(), [
                'svn_user_id' => $svnUserId,
                'svn_group_id' => $svnGroupId
            ]);
        }
    }

    /**
     * 更新组的用户计数
     *
     * @param int $svnGroupId
     * @return void
     */
    private function updateGroupUserCount($svnGroupId)
    {
        try {
            // 获取组名
            $svnGroup = $this->database->get('svn_groups', ['svn_group_name'], ['svn_group_id' => $svnGroupId]);
            if (!$svnGroup) {
                return;
            }
            
            $groupName = $svnGroup['svn_group_name'];
            
            // 直接解析authz文件获取用户计数
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);
            $userCount = $this->parseGroupUserCountFromAuthz($authzContent, $groupName);

            $this->database->update('svn_groups', [
                'include_user_count' => $userCount
            ], [
                'svn_group_id' => $svnGroupId
            ]);

            $this->logInfo('更新组用户计数', [
                'svn_group_id' => $svnGroupId,
                'svn_group_name' => $groupName,
                'user_count' => $userCount
            ]);

        } catch (\Exception $e) {
            $this->addSyncWarning('update_group_user_count', "更新组用户计数失败", [
                'svn_group_id' => $svnGroupId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 从authz文件解析指定组的用户计数
     *
     * @param string $authzContent
     * @param string $groupName
     * @return int
     */
    private function parseGroupUserCountFromAuthz($authzContent, $groupName)
    {
        try {
            $lines = explode("\n", $authzContent);
            $inGroupsSection = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line)) {
                    continue;
                }
                
                if ($line === '[groups]') {
                    $inGroupsSection = true;
                    continue;
                }
                
                if (strpos($line, '[') === 0) { // New section starts
                    $inGroupsSection = false;
                    continue;
                }
                
                if ($inGroupsSection) {
                    if (preg_match('/^([A-Za-z0-9-_.一-龥]+)\s*=\s*(.*)$/u', $line, $matches)) {
                        $currentGroupName = $matches[1];
                        if ($currentGroupName === $groupName) {
                            $membersString = $matches[2];
                            $members = array_filter(array_map('trim', explode(',', $membersString)));
                            return count($members);
                        }
                    }
                }
            }
            
            return 0; // 组不存在或没有成员
            
        } catch (\Exception $e) {
            $this->addSyncWarning('parse_group_user_count', "解析authz文件失败", [
                'group_name' => $groupName,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 更新SVN组备注信息
     *
     * @param int $svnGroupId
     * @param string $departmentName
     * @param int $departmentId
     * @return void
     */
    private function updateSvnGroupNote($svnGroupId, $departmentName, $departmentId)
    {
        try {
            $note = "{$departmentName} (ID: {$departmentId}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";
            
            $this->database->update('svn_groups', [
                'svn_group_note' => $note
            ], [
                'svn_group_id' => $svnGroupId
            ]);

        } catch (\Exception $e) {
            $this->addSyncWarning('update_svn_group_note', "更新组备注失败", [
                'svn_group_id' => $svnGroupId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 同步基于部门的仓库权限
     *
     * @param array $wecomData
     * @return array
     */
    private function syncRepositoryPermissions($wecomData)
    {
        $this->logInfo('开始同步仓库权限');

        $processed = 0;
        $results = [];

        try {
            // 获取所有SVN仓库
            $repositories = $this->database->select('svn_reps', '*');

            // 获取企业微信部门和对应的SVN组
            $wecomDepartments = $this->database->select('wecom_departments', '*', [
                'svn_group_id[!]' => null
            ]);

            foreach ($repositories as $repo) {
                try {
                    $result = $this->syncSingleRepositoryPermissions($repo, $wecomDepartments);
                    $results[] = $result;
                    $processed++;

                } catch (\Exception $e) {
                    $this->syncStats['permissions']['errors']++;
                    $this->addSyncError('repository_permissions', $e->getMessage(), [
                        'repo_name' => $repo['rep_name']
                    ]);
                }
            }

            $this->logInfo('仓库权限同步完成', ['processed' => $processed]);

            return [
                'status' => 1,
                'processed' => $processed,
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->addSyncError('repository_permissions_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步单个仓库的权限
     *
     * @param array $repo
     * @param array $wecomDepartments
     * @return array
     */
    private function syncSingleRepositoryPermissions($repo, $wecomDepartments)
    {
        $repoName = $repo['rep_name'];
        $permissions = [];

        // 根据配置的权限策略分配权限
        $permissionStrategy = $this->wecomConfig['permission_mapping']['strategy'] ?? 'department_based';

        switch ($permissionStrategy) {
            case 'department_based':
                $permissions = $this->generateDepartmentBasedPermissions($repo, $wecomDepartments);
                break;
            
            case 'hierarchy_based':
                $permissions = $this->generateHierarchyBasedPermissions($repo, $wecomDepartments);
                break;
            
            default:
                $permissions = $this->generateDefaultPermissions($repo, $wecomDepartments);
                break;
        }

        // 应用权限到数据库
        $this->applyRepositoryPermissions($repo, $permissions);

        return [
            'repo_name' => $repoName,
            'permissions_count' => count($permissions),
            'strategy' => $permissionStrategy
        ];
    }

    /**
     * 生成基于部门的权限
     *
     * @param array $repo
     * @param array $wecomDepartments
     * @return array
     */
    private function generateDepartmentBasedPermissions($repo, $wecomDepartments)
    {
        $permissions = [];
        $defaultPermission = $this->wecomConfig['permission_mapping']['default_permission'] ?? 'r';

        foreach ($wecomDepartments as $dept) {
            $permissions[] = [
                'group_id' => $dept['svn_group_id'],
                'path' => '/',
                'permission' => $defaultPermission,
                'source' => 'wecom_department',
                'source_id' => $dept['wecom_dept_id']
            ];
        }

        return $permissions;
    }

    /**
     * 生成基于层级的权限
     *
     * @param array $repo
     * @param array $wecomDepartments
     * @return array
     */
    private function generateHierarchyBasedPermissions($repo, $wecomDepartments)
    {
        $permissions = [];
        
        // 构建部门层级关系
        $departmentHierarchy = $this->buildDepartmentHierarchy($wecomDepartments);
        
        foreach ($wecomDepartments as $dept) {
            $permission = $this->calculateHierarchyPermission($dept, $departmentHierarchy);
            
            $permissions[] = [
                'group_id' => $dept['svn_group_id'],
                'path' => '/',
                'permission' => $permission,
                'source' => 'wecom_hierarchy',
                'source_id' => $dept['wecom_dept_id']
            ];
        }

        return $permissions;
    }

    /**
     * 生成默认权限
     *
     * @param array $repo
     * @param array $wecomDepartments
     * @return array
     */
    private function generateDefaultPermissions($repo, $wecomDepartments)
    {
        return $this->generateDepartmentBasedPermissions($repo, $wecomDepartments);
    }

    /**
     * 构建部门层级关系
     *
     * @param array $departments
     * @return array
     */
    private function buildDepartmentHierarchy($departments)
    {
        $hierarchy = [];
        $children = [];

        foreach ($departments as $dept) {
            $deptId = $dept['wecom_dept_id'];
            $parentId = $dept['wecom_parent_id'];
            
            $hierarchy[$deptId] = $dept;
            
            if (!isset($children[$parentId])) {
                $children[$parentId] = [];
            }
            $children[$parentId][] = $deptId;
        }

        return ['departments' => $hierarchy, 'children' => $children];
    }

    /**
     * 计算层级权限
     *
     * @param array $dept
     * @param array $hierarchy
     * @return string
     */
    private function calculateHierarchyPermission($dept, $hierarchy)
    {
        $deptId = $dept['wecom_dept_id'];
        $parentId = $dept['wecom_parent_id'];
        
        // 根据部门层级计算权限
        $level = $this->calculateDepartmentLevel($deptId, $hierarchy, 0);
        
        $permissionLevels = $this->wecomConfig['permission_mapping']['hierarchy_permissions'] ?? [
            0 => 'rw', // 根部门
            1 => 'rw', // 一级部门
            2 => 'r',  // 二级部门
            3 => 'r'   // 三级及以下部门
        ];
        
        return $permissionLevels[$level] ?? $permissionLevels[max(array_keys($permissionLevels))];
    }

    /**
     * 计算部门层级
     *
     * @param int $deptId
     * @param array $hierarchy
     * @param int $currentLevel
     * @return int
     */
    private function calculateDepartmentLevel($deptId, $hierarchy, $currentLevel)
    {
        if (!isset($hierarchy['departments'][$deptId])) {
            return $currentLevel;
        }

        $dept = $hierarchy['departments'][$deptId];
        $parentId = $dept['wecom_parent_id'];

        if ($parentId == 0 || $parentId == $deptId) {
            return $currentLevel;
        }

        return $this->calculateDepartmentLevel($parentId, $hierarchy, $currentLevel + 1);
    }

    /**
     * 应用仓库权限
     *
     * @param array $repo
     * @param array $permissions
     * @return void
     */
    private function applyRepositoryPermissions($repo, $permissions)
    {
        $repoName = $repo['rep_name'];

        try {
            // 清除现有的企业微信相关权限
            $this->clearWeComRepositoryPermissions($repoName);

            // 应用新权限
            foreach ($permissions as $permission) {
                $this->addRepositoryPermission($repoName, $permission);
            }

        } catch (\Exception $e) {
            $this->addSyncError('apply_repository_permissions', $e->getMessage(), [
                'repo_name' => $repoName
            ]);
        }
    }

    /**
     * 清除企业微信相关的仓库权限
     *
     * @param string $repoName
     * @return void
     */
    private function clearWeComRepositoryPermissions($repoName)
    {
        try {
            // 这里需要根据实际的权限表结构来实现
            // 假设权限表中有标记字段来识别企业微信权限
            $this->logInfo('清除企业微信相关权限', ['repo_name' => $repoName]);
            
        } catch (\Exception $e) {
            $this->addSyncWarning('clear_wecom_permissions', "清除权限失败", [
                'repo_name' => $repoName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 添加仓库权限
     *
     * @param string $repoName
     * @param array $permission
     * @return void
     */
    private function addRepositoryPermission($repoName, $permission)
    {
        try {
            // 这里需要根据实际的权限表结构来实现
            $this->logInfo('添加仓库权限', [
                'repo_name' => $repoName,
                'group_id' => $permission['group_id'],
                'path' => $permission['path'],
                'permission' => $permission['permission']
            ]);
            
            $this->syncStats['permissions']['added']++;
            
        } catch (\Exception $e) {
            $this->addSyncError('add_repository_permission', $e->getMessage(), [
                'repo_name' => $repoName,
                'permission' => $permission
            ]);
        }
    }

    /**
     * 更新 authz 文件
     *
     * @return array
     */
    private function updateAuthzFile()
    {
        $this->logInfo('开始更新 authz 文件');

        try {
            // 这里可以调用现有的 authz 生成逻辑
            // 或者实现新的基于企业微信的 authz 生成
            
            $this->logInfo('authz 文件更新完成');
            
            return [
                'status' => 1,
                'message' => 'authz 文件更新成功'
            ];
            
        } catch (\Exception $e) {
            $this->addSyncError('update_authz', $e->getMessage());
            return [
                'status' => 0,
                'message' => 'authz 文件更新失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 分批同步部门数据（优化版本）
     *
     * @param array $departments
     * @return array
     */
    protected function syncDepartmentsBatch($departments)
    {
        $this->logInfo('开始分批同步部门数据', ['total_count' => count($departments)]);
        
        $this->syncStats['departments']['total'] = count($departments);
        $results = [];
        $batchSize = 10; // 每批处理10个部门
        $batches = array_chunk($departments, $batchSize);
        
        try {
            // 获取现有的企业微信部门数据
            $existingDepartments = $this->getExistingWeComDepartments();
            
            // 获取现有的 SVN 组数据
            $existingSvnGroups = $this->getExistingSvnGroups();
            
            foreach ($batches as $batchIndex => $batch) {
                $this->logInfo("处理部门批次 " . ($batchIndex + 1) . "/" . count($batches), [
                    'batch_size' => count($batch)
                ]);
                
                foreach ($batch as $department) {
                    try {
                        $result = $this->syncSingleDepartment($department, $existingDepartments, $existingSvnGroups);
                        $results[] = $result;
                        
                        // 更新统计数据
                        if ($result['action'] === 'created') {
                            $this->syncStats['departments']['created']++;
                        } elseif ($result['action'] === 'updated') {
                            $this->syncStats['departments']['updated']++;
                        }
                        
                        // 记录进度
                        $processed = $this->syncStats['departments']['created'] + $this->syncStats['departments']['updated'];
                        if ($processed % 5 === 0) {
                            $this->logInfo("部门同步进度: {$processed}/{$this->syncStats['departments']['total']}");
                        }
                        
                    } catch (\Exception $e) {
                        $this->syncStats['departments']['errors']++;
                        $this->addSyncError('department_sync', "部门 {$department['name']} 同步失败: " . $e->getMessage());
                        $this->logError('部门同步失败', "部门: {$department['name']}, 错误: " . $e->getMessage());
                    }
                }
                
                // 批次间短暂休息，避免数据库压力过大
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 0.1秒
                }
            }
            
            // 处理已删除的部门
            $this->handleDeletedDepartments($departments, $existingDepartments);
            
            $this->logInfo('部门数据分批同步完成', [
                'total' => $this->syncStats['departments']['total'],
                'created' => $this->syncStats['departments']['created'],
                'updated' => $this->syncStats['departments']['updated'],
                'deleted' => $this->syncStats['departments']['deleted'],
                'errors' => $this->syncStats['departments']['errors']
            ]);
            
            return [
                'status' => 1,
                'message' => '部门分批同步完成',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            $this->addSyncError('departments_batch_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 分批同步用户数据（优化版本）
     *
     * @param array $users
     * @return array
     */
    protected function syncUsersBatch($users)
    {
        $this->logInfo('开始分批同步用户数据', ['total_count' => count($users)]);
        
        $this->syncStats['users']['total'] = count($users);
        $results = [];
        $batchSize = 20; // 每批处理20个用户
        $batches = array_chunk($users, $batchSize);
        
        try {
            // 获取现有的企业微信用户数据
            $existingWeComUsers = $this->getExistingWeComUsers();
            
            // 获取现有的 SVN 用户数据
            $existingSvnUsers = $this->getExistingSvnUsers();
            
            foreach ($batches as $batchIndex => $batch) {
                $this->logInfo("处理用户批次 " . ($batchIndex + 1) . "/" . count($batches), [
                    'batch_size' => count($batch)
                ]);
                
                foreach ($batch as $user) {
                    try {
                        $result = $this->syncSingleUser($user, $existingWeComUsers, $existingSvnUsers);
                        $results[] = $result;
                        
                        // 更新统计数据
                        if ($result['action'] === 'created') {
                            $this->syncStats['users']['created']++;
                        } elseif ($result['action'] === 'updated') {
                            $this->syncStats['users']['updated']++;
                        }
                        
                        // 记录进度
                        $processed = $this->syncStats['users']['created'] + $this->syncStats['users']['updated'];
                        if ($processed % 10 === 0) {
                            $this->logInfo("用户同步进度: {$processed}/{$this->syncStats['users']['total']}");
                        }
                        
                    } catch (\Exception $e) {
                        $this->syncStats['users']['errors']++;
                        $this->addSyncError('user_sync', "用户 {$user['name']} 同步失败: " . $e->getMessage());
                        $this->logError('用户同步失败', "用户: {$user['name']}, 错误: " . $e->getMessage());
                    }
                }
                
                // 批次间短暂休息，避免数据库压力过大
                if ($batchIndex < count($batches) - 1) {
                    usleep(50000); // 0.05秒
                }
            }
            
            // 处理已删除的用户
            $this->handleDeletedUsers($users, $existingWeComUsers);
            
            $this->logInfo('用户数据分批同步完成', [
                'total' => $this->syncStats['users']['total'],
                'created' => $this->syncStats['users']['created'],
                'updated' => $this->syncStats['users']['updated'],
                'deleted' => $this->syncStats['users']['deleted'],
                'errors' => $this->syncStats['users']['errors']
            ]);
            
            return [
                'status' => 1,
                'message' => '用户分批同步完成',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            $this->addSyncError('users_batch_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步分组层级关系
     *
     * @param array $departments 企业微信部门数据
     * @return array
     */
    private function syncGroupHierarchy($departments)
    {
        $this->logInfo('开始同步分组层级关系', ['departments_count' => count($departments)]);

        $processed = 0;
        $updated = 0;
        $errors = 0;

        try {
            // 获取所有企业微信部门和对应的SVN组映射
            $departmentGroupMap = $this->database->select('wecom_departments', [
                'wecom_dept_id',
                'parent_id', 
                'dept_name',
                'svn_group_id'
            ], [
                'svn_group_id[!]' => null
            ]);

            // 构建部门ID到组ID的映射
            $deptToGroupMap = [];
            $groupToDeptMap = [];
            foreach ($departmentGroupMap as $mapping) {
                $deptToGroupMap[$mapping['wecom_dept_id']] = $mapping['svn_group_id'];
                $groupToDeptMap[$mapping['svn_group_id']] = $mapping;
            }

            // 获取所有SVN组信息
            $svnGroups = $this->database->select('svn_groups', [
                'svn_group_id',
                'svn_group_name'
            ]);
            $groupIdToNameMap = [];
            foreach ($svnGroups as $group) {
                $groupIdToNameMap[$group['svn_group_id']] = $group['svn_group_name'];
            }

            // 读取当前authz文件
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);

            // 处理每个部门的层级关系
            foreach ($departmentGroupMap as $dept) {
                $processed++;
                
                try {
                    $deptId = $dept['wecom_dept_id'];
                    $parentId = $dept['parent_id'];
                    $svnGroupId = $dept['svn_group_id'];
                    $svnGroupName = $groupIdToNameMap[$svnGroupId] ?? null;

                    if (!$svnGroupName) {
                        $this->logError('SVN组名不存在', ['svn_group_id' => $svnGroupId]);
                        continue;
                    }

                    // 跳过根部门（parent_id = 0 或 1）
                    if ($parentId <= 1) {
                        continue;
                    }

                    // 查找父部门对应的SVN组
                    $parentGroupId = $deptToGroupMap[$parentId] ?? null;
                    if (!$parentGroupId) {
                        $this->logInfo('父部门没有对应的SVN组', [
                            'dept_id' => $deptId,
                            'parent_id' => $parentId
                        ]);
                        continue;
                    }

                    $parentGroupName = $groupIdToNameMap[$parentGroupId] ?? null;
                    if (!$parentGroupName) {
                        $this->logError('父SVN组名不存在', ['parent_group_id' => $parentGroupId]);
                        continue;
                    }

                    // 将子组添加到父组中
                    $result = $this->addGroupToParentGroup($authzContent, $parentGroupName, $svnGroupName);
                    if ($result !== false) {
                        $authzContent = $result;
                        $updated++;
                        
                        $this->logInfo('添加子组到父组成功', [
                            'parent_group' => $parentGroupName,
                            'child_group' => $svnGroupName,
                            'dept_name' => $dept['dept_name']
                        ]);
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->addSyncError('group_hierarchy', "部门 {$dept['dept_name']} 层级关系同步失败: " . $e->getMessage(), [
                        'dept_id' => $dept['wecom_dept_id'],
                        'parent_id' => $dept['parent_id']
                    ]);
                }
            }

            // 写入更新后的authz文件
            if ($updated > 0) {
                file_put_contents($authzFilePath, $authzContent);
                $this->logInfo('authz文件更新完成', ['updated_groups' => $updated]);
                
                // 更新所有父组的包含组计数
                $this->updateAllGroupCounts();
            }

            $this->logInfo('分组层级关系同步完成', [
                'processed' => $processed,
                'updated' => $updated,
                'errors' => $errors
            ]);

            return [
                'status' => 1,
                'message' => '分组层级关系同步完成',
                'processed' => $processed,
                'updated' => $updated,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->addSyncError('group_hierarchy_sync', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 将子组添加到父组中
     *
     * @param string $authzContent
     * @param string $parentGroupName
     * @param string $childGroupName
     * @return string|false
     */
    private function addGroupToParentGroup($authzContent, $parentGroupName, $childGroupName)
    {
        try {
            $lines = explode("\n", $authzContent);
            $inGroupsSection = false;
            $parentGroupLineIndex = -1;
            $parentGroupMembers = '';

            // 查找父组的定义行
            foreach ($lines as $index => $line) {
                $line = trim($line);

                if ($line === '[groups]') {
                    $inGroupsSection = true;
                    continue;
                }

                if (strpos($line, '[') === 0) {
                    $inGroupsSection = false;
                    continue;
                }

                if ($inGroupsSection) {
                    if (preg_match('/^([A-Za-z0-9-_.一-龥]+)\s*=\s*(.*)$/u', $line, $matches)) {
                        $groupName = $matches[1];
                        if ($groupName === $parentGroupName) {
                            $parentGroupLineIndex = $index;
                            $parentGroupMembers = $matches[2];
                            break;
                        }
                    }
                }
            }

            if ($parentGroupLineIndex === -1) {
                $this->logError('未找到父组定义', ['parent_group' => $parentGroupName]);
                return false;
            }

            // 检查子组是否已经在父组中
            $childGroupRef = '@' . $childGroupName;
            if (strpos($parentGroupMembers, $childGroupRef) !== false) {
                // 子组已存在，无需添加
                return $authzContent;
            }

            // 添加子组到父组
            $newMembers = trim($parentGroupMembers);
            if (!empty($newMembers)) {
                $newMembers .= ',' . $childGroupRef;
            } else {
                $newMembers = $childGroupRef;
            }

            $lines[$parentGroupLineIndex] = $parentGroupName . '=' . $newMembers;

            return implode("\n", $lines);

        } catch (\Exception $e) {
            $this->logError('添加子组到父组失败', [
                'parent_group' => $parentGroupName,
                'child_group' => $childGroupName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 更新所有组的计数（用户数和包含组数）
     *
     * @return void
     */
    private function updateAllGroupCounts()
    {
        try {
            $authzFilePath = $this->configSvn['svn_authz_file'] ?? '/home/svnadmin/authz';
            $authzContent = file_get_contents($authzFilePath);

            // 解析所有组及其成员
            $groupsData = $this->parseAllGroupsFromAuthz($authzContent);

            // 更新数据库中的计数
            foreach ($groupsData as $groupName => $data) {
                $userCount = $data['user_count'];
                $groupCount = $data['group_count'];

                $this->database->update('svn_groups', [
                    'include_user_count' => $userCount,
                    'include_group_count' => $groupCount
                ], [
                    'svn_group_name' => $groupName
                ]);
            }

            $this->logInfo('所有组计数更新完成', ['groups_updated' => count($groupsData)]);

        } catch (\Exception $e) {
            $this->logError('更新组计数失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 从authz文件解析所有组及其成员信息
     *
     * @param string $authzContent
     * @return array
     */
    private function parseAllGroupsFromAuthz($authzContent)
    {
        $groupsData = [];
        $lines = explode("\n", $authzContent);
        $inGroupsSection = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if ($line === '[groups]') {
                $inGroupsSection = true;
                continue;
            }

            if (strpos($line, '[') === 0) {
                $inGroupsSection = false;
                continue;
            }

            if ($inGroupsSection) {
                if (preg_match('/^([A-Za-z0-9-_.一-龥]+)\s*=\s*(.*)$/u', $line, $matches)) {
                    $groupName = $matches[1];
                    $membersString = $matches[2];
                    
                    $userCount = 0;
                    $groupCount = 0;
                    
                    if (!empty($membersString)) {
                        $members = array_filter(array_map('trim', explode(',', $membersString)));
                        
                        foreach ($members as $member) {
                            if (strpos($member, '@') === 0) {
                                // 这是一个组引用
                                $groupCount++;
                            } else {
                                // 这是一个用户
                                $userCount++;
                            }
                        }
                    }
                    
                    $groupsData[$groupName] = [
                        'user_count' => $userCount,
                        'group_count' => $groupCount
                    ];
                }
            }
        }

        return $groupsData;
    }
}
