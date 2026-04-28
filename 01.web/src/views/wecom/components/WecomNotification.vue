<template>
  <div>
    <!-- 状态图例 -->
    <status-legend type="notification" />
    
    <Alert show-icon style="margin-bottom: 20px;">
      通知管理功能可以配置 SVN 操作的企业微信通知规则。
      <template slot="desc">
        支持多种事件类型的通知，可以按仓库、路径、用户等条件进行精确配置。
      </template>
    </Alert>

    <!-- 通知统计概览 -->
    <Row :gutter="16" style="margin-bottom: 20px;">
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-list" style="color: #2d8cf0;"/>
            <div class="kpi-value">{{ notificationStats.total_rules }}<span class="kpi-suffix">条</span></div>
            <div class="kpi-title">通知规则</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-send" style="color: #19be6b;"/>
            <div class="kpi-value">{{ notificationStats.today_sent }}<span class="kpi-suffix">条</span></div>
            <div class="kpi-title">今日发送</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-checkmark-circle" style="color: #19be6b;"/>
            <div class="kpi-value">{{ notificationStats.success_rate }}<span class="kpi-suffix">%</span></div>
            <div class="kpi-title">成功率</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-close-circle" style="color: #ed4014;"/>
            <div class="kpi-value">{{ notificationStats.failed_count }}<span class="kpi-suffix">条</span></div>
            <div class="kpi-title">失败数</div>
          </div>
        </Card>
      </Col>
    </Row>


    
    <!-- 通知规则管理 -->
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-notifications"/>
        通知规则管理
      </p>
      <div v-if="rulesLoading" style="height:200px;display:flex;align-items:center;justify-content:center;">
        <Spin size="large" />
      </div>
      <div v-else>
      <div slot="extra">
        <ButtonGroup>
          <Button @click="refreshRules">
            <Icon type="ios-refresh"/>
            刷新
          </Button>
          <Button type="primary" @click="showCreateModal">
            <Icon type="ios-add"/>
            新建规则
          </Button>
        </ButtonGroup>
      </div>

      <!-- 搜索过滤 -->
      <Row :gutter="16" style="margin-bottom: 16px;">
        <Col span="6">
          <Input 
            v-model="searchFilters.repo_name" 
            placeholder="仓库名称"
            @on-enter="loadRules"
            clearable
          />
        </Col>
        <Col span="6">
          <Select 
            v-model="searchFilters.event_type" 
            placeholder="事件类型"
            @on-change="loadRules"
            clearable
          >
            <Option value="commit">提交</Option>
            <Option value="update">更新</Option>
            <Option value="delete">删除</Option>
            <Option value="copy">复制</Option>
            <Option value="move">移动</Option>
          </Select>
        </Col>
        <Col span="6">
          <Select 
            v-model="searchFilters.enable" 
            placeholder="状态"
            @on-change="loadRules"
            clearable
          >
            <Option :value="1">启用</Option>
            <Option :value="0">禁用</Option>
          </Select>
        </Col>
        <Col span="6">
          <Button type="primary" @click="loadRules" long>
            <Icon type="ios-search"/>
            搜索
          </Button>
        </Col>
      </Row>

      <!-- 规则列表 -->
      <div v-if="notificationRules.length === 0" class="rule-empty">
        <Icon type="ios-notifications-outline" />
        <strong>暂无通知规则</strong>
        <span>新建一条规则后，SVN 操作会按条件推送到企业微信。</span>
      </div>
      <div v-else class="rule-card-list">
        <Card
          v-for="rule in notificationRules"
          :key="rule.id"
          :bordered="false"
          :dis-hover="true"
          class="rule-card"
          :class="{ 'rule-card-disabled': Number(rule.enable) !== 1 }"
        >
          <div class="rule-card-header">
            <div class="rule-title-area">
              <Badge :status="Number(rule.enable) === 1 ? 'success' : 'default'" />
              <div>
                <div class="rule-name">{{ rule.rule_name || ('规则 #' + rule.id) }}</div>
                <div class="rule-meta">创建于 {{ rule.created_at || '-' }}</div>
              </div>
            </div>
            <div class="rule-actions">
              <i-switch
                v-model="rule.enable"
                @on-change="toggleRuleStatus(rule)"
                :true-value="1"
                :false-value="0"
              />
              <Tooltip content="发送测试" placement="top">
                <Button size="small" type="text" icon="ios-play" @click="testRule(rule)" />
              </Tooltip>
              <Tooltip content="编辑规则" placement="top">
                <Button size="small" type="text" icon="ios-create" @click="editRule(rule)" />
              </Tooltip>
              <Tooltip content="删除规则" placement="top">
                <Button size="small" type="text" icon="ios-trash" @click="deleteRule(rule)" />
              </Tooltip>
            </div>
          </div>
          <div class="rule-card-body">
            <div class="rule-field">
              <span>触发条件</span>
              <div class="event-tags">
                <Tag
                  v-for="eventType in parseEventTypes(rule.event_type)"
                  :key="eventType"
                  :color="getEventTypeColor(eventType)"
                >
                  {{ getEventTypeText(eventType) }}
                </Tag>
              </div>
            </div>
            <div class="rule-field">
              <span>通知范围</span>
              <strong>{{ formatRuleScope(rule) }}</strong>
            </div>
            <div class="rule-field">
              <span>接收人</span>
              <div class="recipient-stack">
                <Avatar
                  v-for="recipient in getRecipientPreview(rule)"
                  :key="recipient.key"
                  size="small"
                  :style="{ backgroundColor: recipient.color }"
                >
                  {{ recipient.label }}
                </Avatar>
                <span v-if="getRecipientCount(rule) === 0" class="muted-text">未指定</span>
                <span v-else class="muted-text">{{ getRecipientText(rule) }}</span>
              </div>
            </div>
          </div>
        </Card>
      </div>
      </div>
    </Card>

    <!-- 创建/编辑规则模态框 -->
    <Modal
      v-model="ruleModalVisible"
      :title="isEditMode ? '编辑通知规则' : '创建通知规则'"
      width="1040"
      @on-ok="saveRule"
      @on-cancel="cancelRuleEdit"
    >
      <div class="rule-edit-layout">
      <Form ref="ruleForm" :model="ruleForm" :rules="ruleRules" :label-width="120" class="rule-edit-form">
        <!-- 基础信息 -->
        <Divider orientation="left">基础信息</Divider>
        
        <FormItem label="规则名称" prop="rule_name">
          <Input v-model="ruleForm.rule_name" placeholder="请输入规则名称"/>
        </FormItem>

        <FormItem label="仓库名称">
          <Checkbox v-model="ruleForm.all_repos" @on-change="onAllReposToggle">全部仓库</Checkbox>
          <Select v-if="!ruleForm.all_repos"
                  v-model="ruleForm.repo_names"
                  multiple
                  filterable
                  remote
                  allow-create
                  placeholder="选择或输入仓库名称"
                  :remote-method="searchRepos"
                  :loading="repoLoading">
            <Option v-for="r in repoOptions" :key="r" :value="r">{{ r }}</Option>
          </Select>
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">支持多选；未勾选“全部仓库”时生效</div>
        </FormItem>

        <FormItem label="路径前缀" prop="path_prefix">
          <Input v-model="ruleForm.path_prefix" placeholder="请输入路径前缀，默认为 /"/>
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            只有匹配此路径前缀的操作才会触发通知
          </div>
        </FormItem>

        <FormItem label="事件类型">
          <Checkbox v-model="eventAllChecked" @on-change="toggleAllEvents">全选</Checkbox>
          <Select v-model="ruleForm.event_types" multiple placeholder="请选择事件类型">
            <Option v-for="ev in eventOptions" :key="ev.value" :value="ev.value">{{ ev.label }}</Option>
          </Select>
        </FormItem>
        
        <FormItem label="通知用户">
          <Select v-model="ruleForm.notify_wecom_userids"
                  multiple
                  filterable
                  remote
                  :remote-method="searchMappedUsers"
                  :loading="userLoading"
                  placeholder="选择已映射的企业微信用户">
            <Option v-for="u in userOptions" :key="u.wecom_userid" :value="u.wecom_userid">
              {{ u.wecom_name }} ({{ u.wecom_userid }})
            </Option>
          </Select>
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">仅可选择已映射的企业微信用户</div>
        </FormItem>

        <FormItem label="通知分组">
          <Select v-model="ruleForm.notify_wecom_deptids"
                  multiple
                  filterable
                  remote
                  :remote-method="searchDepartments"
                  :loading="deptLoading"
                  placeholder="选择企业微信部门（包含子部门与成员）">
            <Option v-for="d in deptOptions" :key="String(d.dept_id)" :value="String(d.dept_id)">
              {{ d.dept_name }}
            </Option>
          </Select>
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">选择的部门会自动包含其所有成员以及子部门成员</div>
        </FormItem>

        <!-- 通知配置 -->
        <Divider orientation="left">通知配置</Divider>

        <FormItem label="Webhook URL" prop="webhook_url">
          <Input 
            v-model="ruleForm.webhook_url" 
            type="textarea" 
            :rows="3"
            placeholder="请输入企业微信群机器人的 Webhook URL"
          />
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            在企业微信群中添加机器人，获取 Webhook URL
          </div>
        </FormItem>

        <FormItem label="消息模板">
          <Input 
            v-model="ruleForm.message_template" 
            type="textarea" 
            :rows="6"
            placeholder="请输入消息模板，留空使用默认模板"
          />
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            支持变量：{repo_name}, {author}, {revision}, {message}, {files}, {timestamp}
          </div>
        </FormItem>

        <FormItem label="启用规则">
          <i-switch v-model="ruleForm.enable" :true-value="1" :false-value="0">
            <span slot="open">启用</span>
            <span slot="close">禁用</span>
          </i-switch>
        </FormItem>
      </Form>
      <div class="phone-preview-panel">
        <div class="phone-shell">
          <div class="phone-speaker"></div>
          <div class="phone-screen">
            <div class="phone-status">
              <span>企业微信</span>
              <Icon type="ios-wifi" />
            </div>
            <div class="chat-title">SVNAdmin 通知</div>
            <div class="message-card">
              <div class="message-app">
                <Avatar size="small" style="background:#2d8cf0">S</Avatar>
                <span>SVNAdmin</span>
              </div>
              <pre>{{ previewMessage }}</pre>
            </div>
            <div class="preview-meta">
              <Tag color="blue" size="small">{{ previewRepoText }}</Tag>
              <Tag color="green" size="small">{{ previewEventText }}</Tag>
            </div>
          </div>
        </div>
      </div>
      </div>
    </Modal>

    <!-- 测试通知模态框 -->
    <Modal
      v-model="testModalVisible"
      title="测试通知"
      @on-ok="sendTestNotification"
    >
      <Alert type="info" style="margin-bottom: 15px;">
        将向配置的 Webhook URL 发送一条测试消息。
      </Alert>
      
      <Form :label-width="80">
        <FormItem label="测试消息">
          <Input 
            v-model="testMessage" 
            type="textarea" 
            :rows="4"
            placeholder="请输入测试消息内容"
          />
        </FormItem>
      </Form>
    </Modal>
  </div>
