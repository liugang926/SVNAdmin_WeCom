<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: QQ:1801168257
 */

/**
 * 修改该配置文件后需要重启守护进程程序(svnadmind.php)
 */

return [
    /**
     * socket_read 和 socket_write 的最大传输字节(B)
     * 
     * 默认值 500 KB
     * 
     * 1MB = 1024KB = 1024*1024B
     */
    'socket_data_length' => 500 * 1024,

    /**
     * socket 处理并发的最大队列长度
     */
    'socket_listen_backlog' => 2000,

    /**
     * 企业微信同步相关配置
     */
    'wecom_sync' => [
        /**
         * 是否启用企业微信自动同步
         */
        'enabled' => true,

        /**
         * 同步间隔时间（秒）
         * 默认 300 秒（5分钟）
         */
        'sync_interval' => 300,

        /**
         * 数据清理间隔（秒）
         * 默认 86400 秒（24小时）
         */
        'cleanup_interval' => 86400,

        /**
         * 同步失败重试次数
         */
        'retry_count' => 3,

        /**
         * 同步失败重试间隔（秒）
         */
        'retry_interval' => 60,
    ],
];
