<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信管理控制器
 */

namespace app\controller;

use app\service\WeComAPI as ServiceWeComAPI;
use app\service\WeComSync as ServiceWeComSync;
use app\service\WeComNotification as ServiceWeComNotification;
use app\service\WeComConfig;

class WeComAdmin extends Base
{
    /**
     * 服务层对象
     *
     * @var object
     */
    private $ServiceWeComAPI;
    private $ServiceWeComSync;
    private $ServiceWeComNotification;

    function __construct($parm)
    {
        parent::__construct($parm);

        try {
            $this->ServiceWeComAPI = new ServiceWeComAPI($parm);
            $this->ServiceWeComSync = new ServiceWeComSync($parm);
            $this->ServiceWeComNotification = new ServiceWeComNotification($parm);
        } catch (\Exception $e) {
            // 如果企业微信功能未启用，某些服务可能无法初始化
            // 这里不抛出异常，而是在具体方法中检查
        }
    }

    /**
     * 获取企业微信配置信息
     */
    public function GetConfig()
    {
        try {
            $wecomConfig = WeComConfig::getConfig();
            
            // 隐藏敏感信息
            $safeConfig = $wecomConfig;
            if (isset($safeConfig['corp_secret'])) {
                $safeConfig['corp_secret'] = $this->maskSensitiveData($safeConfig['corp_secret']);
            }
            if (isset($safeConfig['aes_key'])) {
                $safeConfig['aes_key'] = $this->maskSensitiveData($safeConfig['aes_key']);
            }
            if (isset($safeConfig['token'])) {
                $safeConfig['token'] = $this->maskSensitiveData($safeConfig['token']);
            }

            json1(200, 1, '获取配置成功', $safeConfig);
        } catch (\Exception $e) {
            json1(200, 0, '获取配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新企业微信配置
     */
    public function UpdateConfig()
    {
        try {
            $configData = ($this->param['payload'] ?? [])['config_data'] ?? [];
            
            if (empty($configData)) {
                json1(200, 0, '配置数据不能为空');
            }

            // 验证必要字段
            $requiredFields = ['corp_id', 'agent_id'];
            foreach ($requiredFields as $field) {
                if (empty($configData[$field])) {
                    json1(200, 0, "缺少必要字段: {$field}");
                }
            }

            // 读取现有配置
            $currentConfig = WeComConfig::getConfig();

            // 对敏感字段做保护：如果前端传回的是掩码值或空，则保留现有值
            $sensitiveKeys = ['corp_secret', 'aes_key', 'token'];
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $configData)) {
                    $val = (string)$configData[$key];
                    if ($val === '' || strpos($val, '*') !== false) {
                        // 使用旧值
                        $configData[$key] = $currentConfig[$key] ?? '';
                    }
                }
            }

            // 合并配置
            $newConfig = array_merge($currentConfig, $configData);

            // 使用新的配置管理服务保存配置
            if (!WeComConfig::saveConfig($newConfig)) {
                json1(200, 0, '配置保存失败');
            }

            json1(200, 1, '配置更新成功');
        } catch (\Exception $e) {
            json1(200, 0, '配置更新失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试企业微信连接
     */
    public function TestConnection()
    {
        try {
            if (!$this->ServiceWeComAPI) {
                json1(200, 0, '企业微信服务未初始化');
            }

            // 测试获取 Access Token（抛异常即失败）
            $token = $this->ServiceWeComAPI->getAccessToken();

            // 测试获取部门列表
            $departments = $this->ServiceWeComAPI->getDepartments();

            json1(200, 1, '企业微信连接测试成功', [
                'token_info' => substr($token, 0, 10) . '...',
                'departments_count' => is_array($departments) ? count($departments) : 0
            ]);
        } catch (\Exception $e) {
            // 返回详细的错误信息；若是企业微信返回的JSON，则一并透传
            $errorMessage = $e->getMessage();

            $errorDetails = [
                'error_message' => $errorMessage,
                'error_code' => $e->getCode(),
                'error_file' => basename($e->getFile()),
                'error_line' => $e->getLine()
            ];

            // 如果后端抛出的信息本身是JSON，解析并放到 wecom_json 字段，供前端原样展示
            $decoded = json_decode($errorMessage, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $errorDetails['wecom_json'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            json1(200, 0, $errorMessage, $errorDetails);
        }
    }

    /**
     * 获取企业微信组织架构
     */
    public function GetOrganization()
    {
        try {
            if (!$this->ServiceWeComAPI) {
                json1(200, 0, '企业微信服务未初始化');
            }

            $result = $this->ServiceWeComAPI->getFullOrganization();
            
            if ($result['status'] === 1) {
                json1(200, 1, '获取组织架构成功', $result['data']);
            } else {
                json1(200, 0, '获取组织架构失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '获取组织架构失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行手动同步
     */
    public function ManualSync()
    {
        try {
            if (!$this->ServiceWeComSync) {
                json1(200, 0, '同步服务未初始化');
            }

            $syncType = ($this->param['payload'] ?? [])['sync_type'] ?? 'full';

            if (!in_array($syncType, ['full', 'incremental', 'member_only', 'pure'])) {
                json1(200, 0, '无效的同步类型');
            }

            // 执行同步
            if ($syncType === 'full') {
                $result = $this->ServiceWeComSync->fullSync();
            } elseif ($syncType === 'incremental') {
                $result = $this->ServiceWeComSync->incrementalSync();
            } elseif ($syncType === 'member_only') {
                $result = $this->ServiceWeComSync->memberOnlySync();
            } else { // pure
                $result = $this->ServiceWeComSync->pureSync();
            }

            if ($result['status'] === 1) {
                json1(200, 1, '同步执行成功', $result['data']);
            } else {
                json1(200, 0, '同步执行失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '同步执行失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行仅成员同步
     */
    public function MemberOnlySync()
    {
        try {
            if (!$this->ServiceWeComSync) {
                json1(200, 0, '同步服务未初始化');
            }

            // 执行仅成员同步
            $result = $this->ServiceWeComSync->memberOnlySync();

            if ($result['status'] === 1) {
                json1(200, 1, '仅成员同步执行成功', $result['data']);
            } else {
                json1(200, 0, '仅成员同步执行失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '仅成员同步执行失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行纯SVNAdmin方法的企业微信同步
     * 不依赖API配置，只使用现有数据和SVNAdmin原生方法
     */
    public function PureSync()
    {
        try {
            if (!$this->ServiceWeComSync) {
                json1(200, 0, '同步服务未初始化');
            }

            // 执行纯SVNAdmin同步
            $result = $this->ServiceWeComSync->pureSync();

            if ($result['status'] === 1) {
                json1(200, 1, '纯SVNAdmin同步执行成功', [
                    'processed_groups' => $result['processed_groups'],
                    'total_changes' => $result['total_changes'],
                    'results' => $result['results']
                ]);
            } else {
                json1(200, 0, '纯SVNAdmin同步执行失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '纯SVNAdmin同步执行失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取同步状态
     */
    public function GetSyncStatus()
    {
        try {
            global $database;

            // 获取最近的同步日志
            $recentLogs = $database->select('wecom_sync_logs', '*', [
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 10
            ]);

            // 获取统计信息
            $stats = [
                'departments_count' => $database->count('wecom_departments'),
                'users_count' => $database->count('wecom_users'),
                'last_sync_time' => $database->get('wecom_config', 'last_sync_time'),
                'sync_logs' => $recentLogs
            ];

            json1(200, 1, '获取同步状态成功', $stats);
        } catch (\Exception $e) {
            json1(200, 0, '获取同步状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取通知规则列表
     */
    public function GetNotificationRules()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            $filters = [
                'repo_name' => ($this->param['payload'] ?? [])['repo_name'] ?? null,
                'event_type' => ($this->param['payload'] ?? [])['event_type'] ?? null,
                'enable' => ($this->param['payload'] ?? [])['enable'] ?? null
            ];

            // 移除空值
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $result = $this->ServiceWeComNotification->getNotificationRules($filters);

            if ($result['status'] === 1) {
                json1(200, 1, '获取通知规则成功', $result['data']);
            } else {
                json1(200, 0, '获取通知规则失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '获取通知规则失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建通知规则
     */
    public function CreateNotificationRule()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            // 调试：输出接收到的 payload
            error_log('CreateNotificationRule payload: ' . json_encode($this->param['payload'] ?? null));
            
            $ruleData = ($this->param['payload'] ?? [])['rule_data'] ?? [];
            
            // 调试：输出 rule_data
            error_log('CreateNotificationRule rule_data: ' . json_encode($ruleData));
            
            if (empty($ruleData)) {
                json1(200, 0, '规则数据不能为空，payload: ' . json_encode($this->param['payload'] ?? null));
            }

            $result = $this->ServiceWeComNotification->createNotificationRule($ruleData);

            if ($result['status'] === 1) {
                json1(200, 1, '创建通知规则成功', ['rule_id' => $result['rule_id']]);
            } else {
                json1(200, 0, '创建通知规则失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '创建通知规则失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新通知规则
     */
    public function UpdateNotificationRule()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            // 调试：输出接收到的 payload
            error_log('UpdateNotificationRule payload: ' . json_encode($this->param['payload'] ?? null));

            $ruleId = ($this->param['payload'] ?? [])['rule_id'] ?? 0;
            $ruleData = ($this->param['payload'] ?? [])['rule_data'] ?? [];
            
            // 调试：输出 rule_data
            error_log('UpdateNotificationRule rule_data: ' . json_encode($ruleData));
            
            if (empty($ruleId) || empty($ruleData)) {
                json1(200, 0, '规则ID和规则数据不能为空');
            }

            $result = $this->ServiceWeComNotification->updateNotificationRule($ruleId, $ruleData);

            if ($result['status'] === 1) {
                json1(200, 1, '更新通知规则成功');
            } else {
                json1(200, 0, '更新通知规则失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '更新通知规则失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除通知规则
     */
    public function DeleteNotificationRule()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            // 修复：与其他方法保持一致，从payload中获取rule_id
            $ruleId = ($this->param['payload'] ?? [])['rule_id'] ?? ($this->param['rule_id'] ?? 0);
            
            if (empty($ruleId)) {
                json1(200, 0, '规则ID不能为空');
            }

            $result = $this->ServiceWeComNotification->deleteNotificationRule($ruleId);

            if ($result['status'] === 1) {
                json1(200, 1, '删除通知规则成功');
            } else {
                json1(200, 0, '删除通知规则失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '删除通知规则失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试通知发送
     */
    public function TestNotification()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            $webhookUrl = ($this->param['payload'] ?? [])['webhook_url'] ?? '';
            $message = ($this->param['payload'] ?? [])['message'] ?? null;
            $notifyUserIds = ($this->param['payload'] ?? [])['notify_wecom_userids'] ?? '';
            $notifyDeptIds = ($this->param['payload'] ?? [])['notify_wecom_deptids'] ?? '';
            
            // 至少需要配置一种通知方式
            if (empty($webhookUrl) && empty($notifyUserIds) && empty($notifyDeptIds)) {
                json1(200, 0, '请至少配置一种通知方式（Webhook URL、用户或部门）');
            }

            $result = $this->ServiceWeComNotification->testNotification($webhookUrl, $message, $notifyUserIds, $notifyDeptIds);

            if ($result['status'] === 1) {
                json1(200, 1, '测试通知发送成功', $result['results']);
            } else {
                json1(200, 0, '测试通知发送失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '测试通知发送失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取通知统计
     */
    public function GetNotificationStats()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                // 服务未初始化时，返回空统计以避免前端报错
                json1(200, 1, '通知服务未初始化，返回空统计', [
                    'total_count' => 0,
                    'success_count' => 0,
                    'failed_count' => 0,
                    'success_rate' => 0,
                    'event_type_stats' => [],
                    'repo_stats' => []
                ]);
            }

            $filters = [
                'start_date' => $this->param['start_date'] ?? null,
                'end_date' => $this->param['end_date'] ?? null,
                'event_type' => $this->param['event_type'] ?? null,
                'repo_name' => $this->param['repo_name'] ?? null
            ];

            // 移除空值
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $result = $this->ServiceWeComNotification->getNotificationStats($filters);

            if ($result['status'] === 1) {
                json1(200, 1, '获取通知统计成功', $result['data']);
            } else {
                json1(200, 0, '获取通知统计失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '获取通知统计失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取企业微信部门和用户映射
     */
    private function NormalizeSvnUserForMapping($user)
    {
        if (empty($user)) {
            return [
                'svn_user_id' => null,
                'svn_user_name' => null,
                'svn_user_real_name' => '',
                'svn_user_display_name' => '',
                'svn_user_mail' => '',
                'svn_user_note' => '',
                'svn_user_label' => ''
            ];
        }

        $userName = $user['svn_user_name'] ?? '';
        $realName = $user['svn_user_real_name'] ?? '';
        $displayName = $user['svn_user_display_name'] ?? '';
        $labelName = $displayName != '' && $displayName != $userName ? $displayName : ($realName != '' ? $realName : $userName);
        $label = $labelName == $userName ? $userName : $labelName . ' (' . $userName . ')';

        return [
            'svn_user_id' => $user['svn_user_id'] ?? null,
            'svn_user_name' => $userName,
            'svn_user_real_name' => $realName,
            'svn_user_display_name' => $displayName,
            'svn_user_mail' => $user['svn_user_mail'] ?? '',
            'svn_user_note' => $user['svn_user_note'] ?? '',
            'svn_user_label' => $label
        ];
    }

    public function GetMapping()
    {
        try {
            global $database;

            // 若企业微信相关表尚未初始化，则返回空数据，避免前端报错
            if (!$this->checkWeComTables()) {
                json1(200, 1, '未初始化，返回空映射数据', [
                    'departments' => [],
                    'users' => [],
                    'mappings' => []
                ]);
            }

            // 获取部门映射
            $departments = $database->select('wecom_departments', [
                '[>]svn_groups' => ['svn_group_id' => 'svn_group_id']
            ], [
                'wecom_departments.wecom_dept_id(wecom_department_id)',
                'wecom_departments.dept_name(wecom_name)',
                'wecom_departments.parent_id(wecom_parent_id)',
                'svn_groups.svn_group_name',
                'svn_groups.svn_group_note'
            ]);

            // 获取用户映射（已映射的企业微信用户）
            $mappedUsers = $database->select('wecom_users', [
                '[>]svn_users' => ['svn_user_id' => 'svn_user_id']
            ], [
                'wecom_users.wecom_user_id(wecom_userid)',
                'wecom_users.real_name(wecom_name)',
                'wecom_users.email(wecom_email)',
                'wecom_users.mobile(wecom_mobile)',
                'wecom_users.department_ids(wecom_department_ids)',
                'svn_users.svn_user_id',
                'svn_users.svn_user_name',
                'svn_users.svn_user_real_name',
                'svn_users.svn_user_display_name',
                'svn_users.svn_user_mail',
                'svn_users.svn_user_note'
            ]);

            // 获取所有企业微信用户（包括未映射的）
            $allWecomUsers = $database->select('wecom_users', [
                'wecom_user_id(wecom_userid)',
                'real_name(wecom_name)',
                'email(wecom_email)',
                'mobile(wecom_mobile)',
                'department_ids(wecom_department_ids)',
                'svn_user_id'
            ]);

            // 获取未映射的SVN用户（不在wecom_users表中的SVN用户）
            $mappedSvnUserIds = array_filter(array_column($allWecomUsers, 'svn_user_id'));
            $unmappedSvnUsers = [];
            
            if (empty($mappedSvnUserIds)) {
                // 如果没有映射的用户，获取所有SVN用户
                $unmappedSvnUsers = $database->select('svn_users', [
                    'svn_user_id',
                    'svn_user_name',
                    'svn_user_real_name',
                    'svn_user_display_name',
                    'svn_user_mail',
                    'svn_user_note'
                ]);
            } else {
                // 获取未映射的SVN用户
                $unmappedSvnUsers = $database->select('svn_users', [
                    'svn_user_id',
                    'svn_user_name',
                    'svn_user_real_name',
                    'svn_user_display_name',
                    'svn_user_mail',
                    'svn_user_note'
                ], [
                    'svn_user_id[!]' => $mappedSvnUserIds
                ]);
            }

            // 合并所有用户数据
            $users = [];
            
            // 添加已映射的企业微信用户
            foreach ($allWecomUsers as $wecomUser) {
                $mappedUser = null;
                foreach ($mappedUsers as $mapped) {
                    if ($mapped['wecom_userid'] === $wecomUser['wecom_userid']) {
                        $mappedUser = $mapped;
                        break;
                    }
                }
                $svnUser = $this->NormalizeSvnUserForMapping($mappedUser);
                
                $users[] = [
                    'wecom_userid' => $wecomUser['wecom_userid'],
                    'wecom_name' => $wecomUser['wecom_name'],
                    'wecom_email' => $wecomUser['wecom_email'],
                    'wecom_mobile' => $wecomUser['wecom_mobile'],
                    'wecom_department_ids' => $wecomUser['wecom_department_ids'],
                    'svn_user_id' => $svnUser['svn_user_id'],
                    'svn_user_name' => $svnUser['svn_user_name'],
                    'svn_user_real_name' => $svnUser['svn_user_real_name'],
                    'svn_user_display_name' => $svnUser['svn_user_display_name'],
                    'svn_user_mail' => $svnUser['svn_user_mail'],
                    'svn_user_note' => $svnUser['svn_user_note'],
                    'svn_user_label' => $svnUser['svn_user_label'],
                    'user_type' => 'wecom'
                ];
            }
            
            // 添加未映射的SVN用户（作为虚拟的企业微信用户显示）
            foreach ($unmappedSvnUsers as $svnUser) {
                $svnUserInfo = $this->NormalizeSvnUserForMapping($svnUser);
                $users[] = [
                    'wecom_userid' => null,
                    'wecom_name' => null,
                    'wecom_email' => null,
                    'wecom_mobile' => null,
                    'wecom_department_ids' => null,
                    'svn_user_id' => $svnUserInfo['svn_user_id'],
                    'svn_user_name' => $svnUserInfo['svn_user_name'],
                    'svn_user_real_name' => $svnUserInfo['svn_user_real_name'],
                    'svn_user_display_name' => $svnUserInfo['svn_user_display_name'],
                    'svn_user_mail' => $svnUserInfo['svn_user_mail'],
                    'svn_user_note' => $svnUserInfo['svn_user_note'],
                    'svn_user_label' => $svnUserInfo['svn_user_label'],
                    'user_type' => 'svn_only'
                ];
            }

            // 获取所有部门信息用于前端映射
            $allDepartments = $database->select('wecom_departments', [
                'wecom_dept_id',
                'dept_name'
            ]);
            
            // 创建部门ID到名称的映射
            $departmentMap = [];
            foreach ($allDepartments as $dept) {
                $departmentMap[$dept['wecom_dept_id']] = $dept['dept_name'];
            }

            json1(200, 1, '获取映射信息成功', [
                'departments' => $departments,
                'users' => $users,
                'department_map' => $departmentMap
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '获取映射信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理通知日志
     */
    public function CleanupLogs()
    {
        try {
            if (!$this->ServiceWeComNotification) {
                json1(200, 0, '通知服务未初始化');
            }

            $daysToKeep = $this->param['days_to_keep'] ?? 30;
            
            if (!is_numeric($daysToKeep) || $daysToKeep < 1) {
                json1(200, 0, '保留天数必须是大于0的数字');
            }

            $result = $this->ServiceWeComNotification->cleanupNotificationLogs($daysToKeep);

            if ($result['status'] === 1) {
                json1(200, 1, '日志清理成功', ['deleted_count' => $result['deleted_count']]);
            } else {
                json1(200, 0, '日志清理失败: ' . $result['message']);
            }
        } catch (\Exception $e) {
            json1(200, 0, '日志清理失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取系统状态
     */
    public function GetSystemStatus()
    {
        try {
            global $database;

            // 检查企业微信配置
            $wecomConfig = WeComConfig::getConfig();
            $isConfigured = !empty($wecomConfig['corp_id']) && !empty($wecomConfig['agent_id']);

            // 检查数据库表
            $tablesExist = $this->checkWeComTables();

            // 获取统计信息
            $stats = [
                'is_configured' => $isConfigured,
                'tables_exist' => $tablesExist,
                'departments_count' => $tablesExist ? $database->count('wecom_departments') : 0,
                'users_count' => $tablesExist ? $database->count('wecom_users') : 0,
                'notification_rules_count' => $tablesExist ? $database->count('wecom_notification_rules') : 0,
                'last_sync_time' => $tablesExist ? $database->get('wecom_config', 'last_sync_time') : null
            ];

            // 检查服务状态
            $serviceStatus = [
                'api_service' => $this->ServiceWeComAPI !== null,
                'sync_service' => $this->ServiceWeComSync !== null,
                'notification_service' => $this->ServiceWeComNotification !== null
            ];

            json1(200, 1, '获取系统状态成功', [
                'config' => $stats,
                'services' => $serviceStatus
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '获取系统状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查企业微信相关数据库表是否存在
     */
    private function checkWeComTables()
    {
        try {
            global $database;

            $requiredTables = [
                'wecom_config',
                'wecom_departments',
                'wecom_users',
                'wecom_notification_rules',
                'wecom_notification_logs',
                'wecom_sync_logs',
                'wecom_api_logs',
                'wecom_notification_queue'
            ];

            // 根据数据库类型选择更稳妥的表存在性检查
            $dbType = \Config::get('database')['database_type'] ?? 'sqlite';

            foreach ($requiredTables as $table) {
                $exists = false;
                if ($dbType === 'sqlite') {
                    $stmt = $database->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                    if ($stmt !== false && $stmt->fetchColumn()) {
                        $exists = true;
                    }
                } else { // MySQL 及其它：使用 SHOW TABLES LIKE
                    $stmt = $database->query("SHOW TABLES LIKE '{$table}'");
                    if ($stmt !== false && $stmt->fetch()) {
                        $exists = true;
                    }
                }

                if (!$exists) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 掩码敏感数据
     */
    private function maskSensitiveData($data)
    {
        if (empty($data)) {
            return $data;
        }

        $length = strlen($data);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($data, 0, 4) . str_repeat('*', $length - 8) . substr($data, -4);
    }

    // ==================== 扩展配置管理接口 ====================

    /**
     * 验证企业微信配置
     */
    public function ValidateConfig()
    {
        try {
            $payload = $this->param['payload'] ?? [];
            $configData = $payload['config_data'] ?? ($this->param['config_data'] ?? []);
            
            if (empty($configData)) {
                json1(200, 0, '配置数据不能为空');
            }

            $errors = [];
            $warnings = [];

            // 验证必要字段
            $requiredFields = [
                'corp_id' => '企业微信企业ID',
                'agent_id' => '企业微信应用ID',
                'corp_secret' => '企业微信应用密钥'
            ];

            foreach ($requiredFields as $field => $name) {
                if (empty($configData[$field])) {
                    $errors[] = "缺少必要字段: {$name}";
                }
            }

            // 验证字段格式
            if (!empty($configData['corp_id'])) {
                if (!preg_match('/^[a-zA-Z0-9]{10,20}$/', $configData['corp_id'])) {
                    $errors[] = '企业微信企业ID格式不正确';
                }
            }

            if (!empty($configData['agent_id'])) {
                if (!is_numeric($configData['agent_id']) || $configData['agent_id'] <= 0) {
                    $errors[] = '企业微信应用ID必须是正整数';
                }
            }

            if (!empty($configData['corp_secret'])) {
                if (strlen($configData['corp_secret']) < 32) {
                    $errors[] = '企业微信应用密钥长度不足';
                }
            }

            // 验证可选字段
            if (!empty($configData['token'])) {
                if (strlen($configData['token']) < 3 || strlen($configData['token']) > 32) {
                    $errors[] = 'Token长度必须在3-32个字符之间';
                }
            }

            if (!empty($configData['aes_key'])) {
                if (strlen($configData['aes_key']) != 43) {
                    $errors[] = 'EncodingAESKey长度必须是43个字符';
                }
            }

            // 验证同步配置
            if (isset($configData['sync']['sync_interval'])) {
                $interval = $configData['sync']['sync_interval'];
                if (!is_numeric($interval) || $interval < 300) {
                    $warnings[] = '同步间隔建议不少于300秒（5分钟）';
                }
            }

            // 验证通知配置
            if (!empty($configData['notification']['default_webhook_url'])) {
                $url = $configData['notification']['default_webhook_url'];
                if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'qyapi.weixin.qq.com') === false) {
                    $errors[] = '默认Webhook URL格式不正确';
                }
            }

            $isValid = empty($errors);

            json1(200, 1, '配置验证完成', [
                'is_valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '配置验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 重置企业微信配置
     */
    public function ResetConfig()
    {
        try {
            // 备份当前配置
            $configFile = BASE_PATH . '/config/wecom.php';
            $backupFile = BASE_PATH . '/config/wecom.php.backup.' . date('YmdHis');
            
            if (file_exists($configFile)) {
                copy($configFile, $backupFile);
            }

            // 生成默认配置
            $defaultConfig = [
                'enabled' => false,
                'corp_id' => '',
                'corp_secret' => '',
                'agent_id' => '',
                'token' => '',
                'aes_key' => '',
                'access_token_cache' => [
                    'enabled' => true,
                    'type' => 'file',
                    'file_path' => '%sruntime/wecom_access_token.cache',
                    'database_table' => 'wecom_config',
                ],
                'log' => [
                    'enabled' => true,
                    'level' => 'info',
                    'path' => '%slogs/wecom.log',
                    'api_log_enabled' => false,
                    'api_log_path' => '%slogs/wecom_api.log',
                    'sync_log_enabled' => false,
                    'sync_log_path' => '%slogs/wecom_sync.log',
                    'notification_log_enabled' => false,
                    'notification_log_path' => '%slogs/wecom_notification.log',
                ],
                'sync' => [
                    'enabled' => false,
                    'sync_interval' => 3600,
                    'full_sync_interval' => 86400,
                    'auto_sync_on_startup' => false,
                    'auto_add_user_to_group' => true,
                    'auto_remove_user_from_group' => true,
                    'update_authz_file' => true,
                ],
                'user_mapping' => [
                    'match_fields' => ['userid', 'email', 'mobile'],
                    'auto_create_user' => false,
                    'auto_disable_user' => true,
                    'username_prefix' => '',
                    'username_suffix' => '',
                    'default_password' => '123456',
                ],
                'department_mapping' => [
                    'root_department_id' => 1,
                    'group_name_prefix' => 'wecom_',
                    'group_name_format' => '{prefix}{dept_name}',
                    'inherit_permissions' => true,
                    'auto_create_groups' => true,
                ],
                'notification' => [
                    'enabled' => true,
                    'default_chat_id' => '',
                    'message_format' => 'markdown',
                    'max_message_length' => 4096,
                    'rate_limit' => [
                        'enabled' => true,
                        'max_messages_per_minute' => 20,
                        'merge_similar_messages' => true,
                    ],
                    'events' => [
                        'commit' => true,
                        'update' => false,
                        'delete' => true,
                        'copy' => false,
                        'move' => false,
                    ],
                ],
                'message_templates' => [
                    'commit' => [
                        'title' => 'SVN 提交通知',
                        'content' => "仓库: {repo_name}\n提交者: {author}\n版本: {revision}\n消息: {message}\n文件变更:\n{files}"
                    ],
                    'delete' => [
                        'title' => 'SVN 删除通知',
                        'content' => "仓库: {repo_name}\n操作者: {author}\n操作: 删除\n路径: {path}"
                    ],
                    'sync_success' => [
                        'title' => '企业微信同步成功',
                        'content' => "同步时间: {date}\n部门创建: {departments_created}\n部门更新: {departments_updated}\n部门删除: {departments_deleted}\n用户创建: {users_created}\n用户更新: {users_updated}\n用户删除: {users_deleted}"
                    ],
                    'sync_error' => [
                        'title' => '企业微信同步失败',
                        'content' => "同步时间: {date}\n错误信息: {error_message}"
                    ],
                ],
                'permission_mapping' => [
                    'strategy' => 'department_based',
                    'default_permission' => 'r',
                    'hierarchy_permissions' => [
                        0 => 'rw',
                        1 => 'rw',
                        2 => 'r',
                        3 => 'r'
                    ],
                    'custom_rules' => [],
                ],
            ];

            // 写入默认配置
            $configContent = "<?php\n/*\n * 企业微信集成配置\n * 自动生成于: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($defaultConfig, true) . ";\n";

            if (file_put_contents($configFile, $configContent) === false) {
                json1(200, 0, '配置文件写入失败');
            }

            json1(200, 1, '配置重置成功', [
                'backup_file' => basename($backupFile)
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '配置重置失败: ' . $e->getMessage());
        }
    }

    /**
     * 导出企业微信配置
     */
    public function ExportConfig()
    {
        try {
            $wecomConfig = require BASE_PATH . '/config/wecom.php';
            
            // 移除敏感信息
            $exportConfig = $wecomConfig;
            unset($exportConfig['corp_secret']);
            unset($exportConfig['token']);
            unset($exportConfig['aes_key']);

            // 添加导出信息
            $exportData = [
                'export_time' => date('Y-m-d H:i:s'),
                'export_version' => '1.0',
                'config' => $exportConfig
            ];

            json1(200, 1, '配置导出成功', $exportData);
        } catch (\Exception $e) {
            json1(200, 0, '配置导出失败: ' . $e->getMessage());
        }
    }

    /**
     * 导入企业微信配置
     */
    public function ImportConfig()
    {
        try {
            $importData = $this->param['import_data'] ?? [];
            
            if (empty($importData) || !isset($importData['config'])) {
                json1(200, 0, '导入数据格式不正确');
            }

            $importConfig = $importData['config'];
            
            // 读取当前配置
            $currentConfig = require BASE_PATH . '/config/wecom.php';

            // 合并配置（保留敏感信息）
            $newConfig = array_merge($currentConfig, $importConfig);
            
            // 保留当前的敏感信息
            $sensitiveFields = ['corp_secret', 'token', 'aes_key'];
            foreach ($sensitiveFields as $field) {
                if (isset($currentConfig[$field])) {
                    $newConfig[$field] = $currentConfig[$field];
                }
            }

            // 验证配置
            $validationResult = $this->validateConfigData($newConfig);
            if (!$validationResult['is_valid']) {
                json1(200, 0, '导入的配置验证失败: ' . implode(', ', $validationResult['errors']));
            }

            // 备份当前配置
            $configFile = BASE_PATH . '/config/wecom.php';
            $backupFile = BASE_PATH . '/config/wecom.php.backup.' . date('YmdHis');
            copy($configFile, $backupFile);

            // 写入新配置
            $configContent = "<?php\n/*\n * 企业微信集成配置\n * 导入于: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($newConfig, true) . ";\n";

            if (file_put_contents($configFile, $configContent) === false) {
                json1(200, 0, '配置文件写入失败');
            }

            json1(200, 1, '配置导入成功', [
                'backup_file' => basename($backupFile),
                'warnings' => $validationResult['warnings']
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '配置导入失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取配置模板
     */
    public function GetConfigTemplate()
    {
        try {
            $template = [
                'basic' => [
                    'corp_id' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => '企业微信企业ID',
                        'example' => 'ww1234567890abcdef'
                    ],
                    'agent_id' => [
                        'type' => 'integer',
                        'required' => true,
                        'description' => '企业微信应用ID',
                        'example' => 1000001
                    ],
                    'corp_secret' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => '企业微信应用密钥',
                        'example' => 'abcdef1234567890abcdef1234567890'
                    ]
                ],
                'callback' => [
                    'token' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => '企业微信回调Token',
                        'example' => 'mytoken123'
                    ],
                    'aes_key' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => '企业微信回调EncodingAESKey',
                        'example' => 'abcdef1234567890abcdef1234567890abcdef12345'
                    ]
                ],
                'sync' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => '是否启用通讯录同步'
                    ],
                    'sync_interval' => [
                        'type' => 'integer',
                        'default' => 3600,
                        'description' => '同步间隔（秒）'
                    ],
                    'auto_create_user' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => '是否自动创建SVN用户'
                    ]
                ],
                'notification' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'default' => true,
                        'description' => '是否启用通知功能'
                    ],
                    'default_webhook_url' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => '默认通知群Webhook URL',
                        'example' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx'
                    ]
                ]
            ];

            json1(200, 1, '获取配置模板成功', $template);
        } catch (\Exception $e) {
            json1(200, 0, '获取配置模板失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证配置数据（内部方法）
     */
    private function validateConfigData($configData)
    {
        $errors = [];
        $warnings = [];

        // 基础验证逻辑（复用 ValidateConfig 中的逻辑）
        $requiredFields = [
            'corp_id' => '企业微信企业ID',
            'agent_id' => '企业微信应用ID'
        ];

        foreach ($requiredFields as $field => $name) {
            if (empty($configData[$field])) {
                $errors[] = "缺少必要字段: {$name}";
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 获取配置历史记录
     */
    public function GetConfigHistory()
    {
        try {
            $configDir = BASE_PATH . '/config';
            $backupFiles = glob($configDir . '/wecom.php.backup.*');
            
            $history = [];
            foreach ($backupFiles as $file) {
                $filename = basename($file);
                $timestamp = substr($filename, strrpos($filename, '.') + 1);
                
                if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $timestamp, $matches)) {
                    $datetime = sprintf('%s-%s-%s %s:%s:%s', 
                        $matches[1], $matches[2], $matches[3],
                        $matches[4], $matches[5], $matches[6]
                    );
                    
                    $history[] = [
                        'filename' => $filename,
                        'timestamp' => $timestamp,
                        'datetime' => $datetime,
                        'size' => filesize($file)
                    ];
                }
            }

            // 按时间倒序排列
            usort($history, function($a, $b) {
                return strcmp($b['timestamp'], $a['timestamp']);
            });

            json1(200, 1, '获取配置历史成功', $history);
        } catch (\Exception $e) {
            json1(200, 0, '获取配置历史失败: ' . $e->getMessage());
        }
    }

    /**
     * 恢复配置备份
     */
    public function RestoreConfig()
    {
        try {
            $backupFilename = $this->param['backup_filename'] ?? '';
            
            if (empty($backupFilename)) {
                json1(200, 0, '备份文件名不能为空');
            }

            $backupFile = BASE_PATH . '/config/' . $backupFilename;
            $configFile = BASE_PATH . '/config/wecom.php';

            if (!file_exists($backupFile)) {
                json1(200, 0, '备份文件不存在');
            }

            // 创建当前配置的备份
            $currentBackupFile = BASE_PATH . '/config/wecom.php.backup.' . date('YmdHis');
            copy($configFile, $currentBackupFile);

            // 恢复配置
            if (!copy($backupFile, $configFile)) {
                json1(200, 0, '配置恢复失败');
            }

            json1(200, 1, '配置恢复成功', [
                'current_backup' => basename($currentBackupFile)
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '配置恢复失败: ' . $e->getMessage());
        }
    }

    // ==================== 扩展同步管理接口 ====================

    /**
     * 获取详细同步状态
     */
    public function GetDetailedSyncStatus()
    {
        try {
            global $database;

            // 获取基础统计
            $basicStats = [
                'departments_count' => $database->count('wecom_departments'),
                'users_count' => $database->count('wecom_users'),
                'notification_rules_count' => $database->count('wecom_notification_rules')
            ];

            // 获取最后同步时间
            $lastSyncTime = $database->get('wecom_config', 'last_sync_time');

            // 获取最近的同步日志（详细信息）
            $recentSyncLogs = $database->select('wecom_sync_logs', '*', [
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 20
            ]);

            // 兼容前端字段：把 sync_status 映射为 status，把 summary/error_details 映射为 message
            if (is_array($recentSyncLogs)) {
                foreach ($recentSyncLogs as &$log) {
                    if (!isset($log['status']) && isset($log['sync_status'])) {
                        $log['status'] = $log['sync_status'];
                    }
                    if (!isset($log['message'])) {
                        $log['message'] = $log['summary'] ?? ($log['error_details'] ?? '');
                    }
                }
                unset($log);
            }

            // 统计同步成功率
            $totalSyncs = $database->count('wecom_sync_logs');
            $successfulSyncs = $database->count('wecom_sync_logs', ['sync_status' => 'success']);
            $successRate = $totalSyncs > 0 ? round($successfulSyncs / $totalSyncs * 100, 2) : 0;

            // 获取各类型同步统计
            $syncTypeStats = $database->query(
                "SELECT sync_type, sync_status, COUNT(*) as count 
                 FROM wecom_sync_logs 
                 GROUP BY sync_type, sync_status"
            )->fetchAll();

            // 检查是否有正在进行的同步
            $ongoingSyncs = $database->select('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['start_time' => 'DESC'],
                'LIMIT' => 5
            ]);

            if (is_array($ongoingSyncs)) {
                foreach ($ongoingSyncs as &$log) {
                    if (!isset($log['status']) && isset($log['sync_status'])) {
                        $log['status'] = $log['sync_status'];
                    }
                    if (!isset($log['message'])) {
                        $log['message'] = $log['summary'] ?? ($log['error_details'] ?? '');
                    }
                }
                unset($log);
            }

            // 获取同步错误统计
            $errorLogs = $database->select('wecom_sync_logs', '*', [
                'sync_status' => 'failed',
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 10
            ]);

            if (is_array($errorLogs)) {
                foreach ($errorLogs as &$log) {
                    if (!isset($log['status']) && isset($log['sync_status'])) {
                        $log['status'] = $log['sync_status'];
                    }
                    if (!isset($log['message'])) {
                        $log['message'] = $log['summary'] ?? ($log['error_details'] ?? '');
                    }
                }
                unset($log);
            }

            json1(200, 1, '获取详细同步状态成功', [
                'basic_stats' => $basicStats,
                'last_sync_time' => $lastSyncTime,
                'success_rate' => $successRate,
                'total_syncs' => $totalSyncs,
                'successful_syncs' => $successfulSyncs,
                'sync_type_stats' => $syncTypeStats,
                'recent_logs' => $recentSyncLogs,
                'ongoing_syncs' => $ongoingSyncs,
                'recent_errors' => $errorLogs
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '获取详细同步状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行增量同步
     */
    public function IncrementalSync()
    {
        try {
            if (!$this->ServiceWeComSync) {
                json1(200, 0, '同步服务未初始化');
            }

            // 检查是否有正在进行的同步，如超过超时时间则自动清理
            global $database;
            $ongoing = $database->get('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['start_time' => 'DESC']
            ]);

            if ($ongoing) {
                $startTs = strtotime($ongoing['start_time'] ?? '');
                $timeoutSeconds = 600; // 10分钟视为超时
                if ($startTs && (time() - $startTs) > $timeoutSeconds) {
                    $database->update('wecom_sync_logs', [
                        'sync_status' => 'stopped',
                        'end_time' => date('Y-m-d H:i:s'),
                        'summary' => '任务超时，系统自动标记为已停止'
                    ], [
                        'id' => $ongoing['id']
                    ]);
                } else {
                    json1(200, 0, '已有同步任务正在进行中，请稍后重试');
                }
            }

            // 执行增量同步（必须从API获取最新数据）
            try {
                $result = $this->ServiceWeComSync->incrementalSync();

                if ($result['status'] === 1) {
                    json1(200, 1, '企业微信增量同步执行成功', $result['data']);
                } else {
                    // 直接返回失败，不再降级
                    json1(200, 0, '企业微信增量同步失败: ' . $result['message']);
                }
            } catch (\Exception $e) {
                // 直接返回异常，不再降级
                json1(200, 0, '企业微信增量同步异常: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            json1(200, 0, '增量同步执行失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行全量同步
     */
    public function FullSync()
    {
        try {
            if (!$this->ServiceWeComSync) {
                json1(200, 0, '同步服务未初始化');
            }

            // 检查是否有正在进行的同步，如超过超时时间则自动清理
            global $database;
            $ongoing = $database->get('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['start_time' => 'DESC']
            ]);

            if ($ongoing) {
                $startTs = strtotime($ongoing['start_time'] ?? '');
                $timeoutSeconds = 600; // 10分钟视为超时
                if ($startTs && (time() - $startTs) > $timeoutSeconds) {
                    $database->update('wecom_sync_logs', [
                        'sync_status' => 'stopped',
                        'end_time' => date('Y-m-d H:i:s'),
                        'summary' => '任务超时，系统自动标记为已停止'
                    ], [
                        'id' => $ongoing['id']
                    ]);
                } else {
                    json1(200, 0, '已有同步任务正在进行中，请稍后重试');
                }
            }

            // 执行完整的企业微信同步（必须从API获取最新数据）
            try {
                $result = $this->ServiceWeComSync->fullSync();

                if ($result['status'] === 1) {
                    json1(200, 1, '企业微信完整同步执行成功', $result['data']);
                } else {
                    // 直接返回失败，不再降级
                    json1(200, 0, '企业微信完整同步失败: ' . $result['message']);
                }
            } catch (\Exception $e) {
                // 直接返回异常，不再降级
                json1(200, 0, '企业微信完整同步异常: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            json1(200, 0, '全量同步执行失败: ' . $e->getMessage());
        }
    }



    /**
     * 获取同步日志
     */
    public function GetSyncLogs()
    {
        try {
            global $database;

            $page = max(1, intval($this->param['page'] ?? 1));
            $pageSize = max(1, min(100, intval($this->param['page_size'] ?? 20)));
            $syncType = $this->param['sync_type'] ?? null;
            $status = $this->param['status'] ?? null;
            $startDate = $this->param['start_date'] ?? null;
            $endDate = $this->param['end_date'] ?? null;

            // 构建查询条件
            $conditions = [];
            if ($syncType) {
                $conditions['sync_type'] = $syncType;
            }
            if ($status) {
                $conditions['sync_status'] = $status;
            }
            if ($startDate) {
                $conditions['created_at[>=]'] = $startDate . ' 00:00:00';
            }
            if ($endDate) {
                $conditions['created_at[<=]'] = $endDate . ' 23:59:59';
            }

            // 获取总数
            $total = $database->count('wecom_sync_logs', $conditions);

            // 获取分页数据
            $conditions['ORDER'] = ['created_at' => 'DESC'];
            $conditions['LIMIT'] = [($page - 1) * $pageSize, $pageSize];

            $logs = $database->select('wecom_sync_logs', '*', $conditions);

            // 兼容前端字段名：sync_status -> status；summary/error_details -> message
            if (is_array($logs)) {
                foreach ($logs as &$log) {
                    if (!isset($log['status']) && isset($log['sync_status'])) {
                        $log['status'] = $log['sync_status'];
                    }
                    if (!isset($log['message'])) {
                        $log['message'] = $log['summary'] ?? ($log['error_details'] ?? '');
                    }
                }
                unset($log);
            }

            // 计算分页信息
            $totalPages = ceil($total / $pageSize);

            json1(200, 1, '获取同步日志成功', [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'page_size' => $pageSize,
                    'total' => $total,
                    'total_pages' => $totalPages
                ]
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '获取同步日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理同步日志
     */
    public function CleanupSyncLogs()
    {
        try {
            global $database;

            $daysToKeep = max(1, intval($this->param['days_to_keep'] ?? 30));
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

            $deletedCount = $database->delete('wecom_sync_logs', [
                'created_at[<]' => $cutoffDate
            ]);

            json1(200, 1, '同步日志清理成功', [
                'deleted_count' => $deletedCount,
                'days_kept' => $daysToKeep
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '同步日志清理失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取同步配置
     */
    public function GetSyncConfig()
    {
        try {
            $wecomConfig = require BASE_PATH . '/config/wecom.php';
            $syncConfig = $wecomConfig['sync'] ?? [];

            // 添加运行时状态信息
            global $database;
            $lastSyncTime = $database->get('wecom_config', 'last_sync_time');
            $nextSyncTime = null;

            if ($lastSyncTime && isset($syncConfig['sync_interval'])) {
                $nextSyncTime = date('Y-m-d H:i:s', 
                    strtotime($lastSyncTime) + $syncConfig['sync_interval']
                );
            }

            $syncConfig['runtime'] = [
                'last_sync_time' => $lastSyncTime,
                'next_sync_time' => $nextSyncTime,
                'is_enabled' => $syncConfig['enabled'] ?? false
            ];

            json1(200, 1, '获取同步配置成功', $syncConfig);
        } catch (\Exception $e) {
            json1(200, 0, '获取同步配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新同步配置
     */
    public function UpdateSyncConfig()
    {
        try {
            $payload = $this->param['payload'] ?? [];
            $syncConfigData = $payload['sync_config'] ?? ($this->param['sync_config'] ?? []);
            
            if (empty($syncConfigData)) {
                json1(200, 0, '同步配置数据不能为空');
            }

            // 读取当前完整配置
            $configFile = BASE_PATH . '/config/wecom.php';
            $currentConfig = require $configFile;

            // 更新同步配置部分
            $currentConfig['sync'] = array_merge($currentConfig['sync'] ?? [], $syncConfigData);

            // 验证配置
            if (isset($currentConfig['sync']['sync_interval'])) {
                $interval = $currentConfig['sync']['sync_interval'];
                if (!is_numeric($interval) || $interval < 60) {
                    json1(200, 0, '同步间隔不能少于60秒');
                }
            }

            // 备份当前配置
            $backupFile = BASE_PATH . '/config/wecom.php.backup.' . date('YmdHis');
            copy($configFile, $backupFile);

            // 写入新配置
            $configContent = "<?php\n/*\n * 企业微信集成配置\n * 更新于: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($currentConfig, true) . ";\n";

            if (file_put_contents($configFile, $configContent) === false) {
                json1(200, 0, '配置文件写入失败');
            }

            json1(200, 1, '同步配置更新成功', [
                'backup_file' => basename($backupFile)
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '同步配置更新失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试同步连接
     */
    public function TestSyncConnection()
    {
        try {
            if (!$this->ServiceWeComAPI) {
                json1(200, 0, '企业微信API服务未初始化');
            }

            $testResults = [];

            // 测试 API 连接
            $tokenResult = $this->ServiceWeComAPI->getAccessToken();
            $testResults['api_connection'] = [
                'status' => $tokenResult['status'],
                'message' => $tokenResult['message'] ?? '连接成功'
            ];

            if ($tokenResult['status'] === 1) {
                // 测试获取部门列表
                $deptResult = $this->ServiceWeComAPI->getDepartments();
                $testResults['department_access'] = [
                    'status' => $deptResult['status'],
                    'message' => $deptResult['message'] ?? '部门访问成功',
                    'count' => count($deptResult['data'] ?? [])
                ];

                // 测试获取用户列表
                if ($deptResult['status'] === 1 && !empty($deptResult['data'])) {
                    $firstDept = $deptResult['data'][0];
                    $userResult = $this->ServiceWeComAPI->getDepartmentUsers($firstDept['id']);
                    $testResults['user_access'] = [
                        'status' => $userResult['status'],
                        'message' => $userResult['message'] ?? '用户访问成功',
                        'count' => count($userResult['data'] ?? [])
                    ];
                }
            }

            // 检查数据库连接
            try {
                global $database;
                $database->query('SELECT 1')->fetchColumn();
                $testResults['database_connection'] = [
                    'status' => 1,
                    'message' => '数据库连接正常'
                ];
            } catch (\Exception $e) {
                $testResults['database_connection'] = [
                    'status' => 0,
                    'message' => '数据库连接失败: ' . $e->getMessage()
                ];
            }

            // 计算总体状态
            $allSuccess = true;
            foreach ($testResults as $test) {
                if ($test['status'] !== 1) {
                    $allSuccess = false;
                    break;
                }
            }

            json1(200, 1, '同步连接测试完成', [
                'overall_status' => $allSuccess ? 'success' : 'failed',
                'test_results' => $testResults
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '同步连接测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 预览同步数据
     */
    public function PreviewSyncData()
    {
        try {
            if (!$this->ServiceWeComAPI) {
                json1(200, 0, '企业微信API服务未初始化');
            }

            $syncType = $this->param['sync_type'] ?? 'preview';
            $limit = min(50, max(1, intval($this->param['limit'] ?? 10)));

            $previewData = [];

            // 获取部门预览数据
            $deptResult = $this->ServiceWeComAPI->getDepartments();
            if ($deptResult['status'] === 1) {
                $departments = array_slice($deptResult['data'] ?? [], 0, $limit);
                $previewData['departments'] = $departments;
                $previewData['departments_total'] = count($deptResult['data'] ?? []);
            }

            // 获取用户预览数据
            if (!empty($departments)) {
                $users = [];
                $totalUsers = 0;
                
                foreach (array_slice($departments, 0, 3) as $dept) {
                    $userResult = $this->ServiceWeComAPI->getDepartmentUsers($dept['id']);
                    if ($userResult['status'] === 1) {
                        $deptUsers = $userResult['data'] ?? [];
                        $users = array_merge($users, array_slice($deptUsers, 0, 5));
                        $totalUsers += count($deptUsers);
                    }
                }

                $previewData['users'] = array_slice($users, 0, $limit);
                $previewData['users_total'] = $totalUsers;
            }

            // 获取当前映射状态
            global $database;
            $currentMappings = [
                'departments_mapped' => $database->count('wecom_departments'),
                'users_mapped' => $database->count('wecom_users'),
                'svn_groups_total' => $database->count('svn_groups'),
                'svn_users_total' => $database->count('svn_users')
            ];

            json1(200, 1, '同步数据预览成功', [
                'preview_data' => $previewData,
                'current_mappings' => $currentMappings,
                'preview_limit' => $limit
            ]);
        } catch (\Exception $e) {
            json1(200, 0, '同步数据预览失败: ' . $e->getMessage());
        }
    }

    /**
     * 停止同步任务
     */
    public function StopSync()
    {
        try {
            global $database;

            // 获取当前运行中的同步任务
            $runningTask = $database->get('wecom_sync_logs', '*', [
                'sync_status' => 'running',
                'ORDER' => ['id' => 'DESC']
            ]);

            if (!$runningTask) {
                json1(200, 0, '没有正在运行的同步任务');
                return;
            }

            // 将任务状态更新为已取消
            $database->update('wecom_sync_logs', [
                'sync_status' => 'cancelled',
                'end_time' => date('Y-m-d H:i:s'),
                'error_details' => '用户手动停止同步任务'
            ], [
                'id' => $runningTask['id']
            ]);

            // 记录停止操作
            $this->logInfo('同步任务已被用户停止', [
                'task_id' => $runningTask['id'],
                'sync_type' => $runningTask['sync_type'],
                'start_time' => $runningTask['start_time']
            ]);

            json1(200, 1, '同步任务已停止', [
                'stopped_task' => [
                    'id' => $runningTask['id'],
                    'sync_type' => $runningTask['sync_type'],
                    'start_time' => $runningTask['start_time']
                ]
            ]);

        } catch (\Exception $e) {
            json1(200, 0, '停止同步任务失败: ' . $e->getMessage());
        }
    }

    /**
     * 记录日志信息
     */
    private function logInfo($message, $context = [])
    {
        try {
            if (class_exists('\app\service\ServiceLogs')) {
                $logger = new \app\service\ServiceLogs();
                $logger->WriteLog('wecom_sync', 'info', $message, $context);
            }
        } catch (\Exception $e) {
            // 忽略日志记录错误
        }
    }
}
