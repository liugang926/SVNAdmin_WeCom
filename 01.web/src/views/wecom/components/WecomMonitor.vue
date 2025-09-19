<template>
  <div>
    <Alert show-icon style="margin-bottom: 20px;">
      系统监控提供企业微信集成功能的运行状态和统计信息。
      <template slot="desc">
        包括同步状态、通知统计、错误日志等监控信息。
      </template>
    </Alert>

    <!-- 监控面板 -->
    <Card :bordered="false">
      <p slot="title">
        <Icon type="ios-analytics"/>
        系统监控
      </p>

      <div style="text-align: center; padding: 50px;">
        <Icon type="ios-speedometer" size="64" style="color: #c5c8ce;"/>
        <p style="color: #c5c8ce; margin-top: 20px;">系统监控功能开发中...</p>
      </div>
    </Card>
  </div>
</template>

<script>
export default {
  name: 'WecomMonitor'
}
</script>
