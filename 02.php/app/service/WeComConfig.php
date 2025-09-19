<?php

namespace app\service;

/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信配置管理服务类
 */

require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/util/DatabaseHelper.php';

use app\util\DatabaseHelper;

class WeComConfig extends Base
{
    /**
     * 配置缓存
     *
     * @var array
     */
    private static $configCache = null;

    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     * 获取企业微信配置
     * 优先从数据库读取，如果数据库没有则从配置文件读取
     *
     * @return array
     */
    public static function getConfig()
    {
        // 如果已有缓存，直接返回
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        try {
            // 使用统一的数据库连接帮助类
            $database = DatabaseHelper::getConnection();
            
            // 尝试从数据库读取配置
            if ($database) {
                $dbConfig = $database->get('wecom_config', '*', [
                    'ORDER' => ['id' => 'DESC'],
                    'LIMIT' => 1
                ]);
            } else {
                $dbConfig = null;
            }

            if ($dbConfig && !empty($dbConfig['corp_id'])) {
                // 从数据库构建配置
                self::$configCache = self::buildConfigFromDatabase($dbConfig);
                return self::$configCache;
            }
        } catch (\Exception $e) {
            // 数据库读取失败，继续尝试文件
        }

        // 从配置文件读取
        $configFile = BASE_PATH . '/config/wecom.php';
        if (file_exists($configFile)) {
            self::$configCache = require $configFile;
            return self::$configCache;
        }

        // 返回默认配置
        self::$configCache = self::getDefaultConfig();
        return self::$configCache;
    }

    /**
     * 从数据库记录构建配置数组
     *
     * @param array $dbConfig
     * @return array
     */
    private static function buildConfigFromDatabase($dbConfig)
    {
        $config = self::getDefaultConfig();
        
        // 基础配置
        $config['corp_id'] = $dbConfig['corp_id'] ?? '';
        $config['corp_secret'] = $dbConfig['corp_secret'] ?? '';
        $config['agent_id'] = $dbConfig['agent_id'] ?? '';
        $config['aes_key'] = $dbConfig['aes_key'] ?? '';
        $config['token'] = $dbConfig['token'] ?? '';
        
        // 解析额外配置数据
        if (!empty($dbConfig['config_data'])) {
            try {
                $extraConfig = json_decode($dbConfig['config_data'], true);
                if (is_array($extraConfig)) {
                    $config = array_merge($config, $extraConfig);
                }
            } catch (\Exception $e) {
                // 忽略JSON解析错误
            }
        }

        // 智能判断是否启用：检查是否有基本的企业微信配置
        $config['enabled'] = self::shouldEnableWeComIntegration($dbConfig);

        return $config;
    }

    /**
     * 智能判断是否应该启用企业微信集成
     * 
     * @param array $dbConfig 数据库配置
     * @return bool
     */
    private static function shouldEnableWeComIntegration($dbConfig)
    {
        // 首先检查数据库中的sync_enabled字段
        if (isset($dbConfig['sync_enabled']) && !$dbConfig['sync_enabled']) {
            return false;
        }
        
        // 检查基本的企业微信配置是否完整
        $hasBasicConfig = !empty($dbConfig['corp_id']) && 
                         !empty($dbConfig['corp_secret']) && 
                         !empty($dbConfig['agent_id']);
        
        // 只要有基本配置且sync_enabled为true就启用
        return $hasBasicConfig;
    }
    

    /**
     * 获取默认配置
     *
     * @return array
     */
    private static function getDefaultConfig()
    {
        return [
            'enabled' => false,
            'corp_id' => '',
            'corp_secret' => '',
            'agent_id' => '',
            'aes_key' => '',
            'token' => '',
            'api_base_url' => 'https://qyapi.weixin.qq.com/cgi-bin',
            'contact_sync' => [
                'enable' => true,
                'department_root_id' => 1,
                'sync_interval' => 3600,
                'update_authz_file' => false,
                'group_name_prefix' => '',
                'auto_create_svn_group' => true,
                'auto_remove_user_from_group' => false,
            ],
            'notification' => [
                'enabled' => false,
                'default_touser' => '@all',
                'default_toparty' => '',
                'default_totag' => '',
                'safe' => 0,
                'enable_duplicate_check' => 1,
                'duplicate_check_interval' => 1800
            ],
            'message_templates' => [
                'svn_commit' => [
                    'title' => 'SVN 提交通知',
                    'content' => "用户 {user} 在仓库 {repository} 提交了代码\n\n提交信息: {message}\n版本号: {revision}\n时间: {datetime}"
                ],
                'svn_update' => [
                    'title' => 'SVN 更新通知', 
                    'content' => "用户 {user} 更新了仓库 {repository}\n\n版本号: {revision}\n时间: {datetime}"
                ]
            ]
        ];
    }

    /**
     * 清除配置缓存
     */
    public static function clearCache()
    {
        self::$configCache = null;
    }

    /**
     * 保存配置到数据库和文件
     *
     * @param array $config
     * @return bool
     */
    public static function saveConfig($config)
    {
        try {
            global $database;
            
            // 如果数据库连接不存在，尝试初始化
            if (!$database) {
                require_once BASE_PATH . '/config/database.php';
                require_once BASE_PATH . '/config/svn.php';
                require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';
                

                
                $configDatabase = require BASE_PATH . '/config/database.php';
                $configSvn = require BASE_PATH . '/config/svn.php';
                
                // 处理SQLite数据库文件路径占位符
                if (array_key_exists('database_file', $configDatabase)) {
                    $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
                }
                
                try {
                    $database = new \Medoo\Medoo($configDatabase);
                } catch (\Exception $e) {
                    // 数据库连接失败
                    return false;
                }
            }
            
            // 保存到数据库
            $dbData = [
                'corp_id' => $config['corp_id'] ?? '',
                'corp_secret' => $config['corp_secret'] ?? '',
                'agent_id' => $config['agent_id'] ?? '',
                'aes_key' => $config['aes_key'] ?? '',
                'token' => $config['token'] ?? '',
                'config_data' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // 检查是否已有配置记录
            $existingConfig = $database->get('wecom_config', 'id', [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 1
            ]);

            if ($existingConfig) {
                $database->update('wecom_config', $dbData, ['id' => $existingConfig]);
            } else {
                $dbData['created_at'] = date('Y-m-d H:i:s');
                $database->insert('wecom_config', $dbData);
            }

            // 保存到配置文件
            $configFile = BASE_PATH . '/config/wecom.php';
            $configContent = "<?php\n/*\n * 企业微信集成配置\n * 自动生成，请勿手动编辑\n */\n\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configFile, $configContent);

            // 清除缓存
            self::clearCache();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
