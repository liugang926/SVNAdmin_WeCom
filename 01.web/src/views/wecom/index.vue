<template>
  <div>
    <Card :bordered="false" :dis-hover="true">
      <!-- 页面标题和状态指示器 -->
      <div style="margin-bottom: 20px;">
        <Row>
          <Col span="18">
            <h2 style="margin: 0; color: #2d8cf0;">
              <Icon type="ios-chatbubbles" style="margin-right: 8px;"/>
              企业微信集成管理
            </h2>
            <p style="margin: 5px 0 0 0; color: #80848f;">
              管理企业微信与 SVN 的集成配置、同步状态和通知规则
            </p>
          </Col>
          <Col span="6" style="text-align: right;">
            <div style="margin-bottom: 10px;">
              <Badge :status="systemStatus.overall_status === 'success' ? 'success' : 'error'" />
              <span style="margin-left: 5px;">
                {{ systemStatus.overall_status === 'success' ? '系统正常' : '需要配置' }}
              </span>
            </div>
            <Button 
              type="primary" 
              icon="ios-refresh" 
              @click="refreshSystemStatus"
              :loading="statusLoading"
              size="small"
            >
              刷新状态
            </Button>
          </Col>
        </Row>
      </div>

      <!-- 功能导航卡片 -->
      <Row :gutter="16" style="margin-bottom: 20px;">
        <!-- 基础配置 -->
        <Col span="8">
          <Card 
            :bordered="true" 
            :hoverable="true" 
            style="cursor: pointer; height: 140px;"
            @click.native="setActiveTab('config')"
            :class="{ 'active-card': activeTab === 'config' }"
          >
            <div style="text-align: center;">
              <Icon type="ios-settings" size="32" style="color: #2d8cf0; margin-bottom: 10px;"/>
              <h3 style="margin: 0 0 8px 0;">基础配置</h3>
              <p style="margin: 0; color: #80848f; font-size: 12px;">
                企业微信 API 配置<br/>
                连接测试和验证
              </p>
              <div style="margin-top: 10px;">
                <Badge :status="configStatus.is_configured ? 'success' : 'error'" />
                <span style="font-size: 12px;">
                  {{ configStatus.is_configured ? '已配置' : '未配置' }}
                </span>
              </div>
            </div>
          </Card>
        </Col>

        <!-- 数据同步 -->
        <Col span="8">
          <Card 
            :bordered="true" 
            :hoverable="true" 
            style="cursor: pointer; height: 140px;"
            @click.native="setActiveTab('sync')"
            :class="{ 'active-card': activeTab === 'sync' }"
          >
            <div style="text-align: center;">
              <Icon type="ios-sync" size="32" style="color: #19be6b; margin-bottom: 10px;"/>
              <h3 style="margin: 0 0 8px 0;">数据同步</h3>
              <p style="margin: 0; color: #80848f; font-size: 12px;">
                组织架构同步<br/>
                用户权限管理
              </p>
              <div style="margin-top: 10px;">
                <Badge :status="syncStatus.success_rate > 80 ? 'success' : 'warning'" />
                <span style="font-size: 12px;">
                  成功率 {{ syncStatus.success_rate }}%
                </span>
              </div>
            </div>
          </Card>
        </Col>

        <!-- 通知管理 -->
        <Col span="8">
          <Card 
            :bordered="true" 
            :hoverable="true" 
            style="cursor: pointer; height: 140px;"
            @click.native="setActiveTab('notification')"
            :class="{ 'active-card': activeTab === 'notification' }"
          >
            <div style="text-align: center;">
              <Icon type="ios-notifications" size="32" style="color: #ff9900; margin-bottom: 10px;"/>
              <h3 style="margin: 0 0 8px 0;">通知管理</h3>
              <p style="margin: 0; color: #80848f; font-size: 12px;">
                通知规则配置<br/>
                消息推送管理
              </p>
              <div style="margin-top: 10px;">
                <Badge :status="notificationStatus.enabled ? 'success' : 'default'" />
                <span style="font-size: 12px;">
                  {{ notificationStatus.rules_count }} 条规则
                </span>
              </div>
            </div>
          </Card>
        </Col>
      </Row>

      <!-- 主要内容区域 -->
      <Tabs v-model="activeTab" @on-click="setActiveTab" type="card">
        <!-- 基础配置标签页 -->
        <TabPane label="基础配置" name="config" icon="ios-settings">
          <wecom-config 
            ref="wecomConfig"
            @config-updated="handleConfigUpdated"
            @status-changed="handleStatusChanged"
          />
        </TabPane>

        <!-- 数据同步标签页 -->
        <TabPane label="数据同步" name="sync" icon="ios-sync">
          <wecom-sync 
            ref="wecomSync"
            @sync-completed="handleSyncCompleted"
            @status-changed="handleSyncStatusChanged"
          />
        </TabPane>

        <!-- 通知管理标签页 -->
        <TabPane label="通知管理" name="notification" icon="ios-notifications">
          <wecom-notification 
            ref="wecomNotification"
            @rules-updated="handleRulesUpdated"
            @status-changed="handleNotificationStatusChanged"
          />
        </TabPane>

        <!-- 用户映射标签页 -->
        <TabPane label="用户映射" name="mapping" icon="ios-people">
          <wecom-mapping 
            ref="wecomMapping"
            @mappings-updated="handleMappingsUpdated"
          />
        </TabPane>

        <!-- 系统监控标签页 -->
        <TabPane label="系统监控" name="monitor" icon="ios-analytics">
          <wecom-monitor 
            ref="wecomMonitor"
            @data-updated="handleMonitorDataUpdated"
          />
        </TabPane>

        <!-- 帮助文档标签页 -->
        <TabPane label="帮助文档" name="help" icon="ios-help-circle">
          <wecom-help />
        </TabPane>
      </Tabs>
    </Card>
  </div>
