<?php
/*
 * SVNAdmin 路径配置文件
 * 统一管理所有路径配置，避免硬编码
 */

return [
    // 基础路径配置
    'base' => [
        'project_root' => getenv('SVNADMIN_PROJECT_ROOT') ?: '/var/www/html',
        'data_root' => getenv('SVNADMIN_DATA_ROOT') ?: '/home/svnadmin',
    ],
    
    // 数据库配置
    'database' => [
        'sqlite_file' => getenv('SVNADMIN_DB_PATH') ?: null, // 自动检测
        'search_paths' => [
            '/home/svnadmin/svnadmin.db',                     // 生产环境（卷挂载）
            '/var/www/html/templete/database/sqlite/svnadmin.db', // 容器内部
            '/var/www/html/config/svnadmin.db',               // 备用路径
        ],
    ],
    
    // 日志配置
    'logs' => [
        'root' => getenv('SVNADMIN_LOG_ROOT') ?: '/var/www/html/logs',
        'files' => [
            'install' => 'install.log',
            'wecom_migration' => 'wecom_migration.log',
            'migration' => 'migration.log',
            'hook_repair' => 'hook_repair.log',
            'svnadmind' => 'svnadmind.log',
        ],
    ],
    
    // SVN 配置
    'svn' => [
        'data_root' => getenv('SVNADMIN_SVN_ROOT') ?: '/home/svnadmin',
        'repositories' => getenv('SVNADMIN_SVN_REPOS') ?: '/home/svnadmin/rep',
        'authz_file' => getenv('SVNADMIN_AUTHZ_FILE') ?: '/home/svnadmin/authz',
        'passwd_file' => getenv('SVNADMIN_PASSWD_FILE') ?: '/home/svnadmin/passwd',
        'hooks' => getenv('SVNADMIN_HOOKS') ?: '/home/svnadmin/hooks',
    ],
    
    // Apache 配置
    'apache' => [
        'conf_dir' => getenv('SVNADMIN_APACHE_CONF') ?: '/etc/httpd/conf.d',
        'mount_conf' => getenv('SVNADMIN_APACHE_MOUNT') ?: '/home/svnadmin/conf.d',
    ],
    
    // SASL 配置
    'sasl' => [
        'conf_dir' => getenv('SVNADMIN_SASL_CONF') ?: '/etc/sasl2',
        'mount_conf' => getenv('SVNADMIN_SASL_MOUNT') ?: '/home/svnadmin/sasl2',
    ],
    
    // 临时文件配置
    'temp' => [
        'root' => getenv('SVNADMIN_TEMP_ROOT') ?: '/tmp/svnadmin',
        'uploads' => getenv('SVNADMIN_UPLOAD_DIR') ?: '/tmp/svnadmin/uploads',
    ],
    
    // 备份配置
    'backup' => [
        'root' => getenv('SVNADMIN_BACKUP_ROOT') ?: '/home/svnadmin/backup',
        'database' => getenv('SVNADMIN_DB_BACKUP') ?: '/home/svnadmin/backup/database',
    ],
];
?>
