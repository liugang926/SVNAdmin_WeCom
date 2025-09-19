<template>
  <div class="wecom-status-bar">
    <div class="status-item" v-for="(item, index) in statusItems" :key="index">
      <Badge :status="item.status" />
      <span class="status-text">{{ item.text }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WeComStatusBar',
  props: {
    // 状态类型：config（配置）, sync（同步）, notification（通知）, mapping（映射）
    type: {
      type: String,
      default: 'config'
    },
    // 自定义状态项
    customItems: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    statusItems() {
      // 如果有自定义状态项，优先使用
      if (this.customItems && this.customItems.length > 0) {
        return this.customItems
      }
      
      // 根据类型返回默认状态项
      switch (this.type) {
        case 'config':
          return [
            { status: 'success', text: '已完成' },
            { status: 'processing', text: '进行中' },
            { status: 'error', text: '失败/错误' },
            { status: 'default', text: '未开始' }
          ]
        case 'sync':
          return [
            { status: 'success', text: '同步成功' },
            { status: 'processing', text: '同步中' },
            { status: 'warning', text: '部分成功' },
            { status: 'error', text: '同步失败' }
          ]
        case 'notification':
          return [
            { status: 'success', text: '已启用' },
            { status: 'processing', text: '发送中' },
            { status: 'warning', text: '待发送' },
            { status: 'error', text: '发送失败' },
            { status: 'default', text: '已禁用' }
          ]
        case 'mapping':
          return [
            { status: 'success', text: '已映射' },
            { status: 'processing', text: '处理中' },
            { status: 'warning', text: '待确认' },
            { status: 'error', text: '映射失败' },
            { status: 'default', text: '未映射' }
          ]
        default:
          return [
            { status: 'success', text: '正常' },
            { status: 'processing', text: '处理中' },
            { status: 'warning', text: '警告' },
            { status: 'error', text: '错误' },
            { status: 'default', text: '默认' }
          ]
      }
    }
  }
}
</script>

<style scoped>
.wecom-status-bar {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  background-color: #f8f8f9;
  border: 1px solid #e8eaec;
  border-radius: 4px;
  gap: 24px;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.status-text {
  font-size: 12px;
  color: #515a6e;
}

/* 响应式布局 */
@media (max-width: 768px) {
  .wecom-status-bar {
    flex-wrap: wrap;
    gap: 12px;
  }
  
  .status-item {
    min-width: 80px;
  }
}
</style>
</template>

<parameter name="explanation">创建一个通用的状态栏组件，用于显示不同颜色的状态点及其含义
