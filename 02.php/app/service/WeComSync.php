<?php

namespace app\service;

/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信数据同步服务类 - 协调器模式重构版本
 * 
 * 重构说明：
 * - 保持原有接口完全不变，确保向后兼容
 * - 内部使用重构后的专业化服务类
 * - 获得更好的代码结构和可维护性
 */

require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/service/wecom/WeComConfigManager.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataFetcher.php';
require_once BASE_PATH . '/app/service/wecom/WeComDataMapper.php';
require_once BASE_PATH . '/app/service/wecom/WeComUserSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComGroupSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComPermissionSync.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncStatus.php';
require_once BASE_PATH . '/app/service/wecom/WeComSyncRefactored.php';
use app\service\wecom\WeComSyncRefactored;

class WeComSync extends Base
{
    /**
     * 重构后的同步服务实例
     * @var WeComSyncRefactored
     */
    private $syncService;

    /**
     * 构造函数
     * @param array $parm 参数
     */
    function __construct($parm = [])
    {
        try {
            // 调用父类构造函数
            parent::__construct($parm);
        } catch (\Exception $e) {
            // 如果Base类初始化失败，手动初始化必要的属性
            $this->initializeManually($parm);
        }
        
        // 初始化重构后的同步服务
        $this->syncService = new WeComSyncRefactored($parm);
    }

    /**
     * 手动初始化（当Base类初始化失败时的fallback）
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
                
                // 必须使用配置系统获取数据库路径
                require_once BASE_PATH . '/app/util/Config.php';
                \Config::load(BASE_PATH . '/config/');
                $configDatabase = \Config::get('database');
                if (!isset($configDatabase['database_file'])) {
                    throw new \Exception('数据库配置文件路径未设置');
                }
                $databaseFile = $configDatabase['database_file'];
                
                $this->database = new \Medoo\Medoo([
                    'database_type' => 'sqlite',
                    'database_file' => $databaseFile
                ]);
            } catch (\Exception $e) {
                // 数据库连接失败，但不影响功能
                $this->database = null;
            }
        }

        // 初始化配置 - 必须使用配置系统
        try {
            require_once BASE_PATH . '/app/util/Config.php';
            \Config::load(BASE_PATH . '/config/');
            $configSvn = \Config::get('svn');
            if (!empty($configSvn)) {
                $this->configSvn = $configSvn;
            } else {
                throw new \Exception('SVN配置为空');
            }
        } catch (\Exception $configException) {
            // 配置系统失败，抛出异常而不是使用硬编码
            throw new \Exception('无法加载SVN配置: ' . $configException->getMessage());
        }
    }

    /**
     * 执行全量同步
     * @return array 同步结果
     */
    public function fullSync()
    {
        return $this->syncService->fullSync();
    }

    /**
     * 执行增量同步
     * @return array 同步结果
     */
    public function incrementalSync()
    {
        return $this->syncService->incrementalSync();
    }

    /**
     * 执行仅成员同步
     * 基于数据库中现有的企业微信数据，强制同步成员关系
     * @return array 同步结果
     */
    public function memberOnlySync()
    {
        return $this->syncService->memberOnlySync();
    }

    /**
     * 执行纯SVNAdmin方法的企业微信同步
     * 不依赖API配置，只使用现有数据和SVNAdmin原生方法
     * @return array 同步结果
     */
    public function pureSync()
    {
        return $this->syncService->pureSync();
    }

    /**
     * 执行全量同步（带强制参数）
     * @param bool $forceFullSync 是否强制全量同步
     * @return array 同步结果
     */
    public function executeFullSync($forceFullSync = false)
    {
        return $this->syncService->executeFullSync($forceFullSync);
    }

    /**
     * 获取同步统计信息
     * @return array 统计信息
     */
    public function getSyncStats()
    {
        return $this->syncService->getSyncStats();
    }

    /**
     * 获取同步会话ID
     * @return string 会话ID
     */
    public function getSyncSessionId()
    {
        return $this->syncService->getSyncSessionId();
    }

    /**
     * 检查同步状态
     * @return array 同步状态
     */
    public function checkSyncStatus()
    {
        return $this->syncService->checkSyncStatus();
    }

    /**
     * 魔术方法：将未定义的方法调用转发给重构后的服务
     * 这确保了完全的向后兼容性
     * 
     * @param string $method 方法名
     * @param array $arguments 参数
     * @return mixed 方法返回值
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->syncService, $method)) {
            return call_user_func_array([$this->syncService, $method], $arguments);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist in WeComSync or WeComSyncRefactored");
    }
}
