<template>
  <Drawer v-model="visible" :title="title" width="82%" class-name="repo-explorer-drawer">
    <div class="browser-container">
      <div class="browser-header">
        <div class="nav-area">
          <div class="path-nav">
            <Breadcrumb separator="/" class="modern-breadcrumb">
              <BreadcrumbItem
                v-for="(item, index) in breadRepPath.name"
                :key="index"
                @click.native="openPath(breadRepPath.path[index])"
              >
                <Icon :type="index === 0 ? 'md-cube' : 'ios-folder'" style="margin-right: 4px" />
                {{ item }}
              </BreadcrumbItem>
            </Breadcrumb>
          </div>
        </div>
        <div class="action-area">
          <div class="checkout-box">
            <span class="checkout-label">检出地址:</span>
            <Input readonly v-model="tempCheckout" size="small" class="checkout-input">
              <Button slot="append" icon="md-copy" @click="copyCheckout">复制地址</Button>
            </Input>
          </div>
        </div>
      </div>

      <div class="browser-content">
        <Table
          height="calc(100vh - 160px)"
          highlight-row
          :no-data-text="noDataTextRepCon"
          :border="false"
          :loading="loading"
          :columns="columns"
          :data="data"
          @on-row-click="openRow"
          class="browser-table"
        >
          <template slot="empty">
            <div class="repo-empty-folder">
              <svg viewBox="0 0 180 130" role="img" aria-hidden="true">
                <defs>
                  <linearGradient id="folderGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#8fd3ff" />
                    <stop offset="100%" stop-color="#2d8cf0" />
                  </linearGradient>
                </defs>
                <path d="M24 38h46l12 14h74c7 0 12 5 12 12v42c0 7-5 12-12 12H24c-7 0-12-5-12-12V50c0-7 5-12 12-12z" fill="#e8f4ff" />
                <path d="M22 52h136c7 0 12 5 12 12v44c0 7-5 12-12 12H22c-7 0-12-5-12-12V64c0-7 5-12 12-12z" fill="url(#folderGradient)" />
                <circle cx="132" cy="42" r="18" fill="#19be6b" opacity=".16" />
                <path d="M124 42l6 6 12-14" fill="none" stroke="#19be6b" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M52 78h54M52 94h76" stroke="#fff" stroke-width="8" stroke-linecap="round" opacity=".75" />
              </svg>
              <strong>当前文件夹为空</strong>
              <span>可以上传文件，或创建 trunk / branches / tags 等标准分支目录。</span>
            </div>
          </template>
          <template slot-scope="{ row }" slot="resourceType">
            <div class="file-icon-wrapper">
              <Icon v-if="row.resourceType == 1" type="ios-document" size="24" class="icon-file" />
              <Icon v-if="row.resourceType == 2" type="ios-folder" size="24" class="icon-folder" />
            </div>
          </template>
          <template slot-scope="{ row }" slot="resourceName">
            <div class="file-name-info">
              <span class="name-text">{{ row.resourceName }}</span>
            </div>
          </template>
          <template slot-scope="{ row }" slot="revInfo">
            <div class="rev-cell">
              <Tag size="small" color="blue" ghost class="svn-revision-tag">{{ formatRevision(row.revNum) }}</Tag>
              <span class="rev-author">{{ row.revAuthor }}</span>
            </div>
          </template>
        </Table>
      </div>
    </div>
  </Drawer>
</template>

