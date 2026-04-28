<template>
  <div class="wecom-container">
    <Card :bordered="false" :dis-hover="true" class="main-card">
      <div class="wecom-header">
        <div class="header-title">
          <Icon type="ios-chatbubbles" size="32" color="#2d8cf0" />
          <div class="title-text">
            <h2>企业微信集成管理</h2>
            <p>管理企业微信与 SVN 的集成配置、同步状态和通知规则</p>
          </div>
        </div>
        <div class="header-status">
          <Badge :status="systemStatus.overall_status === 'success' ? 'success' : 'error'" />
          <span class="status-label">
            {{ systemStatus.overall_status === 'success' ? '系统正常' : '需要配置' }}
          </span>
          <Button
            type="primary"
            icon="ios-refresh"
            @click="refreshSystemStatus"
            :loading="statusLoading"
            size="small"
            ghost
          >
            刷新状态
          </Button>
          <Button
            type="warning"
            icon="ios-pulse"
            @click="openDiagnostics"
            size="small"
            ghost
          >
            一键诊断
          </Button>
        </div>
      </div>

      <Row :gutter="16" class="wecom-nav-cards">
        <Col :xs="24" :sm="12" :md="8">
          <Card
            :bordered="true"
            :hoverable="true"
            class="nav-card"
            @click.native="setActiveTab('config')"
            :class="{ 'active-card': activeTab === 'config' }"
          >
            <div class="nav-card-content">
              <Icon type="ios-settings" size="32" class="nav-icon config" />
              <h3>基础配置</h3>
              <p>企业微信 API 配置<br />连接测试和验证</p>
              <div class="nav-status">
                <Badge :status="configStatus.is_configured ? 'success' : 'error'" />
                <span>{{ configStatus.is_configured ? '已配置' : '未配置' }}</span>
              </div>
            </div>
          </Card>
        </Col>

        <Col :xs="24" :sm="12" :md="8">
          <Card
            :bordered="true"
            :hoverable="true"
            class="nav-card"
            @click.native="setActiveTab('sync')"
            :class="{ 'active-card': activeTab === 'sync' }"
          >
            <div class="nav-card-content">
              <Icon type="ios-sync" size="32" class="nav-icon sync" />
              <h3>数据同步</h3>
              <p>组织架构同步<br />用户权限管理</p>
              <div class="nav-status">
                <Badge :status="syncStatus.success_rate > 80 ? 'success' : 'warning'" />
                <span>成功率 {{ syncStatus.success_rate }}%</span>
              </div>
            </div>
          </Card>
        </Col>

        <Col :xs="24" :sm="12" :md="8">
          <Card
            :bordered="true"
            :hoverable="true"
            class="nav-card"
            @click.native="setActiveTab('notification')"
            :class="{ 'active-card': activeTab === 'notification' }"
          >
            <div class="nav-card-content">
              <Icon type="ios-notifications" size="32" class="nav-icon notification" />
              <h3>通知管理</h3>
              <p>通知规则配置<br />消息推送管理</p>
              <div class="nav-status">
                <Badge :status="notificationStatus.enabled ? 'success' : 'default'" />
                <span>{{ notificationStatus.rules_count }} 条规则</span>
              </div>
            </div>
          </Card>
        </Col>
      </Row>

      <Tabs v-model="activeTab" @on-click="setActiveTab" type="card">
        <TabPane label="基础配置" name="config" icon="ios-settings">
          <WecomConfig
            ref="wecomConfig"
            @config-updated="handleConfigUpdated"
            @status-changed="handleStatusChanged"
            @request-diagnostics="openDiagnostics"
          />
        </TabPane>

        <TabPane label="数据同步" name="sync" icon="ios-sync">
          <WecomSync
            ref="wecomSync"
            @sync-completed="handleSyncCompleted"
            @status-changed="handleSyncStatusChanged"
          />
        </TabPane>

        <TabPane label="通知管理" name="notification" icon="ios-notifications">
          <WecomNotification
            ref="wecomNotification"
            @rules-updated="handleRulesUpdated"
            @status-changed="handleNotificationStatusChanged"
          />
        </TabPane>

        <TabPane label="用户映射" name="mapping" icon="ios-people">
          <WecomMapping
            ref="wecomMapping"
            @mappings-updated="handleMappingsUpdated"
          />
        </TabPane>

        <TabPane label="系统监控" name="monitor" icon="ios-analytics">
          <WecomMonitor
            ref="wecomMonitor"
            @data-updated="handleMonitorDataUpdated"
          />
        </TabPane>

        <TabPane label="帮助文档" name="help" icon="ios-help-circle">
          <WecomHelp />
        </TabPane>
      </Tabs>
    </Card>
    <WecomDiagnostics ref="wecomDiagnostics" @go-config="goConfigFromDiagnostics" />
  </div>
</template>

<script>
import WecomConfig from './components/WecomConfig.vue'
import WecomSync from './components/WecomSync.vue'
import WecomNotification from './components/WecomNotification.vue'
import WecomMapping from './components/WecomMapping.vue'
import WecomMonitor from './components/WecomMonitor.vue'
import WecomHelp from './components/WecomHelp.vue'
import WecomDiagnostics from './components/WecomDiagnostics.vue'

