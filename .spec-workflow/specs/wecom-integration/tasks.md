# Tasks Document

## 阶段 1: 基础配置和数据库结构

- [x] 1. 创建企业微信配置文件
  - File: 02.php/config/wecom.php
  - 定义企业微信 API 配置结构和默认值
  - 包含企业ID、应用密钥、同步设置等配置项
  - Purpose: 建立企业微信集成的配置基础
  - _Leverage: 02.php/config/database.php, 02.php/config/svn.php_
  - _Requirements: 6.1_

- [x] 2. 创建数据库迁移脚本
  - File: 02.php/templete/database/sqlite/wecom_tables.sql
  - 创建 wecom_config, wecom_departments, wecom_users, wecom_notification_rules 表
  - 为 MySQL 创建对应的迁移脚本
  - Purpose: 建立企业微信数据存储结构
  - _Leverage: 02.php/templete/database/sqlite/svnadmin.sql_
  - _Requirements: 1.1, 2.1, 3.1, 4.1_

- [x] 3. 扩展现有用户表结构
  - File: 04.update/wecom-integration/database_migration.php
  - 为 svn_users 表添加企业微信相关字段
  - 创建数据库升级脚本
  - Purpose: 扩展现有用户系统支持企业微信集成
  - _Leverage: 04.update/to-2.5.10/from-2.3.x-and-2.4.x-and2.5.x/update/index.php_
  - _Requirements: 2.1_

## 阶段 2: 企业微信 API 集成层

- [x] 4. 创建企业微信 API 基础类
  - File: 02.php/app/service/WeComAPI.php
  - 实现企业微信 API 认证和基础请求方法
  - 包含访问令牌管理和错误处理
  - Purpose: 封装企业微信 API 调用的底层逻辑
  - _Leverage: 02.php/app/service/base/Base.php_
  - _Requirements: 1.1, 2.1, 3.1, 4.1_

- [x] 5. 实现通讯录 API 接口
  - File: 02.php/app/service/WeComAPI.php (继续)
  - 添加获取部门列表和用户信息的方法
  - 实现分页和增量获取功能
  - Purpose: 提供组织架构数据获取能力
  - _Leverage: 现有的 cURL 和 JSON 处理函数_
  - _Requirements: 1.1, 2.1_

- [x] 6. 实现消息推送 API 接口
  - File: 02.php/app/service/WeComAPI.php (继续)
  - 添加群消息发送和应用消息推送方法
  - 实现消息模板和变量替换功能
  - Purpose: 提供实时通知发送能力
  - _Leverage: 现有的消息处理机制_
  - _Requirements: 4.1_

## 阶段 3: 数据同步服务层

- [x] 7. 创建企业微信同步服务基础类
  - File: 02.php/app/service/WeComSync.php
  - 继承 Base 服务类，实现基础同步逻辑
  - 添加同步状态管理和错误处理
  - Purpose: 建立数据同步的核心服务框架
  - _Leverage: 02.php/app/service/base/Base.php, 02.php/app/service/Logs.php_
  - _Requirements: 1.1, 2.1, 3.1_

- [x] 8. 实现部门同步功能
  - File: 02.php/app/service/WeComSync.php (继续)
  - 添加部门数据获取、比较和更新方法
  - 实现部门到 SVN 用户组的映射逻辑
  - Purpose: 自动同步企业微信部门结构到 SVN 用户组
  - _Leverage: 02.php/app/service/Svngroup.php, 02.php/app/service/WeComAPI.php_
  - _Requirements: 1.1_

- [x] 9. 实现用户同步功能
  - File: 02.php/app/service/WeComSync.php (继续)
  - 添加用户数据获取、匹配和更新方法
  - 实现多种用户匹配策略（ID、邮箱、手机号）
  - Purpose: 自动同步和匹配企业微信用户到 SVN 用户
  - _Leverage: 02.php/app/service/Svnuser.php, 02.php/app/service/WeComAPI.php_
  - _Requirements: 2.1_

- [x] 10. 实现权限同步功能
  - File: 02.php/app/service/WeComSync.php (继续)
  - 添加基于部门关系的权限分配逻辑
  - 实现 authz 文件的动态生成和更新
  - Purpose: 根据企业微信组织架构自动分配 SVN 权限
  - _Leverage: 02.php/app/service/Apache.php, 现有的 authz 生成机制_
  - _Requirements: 3.1_

