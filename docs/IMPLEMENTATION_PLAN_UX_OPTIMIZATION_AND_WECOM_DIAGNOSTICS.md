# Implementation Plan: 仓库权限体验、布局响应式与 WeCom 一键诊断

## 1. 总体策略

本需求拆成 4 条主线推进:

1. 全局布局底座: 先完成侧边栏折叠与任务球避让，释放表格空间，影响面最大但技术边界清晰。
2. 仓库检索与权限路径: 在现有 `repositoryInfo` 与 `modalRepPri` 基础上增强，不改变后端权限保存逻辑。
3. WeCom 诊断后端: 先提供结构化 `RunDiagnostics` 接口，前端只做通用渲染。
4. WeCom 诊断前端: 在 WeCom 首页与配置页接入诊断结果、排错指引和复制能力。

建议按 P0 -> P1 -> P2 迭代，不一次性大改所有交互。

## 2. 代码现状判断

- 前端技术栈为 Vue 2 + View UI，入口在 `01.web/src`。
- 全局布局在 `01.web/src/views/layout/basicLayout/index.vue` 与 `index.css`，当前侧栏固定 `width="200"`，内容区 CSS 使用 `padding-left: 200px`。
- 后台任务悬浮球已在 `basicLayout/index.vue` 内实现，样式集中在 `index.css` 的 `.global-task-ball`。
- 仓库页面在 `01.web/src/views/repositoryInfo/index.vue`，已有 `searchKeywordRep`、`GetRepList()`、`GetSvnUserRepList()` 和仓库表格。
- 权限弹窗 `01.web/src/components/modalRepPri.vue` 已有左侧 `Tree`、右侧权限表、路径搜索 `searchKeywordRepPathPri`，适合渐进增强。
- WeCom 首页在 `01.web/src/views/wecom/index.vue`，配置页在 `WecomConfig.vue`，后端控制器 `WeComAdmin.php` 已有 `TestConnection()`、`GetSystemStatus()`、`TestSyncConnection()`、`GetDetailedSyncStatus()` 等基础能力。

## 3. 里程碑拆分

### M1: 全局布局与任务球基础体验

目标: 先解决横向空间和遮挡问题，为后续宽表格体验打底。

改动文件:

- `01.web/src/views/layout/basicLayout/index.vue`
- `01.web/src/views/layout/basicLayout/index.css`

任务:

- 新增 `siderCollapsed` 状态，使用 `localStorage` 持久化。
- 将 `<Sider width="200">` 改为响应折叠状态的宽度，折叠宽度建议 64px。
- Header 或侧栏顶部新增折叠按钮。
- 折叠时隐藏菜单文字和菜单组标题，保留图标、激活态、徽标。
- 折叠时为菜单项增加 Tooltip。
- 内容区 `padding-left` 随折叠状态切换。
- 调整 `.global-task-ball`:
  - 默认改为 `right: 24px; bottom: 96px`。
  - 使用差异化毛玻璃样式。
  - 区分空闲/运行中状态。
  - 增加最小化状态和本地记忆。

验收:

- 展开宽度 200px，折叠宽度 64px。
- 折叠后内容区实际变宽。
- 刷新后保持折叠状态。
- 仓库页、WeCom 映射页、通知规则页默认不被任务球遮挡。

### M2: repositoryInfo 联想搜索

目标: 将仓库定位从提交式搜索升级为输入即联想。

改动文件:

- `01.web/src/views/repositoryInfo/index.vue`
- 可选: `02.php/app/controller/Svnrep.php`

前端任务:

- 保留现有搜索框能力，新增候选浮层。
- 新增状态:
  - `repoSuggestKeyword`
  - `repoSuggestions`
  - `repoSuggestLoading`
  - `repoSuggestVisible`
  - `repoSuggestActiveIndex`
  - `repoSuggestTimer`
- 新增方法:
  - `handleRepoSuggestInput()`
  - `loadRepoSuggestions()`
  - `rankRepoSuggestions()`
  - `selectRepoSuggestion(item)`
  - `highlightRepoRow(repName)`
  - `closeRepoSuggestions()`