</template>

<script>
import StatusLegend from '@/components/StatusLegend.vue'

export default {
  name: 'WecomNotification',
  components: {
    StatusLegend
  },
  data() {
    return {
      rulesLoading: false,
      ruleModalVisible: false,
      testModalVisible: false,
      isEditMode: false,
      currentRule: null,
      testMessage: '',

      // 搜索过滤条件
      searchFilters: {
        repo_name: '',
        event_type: '',
        enable: ''
      },

      // 通知统计
      notificationStats: {
        total_rules: 0,
        today_sent: 0,
        success_rate: 0,
        failed_count: 0
      },

      // 通知规则列表
      notificationRules: [],

      // 规则表单
      ruleForm: {
        rule_name: '',
        all_repos: true,
        repo_names: [],
        path_prefix: '/',
        event_types: [],
        webhook_url: '',
        message_template: '',
        notify_wecom_userids: [],
        notify_wecom_deptids: [],
        enable: 1
      },

      // 表单验证规则
      ruleRules: {
        rule_name: [
          { required: true, message: '请输入规则名称', trigger: 'blur' }
        ],
        // 允许为空（勾选"全部仓库"时生效）；不再注入自定义校验避免运行期报错
        repo_names: [],
        // 事件类型必须至少选择一个
        event_types: [
          { required: true, type: 'array', min: 1, message: '请至少选择一个事件类型', trigger: 'change' }
        ],
        // Webhook URL 非必填；若填写则做简单格式校验
        webhook_url: [
          { validator: (rule, value, callback) => {
              if(!value){ callback(); return }
              const ok = /^https:\/\/qyapi\.weixin\.qq\.com\/cgi-bin\/webhook\/send\?key=/.test(value)
              ok ? callback() : callback(new Error('Webhook URL 格式不正确'))
            }, trigger: 'blur' }
        ]
      },

      // 选择项/远程数据
      repoOptions: [],
      repoLoading: false,
      eventOptions: [
        { value: 'commit', label: '提交 (commit)' },
        { value: 'update', label: '更新 (update)' },
        { value: 'delete', label: '删除 (delete)' },
        { value: 'copy', label: '复制 (copy)' },
        { value: 'move', label: '移动 (move)' }
      ],
      eventAllChecked: true,
      userOptions: [],
      userLoading: false,
      // 部门/分组选择
      deptOptions: [],
      deptLoading: false,

      // 缓存（避免频繁请求）
      _allRepoNamesCache: [],
      _allMappedUsersCache: [],
      _allDeptsCache: [],
      _deptChildrenMap: {},

      // 表格列定义
      ruleColumns: [
        {
          title: '规则名称',
          key: 'rule_name',
          width: 150
        },
        {
          title: '仓库',
          key: 'repo_name',
          width: 120
        },
        {
          title: '路径',
          key: 'path_prefix',
          width: 100
        },
        {
          title: '事件类型',
          slot: 'event_type',
          width: 100
        },
        {
          title: '状态',
          slot: 'enable',
          width: 80
        },
        {
          title: '创建时间',
          key: 'created_at',
          width: 160
        },
        {
          title: '操作',
          slot: 'actions',
          width: 120,
          align: 'center'
        }
      ]
    }
  },

  computed: {
    previewRepoText() {
      if (this.ruleForm.all_repos) {
        return '全部仓库'
      }
      const repos = this.ruleForm.repo_names || []
      return repos.length ? repos.join(', ') : '未选择仓库'
    },
    previewEventText() {
      const events = this.ruleForm.event_types || []
      if (!events.length) {
        return '未选择事件'
      }
      return events.map((item) => this.getEventTypeText(item)).join(' / ')
    },
    previewMessage() {
      const template = this.ruleForm.message_template || [
        'SVN 提交通知',
        '仓库：{repo_name}',
        '作者：{author}',
        '版本：r{revision}',
        '路径：{files}',
        '备注：{message}',
        '时间：{timestamp}'
      ].join('\n')
      return template
        .replace(/\{repo_name\}/g, this.previewRepoText)
        .replace(/\{author\}/g, 'zhangsan')
        .replace(/\{revision\}/g, '1286')
        .replace(/\{message\}/g, '优化企业微信通知规则')
        .replace(/\{files\}/g, this.ruleForm.path_prefix || '/trunk')
        .replace(/\{timestamp\}/g, new Date().toLocaleString())
    }
  },

  mounted() {
    this.loadStats()
    this.loadRules()
  },

  methods: {
    // 切换“全部仓库”
    onAllReposToggle(val){
      if(val){
        this.ruleForm.repo_names = []
      }
    },
    // 事件类型全选/全不选
    toggleAllEvents(checked){
      if(checked){
        this.ruleForm.event_types = this.eventOptions.map(e=>e.value)
      }else{
        this.ruleForm.event_types = []
      }
    },
    // 远程检索仓库名：优先使用缓存；否则从后台获取仓库列表再过滤
    async searchRepos(query){
      const q = (query||'').toLowerCase()
      this.repoLoading = true
      try{
        if(!(Array.isArray(this._allRepoNamesCache))){
          this._allRepoNamesCache = []
        }
        if(this._allRepoNamesCache.length === 0){
          // 1) 尝试从数据库接口取
          let rows = []
          try{
            const resp = await this.$axios.post('api.php?c=Svnrep&a=GetRepList&t=web',{page:false, sortName:'rep_name', sortType:'asc'})
            if(resp && resp.data && resp.data.status === 1){
              const payload = resp.data.data || {}
              rows = Array.isArray(payload.data) ? payload.data : []
            }
          }catch(err){
            // 忽略，走同步兜底
          }
          // 2) 若没有数据，触发一次同步兜底（从物理仓库/authz建模）
          if(rows.length === 0){
            try{
              const resp2 = await this.$axios.post('api.php?c=Svnrep&a=GetRepList&t=web',{page:false, sync:true, sync_size:false, sync_rev:false, sortName:'rep_name', sortType:'asc'})
              if(resp2 && resp2.data && resp2.data.status === 1){
                const payload2 = resp2.data.data || {}
                rows = Array.isArray(payload2.data) ? payload2.data : []
              }
            }catch(err2){
              // 仍失败则给空数组
              rows = []
            }
          }
          this._allRepoNamesCache = rows.map(r=>r && r.rep_name).filter(Boolean)
        }
        const base = Array.isArray(this._allRepoNamesCache) ? this._allRepoNamesCache : []
        this.repoOptions = q ? base.filter(n=>String(n).toLowerCase().indexOf(q)>-1) : base.slice(0,50)
      }catch(e){
        console.error('加载仓库列表失败',e)
        this.repoOptions = []
      }finally{
        this.repoLoading = false
      }
    },
    // 远程检索“已映射的企业微信用户”
    async searchMappedUsers(query){
      const q = (query||'').toLowerCase()
      this.userLoading = true
      try{
        if(!(Array.isArray(this._allMappedUsersCache)) || this._allMappedUsersCache.length === 0){
          const resp = await this.$axios.post('api.php?c=WeComAdmin&a=GetMapping&t=web', {})
          if(resp.data && resp.data.status === 1){
            const users = (resp.data.data && resp.data.data.users) || []
            // 只取有 wecom_userid 的（已映射）
            this._allMappedUsersCache = users
              .filter(u=>u.wecom_userid)
              .map(u=>({
                wecom_userid: u.wecom_userid,
                wecom_name: u.wecom_name || u.svn_user_name || u.wecom_userid
              }))
          }else{
            this._allMappedUsersCache = []
          }
        }
        const base = this._allMappedUsersCache
        this.userOptions = q ? base.filter(u=>
          String(u.wecom_userid).toLowerCase().indexOf(q)>-1 ||
          String(u.wecom_name||'').toLowerCase().indexOf(q)>-1
        ).slice(0,50) : base.slice(0,50)
      }catch(e){
        console.error('查询映射用户失败',e)
        this.userOptions = []
      }finally{
        this.userLoading = false
      }
    },

    // 远程检索部门/分组
    async searchDepartments(query){
      const q = (query||'').toLowerCase()
      this.deptLoading = true
      try{
        if(!(Array.isArray(this._allDeptsCache)) || this._allDeptsCache.length===0){
          const resp = await this.$axios.post('api.php?c=WeComAdmin&a=GetMapping&t=web', {})
          if(resp && resp.data && resp.data.status === 1){
            const depts = (resp.data.data && resp.data.data.departments) || []
            this._allDeptsCache = depts.map(d=>({dept_id:d.wecom_department_id, dept_name: d.wecom_name, parent_id: d.wecom_parent_id}))
            // 构建父子映射，供后续扩展（包含子部门成员）
            const map = {}
            this._allDeptsCache.forEach(d=>{
              if(!map[d.parent_id]) map[d.parent_id] = []
              map[d.parent_id].push(d.dept_id)
            })
            this._deptChildrenMap = map
          }else{
            this._allDeptsCache = []
          }
        }
        const base = this._allDeptsCache
        this.deptOptions = q ? base.filter(d=> String(d.dept_name||'').toLowerCase().indexOf(q)>-1) : base.slice(0,100)
      }catch(e){
        console.error('加载部门列表失败',e)
        this.deptOptions = []
      }finally{
        this.deptLoading = false
      }
    },
    /**
     * 安全钳制百分比 [0,100]
     */
    clampPercent(v){
      const n = Number(v)
      if(!isFinite(n)) return 0
      if(n < 0) return 0
      if(n > 100) return 100
      return Math.round(n)
    },
    /** 非负整数 */
    clampCount(v){
      const n = parseInt(v,10)
      return isFinite(n)&&n>0?n:0
    },
    /**
     * 加载通知统计
     */
    async loadStats() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetNotificationStats&t=web', {})
        if (response.data.status === 1) {
          this.notificationStats = {
            total_rules: this.clampCount(response.data.data.total_count),
            today_sent: this.clampCount(response.data.data.today_sent),
            success_rate: this.clampPercent(response.data.data.success_rate),
            failed_count: this.clampCount(response.data.data.failed_count)
          }
        }
      } catch (error) {
        console.error('加载通知统计失败:', error)
      }
    },

    /**
     * 加载通知规则
     */
    async loadRules() {
      this.rulesLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetNotificationRules&t=web', this.searchFilters)
        
        if (response.data.status === 1) {
          this.notificationRules = response.data.data.map(rule => ({
            ...rule,
            rule_name: rule.rule_name || `规则-${rule.id}`
          }))
        } else {
          this.$Message.error('加载通知规则失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('加载通知规则失败:', error)
        this.$Message.error('加载通知规则失败')
      } finally {
        this.rulesLoading = false
      }
    },

    /**
     * 刷新规则列表
     */
    refreshRules() {
      this.loadStats()
      this.loadRules()
    },

    /**
     * 显示创建规则模态框
     */
    showCreateModal() {
      this.isEditMode = false
      this.currentRule = null
      this.resetRuleForm()
      this.ruleModalVisible = true
      this.searchRepos('')
      this.searchMappedUsers('')
      this.searchDepartments('')
    },

    /**
     * 编辑规则
     */
    editRule(rule) {
      this.isEditMode = true
      this.currentRule = rule
      this.ruleForm = {
        rule_name: rule.rule_name || '',
        all_repos: (rule.repo_name||'*') === '*',
        repo_names: (rule.repo_name && rule.repo_name !== '*') ? String(rule.repo_name).split(',') : [],
        path_prefix: rule.path_prefix || '/',
        event_types: rule.event_type ? String(rule.event_type).split(',') : [],
        webhook_url: rule.webhook_url || '',
        message_template: rule.message_template || '',
        notify_wecom_userids: rule.notify_wecom_userids ? String(rule.notify_wecom_userids).split(',') : [],
        notify_wecom_deptids: rule.notify_wecom_deptids ? String(rule.notify_wecom_deptids).split(',') : [],
        enable: rule.enable
      }
      this.ruleModalVisible = true
      this.searchRepos('')
      this.searchMappedUsers('')
      this.searchDepartments('')
    },

    /**
     * 保存规则
     */
    async saveRule() {
      this.$refs.ruleForm.validate(async (valid) => {
        if (!valid) {
          this.$Message.error('请检查表单填写')
          return
        }

        try {
          let response
          if (this.isEditMode) {
            // 编辑模式：后端支持多事件类型，使用完整的事件类型列表
            const editData = this.serializeRule()
            
            console.log('编辑规则数据:', editData)
            // 发送 JSON 格式的数据，包含 rule_id 和 rule_data
            const jsonPayload = { 
              rule_id: this.currentRule.id,
              rule_data: editData 
            }
            console.log('发送编辑JSON数据:', jsonPayload)
            response = await this.$axios.post('api.php?c=WeComAdmin&a=UpdateNotificationRule&t=web', jsonPayload)
            console.log('编辑规则响应:', response.data)
          } else {
            // 创建新规则 - 支持多事件类型合并为一条规则
            const eventTypes = (this.ruleForm.event_types||[])
            const payload = {
              rule_name: this.ruleForm.rule_name,
              repo_name: this.ruleForm.all_repos ? '*' : (this.ruleForm.repo_names||[]).join(','),
              path_prefix: this.ruleForm.path_prefix || '/',
              event_type: eventTypes.join(','), // 多个事件类型用逗号分隔
              webhook_url: this.ruleForm.webhook_url || '',
              message_template: this.ruleForm.message_template || '',
              notify_wecom_userids: (this.ruleForm.notify_wecom_userids||[]).join(','),
              notify_wecom_deptids: (this.ruleForm.notify_wecom_deptids||[]).join(','),
              enable: this.ruleForm.enable !== undefined ? this.ruleForm.enable : 1
            }
            console.log('创建规则数据:', payload)
            // 直接发送 JSON 格式的数据，包装在 rule_data 字段中
            const jsonPayload = { rule_data: payload }
            console.log('发送JSON数据:', jsonPayload)
            response = await this.$axios.post('api.php?c=WeComAdmin&a=CreateNotificationRule&t=web', jsonPayload)
            console.log('创建规则响应:', response.data)
          }

          if (response && response.data && response.data.status === 1) {
            this.$Message.success(this.isEditMode ? '规则更新成功' : '规则创建成功')
            this.ruleModalVisible = false
            this.refreshRules()
            this.$emit('rules-updated')
          } else {
            const errorMsg = (response && response.data && response.data.message) || '未知错误'
            console.error('保存规则失败 - 响应:', response)
            this.$Message.error('保存规则失败: ' + errorMsg)
            // 保存失败时不关闭模态框，让用户可以修改后重试
          }
        } catch (error) {
          console.error('保存规则失败 - 异常:', error)
          if (error.response) {
            console.error('错误响应:', error.response.data)
            const errorMsg = error.response.data.message || error.response.statusText || '服务器错误'
            this.$Message.error('保存规则失败: ' + errorMsg)
          } else {
            this.$Message.error('保存规则失败: 网络错误或服务器无响应')
          }
          // 异常情况下也不关闭模态框，让用户可以修改后重试
        }
      })
    },

    serializeRule(){
      return {
        rule_name: this.ruleForm.rule_name,
        repo_name: this.ruleForm.all_repos ? '*' : (this.ruleForm.repo_names||[]).join(','),
        path_prefix: this.ruleForm.path_prefix,
        event_type: (this.ruleForm.event_types||[]).join(','),
        webhook_url: this.ruleForm.webhook_url,
        message_template: this.ruleForm.message_template,
        notify_wecom_userids: (this.ruleForm.notify_wecom_userids||[]).join(','),
        notify_wecom_deptids: (this.ruleForm.notify_wecom_deptids||[]).join(','),
        enable: this.ruleForm.enable
      }
    },

    /**
     * 取消规则编辑
     */
    cancelRuleEdit() {
      this.resetRuleForm()
    },

    /**
     * 重置规则表单
     */
    resetRuleForm() {
      this.ruleForm = {
        rule_name: '',
        all_repos: true,
        repo_names: [],
        path_prefix: '/',
        event_types: this.eventOptions.map(e=>e.value),
        webhook_url: '',
        message_template: '',
        notify_wecom_userids: [],
        notify_wecom_deptids: [],
        enable: 1
      }
      this.eventAllChecked = true
      this.$refs.ruleForm && this.$refs.ruleForm.resetFields()
    },

    /**
     * 切换规则状态
     */
    async toggleRuleStatus(rule) {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=UpdateNotificationRule&t=web', {
          rule_id: rule.id,
          rule_data: { enable: rule.enable }
        })

        if (response.data.status === 1) {
          this.$Message.success('规则状态更新成功')
        } else {
          // 回滚状态
          rule.enable = rule.enable === 1 ? 0 : 1
          this.$Message.error('规则状态更新失败: ' + response.data.message)
        }
      } catch (error) {
        // 回滚状态
        rule.enable = rule.enable === 1 ? 0 : 1
        console.error('更新规则状态失败:', error)
        this.$Message.error('更新规则状态失败')
      }
    },

    /**
     * 测试规则
     */
    testRule(rule) {
      this.currentRule = rule
      this.testMessage = `这是一条来自 SVNAdmin 的测试通知。\n\n规则: ${rule.rule_name || rule.id}\n仓库: ${rule.repo_name}\n事件: ${this.getEventTypeText(rule.event_type)}\n时间: ${new Date().toLocaleString()}`
      this.testModalVisible = true
    },

    /**
     * 发送测试通知
     */
    async sendTestNotification() {
      if (!this.testMessage.trim()) {
        this.$Message.error('请输入测试消息')
        return
      }

      try {
        const payload = {
          webhook_url: this.currentRule.webhook_url || '',
          message: this.testMessage,
          notify_wecom_userids: (this.currentRule.notify_wecom_userids || []).join ? 
            (this.currentRule.notify_wecom_userids || []).join(',') : 
            (this.currentRule.notify_wecom_userids || ''),
          notify_wecom_deptids: (this.currentRule.notify_wecom_deptids || []).join ? 
            (this.currentRule.notify_wecom_deptids || []).join(',') : 
            (this.currentRule.notify_wecom_deptids || '')
        }
        
        console.log('发送测试通知数据:', payload)
        
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=TestNotification&t=web', payload)

        if (response.data.status === 1) {
          this.$Message.success('测试通知发送成功')
          this.testModalVisible = false
        } else {
          this.$Message.error('测试通知发送失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('发送测试通知失败:', error)
        this.$Message.error('发送测试通知失败')
      }
    },

    /**
     * 删除规则
     */
    deleteRule(rule) {
      this.$Modal.confirm({
        title: '确认删除',
        content: `确定要删除规则 "${rule.rule_name || rule.id}" 吗？`,
        onOk: async () => {
          try {
            const response = await this.$axios.post('api.php?c=WeComAdmin&a=DeleteNotificationRule&t=web', {
              rule_id: rule.id
            })

            if (response.data.status === 1) {
              this.$Message.success('规则删除成功')
              this.loadRules()
              this.$emit('rules-updated')
            } else {
              this.$Message.error('规则删除失败: ' + response.data.message)
            }
          } catch (error) {
            console.error('删除规则失败:', error)
            this.$Message.error('删除规则失败')
          }
        }
      })
    },

    splitValue(value) {
      if (!value) {
        return []
      }
      if (Array.isArray(value)) {
        return value.filter(Boolean)
      }
      return String(value).split(',').map((item) => item.trim()).filter(Boolean)
    },

    formatRuleScope(rule) {
      const repo = !rule.repo_name || rule.repo_name === '*' ? '全部仓库' : rule.repo_name
      const path = rule.path_prefix || '/'
      return repo + ' · ' + path
    },

    getRecipientCount(rule) {
      return this.splitValue(rule.notify_wecom_userids).length + this.splitValue(rule.notify_wecom_deptids).length
    },

    getRecipientText(rule) {
      const users = this.splitValue(rule.notify_wecom_userids)
      const depts = this.splitValue(rule.notify_wecom_deptids)
      const parts = []
      if (users.length) {
        parts.push(users.length + ' 个用户')
      }
      if (depts.length) {
        parts.push(depts.length + ' 个部门')
      }
      return parts.join('，')
    },

    getRecipientPreview(rule) {
      const users = this.splitValue(rule.notify_wecom_userids)
      const depts = this.splitValue(rule.notify_wecom_deptids)
      const items = users.map((item, index) => ({
        key: 'user-' + item + '-' + index,
        label: String(item).charAt(0).toUpperCase() || 'U',
        color: '#2d8cf0'
      }))
      depts.forEach((item, index) => {
        items.push({
          key: 'dept-' + item + '-' + index,
          label: '部',
          color: '#19be6b'
        })
      })
      return items.slice(0, 4)
    },

    /**
     * 获取事件类型颜色
     */
    getEventTypeColor(eventType) {
      const colorMap = {
        'commit': 'blue',
        'update': 'green',
        'delete': 'red',
        'copy': 'orange',
        'move': 'purple'
      }
      return colorMap[eventType] || 'default'
    },

    /**
     * 获取事件类型文本
     */
    getEventTypeText(eventType) {
      const textMap = {
        'commit': '提交',
        'update': '更新',
        'delete': '删除',
        'copy': '复制',
        'move': '移动'
      }
      return textMap[eventType] || eventType
    },

    /**
     * 解析事件类型字符串
     */
    parseEventTypes(eventTypeString) {
      if (!eventTypeString) return []
      return eventTypeString.split(',').map(type => type.trim()).filter(type => type)
    }
  }
}
</script>

