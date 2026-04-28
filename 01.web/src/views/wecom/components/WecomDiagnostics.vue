<template>
  <Modal v-model="visible" title="企业微信一键诊断" width="860" :draggable="true">
    <div class="diagnostics-panel">
      <div class="diagnostics-toolbar">
        <div class="diagnostics-summary" :class="'status-' + diagnostics.overall_status">
          <Icon :type="overallIcon" size="24" />
          <div>
            <div class="summary-title">{{ overallText }}</div>
            <div class="summary-desc">生成时间: {{ diagnostics.generated_at || '-' }}</div>
          </div>
        </div>
        <div class="summary-counts">
          <Tag color="success">通过 {{ summary.passed || 0 }}</Tag>
          <Tag color="warning">警告 {{ summary.warning || 0 }}</Tag>
          <Tag color="error">失败 {{ summary.failed || 0 }}</Tag>
          <Tag>未检测 {{ summary.unchecked || 0 }}</Tag>
        </div>
      </div>

      <Spin v-if="loading" fix>
        <Icon type="ios-loading" size="24" class="spin-icon-load"></Icon>
        <div>正在执行诊断...</div>
      </Spin>

      <Alert v-if="errorMessage" type="error" show-icon style="margin-bottom: 16px;">
        {{ errorMessage }}
      </Alert>

      <Collapse v-if="groupedChecks.length > 0" v-model="openedGroups">
        <Panel v-for="group in groupedChecks" :key="group.name" :name="group.name">
          {{ group.name }}
          <span class="group-stat">{{ group.items.length }} 项</span>
          <div slot="content">
            <div
              v-for="item in group.items"
              :key="item.key"
              class="diagnostic-item"
              :class="'status-' + item.status"
            >
              <div class="item-main">
                <div class="item-status">
                  <Icon :type="getStatusIcon(item.status)" />
                </div>
                <div class="item-body">
                  <div class="item-title-row">
                    <strong>{{ item.name }}</strong>
                    <Tag :color="getStatusColor(item.status)">{{ getStatusText(item.status) }}</Tag>
                  </div>
                  <div class="item-message">{{ item.message }}</div>
                  <div v-if="item.suggestion" class="item-suggestion">
                    <Icon type="ios-bulb" />
                    {{ item.suggestion }}
                  </div>
                  <pre v-if="hasDetails(item)" class="item-details">{{ formatDetails(item.details) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </Panel>
      </Collapse>

      <Empty v-if="!loading && groupedChecks.length === 0 && !errorMessage" description="暂无诊断结果" />
    </div>

    <div slot="footer" class="diagnostics-footer">
      <Button @click="copyDiagnostics" :disabled="groupedChecks.length === 0">
        <Icon type="ios-copy" />
        复制诊断信息
      </Button>
      <Button @click="$emit('go-config')" v-if="hasConfigFailure">
        <Icon type="ios-settings" />
        前往配置
      </Button>
      <Button type="primary" @click="runDiagnostics" :loading="loading">
        <Icon type="ios-pulse" />
        重新诊断
      </Button>
      <Button @click="visible = false">关闭</Button>
    </div>
  </Modal>
</template>

<script>
export default {
  name: 'WecomDiagnostics',
  data() {
    return {
      visible: false,
      loading: false,
      errorMessage: '',
      openedGroups: [],
      diagnostics: {
        overall_status: 'unchecked',
        summary: {},
        checks: [],
        generated_at: ''
      }
    }
  },
  computed: {
    summary() {
      return this.diagnostics.summary || {}
    },
    groupedChecks() {
      const groups = []
      const groupMap = {}
      ;(this.diagnostics.checks || []).forEach(item => {
        const groupName = item.group || '其他'
        if (!groupMap[groupName]) {
          groupMap[groupName] = { name: groupName, items: [] }
          groups.push(groupMap[groupName])
        }
        groupMap[groupName].items.push(item)
      })
      return groups
    },
    overallText() {
      const map = {
        passed: '诊断通过',
        warning: '存在警告',
        failed: '存在失败项',
        unchecked: '未检测'
      }
      return map[this.diagnostics.overall_status] || '未检测'
    },
    overallIcon() {
      const map = {
        passed: 'ios-checkmark-circle',
        warning: 'ios-alert',
        failed: 'ios-close-circle',
        unchecked: 'ios-help-circle'
      }
      return map[this.diagnostics.overall_status] || 'ios-help-circle'
    },
    hasConfigFailure() {
      return (this.diagnostics.checks || []).some(item => {
        return item.group === '基础配置' && (item.status === 'failed' || item.status === 'warning')
      })
    }
  },
  methods: {
    open() {
      this.visible = true
      this.runDiagnostics()
    },
    async runDiagnostics() {
      this.loading = true
      this.errorMessage = ''
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=RunDiagnostics&t=web', {
          scope: 'all',
          include_sensitive: false
        })
        if (response.data.status === 1) {
          this.diagnostics = response.data.data || this.diagnostics
          this.openedGroups = this.groupedChecks.map(group => group.name)
        } else {
          this.errorMessage = response.data.message || '诊断失败'
          if (response.data.data) {
            this.diagnostics = response.data.data
            this.openedGroups = this.groupedChecks.map(group => group.name)
          }
        }
      } catch (error) {
        console.error('企业微信诊断失败:', error)
        this.errorMessage = '诊断请求失败，请检查网络或后端服务'
      } finally {
        this.loading = false
      }
    },
    getStatusIcon(status) {
      return {
        passed: 'ios-checkmark-circle',
        warning: 'ios-alert',
        failed: 'ios-close-circle',
        unchecked: 'ios-help-circle'
      }[status] || 'ios-help-circle'
    },
    getStatusColor(status) {
      return {
        passed: 'success',
        warning: 'warning',
        failed: 'error',
        unchecked: 'default'
      }[status] || 'default'
    },
    getStatusText(status) {
      return {
        passed: '通过',
        warning: '警告',
        failed: '失败',
        unchecked: '未检测'
      }[status] || '未检测'
    },
    hasDetails(item) {
      return item.details && Object.keys(item.details).length > 0
    },
    formatDetails(details) {
      return JSON.stringify(details || {}, null, 2)
    },
    copyDiagnostics() {
      const text = JSON.stringify(this.diagnostics, null, 2)
      if (this.$copyText) {
        this.$copyText(text).then(() => {
          this.$Message.success('诊断信息已复制')
        }).catch(() => {
          this.fallbackCopy(text)
        })
      } else {
        this.fallbackCopy(text)
      }
    },
    fallbackCopy(text) {
      const input = document.createElement('textarea')
      input.value = text
      input.style.position = 'fixed'
      input.style.left = '-9999px'
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      this.$Message.success('诊断信息已复制')
    }
  }
}
</script>

