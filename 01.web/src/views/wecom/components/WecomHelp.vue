<template>
  <div>
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-help-circle"/>
        帮助文档
      </p>

      <Collapse v-model="activeNames">
        <Panel name="1">
          快速开始
          <div slot="content">
            <h4>1. 配置企业微信 API</h4>
            <p>在"基础配置"页面中填入企业微信的企业ID、应用ID和应用密钥。</p>
            
            <h4>2. 测试连接</h4>
            <p>点击"测试连接"按钮验证配置是否正确。</p>
            
            <h4>3. 执行同步</h4>
            <p>在"数据同步"页面中执行全量同步，将企业微信的组织架构同步到 SVN 系统。</p>
            
            <h4>4. 配置通知</h4>
            <p>在"通知管理"页面中配置 SVN 操作的通知规则。</p>
          </div>
        </Panel>

        <Panel name="2">
          企业微信配置获取
          <div slot="content">
            <h4>企业ID (CorpID)</h4>
            <p>登录企业微信管理后台 → 我的企业 → 企业信息 → 企业ID</p>
            
            <h4>应用ID (AgentID)</h4>
            <p>登录企业微信管理后台 → 应用管理 → 自建应用 → 选择应用 → AgentId</p>
            
            <h4>应用密钥 (Secret)</h4>
            <p>登录企业微信管理后台 → 应用管理 → 自建应用 → 选择应用 → 查看Secret</p>
          </div>
        </Panel>

        <Panel name="3">
          常见问题
          <div slot="content">
            <h4>Q: 连接测试失败怎么办？</h4>
            <p>A: 请检查企业微信配置是否正确，网络连接是否正常，应用是否有足够的权限。</p>
            
            <h4>Q: 同步失败怎么办？</h4>
            <p>A: 请查看同步日志中的错误信息，检查企业微信权限和网络连接。</p>
            
            <h4>Q: 通知发送失败怎么办？</h4>
            <p>A: 请检查群机器人 Webhook URL 是否正确，消息格式是否符合要求。</p>
          </div>
        </Panel>

        <Panel name="4">
          技术支持
          <div slot="content">
            <p>如果您在使用过程中遇到问题，可以通过以下方式获取帮助：</p>
            <ul>
              <li>查看系统日志文件</li>
              <li>检查企业微信管理后台的应用配置</li>
              <li>联系系统管理员</li>
            </ul>
          </div>
        </Panel>
      </Collapse>
    </Card>
  </div>
</template>

<script>
export default {
  name: 'WecomHelp',
  data() {
    return {
      activeNames: ['1']
    }
  }
}
</script>

<style scoped>
h4 {
  margin: 16px 0 8px 0;
  color: #2d8cf0;
}

p {
  margin: 8px 0;
  line-height: 1.6;
}

ul {
  margin: 8px 0;
  padding-left: 20px;
}

li {
  margin: 4px 0;
}
</style>
