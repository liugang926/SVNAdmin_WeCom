<template>
  <div>
    <!-- 对话框-仓库权限 -->
    <Modal
      v-model="modalRepPri"
      :title="titleModalRepPri"
      @on-visible-change="ChangeModalVisible"
      fullscreen
    >
      <Row type="flex" justify="center" :gutter="16">
        <Col span="11">
          <Scroll :height="550">
            <Tree
              :data="dataTreeRep"
              :load-data="ExpandRepTree"
              :render="renderContent"
              @on-select-change="ChangeSelectTreeNode"
              @on-contextmenu="handleContextMenu"
            >
              <template slot="contextMenu">
                <DropdownItem @click.native="modalCreateRepFolder = true"
                  >新建文件夹</DropdownItem
                >
              </template>
            </Tree>
            <Spin size="large" fix v-if="loadingRepTree"></Spin>
          </Scroll>
        </Col>
        <Col span="11">
          <Tooltip
            style="width: 100%"
            max-width="450"
            :content="currentRepPath"
            placement="bottom"
          >
            <Input v-model="currentRepPath">
              <span slot="prepend">当前路径:</span>
            </Input>
          </Tooltip>
          <Card
            :bordered="true"
            :dis-hover="true"
            style="height: 500px; margin-top: 18px"
          >
            <Row :gutter="12" type="flex" align="middle">
              <Col span="10">
                <Button
                  icon="md-add"
                  type="primary"
                  ghost
                  @click="modalSvnObject = true"
                  >路径授权</Button
                >
              </Col>
              <Col span="14">
                <Input
                  search
                  clearable
                  v-model="searchKeywordRepPathPri"
                  placeholder="通过对象名称、姓名、显示名、邮箱搜索..."
                  @on-change="GetRepPathAllPri"
                />
              </Col>
            </Row>
            <Table
              border
              ref="repPathPriTable"
              :height="390"
              size="small"
              :loading="loadingRepPathAllPri"
              :columns="tableColumnRepPathAllPri"
              :data="tableDataRepPathAllPri"
              @on-column-width-resize="onColumnWidthResize"
              resizable
              style="margin-top: 20px"
            >
              <template slot-scope="{ row }" slot="objectType">
                <Tag
                  color="blue"
                  v-if="row.objectType == 'user'"
                  style="width: 90px; text-align: center"
                  >SVN用户</Tag
                >
                <Tag
                  color="geekblue"
                  v-if="row.objectType == 'group'"
                  style="width: 90px; text-align: center"
                  >SVN分组</Tag
                >
                <Tag
                  color="purple"
                  v-if="row.objectType == 'aliase'"
                  style="width: 90px; text-align: center"
                  >SVN别名</Tag
                >
                <Tag
                  color="red"
                  v-if="row.objectType == '*'"
                  style="width: 90px; text-align: center"
                  >所有人</Tag
                >
                <Tag
                  color="magenta"
                  v-if="row.objectType == '$authenticated'"
                  style="width: 90px; text-align: center"
                  >所有已认证者</Tag
                >
                <Tag
                  color="volcano"
                  v-if="row.objectType == '$anonymous'"
                  style="width: 90px; text-align: center"
                  >所有匿名者</Tag
                >
              </template>
              <template slot-scope="{ row }" slot="objectPri">
                <RadioGroup
                  v-model="row.objectPri"
                  type="button"
                  size="small"
                  button-style="solid"
                  @on-change="
                    (objectPri) =>
                      ClickRepPathPri(
                        row.objectType,
                        row.invert,
                        row.objectName,
                        objectPri
                      )
                  "
                >
                  <Radio label="rw">读写</Radio>
                  <Radio label="r">只读</Radio>
                  <Radio label="no">禁止</Radio>
                </RadioGroup>
              </template>
              <template slot-scope="{ row }" slot="invert">
                <Switch
                  v-if="row.objectType != '*'"
                  v-model="row.invert"
                  @on-change="
                    (invert) =>
                      ClickRepPathPri(
                        row.objectType,
                        invert,
                        row.objectName,
                        row.objectPri
                      )
                  "
                >
                  <Icon type="md-checkmark" slot="open"></Icon>
                  <Icon type="md-close" slot="close"></Icon>
                </Switch>
              </template>
              <template slot-scope="{ row }" slot="action">
                <Button
                  type="error"
                  size="small"
                  @click="DelRepPathPri(row.objectType, row.objectName)"
                  >删除</Button
                >
              </template>
            </Table>
          </Card>
        </Col>
      </Row>
      <div slot="footer">
        <Button type="primary" ghost @click="CloseModalRepPri">取消</Button>
      </div>
    </Modal>
    <!-- SVN对象列表组件 -->
    <ModalSvnObject
      :propModalSvnObject="modalSvnObject"
      :propChangeParentModalObject="CloseModalObject"
      :propSendParentObject="CreateRepPathPri"
      :propSvnnUserPriPathId="svnn_user_pri_path_id"
      :propShowSvnUserTab="true"
      :propShowSvnGroupTab="true"
      :propShowSvnAliaseTab="true"
      :propShowSvnAllTab="showModalSvnObjectTab"
      :propShowSvnAuthenticatedTab="showModalSvnObjectTab"
      :propShowSvnAnonymousTab="showModalSvnObjectTab"
    />
    <!-- 对话框-新建文件夹 -->
    <Modal v-model="modalCreateRepFolder" :draggable="true" title="新建文件夹">
      <Form :label-width="80">
        <FormItem label="父目录">
          <Input v-model="currentRepPath" readonly></Input>
        </FormItem>
        <FormItem label="文件夹">
          <Input v-model="folderName"></Input>
        </FormItem>
        <FormItem>
          <Button
            type="primary"
            :loading="loadingCreateRepFolder"
            @click="CreateRepFolder"
            >确定</Button
          >
        </FormItem>
      </Form>
      <div slot="footer">
        <Button type="primary" ghost @click="modalCreateRepFolder = false"
          >取消</Button
        >
      </div>
    </Modal>
  </div>
