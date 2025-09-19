<?php
/*
 * @Author: SVNAdmin WeChat Integration
 * 
 * @LastEditors: SVNAdmin WeChat Integration
 * 
 * @Description: 企业微信集成功能安装向导
 */

/**
 * 企业微信集成功能交互式安装向导
 */

/**
 * 将工作模式限制在cli模式
 */
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode' . PHP_EOL);
}

define('BASE_PATH', __DIR__ . '/..');

class WeComSetupWizard
{
    private $config = [];
    private $configFile;

    public function __construct()
    {
        $this->configFile = BASE_PATH . '/config/wecom.php';
    }

    /**
     * 运行安装向导
     */
    public function run()
    {
        $this->printWelcome();
        
        if ($this->checkExistingConfig()) {
            if (!$this->confirmOverwrite()) {
                echo "安装已取消。" . PHP_EOL;
                exit(0);
            }
        }

        $this->collectBasicConfig();
        $this->collectSyncConfig();
        $this->collectNotificationConfig();
        $this->collectMappingConfig();
        
        $this->showConfigSummary();
        
        if ($this->confirmSave()) {
            $this->saveConfig();
            $this->showNextSteps();
        } else {
            echo "配置未保存。" . PHP_EOL;
        }
    }

    /**
     * 显示欢迎信息
     */
    private function printWelcome()
    {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo "  SVNAdmin 企业微信集成配置向导" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;
        echo "此向导将帮助您配置企业微信集成功能。" . PHP_EOL;
        echo "请准备好以下信息：" . PHP_EOL;
        echo "- 企业ID (CorpId)" . PHP_EOL;
        echo "- 应用Secret" . PHP_EOL;
        echo "- 应用ID (AgentId)" . PHP_EOL;
        echo "- Webhook URL (可选)" . PHP_EOL;
        echo PHP_EOL;
        echo "按 Enter 键继续...";
        fgets(STDIN);
        echo PHP_EOL;
    }

    /**
     * 检查现有配置
     */
    private function checkExistingConfig()
    {
        return file_exists($this->configFile);
    }

    /**
     * 确认覆盖现有配置
     */
    private function confirmOverwrite()
    {
        echo "检测到现有的企业微信配置文件。" . PHP_EOL;
        echo "是否要覆盖现有配置？(y/N): ";
        $input = trim(fgets(STDIN));
        return strtolower($input) === 'y';
    }

    /**
     * 收集基础配置
     */
    private function collectBasicConfig()
    {
        echo "========== 基础配置 ==========" . PHP_EOL;
        echo PHP_EOL;

        // 企业ID
        echo "请输入企业ID (CorpId)：" . PHP_EOL;
        echo "提示：在企业微信管理后台的"我的企业"页面获取" . PHP_EOL;
        echo "CorpId: ";
        $this->config['corp_id'] = trim(fgets(STDIN));

        // 应用Secret
        echo PHP_EOL;
        echo "请输入应用Secret：" . PHP_EOL;
        echo "提示：在企业微信管理后台的"应用管理"页面获取" . PHP_EOL;
        echo "Secret: ";
        $this->config['corp_secret'] = trim(fgets(STDIN));

        // 应用ID
        echo PHP_EOL;
        echo "请输入应用ID (AgentId)：" . PHP_EOL;
        echo "提示：在企业微信管理后台的"应用管理"页面获取" . PHP_EOL;
        echo "AgentId: ";
        $this->config['agent_id'] = trim(fgets(STDIN));

        // 启用功能
        echo PHP_EOL;
        echo "是否立即启用企业微信集成功能？(Y/n): ";
        $input = trim(fgets(STDIN));
        $this->config['enabled'] = strtolower($input) !== 'n';

        echo PHP_EOL;
    }

    /**
     * 收集同步配置
     */
    private function collectSyncConfig()
    {
        echo "========== 同步配置 ==========" . PHP_EOL;
        echo PHP_EOL;

        // 启用同步
        echo "是否启用数据同步功能？(Y/n): ";
        $input = trim(fgets(STDIN));
        $this->config['sync_enabled'] = strtolower($input) !== 'n';

        if ($this->config['sync_enabled']) {
            // 同步部门
            echo "是否同步部门信息？(Y/n): ";
            $input = trim(fgets(STDIN));
            $this->config['sync_departments'] = strtolower($input) !== 'n';

            // 同步用户
            echo "是否同步用户信息？(Y/n): ";
            $input = trim(fgets(STDIN));
            $this->config['sync_users'] = strtolower($input) !== 'n';

            // 同步权限
            echo "是否同步权限关系？(Y/n): ";
            $input = trim(fgets(STDIN));
            $this->config['sync_permissions'] = strtolower($input) !== 'n';

            // 同步间隔
            echo "同步间隔时间（分钟，默认5）: ";
            $input = trim(fgets(STDIN));
            $interval = is_numeric($input) && $input > 0 ? intval($input) : 5;
            $this->config['sync_interval'] = $interval * 60;
        }

        echo PHP_EOL;
    }

