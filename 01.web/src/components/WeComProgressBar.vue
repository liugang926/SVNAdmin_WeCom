<template>
  <div class="wecom-progress-wrapper">
    <div class="wecom-progress-bar">
      <div 
        v-for="(item, index) in progressItems" 
        :key="index" 
        class="progress-item"
        :class="{ 'active': item.active, 'completed': item.completed }"
        :style="{ width: itemWidth }"
      >
        <div class="progress-line" v-if="index < progressItems.length - 1"></div>
        <div class="progress-dot">
          <Icon v-if="item.icon" :type="item.icon" />
        </div>
      </div>
    </div>
    <div class="progress-labels">
      <div 
        v-for="(item, index) in progressItems" 
        :key="'label-' + index" 
        class="progress-label"
        :style="{ width: itemWidth }"
      >
        {{ item.label }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WeComProgressBar',
  props: {
    items: {
      type: Array,
      default: () => []
    },
    // 默认的进度项配置
    defaultType: {
      type: String,
      default: 'sync' // sync, config, notification
    }
  },
  computed: {
    progressItems() {
      if (this.items && this.items.length > 0) {
        return this.items
      }
      
      // 根据类型返回默认配置
      switch (this.defaultType) {
        case 'sync':
          return [
            { label: '准备同步', completed: true, icon: 'ios-checkmark' },
            { label: '获取数据', completed: true, icon: 'ios-checkmark' },
            { label: '数据处理', active: true, icon: 'ios-sync' },
            { label: '写入系统', icon: 'ios-time' }
          ]
        case 'config':
          return [
            { label: '基础配置', completed: true, icon: 'ios-checkmark' },
            { label: '连接测试', active: true, icon: 'ios-wifi' },
            { label: '权限验证', icon: 'ios-lock' },
            { label: '配置完成', icon: 'ios-flag' }
          ]
        case 'notification':
          return [
            { label: '规则配置', completed: true, icon: 'ios-checkmark' },
            { label: '消息构建', completed: true, icon: 'ios-checkmark' },
            { label: '推送发送', active: true, icon: 'ios-send' },
            { label: '发送完成', icon: 'ios-checkmark-circle-outline' }
          ]
        default:
          return []
      }
    },
    itemWidth() {
      return this.progressItems.length > 0 ? `${100 / this.progressItems.length}%` : '25%'
    }
  }
}
</script>

<style scoped>
.wecom-progress-wrapper {
  padding: 16px;
  background-color: #f8f8f9;
  border: 1px solid #e8eaec;
  border-radius: 4px;
  margin-bottom: 16px;
}

.wecom-progress-bar {
  display: flex;
  align-items: center;
  position: relative;
  margin-bottom: 12px;
}

.progress-item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.progress-dot {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: #e8eaec;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  position: relative;
  color: #80848f;
  font-size: 16px;
}

.progress-item.completed .progress-dot {
  background-color: #19be6b;
  color: #fff;
}

.progress-item.active .progress-dot {
  background-color: #2d8cf0;
  color: #fff;
  animation: pulse 1.5s ease-in-out infinite;
}

.progress-line {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 100%;
  height: 2px;
  background-color: #e8eaec;
  transform: translateY(-50%);
  z-index: 1;
}

.progress-item.completed .progress-line {
  background-color: #19be6b;
}

.progress-labels {
  display: flex;
}

.progress-label {
  text-align: center;
  font-size: 12px;
  color: #515a6e;
  padding: 0 4px;
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(45, 140, 240, 0.7);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(45, 140, 240, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(45, 140, 240, 0);
  }
}

/* 响应式布局 */
@media (max-width: 768px) {
  .progress-label {
    font-size: 10px;
  }
  
  .progress-dot {
    width: 24px;
    height: 24px;
    font-size: 12px;
  }
}
</style>
</template>

<parameter name="explanation">创建一个带文字标签的进度条组件，用于显示同步、配置等操作的进度状态