</template>

<script>
//SVN对象列表组件
import ModalSvnObject from "./modalSvnObject.vue";

export default {
  props: {
    propModalRepPri: {
      type: Boolean,
      default: false,
    },
    propCurrentRepPath: {
      type: String,
      default: "",
    },
    propCurrentRepName: {
      type: String,
      default: "",
    },
    propSvnnUserPriPathId: {
      type: Number,
      default: -1,
    },
    propChangeParentModalVisible: {
      type: Function,
      default: function () {},
    },
    propChangeParentCurrentRepPath: {
      type: Function,
      default: function () {},
    },
  },
  data() {
    return {
      folderName: "",
      modalRepPri: this.propModalRepPri,
      modalSvnObject: false,
      modalCreateRepFolder: false,
      showModalSvnObjectTab:
        sessionStorage.user_role_id == 1 || sessionStorage.user_role_id == 3,
      loadingRepTree: false,
      loadingRepPathAllPri: false,
      loadingCreateRepFolder: false,
      currentRepPath: this.propCurrentRepPath || "/",
      currentRepName: this.propCurrentRepName,
      svnn_user_pri_path_id: this.propSvnnUserPriPathId,
      searchKeywordRepPathPri: "",
      titleModalRepPri: "仓库权限",
      tableColumnStorageKey: "svn_rep_path_pri_column_widths",
      dataTreeRep: [],
      tableDataRepPathAllPri: [],
      tableColumnRepPathAllPri: [
        {
          title: "授权类型",
          slot: "objectType",
          width: 125,
          minWidth: 110,
          resizable: true,
        },
        {
          title: "对象名称",
          key: "objectLabel",
          tooltip: true,
          width: 160,
          minWidth: 120,
          resizable: true,
        },
        {
          title: "读写权限",
          slot: "objectPri",
          width: 220,
          minWidth: 180,
          resizable: true,
        },
        {
          slot: "invert",
          width: 130,
          minWidth: 110,
          resizable: true,
          renderHeader: function (h) {
            return h("span", "权限反转");
          },
        },
        { title: "操作", slot: "action", width: 100, resizable: false },
      ],
    };
  },
  components: {
    ModalSvnObject,
  },
  computed: {
    pathChunks() {
      return this.currentRepPath.split("/").filter(Boolean);
    },
  },
  watch: {
    propCurrentRepPath: function (value) {
      this.currentRepPath = value || "/";
    },
    propCurrentRepName: function (value) {
      this.currentRepName = value;
    },
    propSvnnUserPriPathId: function (value) {
      this.svnn_user_pri_path_id = value;
    },
    propModalRepPri: function (value) {
      this.modalRepPri = value;
      if (value) {
        this.OpenModalRepPri();
      }
    },
  },
  mounted() {
    this.loadColumnWidths();
  },
  methods: {
    onColumnWidthResize(newWidth, oldWidth, column) {
      this.saveColumnWidth(column.key || column.slot, newWidth);
    },
    loadColumnWidths() {
      const savedWidths = localStorage.getItem(this.tableColumnStorageKey);
      if (!savedWidths) {
        return;
      }
      try {
        const widths = JSON.parse(savedWidths);
        this.tableColumnRepPathAllPri.forEach((column) => {
          const key = column.key || column.slot;
          if (widths[key] && column.resizable !== false) {
            this.$set(column, "width", widths[key]);
          }
        });
      } catch (e) {
        console.warn("Failed to load permission table column widths:", e);
      }
    },
    saveColumnWidth(columnKey, width) {
      if (!columnKey) {
        return;
      }
      let widths = {};
      const savedWidths = localStorage.getItem(this.tableColumnStorageKey);
      if (savedWidths) {
        try {
          widths = JSON.parse(savedWidths);
        } catch (e) {
          console.warn("Failed to parse permission table column widths:", e);
        }
      }
      widths[columnKey] = width;
      localStorage.setItem(this.tableColumnStorageKey, JSON.stringify(widths));
    },
    getObjectTypeText(type) {
      const map = {
        'user': '用户',
        'group': '分组',
        'aliase': '别名',
        '*': '所有人',
        '$authenticated': '已认证用户',
        '$anonymous': '匿名用户'
      };
      return map[type] || type;
    },
    OpenModalRepPri() {
      this.currentRepPath = this.propCurrentRepPath || "/";
      this.currentRepName = this.propCurrentRepName;
      this.svnn_user_pri_path_id = this.propSvnnUserPriPathId;
      this.titleModalRepPri = "仓库权限 - " + this.currentRepName;
      this.dataTreeRep = [];
      this.LoadRepTree(true);
      this.GetRepPathAllPri();
    },
    CloseModalObject() {
      this.modalSvnObject = false;
    },
    CloseModalRepPri() {
      this.modalRepPri = false;
      this.propChangeParentModalVisible();
    },
    ChangeModalVisible(value) {
      if (!value) {
        this.propChangeParentModalVisible();
      }
    },
    renderContent(h, options) {
      const data = options.data;
      return h("span", [
        h("Icon", {
          props: {
            type:
              data.resourceType == "1"
                ? "ios-document-outline"
                : "ios-folder-open",
          },
          style: { marginRight: "8px" },
        }),
        h("span", data.title),
      ]);
    },
    handleContextMenu(data) {
      this.ChangeSelectTreeNode([], data);
    },
    LoadRepTree(first) {
      const that = this;
      that.loadingRepTree = true;
      const request =
        sessionStorage.user_role_id == 2
          ? that.GetRepTree2(first)
          : that.GetRepTree();
      request
        .then(function (response) {
          that.loadingRepTree = false;
          const result = response.data;
          if (result.status == 1) {
            that.dataTreeRep = that.NormalizeTreeData(result.data);
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingRepTree = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    NormalizeTreeData(data) {
      if (data && data.length > 0) {
        return data;
      }
      return [
        {
          resourceType: 2,
          title: this.currentRepName + "/",
          fullPath: "/",
        },
      ];
    },
    CreateRepFolder() {
      const that = this;
      that.loadingCreateRepFolder = true;
      const data = {
        rep_name: that.currentRepName,
        path: that.currentRepPath,
        folder_name: that.folderName,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=CreateRepFolder&t=web", data)
        .then(function (response) {
          that.loadingCreateRepFolder = false;
          const result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.modalCreateRepFolder = false;
            that.folderName = "";
            that.LoadRepTree(false);
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingCreateRepFolder = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    GetRepTree() {
      return this.$axios.post("api.php?c=Svnrep&a=GetRepTree&t=web", {
        rep_name: this.currentRepName,
        path: this.currentRepPath,
      });
    },
    GetRepTree2(first) {
      return this.$axios.post("api.php?c=Svnrep&a=GetRepTree2&t=web", {
        rep_name: this.currentRepName,
        path: this.currentRepPath,
        first: first,
      });
    },
    ExpandRepTree(item, callback) {
      const that = this;
      that.currentRepPath = item.fullPath;
      that.propChangeParentCurrentRepPath(item.fullPath);
      that.GetRepPathAllPri();
      const request =
        sessionStorage.user_role_id == 2
          ? that.GetRepTree2(false)
          : that.GetRepTree();
      request
        .then(function (response) {
          const result = response.data;
          if (result.status == 1) {
            const data = result.data || [];
            if (data.length > 0 && data[0].fullPath != "/") {
              callback(data);
            } else {
              callback([]);
            }
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
            callback([]);
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
          callback([]);
        });
    },
    ChangeSelectTreeNode(selectArray, currentItem) {
      if (!currentItem || !currentItem.fullPath) {
        return;
      }
      this.currentRepPath = currentItem.fullPath;
      this.propChangeParentCurrentRepPath(currentItem.fullPath);
      this.GetRepPathAllPri();
    },
    GetRepPathAllPri() {
      const that = this;
      that.tableDataRepPathAllPri = [];
      that.loadingRepPathAllPri = true;
      const data = {
        rep_name: that.currentRepName,
        path: that.currentRepPath,
        searchKeyword: that.searchKeywordRepPathPri,
        svnn_user_pri_path_id: that.svnn_user_pri_path_id,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=GetRepPathAllPri&t=web", data)
        .then(function (response) {
          that.loadingRepPathAllPri = false;
          const result = response.data;
          if (result.status == 1) {
            that.tableDataRepPathAllPri = result.data;
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingRepPathAllPri = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    CreateRepPathPri(objectType, objectName) {
      const that = this;
      const data = {
        rep_name: that.currentRepName,
        path: that.currentRepPath,
        objectType: objectType,
        objectPri: "rw",
        objectName: objectName,
        svnn_user_pri_path_id: that.svnn_user_pri_path_id,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=CreateRepPathPri&t=web", data)
        .then(function (response) {
          const result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.GetRepPathAllPri();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    ClickRepPathPri(objectType, invert, objectName, objectPri) {
      const that = this;
      const data = {
        rep_name: that.currentRepName,
        path: that.currentRepPath,
        objectType: objectType,
        invert: invert,
        objectName: objectName,
        objectPri: objectPri,
        svnn_user_pri_path_id: that.svnn_user_pri_path_id,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=UpdRepPathPri&t=web", data)
        .then(function (response) {
          const result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.GetRepPathAllPri();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    DelRepPathPri(objectType, objectName) {
      const that = this;
      const data = {
        rep_name: that.currentRepName,
        path: that.currentRepPath,
        objectType: objectType,
        objectName: objectName,
        svnn_user_pri_path_id: that.svnn_user_pri_path_id,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=DelRepPathPri&t=web", data)
        .then(function (response) {
          const result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.GetRepPathAllPri();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
  },
};
</script>

<style scoped>
.modern-permission-modal /deep/ .ivu-modal-body {
  padding: 0;
  background-color: #f5f7f9;
}

.permission-layout {
  display: flex;
  height: calc(100vh - 100px);
}

.permission-sider {
  width: 320px;
  background: #fff;
  border-right: 1px solid #dcdee2;
  display: flex;
  flex-direction: column;
}

.sider-header {
  padding: 16px 20px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  align-items: center;
  font-weight: 600;
  color: #17233d;
}

.sider-title {
  margin-left: 8px;
}

.sider-body {
  padding: 16px;
  flex: 1;
}

.permission-main {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.main-header {
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid #dcdee2;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.path-display {
  display: flex;
  align-items: center;
}

.path-label {
  color: #808695;
  margin-right: 12px;
  font-size: 13px;
}

.modern-breadcrumb {
  background: #f8f9fb;
  padding: 4px 12px;
  border-radius: 4px;
  border: 1px solid #e8eaec;
}

.main-body {
  padding: 24px;
  flex: 1;
  overflow-y: auto;
}

.matrix-table {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 6px rgba(0,0,0,0.05);
}

.object-info {
  display: flex;
  align-items: center;
}

.object-text {
  margin-left: 12px;
  display: flex;
  flex-direction: column;
}

.object-name {
  font-weight: 600;
  color: #17233d;
}

.object-tag {
  font-size: 11px;
  color: #808695;
}

.pri-btn {
  width: 60px;
  text-align: center;
}

.pri-btn.rw.ivu-radio-wrapper-checked {
  background-color: #19be6b !important;
  border-color: #19be6b !important;
  color: #fff !important;
}

.pri-btn.r.ivu-radio-wrapper-checked {
  background-color: #2db7f5 !important;
  border-color: #2db7f5 !important;
  color: #fff !important;
}

.pri-btn.no.ivu-radio-wrapper-checked {
  background-color: #ed4014 !important;
  border-color: #ed4014 !important;
  color: #fff !important;
}

.invert-action {
  display: flex;
  align-items: center;
}

.invert-label {
  margin-left: 8px;
  font-size: 12px;
  color: #ed4014;
}

.modern-tree /deep/ .ivu-tree-title {
  padding: 4px 8px;
  border-radius: 4px;
  transition: all 0.2s;
}

.modern-tree /deep/ .ivu-tree-title:hover {
  background-color: #e8f4ff;
}

.modern-tree /deep/ .ivu-tree-title-selected {
  background-color: #2d8cf0 !important;
  color: #fff !important;
}
</style>