    /**
     * 收集通知配置
     */
    private function collectNotificationConfig()
    {
        echo "========== 通知配置 ==========" . PHP_EOL;
        echo PHP_EOL;

        // 启用通知
        echo "是否启用通知功能？(Y/n): ";
        $input = trim(fgets(STDIN));
        $this->config['notification_enabled'] = strtolower($input) !== 'n';

        if ($this->config['notification_enabled']) {
            // Webhook URL
            echo "请输入默认的 Webhook URL（可选）：" . PHP_EOL;
            echo "提示：在企业微信群聊中创建机器人获取 Webhook URL" . PHP_EOL;
            echo "Webhook URL: ";
            $this->config['default_webhook_url'] = trim(fgets(STDIN));

            // 批量通知
            echo "是否启用批量通知？(Y/n): ";
            $input = trim(fgets(STDIN));
            $this->config['batch_notification'] = strtolower($input) !== 'n';
        }

        echo PHP_EOL;
    }

    /**
     * 收集映射配置
     */
    private function collectMappingConfig()
    {
        echo "========== 映射配置 ==========" . PHP_EOL;
        echo PHP_EOL;

        // 自动创建SVN用户
        echo "是否自动为企业微信用户创建 SVN 账号？(y/N): ";
        $input = trim(fgets(STDIN));
        $autoCreateUser = strtolower($input) === 'y';
        
        $this->config['user_mapping'] = [
            'auto_create_svn_user' => $autoCreateUser,
            'default_password' => '123456',
            'username_prefix' => '',
            'match_by_email' => true,
            'match_by_mobile' => true,
            'match_by_userid' => true,
        ];

        if ($autoCreateUser) {
            // 默认密码
            echo "新用户的默认密码（默认123456）: ";
            $input = trim(fgets(STDIN));
            if (!empty($input)) {
                $this->config['user_mapping']['default_password'] = $input;
            }

            // 用户名前缀
            echo "SVN 用户名前缀（可选）: ";
            $input = trim(fgets(STDIN));
            $this->config['user_mapping']['username_prefix'] = $input;
        }

        // 自动创建SVN组
        echo "是否自动为企业微信部门创建 SVN 用户组？(Y/n): ";
        $input = trim(fgets(STDIN));
        $autoCreateGroup = strtolower($input) !== 'n';

        $this->config['department_mapping'] = [
            'auto_create_svn_group' => $autoCreateGroup,
            'group_prefix' => '',
            'sync_hierarchy' => true,
            'root_department_id' => 1,
        ];

        if ($autoCreateGroup) {
            // 组名前缀
            echo "SVN 用户组名前缀（默认wecom_）: ";
            $input = trim(fgets(STDIN));
            if (!empty($input)) {
                $this->config['department_mapping']['group_prefix'] = $input;
            }
        }

        // 权限配置
        $this->config['permission_mapping'] = [
            'default_permission' => 'r',
            'admin_permission' => 'rw',
            'inherit_parent_permission' => true,
            'department_based_permission' => true,
        ];

        echo PHP_EOL;
    }

