<template>  <div>
    <!-- 状态图例 -->
    <status-legend type="mapping" />
    
    <Alert show-icon style="margin-bottom: 20px;">
      用户映射管理用于查看和处理企业微信用户与 SVN 用户的映射关系。
      <template slot="desc">
        可以查看映射状态、手动创建映射关系，以及处理匹配失败的用户。
      </template>
    </Alert>

    <!-- 映射统计概览 -->
    <Row :gutter="16" style="margin-bottom: 20px;">
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-people" style="color: #2d8cf0;"/>
            <div class="kpi-value">{{ mappingStats.wecom_users_total }}<span class="kpi-suffix">个</span></div>
            <div class="kpi-title">企业微信用户</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-checkmark-circle" style="color: #19be6b;"/>
            <div class="kpi-value">{{ mappingStats.mapped_users }}<span class="kpi-suffix">个</span></div>
            <div class="kpi-title">已映射用户</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-close-circle" style="color: #ed4014;"/>
            <div class="kpi-value">{{ mappingStats.unmapped_users }}<span class="kpi-suffix">个</span></div>
            <div class="kpi-title">未映射用户</div>
          </div>
        </Card>
      </Col>
      <Col span="6">
        <Card :bordered="false">
          <div class="kpi">
            <Icon type="ios-analytics" style="color: #ff9900;"/>
            <div class="kpi-value">{{ mappingStats.mapping_rate }}<span class="kpi-suffix">%</span></div>
            <div class="kpi-title">映射率</div>
          </div>
        </Card>
      </Col>
    </Row>

    <!-- 操作工具栏 -->
    <Card :bordered="false" style="margin-bottom: 20px;">
      <Row :gutter="16">
        <Col span="12">
          <ButtonGroup>
            <Button @click="refreshMappings">
              <Icon type="ios-refresh"/>
              刷新数据
            </Button>
            <Button @click="showBatchMappingModal">
              <Icon type="ios-people"/>
              批量映射
            </Button>
            <Button @click="exportMappings">
              <Icon type="ios-download"/>
              导出映射
            </Button>
            <Button @click="showImportModal">
              <Icon type="ios-cloud-upload"/>
              导入映射
            </Button>
          </ButtonGroup>
        </Col>
        <Col span="12" style="text-align: right;">
          <ButtonGroup>
            <Button @click="autoMatchUsers" type="success" :loading="autoMatchLoading">
              <Icon type="ios-sync"/>
              自动匹配
            </Button>
            <Button @click="showCreateUserModal" type="primary">
              <Icon type="ios-person-add"/>
              创建 SVN 用户
            </Button>
          </ButtonGroup>
        </Col>
      </Row>
    </Card>

    <!-- 映射管理 -->

    
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-people"/>
        用户映射管理
      </p>

      <!-- 搜索过滤 -->
      <Row :gutter="16" style="margin-bottom: 16px;">
        <Col span="6">
          <Input 
            v-model="searchFilters.wecom_name" 
            placeholder="企业微信姓名"
            @on-enter="loadMappings"
            clearable
          />
        </Col>
        <Col span="6">
          <Input 
            v-model="searchFilters.wecom_userid" 
            placeholder="企业微信用户ID"
            @on-enter="loadMappings"
            clearable
          />
        </Col>
        <Col span="6">
          <Select 
            v-model="searchFilters.mapping_status" 
            placeholder="映射状态"
            @on-change="loadMappings"
            clearable
          >
            <Option value="mapped">已映射</Option>
            <Option value="unmapped">未映射</Option>
            <Option value="conflict">冲突</Option>
          </Select>
        </Col>
        <Col span="6">
          <Button type="primary" @click="loadMappings" long>
            <Icon type="ios-search"/>
            搜索
          </Button>
        </Col>
      </Row>

      <!-- 映射列表 -->
      <Table 
        :columns="mappingColumns" 
        :data="userMappings" 
        :loading="mappingsLoading"
        stripe
        :row-selection="{
          selectedRowKeys: selectedRowKeys,
          onChange: onSelectChange
        }"
      >
        <template slot-scope="{ row }" slot="wecom_info">
          <div>
            <strong>{{ row.wecom_name }}</strong>
            <br/>
            <span style="color: #80848f; font-size: 12px;">{{ row.wecom_userid }}</span>
            <br/>
            <span style="color: #80848f; font-size: 12px;">
              <Icon type="ios-mail" v-if="row.wecom_email"/>
              {{ row.wecom_email || '无邮箱' }}
            </span>
          </div>
        </template>

        <template slot-scope="{ row }" slot="svn_info">
          <div v-if="row.svn_user_name">
            <strong>{{ row.svn_user_name }}</strong>
            <br/>
            <span style="color: #80848f; font-size: 12px;">{{ row.svn_user_note || '无备注' }}</span>
          </div>
          <div v-else style="color: #c5c8ce;">
            <Icon type="ios-close-circle"/>
            未映射
          </div>
        </template>

        <template slot-scope="{ row }" slot="departments">
          <div>
            <Tag 
              v-for="dept in row.departments" 
              :key="dept.id"
              size="small"
              style="margin: 2px;"
            >
              {{ dept.name }}
            </Tag>
          </div>
        </template>

        <template slot-scope="{ row }" slot="mapping_status">
          <Badge 
            :status="getMappingStatusBadge(row.mapping_status)" 
            :text="getMappingStatusText(row.mapping_status)"
          />
        </template>
        
        <template slot-scope="{ row }" slot="actions">
          <ButtonGroup size="small">
            <Button 
              v-if="!row.svn_user_name" 
              @click="showMappingModal(row)" 
              type="primary"
              title="创建映射"
            >
              <Icon type="ios-link"/>
            </Button>
            <Button 
              v-else 
              @click="showMappingModal(row)" 
              title="编辑映射"
            >
              <Icon type="ios-create"/>
            </Button>
            <Button 
              v-if="row.svn_user_name" 
              @click="removeMappingConfirm(row)" 
              type="error" 
              title="移除映射"
            >
              <Icon type="ios-trash"/>
            </Button>
          </ButtonGroup>
        </template>
      </Table>

      <!-- 分页 -->
      <div style="margin-top: 16px; text-align: right;">
        <Page 
          :total="mappingsPagination.total" 
          :current="mappingsPagination.current_page"
          :page-size="mappingsPagination.page_size"
          @on-change="handlePageChange"
          @on-page-size-change="handlePageSizeChange"
          show-sizer
          show-elevator
          show-total
        />
      </div>
    </Card>

    <!-- 创建/编辑映射模态框 -->
    <Modal
      v-model="mappingModalVisible"
      :title="currentUser ? (currentUser.svn_user_name ? '编辑用户映射' : '创建用户映射') : '用户映射'"
      width="700"
      @on-ok="saveMappingRelation"
      @on-cancel="cancelMappingEdit"
    >
      <div v-if="currentUser">
        <!-- 企业微信用户信息 -->
        <Divider orientation="left">企业微信用户信息</Divider>
        <Row :gutter="16">
          <Col span="12">
            <p><strong>姓名:</strong> {{ currentUser.wecom_name }}</p>
            <p><strong>用户ID:</strong> {{ currentUser.wecom_userid }}</p>
          </Col>
          <Col span="12">
            <p><strong>邮箱:</strong> {{ currentUser.wecom_email || '无' }}</p>
            <p><strong>手机:</strong> {{ currentUser.wecom_mobile || '无' }}</p>
          </Col>
        </Row>

        <!-- SVN 用户映射 -->
        <Divider orientation="left">SVN 用户映射</Divider>
        <Form ref="mappingForm" :model="mappingForm" :rules="mappingRules" :label-width="100">
          <FormItem label="映射方式">
            <RadioGroup v-model="mappingType" @on-change="handleMappingTypeChange">
              <Radio label="existing">映射到现有用户</Radio>
              <Radio label="create">创建新用户</Radio>
            </RadioGroup>
          </FormItem>

          <FormItem v-if="mappingType === 'existing'" label="选择用户" prop="svn_user_id">
            <Select 
              v-model="mappingForm.svn_user_id" 
              placeholder="请选择 SVN 用户"
              filterable
              remote
              :remote-method="searchSvnUsers"
              :loading="svnUsersLoading"
            >
              <Option 
                v-for="user in svnUsers" 
                :key="user.svn_user_id" 
                :value="user.svn_user_id"
              >
                {{ user.svn_user_name }} ({{ user.svn_user_note || '无备注' }})
              </Option>
            </Select>
          </FormItem>

          <div v-if="mappingType === 'create'">
            <FormItem label="用户名" prop="new_username">
              <Input v-model="mappingForm.new_username" placeholder="请输入 SVN 用户名"/>
            </FormItem>
            <FormItem label="密码" prop="new_password">
              <Input v-model="mappingForm.new_password" type="password" placeholder="请输入密码"/>
            </FormItem>
            <FormItem label="备注">
              <Input v-model="mappingForm.new_note" placeholder="请输入用户备注"/>
            </FormItem>
          </div>
        </Form>
      </div>
    </Modal>

    <!-- 批量映射模态框 -->
    <Modal
      v-model="batchMappingModalVisible"
      title="批量用户映射"
      width="800"
      @on-ok="executeBatchMapping"
    >
      <Alert type="info" style="margin-bottom: 15px;">
        将对选中的未映射用户执行自动匹配，匹配规则：邮箱 > 手机号 > 用户ID
      </Alert>
      
      <p>已选择 <strong>{{ selectedRowKeys.length }}</strong> 个用户进行批量映射</p>
      
      <div style="max-height: 300px; overflow-y: auto;">
        <ul>
          <li v-for="user in selectedUsers" :key="user.wecom_userid">
            {{ user.wecom_name }} ({{ user.wecom_userid }})
          </li>
        </ul>
      </div>
    </Modal>

    <!-- 创建 SVN 用户模态框 -->
    <Modal
      v-model="createUserModalVisible"
      title="批量创建 SVN 用户"
      @on-ok="createSvnUsersForUnmapped"
    >
      <Alert type="warning" style="margin-bottom: 15px;">
        将为所有未映射的企业微信用户自动创建对应的 SVN 用户账号。
      </Alert>
      
      <Form :label-width="120">
        <FormItem label="用户名前缀">
          <Input v-model="createUserConfig.username_prefix" placeholder="可选的用户名前缀"/>
        </FormItem>
        <FormItem label="默认密码">
          <Input v-model="createUserConfig.default_password" placeholder="新用户的默认密码"/>
        </FormItem>
        <FormItem label="自动添加到组">
          <i-switch v-model="createUserConfig.auto_add_to_groups">
            <span slot="open">是</span>
            <span slot="close">否</span>
          </i-switch>
        </FormItem>
      </Form>
      
      <p>将为 <strong>{{ mappingStats.unmapped_users }}</strong> 个未映射用户创建 SVN 账号</p>
    </Modal>
  </div>
