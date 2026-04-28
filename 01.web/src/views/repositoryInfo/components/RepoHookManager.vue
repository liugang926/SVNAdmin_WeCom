<template>
  <div>
    <Drawer v-model="visibleProxy" :title="title" width="760" class-name="repo-hooks-drawer">
      <div class="hooks-container">
        <Alert show-icon type="warning" class="hooks-alert">
          注意：如果 SVN 客户端正在触发钩子，更新可能会阻塞或失败，请确保操作时无活跃事务。
        </Alert>

        <Tabs value="active">
          <TabPane label="仓库活动钩子" name="active" icon="md-flash">
            <div class="hooks-grid">
              <template v-for="(hook, key) in formRepHooks">
                <Card :key="key" class="hook-card" :dis-hover="true">
                  <div class="hook-card-header">
                    <div class="hook-status-info">
                      <Badge :status="hook.hasFile ? 'success' : 'default'" class="status-dot" />
                      <span class="hook-name">{{ key.replace('_', '-').toUpperCase() }}</span>
                    </div>
                    <div class="hook-actions">
                      <Tooltip content="功能介绍" placement="top">
                        <Button size="small" type="text" icon="md-help-circle" @click="openStudyHook(key)" />
                      </Tooltip>
                      <Tooltip content="编辑内容" placement="top">
                        <Button size="small" type="primary" ghost icon="md-create" @click="openEditHook(key)" />
                      </Tooltip>
                      <Tooltip content="移除脚本" placement="top" v-if="hook.hasFile">
                        <Button size="small" type="error" ghost icon="md-trash" @click="deleteHook(hook.fileName)" />
                      </Tooltip>
                    </div>
                  </div>
                  <div class="hook-card-body">
                    <p class="hook-desc">{{ hook.fileName || '未配置脚本' }}</p>
                  </div>
                </Card>
              </template>
            </div>
            <Spin size="large" fix v-if="loadingGetRepHooks"></Spin>
          </TabPane>

          <TabPane label="常用钩子模板" name="templates" icon="md-cube">
            <div class="recommend-hooks-area">
              <Scroll height="400">
                <List border size="small">
                  <ListItem v-for="(item, index) in recommendHooks" :key="index">
                    <ListItemMeta :title="item.hookName" :description="item.hookDescription" />
                    <template slot="action">
                      <Button type="info" size="small" ghost @click="viewRecommendHook(item.hookName)">查看代码</Button>
                    </template>
                  </ListItem>
                </List>
              </Scroll>
            </div>
          </TabPane>
        </Tabs>
      </div>
      <div slot="footer">
        <Button type="primary" size="large" @click="visibleProxy = false">完成</Button>
      </div>
    </Drawer>

    <Modal v-model="studyVisibleProxy" :draggable="true" :title="studyTitle">
      <Input v-model="studyContentProxy" readonly :rows="15" show-word-limit type="textarea" />
      <div slot="footer">
        <Button type="primary" ghost @click="studyVisibleProxy = false">取消</Button>
      </div>
    </Modal>

    <Modal v-model="editVisibleProxy" :draggable="true" :title="editTitle">
      <Input
        v-model="editContentProxy"
        :rows="15"
        show-word-limit
        type="textarea"
        placeholder="具体介绍和语法可看钩子介绍"
      />
      <div slot="footer">
        <Button
          :type="editHookSaveState === 'success' ? 'success' : 'primary'"
          :icon="editHookSaveState === 'success' ? 'md-checkmark' : 'md-cloud-upload'"
          class="hook-save-button"
          :class="{ 'hook-save-success': editHookSaveState === 'success' }"
          @click="updateHook"
          :loading="loadingEditRepHook"
        >{{ editHookSaveText }}</Button>
      </div>
    </Modal>

    <Modal v-model="recommendVisibleProxy" :draggable="true" title="常用钩子">
      <Input v-model="recommendContentProxy" readonly :rows="15" show-word-limit type="textarea" />
      <div slot="footer">
        <Button type="primary" ghost @click="recommendVisibleProxy = false">取消</Button>
      </div>
    </Modal>
  </div>
</template>