<style scoped>
.ivu-statistic {
  text-align: center;
}

.kpi{
  text-align:center;
}
.kpi-value{
  font-size:22px;
  font-weight:600;
  margin-top:6px;
}
.kpi-title{
  color:#80848f;
  margin-top:2px;
}
.kpi-suffix{font-size:12px;color:#80848f;margin-left:4px}

.ivu-form-item {
  margin-bottom: 24px;
}

.ivu-divider-horizontal.ivu-divider-with-text-left {
  margin: 24px 0 16px 0;
}

.rule-card-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.rule-card {
  border: 1px solid #e8eaec;
  border-radius: 8px;
  background: #fff;
  transition: border-color .2s, box-shadow .2s;
}

.rule-card:hover {
  border-color: #2d8cf0;
  box-shadow: 0 8px 22px rgba(45, 140, 240, .10);
}

.rule-card-disabled {
  opacity: .66;
}

.rule-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.rule-title-area,
.rule-actions,
.recipient-stack,
.message-app,
.preview-meta {
  display: flex;
  align-items: center;
}

.rule-title-area {
  gap: 8px;
  min-width: 0;
}

.rule-name {
  color: #17233d;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rule-meta,
.muted-text {
  color: #808695;
  font-size: 12px;
}

.rule-actions {
  gap: 4px;
  flex-shrink: 0;
}

.rule-card-body {
  display: grid;
  gap: 12px;
}

.rule-field {
  display: grid;
  gap: 6px;
}

.rule-field > span {
  color: #808695;
  font-size: 12px;
}

.event-tags {
  min-height: 24px;
}

.recipient-stack {
  gap: 6px;
  flex-wrap: wrap;
}

.rule-empty {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #808695;
  border: 1px dashed #dcdee2;
  border-radius: 8px;
  background: #f8f9fb;
}

.rule-empty .ivu-icon {
  font-size: 40px;
  color: #2d8cf0;
}

.rule-empty strong {
  color: #17233d;
}

.rule-edit-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 24px;
  align-items: flex-start;
}

.rule-edit-form {
  min-width: 0;
}

.phone-preview-panel {
  position: sticky;
  top: 0;
}

.phone-shell {
  width: 280px;
  margin: 0 auto;
  padding: 12px;
  border-radius: 28px;
  background: #17233d;
  box-shadow: 0 20px 45px rgba(23, 35, 61, .22);
}

.phone-speaker {
  width: 54px;
  height: 5px;
  margin: 0 auto 10px;
  border-radius: 999px;
  background: rgba(255,255,255,.35);
}

.phone-screen {
  min-height: 520px;
  padding: 12px;
  border-radius: 20px;
  background: #eef2f6;
}

.phone-status {
  display: flex;
  justify-content: space-between;
  color: #515a6e;
  font-size: 12px;
}

.chat-title {
  margin: 14px 0;
  text-align: center;
  color: #17233d;
  font-weight: 600;
}

.message-card {
  padding: 12px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 2px 10px rgba(0,0,0,.05);
}

.message-app {
  gap: 8px;
  margin-bottom: 10px;
  color: #17233d;
  font-weight: 600;
}

.message-card pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  color: #515a6e;
  font-family: inherit;
  line-height: 1.55;
}

.preview-meta {
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
}

@media (max-width: 960px) {
  .rule-edit-layout {
    grid-template-columns: 1fr;
  }

  .phone-preview-panel {
    position: static;
  }
}
</style>
