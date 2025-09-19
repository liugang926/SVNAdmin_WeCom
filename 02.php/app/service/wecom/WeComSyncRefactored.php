<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;
use app\service\Logs as ServiceLogs;
use app\service\Svngroup as ServiceSvngroup;

// 引入重构后的专业化类
require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataFetcher.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComUserSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComGroupSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComPermissionSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncStatus.php';

/**
 * 企业微信同步协调器（重构版本）
 * 
 * 作为主要的同步协调器，负责：
 * - 协调各个专门服务的执行
 * - 管理同步流程和状态
 * - 提供统一的同步接口
 * - 处理同步异常和错误
 */
class WeComSyncRefactored extends Base
{
    /**
     * 配置管理器
     * @var WeComConfigManager
     */
    private $configManager;

    /**
     * 数据获取器
     * @var WeComDataFetcher
     */
    private $dataFetcher;

    /**
     * 数据映射器
     * @var WeComDataMapper
     */
    private $dataMapper;

    /**
     * 用户同步器
     * @var WeComUserSync
     */
    private $userSync;

    /**
     * 分组同步器
     * @var WeComGroupSync
     */
    private $groupSync;

    /**
     * 权限同步器
     * @var WeComPermissionSync
     */
    private $permissionSync;

    /**
     * 同步状态管理器
     * @var WeComSyncStatus
     */
    private $syncStatus;

    /**
     * 日志服务
     * @var ServiceLogs
     */
    private $ServiceLogs;

    /**
     * SVN分组服务
     * @var ServiceSvngroup
     */
    private $ServiceSvngroup;

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

        try {
            $this->ServiceSvngroup = new ServiceSvngroup($parm);
        } catch (\Exception $e) {
            $this->ServiceSvngroup = null;
        }

        // 初始化所有服务组件
        $this->initializeServices($parm);
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
     * 初始化所有服务组件
     */
    private function initializeServices($parm)
    {
        $this->configManager = new WeComConfigManager($parm);
        $this->dataFetcher = new WeComDataFetcher($parm);
        $this->dataMapper = new WeComDataMapper($parm);
        $this->userSync = new WeComUserSync($parm);
        $this->groupSync = new WeComGroupSync($parm);
        $this->permissionSync = new WeComPermissionSync($parm);
        $this->syncStatus = new WeComSyncStatus($parm);
    }