</template>

<script>
import StatusLegend from '@/components/StatusLegend.vue'

export default {
  name: 'WecomMapping',
  components: {
    StatusLegend
  },
  data() {
    return {
      mappingsLoading: false,
      mappingModalVisible: false,
      batchMappingModalVisible: false,
      createUserModalVisible: false,
      autoMatchLoading: false,
      svnUsersLoading: false,
      currentUser: null,
      mappingType: 'existing',
      selectedRowKeys: [],

      // 搜索过滤条件
      searchFilters: {
        wecom_name: '',
        wecom_userid: '',
        mapping_status: ''
      },

      // 映射统计
      mappingStats: {
        wecom_users_total: 0,
        mapped_users: 0,
        unmapped_users: 0,
        mapping_rate: 0
      },

      // 用户映射列表
      userMappings: [],
      mappingsPagination: {
        current_page: 1,
        page_size: 20,
        total: 0
      },

      // 部门映射信息
      departmentMap: {},

      // SVN 用户列表
      svnUsers: [],

      // 映射表单
      mappingForm: {
        svn_user_id: null,
        new_username: '',
        new_password: '',
        new_note: ''
      },

      // 创建用户配置
      createUserConfig: {
        username_prefix: '',
        default_password: '123456',
        auto_add_to_groups: true
      },

      // 表单验证规则
      mappingRules: {
        svn_user_id: [
          { required: true, type: 'number', message: '请选择 SVN 用户', trigger: 'change' }
        ],
        new_username: [
          { required: true, message: '请输入用户名', trigger: 'blur' },
          { pattern: /^[a-zA-Z0-9_-]{3,20}$/, message: '用户名格式不正确', trigger: 'blur' }
        ],
        new_password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
        ]
      },

      // 表格列定义
      mappingColumns: [
        {
          type: 'selection',
          width: 60,
          align: 'center'
        },
        {
          title: '企业微信用户',
          slot: 'wecom_info',
          width: 200
        },
        {
          title: 'SVN 用户',
          slot: 'svn_info',
          width: 180
        },
        {
          title: '所属部门',
          slot: 'departments',
          width: 200
        },
        {
          title: '映射状态',
          slot: 'mapping_status',
          width: 100
        },
        {
          title: '更新时间',
          key: 'updated_at',
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
    selectedUsers() {
      return this.userMappings.filter(user => 
        this.selectedRowKeys.includes(user.wecom_userid)
      )
    }
  },

  mounted() {
    console.log('WecomMapping 组件已挂载，开始加载数据')
    // 加载映射数据，统计数据会在 loadMappings 中自动更新
    this.loadMappings()
  },

  methods: {
    /**
     * 将百分比钳制在 0-100，非数字返回 0
     */
    clampPercent(value) {
      const n = Number(value)
      if (!isFinite(n)) return 0
      if (n < 0) return 0
      if (n > 100) return 100
      return Math.round(n)
    },
    /** 将数量转为非负整数 */
    clampCount(value) {
      const n = parseInt(value, 10)
      return isFinite(n) && n > 0 ? n : 0
    },
    /**
     * 更新映射统计数据
     */
    updateMappingStats(users) {
      // 确保输入是数组
      if (!Array.isArray(users)) {
        console.warn('updateMappingStats: 输入不是数组', users)
        users = []
      }
      const totalWecom = this.clampCount(users.length)
      const mappedUsers = this.clampCount(users.filter(u => 
        u.svn_user_name && typeof u.svn_user_name === 'string' && u.svn_user_name.trim() !== ''
      ).length)
      const unmapped = Math.max(0, totalWecom - mappedUsers)
      const rawRate = totalWecom > 0 ? (mappedUsers / totalWecom) * 100 : 0
      this.mappingStats = {
        wecom_users_total: totalWecom,
        mapped_users: mappedUsers,
        unmapped_users: unmapped,
        mapping_rate: this.clampPercent(rawRate)
      }
      console.log('映射统计数据已更新:', this.mappingStats)
    },

    /**
     * 加载用户映射
     */
    async loadMappings() {
      console.log('开始加载映射数据...')
      this.mappingsLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetMapping&t=web', {
          ...this.searchFilters,
          page: this.mappingsPagination.current_page,
          page_size: this.mappingsPagination.page_size
        })
        
        if (response.data.status === 1) {
          const data = response.data.data
          console.log('API 响应成功，原始数据:', data)
          
          // 保存部门映射信息
          this.departmentMap = data.department_map || {}
          console.log('部门映射信息:', this.departmentMap)
          
          // 处理用户映射数据
          console.log('开始处理用户数据，用户数量:', data.users ? data.users.length : 0)
          let processedUsers = data.users.map(user => ({
            ...user,
            mapping_status: user.svn_user_name ? 'mapped' : 'unmapped',
            departments: this.parseDepartments(user.wecom_department_ids)
          }))
          console.log('用户数据处理完成，处理后数量:', processedUsers.length)
          
          // 应用前端筛选
          console.log('应用筛选条件:', this.searchFilters)
          const originalCount = processedUsers.length
          
          if (this.searchFilters.mapping_status) {
            processedUsers = processedUsers.filter(user => 
              user.mapping_status === this.searchFilters.mapping_status
            )
            console.log(`映射状态筛选: ${originalCount} -> ${processedUsers.length}`)
          }
          
          if (this.searchFilters.wecom_name) {
            const beforeNameFilter = processedUsers.length
            processedUsers = processedUsers.filter(user => 
              user.wecom_name && user.wecom_name.toLowerCase().includes(this.searchFilters.wecom_name.toLowerCase())
            )
            console.log(`姓名筛选: ${beforeNameFilter} -> ${processedUsers.length}`)
          }
          
          if (this.searchFilters.wecom_userid) {
            const beforeIdFilter = processedUsers.length
            processedUsers = processedUsers.filter(user => 
              user.wecom_userid && user.wecom_userid.toLowerCase().includes(this.searchFilters.wecom_userid.toLowerCase())
            )
            console.log(`用户ID筛选: ${beforeIdFilter} -> ${processedUsers.length}`)
          }
          
          this.userMappings = processedUsers
          
          // 更新统计数据（基于完整数据集，不是筛选后的数据）
          this.updateMappingStats(data.users || [])
          
          console.log('统计数据更新:', this.mappingStats)
          console.log('原始用户数据:', data.users ? data.users.length : 0)
          
          // 更新分页信息
          this.mappingsPagination.total = this.userMappings.length
        } else {
          console.error('API 返回错误:', response.data.message)
          this.$Message.error('加载用户映射失败: ' + response.data.message)
          // 即使API失败，也要重置统计数据
          this.updateMappingStats([])
        }
      } catch (error) {
        console.error('加载用户映射失败:', error)
        this.$Message.error('加载用户映射失败: ' + error.message)
        // 异常情况下重置统计数据
        this.updateMappingStats([])
      } finally {
        this.mappingsLoading = false
      }
    },

    /**
     * 解析部门信息
     */
    parseDepartments(departmentIds) {
      if (!departmentIds) return []
      
      try {
        // 处理JSON格式的部门ID数组
        let deptIds = []
        if (typeof departmentIds === 'string') {
          if (departmentIds.startsWith('[') && departmentIds.endsWith(']')) {
            // JSON数组格式：[1,2,3] 或 ["1","2","3"]
            deptIds = JSON.parse(departmentIds)
          } else {
            // 逗号分隔格式：1,2,3
            deptIds = departmentIds.split(',')
          }
        } else if (Array.isArray(departmentIds)) {
          deptIds = departmentIds
        }
        
        // 使用部门映射信息获取真实的部门名称
        return deptIds.map(id => {
          const trimmedId = String(id).trim()
          return {
            id: trimmedId,
            name: this.departmentMap[trimmedId] || `未知部门(${trimmedId})`
          }
        }).filter(dept => dept.id) // 过滤掉空的部门ID
      } catch (error) {
        console.warn('解析部门ID失败:', departmentIds, error)
        return []
      }
    },

    /**
     * 刷新映射数据
     */
    refreshMappings() {
      this.loadMappings()
    },

    /**
     * 显示映射模态框
     */
    showMappingModal(user) {
      this.currentUser = user
      this.mappingType = user.svn_user_name ? 'existing' : 'existing'
      this.mappingForm = {
        svn_user_id: user.svn_user_name ? 1 : null, // 临时设置，实际应该从用户数据获取
        new_username: '',
        new_password: '',
        new_note: user.wecom_name
      }
      this.mappingModalVisible = true
    },

    /**
     * 处理映射类型变化
     */
    handleMappingTypeChange() {
      this.mappingForm = {
        svn_user_id: null,
        new_username: '',
        new_password: '',
        new_note: this.currentUser ? this.currentUser.wecom_name : ''
      }
    },

    /**
     * 搜索 SVN 用户
     */
    async searchSvnUsers(query) {
      if (!query) {
        this.svnUsers = []
        return
      }

      this.svnUsersLoading = true
      
      try {
        // 这里应该调用搜索 SVN 用户的接口
        // 暂时返回模拟数据
        this.svnUsers = [
          { svn_user_id: 1, svn_user_name: 'user1', svn_user_note: '用户1' },
          { svn_user_id: 2, svn_user_name: 'user2', svn_user_note: '用户2' }
        ].filter(user => 
          user.svn_user_name.includes(query) || 
          (user.svn_user_note && user.svn_user_note.includes(query))
        )
      } catch (error) {
        console.error('搜索 SVN 用户失败:', error)
      } finally {
        this.svnUsersLoading = false
      }
    },

    /**
     * 保存映射关系
     */
    async saveMappingRelation() {
      this.$refs.mappingForm.validate(async (valid) => {
        if (!valid) {
          this.$Message.error('请检查表单填写')
          return
        }

        try {
          // 这里应该调用保存映射关系的接口
          this.$Message.success('映射关系保存成功')
          this.mappingModalVisible = false
          this.loadMappings()
        } catch (error) {
          console.error('保存映射关系失败:', error)
          this.$Message.error('保存映射关系失败')
        }
      })
    },

    /**
     * 取消映射编辑
     */
    cancelMappingEdit() {
      this.currentUser = null
      this.mappingForm = {
        svn_user_id: null,
        new_username: '',
        new_password: '',
        new_note: ''
      }
    },

    /**
     * 移除映射确认
     */
    removeMappingConfirm(user) {
      this.$Modal.confirm({
        title: '确认移除映射',
        content: `确定要移除用户 "${user.wecom_name}" 的映射关系吗？`,
        onOk: () => {
          this.removeMapping(user)
        }
      })
    },

    /**
     * 移除映射
     */
    async removeMapping(user) {
      try {
        // 这里应该调用移除映射的接口
        this.$Message.success('映射关系移除成功')
        this.loadMappings()
        this.loadMappingStats()
      } catch (error) {
        console.error('移除映射失败:', error)
        this.$Message.error('移除映射失败')
      }
    },

    /**
     * 自动匹配用户
     */
    async autoMatchUsers() {
      this.autoMatchLoading = true
      
      try {
        // 这里应该调用自动匹配的接口
        await new Promise(resolve => setTimeout(resolve, 2000)) // 模拟延迟
        
        this.$Message.success('自动匹配完成')
        this.loadMappings()
        this.loadMappingStats()
      } catch (error) {
        console.error('自动匹配失败:', error)
        this.$Message.error('自动匹配失败')
      } finally {
        this.autoMatchLoading = false
      }
    },

    /**
     * 显示批量映射模态框
     */
    showBatchMappingModal() {
      if (this.selectedRowKeys.length === 0) {
        this.$Message.warning('请先选择要映射的用户')
        return
      }
      this.batchMappingModalVisible = true
    },

    /**
     * 执行批量映射
     */
    async executeBatchMapping() {
      try {
        // 这里应该调用批量映射的接口
        this.$Message.success(`批量映射完成，处理了 ${this.selectedRowKeys.length} 个用户`)
        this.batchMappingModalVisible = false
        this.selectedRowKeys = []
        this.loadMappings()
        this.loadMappingStats()
      } catch (error) {
        console.error('批量映射失败:', error)
        this.$Message.error('批量映射失败')
      }
    },

    /**
     * 显示创建用户模态框
     */
    showCreateUserModal() {
      this.createUserModalVisible = true
    },

    /**
     * 为未映射用户创建 SVN 用户
     */
    async createSvnUsersForUnmapped() {
      try {
        // 这里应该调用创建用户的接口
        this.$Message.success(`成功为 ${this.mappingStats.unmapped_users} 个用户创建了 SVN 账号`)
        this.createUserModalVisible = false
        this.loadMappings()
        this.loadMappingStats()
      } catch (error) {
        console.error('创建 SVN 用户失败:', error)
        this.$Message.error('创建 SVN 用户失败')
      }
    },

    /**
     * 导出映射关系
     */
    exportMappings() {
      const mappingData = {
        export_time: new Date().toLocaleString(),
        mappings: this.userMappings.map(user => ({
          wecom_userid: user.wecom_userid,
          wecom_name: user.wecom_name,
          svn_user_name: user.svn_user_name,
          mapping_status: user.mapping_status
        }))
      }
      
      const dataStr = JSON.stringify(mappingData, null, 2)
      const dataBlob = new Blob([dataStr], { type: 'application/json' })
      
      const link = document.createElement('a')
      link.href = URL.createObjectURL(dataBlob)
      link.download = `wecom_user_mappings_${new Date().getTime()}.json`
      link.click()
      
      this.$Message.success('映射关系导出成功')
    },

    /**
     * 显示导入模态框
     */
    showImportModal() {
      this.$Message.info('导入功能开发中...')
    },

    /**
     * 选择变化处理
     */
    onSelectChange(selectedRowKeys) {
      this.selectedRowKeys = selectedRowKeys
    },

    /**
     * 分页变化处理
     */
    handlePageChange(page) {
      this.mappingsPagination.current_page = page
      this.loadMappings()
    },

    /**
     * 页面大小变化处理
     */
    handlePageSizeChange(pageSize) {
      this.mappingsPagination.page_size = pageSize
      this.mappingsPagination.current_page = 1
      this.loadMappings()
    },

    /**
     * 获取映射状态徽章
     */
    getMappingStatusBadge(status) {
      const statusMap = {
        'mapped': 'success',
        'unmapped': 'error',
        'conflict': 'warning'
      }
      return statusMap[status] || 'default'
    },

    /**
     * 获取映射状态文本
     */
    getMappingStatusText(status) {
      const statusMap = {
        'mapped': '已映射',
        'unmapped': '未映射',
        'conflict': '冲突'
      }
      return statusMap[status] || status
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

.ivu-tag {
  margin: 2px;
}
</style>
