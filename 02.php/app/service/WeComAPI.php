<?php

namespace app\service;

/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信 API 集成服务类
 */

require_once BASE_PATH . '/app/service/base/Base.php';
require_once BASE_PATH . '/app/service/WeComConfig.php';
use app\service\Logs as ServiceLogs;
use app\service\WeComConfig;

class WeComAPI extends Base
{
    /**
     * 其它服务层对象
     *
     * @var object
     */
    private $ServiceLogs;

    /**
     * 企业微信配置
     *
     * @var array
     */
    private $wecomConfig;

    /**
     * API 基础URL
     *
     * @var string
     */
    private $apiBaseUrl;

    /**
     * 访问令牌缓存
     *
     * @var string
     */
    private $accessToken;

    /**
     * 令牌过期时间
     *
     * @var int
     */
    private $tokenExpiresAt;

    function __construct($parm = [])
    {
        try {
            parent::__construct($parm);
        } catch (\Exception $e) {
            // 如果Base类初始化失败，尝试手动初始化必要的属性
            $this->initializeManually($parm);
        }

        try {
            $this->ServiceLogs = new ServiceLogs($parm);
        } catch (\Exception $e) {
            // ServiceLogs初始化失败，使用空对象
            $this->ServiceLogs = null;
        }
        
        // 定义BASE_PATH常量（如果未定义）
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $_ENV['BASE_PATH'] ?? dirname(dirname(__DIR__)));
        }
        
        // 加载企业微信配置（优先从数据库读取）
        $this->wecomConfig = WeComConfig::getConfig();
        $this->apiBaseUrl = $this->wecomConfig['api_base_url'];
        
        // 检查企业微信功能是否启用
        if (!$this->wecomConfig['enabled']) {
            throw new \Exception('企业微信集成功能未启用');
        }

        // 验证必要的配置参数
        $this->validateConfig();
    }

    /**
     * 手动初始化必要的属性（当Base类初始化失败时）
     *
     * @param array $parm
     * @return void
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
                $this->database = new \Medoo\Medoo([
                    'database_type' => 'sqlite',
                    'database_file' => '/home/svnadmin/svnadmin.db'
                ]);
            } catch (\Exception $e) {
                // 数据库连接失败，但不影响企业微信API功能
                $this->database = null;
            }
        }
    }

    /**
     * 验证配置参数
     *
     * @return void
     * @throws \Exception
     */
    private function validateConfig()
    {
        $requiredFields = ['corp_id', 'corp_secret', 'agent_id'];
        
        foreach ($requiredFields as $field) {
            if (empty($this->wecomConfig[$field])) {
                throw new \Exception("企业微信配置缺少必要参数: {$field}");
            }
        }
    }

    /**
     * 获取访问令牌
     *
     * @return string
     * @throws \Exception
     */
    public function getAccessToken()
    {
        // 检查缓存的令牌是否有效
        if ($this->isTokenValid()) {
            return $this->accessToken;
        }

        // 从数据库获取令牌
        $tokenData = $this->getTokenFromDatabase();
        if ($tokenData && $this->isTokenValid($tokenData['access_token'], $tokenData['token_expires_at'])) {
            $this->accessToken = $tokenData['access_token'];
            $this->tokenExpiresAt = $tokenData['token_expires_at'];
            return $this->accessToken;
        }

        // 重新获取令牌
        return $this->refreshAccessToken();
    }

    /**
     * 检查令牌是否有效
     *
     * @param string|null $token
     * @param int|null $expiresAt
     * @return bool
     */
    private function isTokenValid($token = null, $expiresAt = null)
    {
        $token = $token ?: $this->accessToken;
        $expiresAt = $expiresAt ?: $this->tokenExpiresAt;
        
        if (empty($token) || empty($expiresAt)) {
            return false;
        }

        // 提前5分钟刷新令牌
        return time() < ($expiresAt - 300);
    }

    /**
     * 从数据库获取令牌
     *
     * @return array|null
     */
    private function getTokenFromDatabase()
    {
        try {
            $result = $this->database->select('wecom_config', 
                ['access_token', 'token_expires_at'], 
                ['id' => 1]
            );
            
            return $result ? $result[0] : null;
        } catch (\Exception $e) {
            $this->logError('获取数据库令牌失败', $e->getMessage());
            return null;
        }
    }

    /**
     * 刷新访问令牌
     *
     * @return string
     * @throws \Exception
     */
    private function refreshAccessToken()
    {
        $url = rtrim($this->apiBaseUrl, '/') . '/gettoken';
        $params = [
            'corpid' => $this->wecomConfig['corp_id'],
            'corpsecret' => $this->wecomConfig['corp_secret']
        ];

        $response = $this->makeRequest('GET', $url, $params, []);
        
        if ($response['errcode'] !== 0) {
            // 仅透传企业微信JSON
            throw new \Exception(json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiresAt = time() + $response['expires_in'];

        // 保存令牌到数据库
        $this->saveTokenToDatabase();

        $this->logInfo('访问令牌刷新成功', [
            'expires_in' => $response['expires_in'],
            'expires_at' => date('Y-m-d H:i:s', $this->tokenExpiresAt)
        ]);

        return $this->accessToken;
    }

    /**
     * 保存令牌到数据库
     *
     * @return void
     */
    private function saveTokenToDatabase()
    {
        try {
            $this->database->update('wecom_config', [
                'access_token' => $this->accessToken,
                'token_expires_at' => $this->tokenExpiresAt,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => 1]);
        } catch (\Exception $e) {
            $this->logError('保存令牌到数据库失败', $e->getMessage());
        }
    }

    /**
     * 发起 HTTP 请求
     *
     * @param string $method
     * @param string $url
     * @param array $params
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function makeRequest($method = 'GET', $url = '', $params = [], $data = [])
    {
        $startTime = microtime(true);
        
        // 构建完整URL
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // 初始化 cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => isset($this->wecomConfig['request_timeout']) ? $this->wecomConfig['request_timeout'] : 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'SVNAdmin/1.0'
        ]);
        
        // 根据请求方法设置HTTP头部
        $headers = [];
        if (strtoupper($method) === 'POST' && !empty($data)) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // 企业微信API必须禁用代理访问（容器可能默认走 26001 端口代理）
        // 强制禁用所有代理设置，确保直连企业微信
        curl_setopt($ch, CURLOPT_PROXY, '');          // 显式禁用代理
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_PROXYPORT, 0);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);
        if (defined('CURLOPT_NOPROXY')) {
            curl_setopt($ch, CURLOPT_NOPROXY, '*');
        }
        // 彻底清除环境变量代理
        foreach (['http_proxy','https_proxy','HTTP_PROXY','HTTPS_PROXY','no_proxy','NO_PROXY'] as $envKey) {
            if (getenv($envKey) !== false) {
                putenv($envKey . '=');
                unset($_ENV[$envKey]);
                unset($_SERVER[$envKey]);
            }
        }
        $this->logInfo('企业微信API访问已强制直连（已禁用代理）', ['url' => $url]);

        // 设置请求方法和数据
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // 获取详细的连接信息
        $connectionInfo = [
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
            'namelookup_time' => curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect_time' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'pretransfer_time' => curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME),
            'starttransfer_time' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
            'primary_ip' => curl_getinfo($ch, CURLINFO_PRIMARY_IP),
            'primary_port' => curl_getinfo($ch, CURLINFO_PRIMARY_PORT),
            'local_ip' => curl_getinfo($ch, CURLINFO_LOCAL_IP),
            'local_port' => curl_getinfo($ch, CURLINFO_LOCAL_PORT),
            'size_download' => curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
            'speed_download' => curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD),
            'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'effective_url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)
        ];
        
        curl_close($ch);

        $responseTime = round((microtime(true) - $startTime) * 1000);

        // 记录 API 调用日志
        $this->logApiCall($method, $url, $data, $response, $httpCode, $responseTime, $error);

        // 检查 cURL 错误
        if (!empty($error)) {
            throw new \Exception("HTTP 请求失败: {$error}");
        }

        // 检查 HTTP 状态码
        if ($httpCode !== 200) {
            // 尝试解析响应中的错误信息
            $responseData = null;
            $wecomError = '';
            
            if (!empty($response)) {
                $responseData = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($responseData['errcode'])) {
                    $wecomError = "企业微信错误码: {$responseData['errcode']}";
                    if (isset($responseData['errmsg'])) {
                        $wecomError .= ", 错误信息: {$responseData['errmsg']}";
                    }
                }
            }
            
            $errorDetails = [
                'url' => $url,
                'method' => $method,
                'http_code' => $httpCode,
                'response_raw' => $response, // 完整响应内容
                'response_parsed' => $responseData,
                'wecom_error' => $wecomError,
                'connection_info' => $connectionInfo, // 详细连接信息
                'request_time' => $responseTime . 'ms'
            ];
            
            $errorMsg = "HTTP 请求失败，状态码: {$httpCode}";
            
            // 根据状态码提供更具体的错误信息
            switch ($httpCode) {
                case 400:
                    $errorMsg .= " (请求参数错误)";
                    break;
                case 401:
                    $errorMsg .= " (认证失败，请检查企业微信配置)";
                    break;
                case 403:
                    $errorMsg .= " (访问被拒绝，请检查应用权限和IP白名单)";
                    break;
                case 404:
                    $errorMsg .= " (接口不存在)";
                    break;
                case 429:
                    $errorMsg .= " (请求频率超限，请稍后重试)";
                    break;
                case 500:
                    $errorMsg .= " (企业微信服务器内部错误)";
                    break;
                case 502:
                case 503:
                case 504:
                    $errorMsg .= " (企业微信服务器暂时不可用)";
                    break;
                default:
                    $errorMsg .= " (未知错误)";
            }
            
            // 如果有企业微信的具体错误信息，优先显示
            if (!empty($wecomError)) {
                $errorMsg .= " - " . $wecomError;
            }
            
            // 记录详细错误信息到日志
            $this->logError('HTTP请求失败详情', $errorDetails);
            
            // 构建包含关键信息的错误消息
            $fullErrorMsg = $errorMsg;
            
            // 添加连接信息
            if (!empty($connectionInfo['primary_ip'])) {
                $fullErrorMsg .= " 连接IP: {$connectionInfo['primary_ip']}:{$connectionInfo['primary_port']}";
            }
            
            if (!empty($connectionInfo['local_ip'])) {
                $fullErrorMsg .= " 本地IP: {$connectionInfo['local_ip']}:{$connectionInfo['local_port']}";
            }
            
            $fullErrorMsg .= " 耗时: {$responseTime}ms";
            
            // 如果有企业微信的具体错误信息，优先显示
            if (!empty($wecomError)) {
                $fullErrorMsg .= " - " . $wecomError;
            }
            
            // 仅透传企业微信API原始响应（若为JSON则原样返回JSON字符串）
            $errorOutput = '';
            if (!empty($response)) {
                $jsonResponse = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonResponse)) {
                    // 有效JSON：直接输出JSON字符串（不添加任何包装/前缀）
                    $errorOutput = json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);
                } else {
                    // 非JSON：输出去标签后的原始文本（限制长度以防过长）
                    $errorOutput = trim(strip_tags(strlen($response) > 2000 ? substr($response, 0, 2000) : $response));
                }
            }

            throw new \Exception($errorOutput !== '' ? $errorOutput : (string)$httpCode);
        }

        // 解析响应
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("响应数据解析失败: " . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 发起带认证的 API 请求
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param int $retryCount
     * @return array
     * @throws \Exception
     */
    public function makeAuthenticatedRequest($method, $endpoint, $data = [], $retryCount = 0)
    {
        $accessToken = $this->getAccessToken();
        $url = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');
        
        // 添加访问令牌参数
        $params = ['access_token' => $accessToken];
        
        // 对于GET请求，将data参数合并到URL参数中
        if (strtoupper($method) === 'GET' && !empty($data)) {
            $params = array_merge($params, $data);
            $data = []; // GET请求不需要body数据
        }

        try {
            $response = $this->makeRequest($method, $url, $params, $data);
            
            // 检查响应错误码
            if (isset($response['errcode']) && $response['errcode'] !== 0) {
                // 如果是令牌相关错误，尝试刷新令牌重试
                if (in_array($response['errcode'], [40014, 42001, 42007, 42009]) && $retryCount < $this->wecomConfig['max_retry_times']) {
                    $this->logInfo('令牌失效，尝试刷新令牌重试', [
                        'errcode' => $response['errcode'],
                        'errmsg' => $response['errmsg'],
                        'retry_count' => $retryCount + 1
                    ]);
                    
                    // 清除缓存的令牌
                    $this->accessToken = null;
                    $this->tokenExpiresAt = null;
                    
                    // 递归重试
                    return $this->makeAuthenticatedRequest($method, $endpoint, $data, $retryCount + 1);
                }
                
                throw new \Exception("API 请求失败: {$response['errmsg']} (错误码: {$response['errcode']})");
            }

            return $response;
            
        } catch (\Exception $e) {
            // 如果是网络错误且未达到重试上限，进行重试
            if ($retryCount < $this->wecomConfig['max_retry_times']) {
                $this->logError('API 请求失败，准备重试', [
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount + 1,
                    'endpoint' => $endpoint
                ]);
                
                // 等待一段时间后重试
                sleep(pow(2, $retryCount)); // 指数退避
                return $this->makeAuthenticatedRequest($method, $endpoint, $data, $retryCount + 1);
            }
            
            throw $e;
        }
    }

    /**
     * 记录 API 调用日志
     *
     * @param string $method
     * @param string $url
     * @param array $requestData
     * @param string $response
     * @param int $httpCode
     * @param int $responseTime
     * @param string $error
     * @return void
     */
    private function logApiCall($method, $url, $requestData, $response, $httpCode, $responseTime, $error = '')
    {
        // 如果启用了 API 日志记录
        if (isset($this->wecomConfig['logging']['log_api_requests']) && $this->wecomConfig['logging']['log_api_requests']) {
            try {
                $this->database->insert('wecom_api_logs', [
                    'api_method' => $method,
                    'api_url' => $url,
                    'request_data' => json_encode($requestData, JSON_UNESCAPED_UNICODE),
                    'response_code' => $httpCode,
                    'response_data' => (isset($this->wecomConfig['debug']['save_api_responses']) && $this->wecomConfig['debug']['save_api_responses']) ? $response : '',
                    'response_time' => $responseTime,
                    'error_message' => $error,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                $this->logError('记录 API 日志失败', $e->getMessage());
            }
        }
    }

    /**
     * 记录信息日志
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logInfo($message, $context = [])
    {
        if (isset($this->wecomConfig['logging']['enabled']) && $this->wecomConfig['logging']['enabled']) {
            $logMessage = '[WeComAPI] ' . $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom');
        }
    }

    /**
     * 记录错误日志
     *
     * @param string $message
     * @param string $error
     * @return void
     */
    private function logError($message, $error = '')
    {
        if (isset($this->wecomConfig['logging']['enabled']) && $this->wecomConfig['logging']['enabled']) {
            $logMessage = '[WeComAPI ERROR] ' . $message;
            if (!empty($error)) {
                $errorStr = is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : (string)$error;
                $logMessage .= ': ' . $errorStr;
            }
            $this->ServiceLogs->WriteLog($logMessage, 'wecom');
        }
    }

    /**
     * 测试 API 连接
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $accessToken = $this->getAccessToken();
            
            return [
                'success' => true,
                'message' => 'API 连接测试成功',
                'access_token' => substr($accessToken, 0, 10) . '...',
                'expires_at' => date('Y-m-d H:i:s', $this->tokenExpiresAt)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API 连接测试失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取企业微信配置
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->wecomConfig;
    }

    /**
     * 检查企业微信功能是否启用
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->wecomConfig['enabled'];
    }

    // ==================== 通讯录 API 接口 ====================

    /**
     * 获取部门列表
     *
     * @param int $departmentId 部门ID，获取指定部门及其下的子部门（以及子部门的子部门等等，递归）
     * @return array
     * @throws \Exception
     */
    public function getDepartments($departmentId = null)
    {
        $endpoint = 'department/list';
        $data = [];
        
        if ($departmentId !== null) {
            $data['id'] = $departmentId;
        }

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        if (!isset($response['department'])) {
            throw new \Exception('获取部门列表失败：响应数据格式错误');
        }

        $this->logInfo('获取部门列表成功', [
            'department_id' => $departmentId,
            'count' => count($response['department'])
        ]);

        return $response['department'];
    }

    /**
     * 获取部门详情
     *
     * @param int $departmentId 部门ID
     * @return array
     * @throws \Exception
     */
    public function getDepartmentDetail($departmentId)
    {
        $endpoint = 'department/get';
        $data = ['id' => $departmentId];

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        if (!isset($response['department'])) {
            throw new \Exception('获取部门详情失败：响应数据格式错误');
        }

        return $response['department'];
    }

    /**
     * 获取部门成员列表（简单信息）
     *
     * @param int $departmentId 部门ID
     * @param bool $fetchChild 是否递归获取子部门成员
     * @return array
     * @throws \Exception
     */
    public function getDepartmentUsers($departmentId, $fetchChild = false)
    {
        $endpoint = 'user/simplelist';
        $data = [
            'department_id' => $departmentId,
            'fetch_child' => $fetchChild ? 1 : 0
        ];

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        if (!isset($response['userlist'])) {
            throw new \Exception('获取部门成员列表失败：响应数据格式错误');
        }

        $this->logInfo('获取部门成员列表成功', [
            'department_id' => $departmentId,
            'fetch_child' => $fetchChild,
            'count' => count($response['userlist'])
        ]);

        return $response['userlist'];
    }

    /**
     * 获取部门成员详情列表
     *
     * @param int $departmentId 部门ID
     * @param bool $fetchChild 是否递归获取子部门成员
     * @return array
     * @throws \Exception
     */
    public function getDepartmentUsersDetail($departmentId, $fetchChild = false)
    {
        $endpoint = 'user/list';
        $data = [
            'department_id' => $departmentId,
            'fetch_child' => $fetchChild ? 1 : 0
        ];

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        if (!isset($response['userlist'])) {
            throw new \Exception('获取部门成员详情失败：响应数据格式错误');
        }

        $this->logInfo('获取部门成员详情成功', [
            'department_id' => $departmentId,
            'fetch_child' => $fetchChild,
            'count' => count($response['userlist'])
        ]);

        return $response['userlist'];
    }

    /**
     * 获取用户详情
     *
     * @param string $userId 用户ID
     * @return array
     * @throws \Exception
     */
    public function getUserDetail($userId)
    {
        $endpoint = 'user/get';
        $data = ['userid' => $userId];

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        // 移除 errcode 和 errmsg 字段，只返回用户数据
        unset($response['errcode'], $response['errmsg']);

        $this->logInfo('获取用户详情成功', ['user_id' => $userId]);

        return $response;
    }

    /**
     * 批量获取用户详情
     *
     * @param array $userIds 用户ID列表
     * @param int $batchSize 批处理大小
     * @return array
     */
    public function getBatchUsersDetail($userIds, $batchSize = 50)
    {
        $users = [];
        $batches = array_chunk($userIds, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $userId) {
                try {
                    $user = $this->getUserDetail($userId);
                    $users[] = $user;
                } catch (\Exception $e) {
                    $this->logError('获取用户详情失败', "用户ID: {$userId}, 错误: " . $e->getMessage());
                    continue;
                }
                
                // 避免请求过于频繁
                usleep(100000); // 0.1秒
            }
        }

        $this->logInfo('批量获取用户详情完成', [
            'total_requested' => count($userIds),
            'successful' => count($users)
        ]);

        return $users;
    }

    /**
     * 获取全量组织架构数据
     *
     * @param int $rootDepartmentId 根部门ID
     * @return array
     */
    public function getFullOrganization($rootDepartmentId = null)
    {
        $rootDepartmentId = $rootDepartmentId ?: $this->wecomConfig['department_mapping']['root_department_id'];
        
        $this->logInfo('开始获取全量组织架构', ['root_department_id' => $rootDepartmentId]);
        
        $startTime = microtime(true);
        
        try {
            // 获取所有部门
            $departments = $this->getDepartments($rootDepartmentId);
            
            // 获取每个部门的用户
            $organizationData = [
                'departments' => $departments,
                'users' => [],
                'department_users' => []
            ];
            
            foreach ($departments as $department) {
                try {
                    $users = $this->getDepartmentUsersDetail($department['id'], false);
                    $organizationData['department_users'][$department['id']] = $users;
                    
                    // 合并到总用户列表（去重）
                    foreach ($users as $user) {
                        $organizationData['users'][$user['userid']] = $user;
                    }
                    
                    // 避免请求过于频繁
                    usleep(200000); // 0.2秒
                    
                } catch (\Exception $e) {
                    $this->logError('获取部门用户失败', "部门ID: {$department['id']}, 错误: " . $e->getMessage());
                    continue;
                }
            }
            
            // 转换用户数组为索引数组
            $organizationData['users'] = array_values($organizationData['users']);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $this->logInfo('全量组织架构获取完成', [
                'departments_count' => count($organizationData['departments']),
                'users_count' => count($organizationData['users']),
                'duration_ms' => $duration
            ]);
            
            return $organizationData;
            
        } catch (\Exception $e) {
            $this->logError('获取全量组织架构失败', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取增量组织架构数据
     *
     * @param string $lastSyncTime 上次同步时间 (Y-m-d H:i:s)
     * @return array
     */
    public function getIncrementalOrganization($lastSyncTime)
    {
        // 企业微信API不直接支持增量获取，这里实现一个简化版本
        // 实际使用中可以结合本地缓存来优化
        
        $this->logInfo('开始获取增量组织架构', ['last_sync_time' => $lastSyncTime]);
        
        // 目前先返回全量数据，后续可以优化为真正的增量
        $fullData = $this->getFullOrganization();
        
        // TODO: 实现真正的增量逻辑
        // 1. 比较部门列表变化
        // 2. 比较用户信息变化
        // 3. 只返回变更的数据
        
        return [
            'type' => 'full', // 标记为全量数据
            'data' => $fullData,
            'sync_time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 验证部门ID是否存在
     *
     * @param int $departmentId
     * @return bool
     */
    public function isDepartmentExists($departmentId)
    {
        try {
            $this->getDepartmentDetail($departmentId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 验证用户ID是否存在
     *
     * @param string $userId
     * @return bool
     */
    public function isUserExists($userId)
    {
        try {
            $this->getUserDetail($userId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==================== 消息推送 API 接口 ====================

    /**
     * 发送应用消息
     *
     * @param array $message 消息内容
     * @return array
     * @throws \Exception
     */
    public function sendApplicationMessage($message)
    {
        $endpoint = 'message/send';
        
        // 设置默认的应用ID
        if (!isset($message['agentid'])) {
            $message['agentid'] = $this->wecomConfig['agent_id'];
        }

        $response = $this->makeAuthenticatedRequest('POST', $endpoint, $message);
        
        $this->logInfo('发送应用消息', [
            'touser' => $message['touser'] ?? '',
            'toparty' => $message['toparty'] ?? '',
            'totag' => $message['totag'] ?? '',
            'msgtype' => $message['msgtype'] ?? '',
            'invaliduser' => $response['invaliduser'] ?? '',
            'invalidparty' => $response['invalidparty'] ?? ''
        ]);

        return $response;
    }

    /**
     * 发送群聊消息
     *
     * @param string $chatId 群聊ID
     * @param array $message 消息内容
     * @return array
     * @throws \Exception
     */
    public function sendGroupMessage($chatId, $message)
    {
        $endpoint = 'appchat/send';
        
        $data = array_merge($message, [
            'chatid' => $chatId
        ]);

        $response = $this->makeAuthenticatedRequest('POST', $endpoint, $data);
        
        $this->logInfo('发送群聊消息', [
            'chatid' => $chatId,
            'msgtype' => $message['msgtype'] ?? ''
        ]);

        return $response;
    }

    /**
     * 发送文本消息
     *
     * @param string $content 消息内容
     * @param string $toUser 接收用户（用户ID列表，用|分隔）
     * @param string $toParty 接收部门（部门ID列表，用|分隔）
     * @param string $toTag 接收标签（标签ID列表，用|分隔）
     * @return array
     * @throws \Exception
     */
    public function sendTextMessage($content, $toUser = '@all', $toParty = '', $toTag = '')
    {
        $message = [
            'touser' => $toUser,
            'toparty' => $toParty,
            'totag' => $toTag,
            'msgtype' => 'text',
            'text' => [
                'content' => $content
            ],
            'safe' => 0
        ];

        return $this->sendApplicationMessage($message);
    }

    /**
     * 发送Markdown消息
     *
     * @param string $content Markdown内容
     * @param string $toUser 接收用户
     * @param string $toParty 接收部门
     * @param string $toTag 接收标签
     * @return array
     * @throws \Exception
     */
    public function sendMarkdownMessage($content, $toUser = '@all', $toParty = '', $toTag = '')
    {
        $message = [
            'touser' => $toUser,
            'toparty' => $toParty,
            'totag' => $toTag,
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $content
            ],
            'safe' => 0
        ];

        return $this->sendApplicationMessage($message);
    }

    /**
     * 发送群聊文本消息
     *
     * @param string $chatId 群聊ID
     * @param string $content 消息内容
     * @return array
     * @throws \Exception
     */
    public function sendGroupTextMessage($chatId, $content)
    {
        $message = [
            'msgtype' => 'text',
            'text' => [
                'content' => $content
            ]
        ];

        return $this->sendGroupMessage($chatId, $message);
    }

    /**
     * 发送群聊Markdown消息
     *
     * @param string $chatId 群聊ID
     * @param string $content Markdown内容
     * @return array
     * @throws \Exception
     */
    public function sendGroupMarkdownMessage($chatId, $content)
    {
        // 检查是否是 webhook URL
        if (strpos($chatId, 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send') === 0) {
            return $this->sendWebhookMessage($chatId, $content);
        }
        
        $message = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $content
            ]
        ];

        return $this->sendGroupMessage($chatId, $message);
    }

    /**
     * 发送 Webhook 消息（群机器人）
     *
     * @param string $webhookUrl Webhook URL
     * @param string $content 消息内容
     * @param string $msgType 消息类型 (text|markdown)
     * @return array
     * @throws \Exception
     */
    public function sendWebhookMessage($webhookUrl, $content, $msgType = 'markdown')
    {
        $startTime = microtime(true);
        
        // 构建消息数据
        $data = [
            'msgtype' => $msgType
        ];
        
        if ($msgType === 'markdown') {
            $data['markdown'] = [
                'content' => $content
            ];
        } else {
            $data['text'] = [
                'content' => $content
            ];
        }
        
        // 初始化 cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: SVNAdmin/1.0'
            ]
        ]);
        
        // 禁用代理
        curl_setopt($ch, CURLOPT_PROXY, '');
        if (defined('CURLOPT_NOPROXY')) {
            curl_setopt($ch, CURLOPT_NOPROXY, '*');
        }
        
        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        // 记录日志
        $this->logInfo('发送 Webhook 消息', [
            'webhook_url' => substr($webhookUrl, 0, 80) . '...',
            'msgtype' => $msgType,
            'http_code' => $httpCode,
            'response_time' => $responseTime . 'ms'
        ]);
        
        // 检查错误
        if (!empty($error)) {
            throw new \Exception("Webhook 请求失败: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("Webhook 请求失败，HTTP状态码: {$httpCode}，响应: {$response}");
        }
        
        // 解析响应
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Webhook 响应解析失败: " . json_last_error_msg());
        }
        
        // 检查企业微信返回的错误码
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            $errorMsg = $result['errmsg'] ?? '未知错误';
            throw new \Exception("Webhook 发送失败: {$errorMsg} (错误码: {$result['errcode']})");
        }
        
        return $result;
    }

    /**
     * 使用模板发送消息
     *
     * @param string $templateName 模板名称
     * @param array $variables 模板变量
     * @param string $chatId 群聊ID（可选）
     * @param string $toUser 接收用户（可选）
     * @return array
     * @throws \Exception
     */
    public function sendTemplateMessage($templateName, $variables = [], $chatId = '', $toUser = '@all')
    {
        // 获取消息模板
        $template = $this->getMessageTemplate($templateName);
        if (!$template) {
            throw new \Exception("消息模板不存在: {$templateName}");
        }

        // 替换模板变量
        $content = $this->replaceTemplateVariables($template['content'], $variables);
        $title = $this->replaceTemplateVariables($template['title'], $variables);

        // 构建完整消息内容
        $fullContent = "**{$title}**\n\n{$content}";

        // 根据配置的消息格式发送
        $messageFormat = $this->wecomConfig['notification']['message_format'];
        
        if (!empty($chatId)) {
            // 发送群聊消息
            if ($messageFormat === 'markdown') {
                return $this->sendGroupMarkdownMessage($chatId, $fullContent);
            } else {
                return $this->sendGroupTextMessage($chatId, strip_tags($fullContent));
            }
        } else {
            // 发送应用消息
            if ($messageFormat === 'markdown') {
                return $this->sendMarkdownMessage($fullContent, $toUser);
            } else {
                return $this->sendTextMessage(strip_tags($fullContent), $toUser);
            }
        }
    }

    /**
     * 获取消息模板
     *
     * @param string $templateName 模板名称
     * @return array|null
     */
    private function getMessageTemplate($templateName)
    {
        $templates = $this->wecomConfig['message_templates'];
        return $templates[$templateName] ?? null;
    }

    /**
     * 替换模板变量
     *
     * @param string $template 模板内容
     * @param array $variables 变量数组
     * @return string
     */
    private function replaceTemplateVariables($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        // 添加一些默认变量
        $defaultVariables = [
            'date' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'SVNAdmin'
        ];
        
        foreach ($defaultVariables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * 发送SVN操作通知
     *
     * @param string $repoName 仓库名称
     * @param string $author 操作者
     * @param string $action 操作类型
     * @param string $message 提交信息
     * @param array $files 文件列表
     * @param string $chatId 群聊ID
     * @return array
     * @throws \Exception
     */
    public function sendSvnNotification($repoName, $author, $action, $message = '', $files = [], $chatId = '')
    {
        $templateName = $action; // commit, delete, update 等
        
        $variables = [
            'repo_name' => $repoName,
            'author' => $author,
            'message' => $message,
            'files' => is_array($files) ? implode("\n", $files) : $files,
            'action' => $action
        ];

        return $this->sendTemplateMessage($templateName, $variables, $chatId);
    }

    /**
     * 发送同步状态通知
     *
     * @param string $status 同步状态 (success/error)
     * @param array $data 同步数据
     * @param string $chatId 群聊ID
     * @return array
     * @throws \Exception
     */
    public function sendSyncNotification($status, $data = [], $chatId = '')
    {
        $templateName = 'sync_' . $status;
        
        $variables = array_merge([
            'status' => $status,
            'date' => date('Y-m-d H:i:s')
        ], $data);

        return $this->sendTemplateMessage($templateName, $variables, $chatId);
    }

    /**
     * 批量发送消息
     *
     * @param array $messages 消息列表
     * @param int $batchSize 批处理大小
     * @param int $delay 批次间延迟（毫秒）
     * @return array
     */
    public function sendBatchMessages($messages, $batchSize = 10, $delay = 1000)
    {
        $results = [];
        $batches = array_chunk($messages, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResults = [];
            
            foreach ($batch as $messageIndex => $message) {
                try {
                    if (isset($message['chat_id'])) {
                        // 群聊消息
                        $result = $this->sendGroupMessage($message['chat_id'], $message['content']);
                    } else {
                        // 应用消息
                        $result = $this->sendApplicationMessage($message['content']);
                    }
                    
                    $batchResults[] = [
                        'success' => true,
                        'result' => $result
                    ];
                    
                } catch (\Exception $e) {
                    $batchResults[] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    
                    $this->logError('批量发送消息失败', "批次: {$batchIndex}, 消息: {$messageIndex}, 错误: " . $e->getMessage());
                }
            }
            
            $results = array_merge($results, $batchResults);
            
            // 批次间延迟
            if ($batchIndex < count($batches) - 1) {
                usleep($delay * 1000);
            }
        }
        
        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        
        $this->logInfo('批量发送消息完成', [
            'total' => count($messages),
            'success' => $successCount,
            'failed' => count($messages) - $successCount
        ]);

        return $results;
    }

    /**
     * 检查消息发送频率限制
     *
     * @param string $chatId 群聊ID
     * @return bool
     */
    public function checkRateLimit($chatId)
    {
        if (!$this->wecomConfig['notification']['rate_limit']['enabled']) {
            return true;
        }

        $maxMessages = $this->wecomConfig['notification']['rate_limit']['max_messages_per_minute'];
        $cacheKey = "wecom_rate_limit_{$chatId}";
        
        // 这里应该使用缓存系统，暂时用简单的文件缓存
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $currentTime = time();
            
            // 清理过期的记录（超过1分钟）
            $data = array_filter($data, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60;
            });
            
            if (count($data) >= $maxMessages) {
                return false;
            }
            
            $data[] = $currentTime;
        } else {
            $data = [time()];
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }

    /**
     * 获取群聊信息
     *
     * @param string $chatId 群聊ID
     * @return array
     * @throws \Exception
     */
    public function getChatInfo($chatId)
    {
        $endpoint = 'appchat/get';
        $data = ['chatid' => $chatId];

        $response = $this->makeAuthenticatedRequest('GET', $endpoint, $data);
        
        if (!isset($response['chat_info'])) {
            throw new \Exception('获取群聊信息失败：响应数据格式错误');
        }

        return $response['chat_info'];
    }
}