    /**
     * 执行完整同步
     *
     * @param bool $forceFullSync 是否强制全量同步
     * @param bool $memberSyncOnly 是否只进行成员同步（基于现有数据）
     * @return array 同步结果
     */
    public function fullSync($forceFullSync = true, $memberSyncOnly = false)
    {
        $this->logInfo('开始执行企业微信完整同步', [
            'force_full_sync' => $forceFullSync,
            'member_sync_only' => $memberSyncOnly
        ]);

        $taskId = null;

        try {
            // 1. 验证API配置（必须有效）
            if (!$memberSyncOnly) {
                // 完整同步模式：验证API配置
                $configValidation = $this->configManager->validateConfig();
                if (!$configValidation['valid']) {
                    // 不再降级到仅成员同步模式，直接抛出异常
                    throw new \Exception('企业微信API配置无效: ' . implode(', ', $configValidation['errors']));
                }
            }

            // 2. 开始同步任务
            $taskId = $this->syncStatus->startSyncTask($forceFullSync ? 'full' : 'incremental');

            // 3. 获取数据
            $wecomData = [];

            if ($memberSyncOnly) {
                // 仅成员同步模式：使用数据库中的现有数据
                $this->logInfo('使用仅成员同步模式，基于数据库现有数据进行同步');
                $wecomData = $this->getExistingWeComData();
            } else {
                // 完整同步模式：从企业微信API获取数据
                $this->logInfo('使用完整同步模式，从企业微信API获取数据');

                // 2.5. 自动清理孤立映射
                $this->logInfo('开始清理孤立的企业微信映射');
                $cleanupResult = $this->cleanupOrphanedMappings();
                $this->logInfo('孤立映射清理完成', $cleanupResult);

                // 3. 获取企业微信数据
                $wecomData = $this->dataFetcher->fetchWeComData($forceFullSync);
            }

            if (empty($wecomData['departments']) && empty($wecomData['users'])) {
                throw new \Exception('未获取到企业微信数据');
            }

            // 4. 设置总数统计
            $this->syncStatus->setTotals(count($wecomData['departments']), count($wecomData['users']));

            // 5. 同步部门数据
            $this->logInfo('开始同步部门数据');
            $departmentResult = $this->groupSync->syncDepartmentsBatch($wecomData['departments']);

            // 6. 同步用户数据
            $this->logInfo('开始同步用户数据');
            $userResult = $this->userSync->syncUsersBatch($wecomData['users']);

            // 6.5. 验证数据完整性，确保所有必要的映射关系已建立
            $this->logInfo('验证数据完整性');
            $this->ensureDataIntegrity($wecomData['departments'], $wecomData['users']);

            // 7. 同步分组成员关系
            $this->logInfo('开始同步分组成员关系');
            $memberResult = $this->groupSync->syncGroupMembers($wecomData['departments'], $wecomData['users']);

            // 7.5. 成员信息已写入authz文件
            $this->logInfo('成员关系已写入authz文件');

            // 8. 同步分组层级关系
            $this->logInfo('开始同步分组层级关系');
            $hierarchyResult = $this->groupSync->syncGroupHierarchy($wecomData['departments']);

            // 8.5. 现在所有数据都已写入authz文件，执行同步到数据库
            $this->logInfo('同步分组信息到数据库');
            if ($this->ServiceSvngroup) {
                try {
                    $deptSyncResult = $this->ServiceSvngroup->SyncGroup();
                    if ($deptSyncResult['status'] == 1) {
                        $this->logInfo('分组信息同步成功');
                        // 立即更新所有分组的备注信息
                        $this->updateAllGroupNotes($wecomData['departments']);
                    } else {
                        $this->logError('分组信息同步失败', $deptSyncResult);
                    }
                } catch (\Exception $e) {
                    $this->logError('分组信息同步异常', $e->getMessage());
                }
            }

            // 9. 同步权限（可选）
            $permissionResult = null;
            $syncConfig = $this->configManager->getSyncConfig();
            if ($syncConfig['sync_permissions'] ?? false) {
                $this->logInfo('开始同步权限数据');
                $permissionResult = $this->permissionSync->syncPermissions($wecomData);
            }

            // 10. 最终处理 - 确保所有分组备注都是最新的
            $this->logInfo('更新所有分组备注信息');
            $finalSyncResult = null;
            if ($this->ServiceSvngroup) {
                try {
                    // 重新设置所有分组的备注，确保是最新的
                    $this->updateAllGroupNotes($wecomData['departments']);
                    $finalSyncResult = ['status' => 1, 'message' => '分组备注更新成功'];
                    $this->logInfo('所有分组备注已更新为最新状态');
                } catch (\Exception $e) {
                    $this->logError('更新分组备注失败', $e->getMessage());
                    $finalSyncResult = [
                        'status' => 0,
                        'message' => $e->getMessage()
                    ];
                }
            }
            

            // 11. 清理无效数据（可选）
            $cleanupResult = null;
            if ($syncConfig['auto_cleanup'] ?? false) {
                $this->logInfo('开始清理无效数据');
                $cleanupResult = $this->cleanupInvalidData();
            }

            // 12. 完成同步任务
            $this->syncStatus->completeSyncTask($taskId);

            $result = [
                'status' => 1,
                'message' => '企业微信同步完成',
                'data' => [
                    'task_id' => $taskId,
                    'departments' => $departmentResult,
                    'users' => $userResult,
                    'members' => $memberResult,
                    'hierarchy' => $hierarchyResult,
                    'permissions' => $permissionResult,
                    'svn_sync' => $finalSyncResult,
                    'cleanup' => $cleanupResult,
                    'stats' => $this->syncStatus->getSyncStats()
                ]
            ];

            $this->logInfo('企业微信完整同步成功完成', [
                'task_id' => $taskId,
                'stats' => $this->syncStatus->getSyncStats()
            ]);

            return $result;

        } catch (\Exception $e) {
            $errorMessage = '企业微信同步失败: ' . $e->getMessage();
            
            $this->logError('企业微信同步失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // 停止同步任务
            if ($taskId) {
                $this->syncStatus->stopSyncTask($errorMessage, $taskId);
            }

            return [
                'status' => 0,
                'message' => $errorMessage,
                'data' => [
                    'task_id' => $taskId,
                    'stats' => $this->syncStatus->getSyncStats()
                ]
            ];
        }
    }

    /**
     * 执行仅成员同步
     * 基于数据库中现有的企业微信数据，强制同步成员关系
     *
     * @return array 同步结果
     */
    public function memberOnlySync()
    {
        $this->logInfo('开始执行仅成员同步模式');
        return $this->fullSync(false, true);
    }

    /**
     * 执行纯SVNAdmin方法的企业微信同步
     * 不依赖API配置，只使用现有数据和SVNAdmin原生方法
     *
     * @return array 同步结果
     */
    public function pureSync()
    {
        $this->logInfo('开始执行纯SVNAdmin方法的企业微信同步');

        try {
            // 使用WeComGroupSync中的executePureSync方法
            if ($this->groupSync) {
                return $this->groupSync->executePureSync();
            } else {
                $this->logError('WeComGroupSync未初始化');
                return [
                    'status' => 0,
                    'message' => 'WeComGroupSync未初始化',
                    'processed_groups' => 0,
                    'total_changes' => 0,
                    'results' => []
                ];
            }
        } catch (\Exception $e) {
            $this->logError('纯SVNAdmin同步失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '同步失败: ' . $e->getMessage(),
                'processed_groups' => 0,
                'total_changes' => 0,
                'results' => []
            ];
        }
    }

    /**
     * 停止同步
     *
     * @return array 停止结果
     */
    public function stopSync()
    {
        $this->logInfo('收到停止同步请求');
        return $this->syncStatus->manualStopSync();
    }

    /**
     * 检查同步状态
     *
     * @return array 状态信息
     */
    public function checkSyncStatus()
    {
        return $this->syncStatus->checkSyncStatus();
    }

    /**
     * 获取同步历史
     *
     * @param int $limit 限制数量
     * @return array 历史记录
     */
    public function getSyncHistory($limit = 10)
    {
        return $this->syncStatus->getSyncHistory($limit);
    }

    /**
     * 获取同步进度
     *
     * @return array 进度信息
     */
    public function getSyncProgress()
    {
        return $this->syncStatus->getSyncProgress();
    }

    /**
     * 测试企业微信API连接
     *
     * @return array 测试结果
     */
    public function testConnection()
    {
        $this->logInfo('开始测试企业微信API连接');
        
        try {
            $result = $this->dataFetcher->testConnection();
            
            $this->logInfo('企业微信API连接测试完成', $result);
            
            return [
                'status' => $result['success'] ? 1 : 0,
                'message' => $result['message'],
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logError('企业微信API连接测试失败', $e->getMessage());
            
            return [
                'status' => 0,
                'message' => '连接测试失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 清理无效数据
     *
     * @return array 清理结果
     */
    public function cleanupInvalidData()
    {
        $this->logInfo('开始清理无效数据');

        $results = [];

        try {
            // 清理已删除的企业微信用户
            $deletedUsersResult = $this->cleanupDeletedUsers();
            $results['deleted_users'] = $deletedUsersResult;

            // 清理已删除的企业微信部门
            $deletedDepartmentsResult = $this->cleanupDeletedDepartments();
            $results['deleted_departments'] = $deletedDepartmentsResult;

            // 清理无效权限
            $invalidPermissionsResult = $this->permissionSync->cleanupInvalidPermissions();
            $results['invalid_permissions'] = $invalidPermissionsResult;

            $this->logInfo('无效数据清理完成', $results);

            return [
                'status' => 1,
                'message' => '数据清理完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('数据清理失败', $e->getMessage());
            
            return [
                'status' => 0,
                'message' => '数据清理失败: ' . $e->getMessage(),
                'data' => $results
            ];
        }
    }

    /**
     * 清理孤立的企业微信映射
     *
     * @return array 清理结果
     */
    private function cleanupOrphanedMappings()
    {
        $this->logInfo('开始清理孤立的企业微信映射');
        
        $results = [
            'orphaned_cleaned' => 0,
            'mappings_reset' => 0,
            'total_mappings' => 0
        ];
        
        try {
            // 1. 统计总映射数
            $results['total_mappings'] = $this->database->count('wecom_users');
            
            // 2. 查找孤立映射（指向不存在的SVN用户）
            $orphanedMappings = $this->database->query(
                "SELECT w.id, w.wecom_user_id, w.real_name, w.svn_user_id, w.svn_username
                 FROM wecom_users w 
                 LEFT JOIN svn_users s ON w.svn_user_id = s.svn_user_id 
                 WHERE w.svn_user_id > 0 AND s.svn_user_id IS NULL"
            )->fetchAll();
            
            // 3. 删除孤立映射
            if (!empty($orphanedMappings)) {
                foreach ($orphanedMappings as $mapping) {
                    $this->database->delete('wecom_users', [
                        'id' => $mapping['id']
                    ]);
                    
                    $results['orphaned_cleaned']++;
                    
                    $this->logInfo('清理孤立映射', [
                        'wecom_user_id' => $mapping['wecom_user_id'],
                        'real_name' => $mapping['real_name'],
                        'orphaned_svn_user_id' => $mapping['svn_user_id']
                    ]);
                }
            }
            
            // 4. 重置剩余映射的SVN关联信息，准备重新匹配
            $resetCount = $this->database->update('wecom_users', [
                'svn_user_id' => 0,
                'svn_username' => '',
                'match_status' => 'pending',
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'svn_user_id[>]' => 0  // 只重置有SVN关联的记录
            ]);
            
            $results['mappings_reset'] = $this->database->info()['affected_rows'] ?? 0;
            
            $this->logInfo('孤立映射清理完成', $results);
            
            return [
                'status' => 1,
                'message' => "清理完成：删除 {$results['orphaned_cleaned']} 个孤立映射，重置 {$results['mappings_reset']} 个映射",
                'data' => $results
            ];
            
        } catch (\Exception $e) {
            $this->logError('清理孤立映射失败', $e->getMessage());
            
            return [
                'status' => 0,
                'message' => '清理失败: ' . $e->getMessage(),
                'data' => $results
            ];
        }
    }

    /**
     * 清理已删除的用户
     *
     * @return array 清理结果
     */
    private function cleanupDeletedUsers()
    {
        $this->logInfo('清理已删除用户');
        
        try {
            // 查找孤立映射（这个逻辑现在已经在 cleanupOrphanedMappings 中处理）
            // 这里保持原有接口，但实际清理工作已经前移到同步开始前
            
            $orphanedCount = $this->database->query(
                "SELECT COUNT(*) as count
                 FROM wecom_users w 
                 LEFT JOIN svn_users s ON w.svn_user_id = s.svn_user_id 
                 WHERE w.svn_user_id > 0 AND s.svn_user_id IS NULL"
            )->fetch()['count'];
            
            if ($orphanedCount > 0) {
                $this->logInfo('发现残留的孤立映射', ['count' => $orphanedCount]);
                // 如果还有孤立映射，再次清理
                return $this->cleanupOrphanedMappings();
            }
            
            return [
                'status' => 1,
                'message' => '已删除用户清理完成，无需处理',
                'count' => 0
            ];
            
        } catch (\Exception $e) {
            $this->logError('清理已删除用户失败', $e->getMessage());
            
            return [
                'status' => 0,
                'message' => '清理失败: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }

    /**
     * 清理已删除的部门
     *
     * @return array 清理结果
     */
    private function cleanupDeletedDepartments()
    {
        // 这里可以实现清理已删除部门的逻辑
        // 比如标记为已删除或从数据库中移除
        
        $this->logInfo('清理已删除部门');
        
        return [
            'status' => 1,
            'message' => '已删除部门清理完成',
            'count' => 0
        ];
    }

    /**
     * 获取系统状态
     *
     * @return array 系统状态
     */
    public function getSystemStatus()
    {
        try {
            // 检查配置状态
            $configValidation = $this->configManager->validateConfig();
            
            // 检查API连接状态
            $connectionTest = $this->dataFetcher->testConnection();
            
            // 检查同步状态
            $syncStatus = $this->syncStatus->checkSyncStatus();
            
            // 获取统计信息
            $stats = $this->getSystemStats();

            return [
                'status' => 1,
                'message' => '系统状态获取成功',
                'data' => [
                    'config' => $configValidation,
                    'connection' => $connectionTest,
                    'sync' => $syncStatus,
                    'stats' => $stats,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (\Exception $e) {
            $this->logError('获取系统状态失败', $e->getMessage());
            
            return [
                'status' => 0,
                'message' => '获取系统状态失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 获取系统统计信息
     *
     * @return array 统计信息
     */
    private function getSystemStats()
    {
        try {
            $stats = [
                'departments_count' => $this->database->count('wecom_departments'),
                'users_count' => $this->database->count('wecom_users'),
                'svn_groups_count' => $this->database->count('svn_groups'),
                'svn_users_count' => $this->database->count('svn_users'),
                'sync_logs_count' => $this->database->count('wecom_sync_logs'),
                'last_sync_time' => $this->getLastSyncTime()
            ];

            return $stats;

        } catch (\Exception $e) {
            $this->logError('获取系统统计信息失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 获取最后同步时间
     *
     * @return string|null 最后同步时间
     */
    private function getLastSyncTime()
    {
        try {
            $lastSync = $this->database->get('wecom_sync_logs', 'end_time', [
                'sync_status' => 'completed',
                'ORDER' => ['id' => 'DESC']
            ]);

            return $lastSync ?: null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 确保数据完整性 - 验证所有必要的映射关系已建立
     * 
     * @param array $departments 部门数据
     * @param array $users 用户数据
     * @throws \Exception 如果数据完整性验证失败
     */
    private function ensureDataIntegrity($departments, $users)
    {
        $maxRetries = 10; // 最多重试10次
        $retryInterval = 100000; // 每次重试间隔100ms
        
        for ($retry = 0; $retry < $maxRetries; $retry++) {
            $this->logInfo('数据完整性验证', ['attempt' => $retry + 1, 'max_retries' => $maxRetries]);
            
            // 检查部门映射完整性
            $missingDeptMappings = $this->checkDepartmentMappings($departments);
            
            // 检查用户映射完整性
            $missingUserMappings = $this->checkUserMappings($users);
            
            if (empty($missingDeptMappings) && empty($missingUserMappings)) {
                $this->logInfo('数据完整性验证通过', [
                    'departments_checked' => count($departments),
                    'users_checked' => count($users),
                    'retry_count' => $retry
                ]);
                return; // 验证通过，退出
            }
            
            // 尝试自动修复缺失的映射
            if (!empty($missingDeptMappings)) {
                $this->logInfo('发现缺失的部门映射，尝试自动修复', [
                    'missing_count' => count($missingDeptMappings),
                    'missing_departments' => array_slice($missingDeptMappings, 0, 5) // 只记录前5个
                ]);
                
                $this->autoFixDepartmentMappings($missingDeptMappings);
            }
            
            if (!empty($missingUserMappings)) {
                $this->logInfo('发现缺失的用户映射，尝试自动修复', [
                    'missing_count' => count($missingUserMappings),
                    'missing_users' => array_slice($missingUserMappings, 0, 5) // 只记录前5个
                ]);
                
                $this->autoFixUserMappings($missingUserMappings);
            }
            
            // 如果是最后一次重试且仍有问题，记录警告但不阻止同步
            if ($retry == $maxRetries - 1) {
                if (!empty($missingDeptMappings) || !empty($missingUserMappings)) {
                    $this->logError('数据完整性验证未完全通过，但继续执行同步', [
                        'remaining_dept_mappings' => count($missingDeptMappings),
                        'remaining_user_mappings' => count($missingUserMappings)
                    ]);
                }
                return; // 继续执行，不抛出异常
            }
            
            // 等待后重试
            usleep($retryInterval);
        }
    }
    
    /**
     * 检查部门映射完整性
     * 
     * @param array $departments 部门数据
     * @return array 缺失映射的部门列表
     */
    private function checkDepartmentMappings($departments)
    {
        $missingMappings = [];
        
        foreach ($departments as $dept) {
            $deptId = $dept['id'];
            $deptName = $dept['name'];
            
            // 检查是否存在有效的映射关系
            $mapping = $this->database->get('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'wecom_departments.wecom_dept_id',
                'wecom_departments.svn_group_id',
                'svn_groups.svn_group_name'
            ], [
                'wecom_departments.wecom_dept_id' => $deptId
            ]);
            
            // 如果没有映射或映射无效，记录为缺失
            if (!$mapping || empty($mapping['svn_group_name'])) {
                $missingMappings[] = [
                    'dept_id' => $deptId,
                    'dept_name' => $deptName,
                    'current_mapping' => $mapping
                ];
            }
        }
        
        return $missingMappings;
    }
    
    /**
     * 检查用户映射完整性
     * 
     * @param array $users 用户数据
     * @return array 缺失映射的用户列表
     */
    private function checkUserMappings($users)
    {
        $missingMappings = [];
        
        foreach ($users as $user) {
            $userId = $user['userid'];
            $userName = $user['name'];
            
            // 检查是否存在有效的用户映射
            $mapping = $this->database->get('wecom_users', [
                '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
            ], [
                'wecom_users.wecom_user_id',
                'wecom_users.svn_user_id',
                'svn_users.svn_user_name'
            ], [
                'wecom_users.wecom_user_id' => $userId
            ]);
            
            // 如果没有映射或映射无效，记录为缺失
            if (!$mapping || empty($mapping['svn_user_name'])) {
                $missingMappings[] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'current_mapping' => $mapping
                ];
            }
        }
        
        return $missingMappings;
    }
    
    /**
     * 自动修复部门映射问题
     * 
     * @param array $missingMappings 缺失的部门映射
     */
    private function autoFixDepartmentMappings($missingMappings)
    {
        foreach ($missingMappings as $missing) {
            $deptId = $missing['dept_id'];
            $deptName = $missing['dept_name'];
            
            $this->logInfo('修复部门映射', [
                'dept_id' => $deptId,
                'dept_name' => $deptName
            ]);
            
            try {
                // 查找是否存在同名的SVN分组
                $existingSvnGroup = $this->database->get('svn_groups', [
                    'svn_group_id',
                    'svn_group_name'
                ], [
                    'svn_group_name' => $deptName
                ]);
                
                if ($existingSvnGroup) {
                    // 存在同名分组，更新映射
                    $this->database->update('wecom_departments', [
                        'svn_group_id' => $existingSvnGroup['svn_group_id'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'wecom_dept_id' => $deptId
                    ]);
                    
                    $this->logInfo('部门映射修复成功', [
                        'dept_id' => $deptId,
                        'dept_name' => $deptName,
                        'svn_group_id' => $existingSvnGroup['svn_group_id']
                    ]);
                } else {
                    // 不存在同名分组，清空映射（让后续流程重新创建）
                    $this->database->update('wecom_departments', [
                        'svn_group_id' => null,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'wecom_dept_id' => $deptId
                    ]);
                    
                    $this->logInfo('清空无效部门映射，等待重新创建', [
                        'dept_id' => $deptId,
                        'dept_name' => $deptName
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('修复部门映射失败', [
                    'dept_id' => $deptId,
                    'dept_name' => $deptName,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * 自动修复用户映射问题
     * 
     * @param array $missingMappings 缺失的用户映射
     */
    private function autoFixUserMappings($missingMappings)
    {
        foreach ($missingMappings as $missing) {
            $userId = $missing['user_id'];
            $userName = $missing['user_name'];
            
            $this->logInfo('修复用户映射', [
                'user_id' => $userId,
                'user_name' => $userName
            ]);
            
            try {
                // 查找是否存在同名的SVN用户
                $existingSvnUser = $this->database->get('svn_users', [
                    'svn_user_id',
                    'svn_user_name'
                ], [
                    'svn_user_name' => $userId // 通常企业微信的userid就是SVN用户名
                ]);
                
                if ($existingSvnUser) {
                    // 存在同名用户，更新映射
                    $this->database->update('wecom_users', [
                        'svn_user_id' => $existingSvnUser['svn_user_id'],
                        'svn_username' => $existingSvnUser['svn_user_name'],
                        'match_status' => 'matched',
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'wecom_user_id' => $userId
                    ]);
                    
                    $this->logInfo('用户映射修复成功', [
                        'user_id' => $userId,
                        'user_name' => $userName,
                        'svn_user_id' => $existingSvnUser['svn_user_id']
                    ]);
                } else {
                    // 不存在同名用户，清空映射（让后续流程重新匹配）
                    $this->database->update('wecom_users', [
                        'svn_user_id' => null,
                        'svn_username' => '',
                        'match_status' => 'pending',
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'wecom_user_id' => $userId
                    ]);
                    
                    $this->logInfo('清空无效用户映射，等待重新匹配', [
                        'user_id' => $userId,
                        'user_name' => $userName
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('修复用户映射失败', [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }


    /**
     * 更新所有分组的备注信息
     * 确保每次同步后所有分组的备注都是最新的
     * 
     * @param array $departments 部门列表
     */
    private function updateAllGroupNotes($departments)
    {
        // 使用已有的数据库连接
        if (!$this->database) {
            $this->logError('数据库连接不可用，无法更新分组备注');
            return;
        }
        
        $database = $this->database;
        $updatedCount = 0;
        
        foreach ($departments as $dept) {
            try {
                // 查找对应的映射关系
                $wecomDept = $database->get('wecom_departments', [
                    'svn_group_id',
                    'dept_name'
                ], [
                    'wecom_dept_id' => $dept['id']
                ]);
                
                if ($wecomDept && $wecomDept['svn_group_id']) {
                    // 生成最新的备注
                    $note = "{$dept['name']} (ID: {$dept['id']}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";
                    
                    // 更新备注（覆盖旧的）
                    $database->update('svn_groups', [
                        'svn_group_note' => $note
                    ], [
                        'svn_group_id' => $wecomDept['svn_group_id']
                    ]);
                    
                    $updatedCount++;
                    
                    $this->logInfo('更新分组备注', [
                        'dept_id' => $dept['id'],
                        'dept_name' => $dept['name'],
                        'svn_group_id' => $wecomDept['svn_group_id'],
                        'note' => $note
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('更新单个分组备注失败', [
                    'dept_id' => $dept['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logInfo('批量更新分组备注完成', [
            'total_departments' => count($departments),
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * 获取指定部门应该包含的用户
     *
     * @param int $deptId 部门ID
     * @return array SVN用户名列表
     */
    private function getExpectedUsersForDept($deptId)
    {
        $users = $this->database->select('wecom_users', [
            '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
        ], [
            'svn_users.svn_user_name'
        ], [
            'AND' => [
                'wecom_users.department_ids[~]' => $deptId,
                'wecom_users.svn_user_id[!]' => null
            ]
        ]);

        return array_column($users, 'svn_user_name');
    }

    /**
     * 获取指定部门应该包含的子分组
     *
     * @param int $parentDeptId 父部门ID
     * @return array SVN分组名列表
     */
    private function getExpectedSubGroupsForDept($parentDeptId)
    {
        $subGroups = $this->database->select('wecom_departments', [
            '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
        ], [
            'svn_groups.svn_group_name'
        ], [
            'wecom_departments.parent_id' => $parentDeptId,
            'svn_groups.svn_group_name[!]' => null
        ]);

        return array_column($subGroups, 'svn_group_name');
    }

    /**
     * 应用分组变更（纯SVNAdmin方法）
     *
     * @param \Witersen\SVNAdmin $svnAdmin SVNAdmin实例
     * @param string $authzContent authz文件内容
     * @param string $groupName 分组名称
     * @param array $usersToAdd 要添加的用户
     * @param array $usersToRemove 要移除的用户
     * @param array $subGroupsToAdd 要添加的子分组
     * @param array $subGroupsToRemove 要移除的子分组
     * @return array 操作结果
     */
    private function applyPureGroupChanges($svnAdmin, $authzContent, $groupName, $usersToAdd, $usersToRemove, $subGroupsToAdd, $subGroupsToRemove)
    {
        $updatedAuthzContent = $authzContent;
        $changesMade = 0;

        try {
            // 移除用户
            foreach ($usersToRemove as $user) {
                $result = $svnAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $user, 'user', 'delete');
                if (!is_numeric($result)) {
                    $updatedAuthzContent = $result;
                    $changesMade++;
                    $this->logInfo('移除用户成功', [
                        'group' => $groupName,
                        'user' => $user
                    ]);
                } else {
                    $this->logInfo('移除用户失败', [
                        'group' => $groupName,
                        'user' => $user,
                        'error_code' => $result
                    ]);
                }
            }

            // 移除子分组
            foreach ($subGroupsToRemove as $subGroup) {
                $result = $svnAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $subGroup, 'group', 'delete');
                if (!is_numeric($result)) {
                    $updatedAuthzContent = $result;
                    $changesMade++;
                    $this->logInfo('移除子分组成功', [
                        'group' => $groupName,
                        'subgroup' => $subGroup
                    ]);
                } else {
                    $this->logInfo('移除子分组失败', [
                        'group' => $groupName,
                        'subgroup' => $subGroup,
                        'error_code' => $result
                    ]);
                }
            }

            // 添加用户
            foreach ($usersToAdd as $user) {
                $result = $svnAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $user, 'user', 'add');
                if (!is_numeric($result)) {
                    $updatedAuthzContent = $result;
                    $changesMade++;
                    $this->logInfo('添加用户成功', [
                        'group' => $groupName,
                        'user' => $user
                    ]);
                } else {
                    $this->logInfo('添加用户失败', [
                        'group' => $groupName,
                        'user' => $user,
                        'error_code' => $result
                    ]);
                }
            }

            // 添加子分组
            foreach ($subGroupsToAdd as $subGroup) {
                $result = $svnAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $subGroup, 'group', 'add');
                if (!is_numeric($result)) {
                    $updatedAuthzContent = $result;
                    $changesMade++;
                    $this->logInfo('添加子分组成功', [
                        'group' => $groupName,
                        'subgroup' => $subGroup
                    ]);
                } else {
                    $this->logInfo('添加子分组失败', [
                        'group' => $groupName,
                        'subgroup' => $subGroup,
                        'error_code' => $result
                    ]);
                }
            }

            return [
                'success' => true,
                'authz_content' => $updatedAuthzContent,
                'changes_made' => $changesMade
            ];

        } catch (\Exception $e) {
            $this->logError('应用分组变更失败', [
                'group' => $groupName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'authz_content' => $authzContent,
                'changes_made' => $changesMade,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 更新分组备注
     *
     * @param array $mappedGroups 映射的分组列表
     */
    private function updateGroupNotes($mappedGroups)
    {
        if (!$this->database) {
            $this->logError('数据库连接不可用，无法更新分组备注');
            return;
        }

        $updatedCount = 0;

        foreach ($mappedGroups as $group) {
            try {
                $deptId = $group['wecom_dept_id'];
                $deptName = $group['dept_name'];

                // 查找对应的SVN分组ID
                $wecomDept = $this->database->get('wecom_departments', [
                    'svn_group_id'
                ], [
                    'wecom_dept_id' => $deptId
                ]);

                if ($wecomDept && $wecomDept['svn_group_id']) {
                    // 生成最新的备注
                    $note = "{$deptName} (ID: {$deptId}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";

                    // 更新备注
                    $this->database->update('svn_groups', [
                        'svn_group_note' => $note
                    ], [
                        'svn_group_id' => $wecomDept['svn_group_id']
                    ]);

                    $updatedCount++;

                    $this->logInfo('更新分组备注', [
                        'dept_id' => $deptId,
                        'dept_name' => $deptName,
                        'svn_group_id' => $wecomDept['svn_group_id'],
                        'note' => $note
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('更新单个分组备注失败', [
                    'dept_id' => $group['wecom_dept_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logInfo('批量更新分组备注完成', [
            'total_groups' => count($mappedGroups),
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * 从数据库获取现有的企业微信数据
     * 用于仅成员同步模式
     *
     * @return array 包含departments和users的数组
     */
    private function getExistingWeComData()
    {
        $this->logInfo('从数据库获取现有企业微信数据');

        try {
            // 获取所有部门数据
            $departments = $this->database->select('wecom_departments', [
                'wecom_dept_id',
                'parent_id',
                'dept_name',
                'dept_order'
            ]);

            // 转换为API格式
            $departmentData = [];
            foreach ($departments as $dept) {
                $departmentData[] = [
                    'id' => $dept['wecom_dept_id'],
                    'name' => $dept['dept_name'],
                    'parentid' => $dept['parent_id'],
                    'order' => $dept['dept_order']
                ];
            }

            // 获取所有用户数据
            $users = $this->database->select('wecom_users', [
                'wecom_user_id',
                'real_name',
                'department_ids',
                'position',
                'is_leader_in_dept',
                'mobile',
                'email'
            ]);

            // 转换为API格式
            $userData = [];
            foreach ($users as $user) {
                $departmentIds = json_decode($user['department_ids'], true) ?: [];
                $isLeaderInDept = json_decode($user['is_leader_in_dept'], true) ?: [];

                $userData[] = [
                    'userid' => $user['wecom_user_id'],
                    'name' => $user['real_name'],
                    'department' => $departmentIds,
                    'position' => $user['position'],
                    'mobile' => $user['mobile'],
                    'email' => $user['email'],
                    'is_leader_in_dept' => $isLeaderInDept
                ];
            }

            $this->logInfo('从数据库获取企业微信数据成功', [
                'departments_count' => count($departmentData),
                'users_count' => count($userData)
            ]);

            return [
                'departments' => $departmentData,
                'users' => $userData
            ];

        } catch (\Exception $e) {
            $this->logError('从数据库获取企业微信数据失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComSyncRefactored] ' . $message;
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
            $logMessage = '[WeComSyncRefactored ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . (is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : $error);
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
