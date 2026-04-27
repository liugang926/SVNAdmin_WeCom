<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: QQ:1801168257
 */

namespace app\service;

class Logs extends Base
{
    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     * 获取日志列表
     */
    public function GetLogList()
    {
        $pageSize = $this->payload['pageSize'];
        $currentPage = $this->payload['currentPage'];
        $searchKeyword = trim($this->payload['searchKeyword']);

        //分页
        $begin = $pageSize * ($currentPage - 1);

        $list = $this->database->select('logs', [
            'log_id',
            'log_type_name',
            'log_content',
            'log_add_user_name',
            'log_add_time',
        ], [
            'AND' => [
                'OR' => [
                    'log_type_name[~]' => $searchKeyword,
                    'log_content[~]' => $searchKeyword,
                    'log_add_user_name[~]' => $searchKeyword,
                    'log_add_time[~]' => $searchKeyword,
                ],
            ],
            'LIMIT' => [$begin, $pageSize],
            'ORDER' => [
                'log_add_time' => 'DESC'
            ]
        ]);

        $total = $this->database->count('logs', [
            'log_id'
        ], [
            'AND' => [
                'OR' => [
                    'log_type_name[~]' => $searchKeyword,
                    'log_content[~]' => $searchKeyword,
                    'log_add_user_name[~]' => $searchKeyword,
                    'log_add_time[~]' => $searchKeyword,
                ],
            ]
        ]);

        return message(200, 1, '成功', [
            'data' => $list,
            'total' => $total
        ]);
    }

    /**
     * 清空日志
     */
    public function DelLogs()
    {
        $this->database->delete('logs', [
            'log_id[>]' => 0
        ]);

        return message();
    }

    /**
     * 写入日志
     */
    public function InsertLog($log_type_name = '', $log_content = '', $log_add_user_name = '')
    {
        $this->database->insert('logs', [
            'log_type_name' => $log_type_name,
            'log_content' => $log_content,
            'log_add_user_name' => $log_add_user_name,
            'log_add_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 写入同步审计日志
     */
    public function InsertSyncAuditLog($objectType = '', $status = '', $reason = '', $items = [], $suggestion = '', $log_add_user_name = '')
    {
        $objectType = $objectType == '' ? '同步对象' : $objectType;
        $status = $status == '' ? '异常' : $status;
        $content = $objectType . $status;

        if ($reason != '') {
            $content .= '；原因：' . $reason;
        }

        if (!empty($items) && is_array($items)) {
            $names = [];
            $count = count($items);
            $index = 0;
            foreach ($items as $item) {
                if ($index >= 50) {
                    $names[] = '...共' . $count . '项';
                    break;
                }

                if (is_array($item)) {
                    $name = isset($item['objectName']) ? $item['objectName'] : (isset($item['name']) ? $item['name'] : '');
                    $itemReason = isset($item['reason']) ? $item['reason'] : '';
                    $names[] = $itemReason == '' ? $name : $name . '(' . $itemReason . ')';
                } else {
                    $names[] = $item;
                }
                $index++;
            }
            $content .= '；对象：' . implode('、', $names);
        }

        if ($suggestion != '') {
            $content .= '；处理建议：' . $suggestion;
        }

        $this->InsertLog(
            'LDAP同步审计',
            $content,
            $log_add_user_name == '' ? 'system' : $log_add_user_name
        );
    }

    /**
     * 写入文件日志（供新模块使用）
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function WriteLog($message = '', $type = 'app')
    {
        // 优先使用系统日志目录
        $logDir = isset($this->configSvn['log_base_path']) ? $this->configSvn['log_base_path'] : BASE_PATH . '/logs/';

        // 兜底，确保结尾是 /
        if (substr($logDir, -1) !== '/') {
            $logDir .= '/';
        }

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $file = $logDir . $type . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