</template>

<script>
import WecomConfig from './components/WecomConfig.vue'
import WecomSync from './components/WecomSync.vue'
import WecomNotification from './components/WecomNotification.vue'
import WecomMapping from './components/WecomMapping.vue'
import WecomMonitor from './components/WecomMonitor.vue'
import WecomHelp from './components/WecomHelp.vue'

export default {
  name: 'WecomIndex',
  components: {
    WecomConfig,
    WecomSync,
    WecomNotification,
    WecomMapping,
    WecomMonitor,
    WecomHelp
  },
  data() {
    return {
      activeTab: 'config',
      statusLoading: false,
      
      // 系统整体状态
      systemStatus: {
        overall_status: 'unknown',
        services: {
          api_service: false,
          sync_service: false,
          notification_service: false
        }
      },

      // 配置状态
      configStatus: {
        is_configured: false,
        tables_exist: false
      },

      // 同步状态
      syncStatus: {
        success_rate: 0,
        last_sync_time: null,
        departments_count: 0,
        users_count: 0
      },

      // 通知状态
      notificationStatus: {
        enabled: false,
        rules_count: 0
      }
    }
  },
  
  mounted() {
    this.initializePage()
  },

  methods: {
    /**
     * 初始化页面
     */
    async initializePage() {
      await this.refreshSystemStatus()
      
      // 根据系统状态决定默认显示的标签页
      if (!this.configStatus.is_configured) {
        this.activeTab = 'config'
      } else if (this.syncStatus.success_rate === 0) {
        this.activeTab = 'sync'
      }
    },

    /**
     * 刷新系统状态
     */
    async refreshSystemStatus() {
      this.statusLoading = true
      
      try {
        // 获取系统状态
        try {
          const systemResponse = await this.$axios.post('api.php?c=WeComAdmin&a=GetSystemStatus&t=web', {})
          if (systemResponse.data.status === 1) {
            this.systemStatus = systemResponse.data.data || {}
            this.configStatus = systemResponse.data.data.config || { is_configured: false, tables_exist: false }
            
            // 设置整体状态
            this.systemStatus.overall_status = this.configStatus.is_configured && this.configStatus.tables_exist ? 'success' : 'error'
          }
        } catch (error) {
          console.error('获取系统状态失败:', error)
          this.configStatus = { is_configured: false, tables_exist: false }
          this.systemStatus.overall_status = 'error'
        }

        // 获取同步状态
        try {
          const syncResponse = await this.$axios.post('api.php?c=WeComAdmin&a=GetDetailedSyncStatus&t=web', {})
          if (syncResponse.data.status === 1) {
            const data = syncResponse.data.data || {}
            this.syncStatus = {
              success_rate: data.success_rate || 0,
              last_sync_time: data.last_sync_time,
              departments_count: (data.basic_stats && data.basic_stats.departments_count) || 0,
              users_count: (data.basic_stats && data.basic_stats.users_count) || 0
            }
          }
        } catch (error) {
          console.error('获取同步状态失败:', error)
          this.syncStatus = { success_rate: 0, last_sync_time: null, departments_count: 0, users_count: 0 }
        }

        // 获取通知状态
        try {
          const notificationResponse = await this.$axios.post('api.php?c=WeComAdmin&a=GetNotificationStats&t=web', {})
          if (notificationResponse.data.status === 1) {
            const data = notificationResponse.data.data || {}
            this.notificationStatus = {
              enabled: true,
              rules_count: data.total_count || 0
            }
          }
        } catch (error) {
          console.error('获取通知状态失败:', error)
          this.notificationStatus = { enabled: false, rules_count: 0 }
        }

      } catch (error) {
        console.error('获取系统状态失败:', error)
        this.$Message.error('获取系统状态失败')
      } finally {
        this.statusLoading = false
      }
    },

    /**
     * 设置活动标签页
     */
    setActiveTab(tabName) {
      this.activeTab = tabName
    },

    /**
     * 处理配置更新事件
     */
    handleConfigUpdated() {
      this.$Message.success('配置更新成功')
      this.refreshSystemStatus()
    },

    /**
     * 处理状态变化事件
     */
    handleStatusChanged(status) {
      this.configStatus = { ...this.configStatus, ...status }
    },

    /**
     * 处理同步完成事件
     */
    handleSyncCompleted(result) {
      this.$Message.success('同步完成')
      this.refreshSystemStatus()
    },

    /**
     * 处理同步状态变化事件
     */
    handleSyncStatusChanged(status) {
      this.syncStatus = { ...this.syncStatus, ...status }
    },

    /**
     * 处理通知规则更新事件
     */
    handleRulesUpdated() {
      this.$Message.success('通知规则更新成功')
      this.refreshSystemStatus()
    },

    /**
     * 处理通知状态变化事件
     */
    handleNotificationStatusChanged(status) {
      this.notificationStatus = { ...this.notificationStatus, ...status }
    },

    /**
     * 处理用户映射更新事件
     */
    handleMappingsUpdated() {
      this.$Message.success('用户映射更新成功')
      this.refreshSystemStatus()
    },

    /**
     * 处理监控数据更新事件
     */
    handleMonitorDataUpdated() {
      // 监控数据更新时的处理逻辑
    }
  }
}
</script>

<style scoped>
/* 活动卡片样式 */
.active-card {
  border-color: #2d8cf0 !important;
  box-shadow: 0 2px 8px rgba(45, 140, 240, 0.15) !important;
}

/* 卡片悬停效果 */
.ivu-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: box-shadow 0.3s ease;
}

/* 标签页样式调整 */
.ivu-tabs-card > .ivu-tabs-bar .ivu-tabs-tab {
  border-radius: 6px 6px 0 0;
}

/* 状态指示器样式 */
.ivu-badge-status-dot {
  width: 8px;
  height: 8px;
}

/* 响应式布局 */
@media (max-width: 768px) {
  .ivu-col {
    margin-bottom: 16px;
  }
}
</style>