## 阶段 4: 通知服务层

- [x] 11. 创建企业微信通知服务类
  - File: 02.php/app/service/WeComNotification.php
  - 实现通知规则管理和消息构建功能
  - 添加消息模板和变量替换机制
  - Purpose: 管理 SVN 操作的企业微信通知
  - _Leverage: 02.php/app/service/base/Base.php, 02.php/app/service/WeComAPI.php_
  - _Requirements: 4.1_

- [x] 12. 实现通知规则引擎
  - File: 02.php/app/service/WeComNotification.php (继续)
  - 添加规则匹配和事件过滤逻辑
  - 实现消息合并和频率限制功能
  - Purpose: 根据配置的规则智能发送通知
  - _Leverage: 现有的日志和配置管理机制_
  - _Requirements: 4.1_

- [x] 13. 创建 SVN 钩子脚本
  - File: hooks/wecom_notify/post-commit
  - 实现 SVN 操作事件的捕获和处理
  - 调用企业微信通知服务发送消息
  - Purpose: 在 SVN 操作时触发企业微信通知
  - _Leverage: hooks/01/post-commit, hooks/02/post-commit_
  - _Requirements: 4.1_

## 阶段 5: Web 管理界面

- [x] 14. 创建企业微信管理控制器
  - File: 02.php/app/controller/WeComAdmin.php
  - 继承 Base 控制器，实现基础管理功能
  - 添加权限验证和错误处理
  - Purpose: 提供企业微信集成的 Web 管理入口
  - _Leverage: 02.php/app/controller/base/Base.php_
  - _Requirements: 5.1_

- [x] 15. 实现配置管理接口
  - File: 02.php/app/controller/WeComAdmin.php (继续)
  - 添加 API 配置的增删改查方法
  - 实现配置验证和测试连接功能
  - Purpose: 允许管理员配置企业微信 API 参数
  - _Leverage: 现有的配置管理模式_
  - _Requirements: 5.1, 6.1_

- [x] 16. 实现同步管理接口
  - File: 02.php/app/controller/WeComAdmin.php (继续)
  - 添加手动同步触发和状态查询方法
  - 实现同步日志查看和错误处理
  - Purpose: 提供同步操作的管理和监控功能
  - _Leverage: 02.php/app/service/WeComSync.php_
  - _Requirements: 5.1, 1.1, 2.1, 3.1_

- [x] 17. 实现通知规则管理接口
  - File: 02.php/app/controller/WeComAdmin.php (继续)
  - 添加通知规则的增删改查方法
  - 实现规则测试和统计查看功能
  - Purpose: 允许管理员配置和管理通知规则
  - _Leverage: 02.php/app/service/WeComNotification.php_
  - _Requirements: 5.1, 4.1_

## 阶段 6: 前端界面开发

- [x] 18. 创建企业微信管理页面结构
  - File: 01.web/src/views/wecom/index.vue
  - 设计企业微信管理的主界面布局
  - 添加导航菜单和状态显示
  - Purpose: 提供企业微信功能的统一管理入口
  - _Leverage: 01.web/src/views/layout/basicLayout/index.vue_
  - _Requirements: 5.1_

- [x] 19. 实现 API 配置页面
  - File: 01.web/src/views/wecom/config.vue
  - 创建企业微信 API 配置表单
  - 添加配置验证和连接测试功能
  - Purpose: 提供友好的 API 配置界面
  - _Leverage: 01.web/src/views/setting/index.vue_
  - _Requirements: 5.1, 6.1_

- [x] 20. 实现同步管理页面
  - File: 01.web/src/views/wecom/sync.vue
  - 创建同步状态监控和操作界面
  - 添加同步日志查看和手动触发功能
  - Purpose: 提供直观的同步管理和监控界面
  - _Leverage: 01.web/src/views/logs/index.vue_
  - _Requirements: 5.1, 1.1, 2.1, 3.1_

