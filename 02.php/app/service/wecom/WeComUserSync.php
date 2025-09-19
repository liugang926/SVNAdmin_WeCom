<?php

namespace app\service\wecom;

require_once BASE_PATH . '/app/service/base/Base.php';
use app\service\Base;

require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncStatus.php';
use app\service\Logs as ServiceLogs;
use app\service\Svnuser as ServiceSvnuser;

/**
 * 企业微信用户同步器
 * 
 * 负责企业微信用户与SVN用户之间的同步，包括：
 * - 用户创建、更新、匹配
 * - 用户状态管理（启用/禁用）
 * - 用户信息同步
 * - 批量用户处理
 */
class WeComUserSync extends Base
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
     * SVN用户服务
     * @var ServiceSvnuser
     */
    private $ServiceSvnuser;

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
            $this->ServiceSvnuser = new ServiceSvnuser($parm);
        } catch (\Exception $e) {
            $this->ServiceSvnuser = null;
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
     * 同步用户数据
     *
     * @param array $users 企业微信用户数据
     * @return array 同步结果
     */
    public function syncUsers($users)
    {
        $this->logInfo('开始同步用户数据', ['count' => count($users)]);

        $this->syncStatus->setTotals(0, count($users));
        $results = [];

        try {
            // 获取现有的企业微信用户数据
            $existingWeComUsers = $this->getExistingWeComUsers();
            $existingSvnUsers = $this->getExistingSvnUsers();

            foreach ($users as $user) {
                try {
                    $result = $this->processUser($user, $existingWeComUsers, $existingSvnUsers);
                    $results[] = $result;
                    
                    // 更新统计
                    $this->syncStatus->incrementUserCount($result['action']);
                    
                } catch (\Exception $e) {
                    $this->logError('处理用户失败', [
                        'user_id' => $user['userid'],
                        'user_name' => $user['name'],
                        'error' => $e->getMessage()
                    ]);
                    
                    $this->syncStatus->addSyncError("用户 {$user['name']} 同步失败: " . $e->getMessage());
                    $results[] = [
                        'user_id' => $user['userid'],
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            $this->logInfo('用户数据同步完成', [
                'total' => count($users),
                'processed' => count($results)
            ]);

            return [
                'status' => 1,
                'message' => '用户同步完成',
                'data' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('用户同步失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量同步用户数据
     *
     * @param array $users 企业微信用户数据
     * @param int $batchSize 批次大小
     * @return array 同步结果
     */
    public function syncUsersBatch($users, $batchSize = 20)
    {
        $this->logInfo('开始分批同步用户数据', ['total_count' => count($users), 'batch_size' => $batchSize]);
        
        $this->syncStatus->setTotals(0, count($users));
        $results = [];
        $batches = array_chunk($users, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $this->logInfo('处理用户批次', [
                'batch' => $batchIndex + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch)
            ]);

            try {
                $batchResult = $this->syncUsers($batch);
                $results = array_merge($results, $batchResult['data']);
                
            } catch (\Exception $e) {
                $this->logError('用户批次处理失败', [
                    'batch' => $batchIndex + 1,
                    'error' => $e->getMessage()
                ]);
                
                // 继续处理下一批次
                continue;
            }
        }

        return [
            'status' => 1,
            'message' => '批量用户同步完成',
            'data' => $results
        ];
    }

    /**
     * 处理单个用户
     *
     * @param array $user 企业微信用户数据
     * @param array $existingWeComUsers 现有企业微信用户
     * @param array $existingSvnUsers 现有SVN用户
     * @return array 处理结果
     */
    public function processUser($user, $existingWeComUsers, &$existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        // 检查企业微信用户是否已存在
        $existingWeComUser = $existingWeComUsers[$userId] ?? null;

        if ($existingWeComUser) {
            // 更新现有用户
            return $this->updateUser($user, $existingWeComUser, $existingSvnUsers);
        } else {
            // 创建新用户
            return $this->createUser($user, $existingSvnUsers);
        }
    }

    /**
     * 创建新用户
     *
     * @param array $user 企业微信用户数据
     * @param array $existingSvnUsers 现有SVN用户
     * @return array 创建结果
     */
    public function createUser($user, &$existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        // 尝试匹配现有的 SVN 用户
        $matchedSvnUser = $this->dataMapper->matchSvnUser($user, $existingSvnUsers);

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

        } elseif ($this->configManager->isAutoCreateUserEnabled()) {
            // 自动创建新的 SVN 用户
            $svnUserId = $this->createSvnUser($user, $existingSvnUsers);
            $action = 'created';

        } else {
            // 不自动创建用户，记录警告
            $this->syncStatus->addSyncWarning("企业微信用户未匹配到SVN用户且未启用自动创建: {$userName}");
            
            return [
                'user_id' => $userId,
                'action' => 'skipped',
                'message' => '用户未匹配且未启用自动创建',
                'svn_user_id' => null
            ];
        }

        // 插入企业微信用户记录
        $this->database->insert('wecom_users', [
            'wecom_user_id' => $userId,
            'real_name' => $userName,
            'name_en' => $user['name_en'] ?? '',
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
            'message' => $action === 'matched' ? '匹配到现有SVN用户' : '创建新用户成功',
            'svn_user_id' => $svnUserId
        ];
    }

    /**
     * 更新现有用户
     *
     * @param array $user 企业微信用户数据
     * @param array $existingWeComUser 现有企业微信用户
     * @param array $existingSvnUsers 现有SVN用户
     * @return array 更新结果
     */
    public function updateUser($user, $existingWeComUser, $existingSvnUsers)
    {
        $userId = $user['userid'];
        $userName = $user['name'];

        $updateData = [];
        $needUpdate = false;

        // 检查需要更新的字段
        if ($existingWeComUser['real_name'] !== $userName) {
            $updateData['real_name'] = $userName;
            $needUpdate = true;
        }

        if (($existingWeComUser['mobile'] ?? '') !== ($user['mobile'] ?? '')) {
            $updateData['mobile'] = $user['mobile'] ?? '';
            $needUpdate = true;
        }

        if (($existingWeComUser['email'] ?? '') !== ($user['email'] ?? '')) {
            $updateData['email'] = $user['email'] ?? '';
            $needUpdate = true;
        }

        $newDepartmentIds = json_encode($user['department'] ?? []);
        if ($existingWeComUser['department_ids'] !== $newDepartmentIds) {
            $updateData['department_ids'] = $newDepartmentIds;
            $needUpdate = true;
        }

        // 检查是否需要重新匹配 SVN 用户
        if (!$existingWeComUser['svn_user_id']) {
            $matchedSvnUser = $this->dataMapper->matchSvnUser($user, $existingSvnUsers);
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

            return [
                'user_id' => $userId,
                'action' => 'updated',
                'message' => '用户信息已更新',
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
     * 创建SVN用户
     *
     * @param array $wecomUser 企业微信用户
     * @param array $existingSvnUsers 现有SVN用户列表
     * @return int SVN用户ID
     * @throws \Exception
     */
    public function createSvnUser($wecomUser, &$existingSvnUsers)
    {
        $userId = $wecomUser['userid'];
        $userName = $wecomUser['name'];
        
        // 生成 SVN 用户名
        $svnUserName = $this->dataMapper->generateSvnUserName($wecomUser);
        
        // 检查用户名是否已存在
        if ($this->dataMapper->isSvnUserNameExists($svnUserName, $existingSvnUsers)) {
            throw new \Exception("SVN用户名已存在: {$svnUserName}");
        }

        try {
            // 插入到 svn_users 表
            $this->database->insert('svn_users', [
                'svn_user_name' => $svnUserName,
                'svn_user_pass' => password_hash($this->configManager->getDefaultPassword(), PASSWORD_DEFAULT),
                'svn_user_mail' => $wecomUser['email'] ?? '',
                'svn_user_note' => $this->dataMapper->generateUserNote($wecomUser, $userId),
                'svn_user_status' => 1,
                'svn_user_create_time' => date('Y-m-d H:i:s')
            ]);

            $svnUserId = $this->database->id();

            // 更新现有用户列表
            $existingSvnUsers[] = [
                'svn_user_id' => $svnUserId,
                'svn_user_name' => $svnUserName,
                'svn_user_mail' => $wecomUser['email'] ?? '',
                'svn_user_note' => $this->dataMapper->generateUserNote($wecomUser, $userId)
            ];

            $this->logInfo('创建SVN用户成功', [
                'wecom_user_id' => $userId,
                'wecom_user_name' => $userName,
                'svn_user_id' => $svnUserId,
                'svn_user_name' => $svnUserName
            ]);

            return $svnUserId;

        } catch (\Exception $e) {
            $this->logError('创建SVN用户失败', [
                'wecom_user_id' => $userId,
                'svn_user_name' => $svnUserName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 更新SVN用户的企业微信信息
     *
     * @param array $svnUser SVN用户
     * @param array $wecomUser 企业微信用户
     */
    public function updateSvnUserWeComInfo($svnUser, $wecomUser)
    {
        try {
            $updateData = [];
            $needUpdate = false;

            // 同步备注：更新为企业微信信息和同步时间
            $newNote = $this->dataMapper->generateUserNote($wecomUser, $wecomUser['userid']);
            if (empty($svnUser['svn_user_note']) || strpos($svnUser['svn_user_note'], '企业微信同步') !== false) {
                $updateData['svn_user_note'] = $newNote;
                $needUpdate = true;
            }

            // 同步邮箱
            $newEmail = $wecomUser['email'] ?? '';
            if ($svnUser['svn_user_mail'] !== $newEmail) {
                $updateData['svn_user_mail'] = $newEmail;
                $needUpdate = true;
            }

            if ($needUpdate) {
                $this->database->update('svn_users', $updateData, [
                    'svn_user_id' => $svnUser['svn_user_id']
                ]);

                $this->logInfo('更新SVN用户企业微信信息', [
                    'svn_user_id' => $svnUser['svn_user_id'],
                    'svn_user_name' => $svnUser['svn_user_name'],
                    'changes' => $updateData
                ]);
            }

        } catch (\Exception $e) {
            $this->logError('更新SVN用户企业微信信息失败', [
                'svn_user_id' => $svnUser['svn_user_id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 禁用SVN用户
     *
     * @param int $svnUserId SVN用户ID
     */
    public function disableSvnUser($svnUserId)
    {
        try {
            $this->database->update('svn_users', [
                'svn_user_status' => 0,
                'svn_user_note' => '用户已被企业微信同步禁用 - ' . date('Y-m-d H:i:s')
            ], [
                'svn_user_id' => $svnUserId
            ]);
            
            $this->logInfo('禁用SVN用户', ['svn_user_id' => $svnUserId]);
            
        } catch (\Exception $e) {
            $this->logError('禁用SVN用户失败', [
                'svn_user_id' => $svnUserId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 删除用户记录
     *
     * @param string $userId 企业微信用户ID
     */
    public function deleteUserRecord($userId)
    {
        try {
            // 获取用户信息
            $user = $this->database->get('wecom_users', '*', [
                'wecom_user_id' => $userId
            ]);
            
            if (!$user) {
                return;
            }
            
            if ($this->configManager->isAutoDisableUserEnabled() && $user['svn_user_id']) {
                // 禁用对应的 SVN 用户
                $this->disableSvnUser($user['svn_user_id']);
            }

            // 删除企业微信用户记录
            $this->database->delete('wecom_users', [
                'wecom_user_id' => $userId
            ]);

            $this->logInfo('删除用户记录', [
                'wecom_user_id' => $userId,
                'real_name' => $user['real_name']
            ]);

        } catch (\Exception $e) {
            $this->logError('删除用户记录失败', [
                'wecom_user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取现有企业微信用户
     *
     * @return array 用户ID为键的用户数组
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
            $this->logError('获取现有企业微信用户失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 获取现有SVN用户
     *
     * @return array SVN用户列表
     */
    private function getExistingSvnUsers()
    {
        try {
            return $this->database->select('svn_users', [
                'svn_user_id',
                'svn_user_name',
                'svn_user_mail',
                'svn_user_note',
                'svn_user_status'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('获取现有SVN用户失败', $e->getMessage());
            return [];
        }
    }

    /**
     * 根据ID获取SVN用户
     *
     * @param int $svnUserId SVN用户ID
     * @param array $existingSvnUsers 现有SVN用户列表
     * @return array|null SVN用户信息
     */
    private function getSvnUserById($svnUserId, $existingSvnUsers)
    {
        foreach ($existingSvnUsers as $user) {
            if ($user['svn_user_id'] == $svnUserId) {
                return $user;
            }
        }
        return null;
    }

    /**
     * 记录信息日志
     */
    private function logInfo($message, $context = [])
    {
        if ($this->configManager->isLogEnabled()) {
            $logMessage = '[WeComUserSync] ' . $message;
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
            $logMessage = '[WeComUserSync ERROR] ' . $message;
            if (!empty($error)) {
                $logMessage .= ': ' . (is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : $error);
            }
            
            if ($this->ServiceLogs) {
                $this->ServiceLogs->writeLog('wecom_sync_error', $logMessage);
            }
        }
    }
}
