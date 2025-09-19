<template>
  <div class="status-legend">
    <div class="legend-title">状态说明：</div>
    <div class="legend-items">
      <div class="legend-item" v-for="(item, index) in legendItems" :key="index">
        <span class="status-dot" :class="item.class"></span>
        <span class="status-text">{{ item.text }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StatusLegend',
  props: {
    // 自定义图例项
    items: {
      type: Array,
      default: () => []
    },
    // 预设类型：sync, mapping, notification, general
    type: {
      type: String,
      default: 'general'
    }
  },
  computed: {
    legendItems() {
      if (this.items && this.items.length > 0) {
        return this.items
      }
      
      // 根据类型返回预设的图例
      switch (this.type) {
        case 'sync':
          return [
            { class: 'status-success', text: '同步成功' },
            { class: 'status-processing', text: '同步中' },
            { class: 'status-warning', text: '部分成功' },
            { class: 'status-error', text: '同步失败' }
          ]
        case 'mapping':
          return [
            { class: 'status-success', text: '已映射' },
            { class: 'status-processing', text: '处理中' },
            { class: 'status-error', text: '未映射' }
          ]
        case 'notification':
          return [
            { class: 'status-success', text: '已启用/发送成功' },
            { class: 'status-processing', text: '发送中' },
            { class: 'status-warning', text: '待发送' },
            { class: 'status-error', text: '发送失败/已禁用' }
          ]
        case 'general':
        default:
          return [
            { class: 'status-success', text: '成功/已完成' },
            { class: 'status-processing', text: '进行中' },
            { class: 'status-warning', text: '警告' },
            { class: 'status-error', text: '错误/失败' },
            { class: 'status-default', text: '未开始/默认' }
          ]
      }
    }
  }
}
</script>

<style scoped>
.status-legend {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background-color: #ffffff;
  border: 1px solid #dcdee2;
  border-radius: 4px;
  font-size: 13px;
  margin-bottom: 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.legend-title {
  color: #17233d;
  margin-right: 20px;
  font-weight: 600;
  font-size: 14px;
}

.legend-items {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.status-success {
  background-color: #19be6b;
}

.status-processing {
  background-color: #2d8cf0;
}

.status-warning {
  background-color: #ff9900;
}

.status-error {
  background-color: #ed4014;
}

.status-default {
  background-color: #c5c8ce;
}

.status-text {
  color: #515a6e;
}

/* 响应式布局 */
@media (max-width: 768px) {
  .status-legend {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .legend-title {
    margin-right: 0;
    margin-bottom: 4px;
  }
  
  .legend-items {
    gap: 12px;
  }
}
</style>
</template>

<parameter name="explanation">创建一个状态图例组件，用于解释页面顶部彩色圆点的含义