- [x] 21. 实现通知规则管理页面
  - File: 01.web/src/views/wecom/notification.vue
  - 创建通知规则配置和管理界面
  - 添加规则向导和消息预览功能
  - Purpose: 提供灵活的通知规则配置界面
  - _Leverage: 01.web/src/components/modalRepPri.vue_
  - _Requirements: 5.1, 4.1_

- [x] 22. 实现用户映射管理页面
  - File: 01.web/src/views/wecom/mapping.vue
  - 创建用户匹配状态查看和手动映射界面
  - 添加批量映射和导入导出功能
  - Purpose: 处理用户匹配失败的情况
  - _Leverage: 01.web/src/views/repositoryUser/index.vue_
  - _Requirements: 5.1, 2.1_

## 阶段 7: 守护进程和定时任务

- [x] 23. 扩展主守护进程
  - File: server/svnadmind.php (修改现有文件)
  - 添加企业微信同步任务调度
  - 实现定时同步和错误恢复机制
  - Purpose: 将企业微信同步集成到现有守护进程
  - _Leverage: 现有的守护进程架构和任务调度机制_
  - _Requirements: 1.1, 2.1, 3.1_

- [x] 24. 创建企业微信通知守护进程
  - File: server/wecom_notification_daemon.php
  - 实现独立的通知处理守护进程
  - 添加消息队列和重试机制
  - Purpose: 处理实时通知发送，避免阻塞主进程
  - _Leverage: server/svnadmind.php 的架构模式_
  - _Requirements: 4.1_

- [x] 25. 创建安装和配置脚本
  - File: server/wecom_install.php
  - 实现企业微信功能的安装和初始化
  - 添加数据库表创建和配置文件生成
  - Purpose: 简化企业微信功能的部署和配置
  - _Leverage: server/install.php_
  - _Requirements: 6.1_

## 阶段 8: 测试和文档

- [x] 26. 创建单元测试框架
  - File: tests/WeComAPITest.php
  - 为企业微信 API 服务创建单元测试
  - 使用 Mock 对象模拟 API 响应
  - Purpose: 确保 API 集成的可靠性
  - _Leverage: PHPUnit 测试框架_
  - _Requirements: 1.1, 2.1, 3.1, 4.1_

- [x] 27. 创建集成测试
  - File: tests/WeComIntegrationTest.php
  - 测试完整的同步和通知流程
  - 使用测试数据库和模拟企业微信环境
  - Purpose: 验证各组件之间的集成正确性
  - _Leverage: 现有的测试基础设施_
  - _Requirements: All_

- [x] 28. 创建用户文档
  - File: docs/WECOM_INTEGRATION.md
  - 编写企业微信集成的配置和使用指南
  - 包含常见问题解答和故障排除
  - Purpose: 帮助用户正确配置和使用企业微信功能
  - _Leverage: README.md 的文档结构_
  - _Requirements: All_

- [x] 29. 创建 API 文档
  - File: docs/WECOM_API.md
  - 记录企业微信集成相关的 API 接口
  - 包含请求参数、响应格式和错误码说明
  - Purpose: 为开发者提供 API 参考文档
  - _Leverage: 现有的 API 文档格式_
  - _Requirements: 5.1_

## 阶段 9: 部署和优化

- [x] 30. 更新 Docker 配置
  - File: 03.cicd/svnadmin_docker/dockerfile (修改)
  - 添加企业微信功能所需的 PHP 扩展和配置
  - 更新启动脚本包含企业微信守护进程
  - Purpose: 支持容器化部署企业微信功能
  - _Leverage: 现有的 Docker 配置_
  - _Requirements: All_

- [x] 31. 创建配置模板
  - File: 02.php/templete/wecom/config_template.php
  - 提供企业微信配置的模板文件
  - 包含详细的配置说明和示例
  - Purpose: 简化初次配置过程
  - _Leverage: 02.php/templete/ 目录下的其他模板_
  - _Requirements: 6.1_

- [x] 32. 性能优化和最终测试
  - File: 多个文件的优化
  - 优化数据库查询和 API 调用性能
  - 进行压力测试和性能调优
  - 修复发现的问题和改进用户体验
  - Purpose: 确保企业微信功能在生产环境中的稳定性
  - _Leverage: 现有的性能监控和优化工具_
  - _Requirements: All_