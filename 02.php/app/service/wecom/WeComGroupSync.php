<?php

namespace app\service\wecom;

use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncStatus.php';
require_once BASE_PATH . '/app/util/DatabaseHelper.php';
use app\service\Logs as ServiceLogs;
use app\service\Svngroup as ServiceSvngroup;
use app\util\DatabaseHelper;

/**
 * 企业微信分组同步器
 * 
 * 负责企业微信部门与SVN分组之间的同步，包括：
 * - 部门创建、更新、删除
 * - SVN分组管理
 * - 分组层级关系维护
 * - 分组成员管理
 */
class WeComGroupSync extends Base
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
     * SVN分组服务
     * @var ServiceSvngroup
     */
    private $ServiceSvngroup;

    /**
     * SVNAdmin实例
     * @var \Witersen\SVNAdmin
     */
    public $SVNAdmin;

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

        // 确保SVNAdmin对象已初始化
        if (!isset($this->SVNAdmin)) {
            require_once BASE_PATH . '/extension/Witersen/SVNAdmin.php';
            $this->SVNAdmin = new \Witersen\SVNAdmin();
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
        
        // 使用统一的数据库连接帮助类
        $this->database = DatabaseHelper::getConnection();

        // 使用配置文件中的路径
        require_once BASE_PATH . '/app/util/Config.php';
        $this->configSvn = \Config::get('svn');
    }

    /**
     * 同步部门数据
     *
     * @param array $departments 企业微信部门数据
     * @return array 同步结果
     */
    public function syncDepartments($departments)
    {
        $this->logInfo('开始同步部门数据', ['count' => count($departments)]);

        $this->syncStatus->setTotals(count($departments), 0);
        $results = [];

        try {
            // 获取现有的企业微信部门数据
            $existingDepartments = $this->getExistingDepartments();
            $existingSvnGroups = $this->getExistingSvnGroups();

            foreach ($departments as $department) {
                try {
                    $result = $this->processDepartment($department, $existingDepartments, $existingSvnGroups);
                    $results[] = $result;
                    
                    // 更新统计
                    $this->syncStatus->incrementDepartmentCount($result['action']);
                    
                } catch (\Exception $e) {
                    $this->logError('处理部门失败', [
                        'dept_id' => $department['id'],
                        'dept_name' => $department['name'],
                        'error' => $e->getMessage()
                    ]);
                    
                    $this->syncStatus->addSyncError("部门 {$department['name']} 同步失败: " . $e->getMessage());
                    $results[] = [
                        'department_id' => $department['id'],
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            $this->logInfo('部门数据同步完成', [
                'total' => count($departments),
                'processed' => count($results)
            ]);

            return [
                'status' => 1,
                'message' => '部门同步完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('部门同步失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量同步部门数据
     *
     * @param array $departments 企业微信部门数据
     * @param int $batchSize 批次大小
     * @return array 同步结果
     */
    public function syncDepartmentsBatch($departments, $batchSize = 10)
    {
        $this->logInfo('开始分批同步部门数据', ['total_count' => count($departments), 'batch_size' => $batchSize]);
        
        $this->syncStatus->setTotals(count($departments), 0);
        $results = [];
        $batches = array_chunk($departments, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $this->logInfo('处理部门批次', [
                'batch' => $batchIndex + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch)
            ]);

            try {
                $batchResult = $this->syncDepartments($batch);
                $results = array_merge($results, $batchResult['data']);
                
            } catch (\Exception $e) {
                $this->logError('部门批次处理失败', [
                    'batch' => $batchIndex + 1,
                    'error' => $e->getMessage()
                ]);
                
                // 继续处理下一批次
                continue;
            }
        }

        return [
            'status' => 1,
            'message' => '批量部门同步完成',
            'data' => $results
        ];
    }

    /**
     * 处理单个部门
     *
     * @param array $department 企业微信部门数据
     * @param array $existingDepartments 现有企业微信部门
     * @param array $existingSvnGroups 现有SVN分组
     * @return array 处理结果
     */
    public function processDepartment($department, $existingDepartments, &$existingSvnGroups)
    {
        $departmentId = $department['id'];
        $departmentName = $department['name'];
        $parentId = $department['parentid'];

        // 生成 SVN 组名
        $svnGroupName = $this->dataMapper->generateSvnGroupName($department);

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
     * @param array $department 企业微信部门数据
     * @param string $svnGroupName SVN组名
     * @param array $existingSvnGroups 现有SVN分组
     * @return array 创建结果
     */
    public function createDepartment($department, $svnGroupName, &$existingSvnGroups)
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

        // 3. 在 authz 中创建对应的 SVN 组（若不存在）
        if ($this->SVNAdmin) {
            try {
                $authzContent = file_get_contents($this->configSvn['svn_authz_file']);
                $result = $this->SVNAdmin->AddGroup($authzContent, $svnGroupName);
                if (!is_numeric($result)) {
                    // 写回 authz 文件并刷新内存内容
                    funFilePutContents($this->configSvn['svn_authz_file'], $result);
                    if (method_exists('app\\service\\Base', 'RereadAuthz')) {
                        parent::RereadAuthz();
                    }
                }
            } catch (\Exception $e) {
                // 忽略：组已存在等情况由后续逻辑处理
            }
        }

        // 4. 更新SVN组的备注信息
        $this->updateSvnGroupNote($svnGroupId, $departmentName, $departmentId);

        // 5. 不要立即同步，因为SyncGroup会覆盖备注信息
        // 备注信息已经通过 updateSvnGroupNote 直接更新到数据库
        $this->logInfo('部门创建成功，备注信息已直接写入数据库', [
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'svn_group_name' => $svnGroupName,
            'note' => "{$departmentName} (ID: {$departmentId})"
        ]);

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
     * @param array $department 企业微信部门数据
     * @param array $existingDepartment 现有企业微信部门
     * @param string $svnGroupName SVN组名
     * @param array $existingSvnGroups 现有SVN分组
     * @return array 更新结果
     */
    public function updateDepartment($department, $existingDepartment, $svnGroupName, &$existingSvnGroups)
    {
        $departmentId = $department['id'];
        $departmentName = $department['name'];
        $parentId = $department['parentid'];

        $needUpdate = false;
        $updateData = [];

        // 检查部门名称是否变更
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

        // 确保SVN分组映射关系存在且有效（以分组名称为准）
        $svnGroupId = $existingDepartment['svn_group_id'];
        $needReMapping = false;
        $remappingReason = '';
        
        // 首先检查是否存在同名的SVN分组
        $correctSvnGroup = $this->database->get('svn_groups', [
            'svn_group_id',
            'svn_group_name'
        ], [
            'svn_group_name' => $svnGroupName
        ]);
        
        if ($correctSvnGroup) {
            // 存在同名SVN分组，检查映射是否正确
            if (!$svnGroupId || $svnGroupId != $correctSvnGroup['svn_group_id']) {
                $needReMapping = true;
                $remappingReason = $svnGroupId ? 
                    "映射指向错误的分组ID (当前: $svnGroupId, 正确: {$correctSvnGroup['svn_group_id']})" : 
                    "缺少分组映射";
            }
        } else {
            // 不存在同名SVN分组，需要创建
            $needReMapping = true;
            $remappingReason = "不存在同名SVN分组，需要创建";
        }
        
        if ($needReMapping) {
            // 重新建立或创建SVN分组映射
            $this->logInfo('开始为部门重新建立SVN分组映射', [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'current_svn_group_id' => $svnGroupId,
                'target_svn_group_name' => $svnGroupName,
                'remapping_reason' => $remappingReason
            ]);
            
            if ($correctSvnGroup) {
                // 存在同名分组，直接使用
                $svnGroupId = $correctSvnGroup['svn_group_id'];
                $this->logInfo('使用现有的同名SVN分组', [
                    'svn_group_id' => $svnGroupId,
                    'svn_group_name' => $svnGroupName
                ]);
            } else {
                // 不存在同名分组，创建新分组
                $svnGroupId = $this->createOrGetSvnGroup($svnGroupName, $existingSvnGroups);
            }
            
            if (!$svnGroupId) {
                $this->logError('创建或获取SVN分组失败', [
                    'department_id' => $departmentId,
                    'department_name' => $departmentName,
                    'svn_group_name' => $svnGroupName
                ]);
                throw new \Exception("无法为部门 {$departmentName} 创建SVN分组");
            }
            
            // 更新部门记录，建立映射关系
            $updateResult = $this->database->update('wecom_departments', [
                'svn_group_id' => $svnGroupId,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'wecom_dept_id' => $departmentId
            ]);
            
            if ($updateResult === false) {
                $this->logError('更新部门SVN分组映射失败', [
                    'department_id' => $departmentId,
                    'department_name' => $departmentName,
                    'svn_group_id' => $svnGroupId,
                    'database_error' => $this->database->error()
                ]);
                throw new \Exception("无法更新部门 {$departmentName} 的SVN分组映射");
            }
            
            $this->logInfo('SVN分组映射已重新建立', [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'old_svn_group_id' => $existingDepartment['svn_group_id'],
                'new_svn_group_id' => $svnGroupId,
                'svn_group_name' => $svnGroupName,
                'remapping_reason' => $remappingReason,
                'update_result' => $updateResult
            ]);
            
            $needUpdate = true;
            $action = 'remapped';
            $message = '部门SVN分组映射已重新建立';
        }

        // 无论部门信息是否有变化，都更新SVN组的备注时间（反映最后同步时间）
        if ($svnGroupId) {
            $this->updateSvnGroupNote($svnGroupId, $departmentName, $departmentId);
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
     * 创建或获取SVN组
     *
     * @param string $groupName 组名
     * @param array $existingSvnGroups 现有SVN分组
     * @return int SVN组ID
     * @throws \Exception
     */
    public function createOrGetSvnGroup($groupName, &$existingSvnGroups)
    {
        // 检查组是否已存在
        foreach ($existingSvnGroups as $group) {
            if ($group['svn_group_name'] === $groupName) {
                return $group['svn_group_id'];
            }
        }

        // 使用SVNAdmin原生API创建分组
        try {
            $this->logInfo('开始使用SVNAdmin原生API创建SVN分组', [
                'svn_group_name' => $groupName
            ]);
            
            // 使用SVNAdmin添加组到authz文件
            if ($this->SVNAdmin) {
                $authzContent = file_get_contents($this->configSvn['svn_authz_file']);
                $result = $this->SVNAdmin->AddGroup($authzContent, $groupName);
                if (!is_numeric($result)) {
                    // 写回 authz 文件
                    funFilePutContents($this->configSvn['svn_authz_file'], $result);
                    if (method_exists('app\\service\\Base', 'RereadAuthz')) {
                        parent::RereadAuthz();
                    }
                    
                    $this->logInfo('SVNAdmin API创建分组成功，authz文件已更新', [
                        'svn_group_name' => $groupName
                    ]);
                } else {
                    $this->logError('SVNAdmin API创建分组失败', [
                        'svn_group_name' => $groupName,
                        'error_code' => $result
                    ]);
                    throw new \Exception("SVNAdmin API创建分组失败: {$groupName}, 错误码: {$result}");
                }
            } else {
                $this->logError('SVNAdmin对象未初始化', [
                    'svn_group_name' => $groupName
                ]);
                throw new \Exception("SVNAdmin对象未初始化，无法创建分组: {$groupName}");
            }

            // 直接在数据库中创建分组记录，避免过早同步导致丢失成员信息
            $this->logInfo('直接创建分组数据库记录', [
                'svn_group_name' => $groupName
            ]);
            
            // 检查是否已存在
            $existingId = $this->database->get('svn_groups', 'svn_group_id', [
                'svn_group_name' => $groupName
            ]);
            
            if ($existingId) {
                $insertedGroup = $existingId;
            } else {
                // 创建新记录
                $this->database->insert('svn_groups', [
                    'svn_group_name' => $groupName,
                    'svn_group_note' => '',  // 备注稍后在同步流程中更新
                    'include_user_count' => 0,
                    'include_group_count' => 0,
                    'include_aliase_count' => 0
                ]);
                
                $insertedGroup = $this->database->id();
                
                if ($insertedGroup) {
                    $this->logInfo('分组记录创建成功', [
                        'svn_group_name' => $groupName,
                        'svn_group_id' => $insertedGroup
                    ]);
                }
            }

            if (!$insertedGroup) {
                $this->logError('创建分组后无法在数据库中找到记录', [
                    'svn_group_name' => $groupName
                ]);
                throw new \Exception("创建分组后无法在数据库中找到记录: {$groupName}");
            }
            
            $svnGroupId = $insertedGroup;

            // 暂时不设置备注，等待后续由调用方设置正确的备注
            // 备注将在 createDepartment 中通过 updateSvnGroupNote 设置

            // 更新现有组列表
            $existingSvnGroups[] = [
                'svn_group_id' => $svnGroupId,
                'svn_group_name' => $groupName,
                'svn_group_note' => '' // 备注将在后续设置
            ];

            $this->logInfo('使用SVNAdmin原生API创建SVN组成功', [
                'svn_group_id' => $svnGroupId,
                'svn_group_name' => $groupName
            ]);

            return $svnGroupId;

        } catch (\Exception $e) {
            $this->logError('创建SVN组失败', [
                'svn_group_name' => $groupName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 更新SVN组备注
     *
     * @param int $svnGroupId SVN组ID
     * @param string $departmentName 部门名称
     * @param int $departmentId 部门ID
     */
    public function updateSvnGroupNote($svnGroupId, $departmentName, $departmentId)
    {
        try {
            $note = $this->dataMapper->generateGroupNote($departmentName, $departmentId);
            
            $this->database->update('svn_groups', [
                'svn_group_note' => $note
            ], [
                'svn_group_id' => $svnGroupId
            ]);

            $this->logInfo('更新SVN组备注成功', [
                'svn_group_id' => $svnGroupId,
                'note' => $note
            ]);

        } catch (\Exception $e) {
            $this->syncStatus->addSyncWarning("更新组备注失败: {$departmentName}");
            $this->logError('更新SVN组备注失败', [
                'svn_group_id' => $svnGroupId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 同步分组成员关系
     *
     * @param array $departments 部门数据
     * @param array $users 用户数据
     * @return array 同步结果
     */
    public function syncGroupMembers($departments, $users)
    {
        $this->logInfo('开始统一同步分组成员关系');

        $results = [];
        $processedGroups = 0;

        try {
            // 首先修复部门映射不一致问题
            $this->fixDepartmentMappingInconsistencies();
            
            // 获取部门和用户映射关系
            // 注意：此时数据完整性已在上层验证，可以直接获取映射
            $this->logInfo('获取部门和用户映射关系');
            $deptToGroupMap = $this->buildDepartmentGroupMapping();
            $userToSvnUserMap = $this->buildUserSvnUserMapping();
            
            $this->logInfo('映射关系统计', [
                'department_mappings' => count($deptToGroupMap),
                'user_mappings' => count($userToSvnUserMap)
            ]);

            // 读取当前authz文件内容
            $authzContent = file_get_contents($this->configSvn['svn_authz_file']);
            $updatedAuthzContent = $authzContent;
            $hasChanges = false;

            foreach ($departments as $dept) {
                $deptId = $dept['id'];
                $deptName = $dept['name'];

                if (!isset($deptToGroupMap[$deptId])) {
                    $this->logInfo('部门未找到SVN分组映射，跳过成员同步', [
                        'department_id' => $deptId,
                        'department_name' => $deptName
                    ]);
                    continue;
                }

                $svnGroupId = $deptToGroupMap[$deptId]['svn_group_id'];
                $svnGroupName = $deptToGroupMap[$deptId]['svn_group_name'];

                // 获取该部门的用户
                $deptUsers = $this->getDepartmentUsers($deptId, $users);
                $svnUserNames = [];

                foreach ($deptUsers as $user) {
                    $userId = $user['userid'];
                    if (isset($userToSvnUserMap[$userId])) {
                        $svnUserNames[] = $userToSvnUserMap[$userId]['svn_user_name'];
                    }
                }

                $this->logInfo('准备同步分组成员', [
                    'department_id' => $deptId,
                    'department_name' => $deptName,
                    'svn_group_name' => $svnGroupName,
                    'wecom_dept_users_count' => count($deptUsers),
                    'mapped_svn_users_count' => count($svnUserNames),
                    'expected_svn_users' => $svnUserNames
                ]);

                // 强制同步模式：确保分组中只包含企业微信部门中的用户
                // 这会移除所有不属于当前企业微信部门的用户（包括手动添加的）
                $syncResult = $this->syncGroupMembersComplete($updatedAuthzContent, $svnGroupName, $svnUserNames);

                if ($syncResult['success']) {
                    $updatedAuthzContent = $syncResult['authz_content'];
                    if ($syncResult['has_changes']) {
                        $hasChanges = true;
                    }

                    // 同步子分组关系
                    $subGroupSyncResult = $this->syncGroupSubGroups($updatedAuthzContent, $svnGroupName, $deptId);
                    if ($subGroupSyncResult['success']) {
                        $updatedAuthzContent = $subGroupSyncResult['authz_content'];
                        if ($subGroupSyncResult['has_changes']) {
                            $hasChanges = true;
                        }
                    }

                    $results[] = [
                        'department_id' => $deptId,
                        'department_name' => $deptName,
                        'svn_group_name' => $svnGroupName,
                        'expected_members' => count($svnUserNames),
                        'added_members' => $syncResult['added_count'],
                        'removed_members' => $syncResult['removed_count'] + ($subGroupSyncResult['removed_count'] ?? 0),
                        'final_members' => $syncResult['final_members'],
                        'action' => 'members_synced'
                    ];
                    
                    $processedGroups++;
                } else {
                    $results[] = [
                        'department_id' => $deptId,
                        'department_name' => $deptName,
                        'svn_group_name' => $svnGroupName,
                        'error' => $syncResult['error'],
                        'action' => 'sync_failed'
                    ];
                }
            }

            // 统一写回authz文件（只写一次，提高效率）
            if ($hasChanges) {
                funFilePutContents($this->configSvn['svn_authz_file'], $updatedAuthzContent);
                if (method_exists('app\\service\\Base', 'RereadAuthz')) {
                    parent::RereadAuthz();
                }
                
                $this->logInfo('authz文件已统一更新，所有分组成员已分配', [
                    'processed_groups' => $processedGroups
                ]);

                // 不执行 SyncGroup，避免覆盖备注信息
                // authz 文件已更新，成员关系已正确设置
                $this->logInfo('成员关系已写入authz文件，保留数据库中的备注信息');
            }

            $this->logInfo('分组成员关系同步完成', [
                'processed_groups' => $processedGroups,
                'total_results' => count($results),
                'has_changes' => $hasChanges
            ]);

            return [
                'status' => 1,
                'message' => '分组成员同步完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('分组成员同步失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步分组层级关系
     *
     * @param array $departments 部门数据
     * @return array 同步结果
     */
    public function syncGroupHierarchy($departments)
    {
        $this->logInfo('开始同步分组层级关系');

        $results = [];

        try {
            // 构建部门层级映射
            $hierarchy = $this->dataMapper->buildDepartmentHierarchy($departments);

            foreach ($departments as $dept) {
                $deptId = $dept['id'];
                $parentId = $dept['parentid'];

                // 跳过根部门
                if ($parentId == 0 || $parentId == 1) {
                    continue;
                }

                // 动态获取最新的部门到SVN组映射（确保包含刚创建的映射）
                $deptToGroupMap = $this->buildDepartmentGroupMapping();

                if (!isset($deptToGroupMap[$deptId]) || !isset($deptToGroupMap[$parentId])) {
                    $this->logInfo('部门或父部门未找到SVN分组映射，跳过层级同步', [
                        'department_id' => $deptId,
                        'parent_id' => $parentId
                    ]);
                    continue;
                }

                $childGroupName = $deptToGroupMap[$deptId]['svn_group_name'];
                $parentGroupName = $deptToGroupMap[$parentId]['svn_group_name'];

                // 在SVN中建立组的包含关系（父组包含子组）
                if ($this->SVNAdmin) {
                    try {
                        $authzContent = file_get_contents($this->configSvn['svn_authz_file']);
                        $res = $this->SVNAdmin->UpdGroupMember($authzContent, $parentGroupName, $childGroupName, 'group', 'add');
                        if (!is_numeric($res)) {
                            funFilePutContents($this->configSvn['svn_authz_file'], $res);
                            if (method_exists('app\\service\\Base', 'RereadAuthz')) {
                                parent::RereadAuthz();
                            }
                        }

                        $this->logInfo('建立分组层级关系成功', [
                            'parent_group' => $parentGroupName,
                            'child_group' => $childGroupName,
                            'dept_id' => $deptId,
                            'parent_dept_id' => $parentId
                        ]);

                        $results[] = [
                            'department_id' => $deptId,
                            'parent_department_id' => $parentId,
                            'child_group' => $childGroupName,
                            'parent_group' => $parentGroupName,
                            'action' => 'linked',
                            'message' => '层级关系建立成功'
                        ];

                    } catch (\Exception $e) {
                        $this->logError('建立分组层级关系失败', [
                            'parent_group' => $parentGroupName,
                            'child_group' => $childGroupName,
                            'error' => $e->getMessage()
                        ]);

                        $results[] = [
                            'department_id' => $deptId,
                            'action' => 'error',
                            'message' => '层级关系建立失败: ' . $e->getMessage()
                        ];
                    }
                }
            }

            $this->logInfo('分组层级关系同步完成', [
                'processed_relations' => count($results)
            ]);

            return [
                'status' => 1,
                'message' => '分组层级关系同步完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('分组层级关系同步失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取现有企业微信部门
     *
     * @return array 部门ID为键的部门数组
     */
    private function getExistingDepartments()
    {
        try {
            $departments = $this->database->select('wecom_departments', '*');
            $result = [];
            
            foreach ($departments as $dept) {
                $result[$dept['wecom_dept_id']] = $dept;
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('获取现有企业微信部门失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 获取现有SVN分组
     *
     * @return array SVN分组列表
     */
    private function getExistingSvnGroups()
    {
        try {
            return $this->database->select('svn_groups', [
                'svn_group_id',
                'svn_group_name',
                'svn_group_note'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('获取现有SVN分组失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 修复部门映射不一致问题
     * 确保所有部门都有有效的SVN分组映射
     */
    private function fixDepartmentMappingInconsistencies()
    {
        $this->logInfo('开始修复部门映射不一致问题');
        
        try {
            // 查找所有映射不一致的部门
            $inconsistentDepts = $this->database->query(
                "SELECT wd.wecom_dept_id, wd.dept_name, wd.svn_group_id, sg.svn_group_name
                 FROM wecom_departments wd
                 LEFT JOIN svn_groups sg ON wd.svn_group_id = sg.svn_group_id
                 WHERE wd.svn_group_id IS NOT NULL AND sg.svn_group_id IS NULL"
            )->fetchAll();
            
            if (empty($inconsistentDepts)) {
                $this->logInfo('没有发现映射不一致的部门');
                return;
            }
            
            $this->logInfo('发现映射不一致的部门', ['count' => count($inconsistentDepts)]);
            
            foreach ($inconsistentDepts as $dept) {
                $deptId = $dept['wecom_dept_id'];
                $deptName = $dept['dept_name'];
                $invalidSvnGroupId = $dept['svn_group_id'];
                
                $this->logInfo('修复部门映射不一致', [
                    'department_id' => $deptId,
                    'department_name' => $deptName,
                    'invalid_svn_group_id' => $invalidSvnGroupId
                ]);
                
                // 查找是否存在同名的SVN分组
                $correctSvnGroup = $this->database->get('svn_groups', [
                    'svn_group_id',
                    'svn_group_name'
                ], [
                    'svn_group_name' => $deptName
                ]);
                
                if ($correctSvnGroup) {
                    // 存在同名分组，更新映射
                    $updateResult = $this->database->update('wecom_departments', [
                        'svn_group_id' => $correctSvnGroup['svn_group_id'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'wecom_dept_id' => $deptId
                    ]);
                    
                    if ($updateResult !== false) {
                        $this->logInfo('部门映射修复成功', [
                            'department_id' => $deptId,
                            'department_name' => $deptName,
                            'old_svn_group_id' => $invalidSvnGroupId,
                            'new_svn_group_id' => $correctSvnGroup['svn_group_id']
                        ]);
                    } else {
                        $this->logError('部门映射修复失败', [
                            'department_id' => $deptId,
                            'department_name' => $deptName,
                            'database_error' => $this->database->error()
                        ]);
                    }
                } else {
                    // 不存在同名分组，需要创建
                    $this->logInfo('部门对应的SVN分组不存在，需要创建', [
                        'department_id' => $deptId,
                        'department_name' => $deptName
                    ]);
                    
                    // 使用SVNAdmin API创建分组
                    if ($this->SVNAdmin) {
                        try {
                            $addResult = $this->SVNAdmin->AddGroup($deptName, '');
                            if ($addResult['status'] == 1) {
                                // 同步到数据库
                                if ($this->ServiceSvngroup) {
                                    $syncResult = $this->ServiceSvngroup->SyncGroup();
                                    if ($syncResult['status'] == 1) {
                                        // 重新查找创建的分组
                                        $newSvnGroup = $this->database->get('svn_groups', [
                                            'svn_group_id',
                                            'svn_group_name'
                                        ], [
                                            'svn_group_name' => $deptName
                                        ]);
                                        
                                        if ($newSvnGroup) {
                                            // 更新部门映射
                                            $updateResult = $this->database->update('wecom_departments', [
                                                'svn_group_id' => $newSvnGroup['svn_group_id'],
                                                'updated_at' => date('Y-m-d H:i:s')
                                            ], [
                                                'wecom_dept_id' => $deptId
                                            ]);
                                            
                                            if ($updateResult !== false) {
                                                $this->logInfo('部门SVN分组创建并映射成功', [
                                                    'department_id' => $deptId,
                                                    'department_name' => $deptName,
                                                    'new_svn_group_id' => $newSvnGroup['svn_group_id']
                                                ]);
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logError('创建SVN分组失败', [
                                'department_name' => $deptName,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->logError('修复部门映射不一致失败', $e->getMessage());
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
                // 只包含有效的映射（svn_group_name不为空）
                if (!empty($dept['svn_group_name'])) {
                    $mapping[$dept['wecom_dept_id']] = [
                        'svn_group_id' => $dept['svn_group_id'],
                        'svn_group_name' => $dept['svn_group_name']
                    ];
                }
            }

            return $mapping;

        } catch (\Exception $e) {
            $this->logError('构建部门组映射失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 完整的分组成员同步（包括添加和删除）
     * 
     * @param string $authzContent authz文件内容
     * @param string $groupName 分组名称
     * @param array $expectedMembers 应该有的成员列表
     * @return array 同步结果
     */
    private function syncGroupMembersComplete($authzContent, $groupName, $expectedMembers)
    {
        try {
            // 获取分组当前成员
            $currentMembers = $this->getGroupCurrentMembers($authzContent, $groupName);
            
            // 计算需要添加和删除的成员
            $membersToAdd = array_diff($expectedMembers, $currentMembers);
            $membersToRemove = array_diff($currentMembers, $expectedMembers);
            
            $this->logInfo('分组成员同步分析', [
                'group_name' => $groupName,
                'current_members' => $currentMembers,
                'expected_members' => $expectedMembers,
                'members_to_add' => $membersToAdd,
                'members_to_remove' => $membersToRemove,
                'sync_mode' => '强制模式：移除所有不属于企业微信的成员'
            ]);
            
            $updatedAuthzContent = $authzContent;
            $hasChanges = false;
            $addedCount = 0;
            $removedCount = 0;
            
            // 删除不应该存在的成员（包括手动添加但不属于企业微信的成员）
            foreach ($membersToRemove as $memberToRemove) {
                $this->logInfo('准备从分组删除用户', [
                    'group_name' => $groupName,
                    'user_name' => $memberToRemove,
                    'reason' => '该用户不属于企业微信对应部门'
                ]);

                if ($this->SVNAdmin) {
                    $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $memberToRemove, 'user', 'delete');
                    if (!is_numeric($result)) {
                        $updatedAuthzContent = $result;
                        $hasChanges = true;
                        $removedCount++;
                        $this->logInfo('成功从分组删除用户', [
                            'group_name' => $groupName,
                            'user_name' => $memberToRemove,
                            'result' => '删除成功'
                        ]);
                    } else {
                        // 错误码402表示用户不在组中，这也是可以接受的
                        if ($result == 402) {
                            $this->logInfo('用户不在分组中，无需删除', [
                                'group_name' => $groupName,
                                'user_name' => $memberToRemove,
                                'error_code' => $result
                            ]);
                        } else {
                            $this->logError('从分组删除用户失败', [
                                'group_name' => $groupName,
                                'user_name' => $memberToRemove,
                                'error_code' => $result
                            ]);
                        }
                    }
                }
            }
            
            // 添加缺少的成员
            foreach ($membersToAdd as $memberToAdd) {
                if ($this->SVNAdmin) {
                    $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $memberToAdd, 'user', 'add');
                    if (!is_numeric($result)) {
                        $updatedAuthzContent = $result;
                        $hasChanges = true;
                        $addedCount++;
                        $this->logInfo('成功向分组添加用户', [
                            'group_name' => $groupName,
                            'user_name' => $memberToAdd
                        ]);
                    } else {
                        // 错误码803表示用户已存在，这是正常的
                        if ($result == 803) {
                            $this->logInfo('用户已在分组中，跳过', [
                                'group_name' => $groupName,
                                'user_name' => $memberToAdd
                            ]);
                        } else {
                            $this->logError('向分组添加用户失败', [
                                'group_name' => $groupName,
                                'user_name' => $memberToAdd,
                                'error_code' => $result
                            ]);
                        }
                    }
                }
            }
            
            // 获取最终成员列表
            $finalMembers = $this->getGroupCurrentMembers($updatedAuthzContent, $groupName);
            
            return [
                'success' => true,
                'authz_content' => $updatedAuthzContent,
                'has_changes' => $hasChanges,
                'added_count' => $addedCount,
                'removed_count' => $removedCount,
                'final_members' => $finalMembers
            ];
            
        } catch (\Exception $e) {
            $this->logError('分组成员完整同步失败', [
                'group_name' => $groupName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'authz_content' => $authzContent,
                'has_changes' => false,
                'added_count' => 0,
                'removed_count' => 0,
                'final_members' => []
            ];
        }
    }
    
    /**
     * 获取分组当前成员列表
     * 
     * @param string $authzContent authz文件内容
     * @param string $groupName 分组名称
     * @return array 当前成员列表
     */
    private function getGroupCurrentMembers($authzContent, $groupName)
    {
        if (preg_match('/^' . preg_quote($groupName, '/') . '=([^\r\n]*)/m', $authzContent, $matches)) {
            $membersString = trim($matches[1]);
            if (empty($membersString)) {
                return [];
            }
            return array_filter(array_map('trim', explode(',', $membersString)));
        }
        return [];
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
     * 获取部门用户
     *
     * @param int $deptId 部门ID
     * @param array $allUsers 所有用户
     * @return array 部门用户列表
     */
    private function getDepartmentUsers($deptId, $allUsers)
    {
        $deptUsers = [];
        
        foreach ($allUsers as $user) {
            $userDepts = $user['department'] ?? [];
            if (in_array($deptId, $userDepts)) {
                $deptUsers[] = $user;
            }
        }
        
        return $deptUsers;
    }

    /**
     * 获取指定部门应该包含的子分组
     *
     * @param int $parentDeptId 父部门ID
     * @return array 子分组名称列表
     */
    private function getExpectedSubGroups($parentDeptId)
    {
        try {
            // 查找该部门的子部门对应的SVN分组
            $childGroups = $this->database->select('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'svn_groups.svn_group_name'
            ], [
                'wecom_departments.parent_id' => $parentDeptId,
                'svn_groups.svn_group_name[!]' => null
            ]);

            return array_column($childGroups, 'svn_group_name');

        } catch (\Exception $e) {
            $this->logError('获取预期子分组失败', [
                'parent_dept_id' => $parentDeptId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 同步分组的子分组关系
     *
     * @param string $authzContent authz文件内容
     * @param string $groupName 分组名称
     * @param int $deptId 部门ID
     * @return array 同步结果
     */
    private function syncGroupSubGroups($authzContent, $groupName, $deptId)
    {
        try {
            // 获取当前分组信息
            $groupInfo = $this->SVNAdmin->GetGroupInfo($authzContent, $groupName);

            if (is_numeric($groupInfo)) {
                return [
                    'success' => false,
                    'authz_content' => $authzContent,
                    'has_changes' => false,
                    'added_count' => 0,
                    'removed_count' => 0,
                    'final_subgroups' => []
                ];
            }

            $currentSubGroups = $groupInfo['include']['groups']['list'] ?? [];
            $expectedSubGroups = $this->getExpectedSubGroups($deptId);

            $subGroupsToAdd = array_diff($expectedSubGroups, $currentSubGroups);
            $subGroupsToRemove = array_diff($currentSubGroups, $expectedSubGroups);

            $this->logInfo('分组子分组同步分析', [
                'group_name' => $groupName,
                'current_subgroups' => $currentSubGroups,
                'expected_subgroups' => $expectedSubGroups,
                'subgroups_to_add' => $subGroupsToAdd,
                'subgroups_to_remove' => $subGroupsToRemove
            ]);

            $updatedAuthzContent = $authzContent;
            $hasChanges = false;
            $addedCount = 0;
            $removedCount = 0;

            // 移除不应该存在的子分组
            foreach ($subGroupsToRemove as $subGroupToRemove) {
                $this->logInfo('准备从分组删除子分组', [
                    'group_name' => $groupName,
                    'subgroup_name' => $subGroupToRemove,
                    'reason' => '该子分组不属于企业微信对应部门'
                ]);

                if ($this->SVNAdmin) {
                    $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $subGroupToRemove, 'group', 'delete');
                    if (!is_numeric($result)) {
                        $updatedAuthzContent = $result;
                        $hasChanges = true;
                        $removedCount++;
                        $this->logInfo('成功从分组删除子分组', [
                            'group_name' => $groupName,
                            'subgroup_name' => $subGroupToRemove
                        ]);
                    } else {
                        $this->logError('从分组删除子分组失败', [
                            'group_name' => $groupName,
                            'subgroup_name' => $subGroupToRemove,
                            'error_code' => $result
                        ]);
                    }
                }
            }

            // 添加缺少的子分组
            foreach ($subGroupsToAdd as $subGroupToAdd) {
                $this->logInfo('准备向分组添加子分组', [
                    'group_name' => $groupName,
                    'subgroup_name' => $subGroupToAdd
                ]);

                if ($this->SVNAdmin) {
                    $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $groupName, $subGroupToAdd, 'group', 'add');
                    if (!is_numeric($result)) {
                        $updatedAuthzContent = $result;
                        $hasChanges = true;
                        $addedCount++;
                        $this->logInfo('成功向分组添加子分组', [
                            'group_name' => $groupName,
                            'subgroup_name' => $subGroupToAdd
                        ]);
                    } else {
                        if ($result == 803) {
                            $this->logInfo('子分组已在分组中，跳过', [
                                'group_name' => $groupName,
                                'subgroup_name' => $subGroupToAdd
                            ]);
                        } else {
                            $this->logError('向分组添加子分组失败', [
                                'group_name' => $groupName,
                                'subgroup_name' => $subGroupToAdd,
                                'error_code' => $result
                            ]);
                        }
                    }
                }
            }

            // 获取最终子分组列表
            $finalGroupInfo = $this->SVNAdmin->GetGroupInfo($updatedAuthzContent, $groupName);
            $finalSubGroups = [];
            if (!is_numeric($finalGroupInfo)) {
                $finalSubGroups = $finalGroupInfo['include']['groups']['list'] ?? [];
            }

            return [
                'success' => true,
                'authz_content' => $updatedAuthzContent,
                'has_changes' => $hasChanges,
                'added_count' => $addedCount,
                'removed_count' => $removedCount,
                'final_subgroups' => $finalSubGroups,
                'changes' => [
                    'subgroups_added' => $subGroupsToAdd,
                    'subgroups_removed' => $subGroupsToRemove
                ]
            ];

        } catch (\Exception $e) {
            $this->logError('分组子分组同步失败', [
                'group_name' => $groupName,
                'dept_id' => $deptId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'authz_content' => $authzContent,
                'has_changes' => false,
                'added_count' => 0,
                'removed_count' => 0,
                'final_subgroups' => []
            ];
        }
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComGroupSync] ' . $message;
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
            $logMessage = '[WeComGroupSync ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . (is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : $error);
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }

    /**
     * 执行纯SVNAdmin方法的成员同步
     * 基于现有数据库映射关系，不依赖API
     *
     * @return array 同步结果
     */
    public function executePureSync()
    {
        $this->logInfo('开始执行纯SVNAdmin方法的企业微信同步');

        $results = [];
        $totalProcessed = 0;
        $totalChanges = 0;

        try {
            // 1. 获取所有有企业微信映射的分组
            $mappedGroups = $this->database->select('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'wecom_departments.wecom_dept_id',
                'wecom_departments.dept_name',
                'svn_groups.svn_group_name',
                'wecom_departments.parent_id'
            ], [
                'svn_groups.svn_group_name[!]' => null
            ]);

            $this->logInfo('找到有企业微信映射的分组', ['count' => count($mappedGroups)]);

            if (empty($mappedGroups)) {
                return [
                    'status' => 1,
                    'message' => '没有找到需要同步的分组',
                    'processed_groups' => 0,
                    'total_changes' => 0,
                    'results' => []
                ];
            }

            // 2. 读取当前authz文件
            $authzContent = file_get_contents($this->configSvn['svn_authz_file']);
            $updatedAuthzContent = $authzContent;

            // 3. 逐个处理每个分组
            foreach ($mappedGroups as $group) {
                $deptId = $group['wecom_dept_id'];
                $deptName = $group['dept_name'];
                $svnGroupName = $group['svn_group_name'];

                $this->logInfo('处理分组', [
                    'svn_group_name' => $svnGroupName,
                    'dept_name' => $deptName,
                    'dept_id' => $deptId
                ]);

                // 3.1 获取该分组当前的成员和子分组
                $currentGroupInfo = $this->SVNAdmin->GetGroupInfo($updatedAuthzContent, $svnGroupName);

                if (is_numeric($currentGroupInfo)) {
                    $this->logInfo('无法获取分组信息，跳过', [
                        'svn_group_name' => $svnGroupName,
                        'error_code' => $currentGroupInfo
                    ]);
                    continue;
                }

                $currentUsers = $currentGroupInfo['include']['users']['list'] ?? [];
                $currentSubGroups = $currentGroupInfo['include']['groups']['list'] ?? [];

                // 3.2 获取应该有的用户（从企业微信映射）
                $expectedUsers = $this->getExpectedUsersForDept($deptId);

                // 3.3 获取应该有的子分组（该部门的子部门）
                $expectedSubGroups = $this->getExpectedSubGroupsForDept($deptId);

                // 3.4 计算差异
                $usersToAdd = array_diff($expectedUsers, $currentUsers);
                $usersToRemove = array_diff($currentUsers, $expectedUsers);
                $subGroupsToAdd = array_diff($expectedSubGroups, $currentSubGroups);
                $subGroupsToRemove = array_diff($currentSubGroups, $expectedSubGroups);

                $changeCount = count($usersToAdd) + count($usersToRemove) + count($subGroupsToAdd) + count($subGroupsToRemove);

                if ($changeCount > 0) {
                    $this->logInfo('需要变更', [
                        'svn_group_name' => $svnGroupName,
                        'users_to_remove' => $usersToRemove,
                        'users_to_add' => $usersToAdd,
                        'subgroups_to_remove' => $subGroupsToRemove,
                        'subgroups_to_add' => $subGroupsToAdd
                    ]);

                    // 3.5 移除不应该存在的用户
                    foreach ($usersToRemove as $userToRemove) {
                        $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $svnGroupName, $userToRemove, 'user', 'delete');
                        if (!is_numeric($result)) {
                            $updatedAuthzContent = $result;
                            $this->logInfo('移除用户成功', [
                                'group' => $svnGroupName,
                                'user' => $userToRemove
                            ]);
                        }
                    }

                    // 3.6 添加缺少的用户
                    foreach ($usersToAdd as $userToAdd) {
                        $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $svnGroupName, $userToAdd, 'user', 'add');
                        if (!is_numeric($result)) {
                            $updatedAuthzContent = $result;
                            $this->logInfo('添加用户成功', [
                                'group' => $svnGroupName,
                                'user' => $userToAdd
                            ]);
                        }
                    }

                    // 3.7 移除不应该存在的子分组
                    foreach ($subGroupsToRemove as $subGroupToRemove) {
                        $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $svnGroupName, $subGroupToRemove, 'group', 'delete');
                        if (!is_numeric($result)) {
                            $updatedAuthzContent = $result;
                            $this->logInfo('移除子分组成功', [
                                'group' => $svnGroupName,
                                'subgroup' => $subGroupToRemove
                            ]);
                        }
                    }

                    // 3.8 添加缺少的子分组
                    foreach ($subGroupsToAdd as $subGroupToAdd) {
                        $result = $this->SVNAdmin->UpdGroupMember($updatedAuthzContent, $svnGroupName, $subGroupToAdd, 'group', 'add');
                        if (!is_numeric($result)) {
                            $updatedAuthzContent = $result;
                            $this->logInfo('添加子分组成功', [
                                'group' => $svnGroupName,
                                'subgroup' => $subGroupToAdd
                            ]);
                        }
                    }

                    $totalChanges += $changeCount;
                    $results[] = [
                        'svn_group_name' => $svnGroupName,
                        'dept_name' => $deptName,
                        'changes_made' => $changeCount,
                        'users_removed' => count($usersToRemove),
                        'users_added' => count($usersToAdd),
                        'subgroups_removed' => count($subGroupsToRemove),
                        'subgroups_added' => count($subGroupsToAdd)
                    ];
                }

                $totalProcessed++;
            }

            // 4. 保存更新后的authz文件
            if ($authzContent !== $updatedAuthzContent) {
                funFilePutContents($this->configSvn['svn_authz_file'], $updatedAuthzContent);
                if (method_exists('app\\service\\Base', 'RereadAuthz')) {
                    parent::RereadAuthz();
                }
                $this->logInfo('authz文件已更新');
            }

            $this->logInfo('纯SVNAdmin同步完成', [
                'processed_groups' => $totalProcessed,
                'total_changes' => $totalChanges
            ]);

            return [
                'status' => 1,
                'message' => '纯SVNAdmin同步完成',
                'processed_groups' => $totalProcessed,
                'total_changes' => $totalChanges,
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('纯SVNAdmin同步失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '同步失败: ' . $e->getMessage(),
                'processed_groups' => $totalProcessed,
                'total_changes' => $totalChanges,
                'results' => $results
            ];
        }
    }

    /**
     * 获取部门应该有的用户列表
     *
     * @param int $deptId 部门ID
     * @return array 用户SVN用户名列表
     */
    private function getExpectedUsersForDept($deptId)
    {
        try {
            // 获取所有有SVN映射的企业微信用户
            $allUsers = $this->database->select('wecom_users', [
                '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
            ], [
                'wecom_users.department_ids',
                'svn_users.svn_user_name'
            ], [
                'svn_users.svn_user_name[!]' => null
            ]);

            // 过滤出属于指定部门的用户
            $deptUsers = [];
            foreach ($allUsers as $user) {
                $deptIds = json_decode($user['department_ids'], true);
                if (is_array($deptIds) && in_array($deptId, $deptIds)) {
                    $deptUsers[] = $user['svn_user_name'];
                }
            }

            $this->logInfo('获取部门用户', [
                'dept_id' => $deptId,
                'total_users' => count($allUsers),
                'dept_users' => count($deptUsers),
                'user_list' => $deptUsers
            ]);

            return $deptUsers;

        } catch (\Exception $e) {
            $this->logError('获取部门用户失败', [
                'dept_id' => $deptId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取部门应该有的子分组列表
     *
     * @param int $parentDeptId 父部门ID
     * @return array 子分组名称列表
     */
    private function getExpectedSubGroupsForDept($parentDeptId)
    {
        try {
            $childGroups = $this->database->select('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'svn_groups.svn_group_name'
            ], [
                'wecom_departments.parent_id' => $parentDeptId,
                'svn_groups.svn_group_name[!]' => null
            ]);

            return array_column($childGroups, 'svn_group_name');

        } catch (\Exception $e) {
            $this->logError('获取部门子分组失败', [
                'parent_dept_id' => $parentDeptId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
