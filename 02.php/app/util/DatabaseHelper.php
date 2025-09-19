<?php

namespace app\util;

/*
 * @Author: witersen
 * 
 * @Description: 数据库连接帮助类 - 统一数据库连接逻辑
 */

use Config;
use Medoo\Medoo;

class DatabaseHelper
{
    /**
     * 获取数据库连接
     * 复用Base类的标准数据库连接逻辑
     *
     * @return Medoo|null
     */
    public static function getConnection()
    {
        global $database;
        
        if ($database) {
            return $database;
        }
        
        try {
            // 确保配置路径已设置
            if (empty(Config::$_configPath)) {
                Config::load(BASE_PATH . '/config/');
            }
            
            $configDatabase = Config::get('database');
            $configSvn = Config::get('svn');
            
            if (array_key_exists('database_file', $configDatabase)) {
                $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
            }
            
            $database = new Medoo($configDatabase);
            return $database;
            
        } catch (\Exception $e) {
            // 记录错误但不抛出异常，保持与Base类一致的行为
            error_log("DatabaseHelper: Failed to create database connection - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 检查数据库连接是否可用
     *
     * @return bool
     */
    public static function isConnectionAvailable()
    {
        $db = self::getConnection();
        return $db !== null;
    }
    
    /**
     * 获取数据库配置信息（用于调试）
     *
     * @return array
     */
    public static function getDatabaseConfig()
    {
        try {
            $configDatabase = Config::get('database');
            $configSvn = Config::get('svn');
            
            if (array_key_exists('database_file', $configDatabase)) {
                $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
            }
            
            return $configDatabase;
            
        } catch (\Exception $e) {
            return [];
        }
    }
}
