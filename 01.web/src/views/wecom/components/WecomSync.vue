<template>
  <div>
    <Alert show-icon style="margin-bottom: 20px;">
      数据同步功能将企业微信的组织架构和用户信息同步到 SVN 系统中。
      <template slot="desc">
        支持全量同步和增量同步，可以自动创建 SVN 用户组和用户账号。
      </template>
    </Alert>

    <!-- 同步状态概览 -->
    <Card :bordered="false" style="margin-bottom: 20px;">
      <p slot="title">
        <Icon type="ios-analytics"/>
        同步状态概览
      </p>
      <Row :gutter="16">
        <Col span="6">
          <Statistic title="部门数量" :value="syncStats.departments_count" suffix="个"/>
        </Col>
        <Col span="6">
          <Statistic title="用户数量" :value="syncStats.users_count" suffix="个"/>
        </Col>
        <Col span="6">
          <Statistic title="成功率" :value="syncStats.success_rate" suffix="%"/>
        </Col>
        <Col span="6">
          <Statistic title="最后同步" :value="lastSyncText"/>
        </Col>
      </Row>
    </Card>

    <!-- 同步操作 -->
    <Card :bordered="false" style="margin-bottom: 20px;">
      <p slot="title">
        <Icon type="ios-sync"/>
        同步操作
      </p>
      
      <!-- 同步进度显示 -->
      <div v-if="syncProgress.show" style="margin-bottom: 20px;">
        <Alert type="info" show-icon>
          <div slot="desc">
            <div style="margin-bottom: 10px;">{{ syncProgress.message }}</div>
            <Progress 
              :percent="syncProgress.percent" 
              :status="syncProgress.status"
              stroke-width="8"
            >
              <span>{{ syncProgress.detail }}</span>
            </Progress>
          </div>
        </Alert>
      </div>
      
      <Row :gutter="16">
        <Col span="6">
          <Button 
            type="primary" 
            long 
            @click="executeFullSync"
            :loading="fullSyncLoading"
            :disabled="syncProgress.show"
          >
            <Icon type="ios-refresh-circle"/>
            执行全量同步
          </Button>
          <div style="margin-top: 8px; color: #80848f; font-size: 12px; text-align: center;">
            分批处理，提高效率
          </div>
        </Col>
        <Col span="6">
          <Button 
            type="success" 
            long 
            @click="executeIncrementalSync"
            :loading="incrementalSyncLoading"
            :disabled="syncProgress.show"
          >
            <Icon type="ios-sync"/>
            执行增量同步
          </Button>
          <div style="margin-top: 8px; color: #80848f; font-size: 12px; text-align: center;">
            只同步变更的数据
          </div>
        </Col>
        <Col span="6">
          <Button 
            type="warning" 
            long 
            @click="previewSyncData"
            :loading="previewLoading"
            :disabled="syncProgress.show"
          >
            <Icon type="ios-eye"/>
            预览同步数据
          </Button>
          <div style="margin-top: 8px; color: #80848f; font-size: 12px; text-align: center;">
            查看将要同步的数据
          </div>
        </Col>
        <Col span="6">
          <Button 
            type="error" 
            long 
            @click="stopSync"
            :loading="stopSyncLoading"
            :disabled="!syncProgress.show"
          >
            <Icon type="ios-close-circle"/>
            停止同步
          </Button>
          <div style="margin-top: 8px; color: #80848f; font-size: 12px; text-align: center;">
            终止正在运行的任务
          </div>
        </Col>
      </Row>
    </Card>

    <!-- 同步日志 -->
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-list"/>
        同步日志
      </p>
      <div slot="extra">
        <Button size="small" @click="refreshLogs">
          <Icon type="ios-refresh"/>
          刷新
        </Button>
      </div>
      
      <Table 
        :columns="logColumns" 
        :data="syncLogs" 
        :loading="logsLoading"
        stripe
      >
        <template slot-scope="{ row }" slot="status">
          <Badge 
            :status="getStatusBadge(row.status)" 
            :text="getStatusText(row.status)"
          />
        </template>
        <template slot-scope="{ row }" slot="duration">
          {{ calculateDuration(row.start_time, row.end_time) }}
        </template>
      </Table>
      
      <div style="margin-top: 16px; text-align: right;">
        <Page 
          :total="logsPagination.total" 
          :current="logsPagination.current_page"
          :page-size="logsPagination.page_size"
          @on-change="handlePageChange"
          show-sizer
          show-elevator
          show-total
        />
      </div>
    </Card>
  </div>
</template>

