<?php

/**
 * 企业微信集成配置模板
 * 
 * 此文件是企业微信集成功能的配置模板，包含了所有可配置的选项和详细说明。
 * 
 * 使用方法：
 * 1. 复制此文件到 02.php/config/wecom.php
 * 2. 根据实际情况修改配置项
 * 3. 运行配置向导或手动配置
 * 
 * @version 1.0
 * @author SVNAdmin Team
 * @date 2024-08-29
 */

return [
    // ==================== 基础配置 ====================
    
    /**
     * 企业微信应用基础信息
     * 
     * 获取方式：
     * 1. 登录企业微信管理后台 (work.weixin.qq.com)
     * 2. 进入"应用管理" → "自建应用"
     * 3. 创建或选择应用，获取以下信息
     */
    'corp_id' => 'your_corp_id_here',           // 企业 ID，在"我的企业" → "企业信息"中查看
    'corp_secret' => 'your_corp_secret_here',   // 应用密钥，在应用详情页面查看
    'agent_id' => 'your_agent_id_here',         // 应用 ID，在应用详情页面查看
    
    /**
     * API 配置
     */
    'api_base_url' => 'https://qyapi.weixin.qq.com',  // 企业微信 API 基础地址
    'api_timeout' => 30,                               // API 请求超时时间（秒）
    'api_retry_count' => 3,                            // API 请求重试次数
    'api_retry_interval' => 5,                         // API 请求重试间隔（秒）
    
    /**
     * 访问令牌配置
     */
    'access_token' => '',                              // 访问令牌（自动获取，无需手动设置）
    'token_expires_at' => 0,                           // 令牌过期时间（自动管理）
    'token_refresh_threshold' => 300,                  // 令牌刷新阈值（秒），提前刷新避免过期
    
    // ==================== 功能开关 ====================
    
    /**
     * 功能启用开关
     */
    'sync_enabled' => true,                            // 是否启用数据同步
    'notification_enabled' => true,                    // 是否启用消息通知
    'debug' => false,                                  // 是否启用调试模式
    'log_enabled' => true,                             // 是否启用日志记录
    'log_level' => 'INFO',                             // 日志级别：DEBUG, INFO, WARN, ERROR
    
    // ==================== 同步配置 ====================
    
    /**
     * 数据同步配置
     */
    'sync' => [
        // 同步间隔配置（秒）
        'department_interval' => 3600,                 // 部门同步间隔（1小时）
        'user_interval' => 1800,                       // 用户同步间隔（30分钟）
        'permission_interval' => 3600,                 // 权限同步间隔（1小时）
        'cleanup_interval' => 86400,                   // 数据清理间隔（24小时）
        
        // 同步选项
        'auto_create_users' => true,                   // 是否自动创建 SVN 用户
        'auto_update_users' => true,                   // 是否自动更新用户信息
        'auto_disable_users' => false,                 // 是否自动禁用已离职用户
        'sync_user_avatar' => false,                   // 是否同步用户头像
        
        // 批量处理配置
        'batch_size' => 100,                           // 批量处理大小
        'max_sync_time' => 300,                        // 最大同步时间（秒）
        
        // 错误处理
        'retry_count' => 3,                            // 同步失败重试次数
        'retry_interval' => 60,                        // 重试间隔（秒）
        'ignore_errors' => false,                      // 是否忽略非关键错误
    ],
    
    // ==================== 部门映射配置 ====================
    
    /**
     * 部门到 SVN 用户组的映射配置
     */
    'department_mapping' => [
        // 用户组命名规则
        'group_name_prefix' => 'wecom_',               // 用户组名前缀
        'group_name_format' => 'lowercase',            // 命名格式：lowercase, uppercase, original
        'auto_create_groups' => true,                  // 是否自动创建用户组
        
        // 层级关系处理
        'preserve_hierarchy' => true,                  // 是否保持部门层级关系
        'max_depth' => 10,                             // 最大层级深度
        'root_department_id' => 1,                     // 根部门 ID
        
        // 特殊部门处理
        'exclude_departments' => [],                   // 排除的部门 ID 列表
        'department_aliases' => [                      // 部门别名映射
            // '部门ID' => '自定义用户组名'
            // '2' => 'tech_team',
            // '3' => 'product_team',
        ],
        
        // 清理配置
        'cleanup_inactive' => true,                    // 是否清理非活跃部门
        'inactive_threshold' => 30,                    // 非活跃阈值（天）
    ],
    
    // ==================== 用户匹配配置 ====================
    
    /**
     * 企业微信用户与 SVN 用户的匹配配置
     */
    'user_matching' => [
        // 匹配策略优先级（按顺序尝试）
        'strategies' => [
            'userid',                                   // 企业微信 userid 与 SVN 用户名匹配
            'email',                                    // 邮箱匹配
            'mobile',                                   // 手机号匹配
            'name',                                     // 姓名模糊匹配
        ],
        
        // 匹配选项
        'case_sensitive' => false,                     // 是否区分大小写
        'fuzzy_match' => true,                         // 是否启用模糊匹配
        'fuzzy_threshold' => 0.8,                      // 模糊匹配阈值（0-1）
        
        // 自动创建用户配置
        'auto_create_svn_users' => false,              // 是否自动创建 SVN 用户
        'default_password' => '123456',                // 新用户默认密码
        'password_policy' => [
            'min_length' => 6,                         // 最小密码长度
            'require_special_chars' => false,          // 是否需要特殊字符
        ],
        
        // 用户信息同步
        'sync_fields' => [
            'name' => true,                            // 同步姓名到备注
            'email' => true,                           // 同步邮箱
            'mobile' => false,                         // 同步手机号
            'department' => true,                      // 同步部门信息
        ],
    ],
    
    // ==================== 权限映射配置 ====================
    
    /**
     * 权限映射配置
     */
    'permission_mapping' => [
        // 默认权限
        'default_permission' => 'r',                   // 默认权限：r(只读), rw(读写), 空(无权限)
        
        // 管理员配置
        'admin_departments' => [1],                    // 管理员部门 ID 列表
        'admin_users' => [],                           // 管理员用户 userid 列表
        'admin_permission' => 'rw',                    // 管理员权限
        
        // 特殊权限配置
        'readonly_departments' => [],                  // 只读部门 ID 列表
        'readonly_users' => [],                        // 只读用户 userid 列表
        
        // 仓库级权限配置
        'repository_permissions' => [
            // '仓库名' => [
            //     'departments' => ['部门ID' => '权限'],
            //     'users' => ['userid' => '权限'],
            // ],
        ],
        
        // 路径级权限配置
        'path_permissions' => [
            // '/trunk' => [
            //     'departments' => ['2' => 'rw', '3' => 'r'],
            //     'users' => ['admin' => 'rw'],
            // ],
        ],
        
        // 权限继承
        'inherit_permissions' => true,                 // 是否继承父级权限
        'merge_permissions' => 'union',                // 权限合并策略：union(并集), intersection(交集)
    ],
    
    // ==================== 通知配置 ====================
    
    /**
     * 消息通知配置
     */
    'notification' => [
        // 通知开关
        'enabled' => true,                             // 是否启用通知
        'queue_enabled' => true,                       // 是否启用消息队列
        'batch_processing' => true,                    // 是否启用批量处理
        
        // 默认通知配置
        'default_webhook' => '',                       // 默认 Webhook 地址
        'default_template' => '📝 SVN 操作通知\n仓库: {repository}\n作者: {author}\n版本: {revision}\n说明: {message}',
        
        // 消息队列配置
        'queue' => [
            'max_size' => 1000,                       // 队列最大大小
            'batch_size' => 50,                       // 批量处理大小
            'process_interval' => 30,                 // 处理间隔（秒）
            'max_retries' => 3,                       // 最大重试次数
            'retry_interval' => 300,                  // 重试间隔（秒）
        ],
        
        // 通知规则
        'rules' => [
            // 示例规则（可在 Web 界面中管理）
            [
                'rule_name' => '默认提交通知',
                'repo_name' => '*',                    // * 表示所有仓库
                'event_type' => 'commit',
                'webhook_url' => '',                   // 留空使用默认 Webhook
                'message_template' => '',              // 留空使用默认模板
                'path_filter' => '',                   // 路径过滤，逗号分隔
                'user_filter' => '',                   // 用户过滤，逗号分隔
                'is_enabled' => false,                 // 默认禁用，需手动启用
            ],
        ],
        
        // 消息格式配置
        'message_format' => [
            'max_length' => 4096,                     // 最大消息长度
            'truncate_long_message' => true,          // 是否截断长消息
            'include_diff' => false,                  // 是否包含代码差异
            'max_diff_lines' => 50,                   // 最大差异行数
        ],
        
        // 频率限制
        'rate_limit' => [
            'enabled' => true,                        // 是否启用频率限制
            'max_per_minute' => 60,                   // 每分钟最大通知数
            'max_per_hour' => 1000,                   // 每小时最大通知数
        ],
    ],
    
    // ==================== 缓存配置 ====================
    
    /**
     * 缓存配置
     */
    'cache' => [
        'enabled' => true,                             // 是否启用缓存
        'driver' => 'file',                            // 缓存驱动：file, redis, memcached
        'ttl' => 3600,                                 // 默认缓存时间（秒）
        
        // 文件缓存配置
        'file' => [
            'path' => '/tmp/svnadmin_wecom_cache',     // 缓存文件路径
        ],
        
        // Redis 缓存配置
        'redis' => [
            'host' => '127.0.0.1',                    // Redis 主机
            'port' => 6379,                           // Redis 端口
            'password' => '',                         // Redis 密码
            'database' => 0,                          // Redis 数据库
            'prefix' => 'svnadmin_wecom:',            // 键前缀
        ],
        
        // 缓存策略
        'strategies' => [
            'access_token' => 7200,                   // 访问令牌缓存时间
            'department_list' => 3600,                // 部门列表缓存时间
            'user_list' => 1800,                      // 用户列表缓存时间
            'user_detail' => 3600,                    // 用户详情缓存时间
        ],
    ],
    
    // ==================== 日志配置 ====================
    
    /**
     * 日志配置
     */
    'logging' => [
        'enabled' => true,                             // 是否启用日志
        'level' => 'INFO',                             // 日志级别：DEBUG, INFO, WARN, ERROR
        'max_files' => 30,                             // 最大日志文件数
        'max_size' => '10MB',                          // 单个日志文件最大大小
        
        // 日志文件配置
        'files' => [
            'api' => '02.php/logs/wecom_api.log',             // API 调用日志
            'sync' => '02.php/logs/wecom_sync.log',           // 同步操作日志
            'notification' => '02.php/logs/wecom_notification.log', // 通知日志
            'error' => '02.php/logs/wecom_error.log',         // 错误日志
        ],
        
        // 日志格式
        'format' => '[{datetime}] {level}: {message} {context}',
        'date_format' => 'Y-m-d H:i:s',
        
        // 敏感信息过滤
        'filter_sensitive' => true,                   // 是否过滤敏感信息
        'sensitive_fields' => [                       // 敏感字段列表
            'corp_secret',
            'access_token',
            'password',
        ],
    ],
    
    // ==================== 安全配置 ====================
    
    /**
     * 安全配置
     */
    'security' => [
        // IP 白名单
        'ip_whitelist' => [],                          // IP 白名单，空数组表示不限制
        
        // API 安全
        'verify_ssl' => true,                          // 是否验证 SSL 证书
        'user_agent' => 'SVNAdmin-WeChat-Integration/1.0', // User-Agent
        
        // 数据加密
        'encrypt_sensitive_data' => false,             // 是否加密敏感数据
        'encryption_key' => '',                        // 加密密钥
        
        // 访问控制
        'require_admin' => true,                       // 是否需要管理员权限
        'session_timeout' => 3600,                    // 会话超时时间（秒）
    ],
    
    // ==================== 高级配置 ====================
    
    /**
     * 高级配置选项
     */
    'advanced' => [
        // 性能优化
        'enable_gzip' => true,                         // 是否启用 Gzip 压缩
        'connection_pool_size' => 10,                  // 连接池大小
        'max_concurrent_requests' => 5,                // 最大并发请求数
        
        // 调试选项
        'debug_api_calls' => false,                    // 是否调试 API 调用
        'debug_sql_queries' => false,                  // 是否调试 SQL 查询
        'profile_performance' => false,                // 是否启用性能分析
        
        // 实验性功能
        'experimental_features' => [
            'async_notifications' => false,            // 异步通知（实验性）
            'smart_caching' => false,                  // 智能缓存（实验性）
            'auto_scaling' => false,                   // 自动扩展（实验性）
        ],
        
        // 兼容性选项
        'compatibility' => [
            'legacy_api_support' => false,             // 是否支持旧版 API
            'strict_mode' => true,                     // 是否启用严格模式
        ],
    ],
    
    // ==================== 监控配置 ====================
    
    /**
     * 监控和统计配置
     */
    'monitoring' => [
        'enabled' => true,                             // 是否启用监控
        'collect_metrics' => true,                     // 是否收集指标
        'metrics_retention' => 30,                     // 指标保留天数
        
        // 健康检查
        'health_check' => [
            'enabled' => true,                         // 是否启用健康检查
            'interval' => 300,                         // 检查间隔（秒）
            'timeout' => 30,                           // 检查超时（秒）
        ],
        
        // 告警配置
        'alerts' => [
            'enabled' => false,                        // 是否启用告警
            'webhook_url' => '',                       // 告警 Webhook 地址
            'thresholds' => [
                'api_error_rate' => 0.1,               // API 错误率阈值
                'sync_failure_rate' => 0.05,           // 同步失败率阈值
                'notification_failure_rate' => 0.1,    // 通知失败率阈值
            ],
        ],
    ],
    
    // ==================== 版本信息 ====================
    
    /**
     * 版本和元数据信息
     */
    'version' => '1.0.0',                             // 配置版本
    'created_at' => '',                               // 创建时间（自动设置）
    'updated_at' => '',                               // 更新时间（自动设置）
    'config_hash' => '',                              // 配置哈希（自动计算）
    
    // ==================== 自定义配置 ====================
    
    /**
     * 自定义配置项
     * 
     * 您可以在这里添加自定义的配置项，用于扩展功能或特殊需求
     */
    'custom' => [
        // 示例自定义配置
        // 'custom_field' => 'custom_value',
    ],
];

/**
 * 配置说明：
 * 
 * 1. 必填配置项：
 *    - corp_id: 企业 ID
 *    - corp_secret: 应用密钥
 *    - agent_id: 应用 ID
 * 
 * 2. 重要配置项：
 *    - sync_enabled: 控制是否启用数据同步
 *    - notification_enabled: 控制是否启用消息通知
 *    - debug: 开发调试时可启用
 * 
 * 3. 性能相关：
 *    - cache.enabled: 启用缓存可显著提升性能
 *    - sync.batch_size: 根据数据量调整批量处理大小
 *    - notification.queue_enabled: 启用消息队列提升通知性能
 * 
 * 4. 安全相关：
 *    - security.verify_ssl: 生产环境建议启用
 *    - logging.filter_sensitive: 避免敏感信息泄露
 *    - security.ip_whitelist: 限制访问来源
 * 
 * 5. 维护相关：
 *    - logging.max_files: 控制日志文件数量
 *    - sync.cleanup_interval: 定期清理过期数据
 *    - monitoring.enabled: 启用监控便于运维
 */
