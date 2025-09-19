<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
use app\service\Logs as ServiceLogs;

/**
 * 企业微信数据获取器
 * 
 * 负责从企业微信API获取组织架构数据，包括：
 * - 部门信息获取
 * - 用户信息获取
 * - 全量/增量同步数据获取
 * - API访问令牌管理
 */
class WeComDataFetcher extends Base
{
    /**
     * 企业微信API实例
     * @var \app\service\WeComAPI
     */
    private $WeComAPI;

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
        $this->initializeAPI();
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
     * 初始化企业微信API
     */
    private function initializeAPI()
    {
        require_once BASE_PATH . '/app/service/WeComAPI.php';
        
        $apiConfig = $this->configManager->getApiConfig();
        $this->WeComAPI = new \app\service\WeComAPI($apiConfig);
    }

    /**
     * 获取完整组织架构数据
     *
     * @param int $rootDepartmentId 根部门ID
     * @return array
     * @throws \Exception
     */
    public function getFullOrganization($rootDepartmentId = 1)
    {
        $this->logInfo('开始获取完整组织架构数据', ['root_department_id' => $rootDepartmentId]);

        try {
            // WeComAPI会自动处理访问令牌
            $this->logInfo('开始获取企业微信数据');
            
            // 获取所有部门
            $departments = $this->WeComAPI->getDepartments($rootDepartmentId);
            $this->logInfo('获取部门列表成功', ['count' => count($departments)]);
            
            // 获取所有用户
            $users = [];
            $processedDepts = 0;
            
            foreach ($departments as $dept) {
                try {
                    $deptUsers = $this->WeComAPI->getDepartmentUsers($dept['id'], false);
                    $users = array_merge($users, $deptUsers);
                    $processedDepts++;
                    
                    $this->logInfo('获取部门用户成功', [
                        'dept_id' => $dept['id'],
                        'dept_name' => $dept['name'],
                        'users_count' => count($deptUsers),
                        'progress' => "{$processedDepts}/" . count($departments)
                    ]);
                } catch (\Exception $e) {
                    $this->logError('获取部门用户失败', [
                        'dept_id' => $dept['id'],
                        'dept_name' => $dept['name'],
                        'error' => $e->getMessage()
                    ]);
                    // 继续处理其他部门
                    continue;
                }
            }
            
            // 去重用户（一个用户可能在多个部门）
            $uniqueUsers = [];
            foreach ($users as $user) {
                $uniqueUsers[$user['userid']] = $user;
            }
            
            $result = [
                'departments' => $departments,
                'users' => array_values($uniqueUsers)
            ];

            $this->logInfo('完整组织架构数据获取完成', [
                'departments_count' => count($result['departments']),
                'users_count' => count($result['users'])
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logError('获取完整组织架构数据失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取增量组织架构数据
     *
     * @param int $lastSyncTime 上次同步时间戳
     * @param int $rootDepartmentId 根部门ID
     * @return array
     * @throws \Exception
     */
    public function getIncrementalOrganization($lastSyncTime, $rootDepartmentId = 1)
    {
        $this->logInfo('尝试获取增量组织架构数据', [
            'last_sync_time' => date('Y-m-d H:i:s', $lastSyncTime),
            'root_department_id' => $rootDepartmentId
        ]);

        // 企业微信API不直接支持增量获取，使用全量获取
        // 注意：这里可以根据实际需求实现真正的增量逻辑
        $this->logInfo('企业微信API不支持增量获取，降级为全量获取');
        
        return $this->getFullOrganization($rootDepartmentId);
    }

    /**
     * 获取企业微信数据（智能选择全量或增量）
     *
     * @param bool $forceFullSync 是否强制全量同步
     * @return array
     * @throws \Exception
     */
    public function fetchWeComData($forceFullSync = false)
    {
        $syncConfig = $this->configManager->getSyncConfig();
        $rootDepartmentId = $syncConfig['department_root_id'] ?? 1;

        $this->logInfo('开始获取企业微信数据', [
            'force_full_sync' => $forceFullSync,
            'root_department_id' => $rootDepartmentId
        ]);

        if ($forceFullSync) {
            // 强制全量同步
            $data = $this->getFullOrganization($rootDepartmentId);
        } else {
            // 检查是否需要增量同步
            $lastSyncTime = $this->getLastSyncTime();
            if ($lastSyncTime && $this->shouldUseIncrementalSync($lastSyncTime)) {
                $data = $this->getIncrementalOrganization($lastSyncTime, $rootDepartmentId);
                $this->logInfo('使用增量同步模式', ['last_sync_time' => date('Y-m-d H:i:s', $lastSyncTime)]);
            } else {
                $data = $this->getFullOrganization($rootDepartmentId);
                $this->logInfo('使用全量同步模式');
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
     * @return int|null
     */
    private function getLastSyncTime()
    {
        try {
            $lastSync = $this->database->get('wecom_sync_logs', 'end_time', [
                'sync_status' => 'completed',
                'ORDER' => ['id' => 'DESC']
            ]);

            if ($lastSync) {
                return strtotime($lastSync);
            }
        } catch (\Exception $e) {
            $this->logError('获取上次同步时间失败', $e->getMessage());
        }

        return null;
    }

    /**
     * 判断是否应该使用增量同步
     *
     * @param int $lastSyncTime
     * @return bool
     */
    private function shouldUseIncrementalSync($lastSyncTime)
    {
        // 如果上次同步时间超过24小时，使用全量同步
        $maxIncrementalInterval = 24 * 3600; // 24小时
        return (time() - $lastSyncTime) < $maxIncrementalInterval;
    }

    /**
     * 获取特定部门的用户列表
     *
     * @param int $departmentId 部门ID
     * @param bool $fetchChild 是否获取子部门用户
     * @return array
     * @throws \Exception
     */
    public function getDepartmentUsers($departmentId, $fetchChild = false)
    {
        try {
            // WeComAPI会自动处理访问令牌
            $users = $this->WeComAPI->getDepartmentUsers($departmentId, $fetchChild);
            
            $this->logInfo('获取部门用户成功', [
                'department_id' => $departmentId,
                'fetch_child' => $fetchChild,
                'users_count' => count($users)
            ]);

            return $users;

        } catch (\Exception $e) {
            $this->logError('获取部门用户失败', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取部门列表
     *
     * @param int $rootDepartmentId 根部门ID
     * @return array
     * @throws \Exception
     */
    public function getDepartmentList($rootDepartmentId = 1)
    {
        try {
            // WeComAPI会自动处理访问令牌
            $departments = $this->WeComAPI->getDepartments($rootDepartmentId);
            
            $this->logInfo('获取部门列表成功', [
                'root_department_id' => $rootDepartmentId,
                'departments_count' => count($departments)
            ]);

            return $departments;

        } catch (\Exception $e) {
            $this->logError('获取部门列表失败', [
                'root_department_id' => $rootDepartmentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 测试API连接
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $accessToken = $this->WeComAPI->getAccessToken();
            
            if ($accessToken) {
                return [
                    'success' => true,
                    'message' => 'API连接测试成功',
                    'access_token' => substr($accessToken, 0, 10) . '...'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'API连接测试失败：无法获取访问令牌'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API连接测试失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComDataFetcher] ' . $message;
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
            $logMessage = '[WeComDataFetcher ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