export default {
  name: 'WecomIndex',
  components: {
    WecomConfig,
    WecomSync,
    WecomNotification,
    WecomMapping,
    WecomMonitor,
    WecomHelp,
    WecomDiagnostics
  },
  data() {
    return {
      activeTab: 'config',
      statusLoading: false,
      systemStatus: {
        overall_status: 'unknown',
        services: {
          api_service: false,
          sync_service: false,
          notification_service: false
        }
      },
      configStatus: {
        is_configured: false,
        tables_exist: false
      },
      syncStatus: {
        success_rate: 0,
        last_sync_time: null,
        departments_count: 0,
        users_count: 0
      },
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
    async initializePage() {
      await this.refreshSystemStatus()
      if (!this.configStatus.is_configured) {
        this.activeTab = 'config'
      } else if (this.syncStatus.success_rate === 0) {
        this.activeTab = 'sync'
      }
    },
    async refreshSystemStatus() {
      this.statusLoading = true
      try {
        await Promise.all([
          this.loadSystemStatus(),
          this.loadSyncStatus(),
          this.loadNotificationStatus()
        ])
      } catch (error) {
        console.error('获取企业微信状态失败:', error)
        this.$Message.error('获取企业微信状态失败')
      } finally {
        this.statusLoading = false
      }
    },
    async loadSystemStatus() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetSystemStatus&t=web', {})
        if (response.data.status === 1) {
          const data = response.data.data || {}
          this.systemStatus = {
            ...this.systemStatus,
            ...data
          }
          this.configStatus = data.config || { is_configured: false, tables_exist: false }
          this.systemStatus.overall_status =
            this.configStatus.is_configured && this.configStatus.tables_exist ? 'success' : 'error'
        }
      } catch (error) {
        console.error('获取系统状态失败:', error)
        this.configStatus = { is_configured: false, tables_exist: false }
        this.systemStatus.overall_status = 'error'
      }
    },
    async loadSyncStatus() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetDetailedSyncStatus&t=web', {})
        if (response.data.status === 1) {
          const data = response.data.data || {}
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
    },
    async loadNotificationStatus() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetNotificationStats&t=web', {})
        if (response.data.status === 1) {
          const data = response.data.data || {}
          this.notificationStatus = {
            enabled: true,
            rules_count: data.total_count || 0
          }
        }
      } catch (error) {
        console.error('获取通知状态失败:', error)
        this.notificationStatus = { enabled: false, rules_count: 0 }
      }
    },
    setActiveTab(tabName) {
      this.activeTab = tabName
    },
    openDiagnostics() {
      if (this.$refs.wecomDiagnostics) {
        this.$refs.wecomDiagnostics.open()
      }
    },
    goConfigFromDiagnostics() {
      this.activeTab = 'config'
    },
    handleConfigUpdated() {
      this.$Message.success('配置更新成功')
      this.refreshSystemStatus()
    },
    handleStatusChanged(status) {
      this.configStatus = { ...this.configStatus, ...status }
    },
    handleSyncCompleted() {
      this.$Message.success('同步完成')
      this.refreshSystemStatus()
    },
    handleSyncStatusChanged(status) {
      this.syncStatus = { ...this.syncStatus, ...status }
    },
    handleRulesUpdated() {
      this.$Message.success('通知规则更新成功')
      this.refreshSystemStatus()
    },
    handleNotificationStatusChanged(status) {
      this.notificationStatus = { ...this.notificationStatus, ...status }
    },
    handleMappingsUpdated() {
      this.$Message.success('用户映射更新成功')
      this.refreshSystemStatus()
    },
    handleMonitorDataUpdated() {}
  }
}
</script>

<style scoped lang="less">
.wecom-container {
  padding: 0;
}
.main-card {
  min-height: 600px;
}
.wecom-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0 24px 0;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 24px;
}
.header-title {
  display: flex;
  align-items: center;
}
.title-text {
  margin-left: 16px;
}
.title-text h2 {
  font-size: 20px;
  color: var(--text-main);
  margin-bottom: 4px;
  font-weight: 600;
}
.title-text p {
  color: var(--text-light);
  font-size: 13px;
}
.header-status {
  display: flex;
  align-items: center;
  background: #fff;
  padding: 6px 16px;
  border-radius: 20px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-light);
}
.status-label {
  margin: 0 12px 0 6px;
  font-weight: 600;
  font-size: 13px;
}

.wecom-nav-cards {
  margin-bottom: 24px;
}

.nav-card {
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  text-align: center;
  border: 1px solid var(--border-color);
  background: #fff;
  
  &:hover {
    border-color: var(--primary-color);
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
  }
  
  &.active-card {
    border-color: var(--primary-color) !important;
    background: var(--primary-light) !important;
    box-shadow: 0 4px 12px rgba(45, 140, 240, 0.1) !important;
    
    .nav-icon {
      transform: scale(1.1);
    }
  }
}

.nav-card-content {
  padding: 20px 0;
  .nav-icon {
    margin-bottom: 16px;
    transition: transform 0.3s;
    &.config { color: var(--primary-color); }
    &.sync { color: var(--success-color); }
    &.notification { color: var(--warning-color); }
  }
  h3 {
    font-size: 16px;
    margin-bottom: 8px;
    color: var(--text-main);
  }
  p {
    color: var(--text-light);
    font-size: 12px;
    line-height: 1.6;
    margin-bottom: 16px;
  }
}

.nav-status {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  background: #f8f9fa;
  border-radius: 12px;
  font-size: 12px;
  color: var(--text-sub);
  border: 1px solid var(--border-color);
}

.step-wrapper {
  padding: 32px;
  background: var(--primary-light);
  border-radius: var(--border-radius);
  margin-bottom: 24px;
}

/* 标签页样式美化 */
/deep/ .ivu-tabs-nav-container {
  font-size: 14px;
  font-weight: 500;
}

/deep/ .ivu-tabs-tab-active {
  font-weight: 700;
}
</style>
