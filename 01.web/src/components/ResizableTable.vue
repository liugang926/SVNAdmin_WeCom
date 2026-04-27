<template>
  <div class="resizable-table-wrapper" :class="{ 'is-resizing': isResizing }">
    <Table
      ref="resizableTable"
      v-bind="$attrs"
      v-on="$listeners"
      :columns="enhancedColumns"
      :data="data"
      :loading="loading"
      @on-column-width-resize="handleColumnResize"
      border
      size="small"
      class="custom-modern-table"
    >
      <!-- 透传所有插槽 -->
      <template v-for="(_, slot) of $scopedSlots" v-slot:[slot]="scope">
        <slot :name="slot" v-bind="scope" />
      </template>
    </Table>
    
    <!-- 拖拽时的全局遮罩，防止 iframe 或选区干扰 -->
    <div v-if="isResizing" class="resizing-mask"></div>
  </div>
</template>

<script>
export default {
  name: 'ResizableTable',
  inheritAttrs: false,
  props: {
    columns: {
      type: Array,
      required: true
    },
    data: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    },
    tableKey: {
      type: String,
      required: true
    },
    resizable: {
      type: Boolean,
      default: true
    },
    saveColumnWidth: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      enhancedColumns: [],
      columnWidths: {},
      isResizing: false
    }
  },
  watch: {
    columns: {
      handler(newColumns) {
        this.initializeColumns(newColumns)
      },
      immediate: true,
      deep: true
    }
  },
  mounted() {
    this.loadColumnWidths()
    
    // 监听原生事件以优化交互状态
    const tableEl = this.$el.querySelector('.ivu-table-header')
    if (tableEl) {
      tableEl.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains('ivu-table-header-resizable')) {
          this.isResizing = true
          document.addEventListener('mouseup', this.stopResizing)
        }
      })
    }
  },
  beforeDestroy() {
    document.removeEventListener('mouseup', this.stopResizing)
  },
  methods: {
    stopResizing() {
      this.isResizing = false
      document.removeEventListener('mouseup', this.stopResizing)
    },
    
    initializeColumns(columns) {
      this.enhancedColumns = columns.map(column => {
        const enhancedColumn = { ...column }
        if (this.resizable) {
          enhancedColumn.resizable = column.resizable !== false
          if (!enhancedColumn.width && !enhancedColumn.minWidth) {
            enhancedColumn.minWidth = 100
          }
          const columnKey = column.key || column.slot
          if (columnKey && this.columnWidths[columnKey]) {
            enhancedColumn.width = this.columnWidths[columnKey]
          }
        }
        return enhancedColumn
      })
    },
    
    handleColumnResize(newWidth, oldWidth, column) {
      const columnKey = column.key || column.slot
      if (columnKey) {
        this.$set(this.columnWidths, columnKey, newWidth)
        if (this.saveColumnWidth) {
          this.saveColumnWidths()
        }
        this.$emit('on-column-resize', { columnKey, newWidth, oldWidth })
      }
    },
    
    loadColumnWidths() {
      if (!this.saveColumnWidth || !this.tableKey) return
      try {
        const storageKey = `table_widths_${this.tableKey}`
        const saved = localStorage.getItem(storageKey)
        if (saved) {
          this.columnWidths = JSON.parse(saved)
          this.initializeColumns(this.columns)
        }
      } catch (e) {
        console.warn('Failed to load column widths', e)
      }
    },
    
    saveColumnWidths() {
      if (!this.saveColumnWidth || !this.tableKey) return
      try {
        const storageKey = `table_widths_${this.tableKey}`
        localStorage.setItem(storageKey, JSON.stringify(this.columnWidths))
      } catch (e) {
        console.warn('Failed to save column widths', e)
      }
    }
  }
}
</script>

<style lang="less" scoped>
.resizable-table-wrapper {
  position: relative;
  border-radius: var(--border-radius);
  overflow: hidden;
  background: #fff;
  box-shadow: var(--shadow-light);
  
  &.is-resizing {
    cursor: col-resize;
    user-select: none;
  }
}

.resizing-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  cursor: col-resize;
}

.custom-modern-table {
  /deep/ .ivu-table-header {
    th {
      background-color: #f8f9fb;
      color: var(--text-main);
      font-weight: 600;
      height: 44px;
      border-bottom: 1px solid var(--border-color);
    }
  }
  
  /deep/ .ivu-table-row {
    td {
      height: 48px;
      color: var(--text-sub);
    }
    
    &:hover td {
      background-color: var(--primary-light);
    }
  }

  /deep/ .ivu-table-header-resizable {
    position: relative;
    
    &::after {
      content: '';
      position: absolute;
      right: 0;
      top: 25%;
      height: 50%;
      width: 1px;
      background: var(--border-color);
    }
    
    &:hover::after {
      width: 2px;
      background: var(--primary-color);
    }
  }
}
</style>
