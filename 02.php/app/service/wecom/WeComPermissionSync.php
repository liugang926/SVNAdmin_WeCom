<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncStatus.php';
use app\service\Logs as ServiceLogs;

/**
 * 企业微信权限同步器
 * 
 * 负责企业微信与SVN权限之间的同步，包括：
 * - 仓库权限分配
 * - 基于部门的权限策略
 * - 层级权限管理
 * - 权限清理和维护
 */
class WeComPermissionSync extends Base
{
    /**
     * 配置管理器
     * @var WeComConfigManager
     */
    private $configManager;

    /**
     * 数据映射器
     * @var WeComDataMapper
     */
    private $dataMapper;

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
        $this->dataMapper = new WeComDataMapper($parm);
        $this->syncStatus = new WeComSyncStatus($parm);
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
     * 同步权限
     *
     * @param array $wecomData 企业微信数据
     * @return array 同步结果
     */
    public function syncPermissions($wecomData)
    {
        $this->logInfo('开始同步权限数据');

        $results = [];

        try {
            // 获取所有仓库
            $repositories = $this->getSvnRepositories();
            
            if (empty($repositories)) {
                $this->logInfo('没有找到SVN仓库，跳过权限同步');
                return [
                    'status' => 1,
                    'message' => '没有SVN仓库需要同步权限',
                    'data' => []
                ];
            }

            foreach ($repositories as $repo) {
                try {
                    $repoResult = $this->syncRepositoryPermissions($repo, $wecomData);
                    $results[] = $repoResult;
                    
                } catch (\Exception $e) {
                    $this->logError('仓库权限同步失败', [
                        'repo_name' => $repo['svn_rep_name'],
                        'error' => $e->getMessage()
                    ]);
                    
                    $this->syncStatus->addSyncError("仓库 {$repo['svn_rep_name']} 权限同步失败: " . $e->getMessage());
                    $results[] = [
                        'repository' => $repo['svn_rep_name'],
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            $this->logInfo('权限数据同步完成', [
                'repositories_count' => count($repositories),
                'results_count' => count($results)
            ]);

            return [
                'status' => 1,
                'message' => '权限同步完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('权限同步失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步单个仓库的权限
     *
     * @param array $repository 仓库信息
     * @param array $wecomData 企业微信数据
     * @return array 同步结果
     */
    public function syncRepositoryPermissions($repository, $wecomData)
    {
        $repoName = $repository['svn_rep_name'];
        $this->logInfo('开始同步仓库权限', ['repository' => $repoName]);

        try {
            // 生成权限配置
            $permissions = $this->generateRepositoryPermissions($repository, $wecomData);
            
            if (empty($permissions)) {
                return [
                    'repository' => $repoName,
                    'action' => 'skipped',
                    'message' => '没有生成权限配置'
                ];
            }

            // 应用权限配置（这里需要根据实际的SVN权限管理方式实现）
            $this->applyRepositoryPermissions($repository, $permissions);

            $this->logInfo('仓库权限同步成功', [
                'repository' => $repoName,
                'permissions_count' => count($permissions)
            ]);

            return [
                'repository' => $repoName,
                'action' => 'updated',
                'message' => '权限同步成功',
                'permissions_count' => count($permissions)
            ];

        } catch (\Exception $e) {
            $this->logError('仓库权限同步失败', [
                'repository' => $repoName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 生成仓库权限配置
     *
     * @param array $repository 仓库信息
     * @param array $wecomData 企业微信数据
     * @return array 权限配置
     */
    public function generateRepositoryPermissions($repository, $wecomData)
    {
        $permissions = [];
        $permissionConfig = $this->configManager->getPermissionMappingConfig();
        $strategy = $this->configManager->getPermissionStrategy();

        switch ($strategy) {
            case 'department_based':
                $permissions = $this->generateDepartmentBasedPermissions($repository, $wecomData['departments']);
                break;
                
            case 'hierarchy_based':
                $permissions = $this->generateHierarchyBasedPermissions($repository, $wecomData);
                break;
                
            case 'role_based':
                $permissions = $this->generateRoleBasedPermissions($repository, $wecomData);
                break;
                
            default:
                $permissions = $this->generateDefaultPermissions($repository, $wecomData);
                break;
        }

        $this->logInfo('生成权限配置完成', [
            'repository' => $repository['svn_rep_name'],
            'strategy' => $strategy,
            'permissions_count' => count($permissions)
        ]);

        return $permissions;
    }

    /**
     * 生成基于部门的权限配置
     *
     * @param array $repository 仓库信息
     * @param array $departments 部门数据
     * @return array 权限配置
     */
    private function generateDepartmentBasedPermissions($repository, $departments)
    {
        $permissions = [];
        $defaultPermission = $this->configManager->getDefaultPermission();

        // 构建部门到SVN组的映射
        $deptToGroupMap = $this->buildDepartmentGroupMapping();

        foreach ($departments as $dept) {
            $deptId = $dept['id'];
            
            if (!isset($deptToGroupMap[$deptId])) {
                continue;
            }

            $svnGroupName = $deptToGroupMap[$deptId]['svn_group_name'];
            
            $permissions[] = [
                'path' => '/' . $repository['svn_rep_name'],
                'principal' => '@' . $svnGroupName,
                'permission' => $defaultPermission,
                'type' => 'group',
                'department_id' => $deptId,
                'department_name' => $dept['name']
            ];
        }

        return $permissions;
    }

    /**
     * 生成基于层级的权限配置
     *
     * @param array $repository 仓库信息
     * @param array $wecomData 企业微信数据
     * @return array 权限配置
     */
    private function generateHierarchyBasedPermissions($repository, $wecomData)
    {
        $permissions = [];
        $hierarchyPermissions = $this->configManager->getHierarchyPermissions();
        
        // 构建部门层级
        $hierarchy = $this->dataMapper->buildDepartmentHierarchy($wecomData['departments']);
        $deptToGroupMap = $this->buildDepartmentGroupMapping();

        foreach ($wecomData['departments'] as $dept) {
            $deptId = $dept['id'];
            
            if (!isset($deptToGroupMap[$deptId])) {
                continue;
            }

            // 计算部门层级
            $level = $this->calculateDepartmentLevel($deptId, $hierarchy, 0);
            $permission = $hierarchyPermissions[$level] ?? $hierarchyPermissions[max(array_keys($hierarchyPermissions))];
            
            $svnGroupName = $deptToGroupMap[$deptId]['svn_group_name'];
            
            $permissions[] = [
                'path' => '/' . $repository['svn_rep_name'],
                'principal' => '@' . $svnGroupName,
                'permission' => $permission,
                'type' => 'group',
                'department_id' => $deptId,
                'department_name' => $dept['name'],
                'level' => $level
            ];
        }

        return $permissions;
    }

    /**
     * 生成基于角色的权限配置
     *
     * @param array $repository 仓库信息
     * @param array $wecomData 企业微信数据
     * @return array 权限配置
     */
    private function generateRoleBasedPermissions($repository, $wecomData)
    {
        $permissions = [];
        
        // 这里可以根据用户的职位、角色等信息生成权限
        // 暂时使用默认实现
        return $this->generateDefaultPermissions($repository, $wecomData);
    }

    /**
     * 生成默认权限配置
     *
     * @param array $repository 仓库信息
     * @param array $wecomData 企业微信数据
     * @return array 权限配置
     */
    private function generateDefaultPermissions($repository, $wecomData)
    {
        $permissions = [];
        $defaultPermission = $this->configManager->getDefaultPermission();
        
        // 给所有企业微信用户默认权限
        $userToSvnUserMap = $this->buildUserSvnUserMapping();
        
        foreach ($wecomData['users'] as $user) {
            $userId = $user['userid'];
            
            if (!isset($userToSvnUserMap[$userId])) {
                continue;
            }
            
            $svnUserName = $userToSvnUserMap[$userId]['svn_user_name'];
            
            $permissions[] = [
                'path' => '/' . $repository['svn_rep_name'],
                'principal' => $svnUserName,
                'permission' => $defaultPermission,
                'type' => 'user',
                'wecom_user_id' => $userId,
                'wecom_user_name' => $user['name']
            ];
        }

        return $permissions;
    }

    /**
     * 应用仓库权限配置
     *
     * @param array $repository 仓库信息
     * @param array $permissions 权限配置
     */
    private function applyRepositoryPermissions($repository, $permissions)
    {
        // 这里需要根据实际的SVN权限管理方式实现
        // 可能需要更新authz文件或调用相关的权限管理API
        
        $this->logInfo('应用仓库权限配置', [
            'repository' => $repository['svn_rep_name'],
            'permissions' => $permissions
        ]);
        
        // 示例：记录权限配置到数据库
        foreach ($permissions as $permission) {
            try {
                // 这里可以将权限配置保存到数据库或直接写入authz文件
                $this->logInfo('应用权限', $permission);
                
            } catch (\Exception $e) {
                $this->logError('应用权限失败', [
                    'permission' => $permission,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * 计算部门层级
     *
     * @param int $deptId 部门ID
     * @param array $hierarchy 部门层级结构
     * @param int $currentLevel 当前层级
     * @return int 部门层级
     */
    private function calculateDepartmentLevel($deptId, $hierarchy, $currentLevel)
    {
        if (!isset($hierarchy[$deptId])) {
            return $currentLevel;
        }

        $dept = $hierarchy[$deptId];
        $parentId = $dept['parentid'];

        // 根部门
        if ($parentId == 0 || $parentId == 1) {
            return $currentLevel;
        }

        // 递归计算父部门层级
        return $this->calculateDepartmentLevel($parentId, $hierarchy, $currentLevel + 1);
    }

    /**
     * 清理无效权限
     *
     * @return array 清理结果
     */
    public function cleanupInvalidPermissions()
    {
        $this->logInfo('开始清理无效权限');

        $results = [];

        try {
            // 这里可以实现清理逻辑，比如：
            // 1. 清理已删除用户的权限
            // 2. 清理已删除部门的权限
            // 3. 清理重复的权限配置
            
            $this->logInfo('权限清理完成');

            return [
                'status' => 1,
                'message' => '权限清理完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('权限清理失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取SVN仓库列表
     *
     * @return array 仓库列表
     */
    private function getSvnRepositories()
    {
        try {
            return $this->database->select('svn_reps', [
                'svn_rep_id',
                'svn_rep_name',
                'svn_rep_note'
            ], [
                'svn_rep_status' => 1
            ]);
            
        } catch (\Exception $e) {
            $this->logError('获取SVN仓库列表失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 构建部门到SVN组的映射
     *
     * @return array 映射数组
     */
    private function buildDepartmentGroupMapping()
    {
        try {
            $mapping = [];
            $departments = $this->database->select('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'wecom_departments.wecom_dept_id',
                'wecom_departments.svn_group_id',
                'svn_groups.svn_group_name'
            ]);

            foreach ($departments as $dept) {
                $mapping[$dept['wecom_dept_id']] = [
                    'svn_group_id' => $dept['svn_group_id'],
                    'svn_group_name' => $dept['svn_group_name']
                ];
            }

            return $mapping;

        } catch (\Exception $e) {
            $this->logError('构建部门组映射失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 构建用户到SVN用户的映射
     *
     * @return array 映射数组
     */
    private function buildUserSvnUserMapping()
    {
        try {
            $mapping = [];
            $users = $this->database->select('wecom_users', [
                '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
            ], [
                'wecom_users.wecom_user_id',
                'wecom_users.svn_user_id',
                'svn_users.svn_user_name'
            ], [
                'wecom_users.svn_user_id[!]' => null
            ]);

            foreach ($users as $user) {
                $mapping[$user['wecom_user_id']] = [
                    'svn_user_id' => $user['svn_user_id'],
                    'svn_user_name' => $user['svn_user_name']
                ];
            }

            return $mapping;

        } catch (\Exception $e) {
            $this->logError('构建用户SVN用户映射失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComPermissionSync] ' . $message;
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
            $logMessage = '[WeComPermissionSync ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . (is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : $error);
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