    /**
     * 显示配置摘要
     */
    private function showConfigSummary()
    {
        echo "========== 配置摘要 ==========" . PHP_EOL;
        echo PHP_EOL;
        
        echo "基础配置：" . PHP_EOL;
        echo "  企业ID: " . $this->config['corp_id'] . PHP_EOL;
        echo "  应用Secret: " . str_repeat('*', strlen($this->config['corp_secret'])) . PHP_EOL;
        echo "  应用ID: " . $this->config['agent_id'] . PHP_EOL;
        echo "  启用功能: " . ($this->config['enabled'] ? '是' : '否') . PHP_EOL;
        echo PHP_EOL;

        if (isset($this->config['sync_enabled'])) {
            echo "同步配置：" . PHP_EOL;
            echo "  启用同步: " . ($this->config['sync_enabled'] ? '是' : '否') . PHP_EOL;
            if ($this->config['sync_enabled']) {
                echo "  同步部门: " . ($this->config['sync_departments'] ? '是' : '否') . PHP_EOL;
                echo "  同步用户: " . ($this->config['sync_users'] ? '是' : '否') . PHP_EOL;
                echo "  同步权限: " . ($this->config['sync_permissions'] ? '是' : '否') . PHP_EOL;
                echo "  同步间隔: " . ($this->config['sync_interval'] / 60) . " 分钟" . PHP_EOL;
            }
            echo PHP_EOL;
        }

        if (isset($this->config['notification_enabled'])) {
            echo "通知配置：" . PHP_EOL;
            echo "  启用通知: " . ($this->config['notification_enabled'] ? '是' : '否') . PHP_EOL;
            if ($this->config['notification_enabled']) {
                echo "  Webhook URL: " . ($this->config['default_webhook_url'] ?: '未设置') . PHP_EOL;
                echo "  批量通知: " . ($this->config['batch_notification'] ? '是' : '否') . PHP_EOL;
            }
            echo PHP_EOL;
        }

        echo "映射配置：" . PHP_EOL;
        echo "  自动创建SVN用户: " . ($this->config['user_mapping']['auto_create_svn_user'] ? '是' : '否') . PHP_EOL;
        echo "  自动创建SVN组: " . ($this->config['department_mapping']['auto_create_svn_group'] ? '是' : '否') . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 确认保存配置
     */
    private function confirmSave()
    {
        echo "是否保存以上配置？(Y/n): ";
        $input = trim(fgets(STDIN));
        return strtolower($input) !== 'n';
    }

    /**
     * 保存配置
     */
    private function saveConfig()
    {
        // 加载模板配置
        $templateFile = BASE_PATH . '/config/wecom.php.template';
        if (file_exists($templateFile)) {
            $template = include $templateFile;
        } else {
            $template = $this->getDefaultTemplate();
        }

        // 合并用户配置
        $finalConfig = array_merge($template, $this->config);

        // 生成配置文件内容
        $configContent = "<?php\n/*\n * 企业微信集成配置文件\n * \n * 此文件由安装向导自动生成\n * 生成时间: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($finalConfig, true) . ";\n";

        // 保存配置文件
        if (file_put_contents($this->configFile, $configContent)) {
            echo PHP_EOL;
            echo "✓ 配置文件保存成功！" . PHP_EOL;
            echo "配置文件位置: " . $this->configFile . PHP_EOL;
        } else {
            echo PHP_EOL;
            echo "✗ 配置文件保存失败！" . PHP_EOL;
            echo "请检查目录权限: " . dirname($this->configFile) . PHP_EOL;
            exit(1);
        }
    }

    /**
     * 显示后续步骤
     */
    private function showNextSteps()
    {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo "配置完成！后续步骤：" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo PHP_EOL;
        
        echo "1. 运行安装脚本创建数据库表：" . PHP_EOL;
        echo "   php wecom_install.php install" . PHP_EOL;
        echo PHP_EOL;
        
        echo "2. 启动守护进程：" . PHP_EOL;
        echo "   php svnadmind.php start" . PHP_EOL;
        echo "   php wecom_notification_daemon.php start" . PHP_EOL;
        echo PHP_EOL;
        
        echo "3. 访问 SVNAdmin Web 界面进行进一步配置：" . PHP_EOL;
        echo "   - 企业微信配置页面" . PHP_EOL;
        echo "   - 同步管理页面" . PHP_EOL;
        echo "   - 通知规则配置页面" . PHP_EOL;
        echo PHP_EOL;
        
        echo "4. 测试功能：" . PHP_EOL;
        echo "   - 手动执行一次同步" . PHP_EOL;
        echo "   - 测试通知发送" . PHP_EOL;
        echo "   - 检查用户映射" . PHP_EOL;
        echo PHP_EOL;
        
        echo "如需帮助，请查看帮助文档或运行：" . PHP_EOL;
        echo "php wecom_install.php check" . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * 获取默认模板
     */
    private function getDefaultTemplate()
    {
        return [
            'api_base_url' => 'https://qyapi.weixin.qq.com',
            'token_cache_time' => 7200,
            'request_timeout' => 30,
            'max_retries' => 3,
            'full_sync_interval' => 86400,
            'notification_timeout' => 10,
            'log_enabled' => true,
            'log_level' => 'info',
            'log_retention_days' => 30,
            'permission_mapping' => [
                'default_permission' => 'r',
                'admin_permission' => 'rw',
                'inherit_parent_permission' => true,
                'department_based_permission' => true,
            ],
            'debug_mode' => false,
            'test_mode' => false,
            'custom_config' => [],
        ];
    }
}

// 运行安装向导
$wizard = new WeComSetupWizard();
$wizard->run();