<script>
export default {
  name: "RepoExplorer",
  props: {
    svnserveReady: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      visible: false,
      title: "",
      userRoleId: sessionStorage.user_role_id,
      currentRepName: "",
      currentRepPath: "/",
      tempCheckout: "",
      noDataTextRepCon: "暂无数据",
      loading: true,
      checkInfo: {
        protocal: "",
        prefix: "",
      },
      breadRepPath: {
        name: [],
        path: [],
      },
      columns: [
        {
          title: "类型",
          slot: "resourceType",
          width: 70,
          align: "center",
        },
        {
          title: "名称",
          slot: "resourceName",
          minWidth: 180,
          tooltip: true,
        },
        {
          title: "版本",
          slot: "revInfo",
          width: 150,
        },
        {
          title: "体积",
          key: "fileSize",
          width: 100,
        },
        {
          title: "最后修改日期",
          key: "revTime",
          width: 180,
        },
        {
          title: "提交日志",
          key: "revLog",
          tooltip: true,
          minWidth: 150,
        },
      ],
      data: [],
    };
  },
  methods: {
    openAdmin(repName) {
      this.noDataTextRepCon = "暂无数据";
      this.currentRepPath = "/";
      this.currentRepName = repName;
      this.title = "仓库内容 - " + repName;
      this.visible = true;
      this.getCheckout().then(() => {
        this.getRepCon();
      });
    },
    openUser(repName, priPath) {
      this.noDataTextRepCon = "暂无数据";
      this.currentRepPath = priPath;
      this.currentRepName = repName;
      this.title = "仓库内容 - " + repName;
      this.visible = true;
      this.getCheckout().then(() => {
        if (this.svnserveReady) {
          this.getUserRepCon();
        } else {
          this.loading = false;
          this.noDataTextRepCon =
            "由于svnserve服务未启动，SVN用户只能复制检出地址而不能进行仓库内容浏览";
          this.tempCheckout = this.buildCheckoutUrl();
        }
      });
    },
    openRaw(rowUrl) {
      window.open(this.resolveCheckoutUrl(rowUrl), "_blank");
    },
    getCheckout() {
      this.tempCheckout = "";
      this.data = [];
      this.breadRepPath = {
        name: [],
        path: [],
      };
      this.loading = true;
      return new Promise((resolve, reject) => {
        this.$axios
          .post("api.php?c=Svnrep&a=GetCheckout&t=web", {})
          .then((response) => {
            const result = response.data;
            if (result.status == 1) {
              this.checkInfo = result.data;
            } else {
              this.loading = false;
              this.$Message.error({ content: result.message, duration: 2 });
            }
            resolve(response);
          })
          .catch((error) => {
            this.loading = false;
            console.log(error);
            this.$Message.error("出错了 请联系管理员！");
            reject(error);
          });
      });
    },
    isLocalCheckoutHost(host) {
      return (
        host == "localhost" ||
        host == "0.0.0.0" ||
        host == "::1" ||
        host == "[::1]" ||
        /^127\./.test(host)
      );
    },
    resolveCheckoutProtocol(protocol, prefix) {
      const originalProtocol = protocol || "";
      if (
        typeof window === "undefined" ||
        originalProtocol.indexOf("http") !== 0 ||
        !/^https?:$/.test(window.location.protocol)
      ) {
        return originalProtocol;
      }

      try {
        const url = new URL(originalProtocol + String(prefix || ""));
        return this.isLocalCheckoutHost(url.hostname)
          ? window.location.protocol + "//"
          : originalProtocol;
      } catch (e) {
        return originalProtocol;
      }
    },
    resolveCheckoutPrefix(protocol, prefix) {
      const originalPrefix = String(prefix || "");
      if (typeof window === "undefined" || originalPrefix == "") {
        return originalPrefix;
      }

      try {
        const url = new URL((protocol || "http://") + originalPrefix);
        if (!this.isLocalCheckoutHost(url.hostname)) {
          return originalPrefix;
        }

        const pathPrefix = url.pathname == "/" ? "" : url.pathname.replace(/\/+$/, "");
        if ((protocol || "").indexOf("http") === 0) {
          return window.location.host + pathPrefix;
        }

        let browserHost = window.location.hostname;
        if (browserHost.indexOf(":") >= 0 && browserHost.charAt(0) != "[") {
          browserHost = "[" + browserHost + "]";
        }
        return browserHost + (url.port ? ":" + url.port : "") + pathPrefix;
      } catch (e) {
        return originalPrefix;
      }
    },
    resolveCheckoutUrl(checkoutUrl) {
      const originalUrl = String(checkoutUrl || "");
      if (typeof window === "undefined" || originalUrl == "") {
        return originalUrl;
      }

      try {
        const url = new URL(originalUrl);
        if (!this.isLocalCheckoutHost(url.hostname)) {
          return originalUrl;
        }

        const protocol = url.protocol + "//";
        const prefix = url.host + url.pathname;
        return (
          this.resolveCheckoutProtocol(protocol, prefix) +
          this.resolveCheckoutPrefix(protocol, prefix).replace(/\/+$/, "") +
          url.search +
          url.hash
        );
      } catch (e) {
        return originalUrl;
      }
    },
    buildCheckoutUrl() {
      const rawProtocol = (this.checkInfo && this.checkInfo.protocal) || "";
      const rawPrefix = (this.checkInfo && this.checkInfo.prefix) || "";
      const protocol = this.resolveCheckoutProtocol(rawProtocol, rawPrefix);
      const prefix = this.resolveCheckoutPrefix(rawProtocol, rawPrefix).replace(/\/+$/, "");
      let repPath = this.currentRepPath || "/";
      if (repPath.charAt(0) != "/") {
        repPath = "/" + repPath;
      }
      return protocol + prefix + "/" + this.currentRepName + repPath;
    },
    getRepCon() {
      this.loading = true;
      this.$axios
        .post("api.php?c=Svnrep&a=GetRepCon&t=web", {
          rep_name: this.currentRepName,
          path: this.currentRepPath,
        })
        .then((response) => {
          this.loading = false;
          const result = response.data;
          if (result.status == 1) {
            this.data = result.data.data;
            this.breadRepPath = result.data.bread;
            this.tempCheckout = this.buildCheckoutUrl();
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.loading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    getUserRepCon() {
      this.loading = true;
      this.$axios
        .post("api.php?c=Svnrep&a=GetUserRepCon&t=web", {
          rep_name: this.currentRepName,
          path: this.currentRepPath,
        })
        .then((response) => {
          this.loading = false;
          const result = response.data;
          if (result.status == 1) {
            this.data = result.data.data;
            this.breadRepPath = result.data.bread;
            this.tempCheckout = this.buildCheckoutUrl();
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.loading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    openRow(row, index) {
      if (this.data[index] && this.data[index].resourceType == "2") {
        this.openPath(this.data[index].fullPath);
      }
    },
    openPath(fullPath) {
      this.currentRepPath = fullPath;
      if (this.userRoleId == 1 || this.userRoleId == 3) {
        this.getRepCon();
      } else if (this.userRoleId == 2) {
        this.getUserRepCon();
      }
    },
    copyCheckout() {
      this.$copyText(this.tempCheckout).then(
        () => {
          this.$Message.success("复制成功");
        },
        () => {
          this.$Message.error("复制失败，请手动复制");
        }
      );
    },
    formatRevision(revision) {
      const value = String(revision || "-");
      return value.charAt(0).toLowerCase() == "r" ? value : "r" + value;
    },
  },
};
</script>

<style lang="less">
.repo-explorer-drawer .ivu-drawer-body {
  padding: 0;
  background-color: #fff;
}
</style>

<style lang="less" scoped>
.browser-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 52px);
}

.browser-content {
  flex: 1;
  overflow: hidden;
}

.browser-header {
  padding: 12px 24px;
  background: var(--primary-light);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modern-breadcrumb {
  background: #fff;
  padding: 4px 16px;
  border-radius: 20px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-light);
}

.checkout-box {
  display: flex;
  align-items: center;
  background: #fff;
  padding: 4px 12px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-light);
}

.checkout-label {
  font-size: 12px;
  color: var(--text-light);
  margin-right: 8px;
  font-weight: 600;
}

.checkout-input {
  width: 400px;
  /deep/ .ivu-input {
    border: none;
    background: transparent;
    font-family: monospace;
    font-size: 13px;
    color: var(--primary-color);
  }
}

.browser-table /deep/ .ivu-table-row {
  cursor: pointer;
  &:hover td {
    background-color: var(--primary-light) !important;
  }
}

.browser-table /deep/ .ivu-table-wrapper,
.browser-table /deep/ .ivu-table,
.browser-table /deep/ .ivu-table th,
.browser-table /deep/ .ivu-table td {
  border-left: 0;
  border-right: 0;
}

.file-icon-wrapper {
  display: flex;
  justify-content: center;
}

.icon-file {
  color: var(--text-light);
}

.icon-folder {
  color: var(--primary-color);
}

.file-name-info {
  font-weight: 500;
  color: var(--text-main);
}

.rev-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  .rev-author {
    color: var(--text-sub);
    font-size: 12px;
  }
}

.svn-revision-tag {
  font-family: var(--mono-font);
}

.repo-empty-folder {
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: var(--text-light);
  text-align: center;
}

.repo-empty-folder svg {
  width: 180px;
  max-width: 42%;
  margin-bottom: 4px;
}

.repo-empty-folder strong {
  color: var(--text-main);
  font-size: 15px;
}

.repo-empty-folder span {
  max-width: 360px;
  line-height: 1.6;
}
</style>
