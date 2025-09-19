<?php
/**
 * 企业微信钩子服务
 * 
 * 负责SVN钩子的安装、管理、清理等功能
 * 
 * @author SVNAdmin Team
 * @version 1.0
 */

namespace app\service\wecom;

class WeComHookService
{
    /**
     * 数据库连接
     * @var object
     */
    private $database;
    
    /**
     * 日志服务
     * @var object
     */
    private $logger;
    
    /**
     * SVN配置
     * @var array
     */
    private $svnConfig;
    
    /**
     * 模板管理器
     * @var \app\util\wecom\WeComTemplateManager
     */
    private $templateManager;
    
    /**
     * 规则服务
     * @var WeComRuleService
     */
    private $ruleService;
    
    /**
     * 构造函数
     * 
     * @param object $database 数据库连接
     * @param object $logger 日志服务
     * @param array $svnConfig SVN配置
     * @param \app\util\wecom\WeComTemplateManager $templateManager 模板管理器
     */
    public function __construct($database, $logger, $svnConfig, $templateManager)
    {
        $this->database = $database;
        $this->logger = $logger;
        $this->svnConfig = $svnConfig;
        $this->templateManager = $templateManager;
    }
    
    /**
     * 设置规则服务（避免循环依赖）
     * 
     * @param WeComRuleService $ruleService 规则服务
     */
    public function setRuleService($ruleService)
    {
        $this->ruleService = $ruleService;
    }
    
