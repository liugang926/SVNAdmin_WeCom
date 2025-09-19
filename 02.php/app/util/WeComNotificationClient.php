<?php
/*
 * @Author: SVNAdmin WeChat Integration
 * 
 * @LastEditors: SVNAdmin WeChat Integration
 * 
 * @Description: 企业微信通知守护进程客户端
 */

class WeComNotificationClient
{
    private $socketPath;
    private $timeout;
    private $maxDataLength;

    public function __construct()
    {
        $this->socketPath = BASE_PATH . '/server/wecom_notification.socket';
        $this->timeout = 10; // 10秒超时
        $this->maxDataLength = 500 * 1024; // 500KB
    }

    /**
     * 发送通知到队列
     *
     * @param string $type 通知类型
     * @param array $eventData 事件数据
     * @param string $webhookUrl Webhook URL
     * @param string $messageTemplate 消息模板
     * @param int $maxRetries 最大重试次数
     * @return array
     */
    public function queueNotification($type, $eventData, $webhookUrl = '', $messageTemplate = '', $maxRetries = 3)
    {
        $data = [
            'type' => $type,
            'event_data' => $eventData,
            'webhook_url' => $webhookUrl,
            'message_template' => $messageTemplate,
            'max_retries' => $maxRetries
        ];

        return $this->sendRequest('queue_notification', $data);
    }

    /**
     * 获取队列状态
     *
     * @return array
     */
    public function getQueueStatus()
    {
        return $this->sendRequest('get_queue_status', []);
    }

    /**
     * 发送SVN提交通知
     *
     * @param array $commitData SVN提交数据
     * @param string $webhookUrl Webhook URL
     * @param string $messageTemplate 消息模板
     * @return array
     */
    public function sendSvnCommitNotification($commitData, $webhookUrl, $messageTemplate = '')
    {
        return $this->queueNotification('svn_commit', $commitData, $webhookUrl, $messageTemplate);
    }

    /**
     * 发送SVN删除通知
     *
     * @param array $deleteData SVN删除数据
     * @param string $webhookUrl Webhook URL
     * @param string $messageTemplate 消息模板
     * @return array
     */
    public function sendSvnDeleteNotification($deleteData, $webhookUrl, $messageTemplate = '')
    {
        return $this->queueNotification('svn_delete', $deleteData, $webhookUrl, $messageTemplate);
    }

    /**
     * 发送同步完成通知
     *
     * @param array $syncData 同步数据
     * @param string $webhookUrl Webhook URL
     * @return array
     */
    public function sendSyncCompleteNotification($syncData, $webhookUrl)
    {
        return $this->queueNotification('sync_complete', $syncData, $webhookUrl);
    }

    /**
     * 发送请求到守护进程
     *
     * @param string $type 请求类型
     * @param array $content 请求内容
     * @return array
     */
    private function sendRequest($type, $content)
    {
        try {
            // 检查守护进程是否运行
            if (!$this->isDaemonRunning()) {
                return [
                    'success' => false,
                    'error' => '企业微信通知守护进程未运行'
                ];
            }

            // 创建套接字
            $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($socket === false) {
                return [
                    'success' => false,
                    'error' => '创建套接字失败: ' . socket_strerror(socket_last_error())
                ];
            }

            // 设置超时
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

            // 连接到守护进程
            $result = socket_connect($socket, $this->socketPath);
            if ($result === false) {
                socket_close($socket);
                return [
                    'success' => false,
                    'error' => '连接守护进程失败: ' . socket_strerror(socket_last_error($socket))
                ];
            }

            // 准备请求数据
            $request = [
                'type' => $type,
                'content' => $content
            ];
            $requestJson = json_encode($request);

            // 发送请求
            $result = socket_write($socket, $requestJson, strlen($requestJson));
            if ($result === false) {
                socket_close($socket);
                return [
                    'success' => false,
                    'error' => '发送请求失败: ' . socket_strerror(socket_last_error($socket))
                ];
            }

            // 接收响应
            $response = socket_read($socket, $this->maxDataLength);
            socket_close($socket);

            if ($response === false) {
                return [
                    'success' => false,
                    'error' => '接收响应失败: ' . socket_strerror(socket_last_error($socket))
                ];
            }

            // 解析响应
            $responseData = json_decode($response, true);
            if ($responseData === null) {
                return [
                    'success' => false,
                    'error' => '响应数据格式错误'
                ];
            }

            if ($responseData['code'] == 0) {
                return [
                    'success' => true,
                    'data' => $responseData['result']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $responseData['error']
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => '请求异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查守护进程是否运行
     *
     * @return bool
     */
    private function isDaemonRunning()
    {
        $pidFile = BASE_PATH . '/server/wecom_notification_daemon.pid';
        
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = trim(file_get_contents($pidFile));
        if (empty($pid)) {
            return false;
        }

        // 检查进程是否存在
        if (PHP_OS == 'Linux') {
            $result = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
            return !empty(trim($result));
        }

        return true; // 非Linux系统暂时返回true
    }

    /**
     * 启动守护进程
     *
     * @return array
     */
    public function startDaemon()
    {
        if ($this->isDaemonRunning()) {
            return [
                'success' => false,
                'error' => '守护进程已在运行'
            ];
        }

        $daemonScript = BASE_PATH . '/server/wecom_notification_daemon.php';
        if (!file_exists($daemonScript)) {
            return [
                'success' => false,
                'error' => '守护进程脚本不存在'
            ];
        }

        $command = "php {$daemonScript} start > /dev/null 2>&1 &";
        $result = shell_exec($command);

        // 等待一秒后检查是否启动成功
        sleep(1);
        
        if ($this->isDaemonRunning()) {
            return [
                'success' => true,
                'message' => '守护进程启动成功'
            ];
        } else {
            return [
                'success' => false,
                'error' => '守护进程启动失败'
            ];
        }
    }

    /**
     * 停止守护进程
     *
     * @return array
     */
    public function stopDaemon()
    {
        if (!$this->isDaemonRunning()) {
            return [
                'success' => false,
                'error' => '守护进程未运行'
            ];
        }

        $daemonScript = BASE_PATH . '/server/wecom_notification_daemon.php';
        if (!file_exists($daemonScript)) {
            return [
                'success' => false,
                'error' => '守护进程脚本不存在'
            ];
        }

        $command = "php {$daemonScript} stop";
        $result = shell_exec($command);

        // 等待一秒后检查是否停止成功
        sleep(1);
        
        if (!$this->isDaemonRunning()) {
            return [
                'success' => true,
                'message' => '守护进程停止成功'
            ];
        } else {
            return [
                'success' => false,
                'error' => '守护进程停止失败'
            ];
        }
    }

    /**
     * 获取守护进程状态
     *
     * @return array
     */
    public function getDaemonStatus()
    {
        $isRunning = $this->isDaemonRunning();
        $pidFile = BASE_PATH . '/server/wecom_notification_daemon.pid';
        
        $status = [
            'running' => $isRunning,
            'pid' => null,
            'uptime' => null
        ];

        if ($isRunning && file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            $status['pid'] = $pid;
            
            // 获取进程启动时间（Linux）
            if (PHP_OS == 'Linux' && !empty($pid)) {
                $startTime = shell_exec("ps -o lstart= -p {$pid} 2>/dev/null");
                if (!empty($startTime)) {
                    $startTimestamp = strtotime(trim($startTime));
                    $status['uptime'] = time() - $startTimestamp;
                }
            }
        }

        return $status;
    }
}
