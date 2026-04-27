<template>
  <div class="dashboard-container">
    <!-- 系统负载与状态 -->
    <Card
      :bordered="false"
      :dis-hover="true"
      class="status-group-card"
      v-if="display.part1"
    >
      <div slot="title" class="card-header-slot">
        <Icon type="md-pulse" class="header-icon" />
        <span class="header-title">系统实时状态</span>
        <Tooltip
          max-width="500"
          placement="bottom"
          :transfer="true"
          :content="systemBrif.os"
          class="header-os-info"
        >
          <Tag color="primary" ghost size="small">{{ systemBrif.os }}</Tag>
        </Tooltip>
      </div>

      <div class="status-grid">
        <!-- 负载状态 -->
        <div class="status-item">
          <div class="status-label">负载状态</div>
          <Tooltip placement="bottom" max-width="200">
            <Circle
              :percent="statusInfo.load.percent"
              dashboard
              :size="100"
              :stroke-color="statusInfo.load.color"
              stroke-width="6"
            >
              <div class="circle-content">
                <span class="circle-value">{{ statusInfo.load.percent }}%</span>
              </div>
            </Circle>
            <div slot="content" class="tooltip-info">
              <p>1分钟负载：{{ statusInfo.load.cpuLoad1Min }}</p>
              <p>5分钟负载：{{ statusInfo.load.cpuLoad5Min }}</p>
              <p>15分钟负载：{{ statusInfo.load.cpuLoad15Min }}</p>
            </div>
          </Tooltip>
          <div class="status-desc">{{ statusInfo.load.title }}</div>
        </div>

        <!-- CPU使用率 -->
        <div class="status-item">
          <div class="status-label">CPU使用率</div>
          <Tooltip placement="bottom" max-width="200">
            <Circle
              :percent="statusInfo.cpu.percent"
              dashboard
              :size="100"
              :stroke-color="statusInfo.cpu.color"
              stroke-width="6"
            >
              <div class="circle-content">
                <span class="circle-value">{{ statusInfo.cpu.percent }}%</span>
              </div>
            </Circle>
            <div slot="content" class="tooltip-info">
              <p v-for="item in statusInfo.cpu.cpu" :key="item">{{ item }}</p>
              <p>{{ statusInfo.cpu.cpuPhysical }}个物理CPU | {{ statusInfo.cpu.cpuCore }}核心</p>
            </div>
          </Tooltip>
          <div class="status-desc">{{ statusInfo.cpu.cpuCore }} 核心</div>
        </div>

        <!-- 内存使用率 -->
        <div class="status-item">
          <div class="status-label">内存使用率</div>
          <Circle
            :percent="statusInfo.mem.percent"
            dashboard
            :size="100"
            :stroke-color="statusInfo.mem.color"
            stroke-width="6"
          >
            <div class="circle-content">
              <span class="circle-value">{{ statusInfo.mem.percent }}%</span>
            </div>
          </Circle>
          <div class="status-desc">
            {{ statusInfo.mem.memUsed }} / {{ statusInfo.mem.memTotal }} MB
          </div>
        </div>

        <!-- 磁盘占用 -->
        <div class="status-item" v-for="(item, index) in diskList" :key="'disk-'+index">
          <div class="status-label">{{ item.mountedOn }}</div>
          <Tooltip placement="bottom" max-width="200">
            <div slot="content" class="tooltip-info">
              <p>文件系统：{{ item.fileSystem }}</p>
              <p>容量：{{ item.size }} | 已用：{{ item.used }}</p>
              <p>可用：{{ item.avail }}</p>
            </div>
            <Circle
              :percent="item.percent"
              dashboard
              :size="100"
              :stroke-color="item.color"
              stroke-width="6"
            >
              <div class="circle-content">
                <span class="circle-value">{{ item.percent }}%</span>
              </div>
            </Circle>
          </Tooltip>
          <div class="status-desc">{{ item.used }} / {{ item.size }}</div>
        </div>
      </div>
    </Card>

    <!-- 业务统计 -->
    <Card :bordered="false" :dis-hover="true" class="stat-group-card">
      <div slot="title" class="card-header-slot">
        <Icon type="ios-stats" class="header-icon" />
        <span class="header-title">业务统计概览</span>
      </div>

      <div class="stat-section">
        <h3 class="section-title">资源资产</h3>
        <Row :gutter="16">
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper blue">
                <Icon type="md-cube" />
              </div>
              <div class="stat-info">
                <div class="stat-label">SVN仓库</div>
                <div class="stat-value">{{ systemBrif.repCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper cyan">
                <Icon type="md-cloud-done" />
              </div>
              <div class="stat-info">
                <div class="stat-label">仓库占用</div>
                <div class="stat-value small">{{ systemBrif.repSize }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper orange">
                <Icon type="md-copy" />
              </div>
              <div class="stat-info">
                <div class="stat-label">仓库备份</div>
                <div class="stat-value">{{ systemBrif.backupCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper purple">
                <Icon type="md-archive" />
              </div>
              <div class="stat-info">
                <div class="stat-label">备份占用</div>
                <div class="stat-value small">{{ systemBrif.backupSize }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper grey">
                <Icon type="md-paper" />
              </div>
              <div class="stat-info">
                <div class="stat-label">运行日志</div>
                <div class="stat-value">{{ systemBrif.logCount }}</div>
              </div>
            </div>
          </Col>
        </Row>

        <h3 class="section-title mt-20">权限架构</h3>
        <Row :gutter="16">
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper primary">
                <Icon type="md-contact" />
              </div>
              <div class="stat-info">
                <div class="stat-label">管理员</div>
                <div class="stat-value">{{ systemBrif.adminCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper green">
                <Icon type="md-contacts" />
              </div>
              <div class="stat-info">
                <div class="stat-label">子管理员</div>
                <div class="stat-value">{{ systemBrif.subadminCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper blue">
                <Icon type="md-person" />
              </div>
              <div class="stat-info">
                <div class="stat-label">SVN用户</div>
                <div class="stat-value">{{ systemBrif.userCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper cyan">
                <Icon type="md-people" />
              </div>
              <div class="stat-info">
                <div class="stat-label">SVN分组</div>
                <div class="stat-value">{{ systemBrif.groupCount }}</div>
              </div>
            </div>
          </Col>
          <Col :xs="12" :sm="8" :md="4">
            <div class="stat-box">
              <div class="stat-icon-wrapper grey">
                <Icon type="md-pricetags" />
              </div>
              <div class="stat-info">
                <div class="stat-label">SVN别名</div>
                <div class="stat-value">{{ systemBrif.aliaseCount }}</div>
              </div>
            </div>
          </Col>
        </Row>
      </div>
    </Card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      /**
       * 两个板块的显示控制
       */
      display: {
        part1: true,
        part2: true,
      },
      /**
       * 硬盘信息
       */
      diskList: [],
      /**
       * 状态信息
       */
      statusInfo: {
        load: {
          cpuLoad15Min: 0,
          cpuLoad5Min: 0,
          cpuLoad1Min: 0,
          percent: 0,
          color: "#2d8cf0",
        },
        cpu: {
          percent: 0,
          cpu: [],
          cpuPhysical: 0,
          cpuPhysicalCore: 0,
          cpuCore: 0,
          cpuProcessor: 0,
          color: "#2d8cf0",
        },
        mem: {
          memTotal: 0,
          memUsed: 0,
          memFree: 0,
          percent: 0,
          color: "#2d8cf0",
        },
      },
      /**
       * 统计信息
       */
      systemBrif: {
        os: "",
        repCount: 0,
        repSize: 0,
        backupCount: 0,
        backupSize: 0,
        logCount: 0,
        adminCount: 0,
        subadminCount: 0,
        userCount: 0,
        groupCount: 0,
        aliaseCount: 0,
      },
    };
  },
  mounted() {
    if (this.display.part1) {
      this.GetDiskInfo();
      this.GetLoadInfo();
      this.timer = window.setInterval(() => {
        this.GetLoadInfo();
      }, 3000);
      this.$once("hook:beforeDestroy", () => {
        clearInterval(this.timer);
      });
    }
    if (this.display.part2) {
      this.GetStatisticsInfo();
    }
  },
  methods: {
    GetDiskInfo() {
      this.$axios.post("api.php?c=Statistics&a=GetDiskInfo&t=web")
        .then(res => {
          if (res.data.status == 1) this.diskList = res.data.data;
        });
    },
    GetLoadInfo() {
      this.$axios.post("api.php?c=Statistics&a=GetLoadInfo&t=web")
        .then(res => {
          if (res.data.status == 1) this.statusInfo = res.data.data;
        });
    },
    GetStatisticsInfo() {
      this.$axios.post("api.php?c=Statistics&a=GetStatisticsInfo&t=web")
        .then(res => {
          if (res.data.status == 1) this.systemBrif = res.data.data;
        });
    },
  },
};
</script>

<style scoped lang="less">
.dashboard-container {
  padding: 0;
}

.card-header-slot {
  display: flex;
  align-items: center;
  .header-icon {
    font-size: 20px;
    margin-right: 8px;
    color: var(--primary-color);
  }
  .header-title {
    font-size: 16px;
    font-weight: 600;
  }
  .header-os-info {
    margin-left: 12px;
  }
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 20px;
  padding: 10px 0;
}

.status-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;

  .status-label {
    font-size: 13px;
    color: var(--text-sub);
    margin-bottom: 12px;
    font-weight: 500;
  }

  .circle-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    .circle-value {
      font-size: 22px;
      font-weight: 600;
      color: var(--text-main);
    }
  }

  .status-desc {
    margin-top: 12px;
    font-size: 12px;
    color: var(--text-light);
    background: #f4f5f7;
    padding: 2px 8px;
    border-radius: 10px;
  }
}

.stat-section {
  .section-title {
    font-size: 14px;
    color: var(--text-main);
    margin-bottom: 16px;
    padding-left: 8px;
    border-left: 3px solid var(--primary-color);
    line-height: 1;
  }
  .mt-20 { margin-top: 24px; }
}

.stat-box {
  display: flex;
  align-items: center;
  padding: 16px;
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 16px;
  transition: all 0.2s;

  &:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  }

  .stat-icon-wrapper {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-right: 12px;

    &.blue { background: #e6f7ff; color: #1890ff; }
    &.cyan { background: #e6fffb; color: #13c2c2; }
    &.orange { background: #fff7e6; color: #fa8c16; }
    &.purple { background: #f9f0ff; color: #722ed1; }
    &.primary { background: #f0f7ff; color: #2d8cf0; }
    &.green { background: #f6ffed; color: #52c41a; }
    &.grey { background: #f5f5f5; color: #595959; }
  }

  .stat-info {
    .stat-label {
      font-size: 12px;
      color: var(--text-light);
      margin-bottom: 2px;
    }
    .stat-value {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.2;
      &.small { font-size: 14px; }
    }
  }
}

.tooltip-info {
  font-size: 11px;
  line-height: 1.6;
  padding: 4px;
}
</style>