<template>
  <div>
    <Alert show-icon style="margin-bottom: 20px;">
      企业微信配置是集成功能的基础，请确保正确配置企业微信 API 参数。
      <template slot="desc">
        配置完成后，系统将能够与企业微信进行通信，实现组织架构同步和消息通知功能。
      </template>
    </Alert>

    <!-- 配置状态卡片 -->
    <Card :bordered="false" style="margin-bottom: 20px;">
      <p slot="title">
        <Icon type="ios-information-circle"/>
        配置状态
      </p>
      <Row :gutter="16">
        <Col span="6">
          <div style="text-align: center; padding: 16px;">
            <div style="font-size: 14px; color: #515a6e; margin-bottom: 8px;">配置状态</div>
            <div style="display: flex; align-items: center; justify-content: center;">
              <Badge :status="configStatus.is_configured ? 'success' : 'error'" />
              <span style="margin-left: 8px; font-size: 16px; font-weight: 500;">
                {{ configStatus.is_configured ? '已配置' : '未配置' }}
              </span>
            </div>
          </div>
        </Col>
        <Col span="6">
          <div style="text-align: center; padding: 16px;">
            <div style="font-size: 14px; color: #515a6e; margin-bottom: 8px;">数据库表</div>
            <div style="display: flex; align-items: center; justify-content: center;">
              <Badge :status="configStatus.tables_exist ? 'success' : 'error'" />
              <span style="margin-left: 8px; font-size: 16px; font-weight: 500;">
                {{ configStatus.tables_exist ? '已创建' : '未创建' }}
              </span>
            </div>
          </div>
        </Col>
        <Col span="6">
          <div style="text-align: center; padding: 16px;">
            <div style="font-size: 14px; color: #515a6e; margin-bottom: 8px;">API 服务</div>
            <div style="display: flex; align-items: center; justify-content: center;">
              <Badge :status="serviceStatus.api_service ? 'success' : 'error'" />
              <span style="margin-left: 8px; font-size: 16px; font-weight: 500;">
                {{ serviceStatus.api_service ? '正常' : '异常' }}
              </span>
            </div>
          </div>
        </Col>
        <Col span="6">
          <div style="text-align: right;">
            <Button type="primary" @click="testConnection" :loading="testLoading">
              <Icon type="ios-wifi"/>
              测试连接
            </Button>
            <Button style="margin-top: 8px;" @click="$emit('request-diagnostics')">
              <Icon type="ios-pulse"/>
              一键诊断
            </Button>
          </div>
        </Col>
      </Row>
    </Card>

    <!-- 配置表单 -->
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-settings"/>
        企业微信 API 配置
      </p>
      
      <Form ref="configForm" :model="configForm" :rules="configRules" :label-width="120">
        <!-- 基础配置 -->
        <Divider orientation="left">基础配置</Divider>
        
        <FormItem label="企业微信企业ID" prop="corp_id">
          <Input 
            v-model="configForm.corp_id" 
            placeholder="请输入企业微信企业ID"
            style="width: 400px;"
          />
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            在企业微信管理后台 -> 我的企业 -> 企业信息中获取
          </div>
        </FormItem>

        <FormItem label="应用ID" prop="agent_id">
          <InputNumber 
            v-model="configForm.agent_id" 
            placeholder="请输入应用ID"
            style="width: 400px;"
            :min="1"
          />
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            在企业微信管理后台 -> 应用管理 -> 自建应用中获取
          </div>
        </FormItem>

        <FormItem label="应用密钥" prop="corp_secret">
          <Input 
            v-model="configForm.corp_secret" 
            type="password" 
            placeholder="请输入应用密钥"
            style="width: 400px;"
          />
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            在企业微信管理后台 -> 应用管理 -> 自建应用 -> 查看Secret
          </div>
        </FormItem>

        <!-- 回调配置 -->
        <Divider orientation="left">回调配置（可选）</Divider>
        
        <FormItem label="回调Token" prop="token">
          <Input 
            v-model="configForm.token" 
            placeholder="请输入回调Token（可选）"
            style="width: 400px;"
          />
        </FormItem>

        <FormItem label="EncodingAESKey" prop="aes_key">
          <Input 
            v-model="configForm.aes_key" 
            placeholder="请输入EncodingAESKey（可选）"
            style="width: 400px;"
          />
        </FormItem>

        <!-- 功能开关 -->
        <Divider orientation="left">功能开关</Divider>
        
        <FormItem label="启用集成功能">
          <i-switch v-model="configForm.enabled" size="large">
            <span slot="open">启用</span>
            <span slot="close">禁用</span>
          </i-switch>
          <div style="margin-top: 5px; color: #80848f; font-size: 12px;">
            启用后将开始企业微信集成功能
          </div>
        </FormItem>

        <!-- 操作按钮 -->
        <FormItem>
          <Button type="primary" @click="saveConfig" :loading="saveLoading">
            <Icon type="ios-save"/>
            保存配置
          </Button>
          <Button @click="resetForm" style="margin-left: 8px;">
            <Icon type="ios-refresh"/>
            重置
          </Button>
          <Button @click="exportConfig" style="margin-left: 8px;">
            <Icon type="ios-download"/>
            导出配置
          </Button>
          <Button @click="$emit('request-diagnostics')" style="margin-left: 8px;">
            <Icon type="ios-pulse"/>
            一键诊断
          </Button>
          <Button @click="showImportModal" style="margin-left: 8px;">
            <Icon type="ios-cloud-upload"/>
            导入配置
          </Button>
        </FormItem>
      </Form>
    </Card>

    <!-- 导入配置模态框 -->
    <Modal
      v-model="importModalVisible"
      title="导入配置"
      @on-ok="importConfig"
      @on-cancel="cancelImport"
    >
      <Alert type="warning" style="margin-bottom: 15px;">
        导入配置将覆盖当前的非敏感配置项，敏感信息（如密钥）将保留现有值。
      </Alert>
      
      <Upload
        :before-upload="handleImportFile"
        action=""
        :show-upload-list="false"
        accept=".json"
      >
        <Button icon="ios-cloud-upload-outline">选择配置文件</Button>
      </Upload>
      
      <div v-if="importData" style="margin-top: 15px;">
        <Alert type="success">
          已选择配置文件，导出时间: {{ importData.export_time }}
        </Alert>
      </div>
    </Modal>
  </div>
