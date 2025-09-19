<template>
  <div class="table-toolbar">
    <div class="toolbar-left">
      <slot name="left" />
    </div>
    
    <div class="toolbar-right">
      <slot name="right" />
      
      <!-- 表格设置下拉菜单 -->
      <Dropdown 
        v-if="showTableSettings"
        trigger="click" 
        placement="bottom-end"
        @on-click="handleSettingsClick"
      >
        <Button icon="ios-settings" type="text" size="small">
          表格设置
          <Icon type="ios-arrow-down" />
        </Button>
        <DropdownMenu slot="list">
          <DropdownItem name="resetColumnWidth" icon="ios-refresh">
            重置列宽
          </DropdownItem>
          <DropdownItem name="exportSettings" icon="ios-download">
            导出设置
          </DropdownItem>
          <DropdownItem name="importSettings" icon="ios-cloud-upload">
            导入设置
          </DropdownItem>
          <DropdownItem divided name="columnSettings" icon="ios-list">
            列显示设置
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    </div>
    
    <!-- 列显示设置模态框 -->
    <Modal
      v-model="showColumnModal"
      title="列显示设置"
      :mask-closable="false"
      width="500"
    >
      <div class="column-settings">
        <div class="settings-header">
          <Checkbox 
            :indeterminate="indeterminate"
            :value="checkAll"
            @on-change="handleCheckAll"
          >
            全选
          </Checkbox>
          <Button type="text" size="small" @click="resetColumnSettings">
            重置
          </Button>
        </div>
        
        <CheckboxGroup v-model="visibleColumns" @on-change="handleColumnChange">
          <div class="column-item" v-for="column in configurableColumns" :key="column.key">
            <Checkbox :label="column.key" :disabled="column.fixed">
              <span class="column-title">{{ column.title }}</span>
              <Tag v-if="column.fixed" size="small" color="blue">固定</Tag>
            </Checkbox>
          </div>
        </CheckboxGroup>
      </div>
      
      <div slot="footer">
        <Button @click="showColumnModal = false">取消</Button>
        <Button type="primary" @click="applyColumnSettings">确定</Button>
      </div>
    </Modal>
    
    <!-- 导入设置文件输入 -->
    <input
      ref="fileInput"
      type="file"
      accept=".json"
      style="display: none"
      @change="handleFileImport"
    />
  </div>
</template>

<script>
export default {
  name: 'TableToolbar',
  props: {
    // 表格列配置
    columns: {
      type: Array,
      default: () => []
    },
    // 是否显示表格设置
    showTableSettings: {
      type: Boolean,
      default: true
    },
    // 表格引用，用于调用表格方法
    tableRef: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      showColumnModal: false,
      visibleColumns: [],
      configurableColumns: [],
      checkAll: true,
      indeterminate: false
    }
  },
  watch: {
    columns: {
      handler(newColumns) {
        this.initializeColumnSettings(newColumns)
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    /**
     * 初始化列设置
     */
    initializeColumnSettings(columns) {
      this.configurableColumns = columns
        .filter(column => column.key || column.slot)
        .map(column => ({
          key: column.key || column.slot,
          title: column.title || column.slot,
          fixed: column.fixed === 'left' || column.fixed === 'right'
        }))
      
      this.visibleColumns = this.configurableColumns.map(col => col.key)
      this.updateCheckAllState()
    },
    
    /**
     * 处理设置菜单点击
     */
    handleSettingsClick(name) {
      switch (name) {
        case 'resetColumnWidth':
          this.resetColumnWidth()
          break
        case 'exportSettings':
          this.exportSettings()
          break
        case 'importSettings':
          this.importSettings()
          break
        case 'columnSettings':
          this.showColumnModal = true
          break
      }
    },
    
    /**
     * 重置列宽
     */
    resetColumnWidth() {
      if (this.tableRef && this.tableRef.resetColumnWidths) {
        this.tableRef.resetColumnWidths()
      } else {
        this.$emit('reset-column-width')
      }
    },
    
    /**
     * 导出设置
     */
    exportSettings() {
      try {
        const settings = {
          columnWidths: this.tableRef ? this.tableRef.exportColumnWidths() : {},
          visibleColumns: this.visibleColumns,
          exportTime: new Date().toISOString()
        }
        
        const blob = new Blob([JSON.stringify(settings, null, 2)], {
          type: 'application/json'
        })
        
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `table-settings-${Date.now()}.json`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
        
        this.$Message.success('设置导出成功')
      } catch (error) {
        this.$Message.error('设置导出失败')
        console.error('导出设置失败:', error)
      }
    },
    
    /**
     * 导入设置
     */
    importSettings() {
      this.$refs.fileInput.click()
    },
    
    /**
     * 处理文件导入
     */
    handleFileImport(event) {
      const file = event.target.files[0]
      if (!file) return
      
      const reader = new FileReader()
      reader.onload = (e) => {
        try {
          const settings = JSON.parse(e.target.result)
          
          // 导入列宽设置
          if (settings.columnWidths && this.tableRef && this.tableRef.importColumnWidths) {
            this.tableRef.importColumnWidths(settings.columnWidths)
          }
          
          // 导入列显示设置
          if (settings.visibleColumns) {
            this.visibleColumns = settings.visibleColumns.filter(col => 
              this.configurableColumns.some(c => c.key === col)
            )
            this.updateCheckAllState()
            this.applyColumnSettings()
          }
          
          this.$Message.success('设置导入成功')
        } catch (error) {
          this.$Message.error('设置文件格式错误')
          console.error('导入设置失败:', error)
        }
      }
      
      reader.readAsText(file)
      event.target.value = '' // 清空文件输入
    },
    
    /**
     * 处理全选
     */
    handleCheckAll(checked) {
      if (checked) {
        this.visibleColumns = this.configurableColumns.map(col => col.key)
      } else {
        this.visibleColumns = this.configurableColumns
          .filter(col => col.fixed)
          .map(col => col.key)
      }
      this.updateCheckAllState()
    },
    
    /**
     * 处理列选择变化
     */
    handleColumnChange() {
      this.updateCheckAllState()
    },
    
    /**
     * 更新全选状态
     */
    updateCheckAllState() {
      const total = this.configurableColumns.length
      const selected = this.visibleColumns.length
      
      this.checkAll = selected === total
      this.indeterminate = selected > 0 && selected < total
    },
    
    /**
     * 重置列设置
     */
    resetColumnSettings() {
      this.visibleColumns = this.configurableColumns.map(col => col.key)
      this.updateCheckAllState()
    },
    
    /**
     * 应用列设置
     */
    applyColumnSettings() {
      this.$emit('column-visibility-change', this.visibleColumns)
      this.showColumnModal = false
      this.$Message.success('列设置已应用')
    }
  }
}
</script>

<style lang="less" scoped>
.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding: 8px 0;
  
  .toolbar-left {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.column-settings {
  .settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e8eaec;
  }
  
  .column-item {
    margin-bottom: 8px;
    padding: 4px 0;
    
    .column-title {
      margin-right: 8px;
    }
  }
}

// 响应式调整
@media (max-width: 768px) {
  .table-toolbar {
    flex-direction: column;
    gap: 8px;
    
    .toolbar-left,
    .toolbar-right {
      width: 100%;
      justify-content: center;
    }
  }
}
</style>
