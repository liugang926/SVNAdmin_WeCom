<template>
  <div class="resizable-table-wrapper">
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
    >
      <!-- 透传所有插槽 -->
      <template v-for="(_, slot) of $scopedSlots" v-slot:[slot]="scope">
        <slot :name="slot" v-bind="scope" />
      </template>
    </Table>
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
    // 表格标识，用于保存列宽配置
    tableKey: {
      type: String,
      required: true
    },
    // 是否启用列宽调整
    resizable: {
      type: Boolean,
      default: true
    },
    // 是否保存列宽配置到本地存储
    saveColumnWidth: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      enhancedColumns: [],
      columnWidths: {}
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
  },
  methods: {
    /**
     * 初始化列配置
     */
    initializeColumns(columns) {
      this.enhancedColumns = columns.map(column => {
        const enhancedColumn = { ...column }
        
        // 如果启用了列宽调整功能
        if (this.resizable) {
          // 设置默认可调整大小
          enhancedColumn.resizable = column.resizable !== false
          
          // 如果没有设置宽度，使用最小宽度
          if (!enhancedColumn.width && !enhancedColumn.minWidth) {
            enhancedColumn.minWidth = 80
          }
          
          // 从本地存储加载保存的列宽
          const columnKey = column.key || column.slot
          if (columnKey) {
            const savedWidth = this.columnWidths[columnKey]
            if (savedWidth) {
              enhancedColumn.width = savedWidth
            }
          }
        }
        
        return enhancedColumn
      })
    },
    
    /**
     * 处理列宽调整事件
     */
    handleColumnResize(newWidth, oldWidth, column, event) {
      const columnKey = column.key || column.slot
      
      if (columnKey) {
        // 更新内存中的列宽
        this.$set(this.columnWidths, columnKey, newWidth)
        
        // 保存到本地存储
        if (this.saveColumnWidth) {
          this.saveColumnWidths()
        }
        
        // 触发自定义事件
        this.$emit('on-column-resize', {
          columnKey,
          newWidth,
          oldWidth,
          column,
          event
        })
      }
    },
    
    /**
     * 从本地存储加载列宽配置
     */
    loadColumnWidths() {
      if (!this.saveColumnWidth || !this.tableKey) return
      
      try {
        const storageKey = `table_column_widths_${this.tableKey}`
        const savedWidths = localStorage.getItem(storageKey)
        
        if (savedWidths) {
          this.columnWidths = JSON.parse(savedWidths)
          // 重新初始化列配置以应用保存的宽度
          this.initializeColumns(this.columns)
        }
      } catch (error) {
        console.warn('加载表格列宽配置失败:', error)
      }
    },
    
    /**
     * 保存列宽配置到本地存储
     */
    saveColumnWidths() {
      if (!this.saveColumnWidth || !this.tableKey) return
      
      try {
        const storageKey = `table_column_widths_${this.tableKey}`
        localStorage.setItem(storageKey, JSON.stringify(this.columnWidths))
      } catch (error) {
        console.warn('保存表格列宽配置失败:', error)
      }
    },
    
    /**
     * 重置列宽配置
     */
    resetColumnWidths() {
      this.columnWidths = {}
      
      if (this.saveColumnWidth && this.tableKey) {
        const storageKey = `table_column_widths_${this.tableKey}`
        localStorage.removeItem(storageKey)
      }
      
      // 重新初始化列配置
      this.initializeColumns(this.columns)
      
      this.$Message.success('列宽配置已重置')
    },
    
    /**
     * 获取表格实例
     */
    getTableInstance() {
      return this.$refs.resizableTable
    },
    
    /**
     * 导出列宽配置
     */
    exportColumnWidths() {
      return { ...this.columnWidths }
    },
    
    /**
     * 导入列宽配置
     */
    importColumnWidths(widths) {
      this.columnWidths = { ...widths }
      this.initializeColumns(this.columns)
      
      if (this.saveColumnWidth) {
        this.saveColumnWidths()
      }
    }
  }
}
</script>

<style lang="less" scoped>
.resizable-table-wrapper {
  position: relative;
  
  // 增强表格样式
  /deep/ .ivu-table {
    // 列调整手柄样式
    .ivu-table-header {
      .ivu-table-cell {
        position: relative;
        
        &:hover {
          .column-resize-handle {
            opacity: 1;
          }
        }
      }
    }
    
    // 调整手柄
    .column-resize-handle {
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      cursor: col-resize;
      background: #dcdee2;
      opacity: 0;
      transition: opacity 0.2s;
      
      &:hover {
        background: #2d8cf0;
      }
    }
    
    // 调整过程中的样式
    &.column-resizing {
      user-select: none;
      
      .column-resize-handle {
        opacity: 1;
        background: #2d8cf0;
      }
    }
  }
}

// 响应式调整
@media (max-width: 768px) {
  .resizable-table-wrapper {
    /deep/ .ivu-table {
      font-size: 12px;
      
      .ivu-table-cell {
        padding: 8px 4px;
      }
    }
  }
}
</style>
