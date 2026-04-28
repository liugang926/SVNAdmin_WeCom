<template>
  <div>
    <!-- 对话框-仓库权限 -->
    <Drawer
      v-model="modalRepPri"
      :title="titleModalRepPri"
      @on-visible-change="ChangeModalVisible"
      width="960"
      :mask="false"
      class-name="permission-drawer"
    >
      <Row type="flex" class="permission-drawer-layout" :gutter="16">
        <Col span="8">
          <Scroll class="permission-tree-scroll" :height="drawerContentHeight">
            <div class="path-tree-hint">
              <Icon type="ios-git-branch" />
              <span>路径层级</span>
              <Tag size="small" color="blue" v-if="tableDataRepPathAllPri.length > 0">当前路径有显式权限</Tag>
            </div>
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
        <Col span="16">
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
            :bordered="false"
            :dis-hover="true"
            class="permission-matrix-card"
          >
            <Row :gutter="12" type="flex" align="middle">
              <Col span="7">
                <Button
                  icon="md-add"
                  type="primary"
                  ghost
                  @click="modalSvnObject = true"
                  >路径授权</Button
                >
              </Col>
              <Col span="9">
                <Input
                  search
                  clearable
                  v-model="searchKeywordRepPathPri"
                  placeholder="通过对象名称、姓名、显示名、邮箱搜索..."
                  @on-change="GetRepPathAllPri"
                />
              </Col>
              <Col span="8">
                <Select v-model="permissionFilter" size="small" style="width: 100%">
                  <Option value="all">全部权限</Option>
                  <Option value="explicit">显式权限</Option>
                  <Option value="wecom">WeCom 同步组</Option>
                  <Option value="deny">禁止权限</Option>
                </Select>
              </Col>
            </Row>
            <div class="permission-list-wrap" :style="{ maxHeight: permissionTableHeight + 'px' }">
              <Spin size="large" fix v-if="loadingRepPathAllPri"></Spin>
              <div
                v-if="!loadingRepPathAllPri && filteredTableDataRepPathAllPri.length == 0"
                class="permission-empty"
              >
                <Icon type="ios-key-outline" />
                <strong>当前路径还没有显式权限</strong>
                <span>点击“路径授权”添加用户、分组或别名。</span>
              </div>
              <div
                v-for="row in filteredTableDataRepPathAllPri"
                :key="buildPermissionRowKey(row)"
                class="permission-list-item"
              >
                <div class="permission-object">
                  <Tag
                    :color="getObjectTypeColor(row)"
                    class="permission-object-tag"
                  >
                    {{ getObjectTypeLabel(row) }}
                  </Tag>
                  <div class="permission-subject">
                    <strong>{{ row.objectLabel || row.objectName }}</strong>
                    <span v-if="row.objectName && row.objectName !== row.objectLabel">{{ row.objectName }}</span>
                  </div>
                  <Tag
                    size="small"
                    class="permission-code-tag"
                    :class="'permission-code-' + getPermissionLabel(row.objectPri)"
                  >
                    {{ getPermissionLabel(row.objectPri) }}
                  </Tag>
                </div>
                <div class="permission-actions">
                  <div class="permission-control">
                    <RadioGroup
                      v-model="row.objectPri"
                      class="permission-radio-group"
                      type="button"
                      size="small"
                      button-style="solid"
                      @on-change="
                        (objectPri) =>
                          ClickRepPathPri(row.objectType, row.invert, row.objectName, objectPri)
                      "
                    >
                      <Radio label="rw">读写</Radio>
                      <Radio label="r">只读</Radio>
                      <Radio label="no">禁止</Radio>
                    </RadioGroup>
                  </div>
                  <div class="permission-extra">
                    <Tooltip
                      content="权限反转"
                      placement="top"
                      :transfer="true"
                      v-if="row.objectType != '*'"
                    >
                      <Switch
                        v-model="row.invert"
                        size="small"
                        @on-change="
                          (invert) =>
                            ClickRepPathPri(row.objectType, invert, row.objectName, row.objectPri)
                        "
                      >
                        <Icon type="md-checkmark" slot="open"></Icon>
                        <Icon type="md-close" slot="close"></Icon>
                      </Switch>
                    </Tooltip>
                    <span v-else class="permission-extra-placeholder"></span>
                    <Tooltip content="删除授权" placement="top" :transfer="true">
                      <Button
                        type="error"
                        ghost
                        icon="md-trash"
                        size="small"
                        @click="DelRepPathPri(row.objectType, row.objectName)"
                      ></Button>
                    </Tooltip>
                  </div>
                </div>
              </div>
            </div>
            <Table
              v-if="false"
              ref="repPathPriTable"
              :height="permissionTableHeight"
              size="small"
              :loading="loadingRepPathAllPri"
              :columns="tableColumnRepPathAllPri"
              :data="filteredTableDataRepPathAllPri"
              @on-column-width-resize="onColumnWidthResize"
              resizable
              style="margin-top: 16px"
            >
              <template slot-scope="{ row }" slot="objectType">
                <Tag
                  color="cyan"
                  v-if="isWeComSubject(row)"
                  style="width: 90px; text-align: center"
                  >WeCom组</Tag
                >
                <Tag
                  color="blue"
                  v-else-if="row.objectType == 'user'"
                  style="width: 90px; text-align: center"
                  >SVN用户</Tag
                >
                <Tag
                  color="geekblue"
                  v-else-if="row.objectType == 'group'"
                  style="width: 90px; text-align: center"
                  >SVN分组</Tag
                >
                <Tag
                  color="purple"
                  v-else-if="row.objectType == 'aliase'"
                  style="width: 90px; text-align: center"
                  >SVN别名</Tag
                >
                <Tag
                  color="red"
                  v-else-if="row.objectType == '*'"
                  style="width: 90px; text-align: center"
                  >所有人</Tag
                >
                <Tag
                  color="magenta"
                  v-else-if="row.objectType == '$authenticated'"
                  style="width: 90px; text-align: center"
                  >所有已认证者</Tag
                >
                <Tag
                  color="volcano"
                  v-else-if="row.objectType == '$anonymous'"
                  style="width: 90px; text-align: center"
                  >所有匿名者</Tag
                >
              </template>
              <template slot-scope="{ row }" slot="objectLabel">
                <div class="permission-subject">
                  <strong>{{ row.objectLabel || row.objectName }}</strong>
                  <span v-if="row.objectName && row.objectName !== row.objectLabel">{{ row.objectName }}</span>
                </div>
              </template>
              <template slot-scope="{ row }" slot="objectPri">
                <div class="permission-value">
                  <Tag
                    :color="getPermissionColor(row.objectPri)"
                    size="small"
                    class="permission-code-tag"
                    :class="'permission-code-' + getPermissionLabel(row.objectPri)"
                  >
                    {{ getPermissionLabel(row.objectPri) }}
                  </Tag>
                </div>
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
    </Drawer>
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
      permissionFilter: "all",
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
          slot: "objectLabel",
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
    drawerContentHeight() {
      return Math.max(420, window.innerHeight - 150);
    },
    permissionTableHeight() {
      return Math.max(300, window.innerHeight - 300);
    },
    filteredTableDataRepPathAllPri() {
      if (this.permissionFilter === "explicit") {
        return this.tableDataRepPathAllPri;
      }
      if (this.permissionFilter === "wecom") {
        return this.tableDataRepPathAllPri.filter((row) => this.isWeComSubject(row));
      }
      if (this.permissionFilter === "deny") {
        return this.tableDataRepPathAllPri.filter((row) => row.objectPri === "no");
      }
      return this.tableDataRepPathAllPri;
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
    getObjectTypeLabel(row) {
      if (this.isWeComSubject(row)) {
        return "WeCom组";
      }
      return this.getObjectTypeText(row && row.objectType);
    },
    getObjectTypeColor(row) {
      if (this.isWeComSubject(row)) {
        return "cyan";
      }
      const map = {
        user: "blue",
        group: "geekblue",
        aliase: "purple",
        "*": "red",
        "$authenticated": "magenta",
        "$anonymous": "volcano",
      };
      return map[row && row.objectType] || "default";
    },
    buildPermissionRowKey(row) {
      if (!row) {
        return "empty";
      }
      return [row.objectType, row.objectName, row.objectLabel].join("-");
    },
    getPermissionLabel(permission) {
      const map = {
        rw: "rw",
        r: "r",
        no: "n",
        n: "n",
      };
      return map[permission] || "未设置";
    },
    getPermissionColor(permission) {
      const map = {
        rw: "success",
        r: "default",
        no: "error",
        n: "error",
      };
      return map[permission] || "default";
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
      const isCurrent = data.fullPath === this.currentRepPath;
      const hasExplicitPri = isCurrent && this.tableDataRepPathAllPri.length > 0;
      const title = data.title || "/";
      const fullPath = data.fullPath || title;
      return h("span", { class: "permission-tree-node" }, [
        h("Icon", {
          props: {
            type:
              data.resourceType == "1"
                ? "ios-document-outline"
                : "ios-folder-open",
          },
          style: { marginRight: "8px" },
        }),
        h("Tooltip", { props: { content: fullPath, placement: "right", transfer: true } }, [
          h("span", { class: "tree-node-title" }, title),
        ]),
        hasExplicitPri
          ? h("Tag", { props: { color: "blue", size: "small" }, class: "tree-node-tag" }, "显式")
          : h("Tag", { props: { size: "small" }, class: "tree-node-tag tree-node-tag-muted" }, "继承"),
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
        return this.NormalizeLazyTreeNodes(data);
      }
      return [
        {
          expand: true,
          contextmenu: true,
          loading: false,
          resourceType: 2,
          title: this.currentRepName + "/",
          fullPath: "/",
          children: [],
        },
      ];
    },
    NormalizeLazyTreeNodes(nodes) {
      return (nodes || []).map((node) => {
        const item = Object.assign({}, node);
        if (String(item.resourceType) == "2") {
          item.contextmenu = item.contextmenu !== false;
          item.loading = false;
          if (!Array.isArray(item.children)) {
            item.children = [];
          }
          item.children = this.NormalizeLazyTreeNodes(item.children);
        } else if (item.children) {
          delete item.children;
        }
        return item;
      });
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
            const data = that.NormalizeLazyTreeNodes(result.data || []);
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
            that.tableDataRepPathAllPri = (result.data || []).map(function (item) {
              item.objectLabel = item.objectLabel || item.objectName;
              return item;
            });
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
    isWeComSubject(row) {
      const name = String((row && (row.objectName || row.objectLabel)) || "").toLowerCase();
      return row && row.objectType == "group" && name.indexOf("wecom_") === 0;
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
.permission-drawer /deep/ .ivu-drawer-body {
  padding: 0;
  background-color: var(--content-bg);
}

.permission-drawer /deep/ .ivu-drawer-header {
  border-bottom: 1px solid #e8eaec;
}

.permission-drawer /deep/ .ivu-drawer-footer {
  border-top: 1px solid #e8eaec;
}

.permission-drawer /deep/ .ivu-drawer-content {
  box-shadow: -12px 0 30px rgba(15, 23, 42, 0.16);
}

.permission-drawer-layout {
  min-height: calc(100vh - 104px);
  padding: 16px;
}

.permission-tree-scroll {
  position: relative;
  padding: 12px;
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
}

.permission-matrix-card {
  min-height: calc(100vh - 172px);
  margin-top: 16px;
}

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

.matrix-table /deep/ .ivu-table-wrapper,
.matrix-table /deep/ .ivu-table,
.matrix-table /deep/ .ivu-table th,
.matrix-table /deep/ .ivu-table td {
  border-left: 0;
  border-right: 0;
}

.matrix-table /deep/ .ivu-table:before,
.matrix-table /deep/ .ivu-table:after {
  display: none;
}

.permission-value {
  margin-bottom: 8px;
}

.permission-value /deep/ .ivu-tag {
  min-width: 54px;
  text-align: center;
  font-family: var(--mono-font);
  font-weight: 700;
}

.permission-list-wrap {
  position: relative;
  min-height: 320px;
  margin-top: 12px;
  overflow-x: hidden;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  background: #fff;
}

.permission-list-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 6px;
  align-items: start;
  min-height: 72px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-color);
}

.permission-list-item:last-child {
  border-bottom: 0;
}

.permission-list-item:hover {
  background: var(--primary-light);
}

.permission-object {
  display: grid;
  grid-template-columns: 76px minmax(0, 1fr) 44px;
  gap: 10px;
  align-items: center;
  min-width: 0;
}

.permission-object-tag {
  width: 70px;
  margin-right: 0;
  text-align: center;
}

.permission-code-tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  min-width: 42px;
  height: 28px;
  margin-right: 0;
  font-family: var(--mono-font);
  font-weight: 700;
  line-height: 1;
  text-align: center;
}

.permission-code-tag /deep/ .ivu-tag-text {
  color: inherit !important;
}

.permission-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding-left: 86px;
}

