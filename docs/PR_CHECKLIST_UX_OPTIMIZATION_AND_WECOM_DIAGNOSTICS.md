# PR Checklist: UX Optimization and WeCom Diagnostics

## Scope

本次变更覆盖仓库管理页交互重构、全局任务球视觉分层、Layout 侧边栏折叠、WeCom 一键诊断，以及 repositoryInfo 页面组件拆分和体验微调。

## Automated Checks

- [x] 前端生产构建通过：`npm run build`
- [x] 空白/尾随空格检查通过：`git diff --check`
- [x] 浏览器可打开本地应用登录页：`http://localhost:8080/#/login`
- [ ] PHP 语法检查：当前本机未配置 `php` 命令，需在 PHP 运行环境执行 `php -l 02.php/app/controller/WeComAdmin.php`

## Login-Gated Manual Regression

### Repository Info

- [ ] 仓库搜索框输入 1 个字符时展示本地联想，不触发后端请求。
- [ ] 仓库搜索框输入 2 个以上字符时，联想搜索经过约 300ms 防抖后请求后端。
- [ ] 键盘方向键、Enter、Esc 可正常操作联想面板。
- [ ] 选择联想项后，如果当前页存在该仓库，行高亮提示正常。
- [ ] 调整仓库列表列宽后刷新页面，列宽保持上次设置。
- [ ] 修改备注后失焦可保存。
- [ ] 修改备注后按 Enter 可保存。
- [ ] 备注保存期间局部 loading 状态正常，不影响整表操作。

### Repository Components

- [ ] `RepoExplorer` 可打开管理员仓库浏览。
- [ ] `RepoExplorer` 可打开 SVN 用户授权路径浏览。
- [ ] `RepoExplorer` 面包屑、目录下钻、检出地址复制正常。
- [ ] `RepoPermission` 权限路径层级展示清晰，路径切换和授权操作正常。
- [ ] `RepoHookManager` 钩子列表、编辑、删除、推荐模板查看/应用正常。
- [ ] `RepoAdvancedSettings` 仓库属性加载正常。
- [ ] `RepoAdvancedSettings` UUID 重设弹窗和提交正常。
- [ ] `RepoAdvancedSettings` 备份列表加载正常。
- [ ] `RepoAdvancedSettings` 立即备份可加入后台任务。
- [ ] `RepoAdvancedSettings` 上传 `.dump` 文件时分片进度、暂停、完成刷新正常。
- [ ] `RepoAdvancedSettings` 恢复失败时错误详情弹窗正常。

### Layout and Global Task Ball

- [ ] Layout 侧边栏可展开/折叠。
- [ ] 折叠状态刷新后保持。
- [ ] 复杂表格页面在侧边栏折叠后横向空间明显增加。
- [ ] `global-task-ball` 不遮挡仓库列表核心操作按钮。
- [ ] `global-task-ball` 展开/收起状态下视觉层级与主业务界面有明显区分。

### WeCom Diagnostics

- [ ] WeCom 页面显示“一键诊断”入口。
- [ ] 点击诊断后展示配置、PHP 扩展、网络连通性等诊断项。
- [ ] 诊断失败项提供明确排错建议。
- [ ] 诊断接口异常时前端有可理解的错误提示。

### Service Status Banner

- [ ] SVN 服务异常时，页面顶部通过 `ServiceStatusBanner` 展示系统级提示。
- [ ] Banner 样式不遮挡主操作区。
- [ ] 后续 WeCom、磁盘空间等系统级通知可复用同一组件。

## PR Notes

- `repositoryInfo/index.vue` 已拆分出 `RepoExplorer.vue`、`RepoPermission.vue`、`RepoHookManager.vue`、`RepoAdvancedSettings.vue`，父页面仅保留列表、搜索和入口编排逻辑。
- 当前浏览器停留在登录页，业务页面手工验收需要登录态和验证码配合。
- 工作区存在若干非本次变更文件，提交时应只纳入本 PR 相关文件。