    /**
     * 为指定仓库安装post-commit钩子
     *
     * @param string $repoName 仓库名称，支持通配符 '*'
     * @return array 安装结果
     */
    public function installHookForRepository($repoName)
    {
        try {
            $this->logInfo('开始为仓库安装钩子', ['repo_name' => $repoName]);
            
            // 使用最新的钩子模板文件路径
            $hookSource = BASE_PATH . '/templete/hooks/wecom_notify/post-commit';
            $repBasePath = $this->svnConfig['rep_base_path'] ?? ($this->svnConfig['home_path'] ?? '/home/svnadmin/') . 'rep/';
            
            $this->logInfo('钩子安装路径配置', [
                'hook_source' => $hookSource,
                'rep_base_path' => $repBasePath
            ]);
            
            // 检查钩子源文件是否存在
            if (!file_exists($hookSource)) {
                throw new \Exception("钩子源文件不存在: {$hookSource}，请检查路径配置或确保钩子模板文件已正确部署");
            }
            
            $installedRepos = [];
            $failedRepos = [];
            
            // 处理通配符 '*' - 为所有仓库安装钩子
            if ($repoName === '*') {
                $repos = $this->getAllRepositories($repBasePath);
                foreach ($repos as $repo) {
                    $result = $this->installHookToSingleRepo($repo, $hookSource, $repBasePath);
                    if ($result['status'] === 1) {
                        $installedRepos[] = $repo;
                    } else {
                        $failedRepos[] = ['repo' => $repo, 'error' => $result['message']];
                    }
                }
            } else {
                // 为单个仓库安装钩子
                $result = $this->installHookToSingleRepo($repoName, $hookSource, $repBasePath);
                if ($result['status'] === 1) {
                    $installedRepos[] = $repoName;
                } else {
                    $failedRepos[] = ['repo' => $repoName, 'error' => $result['message']];
                }
            }
            
            $this->logInfo('钩子安装完成', [
                'repo_name' => $repoName,
                'installed_count' => count($installedRepos),
                'failed_count' => count($failedRepos),
                'installed_repos' => $installedRepos,
                'failed_repos' => $failedRepos
            ]);
            
            if (empty($failedRepos)) {
                return [
                    'status' => 1,
                    'message' => '钩子安装成功',
                    'installed_repos' => $installedRepos
                ];
            } else {
                return [
                    'status' => count($installedRepos) > 0 ? 1 : 0,
                    'message' => count($installedRepos) > 0 ? '部分钩子安装成功' : '钩子安装失败',
                    'installed_repos' => $installedRepos,
                    'failed_repos' => $failedRepos
                ];
            }
            
        } catch (\Exception $e) {
            $this->logError('钩子安装失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '钩子安装失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 为单个仓库安装钩子（智能模式）
     *
     * @param string $repoName 仓库名
     * @param string $hookSource 钩子源文件
     * @param string $repBasePath 仓库基础路径
     * @return array 安装结果
     */
    private function installHookToSingleRepo($repoName, $hookSource, $repBasePath)
    {
        try {
            $repoPath = rtrim($repBasePath, '/') . '/' . $repoName;
            $hooksDir = $repoPath . '/hooks';
            $hookTarget = $hooksDir . '/post-commit';
            
            // 检查仓库是否存在
            if (!is_dir($repoPath)) {
                throw new \Exception("仓库不存在: {$repoPath}");
            }
            
            // 检查hooks目录是否存在
            if (!is_dir($hooksDir)) {
                throw new \Exception("仓库hooks目录不存在: {$hooksDir}");
            }
            
            // 分析现有钩子
            $hookAnalysis = $this->analyzeExistingHook($hookTarget);
            
            // 根据分析结果采取不同策略
            switch ($hookAnalysis['type']) {
                case 'none':
                    // 没有钩子，直接安装
                    return $this->installDirectHook($repoName, $hooksDir, $hookSource);
                    
                case 'dispatcher':
                    // 已经是调度器，只需安装子钩子
                    return $this->installSubHook($repoName, $hooksDir);
                    
                case 'wecom_only':
                    // 已经是我们的钩子，跳过
                    return [
                        'status' => 1,
                        'message' => '企业微信钩子已存在'
                    ];
                    
                case 'custom':
                    // 存在自定义钩子，需要迁移到调度器模式
                    return $this->migrateCustomHook($repoName, $hooksDir, $hookAnalysis['content']);
                    
                default:
                    throw new \Exception("未知的钩子类型: " . $hookAnalysis['type']);
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 分析现有钩子类型
     *
     * @param string $hookFile 钩子文件路径
     * @return array 分析结果
     */
    private function analyzeExistingHook($hookFile)
    {
        if (!file_exists($hookFile)) {
            return ['type' => 'none'];
        }
        
        $content = file_get_contents($hookFile);
        
        // 检查是否是我们的调度器
        if (strpos($content, 'SVNAdmin Hook Dispatcher') !== false) {
            return ['type' => 'dispatcher', 'managed' => true];
        }
        
        // 检查是否是我们的企业微信钩子
        if (strpos($content, 'WeComNotification') !== false || 
            strpos($content, 'hook_call') !== false) {
            return ['type' => 'wecom_only', 'managed' => true];
        }
        
        // 其他自定义钩子
        return ['type' => 'custom', 'managed' => false, 'content' => $content];
    }
    
    /**
     * 直接安装钩子（当没有现有钩子时）
     *
     * @param string $repoName 仓库名
     * @param string $hooksDir 钩子目录
     * @param string $hookSource 钩子源文件
     * @return array 安装结果
     */
    private function installDirectHook($repoName, $hooksDir, $hookSource)
    {
        try {
            $hookTarget = $hooksDir . '/post-commit';
            
            // 直接复制钩子文件
            if (!copy($hookSource, $hookTarget)) {
                throw new \Exception("无法复制钩子文件到: {$hookTarget}");
            }
            
            // 设置权限
            chmod($hookTarget, 0755);
            @chown($hookTarget, 'apache');
            @chgrp($hookTarget, 'apache');
            
            $this->logInfo('直接钩子安装成功', [
                'repo' => $repoName,
                'hook_file' => $hookTarget
            ]);
            
            return [
                'status' => 1,
                'message' => '企业微信钩子安装成功'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '直接钩子安装失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 安装调度器钩子
     *
     * @param string $repoName 仓库名
     * @param string $hooksDir 钩子目录
     * @return array 安装结果
     */
    private function installDispatcherHook($repoName, $hooksDir)
    {
        try {
            $hookFile = $hooksDir . '/post-commit';
            $subHooksDir = $hooksDir . '/post-commit.d';
            
            // 创建子钩子目录
            if (!is_dir($subHooksDir)) {
                if (!mkdir($subHooksDir, 0755, true)) {
                    throw new \Exception("无法创建子钩子目录: {$subHooksDir}");
                }
            }
            
            // 生成调度器内容
            $dispatcherContent = $this->templateManager->generateDispatcherContent();
            
            // 写入调度器钩子
            if (file_put_contents($hookFile, $dispatcherContent) === false) {
                throw new \Exception("无法写入调度器钩子文件");
            }
            
            // 设置权限
            chmod($hookFile, 0755);
            @chown($hookFile, 'apache');
            @chgrp($hookFile, 'apache');
            
            // 安装企业微信子钩子
            $subHookResult = $this->installWeComSubHook($subHooksDir);
            
            $this->logInfo('调度器钩子安装成功', [
                'repo' => $repoName,
                'dispatcher' => $hookFile,
                'sub_hooks_dir' => $subHooksDir,
                'wecom_sub_hook' => $subHookResult
            ]);
            
            return [
                'status' => 1,
                'message' => '调度器钩子安装成功，支持多钩子共存'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '调度器安装失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 安装子钩子（当调度器已存在时）
     *
     * @param string $repoName 仓库名
     * @param string $hooksDir 钩子目录
     * @return array 安装结果
     */
    private function installSubHook($repoName, $hooksDir)
    {
        try {
            $subHooksDir = $hooksDir . '/post-commit.d';
            
            // 确保子钩子目录存在
            if (!is_dir($subHooksDir)) {
                if (!mkdir($subHooksDir, 0755, true)) {
                    throw new \Exception("无法创建子钩子目录: {$subHooksDir}");
                }
            }
            
            // 安装企业微信子钩子
            $result = $this->installWeComSubHook($subHooksDir);
            
            $this->logInfo('子钩子安装成功', [
                'repo' => $repoName,
                'sub_hooks_dir' => $subHooksDir,
                'result' => $result
            ]);
            
            return [
                'status' => 1,
                'message' => '企业微信子钩子安装成功'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '子钩子安装失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 迁移自定义钩子到调度器模式
     *
     * @param string $repoName 仓库名
     * @param string $hooksDir 钩子目录
     * @param string $existingContent 现有钩子内容
     * @return array 迁移结果
     */
    private function migrateCustomHook($repoName, $hooksDir, $existingContent)
    {
        try {
            $hookFile = $hooksDir . '/post-commit';
            $subHooksDir = $hooksDir . '/post-commit.d';
            
            // 备份原钩子
            $backupFile = $hookFile . '.backup.' . date('Y-m-d-H-i-s');
            if (!copy($hookFile, $backupFile)) {
                throw new \Exception("无法备份原钩子文件");
            }
            
            // 创建子钩子目录
            if (!is_dir($subHooksDir)) {
                if (!mkdir($subHooksDir, 0755, true)) {
                    throw new \Exception("无法创建子钩子目录: {$subHooksDir}");
                }
            }
            
            // 将原钩子保存为子钩子
            $customSubHook = $subHooksDir . '/01-existing-custom.sh';
            if (file_put_contents($customSubHook, $existingContent) === false) {
                throw new \Exception("无法保存原钩子为子钩子");
            }
            chmod($customSubHook, 0755);
            @chown($customSubHook, 'apache');
            @chgrp($customSubHook, 'apache');
            
            // 安装调度器
            $dispatcherContent = $this->templateManager->generateDispatcherContent();
            if (file_put_contents($hookFile, $dispatcherContent) === false) {
                throw new \Exception("无法安装调度器钩子");
            }
            chmod($hookFile, 0755);
            @chown($hookFile, 'apache');
            @chgrp($hookFile, 'apache');
            
            // 安装企业微信子钩子
            $this->installWeComSubHook($subHooksDir);
            
            $this->logInfo('自定义钩子迁移成功', [
                'repo' => $repoName,
                'backup_file' => $backupFile,
                'custom_sub_hook' => $customSubHook,
                'dispatcher' => $hookFile
            ]);
            
            return [
                'status' => 1,
                'message' => '自定义钩子已迁移到调度器模式，原钩子已备份并保留功能'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '钩子迁移失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 安装企业微信子钩子
     *
     * @param string $subHooksDir 子钩子目录
     * @return array 安装结果
     */
    private function installWeComSubHook($subHooksDir)
    {
        try {
            $wecomSubHook = $subHooksDir . '/50-wecom-notification.sh';
            
            // 检查是否已存在
            if (file_exists($wecomSubHook)) {
                return [
                    'status' => 1,
                    'message' => '企业微信子钩子已存在'
                ];
            }
            
            // 获取钩子内容
            $hookContent = $this->templateManager->getDirectApiHookContent();
            
            // 写入钩子文件
            if (file_put_contents($wecomSubHook, $hookContent) === false) {
                throw new \Exception("写入企业微信钩子失败");
            }
            
            // 设置权限
            chmod($wecomSubHook, 0755);
            @chown($wecomSubHook, 'apache');
            @chgrp($wecomSubHook, 'apache');
            
            return [
                'status' => 1,
                'message' => '企业微信子钩子安装成功'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '企业微信子钩子安装失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取所有仓库列表
     *
     * @param string $repBasePath 仓库基础路径
     * @return array 仓库列表
     */
    private function getAllRepositories($repBasePath)
    {
        $repos = [];
        
        if (!is_dir($repBasePath)) {
            return $repos;
        }
        
        $items = scandir($repBasePath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $repBasePath . '/' . $item;
            if (is_dir($itemPath)) {
                // 检查是否是SVN仓库（包含hooks、conf、db等目录）
                $requiredDirs = ['hooks', 'conf', 'db'];
                $isRepo = true;
                foreach ($requiredDirs as $dir) {
                    if (!is_dir($itemPath . '/' . $dir)) {
                        $isRepo = false;
                        break;
                    }
                }
                
                if ($isRepo) {
                    $repos[] = $item;
                }
            }
        }
        
        return $repos;
    }
    
    /**
     * 智能移除钩子（支持调度器模式）
     *
     * @param string $repoName 仓库名
     * @return array 移除结果
     */
    public function removeHookFromRepository($repoName)
    {
        try {
            // 处理通配符的情况
            if ($repoName === '*') {
                return [
                    'status' => 1,
                    'message' => '通配符规则不执行钩子移除操作'
                ];
            }
            
            $repBasePath = $this->svnConfig['rep_base_path'] ?? ($this->svnConfig['home_path'] ?? '/home/svnadmin/') . 'rep/';
            $repoPath = rtrim($repBasePath, '/') . '/' . $repoName;
            $hooksDir = $repoPath . '/hooks';
            $hookFile = $hooksDir . '/post-commit';
            
            // 检查仓库和钩子是否存在
            if (!is_dir($repoPath)) {
                return [
                    'status' => 1,
                    'message' => '仓库不存在，无需清理钩子'
                ];
            }
            
            if (!file_exists($hookFile)) {
                return [
                    'status' => 1,
                    'message' => '钩子文件不存在，无需清理'
                ];
            }
            
            // 分析钩子类型
            $hookAnalysis = $this->analyzeExistingHook($hookFile);
            
            switch ($hookAnalysis['type']) {
                case 'dispatcher':
                    // 调度器模式，只移除企业微信子钩子
                    return $this->removeWeComSubHook($hooksDir);
                    
                case 'wecom_only':
                    // 纯企业微信钩子，直接删除
                    if (unlink($hookFile)) {
                        $this->logInfo('企业微信钩子已删除', ['repo' => $repoName, 'hook_file' => $hookFile]);
                        return [
                            'status' => 1,
                            'message' => '企业微信钩子已成功移除'
                        ];
                    } else {
                        throw new \Exception("无法删除钩子文件: {$hookFile}");
                    }
                    
                case 'custom':
                    // 自定义钩子，不删除（保护用户数据）
                    return [
                        'status' => 1,
                        'message' => '检测到自定义钩子，为保护数据未删除'
                    ];
                    
                default:
                    return [
                        'status' => 1,
                        'message' => '未知钩子类型，跳过删除'
                    ];
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 移除企业微信子钩子
     *
     * @param string $hooksDir 钩子目录
     * @return array 移除结果
     */
    private function removeWeComSubHook($hooksDir)
    {
        try {
            $subHooksDir = $hooksDir . '/post-commit.d';
            $wecomSubHook = $subHooksDir . '/50-wecom-notification.sh';
            
            if (!file_exists($wecomSubHook)) {
                return [
                    'status' => 1,
                    'message' => '企业微信子钩子不存在，无需清理'
                ];
            }
            
            if (unlink($wecomSubHook)) {
                $this->logInfo('企业微信子钩子已删除', ['sub_hook' => $wecomSubHook]);
                
                // 检查子钩子目录是否为空（除了可能的日志文件）
                $remainingHooks = glob($subHooksDir . '/*');
                $remainingHooks = array_filter($remainingHooks, function($file) {
                    return !in_array(basename($file), ['post-commit.log', '.', '..']);
                });
                
                if (empty($remainingHooks)) {
                    return [
                        'status' => 1,
                        'message' => '企业微信子钩子已移除，调度器保留（可手动清理）'
                    ];
                } else {
                    return [
                        'status' => 1,
                        'message' => '企业微信子钩子已移除，其他子钩子保留'
                    ];
                }
            } else {
                throw new \Exception("无法删除企业微信子钩子: {$wecomSubHook}");
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'message' => '移除企业微信子钩子失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 清理孤儿钩子
     *
     * @param string $repoName 仓库名
     * @return array 清理结果
     */
    public function cleanupOrphanedHooks($repoName)
    {
        try {
            // 检查仓库是否还有启用的规则
            $hasActiveRules = $this->ruleService ? $this->ruleService->hasActiveRulesForRepo($repoName) : true;
            
            if (!$hasActiveRules) {
                // 没有启用的规则，移除钩子
                $removeResult = $this->removeHookFromRepository($repoName);
                
                return [
                    'cleaned' => $removeResult['status'] === 1,
                    'message' => $removeResult['status'] === 1 ? '已清理孤儿钩子' : '清理钩子失败: ' . $removeResult['message'],
                    'repo_name' => $repoName
                ];
            } else {
                return [
                    'cleaned' => false,
                    'message' => '仓库仍有启用的规则，保留钩子',
                    'repo_name' => $repoName
                ];
            }
            
        } catch (\Exception $e) {
            $this->logError('清理孤儿钩子失败', $e->getMessage());
            return [
                'cleaned' => false,
                'message' => '清理失败: ' . $e->getMessage(),
                'repo_name' => $repoName
            ];
        }
    }
    
    /**
     * 获取钩子状态报告
     *
     * @return array 状态报告
     */
    public function getHookStatusReport()
    {
        try {
            $repBasePath = $this->svnConfig['rep_base_path'] ?? ($this->svnConfig['home_path'] ?? '/home/svnadmin/') . 'rep/';
            $repos = $this->getAllRepositories($repBasePath);
            
            $report = [
                'total_repos' => count($repos),
                'repos_with_hooks' => 0,
                'repos_with_wecom_hooks' => 0,
                'repos_with_dispatcher' => 0,
                'repos_with_custom_hooks' => 0,
                'repos_without_hooks' => 0,
                'details' => []
            ];
            
            foreach ($repos as $repo) {
                $hooksDir = $repBasePath . $repo . '/hooks';
                $hookFile = $hooksDir . '/post-commit';
                
                $repoStatus = [
                    'repo_name' => $repo,
                    'has_hook' => file_exists($hookFile),
                    'hook_type' => 'none',
                    'hook_analysis' => null
                ];
                
                if ($repoStatus['has_hook']) {
                    $report['repos_with_hooks']++;
                    $analysis = $this->analyzeExistingHook($hookFile);
                    $repoStatus['hook_type'] = $analysis['type'];
                    $repoStatus['hook_analysis'] = $analysis;
                    
                    switch ($analysis['type']) {
                        case 'wecom_only':
                            $report['repos_with_wecom_hooks']++;
                            break;
                        case 'dispatcher':
                            $report['repos_with_dispatcher']++;
                            break;
                        case 'custom':
                            $report['repos_with_custom_hooks']++;
                            break;
                    }
                } else {
                    $report['repos_without_hooks']++;
                }
                
                $report['details'][] = $repoStatus;
            }
            
            return [
                'status' => 1,
                'message' => '获取钩子状态报告成功',
                'data' => $report
            ];
            
        } catch (\Exception $e) {
            $this->logError('获取钩子状态报告失败', $e->getMessage());
            return [
                'status' => 0,
                'message' => '获取状态报告失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 记录信息日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    private function logInfo($message, $context = [])
    {
        if ($this->logger) {
            $this->logger->writeLog('info', '[WeComHookService] ' . $message, $context);
        }
    }
    
    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param string $error 错误详情
     */
    private function logError($message, $error = '')
    {
        if ($this->logger) {
            $this->logger->writeLog('error', '[WeComHookService] ' . $message, ['error' => $error]);
        }
    }
}
?>
