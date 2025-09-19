<?php
/*
 * SVNAdmin 路径管理器
 * 提供统一的路径管理和自动检测功能
 */

class PathManager
{
    private static $config = null;
    private static $detectedPaths = [];
    
    /**
     * 初始化路径管理器
     */
    public static function init($configFile = null)
    {
        if (self::$config === null) {
            $configFile = $configFile ?: dirname(__DIR__) . '/config/paths.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                // 使用默认配置
                self::$config = self::getDefaultConfig();
            }
        }
    }
    
    /**
     * 获取默认配置
     */
    private static function getDefaultConfig()
    {
        return [
            'base' => [
                'project_root' => '/var/www/html',
                'data_root' => '/home/svnadmin',
            ],
            'database' => [
                'sqlite_file' => null,
                'search_paths' => [
                    '/home/svnadmin/svnadmin.db',
                    '/var/www/html/templete/database/sqlite/svnadmin.db',
                    '/var/www/html/config/svnadmin.db',
                ],
            ],
            'logs' => [
                'root' => '/var/www/html/logs',
                'files' => [
                    'install' => 'install.log',
                    'wecom_migration' => 'wecom_migration.log',
                    'migration' => 'migration.log',
                    'hook_repair' => 'hook_repair.log',
                    'svnadmind' => 'svnadmind.log',
                ],
            ],
        ];
    }
    
    /**
     * 获取项目根目录
     */
    public static function getProjectRoot()
    {
        self::init();
        return self::$config['base']['project_root'];
    }
    
    /**
     * 获取数据根目录
     */
    public static function getDataRoot()
    {
        self::init();
        return self::$config['base']['data_root'];
    }
    
    /**
     * 智能检测数据库路径
     */
    public static function getDatabasePath()
    {
        self::init();
        
        // 如果已经检测过，直接返回
        if (isset(self::$detectedPaths['database'])) {
            return self::$detectedPaths['database'];
        }
        
        // 检查环境变量
        $envPath = getenv('SVNADMIN_DB_PATH');
        if ($envPath && file_exists($envPath)) {
            self::$detectedPaths['database'] = $envPath;
            return $envPath;
        }
        
        // 检查配置中的搜索路径
        foreach (self::$config['database']['search_paths'] as $path) {
            if (file_exists($path)) {
                self::$detectedPaths['database'] = $path;
                return $path;
            }
        }
        
        // 如果都不存在，返回默认路径
        $defaultPath = self::$config['database']['search_paths'][0];
        self::$detectedPaths['database'] = $defaultPath;
        return $defaultPath;
    }
    
    /**
     * 获取日志文件路径
     */
    public static function getLogPath($logName = null)
    {
        self::init();
        
        $logRoot = self::$config['logs']['root'];
        
        if ($logName === null) {
            return $logRoot;
        }
        
        $logFile = self::$config['logs']['files'][$logName] ?? $logName;
        return $logRoot . '/' . $logFile;
    }
    
    /**
     * 获取SVN相关路径
     */
    public static function getSvnPath($type = 'data_root')
    {
        self::init();
        return self::$config['svn'][$type] ?? null;
    }
    
    /**
     * 确保目录存在
     */
    public static function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }
    
    /**
     * 获取相对路径
     */
    public static function getRelativePath($from, $to)
    {
        $from = rtrim($from, '/');
        $to = rtrim($to, '/');
        
        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);
        
        // 找到共同的前缀
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }
        
        // 构建相对路径
        $relativeParts = [];
        
        // 添加 "../" 部分
        for ($i = $commonLength; $i < count($fromParts); $i++) {
            $relativeParts[] = '..';
        }
        
        // 添加目标路径部分
        for ($i = $commonLength; $i < count($toParts); $i++) {
            $relativeParts[] = $toParts[$i];
        }
        
        return implode('/', $relativeParts);
    }
    
    /**
     * 检查路径是否可写
     */
    public static function isWritable($path)
    {
        if (is_dir($path)) {
            return is_writable($path);
        }
        
        $dir = dirname($path);
        return is_dir($dir) && is_writable($dir);
    }
    
    /**
     * 获取所有配置的路径信息
     */
    public static function getAllPaths()
    {
        self::init();
        
        return [
            'project_root' => self::getProjectRoot(),
            'data_root' => self::getDataRoot(),
            'database' => self::getDatabasePath(),
            'logs' => self::getLogPath(),
            'svn_data' => self::getSvnPath('data_root'),
            'svn_repos' => self::getSvnPath('repositories'),
            'svn_authz' => self::getSvnPath('authz_file'),
            'svn_passwd' => self::getSvnPath('passwd_file'),
        ];
    }
    
    /**
     * 验证所有关键路径
     */
    public static function validatePaths()
    {
        $paths = self::getAllPaths();
        $issues = [];
        
        foreach ($paths as $name => $path) {
            if (!file_exists($path) && !is_dir(dirname($path))) {
                $issues[] = "路径不存在且无法创建: $name -> $path";
            } elseif (file_exists($path) && !self::isWritable($path)) {
                $issues[] = "路径不可写: $name -> $path";
            }
        }
        
        return $issues;
    }
}
?>