.permission-control {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
}

.permission-radio-group {
  white-space: nowrap;
}

.permission-radio-group /deep/ .ivu-radio-wrapper {
  min-width: 52px;
  height: 28px;
  line-height: 26px;
  padding: 0 10px;
  text-align: center;
}

.permission-extra {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  min-width: 72px;
}

.permission-extra-placeholder {
  width: 36px;
  height: 1px;
}

.permission-empty {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: center;
  justify-content: center;
  min-height: 260px;
  color: var(--text-light);
  text-align: center;
}

.permission-empty .ivu-icon {
  font-size: 36px;
  color: var(--primary-color);
}

.permission-empty strong {
  color: var(--text-main);
}

.permission-code-rw {
  background: var(--permission-rw-bg) !important;
  border-color: #8ce0b3 !important;
  color: var(--permission-rw-text) !important;
}

.permission-code-r {
  background: var(--permission-r-bg) !important;
  border-color: #d0d5dd !important;
  color: var(--permission-r-text) !important;
}

.permission-code-n {
  background: var(--permission-n-bg) !important;
  border-color: #ffccc7 !important;
  color: var(--permission-n-text) !important;
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

.path-tree-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  padding: 8px 10px;
  border-radius: 6px;
  background: #f8f9fb;
  color: #515a6e;
}

.permission-tree-node {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
}

.tree-node-title {
  display: inline-block;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

.tree-node-tag {
  margin-left: 8px;
}

.tree-node-tag-muted {
  opacity: 0.7;
}

.permission-subject {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  line-height: 1.3;
}

.permission-subject strong {
  overflow: hidden;
  color: var(--text-main);
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.permission-subject span {
  overflow: hidden;
  min-width: 0;
  color: #808695;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 1280px) {
  .permission-actions {
    padding-left: 0;
  }
}
</style>
