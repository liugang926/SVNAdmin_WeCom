<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: 企业微信钩子管理API路由配置
 */

return [
    // 生成钩子代码
    '/api/wecom/hook/generate' => [
        'controller' => 'WeComHookManager',
        'method' => 'generateHookCode',
        'auth_required' => true
    ],
    
    // 自动部署钩子
    '/api/wecom/hook/deploy' => [
        'controller' => 'WeComHookManager',
        'method' => 'deployHooksToRepositories',
        'auth_required' => true
    ],
    
    // 手动触发监控
    '/api/wecom/hook/monitor' => [
        'controller' => 'WeComHookManager',
        'method' => 'triggerLogMonitoring',
        'auth_required' => true
    ],
    
    // 获取监控统计
    '/api/wecom/hook/stats' => [
        'controller' => 'WeComHookManager',
        'method' => 'getMonitoringStats',
        'auth_required' => true
    ],
    
    // 清理旧记录
    '/api/wecom/hook/cleanup' => [
        'controller' => 'WeComHookManager',
        'method' => 'cleanupOldRecords',
        'auth_required' => true
    ],
    
    // 测试钩子
    '/api/wecom/hook/test' => [
        'controller' => 'WeComHookManager',
        'method' => 'testHookCode',
        'auth_required' => true
    ]
];
?>