- 输入防抖 250-300ms。
- 优先从当前 `tableDataRep` / `tableDataUserRep` 本地过滤，后续再接服务端。
- 候选展示仓库名、备注、版本、体积或状态。
- Enter/上下键/Esc 支持。

后端可选任务:

- 若需要跨页联想，新增轻量接口或复用 `GetRepList`:
  - 输入: `keyword`, `limit`, `role_scope`
  - 输出: `rep_name`, `rep_note`, `rep_rev`, `rep_size`, `rep_status`
- 不返回当前用户无权访问的仓库。

验收:

- 输入后 300ms 内出现候选。
- 当前页仓库可立即命中。
- 选中候选后能高亮对应行或触发搜索。
- 普通用户候选不越权。

### M3: 权限路径层级展示增强

目标: 基于现有 `modalRepPri` 的 Tree + Table，补足层级、显式权限和主体类型信息。

改动文件:

- `01.web/src/components/modalRepPri.vue`
- 可选: `02.php/app/controller/Svnrep.php`

前端任务:

- 扩展 Tree 节点渲染:
  - 路径名
  - 是否有显式权限的标记
  - 权限摘要标签，如 `RW`、`R`、`-`
  - WeCom 同步组标记，识别 `wecom_` 前缀或后端标识
- 增加权限路径筛选:
  - 全部
  - 仅显式权限
  - 仅当前主体相关
  - 异常/无权限
- 搜索路径时自动展开父节点。
- 右侧权限表增加主体类型视觉区分:
  - SVN 用户
  - SVN 分组
  - SVN 别名
  - WeCom 同步组
- 长路径做中间省略，Tooltip 展示完整路径。

后端可选任务:

- 若现有 Tree 接口无法判断显式权限，扩展 `GetRepTree` / `GetRepTree2` 返回:
  - `hasExplicitPri`
  - `priSummary`
  - `inheritedFrom`
  - `subjectCount`

验收:

- 不影响新增、修改、删除权限。
- 多级目录可清楚看到父子结构。
- 显式权限和继承态可区分。
- WeCom 同步组在权限主体中可识别。

### M4: WeCom 一键诊断后端

目标: 提供稳定、脱敏、结构化的诊断数据。

改动文件:

- `02.php/app/controller/WeComAdmin.php`

新增接口:

- `api.php?c=WeComAdmin&a=RunDiagnostics&t=web`

新增方法建议:

- `RunDiagnostics()`
- `buildDiagnosticCheck($key, $group, $name, $status, $message, $suggestion = '', $details = [])`
- `diagnoseWeComConfig()`
- `diagnosePhpRuntime()`
- `diagnoseFilePermissions()`
- `diagnoseNetwork()`
- `diagnoseWeComApi()`
- `diagnoseDatabaseTables()`
- `diagnoseSyncAndNotification()`
- `calculateDiagnosticSummary($checks)`

状态枚举:

- `passed`
- `warning`
- `failed`
- `unchecked`

检测项:

- 配置完整性: `corp_id`、`agent_id`、`corp_secret`、通知/回调配置。
- PHP 运行环境: `PHP_VERSION`、`curl`、`openssl`、`json`、`PDO`、`pdo_sqlite`。
- 文件权限: `config/wecom.php`、runtime token 缓存目录、logs 目录。
- 网络: DNS 解析 `qyapi.weixin.qq.com`、HTTPS 连接、access token。
- 数据库: WeCom 表存在性、关键表记录数、最近同步时间。
- 同步/通知: 最近同步状态、失败日志摘要、通知规则数量。

安全要求:

- 不返回明文 `corp_secret`、token、access_token。
- API 错误详情只返回错误码、错误摘要、建议动作。
- 文件路径可返回相对路径，避免泄露过多服务器结构。

验收:

- 接口始终返回结构化 `checks`，即使部分检测异常。
- 单项检测异常不会中断整次诊断。
- 覆盖 PRD 要求的五大类问题。

### M5: WeCom 一键诊断前端

目标: 管理员能在页面内完成查看、定位和复制诊断结果。

