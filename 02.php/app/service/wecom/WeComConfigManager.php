<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\WeComConfig;
use app\service\Base;

/**
 * 企业微信配置管理器
 * 
 * 负责管理企业微信相关的所有配置项，包括：
 * - 基础配置（corp_id, corp_secret, agent_id）
 * - 同步配置（部门映射、用户映射、权限映射）
 * - 日志配置
 * - 通知配置
 */
class WeComConfigManager extends Base
{
    /**
     * 企业微信配置
     * @var array
     */
    private $wecomConfig;

    /**
     * 同步配置
     * @var array
     */
    private $syncConfig;

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

        $this->loadConfig();
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
     * 加载配置
     */
    private function loadConfig()
    {
        // 加载企业微信配置（优先从数据库读取）
        $this->wecomConfig = WeComConfig::getConfig();
        
        // 兼容旧/新配置：优先使用 contact_sync；否则根据现有配置动态构建
        if (isset($this->wecomConfig['contact_sync'])) {
            $this->syncConfig = $this->wecomConfig['contact_sync'];
        } else {
            $dept = $this->wecomConfig['department_mapping'] ?? [];
            $sync = $this->wecomConfig['sync'] ?? [];
            $this->syncConfig = [
                'enable' => true,
                'department_root_id' => $dept['root_department_id'] ?? 1,
                'include_child_departments' => $dept['include_child_departments'] ?? true,
                'auto_create_group' => $sync['auto_create_group'] ?? true,
                'auto_update_group' => $sync['auto_update_group'] ?? true,
                'auto_remove_group' => $sync['auto_remove_group'] ?? false,
                'auto_create_user' => $sync['auto_create_user'] ?? true,
                'auto_update_user' => $sync['auto_update_user'] ?? true,
                'auto_remove_user' => $sync['auto_remove_user'] ?? false,
                'auto_remove_user_from_group' => $sync['auto_remove_user_from_group'] ?? true,
                'sync_user_to_group' => $sync['sync_user_to_group'] ?? true,
                'batch_size' => $sync['batch_size'] ?? 50
            ];
        }
    }

    /**
     * 获取企业微信基础配置
     * 
     * @return array
     */
    public function getWeComConfig()
    {
        return $this->wecomConfig;
    }

    /**
     * 获取同步配置
     * 
     * @return array
     */
    public function getSyncConfig()
    {
        return $this->syncConfig;
    }

    /**
     * 获取API配置（用于创建API实例）
     * 
     * @return array
     */
    public function getApiConfig()
    {
        return [
            'corp_id' => $this->wecomConfig['corp_id'],
            'corp_secret' => $this->wecomConfig['corp_secret'],
            'agent_id' => $this->wecomConfig['agent_id']
        ];
    }

    /**
     * 检查日志是否启用
     * 
     * @return bool
     */
    public function isLogEnabled()
    {
        return isset($this->wecomConfig['sync_log']['enable']) && $this->wecomConfig['sync_log']['enable'];
    }

    /**
     * 获取用户映射配置
     * 
     * @return array
     */
    public function getUserMappingConfig()
    {
        return $this->wecomConfig['user_mapping'] ?? [];
    }

    /**
     * 获取部门映射配置
     * 
     * @return array
     */
    public function getDepartmentMappingConfig()
    {
        return $this->wecomConfig['department_mapping'] ?? [];
    }

    /**
     * 获取权限映射配置
     * 
     * @return array
     */
    public function getPermissionMappingConfig()
    {
        return $this->wecomConfig['permission_mapping'] ?? [];
    }

    /**
     * 获取通知配置
     * 
     * @return array
     */
    public function getNotificationConfig()
    {
        return $this->wecomConfig['notification'] ?? [];
    }

    /**
     * 检查是否启用自动创建用户
     * 
     * @return bool
     */
    public function isAutoCreateUserEnabled()
    {
        return $this->wecomConfig['user_mapping']['auto_create_user'] ?? false;
    }

    /**
     * 检查是否启用自动禁用用户
     * 
     * @return bool
     */
    public function isAutoDisableUserEnabled()
    {
        return $this->wecomConfig['user_mapping']['auto_disable_user'] ?? false;
    }

    /**
     * 获取用户匹配字段
     * 
     * @return array
     */
    public function getUserMatchFields()
    {
        return $this->wecomConfig['user_mapping']['match_fields'] ?? ['userid', 'email'];
    }

    /**
     * 获取默认密码
     * 
     * @return string
     */
    public function getDefaultPassword()
    {
        return $this->wecomConfig['user_mapping']['default_password'] ?? '123456';
    }

    /**
     * 获取用户名前缀
     * 
     * @return string
     */
    public function getUsernamePrefix()
    {
        return $this->wecomConfig['user_mapping']['username_prefix'] ?? '';
    }

    /**
     * 获取用户名后缀
     * 
     * @return string
     */
    public function getUsernameSuffix()
    {
        return $this->wecomConfig['user_mapping']['username_suffix'] ?? '';
    }

    /**
     * 获取组名前缀
     * 
     * @return string
     */
    public function getGroupNamePrefix()
    {
        return $this->wecomConfig['department_mapping']['group_name_prefix'] ?? '';
    }

    /**
     * 获取权限策略
     * 
     * @return string
     */
    public function getPermissionStrategy()
    {
        return $this->wecomConfig['permission_mapping']['strategy'] ?? 'department_based';
    }

    /**
     * 获取默认权限
     * 
     * @return string
     */
    public function getDefaultPermission()
    {
        return $this->wecomConfig['permission_mapping']['default_permission'] ?? 'r';
    }

    /**
     * 获取层级权限配置
     * 
     * @return array
     */
    public function getHierarchyPermissions()
    {
        return $this->wecomConfig['permission_mapping']['hierarchy_permissions'] ?? [
            0 => 'rw', // 根部门
            1 => 'rw', // 一级部门
            2 => 'r',  // 二级部门
            3 => 'r'   // 三级及以下部门
        ];
    }

    /**
     * 重新加载配置
     */
    public function reloadConfig()
    {
        $this->loadConfig();
    }

    /**
     * 验证配置完整性
     * 
     * @return array 验证结果
     */
    public function validateConfig()
    {
        $errors = [];
        $warnings = [];

        // 检查基础配置
        if (empty($this->wecomConfig['corp_id'])) {
            $errors[] = '企业ID (corp_id) 不能为空';
        }

        if (empty($this->wecomConfig['corp_secret'])) {
            $errors[] = '企业应用密钥 (corp_secret) 不能为空';
        }

        if (empty($this->wecomConfig['agent_id'])) {
            $errors[] = '应用ID (agent_id) 不能为空';
        }

        // 检查用户映射配置
        $userMapping = $this->getUserMappingConfig();
        if (empty($userMapping['match_fields'])) {
            $warnings[] = '用户匹配字段未配置，将使用默认值';
        }

        if (empty($userMapping['default_password'])) {
            $warnings[] = '默认密码未配置，将使用默认值';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}