<script>
export default {
  name: 'WecomSync',
  data() {
    return {
      fullSyncLoading: false,
      incrementalSyncLoading: false,
      previewLoading: false,
      logsLoading: false,
      stopSyncLoading: false,

      // 同步统计
      syncStats: {
        departments_count: 0,
        users_count: 0,
        success_rate: 0,
        last_sync_time: null
      },

      // 同步进度
      syncProgress: {
        show: false,
        message: '',
        detail: '',
        percent: 0,
        status: 'active'
      },

      // 进度监控定时器
      progressTimer: null,

      // 同步日志
      syncLogs: [],
      logsPagination: {
        current_page: 1,
        page_size: 20,
        total: 0
      },

      // 表格列定义
      logColumns: [
        {
          title: '同步类型',
          key: 'sync_type',
          width: 100
        },
        {
          title: '状态',
          slot: 'status',
          width: 100
        },
        {
          title: '开始时间',
          key: 'start_time',
          width: 160
        },
        {
          title: '结束时间',
          key: 'end_time',
          width: 160
        },
        {
          title: '耗时',
          slot: 'duration',
          width: 80
        },
        {
          title: '消息',
          key: 'message',
          ellipsis: true
        }
      ]
    }
  },

  computed: {
    lastSyncText() {
      if (!this.syncStats.last_sync_time) {
        return '从未同步'
      }
      return this.formatTime(this.syncStats.last_sync_time)
    }
  },

  mounted() {
    this.loadSyncStatus()
    this.loadSyncLogs()
  },

  beforeDestroy() {
    // 清理定时器
    this.stopProgressMonitoring()
  },

  methods: {
    /**
     * 加载同步状态
     */
    async loadSyncStatus() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetDetailedSyncStatus&t=web', {})
        if (response.data.status === 1) {
          this.syncStats = {
            departments_count: response.data.data.basic_stats.departments_count || 0,
            users_count: response.data.data.basic_stats.users_count || 0,
            success_rate: response.data.data.success_rate || 0,
            last_sync_time: response.data.data.last_sync_time
          }
        }
      } catch (error) {
        console.error('加载同步状态失败:', error)
      }
    },

    /**
     * 执行全量同步
     */
    async executeFullSync() {
      this.fullSyncLoading = true
      this.startProgressMonitoring('全量同步')
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=FullSync&t=web', {})
        
        if (response.data.status === 1) {
          this.updateProgress('同步完成', 100, 'success')
          this.$Message.success('全量同步执行成功')
          this.$emit('sync-completed', response.data.data)
          this.loadSyncStatus()
          this.loadSyncLogs()
          
          // 3秒后隐藏进度条
          setTimeout(() => {
            this.hideProgress()
          }, 3000)
        } else {
          this.updateProgress('同步失败', 100, 'exception')
          this.$Message.error('全量同步失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('全量同步失败:', error)
        this.updateProgress('同步失败', 100, 'exception')
        this.$Message.error('全量同步失败')
      } finally {
        this.fullSyncLoading = false
        this.stopProgressMonitoring()
      }
    },

    /**
     * 执行增量同步
     */
    async executeIncrementalSync() {
      this.incrementalSyncLoading = true
      this.startProgressMonitoring('增量同步')
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=IncrementalSync&t=web', {})
        
        if (response.data.status === 1) {
          this.updateProgress('同步完成', 100, 'success')
          this.$Message.success('增量同步执行成功')
          this.$emit('sync-completed', response.data.data)
          this.loadSyncStatus()
          this.loadSyncLogs()
          
          // 3秒后隐藏进度条
          setTimeout(() => {
            this.hideProgress()
          }, 3000)
        } else {
          this.updateProgress('同步失败', 100, 'exception')
          this.$Message.error('增量同步失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('增量同步失败:', error)
        this.updateProgress('同步失败', 100, 'exception')
        this.$Message.error('增量同步失败')
      } finally {
        this.incrementalSyncLoading = false
        this.stopProgressMonitoring()
      }
    },

    /**
     * 预览同步数据
     */
    async previewSyncData() {
      this.previewLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=PreviewSyncData&t=web', {
          limit: 10
        })
        
        if (response.data.status === 1) {
          // 显示预览数据的模态框
          this.showPreviewModal(response.data.data)
        } else {
          this.$Message.error('预览数据失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('预览数据失败:', error)
        this.$Message.error('预览数据失败')
      } finally {
        this.previewLoading = false
      }
    },

    /**
     * 显示预览数据模态框
     */
    showPreviewModal(data) {
      this.$Modal.info({
        title: '同步数据预览',
        width: 800,
        render: (h) => {
          return h('div', [
            h('h4', '部门信息'),
            h('p', `总计 ${data.preview_data.departments_total} 个部门，预览前 ${data.preview_data.departments.length} 个：`),
            h('ul', data.preview_data.departments.map(dept => 
              h('li', `${dept.name} (ID: ${dept.id})`)
            )),
            h('h4', { style: { marginTop: '20px' } }, '用户信息'),
            h('p', `总计 ${data.preview_data.users_total} 个用户，预览前 ${data.preview_data.users.length} 个：`),
            h('ul', data.preview_data.users.map(user => 
              h('li', `${user.name} (${user.userid})`)
            ))
          ])
        }
      })
    },

    /**
     * 加载同步日志
     */
    async loadSyncLogs() {
      this.logsLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetSyncLogs&t=web', {
          page: this.logsPagination.current_page,
          page_size: this.logsPagination.page_size
        })
        
        if (response.data.status === 1) {
          this.syncLogs = response.data.data.logs
          this.logsPagination = response.data.data.pagination
        }
      } catch (error) {
        console.error('加载同步日志失败:', error)
      } finally {
        this.logsLoading = false
      }
    },

    /**
     * 刷新日志
     */
    refreshLogs() {
      this.loadSyncLogs()
    },

    /**
     * 处理分页变化
     */
    handlePageChange(page) {
      this.logsPagination.current_page = page
      this.loadSyncLogs()
    },

    /**
     * 获取状态徽章
     */
    getStatusBadge(status) {
      const statusMap = {
        'success': 'success',
        'failed': 'error',
        'running': 'processing',
        'stopped': 'warning'
      }
      return statusMap[status] || 'default'
    },

    /**
     * 获取状态文本
     */
    getStatusText(status) {
      const statusMap = {
        'success': '成功',
        'failed': '失败',
        'running': '运行中',
        'stopped': '已停止'
      }
      return statusMap[status] || status
    },

    /**
     * 计算持续时间
     */
    calculateDuration(startTime, endTime) {
      if (!startTime || !endTime) {
        return '-'
      }
      
      const start = new Date(startTime)
      const end = new Date(endTime)
      const duration = Math.round((end - start) / 1000)
      
      if (duration < 60) {
        return `${duration}秒`
      } else if (duration < 3600) {
        return `${Math.floor(duration / 60)}分${duration % 60}秒`
      } else {
        const hours = Math.floor(duration / 3600)
        const minutes = Math.floor((duration % 3600) / 60)
        return `${hours}时${minutes}分`
      }
    },

    /**
     * 格式化时间
     */
    formatTime(timeStr) {
      if (!timeStr) return '-'
      
      const now = new Date()
      const time = new Date(timeStr)
      const diff = Math.floor((now - time) / 1000)
      
      if (diff < 60) {
        return '刚刚'
      } else if (diff < 3600) {
        return `${Math.floor(diff / 60)}分钟前`
      } else if (diff < 86400) {
        return `${Math.floor(diff / 3600)}小时前`
      } else {
        return `${Math.floor(diff / 86400)}天前`
      }
    },

    /**
     * 开始进度监控
     */
    startProgressMonitoring(syncType) {
      this.syncProgress = {
        show: true,
        message: `正在执行${syncType}，请稍候...`,
        detail: '准备中...',
        percent: 0,
        status: 'active'
      }
      
      // 模拟进度更新
      let progress = 0
      this.progressTimer = setInterval(() => {
        if (progress < 90) {
          progress += Math.random() * 10
          if (progress > 90) progress = 90
          
          let detail = '正在处理...'
          if (progress < 30) {
            detail = '正在获取企业微信数据...'
          } else if (progress < 60) {
            detail = '正在同步部门数据...'
          } else if (progress < 90) {
            detail = '正在同步用户数据...'
          }
          
          this.updateProgress(this.syncProgress.message, Math.floor(progress), 'active', detail)
        }
      }, 1000)
    },

    /**
     * 停止进度监控
     */
    stopProgressMonitoring() {
      if (this.progressTimer) {
        clearInterval(this.progressTimer)
        this.progressTimer = null
      }
    },

    /**
     * 更新进度
     */
    updateProgress(message, percent, status = 'active', detail = '') {
      this.syncProgress.message = message
      this.syncProgress.percent = percent
      this.syncProgress.status = status
      this.syncProgress.detail = detail || this.syncProgress.detail
    },

    /**
     * 隐藏进度
     */
    hideProgress() {
      this.syncProgress.show = false
    },

    /**
     * 停止同步任务
     */
    async stopSync() {
      this.stopSyncLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=StopSync&t=web', {})
        
        if (response.data.status === 1) {
          this.$Message.success('同步任务已停止')
          
          // 停止进度监控
          this.stopProgressMonitoring()
          
          // 更新进度显示为已停止
          this.updateProgress('同步已停止', 100, 'exception', '用户手动停止')
          
          // 重新加载状态和日志
          this.loadSyncStatus()
          this.loadSyncLogs()
          
          // 3秒后隐藏进度条
          setTimeout(() => {
            this.hideProgress()
          }, 3000)
          
        } else {
          this.$Message.error(response.data.message || '停止同步失败')
        }
      } catch (error) {
        console.error('停止同步失败:', error)
        this.$Message.error('停止同步失败: ' + (error.response && error.response.data && error.response.data.message ? error.response.data.message : error.message))
      } finally {
        this.stopSyncLoading = false
      }
    }
  }
}
</script>

<style scoped>
.ivu-statistic {
  text-align: center;
}
</style>