改动文件:

- `01.web/src/views/wecom/index.vue`
- `01.web/src/views/wecom/components/WecomConfig.vue`
- 可选新增: `01.web/src/views/wecom/components/WecomDiagnostics.vue`

任务:

- 新增 `WecomDiagnostics.vue` 组件，避免 WeCom 首页继续膨胀。
- WeCom 首页头部新增“一键诊断”按钮。
- 配置页连接测试区域增加次级入口。
- 诊断弹窗或抽屉展示:
  - 总体状态
  - 通过/警告/失败/未检测数量
  - 按 group 分组的检查项
  - 每项状态、消息、建议、技术详情折叠区
  - 复制诊断信息
- 点击配置类失败项可切换到配置 Tab。
- 运行中展示步骤进度和 loading 状态。

验收:

- 点击后调用 `RunDiagnostics` 并展示完整结果。
- 失败项有明确建议。
- 复制内容已脱敏。
- 从诊断结果可回到配置项继续处理。

### M6: 联调、回归与打包

任务:

- 前端构建: `cd 01.web && npm run build`
- PHP 语法检查:
  - `php -l 02.php/app/controller/WeComAdmin.php`
  - 如改动 `Svnrep.php`，同步执行 `php -l`
- 手动回归:
  - 管理员仓库列表搜索、分页、排序、同步按钮。
  - 普通用户仓库列表搜索。
  - 权限弹窗新增/修改/删除权限。
  - 侧栏展开/折叠、刷新保持。
  - WeCom 首页状态刷新、诊断运行、诊断失败展示。
  - 后台任务弹窗、任务球点击、任务运行态。

## 4. 实施顺序建议

1. M1 侧栏折叠 + 任务球默认避让。
2. M4 WeCom 诊断后端。
3. M5 WeCom 诊断前端。
4. M2 仓库联想搜索。
5. M3 权限路径层级增强。
6. M6 全量回归。

原因:

- M1 是全局底座，越早完成越能暴露布局兼容问题。
- WeCom 诊断后端可独立开发，前端依赖清晰。
- 仓库搜索和权限弹窗都在仓库域，可最后集中回归权限业务。

## 5. 分支与提交建议

建议分支:

- `codex/ux-wecom-diagnostics-plan`

建议提交拆分:

1. `feat(layout): add collapsible sidebar and task ball refinement`
2. `feat(wecom): add diagnostics backend endpoint`
3. `feat(wecom): add diagnostics panel`
4. `feat(repository): add search-as-you-type suggestions`
5. `feat(permission): enhance permission path hierarchy`
6. `test: update verification notes`

## 6. 主要风险与缓解

- 风险: 全局侧栏样式影响所有页面。
  - 缓解: 先做最小折叠能力，重点回归宽表格和弹窗。
- 风险: 诊断接口中网络请求耗时。
  - 缓解: 每个检测项设置超时，失败转为单项 `failed` 或 `warning`。
- 风险: 权限路径接口无法提供显式权限摘要。
  - 缓解: 一期先在前端基于当前路径权限表和树节点做标记，后续再扩展接口。
- 风险: 联想搜索跨页命中需要服务端支持。
  - 缓解: 一期本地候选 + 选中后复用现有 `GetRepList`，二期新增轻量 suggest 接口。

## 7. 开发检查清单

- [ ] 侧栏折叠状态可保存。
- [ ] 折叠菜单图标、Tooltip、激活态正常。
- [ ] 内容区随侧栏宽度变化。
- [ ] 任务球默认不遮挡分页和表格操作。
- [ ] 任务球视觉与业务按钮区分明显。
- [ ] WeCom 诊断接口返回结构化结果。
- [ ] WeCom 诊断结果脱敏。
- [ ] WeCom 诊断前端支持分组、详情、复制。
- [ ] 仓库搜索输入即联想。
- [ ] 仓库候选选择后能定位或搜索。
- [ ] 权限路径可筛选、可区分显式权限。
- [ ] 权限增删改不回归。
- [ ] 前端构建通过。
- [ ] PHP 语法检查通过。