<script>
export default {
  name: "RepoHookManager",
  data() {
    return {
      visible: false,
      title: "",
      currentRepName: "",
      formRepHooks: this.getDefaultHooks(),
      recommendHooks: [],
      loadingGetRepHooks: true,
      loadingEditRepHook: false,
      studyVisible: false,
      studyTitle: "",
      studyContent: "",
      editVisible: false,
      editTitle: "",
      editContent: "",
      recommendVisible: false,
      recommendContent: "",
      tempSelectRepHook: "",
      editHookSaveState: "idle",
      editHookSaveTimer: null,
    };
  },
  computed: {
    visibleProxy: {
      get() { return this.visible; },
      set(value) { this.visible = value; },
    },
    studyVisibleProxy: {
      get() { return this.studyVisible; },
      set(value) { this.studyVisible = value; },
    },
    studyContentProxy: {
      get() { return this.studyContent; },
      set(value) { this.studyContent = value; },
    },
    editVisibleProxy: {
      get() { return this.editVisible; },
      set(value) { this.editVisible = value; },
    },
    editContentProxy: {
      get() { return this.editContent; },
      set(value) { this.editContent = value; },
    },
    recommendVisibleProxy: {
      get() { return this.recommendVisible; },
      set(value) { this.recommendVisible = value; },
    },
    recommendContentProxy: {
      get() { return this.recommendContent; },
      set(value) { this.recommendContent = value; },
    },
    editHookSaveText() {
      return this.editHookSaveState === "success" ? "保存成功" : "保存钩子";
    },
  },
  beforeDestroy() {
    if (this.editHookSaveTimer) {
      clearTimeout(this.editHookSaveTimer);
    }
  },
  methods: {
    getDefaultHooks() {
      return {
        start_commit: { fileName: "", hasFile: false, con: "", tmpl: "" },
        pre_commit: { fileName: "", hasFile: false, con: "", tmpl: "" },
        post_commit: { fileName: "", hasFile: false, con: "", tmpl: "" },
        pre_lock: { fileName: "", hasFile: false, con: "", tmpl: "" },
        post_lock: { fileName: "", hasFile: false, con: "", tmpl: "" },
        pre_unlock: { fileName: "", hasFile: false, con: "", tmpl: "" },
        post_unlock: { fileName: "", hasFile: false, con: "", tmpl: "" },
        pre_revprop_change: { fileName: "", hasFile: false, con: "", tmpl: "" },
        post_revprop_change: { fileName: "", hasFile: false, con: "", tmpl: "" },
      };
    },
    open(repName) {
      this.currentRepName = repName;
      this.title = "仓库钩子 - " + repName;
      this.visible = true;
      this.getRepHooks();
      this.getRecommendHooks();
    },
    getRepHooks() {
      this.loadingGetRepHooks = true;
      this.$axios
        .post("api.php?c=Svnrep&a=GetRepHooks&t=web", {
          rep_name: this.currentRepName,
        })
        .then((response) => {
          this.loadingGetRepHooks = false;
          const result = response.data;
          if (result.status == 1) {
            this.formRepHooks = result.data;
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.loadingGetRepHooks = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    getRecommendHooks() {
      this.$axios
        .post("api.php?c=Svnrep&a=GetRecommendHooks&t=web", {})
        .then((response) => {
          const result = response.data;
          if (result.status == 1) {
            this.recommendHooks = result.data;
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    deleteHook(fileName) {
      this.loadingGetRepHooks = true;
      this.$axios
        .post("api.php?c=Svnrep&a=DelRepHook&t=web", {
          rep_name: this.currentRepName,
          fileName: fileName,
        })
        .then((response) => {
          const result = response.data;
          if (result.status == 1) {
            this.$Message.success(result.message);
            this.getRepHooks();
          } else {
            this.loadingGetRepHooks = false;
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.loadingGetRepHooks = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    openStudyHook(key) {
      const hook = this.formRepHooks[key] || {};
      this.tempSelectRepHook = hook.fileName;
      this.studyContent = hook.tmpl;
      this.studyTitle = "钩子信息介绍 - " + hook.fileName;
      this.studyVisible = true;
    },
    openEditHook(key) {
      const hook = this.formRepHooks[key] || {};
      this.resetEditHookSaveState();
      this.tempSelectRepHook = hook.fileName;
      this.editContent = hook.con;
      this.editTitle = "钩子文件编辑 - " + hook.fileName;
      this.editVisible = true;
    },
    resetEditHookSaveState() {
      if (this.editHookSaveTimer) {
        clearTimeout(this.editHookSaveTimer);
        this.editHookSaveTimer = null;
      }
      this.editHookSaveState = "idle";
    },
    viewRecommendHook(hookName) {
      const hook = this.recommendHooks.find((item) => item.hookName === hookName);
      this.recommendContent = hook ? hook.hookContent : "";
      this.recommendVisible = true;
    },
    updateHook() {
      this.loadingEditRepHook = true;
      this.$axios
        .post("api.php?c=Svnrep&a=UpdRepHook&t=web", {
          rep_name: this.currentRepName,
          fileName: this.tempSelectRepHook,
          content: this.editContent,
        })
        .then((response) => {
          this.loadingEditRepHook = false;
          const result = response.data;
          if (result.status == 1) {
            this.editHookSaveState = "success";
            this.$Message.success(result.message);
            this.getRepHooks();
            this.editHookSaveTimer = setTimeout(() => {
              this.editHookSaveState = "idle";
            }, 1500);
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.loadingEditRepHook = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
  },
};
</script>

<style lang="less">
.repo-hooks-drawer .ivu-drawer-body {
  background-color: var(--content-bg);
  padding: 16px 24px;
}

.repo-hooks-drawer .ivu-drawer-footer {
  border-top: 1px solid var(--border-color);
}
</style>

<style lang="less" scoped>
.hooks-container {
  padding: 10px 0;
}

.hooks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 16px;
  padding: 16px 4px;
}

.hook-card {
  border: 1px solid var(--border-color);
  background: #fff;

  &:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
  }
}

.hook-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.hook-status-info {
  display: flex;
  align-items: center;
}

.hook-name {
  font-weight: 700;
  color: var(--text-main);
  font-size: 13px;
  margin-left: 8px;
}

.hook-actions {
  display: flex;
  gap: 4px;
}

.hook-card-body {
  background: var(--content-bg);
  padding: 10px 12px;
  border-radius: 6px;
}

.hook-desc {
  font-family: var(--mono-font);
  font-size: 11px;
  color: var(--text-sub);
}

.hook-save-button {
  min-width: 112px;
  transition: all 0.24s ease;
}

.hook-save-success {
  animation: hook-save-pop 0.28s ease-out;
  box-shadow: 0 6px 14px rgba(25, 190, 107, 0.22);
}

@keyframes hook-save-pop {
  0% { transform: scale(0.94); }
  70% { transform: scale(1.03); }
  100% { transform: scale(1); }
}
</style>
