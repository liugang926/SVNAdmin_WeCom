<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: QQ:1801168257
 */

/**
 * 企业微信集成配置
 * 
 * 配置企业微信 API 接入参数和功能开关
 * 获取配置信息请参考：https://developer.work.weixin.qq.com/
 */

return [
    /**
     * 功能开关
     */
    'enabled' => false, // 是否启用企业微信集成功能

    /**
     * 企业微信 API 配置
     */
    'corp_id' => '', // 企业微信企业ID (必填)
    'corp_secret' => '', // 企业微信应用密钥 (必填)
    'agent_id' => '', // 企业微信应用ID (必填)

    /**
     * API 接口配置
     */
    'api_base_url' => 'https://qyapi.weixin.qq.com/cgi-bin/', // 企业微信 API 基础URL
    'token_cache_time' => 7200, // 访问令牌缓存时间（秒）
    'request_timeout' => 30, // API 请求超时时间（秒）
    'max_retry_times' => 3, // API 请求失败重试次数

    /**
     * 同步配置
     */
    'sync' => [
        'enabled' => true, // 是否启用自动同步
        'interval' => 3600, // 自动同步间隔（秒），默认1小时
        'full_sync_interval' => 86400, // 全量同步间隔（秒），默认24小时
        'batch_size' => 100, // 批量处理大小
        'max_departments' => 1000, // 最大部门数量限制
        'max_users' => 10000, // 最大用户数量限制
    ],

    /**
     * 用户匹配规则配置
     */
    'user_mapping' => [
        'match_fields' => ['userid', 'email', 'mobile'], // 匹配字段优先级
        'auto_create_user' => false, // 是否自动创建不存在的 SVN 用户
        'auto_disable_user' => true, // 是否自动禁用离职用户
        'username_prefix' => '', // SVN 用户名前缀
        'username_suffix' => '', // SVN 用户名后缀
        'default_password' => '123456', // 新用户默认密码
    ],

    /**
     * 部门映射配置
     */
    'department_mapping' => [
        'root_department_id' => 1, // 企业微信根部门ID
        'group_name_prefix' => '', // SVN 用户组名前缀
        'group_name_format' => '{prefix}{dept_name}', // 用户组名格式
        'inherit_permissions' => true, // 是否继承父部门权限
        'auto_create_groups' => true, // 是否自动创建用户组
    ],

    /**
     * 通知配置
     */
    'notification' => [
        'enabled' => true, // 是否启用通知功能
        'default_chat_id' => '', // 默认通知群聊ID
        'message_format' => 'markdown', // 消息格式：text, markdown
        'max_message_length' => 4096, // 最大消息长度
        'rate_limit' => [
            'enabled' => true, // 是否启用频率限制
            'max_messages_per_minute' => 20, // 每分钟最大消息数
            'merge_similar_messages' => true, // 是否合并相似消息
        ],
        'events' => [
            'commit' => true, // 提交事件
            'update' => false, // 更新事件
            'delete' => true, // 删除事件
            'copy' => false, // 复制事件
            'move' => false, // 移动事件
        ],
    ],

    /**
     * 消息模板配置
     */
    'message_templates' => [
        'commit' => [
            'title' => 'SVN 代码提交通知',
            'content' => "**仓库**: {repo_name}\n**提交者**: {author}\n**时间**: {date}\n**修改文件**: {files}\n**提交信息**: {message}",
        ],
        'delete' => [
            'title' => 'SVN 文件删除通知',
            'content' => "**仓库**: {repo_name}\n**操作者**: {author}\n**时间**: {date}\n**删除文件**: {files}",
        ],
        'sync_success' => [
            'title' => '企业微信同步成功',
            'content' => "**同步时间**: {date}\n**同步部门**: {dept_count} 个\n**同步用户**: {user_count} 个\n**匹配成功**: {matched_count} 个",
        ],
        'sync_error' => [
            'title' => '企业微信同步失败',
            'content' => "**错误时间**: {date}\n**错误信息**: {error_message}\n**建议**: 请检查网络连接和API配置",
        ],
    ],

    /**
     * 日志配置
     */
    'logging' => [
        'enabled' => true, // 是否启用日志
        'level' => 'info', // 日志级别：debug, info, warning, error
        'max_file_size' => 10485760, // 最大日志文件大小（字节），默认10MB
        'max_files' => 5, // 最大日志文件数量
        'log_api_requests' => false, // 是否记录API请求详情（调试用）
    ],

    /**
     * 缓存配置
     */
    'cache' => [
        'enabled' => true, // 是否启用缓存
        'type' => 'file', // 缓存类型：file, redis（暂时只支持文件缓存）
        'ttl' => 3600, // 默认缓存时间（秒）
        'prefix' => 'wecom_', // 缓存键前缀
    ],

    /**
     * 安全配置
     */
    'security' => [
        'encrypt_secrets' => true, // 是否加密存储敏感信息
        'allowed_ips' => [], // 允许访问的IP地址列表（空数组表示不限制）
        'webhook_token' => '', // Webhook 验证令牌
        'api_rate_limit' => [
            'enabled' => true, // 是否启用API频率限制
            'max_requests_per_minute' => 600, // 每分钟最大请求数
        ],
    ],

    /**
     * 调试配置
     */
    'debug' => [
        'enabled' => false, // 是否启用调试模式
        'log_level' => 'debug', // 调试日志级别
        'mock_api' => false, // 是否使用模拟API（测试用）
        'save_api_responses' => false, // 是否保存API响应（调试用）
    ],
];
