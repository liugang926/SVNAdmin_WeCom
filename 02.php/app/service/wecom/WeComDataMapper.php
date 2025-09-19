<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
use app\service\Logs as ServiceLogs;

/**
 * 企业微信数据映射器
 * 
 * 负责企业微信数据与SVN数据之间的转换和映射，包括：
 * - 用户名生成和匹配
 * - 组名生成和清理
 * - 数据格式转换
 * - 映射规则应用
 */
class WeComDataMapper extends Base
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
     * 生成SVN组名
     *
     * @param array $department 企业微信部门信息
     * @return string
     */
    public function generateSvnGroupName($department)
    {
        $departmentName = $department['name'];
        
        // 清理部门名称，移除特殊字符（保留中文、英文字母、数字、下划线、连字符）
        $cleanName = $this->cleanName($departmentName);
        
        // 应用组名前缀（如果配置了）
        $prefix = $this->configManager->getGroupNamePrefix();
        if (!empty($prefix)) {
            $cleanName = $prefix . $cleanName;
        }
        
        $this->logInfo('生成SVN组名', [
            'original_name' => $departmentName,
            'clean_name' => $cleanName,
            'prefix' => $prefix
        ]);
        
        return $cleanName;
    }

    /**
     * 生成SVN用户名
     *
     * @param array $wecomUser 企业微信用户信息
     * @return string
     */
    public function generateSvnUserName($wecomUser)
    {
        $prefix = $this->configManager->getUsernamePrefix();
        $suffix = $this->configManager->getUsernameSuffix();
        
        // 优先使用 userid，如果不合适则使用邮箱前缀
        $baseName = $wecomUser['userid'];
        
        if (empty($baseName) && !empty($wecomUser['email'])) {
            $baseName = strstr($wecomUser['email'], '@', true);
        }
        
        if (empty($baseName)) {
            // 如果还是空，使用姓名的拼音或英文
            $baseName = $this->generateUsernameFromName($wecomUser['name']);
        }
        
        // 清理用户名
        $baseName = $this->cleanUsername($baseName);
        
        // 应用前缀和后缀
        $svnUserName = $prefix . $baseName . $suffix;
        
        $this->logInfo('生成SVN用户名', [
            'wecom_userid' => $wecomUser['userid'],
            'wecom_name' => $wecomUser['name'],
            'base_name' => $baseName,
            'final_name' => $svnUserName,
            'prefix' => $prefix,
            'suffix' => $suffix
        ]);
        
        return $svnUserName;
    }

    /**
     * 匹配SVN用户
     *
     * @param array $wecomUser 企业微信用户
     * @param array $existingSvnUsers 现有SVN用户列表
     * @return array|null 匹配的SVN用户信息
     */
    public function matchSvnUser($wecomUser, $existingSvnUsers)
    {
        $matchFields = $this->configManager->getUserMatchFields();

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
                case 'name':
                    $matchValue = $wecomUser['name'];
                    break;
                default:
                    continue 2; // 跳过未知字段
            }
            
            if (empty($matchValue)) {
                continue;
            }
            
            // 在现有SVN用户中查找匹配
            foreach ($existingSvnUsers as $svnUser) {
                if ($this->isUserMatch($svnUser, $field, $matchValue)) {
                    $this->logInfo('匹配到SVN用户', [
                        'wecom_userid' => $wecomUser['userid'],
                        'wecom_name' => $wecomUser['name'],
                        'match_field' => $field,
                        'match_value' => $matchValue,
                        'svn_user_id' => $svnUser['svn_user_id'],
                        'svn_user_name' => $svnUser['svn_user_name']
                    ]);
                    
                    return $svnUser;
                }
            }
        }
        
        $this->logInfo('未匹配到SVN用户', [
            'wecom_userid' => $wecomUser['userid'],
            'wecom_name' => $wecomUser['name'],
            'match_fields' => $matchFields
        ]);
        
        return null;
    }

    /**
     * 检查用户是否匹配
     *
     * @param array $svnUser SVN用户
     * @param string $field 匹配字段
     * @param string $value 匹配值
     * @return bool
     */
    private function isUserMatch($svnUser, $field, $value)
    {
        switch ($field) {
            case 'userid':
                // 精确匹配用户名
                return strcasecmp($svnUser['svn_user_name'], $value) === 0;
                
            case 'email':
                // 检查邮箱是否匹配
                return strcasecmp($svnUser['svn_user_mail'], $value) === 0;
                
            case 'mobile':
                // 检查备注中是否包含手机号
                return strpos($svnUser['svn_user_note'], $value) !== false;
                
            case 'name':
                // 检查备注中是否包含姓名
                return strpos($svnUser['svn_user_note'], $value) !== false;
                
            default:
                return false;
        }
    }

    /**
     * 清理名称（移除特殊字符）
     *
     * @param string $name 原始名称
     * @return string 清理后的名称
     */
    public function cleanName($name)
    {
        // 移除特殊字符，保留中文、英文字母、数字、下划线、连字符
        $cleanName = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $name);
        
        // 移除连续的下划线
        $cleanName = preg_replace('/_+/', '_', $cleanName);
        
        // 移除首尾的下划线
        $cleanName = trim($cleanName, '_');
        
        return $cleanName;
    }

    /**
     * 清理用户名
     *
     * @param string $username 原始用户名
     * @return string 清理后的用户名
     */
    public function cleanUsername($username)
    {
        // 转换为小写
        $username = strtolower($username);
        
        // 只保留字母、数字、下划线、连字符
        $username = preg_replace('/[^a-z0-9_-]/', '_', $username);
        
        // 移除连续的下划线
        $username = preg_replace('/_+/', '_', $username);
        
        // 移除首尾的下划线
        $username = trim($username, '_');
        
        // 确保用户名不为空
        if (empty($username)) {
            $username = 'user_' . time();
        }
        
        return $username;
    }

    /**
     * 从姓名生成用户名
     *
     * @param string $name 姓名
     * @return string 用户名
     */
    private function generateUsernameFromName($name)
    {
        // 简单的中文转拼音逻辑（这里可以集成更复杂的拼音库）
        $username = $name;
        
        // 如果是中文，尝试转换为拼音（这里简化处理）
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $name)) {
            // 这里可以集成拼音转换库，暂时使用简化处理
            $username = 'user_' . md5($name);
        }
        
        return $username;
    }

    /**
     * 转换企业微信部门数据为标准格式
     *
     * @param array $departments 企业微信部门数据
     * @return array 标准格式的部门数据
     */
    public function transformDepartments($departments)
    {
        $transformed = [];
        
        foreach ($departments as $dept) {
            $transformed[] = [
                'id' => $dept['id'],
                'name' => $dept['name'],
                'parentid' => $dept['parentid'],
                'order' => $dept['order'] ?? 0,
                'svn_group_name' => $this->generateSvnGroupName($dept)
            ];
        }
        
        return $transformed;
    }

    /**
     * 转换企业微信用户数据为标准格式
     *
     * @param array $users 企业微信用户数据
     * @return array 标准格式的用户数据
     */
    public function transformUsers($users)
    {
        $transformed = [];
        
        foreach ($users as $user) {
            $transformed[] = [
                'userid' => $user['userid'],
                'name' => $user['name'],
                'department' => $user['department'] ?? [],
                'position' => $user['position'] ?? '',
                'mobile' => $user['mobile'] ?? '',
                'email' => $user['email'] ?? '',
                'status' => $user['status'] ?? 1,
                'svn_user_name' => $this->generateSvnUserName($user)
            ];
        }
        
        return $transformed;
    }

    /**
     * 生成用户备注
     *
     * @param array $wecomUser 企业微信用户
     * @param string $userId 用户ID
     * @return string 用户备注
     */
    public function generateUserNote($wecomUser, $userId)
    {
        $userName = $wecomUser['name'];
        return "{$userName} ({$userId}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";
    }

    /**
     * 生成组备注
     *
     * @param string $departmentName 部门名称
     * @param string $departmentId 部门ID
     * @return string 组备注
     */
    public function generateGroupNote($departmentName, $departmentId)
    {
        return "{$departmentName} (ID: {$departmentId}) - " . date('Y-m-d H:i:s') . " - 企业微信同步";
    }

    /**
     * 检查SVN用户名是否已存在
     *
     * @param string $svnUserName SVN用户名
     * @param array $existingSvnUsers 现有SVN用户列表
     * @return bool
     */
    public function isSvnUserNameExists($svnUserName, $existingSvnUsers)
    {
        foreach ($existingSvnUsers as $user) {
            if (strcasecmp($user['svn_user_name'], $svnUserName) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查SVN组名是否已存在
     *
     * @param string $svnGroupName SVN组名
     * @param array $existingSvnGroups 现有SVN组列表
     * @return bool
     */
    public function isSvnGroupNameExists($svnGroupName, $existingSvnGroups)
    {
        foreach ($existingSvnGroups as $group) {
            if (strcasecmp($group['svn_group_name'], $svnGroupName) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 构建部门层级映射
     *
     * @param array $departments 部门列表
     * @return array 层级映射
     */
    public function buildDepartmentHierarchy($departments)
    {
        $hierarchy = [];
        $children = [];
        
        // 构建父子关系映射
        foreach ($departments as $dept) {
            $deptId = $dept['id'];
            $parentId = $dept['parentid'];
            
            if (!isset($children[$parentId])) {
                $children[$parentId] = [];
            }
            $children[$parentId][] = $deptId;
            
            $hierarchy[$deptId] = [
                'id' => $deptId,
                'name' => $dept['name'],
                'parentid' => $parentId,
                'children' => []
            ];
        }
        
        // 设置子部门
        foreach ($hierarchy as $deptId => &$dept) {
            if (isset($children[$deptId])) {
                $dept['children'] = $children[$deptId];
            }
        }
        
        return $hierarchy;
    }

    /**
     * 获取部门路径
     *
     * @param int $deptId 部门ID
     * @param array $hierarchy 部门层级
     * @return array 部门路径
     */
    public function getDepartmentPath($deptId, $hierarchy)
    {
        $path = [];
        $currentId = $deptId;
        
        while ($currentId && isset($hierarchy[$currentId])) {
            $dept = $hierarchy[$currentId];
            array_unshift($path, [
                'id' => $dept['id'],
                'name' => $dept['name']
            ]);
            $currentId = $dept['parentid'];
            
            // 防止无限循环
            if (in_array($currentId, array_column($path, 'id'))) {
                break;
            }
        }
        
        return $path;
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComDataMapper] ' . $message;
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
            $logMessage = '[WeComDataMapper ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . $error;
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