</template>

<script>
export default {
  name: 'WecomConfig',
  data() {
    return {
      testLoading: false,
      saveLoading: false,
      importModalVisible: false,
      importData: null,

      // 配置状态
      configStatus: {
        is_configured: false,
        tables_exist: false
      },

      // 服务状态
      serviceStatus: {
        api_service: false,
        sync_service: false,
        notification_service: false
      },

      // 配置表单
      configForm: {
        enabled: false,
        corp_id: '',
        agent_id: null,
        corp_secret: '',
        token: '',
        aes_key: ''
      },

      // 表单验证规则
      configRules: {
        corp_id: [
          { required: true, message: '请输入企业微信企业ID', trigger: 'blur' },
          { pattern: /^[a-zA-Z0-9]{10,20}$/, message: '企业ID格式不正确', trigger: 'blur' }
        ],
        agent_id: [
          { required: true, type: 'number', message: '请输入应用ID', trigger: 'blur' }
        ],
        corp_secret: [
          { required: true, message: '请输入应用密钥', trigger: 'blur' },
          { min: 32, message: '应用密钥长度不足', trigger: 'blur' }
        ]
      }
    }
  },

  mounted() {
    this.loadConfig()
    this.loadStatus()
  },

  methods: {
    /**
     * 加载配置
     */
    async loadConfig() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetConfig&t=web', {})
        if (response.data.status === 1) {
          this.configForm = { ...this.configForm, ...response.data.data }
        }
      } catch (error) {
        console.error('加载配置失败:', error)
      }
    },

    /**
     * 加载状态
     */
    async loadStatus() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=GetSystemStatus&t=web', {})
        if (response.data.status === 1) {
          this.configStatus = response.data.data.config || { is_configured: false, tables_exist: false }
          this.serviceStatus = response.data.data.services || { api_service: false, sync_service: false, notification_service: false }
        } else {
          console.warn('获取状态失败:', response.data.message)
          // 保持默认值
        }
      } catch (error) {
        console.error('加载状态失败:', error)
        // 保持默认值
        this.configStatus = { is_configured: false, tables_exist: false }
        this.serviceStatus = { api_service: false, sync_service: false, notification_service: false }
      }
    },

    /**
     * 测试连接
     */
    async testConnection() {
      this.testLoading = true
      
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=TestConnection&t=web', {})
        
        if (response.data.status === 1) {
          this.$Message.success('连接测试成功')
          this.loadStatus() // 重新加载状态
        } else {
          // 显示详细的错误信息
          this.showDetailedError('连接测试失败', response.data)
        }
      } catch (error) {
        console.error('连接测试失败:', error)
        this.$Message.error('连接测试失败')
      } finally {
        this.testLoading = false
      }
    },

    /**
     * 显示详细的错误信息
     */
    showDetailedError(title, responseData) {
      let errorMessage = responseData.message || '未知错误'
      
      // 优先展示后端透传的企业微信JSON
      if (responseData.data && typeof responseData.data === 'object') {
        const errorDetails = responseData.data
        if (errorDetails.wecom_json) {
          this.$Modal.error({
            title: title + '（企业微信返回）',
            content: `<pre style="max-height:420px;overflow:auto;text-align:left;white-space:pre-wrap;">${errorDetails.wecom_json}</pre>`,
            width: 700
          })
          return
        }
        let detailsHtml = `<div style="text-align: left;">
          <p><strong>错误信息:</strong> ${errorDetails.error_message || errorMessage}</p>`
        
        if (errorDetails.error_code) {
          detailsHtml += `<p><strong>错误代码:</strong> ${errorDetails.error_code}</p>`
        }
        
        if (errorDetails.error_file) {
          detailsHtml += `<p><strong>错误文件:</strong> ${errorDetails.error_file}</p>`
        }
        
        if (errorDetails.error_line) {
          detailsHtml += `<p><strong>错误行号:</strong> ${errorDetails.error_line}</p>`
        }
        
        detailsHtml += '</div>'
        
        this.$Modal.error({
          title: title,
          content: detailsHtml,
          width: 500
        })
      } else {
        this.$Message.error(title + ': ' + errorMessage)
      }
    },

    /**
     * 保存配置
     */
    async saveConfig() {
      this.$refs.configForm.validate(async (valid) => {
        if (!valid) {
          this.$Message.error('请检查表单填写')
          return
        }

        this.saveLoading = true

        try {
          const response = await this.$axios.post('api.php?c=WeComAdmin&a=UpdateConfig&t=web', {
            config_data: this.configForm
          })

          if (response.data.status === 1) {
            this.$Message.success('配置保存成功')
            this.$emit('config-updated')
            this.loadStatus()
          } else {
            this.$Message.error('配置保存失败: ' + response.data.message)
          }
        } catch (error) {
          console.error('保存配置失败:', error)
          this.$Message.error('保存配置失败')
        } finally {
          this.saveLoading = false
        }
      })
    },

    /**
     * 重置表单
     */
    resetForm() {
      this.$refs.configForm.resetFields()
      this.loadConfig()
    },

    /**
     * 导出配置
     */
    async exportConfig() {
      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=ExportConfig&t=web', {})
        
        if (response.data.status === 1) {
          const dataStr = JSON.stringify(response.data.data, null, 2)
          const dataBlob = new Blob([dataStr], { type: 'application/json' })
          
          const link = document.createElement('a')
          link.href = URL.createObjectURL(dataBlob)
          link.download = `wecom_config_${new Date().getTime()}.json`
          link.click()
          
          this.$Message.success('配置导出成功')
        } else {
          this.$Message.error('配置导出失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('导出配置失败:', error)
        this.$Message.error('导出配置失败')
      }
    },

    /**
     * 显示导入模态框
     */
    showImportModal() {
      this.importModalVisible = true
      this.importData = null
    },

    /**
     * 处理导入文件
     */
    handleImportFile(file) {
      const reader = new FileReader()
      reader.onload = (e) => {
        try {
          this.importData = JSON.parse(e.target.result)
          this.$Message.success('配置文件读取成功')
        } catch (error) {
          this.$Message.error('配置文件格式错误')
        }
      }
      reader.readAsText(file)
      return false // 阻止自动上传
    },

    /**
     * 导入配置
     */
    async importConfig() {
      if (!this.importData) {
        this.$Message.error('请先选择配置文件')
        return
      }

      try {
        const response = await this.$axios.post('api.php?c=WeComAdmin&a=ImportConfig&t=web', {
          import_data: this.importData
        })

        if (response.data.status === 1) {
          this.$Message.success('配置导入成功')
          this.importModalVisible = false
          this.loadConfig()
          this.$emit('config-updated')
        } else {
          this.$Message.error('配置导入失败: ' + response.data.message)
        }
      } catch (error) {
        console.error('导入配置失败:', error)
        this.$Message.error('导入配置失败')
      }
    },

    /**
     * 取消导入
     */
    cancelImport() {
      this.importData = null
    }
  }
}
</script>

<style scoped>
.ivu-form-item {
  margin-bottom: 24px;
}

.ivu-divider-horizontal.ivu-divider-with-text-left {
  margin: 24px 0 16px 0;
}
</style>