<style scoped lang="less">
.diagnostics-panel {
  min-height: 360px;
  position: relative;
}

.diagnostics-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.diagnostics-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  background: #f7f8fa;
  border: 1px solid #e8eaec;
}

.diagnostics-summary.status-passed {
  background: #f0faf4;
  border-color: #b7ebc6;
  color: #19be6b;
}

.diagnostics-summary.status-warning {
  background: #fff9e6;
  border-color: #ffe58f;
  color: #ff9900;
}

.diagnostics-summary.status-failed {
  background: #fff1f0;
  border-color: #ffa39e;
  color: #ed4014;
}

.summary-title {
  font-size: 15px;
  font-weight: 600;
  color: #17233d;
}

.summary-desc {
  margin-top: 2px;
  color: #808695;
  font-size: 12px;
}

.summary-counts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}

.group-stat {
  margin-left: 8px;
  color: #808695;
  font-size: 12px;
}

.diagnostic-item {
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.diagnostic-item:last-child {
  border-bottom: none;
}

.item-main {
  display: flex;
  gap: 12px;
}

.item-status {
  width: 24px;
  padding-top: 2px;
  font-size: 20px;
}

.diagnostic-item.status-passed .item-status {
  color: #19be6b;
}

.diagnostic-item.status-warning .item-status {
  color: #ff9900;
}

.diagnostic-item.status-failed .item-status {
  color: #ed4014;
}

.diagnostic-item.status-unchecked .item-status {
  color: #808695;
}

.item-body {
  flex: 1;
  min-width: 0;
}

.item-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.item-message {
  color: #515a6e;
  line-height: 1.5;
}

.item-suggestion {
  margin-top: 8px;
  padding: 8px 10px;
  border-radius: 6px;
  background: #f8f8f9;
  color: #515a6e;
}

.item-details {
  margin: 8px 0 0;
  padding: 10px;
  border-radius: 6px;
  background: #17233d;
  color: #d7dde8;
  max-height: 160px;
  overflow: auto;
  font-size: 12px;
}

.diagnostics-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.spin-icon-load {
  animation: ani-demo-spin 1s linear infinite;
}

@keyframes ani-demo-spin {
  from { transform: rotate(0deg); }
  50% { transform: rotate(180deg); }
  to { transform: rotate(360deg); }
}
</style>
