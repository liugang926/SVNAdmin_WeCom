<?php
/**
 * 企业微信用户映射器
 * 
 * 负责SVN用户名与企业微信用户的映射转换
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\util\wecom;

class WeComUserMapper
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
     * @param object $logger 日志服务（可选）
     */
    public function __construct($database, $logger = null)
    {
        $this->database = $database;
        $this->logger = $logger;
    }
    
    /**
     * 转换SVN用户名为真实姓名
     *
     * @param string $svnUsername SVN用户名
     * @return string 真实姓名或原用户名
     */
    public function convertSvnUsernameToRealName($svnUsername)
    {
        if (empty($svnUsername)) {
            return $svnUsername;
        }
        
        try {
            // 从企业微信用户表中查找对应的真实姓名
            $user = $this->database->get('wecom_users', ['real_name'], [
                'svn_username' => $svnUsername,
                'is_active' => 1
            ]);
            
            if ($user && !empty($user['real_name'])) {
                return $user['real_name'];
            }
            
            // 如果没有找到映射，尝试通过wecom_user_id匹配（假设wecom_user_id就是SVN用户名）
            $userByWecomId = $this->database->get('wecom_users', ['real_name'], [
                'wecom_user_id' => $svnUsername,
                'is_active' => 1
            ]);
            
            if ($userByWecomId && !empty($userByWecomId['real_name'])) {
                return $userByWecomId['real_name'];
            }
            
            // 如果都没有找到，返回原用户名
            return $svnUsername;
            
        } catch (\Exception $e) {
            $this->logError('转换用户名失败', $e->getMessage());
            return $svnUsername;
        }
    }
    
    /**
     * 获取用户信息
     *
     * @param string $userId 用户ID
     * @return array 用户信息
     */
    public function getUserInfo($userId)
    {
        try {
            $user = $this->database->get('wecom_users', [
                'userid',
                'name', 
                'real_name',
                'department',
                'position',
                'mobile',
                'email',
                'is_active'
            ], [
                'userid' => $userId
            ]);
            
            return $user ?: [
                'userid' => $userId, 
                'name' => '未知用户', 
                'real_name' => '未知用户',
                'department' => '',
                'position' => '',
                'mobile' => '',
                'email' => '',
                'is_active' => 0
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取用户信息失败', $e->getMessage());
            return [
                'userid' => $userId, 
                'name' => '未知用户', 
                'real_name' => '未知用户',
                'department' => '',
                'position' => '',
                'mobile' => '',
                'email' => '',
                'is_active' => 0
            ];
        }
    }
    
    /**
     * 获取部门信息
     *
     * @param string|int $deptId 部门ID
     * @return array 部门信息
     */
    public function getDepartmentInfo($deptId)
    {
        try {
            $dept = $this->database->get('wecom_departments', [
                'id',
                'name',
                'parentid',
                'order'
            ], [
                'id' => $deptId
            ]);
            
            return $dept ?: [
                'id' => $deptId, 
                'name' => '未知部门', 
                'parentid' => 0,
                'order' => 0
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取部门信息失败', $e->getMessage());
            return [
                'id' => $deptId, 
                'name' => '未知部门', 
                'parentid' => 0,
                'order' => 0
            ];
        }
    }
    
    /**
     * 批量获取用户信息
     *
     * @param array $userIds 用户ID数组
     * @return array 用户信息数组
     */
    public function getBatchUserInfo($userIds)
    {
        if (empty($userIds)) {
            return [];
        }
        
        try {
            $users = $this->database->select('wecom_users', [
                'userid',
                'name',
                'real_name',
                'department',
                'position',
                'mobile',
                'email',
                'is_active'
            ], [
                'userid' => $userIds,
                'is_active' => 1
            ]);
            
            // 创建以userid为键的关联数组
            $userMap = [];
            foreach ($users as $user) {
                $userMap[$user['userid']] = $user;
            }
            
            // 确保所有请求的用户ID都有对应的记录
            $result = [];
            foreach ($userIds as $userId) {
                $result[$userId] = $userMap[$userId] ?? [
                    'userid' => $userId,
                    'name' => '未知用户',
                    'real_name' => '未知用户',
                    'department' => '',
                    'position' => '',
                    'mobile' => '',
                    'email' => '',
                    'is_active' => 0
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('批量获取用户信息失败', $e->getMessage());
            
            // 返回默认信息
            $result = [];
            foreach ($userIds as $userId) {
                $result[$userId] = [
                    'userid' => $userId,
                    'name' => '未知用户',
                    'real_name' => '未知用户',
                    'department' => '',
                    'position' => '',
                    'mobile' => '',
                    'email' => '',
                    'is_active' => 0
                ];
            }
            return $result;
        }
    }
    
    /**
     * 批量获取部门信息
     *
     * @param array $deptIds 部门ID数组
     * @return array 部门信息数组
     */
    public function getBatchDepartmentInfo($deptIds)
    {
        if (empty($deptIds)) {
            return [];
        }
        
        try {
            $departments = $this->database->select('wecom_departments', [
                'id',
                'name',
                'parentid',
                'order'
            ], [
                'id' => $deptIds
            ]);
            
            // 创建以id为键的关联数组
            $deptMap = [];
            foreach ($departments as $dept) {
                $deptMap[$dept['id']] = $dept;
            }
            
            // 确保所有请求的部门ID都有对应的记录
            $result = [];
            foreach ($deptIds as $deptId) {
                $result[$deptId] = $deptMap[$deptId] ?? [
                    'id' => $deptId,
                    'name' => '未知部门',
                    'parentid' => 0,
                    'order' => 0
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('批量获取部门信息失败', $e->getMessage());
            
            // 返回默认信息
            $result = [];
            foreach ($deptIds as $deptId) {
                $result[$deptId] = [
                    'id' => $deptId,
                    'name' => '未知部门',
                    'parentid' => 0,
                    'order' => 0
                ];
            }
            return $result;
        }
    }
    
    /**
     * 根据SVN用户名查找企业微信用户ID
     *
     * @param string $svnUsername SVN用户名
     * @return string|null 企业微信用户ID
     */
    public function findWeComUserIdBySvnUsername($svnUsername)
    {
        if (empty($svnUsername)) {
            return null;
        }
        
        try {
            // 首先通过svn_username字段查找
            $user = $this->database->get('wecom_users', ['userid'], [
                'svn_username' => $svnUsername,
                'is_active' => 1
            ]);
            
            if ($user) {
                return $user['userid'];
            }
            
            // 如果没有找到，尝试通过wecom_user_id匹配
            $userByWecomId = $this->database->get('wecom_users', ['userid'], [
                'userid' => $svnUsername,
                'is_active' => 1
            ]);
            
            return $userByWecomId ? $userByWecomId['userid'] : null;
            
        } catch (\Exception $e) {
            $this->logError('查找企业微信用户ID失败', $e->getMessage());
            return null;
        }
    }
    
    /**
     * 解析通知目标字符串
     *
     * @param string $userIds 用户ID字符串（逗号分隔）
     * @param string $deptIds 部门ID字符串（逗号分隔）
     * @return array 解析后的通知目标信息
     */
    public function parseNotificationTargets($userIds = '', $deptIds = '')
    {
        $targets = [
            'users' => [],
            'departments' => []
        ];
        
        // 解析用户ID
        if (!empty($userIds)) {
            $userIdArray = array_filter(array_map('trim', explode(',', $userIds)));
            if (!empty($userIdArray)) {
                $targets['users'] = $this->getBatchUserInfo($userIdArray);
            }
        }
        
        // 解析部门ID
        if (!empty($deptIds)) {
            $deptIdArray = array_filter(array_map('trim', explode(',', $deptIds)));
            if (!empty($deptIdArray)) {
                $targets['departments'] = $this->getBatchDepartmentInfo($deptIdArray);
            }
        }
        
        return $targets;
    }
    
    /**
     * 格式化通知目标为显示文本
     *
     * @param array $targets 通知目标数组
     * @return string 格式化后的文本
     */
    public function formatNotificationTargets($targets)
    {
        $parts = [];
        
        if (!empty($targets['users'])) {
            $userNames = [];
            foreach ($targets['users'] as $user) {
                $userNames[] = $user['real_name'] . '(' . $user['userid'] . ')';
            }
            $parts[] = '用户: ' . implode(', ', $userNames);
        }
        
        if (!empty($targets['departments'])) {
            $deptNames = [];
            foreach ($targets['departments'] as $dept) {
                $deptNames[] = $dept['name'] . '(' . $dept['id'] . ')';
            }
            $parts[] = '部门: ' . implode(', ', $deptNames);
        }
        
        return empty($parts) ? '无通知目标' : implode('；', $parts);
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
            $this->logger->writeLog('error', '[WeComUserMapper] ' . $message, ['error' => $error]);
        }
    }
}
?>
