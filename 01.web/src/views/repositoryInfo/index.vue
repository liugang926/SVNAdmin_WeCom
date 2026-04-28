<template>
  <div class="repository-page">
    <Card :bordered="false" :dis-hover="true">
      <ServiceStatusBanner :items="serviceStatusItems" />
      <Row class="repo-toolbar" type="flex" align="middle" :gutter="16">
        <Col :flex="1">
          <div class="action-bar">
            <!-- 核心管理组 -->
            <ButtonGroup class="mr-10">
              <Button
                icon="md-add"
                type="primary"
                @click="ModalCreateRep"
                v-if="user_role_id == 1 || user_role_id == 3"
                >新建仓库</Button
              >
            </ButtonGroup>

            <!-- 同步更新组 -->
            <ButtonGroup class="mr-10" v-if="user_role_id == 1 || user_role_id == 3">
              <Tooltip max-width="250" content="仅扫描磁盘仓库列表" placement="bottom" :transfer="true">
                <Button icon="ios-sync" @click="GetRepList(true, true, false, false)">同步列表</Button>
              </Tooltip>
              <Tooltip max-width="250" content="同步磁盘仓库列表及体积、版本信息（耗时）" placement="bottom" :transfer="true">
                <Button icon="md-refresh-circle" @click="GetRepList(true, true, true, true)">全量同步</Button>
              </Tooltip>
            </ButtonGroup>

            <!-- 维护组 -->
            <ButtonGroup class="mr-10" v-if="user_role_id == 1 || user_role_id == 3">
              <Tooltip content="检测 authz 配置文件合法性" placement="bottom" :transfer="true">
                <Button icon="ios-hammer-outline" @click="CheckAuthz">Authz 检测</Button>
              </Tooltip>
            </ButtonGroup>

            <!-- 用户端同步 -->
            <Button
              v-if="user_role_id == 2"
              icon="ios-sync"
              type="warning"
              ghost
              @click="GetSvnUserRepList(true)"
              >同步列表</Button
            >
          </div>
        </Col>
        <Col span="6">
          <div class="repo-search-box">
            <Input
              search
              enter-button
              placeholder="搜索仓库名、备注..."
              @on-search="submitRepoSearch"
              @on-change="handleRepoSuggestInput"
              @keydown.native="handleRepoSuggestKeydown"
              @on-focus="openRepoSuggestions"
              v-model="searchKeywordRep"
              v-if="user_role_id == 1 || user_role_id == 3"
              class="search-input"
            />
            <Input
              search
              enter-button
              placeholder="搜索仓库名..."
              @on-search="submitRepoSearch"
              @on-change="handleRepoSuggestInput"
              @keydown.native="handleRepoSuggestKeydown"
              @on-focus="openRepoSuggestions"
              v-model="searchKeywordRep"
              v-if="user_role_id == 2"
              class="search-input"
            />
            <div v-if="repoSuggestVisible" class="repo-suggest-panel">
              <div v-if="repoSuggestLoading" class="repo-suggest-state">正在查找...</div>
              <template v-else>
                <div
                  v-for="(item, index) in repoSuggestions"
                  :key="item.rep_name + '-' + index"
                  class="repo-suggest-item"
                  :class="{ active: repoSuggestActiveIndex === index }"
                  @mousedown.prevent="selectRepoSuggestion(item)"
                >
                  <div class="suggest-main">
                    <Icon type="ios-folder" />
                    <strong v-html="highlightSuggestText(item.rep_name)"></strong>
                    <Tag size="small" v-if="item.matchLabel">{{ item.matchLabel }}</Tag>
                  </div>
                  <div class="suggest-meta">
                    <span v-if="item.rep_note" v-html="highlightSuggestText(item.rep_note)"></span>
                    <span v-if="item.rep_rev">版本 {{ item.rep_rev }}</span>
                    <span v-if="item.rep_size">体积 {{ item.rep_size }}</span>
                  </div>
                </div>
                <div v-if="repoSuggestions.length === 0" class="repo-empty-state">
                  <div class="repo-empty-illustration">
                    <Icon type="ios-search" />
                  </div>
                  <strong>没有找到相关仓库</strong>
                  <span>试试换个关键词？</span>
                </div>
              </template>
            </div>
          </div>
        </Col>
      </Row>
      
      <!-- 管理人员仓库列表 -->
      <div v-if="user_role_id == 1 || user_role_id == 3" class="table-container">
        <div class="repo-table-skeleton" v-if="loadingRep && tableDataRep.length > 0">
          <div v-for="n in 6" :key="'rep-skeleton-' + n" class="skeleton-row">
            <span></span><span></span><span></span><span></span>
          </div>
        </div>
        <Table
          ref="repTable"
          @on-sort-change="SortChangeRep"
          @on-column-width-resize="onColumnWidthResize"
          :columns="tableColumnRep"
          :data="tableDataRep"
          :loading="loadingRep"
          :row-class-name="getRepoRowClassName"
          resizable
          size="small"
          class="modern-table"
          :class="{ 'table-soft-loading': loadingRep && tableDataRep.length > 0 }"
        >
        <template slot-scope="{ index }" slot="index">
          <span class="index-cell">{{ pageSizeRep * (pageCurrentRep - 1) + index + 1 }}</span>
        </template>
        <template slot-scope="{ row, index }" slot="rep_rev">
          <div class="sync-cell">
            <span class="value-text">{{ row.rep_rev || '-' }}</span>
            <Icon
              type="ios-refresh"
              class="sync-btn"
              :class="{ 'ani-rotate': row.loading_rep_rev }"
              @click="SyncRepRev(row.rep_name, index)"
            />
          </div>
        </template>
        <template slot-scope="{ row, index }" slot="rep_size">
          <div class="sync-cell">
            <span class="value-text">{{ row.rep_size || '-' }}</span>
            <Icon
              type="ios-refresh"
              class="sync-btn"
              :class="{ 'ani-rotate': row.loading_rep_size }"
              @click="SyncRepSize(row.rep_name, index)"
            />
          </div>
        </template>
        <template slot-scope="{ row, index }" slot="rep_note">
          <div class="note-input-wrap">
            <Icon
              v-if="!isRepNoteSaving(row.rep_name) && !isRepNoteSaved(row.rep_name)"
              type="md-create"
              class="note-edit-icon"
            />
            <Input
              ghost
              class="note-input"
              v-model="tableDataRep[index].rep_note"
              @on-blur="UpdRepNote(index, row.rep_name)"
              @on-enter="UpdRepNote(index, row.rep_name)"
              placeholder="点击添加备注..."
            />
            <Icon
              v-if="isRepNoteSaving(row.rep_name)"
              type="ios-loading"
              class="note-saving-icon ani-rotate"
            />
            <Icon
              v-else-if="isRepNoteSaved(row.rep_name)"
              type="md-checkmark-circle"
              class="note-saved-icon"
            />
          </div>
        </template>
        <template slot-scope="{ row }" slot="action_main">
          <div class="repo-action-group">
            <Button
              size="small"
              type="primary"
              ghost
              icon="ios-folder-open"
              class="repo-action-button"
              @click="OpenRepoExplorer(row.rep_name)"
              >浏览</Button
            >
            <Button
              size="small"
              type="info"
              ghost
              icon="md-key"
              class="repo-action-button"
              @click="ModalRepPri(row.rep_name)"
              >权限</Button
            >
            <Button
              size="small"
              type="success"
              ghost
              icon="ios-git-branch"
              class="repo-action-button"
              @click="OpenRepoHookManager(row.rep_name)"
              >钩子</Button
            >
          </div>
        </template>
        <template slot-scope="{ row }" slot="action_more">
          <Dropdown trigger="click" transfer placement="bottom-end" @on-click="handleMoreAction($event, row.rep_name)">
            <Button size="small" icon="ios-more" class="repo-more-button"></Button>
            <DropdownMenu slot="list" class="repo-more-menu">
              <DropdownItem name="advance"><Icon type="md-settings" /> 高级设置</DropdownItem>
              <DropdownItem name="edit"><Icon type="md-create" /> 重命名</DropdownItem>
              <DropdownItem name="delete" style="color: #ed4014"><Icon type="md-trash" /> 删除仓库</DropdownItem>
            </DropdownMenu>
          </Dropdown>
        </template>
        </Table>
      </div>
      
      <!-- 用户仓库列表 -->
      <Table
        v-if="user_role_id == 2"
        @on-sort-change="SortChangeUserRep"
        :loading="loadingUserRep"
        :columns="tableColumnUserRep"
        :data="tableDataUserRep"
        :row-class-name="getRepoRowClassName"
        size="small"
      >
        <template slot-scope="{ index }" slot="index">
          {{ pageSizeUserRep * (pageCurrentUserRep - 1) + index + 1 }}
        </template>
        <template slot-scope="{ row }" slot="second_pri">
          <Button
            :disabled="!row.second_pri"
            type="info"
            size="small"
            @click="
              ModalRepPriUser(
                row.rep_name,
                row.svnn_user_pri_path_id,
                row.pri_path
              )
            "
            >配置</Button
          >
        </template>
        <template slot-scope="{ row }" slot="action">
          <Button
            type="info"
            size="small"
            @click="OpenRepoExplorerUser(row.rep_name, row.pri_path)"
            >浏览</Button
          >
          <Button
            type="info"
            size="small"
            v-if="enableCheckout == 'http'"
            @click="OpenRepoExplorerRaw(row.raw_url)"
            >原生浏览</Button
          >
        </template>
      </Table>
      <!-- 管理人员SVN仓库分页 -->
      <Card
        :bordered="false"
        :dis-hover="true"
        v-if="user_role_id == 1 || user_role_id == 3"
      >
        <Page
          v-if="totalRep != 0"
          :total="totalRep"
          :current="pageCurrentRep"
          :page-size="pageSizeRep"
          @on-page-size-change="PageSizeChangeRep"
          @on-change="PageChangeRep"
          size="small"
          show-sizer
        />
      </Card>
      <!-- 用户SVN仓库分页 -->
      <Card :bordered="false" :dis-hover="true" v-if="user_role_id == 2">
        <Page
          v-if="totalUserRep != 0"
          :total="totalUserRep"
          :current="pageCurrentUserRep"
          :page-size="pageSizeUserRep"
          @on-page-size-change="PageSizeChangeUserRep"
          @on-change="PageChangeUserRep"
          size="small"
          show-sizer
        />
      </Card>
    </Card>
    <!-- 对话框-新建SVN仓库 -->
    <Modal v-model="modalCreateRep" :draggable="true" title="新建SVN仓库">
      <Form :model="formRepAdd" :label-width="80">
        <FormItem label="仓库名称">
          <Input v-model="formRepAdd.rep_name"></Input>
        </FormItem>
        <FormItem>
          <Alert type="warning" show-icon
            >仓库名称只能包含中文、字母、数字、破折号、下划线、点，不能以点开头或结尾</Alert
          >
        </FormItem>
        <FormItem label="备注信息">
          <Input v-model="formRepAdd.rep_note"></Input>
        </FormItem>
        <FormItem label="仓库类型">
          <RadioGroup vertical v-model="formRepAdd.rep_type">
            <Radio label="1">
              <Icon type="social-apple"></Icon>
              <span>空仓库</span>
            </Radio>
            <Radio label="2">
              <Icon type="social-android"></Icon>
              <span>指定结构的仓库(包含 "trunk" "branches" "tags" 文件夹)</span>
            </Radio>
          </RadioGroup>
        </FormItem>
        <FormItem>
          <Button type="primary" @click="CreateRep" :loading="loadingCreateRep"
            >确定</Button
          >
        </FormItem>
      </Form>
      <div slot="footer">
        <Button type="primary" ghost @click="modalCreateRep = false"
          >取消</Button
        >
      </div>
    </Modal>
    <RepoExplorer ref="repoExplorer" :svnserve-ready="formStatusSubversion.status == true" />
    <RepoHookManager ref="repoHookManager" />
    <RepoAdvancedSettings ref="repoAdvancedSettings" />
    <!-- 对话框-编辑仓库名称 -->
    <Modal
      v-model="modalEditRepName"
      :draggable="true"
      :title="titleModalEditRepName"
    >
      <Form :model="formRepEdit" :label-width="80">
        <FormItem label="仓库名称">
          <Input v-model="formRepEdit.new_rep_name"></Input>
        </FormItem>
        <FormItem>
          <Button
            type="primary"
            :loading="loadingEditRepName"
            @click="UpdRepName"
            >确定</Button
          >
        </FormItem>
      </Form>
      <div slot="footer">
        <Button type="primary" ghost @click="modalEditRepName = false"
          >取消</Button
        >
      </div>
    </Modal>
    <!-- 对话框-authz检测结果 -->
    <Modal v-model="modalValidateAuthz" title="authz检测结果">
      <Input
        v-model="tempmodalValidateAuthz"
        readonly
        :rows="15"
        show-word-limit
        type="textarea"
      />
      <div slot="footer">
        <Button type="primary" ghost @click="modalValidateAuthz = false"
          >取消</Button
        >
      </div>
    </Modal>
    <RepoPermission
      :visible="modalRepPri"
      :current-rep-name="currentRepName"
      :current-rep-path="currentRepPath"
      :svnn-user-pri-path-id="svnn_user_pri_path_id"
      @close="CloseModalRepPri"
      @path-change="ChangeCurrentRepPath"
    />
    <Modal
      v-model="deleteConfirm.visible"
      title="删除仓库"
      :draggable="true"
      class-name="delete-confirm-modal"
      @on-visible-change="handleDeleteConfirmVisible"
    >
      <Alert type="error" show-icon>
        删除仓库不可逆。请输入仓库名以确认删除。
      </Alert>
      <div class="delete-confirm-repo">
        <span>目标仓库</span>
        <strong>{{ deleteConfirm.repName }}</strong>
      </div>
      <Input
        v-model="deleteConfirm.input"
        :placeholder="'请输入 ' + deleteConfirm.repName"
        @on-enter="ConfirmDeleteRep"
      />
      <div slot="footer">
        <Button @click="deleteConfirm.visible = false">取消</Button>
        <Button
          type="error"
          :loading="deleteConfirm.loading"
          :disabled="deleteConfirm.input !== deleteConfirm.repName"
          @click="ConfirmDeleteRep"
        >
          确认删除
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script>
import ServiceStatusBanner from "@/components/ServiceStatusBanner.vue";
import RepoAdvancedSettings from "./components/RepoAdvancedSettings.vue";
import RepoExplorer from "./components/RepoExplorer.vue";
import RepoHookManager from "./components/RepoHookManager.vue";
import RepoPermission from "./components/RepoPermission.vue";

export default {
  data() {
    return {
      /**
       * 权限相关
       */
      token: sessionStorage.token,
      user_role_id: sessionStorage.user_role_id,

      /**
       * 当前启用协议
       */
      enableCheckout: "passwd",

      /**
       * 对话框
      */
      //新建SVN仓库
      modalCreateRep: false,
      //编辑仓库信息
      modalEditRepName: false,
      //显示authz检测结果
      modalValidateAuthz: false,
      //仓库权限
      modalRepPri: false,

      /**
       * 排序数据
       */
      //获取仓库列表
      sortNameGetRepList: "rep_name",
      sortTypeGetRepList: "asc",

      //获取SVN用户有权限的仓库路径列表
      sortNameGetSvnUserRepList: "",
      sortTypeGetSvnUserRepList: "asc",

      /**
       * 分页数据
       */
      //所有仓库
      pageCurrentRep: 1,
      pageSizeRep: 20,
      totalRep: 0,
      //用户仓库
      pageCurrentUserRep: 1,
      pageSizeUserRep: 10,
      totalUserRep: 0,

      /**
       * 搜索关键词
       */
      searchKeywordRep: "",
      repoSuggestions: [],
      repoSuggestVisible: false,
      repoSuggestLoading: false,
      repoSuggestActiveIndex: -1,
      repoSuggestTimer: null,
      repoSuggestDebounceDelay: 300,
      repoSuggestRequestKeyword: "",
      highlightedRepName: "",
      noteSavingMap: {},
      noteSavedMap: {},

      /**
       * 加载
       */
      //所有仓库列表
      loadingRep: true,
      //创建仓库
      loadingCreateRep: false,
      //用户仓库列表
      loadingUserRep: true,
      //修改仓库名称
      loadingEditRepName: false,

      /**
       * 临时变量
       */
      //临时选中的仓库名称
      currentRepName: "",
      //当前仓库路径
      currentRepPath: "",
      //选中的id
      svnn_user_pri_path_id: -1,
      //authz检测结果
      tempmodalValidateAuthz: "",

      /**
       * 对话框标题
       */
      //编辑仓库名称
      titleModalEditRepName: "",

      /**
       * 表单
       */
      //新建SVN仓库
      formRepAdd: {
        rep_name: "",
        rep_note: "",
        rep_type: "1",
      },
      //编辑仓库
      formRepEdit: {
        old_rep_name: "",
        new_rep_name: "",
      },
      //页头提示信息
      formStatusSubversion: {
        status: true,
        info: "",
      },
      deleteConfirm: {
        visible: false,
        repName: "",
        input: "",
        loading: false,
      },

      /**
       * 表格
       */
      //所有仓库
      tableColumnRep: [
        {
          title: "序号",
          slot: "index",
          fixed: "left",
          width: 70,
          align: "center",
          resizable: false,
        },
        {
          title: "仓库名",
          key: "rep_name",
          tooltip: true,
          sortable: "custom",
          width: 160,
          resizable: true,
        },
        {
          title: "版本",
          slot: "rep_rev",
          sortable: "custom",
          width: 100,
          resizable: true,
        },
        {
          title: "体积",
          slot: "rep_size",
          sortable: "custom",
          width: 110,
          resizable: true,
        },
        {
          title: "备注信息",
          slot: "rep_note",
          minWidth: 200,
          resizable: true,
        },
        {
          title: "核心操作",
          slot: "action_main",
          width: 250,
          align: "center",
          resizable: false,
        },
        {
          title: "更多",
          slot: "action_more",
          width: 70,
          align: "center",
          resizable: false,
        },
      ],
      tableDataRep: [],
      //SVN用户仓库
      tableColumnUserRep: [
        {
          title: "序号",
          slot: "index",
          fixed: "left",
          minWidth: 80,
        },
        {
          title: "仓库名",
          key: "rep_name",
          tooltip: true,
          sortable: "custom",
          minWidth: 120,
        },
        {
          title: "路径/文件",
          tooltip: true,
          key: "pri_path",
          minWidth: 120,
        },
        {
          title: "权限",
          key: "rep_pri",
          minWidth: 120,
        },
        {
          title: "二次授权",
          slot: "second_pri",
          minWidth: 120,
        },
        {
          title: "其它",
          slot: "action",
          width: 180,
          // fixed:"right"
        },
      ],
      tableDataUserRep: [],
    };
  },
  components: {
    ServiceStatusBanner,
    RepoAdvancedSettings,
    RepoExplorer,
    RepoHookManager,
    RepoPermission,
  },
  computed: {
    serviceStatusItems() {
      return [
        {
          key: "svnserve",
          type: "error",
          visible: this.formStatusSubversion.status == false,
          message: this.formStatusSubversion.info,
        },
      ];
    },
  },

  mounted() {
    this.GetSvnserveStatus();
    this.loadColumnWidths();
    if (this.user_role_id == 1 || this.user_role_id == 3) {
      this.GetRepList();
    } else if (this.user_role_id == 2) {
      this.GetSvnUserRepList();
    }
  },
  methods: {
    submitRepoSearch() {
      this.closeRepoSuggestions();
      if (this.user_role_id == 1 || this.user_role_id == 3) {
        this.pageCurrentRep = 1;
        this.GetRepList();
      } else {
        this.pageCurrentUserRep = 1;
        this.GetSvnUserRepList();
      }
    },
    openRepoSuggestions() {
      if (this.searchKeywordRep) {
        this.handleRepoSuggestInput();
      }
    },
    closeRepoSuggestions() {
      this.repoSuggestVisible = false;
      this.repoSuggestActiveIndex = -1;
    },
    handleRepoSuggestInput() {
      clearTimeout(this.repoSuggestTimer);
      const keyword = String(this.searchKeywordRep || "").trim();
      if (!keyword) {
        this.repoSuggestions = [];
        this.closeRepoSuggestions();
        return;
      }
      this.repoSuggestVisible = true;
      this.repoSuggestTimer = setTimeout(() => {
        this.loadRepoSuggestions(keyword);
      }, this.repoSuggestDebounceDelay);
    },
    handleRepoSuggestKeydown(event) {
      if (!this.repoSuggestVisible) {
        return;
      }
      if (event.key === "ArrowDown") {
        event.preventDefault();
        if (this.repoSuggestions.length > 0) {
          this.repoSuggestActiveIndex =
            (this.repoSuggestActiveIndex + 1) % this.repoSuggestions.length;
        }
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        if (this.repoSuggestions.length > 0) {
          this.repoSuggestActiveIndex =
            (this.repoSuggestActiveIndex - 1 + this.repoSuggestions.length) %
            this.repoSuggestions.length;
        }
      } else if (event.key === "Enter") {
        if (this.repoSuggestActiveIndex >= 0 && this.repoSuggestions[this.repoSuggestActiveIndex]) {
          event.preventDefault();
          this.selectRepoSuggestion(this.repoSuggestions[this.repoSuggestActiveIndex]);
        }
      } else if (event.key === "Escape") {
        this.closeRepoSuggestions();
      }
    },
    loadRepoSuggestions(keyword) {
      this.repoSuggestRequestKeyword = keyword;
      const localRows = this.getCurrentRepoRows();
      const localSuggestions = this.rankRepoSuggestions(localRows, keyword);
      this.repoSuggestions = localSuggestions.slice(0, 10);
      this.repoSuggestActiveIndex = this.repoSuggestions.length > 0 ? 0 : -1;

      if (keyword.length < 2) {
        return;
      }

      this.repoSuggestLoading = true;
      const isAdmin = this.user_role_id == 1 || this.user_role_id == 3;
      const data = isAdmin
        ? {
            pageSize: 10,
            currentPage: 1,
            searchKeyword: keyword,
            sortName: this.sortNameGetRepList,
            sortType: this.sortTypeGetRepList,
            sync: false,
            page: true,
            sync_size: false,
            sync_rev: false,
          }
        : {
            pageSize: 10,
            currentPage: 1,
            searchKeyword: keyword,
            sortType: this.sortTypeGetSvnUserRepList,
            sync: false,
            page: true,
          };

      this.$axios
        .post(
          isAdmin
            ? "api.php?c=Svnrep&a=GetRepList&t=web"
            : "api.php?c=Svnrep&a=GetSvnUserRepList&t=web",
          data
        )
        .then((response) => {
          if (this.repoSuggestRequestKeyword !== keyword) {
            return;
          }
          this.repoSuggestLoading = false;
          const result = response.data;
          if (result.status == 1) {
            const remoteRows = (result.data && result.data.data) || [];
            const merged = this.mergeRepoSuggestions(localSuggestions, remoteRows);
            this.repoSuggestions = this.rankRepoSuggestions(merged, keyword).slice(0, 10);
            this.repoSuggestActiveIndex = this.repoSuggestions.length > 0 ? 0 : -1;
          }
        })
        .catch((error) => {
          if (this.repoSuggestRequestKeyword !== keyword) {
            return;
          }
          this.repoSuggestLoading = false;
          console.log(error);
        });
    },
    getCurrentRepoRows() {
      return this.user_role_id == 1 || this.user_role_id == 3
        ? this.tableDataRep || []
        : this.tableDataUserRep || [];
    },
    mergeRepoSuggestions(localRows, remoteRows) {
      const map = {};
      localRows.concat(remoteRows || []).forEach((item) => {
        if (item && item.rep_name) {
          map[item.rep_name] = Object.assign({}, map[item.rep_name] || {}, item);
        }
      });
      return Object.keys(map).map((key) => map[key]);
    },
    rankRepoSuggestions(rows, keyword) {
      const q = String(keyword || "").toLowerCase();
      return (rows || [])
        .map((row) => {
          const name = String(row.rep_name || "").toLowerCase();
          const note = String(row.rep_note || "").toLowerCase();
          let score = -1;
          let matchLabel = "";
          if (name === q) {
            score = 100;
            matchLabel = "完全匹配";
          } else if (name.indexOf(q) === 0) {
            score = 80;
            matchLabel = "名称前缀";
          } else if (name.indexOf(q) > -1) {
            score = 60;
            matchLabel = "名称包含";
          } else if (note.indexOf(q) > -1) {
            score = 40;
            matchLabel = "备注";
          }
          return Object.assign({}, row, { _suggestScore: score, matchLabel: matchLabel });
        })
        .filter((row) => row._suggestScore >= 0)
        .sort((a, b) => {
          if (b._suggestScore !== a._suggestScore) {
            return b._suggestScore - a._suggestScore;
          }
          return String(a.rep_name || "").localeCompare(String(b.rep_name || ""));
        });
    },
    escapeHtml(value) {
      return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },
    escapeRegExp(value) {
      return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    },
    highlightSuggestText(value) {
      const text = this.escapeHtml(value);
      const keyword = String(this.searchKeywordRep || "").trim();
      if (!keyword) {
        return text;
      }
      const pattern = new RegExp("(" + this.escapeRegExp(this.escapeHtml(keyword)) + ")", "ig");
      return text.replace(pattern, '<mark class="suggest-highlight">$1</mark>');
    },
    selectRepoSuggestion(item) {
      if (!item || !item.rep_name) {
        return;
      }
      this.searchKeywordRep = item.rep_name;
      this.closeRepoSuggestions();
      const existsInCurrentPage = this.getCurrentRepoRows().some((row) => row.rep_name === item.rep_name);
      if (existsInCurrentPage) {
        this.highlightRepoRow(item.rep_name);
        return;
      }
      if (this.user_role_id == 1 || this.user_role_id == 3) {
        this.pageCurrentRep = 1;
        this.GetRepList();
      } else {
        this.pageCurrentUserRep = 1;
        this.GetSvnUserRepList();
      }
      this.$nextTick(() => {
        setTimeout(() => this.highlightRepoRow(item.rep_name), 600);
      });
    },
    highlightRepoRow(repName) {
      this.highlightedRepName = repName;
      setTimeout(() => {
        if (this.highlightedRepName === repName) {
          this.highlightedRepName = "";
        }
      }, 2200);
    },
    getRepoRowClassName(row) {
      return row && row.rep_name === this.highlightedRepName ? "repo-row-highlight" : "";
    },
    /**
     * 列宽调整相关方法
     */
    onColumnWidthResize(newWidth, oldWidth, column, event) {
      // 保存列宽到本地存储
      this.saveColumnWidth(column.key || column.slot, newWidth);
    },

    loadColumnWidths() {
      // 从本地存储加载列宽设置
      const savedWidths = localStorage.getItem('svn_repository_column_widths');
      if (savedWidths) {
        try {
          const widths = JSON.parse(savedWidths);
          this.tableColumnRep.forEach(column => {
            const key = column.key || column.slot;
            if (widths[key] && column.resizable !== false) {
              this.$set(column, "width", widths[key]);
            }
          });
        } catch (e) {
          console.warn('Failed to load column widths:', e);
        }
      }
    },

    saveColumnWidth(columnKey, width) {
      if (!columnKey || !width) {
        return;
      }
      // 保存单个列宽到本地存储
      const savedWidths = localStorage.getItem('svn_repository_column_widths');
      let widths = {};
      if (savedWidths) {
        try {
          widths = JSON.parse(savedWidths);
        } catch (e) {
          console.warn('Failed to parse saved column widths:', e);
        }
      }
      widths[columnKey] = width;
      localStorage.setItem('svn_repository_column_widths', JSON.stringify(widths));
    },
    handleMoreAction(action, repName) {
      if (!action || !repName) {
        return;
      }
      switch (action) {
        case "advance":
          this.ModalRepAdvance(repName);
          break;
        case "edit":
          this.ModalEditRepName(repName);
          break;
        case "delete":
          this.DelRep(repName);
          break;
        default:
          break;
      }
    },

    /**
     * 子组件 modalRepPri 传递变量给父组件
     */
    CloseModalRepPri() {
      this.modalRepPri = false;
    },
    /**
     * 子组件 modalRepPri 传递变量给父组件
     */
    ChangeCurrentRepPath(value) {
      this.currentRepPath = value;
    },
    OpenRepoHookManager(repName) {
      if (this.$refs.repoHookManager) {
        this.$refs.repoHookManager.open(repName);
      }
    },
    OpenRepoExplorer(repName) {
      if (this.$refs.repoExplorer) {
        this.$refs.repoExplorer.openAdmin(repName);
      }
    },
    OpenRepoExplorerUser(repName, priPath) {
      if (this.$refs.repoExplorer) {
        this.$refs.repoExplorer.openUser(repName, priPath);
      }
    },
    OpenRepoExplorerRaw(rowUrl) {
      if (this.$refs.repoExplorer) {
        this.$refs.repoExplorer.openRaw(rowUrl);
      }
    },

    /**
     * 获取svnserve运行状态
     */
    GetSvnserveStatus() {
      var that = this;
      var data = {};
      that.$axios
        .post("api.php?c=Svnrep&a=GetSvnserveStatus&t=web", data)
        .then(function (response) {
          var result = response.data;
          if (result.status == 1) {
            if (!result.data) {
              that.formStatusSubversion.status = result.data;
              that.formStatusSubversion.info = result.message;
            }
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },

    /**
     * 检测 authz 文件状态
     */
    CheckAuthz() {
      var that = this;
      var data = {};
      that.$axios
        .post("api.php?c=Svnrep&a=CheckAuthz&t=web", data)
        .then(function (response) {
          var result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
          } else if (result.status == 2) {
            that.$Message.error({ content: result.message, duration: 2 });
            that.modalValidateAuthz = true;
            that.tempmodalValidateAuthz = result.data;
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },

    /**
     * 添加仓库
     */
    ModalCreateRep() {
      this.modalCreateRep = true;
    },
    CreateRep() {
      var that = this;
      that.loadingCreateRep = true;
      var data = {
        rep_name: that.formRepAdd.rep_name,
        rep_note: that.formRepAdd.rep_note,
        rep_type: that.formRepAdd.rep_type,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=CreateRep&t=web", data)
        .then(function (response) {
          that.loadingCreateRep = false;
          var result = response.data;
          if (result.status == 1) {
            that.modalCreateRep = false;
            that.$Message.success(result.message);
            that.GetRepList();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingCreateRep = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },

    GetRepList(sync = false, page = true, sync_size = false, sync_rev = false) {
      var that = this;
      that.loadingRep = true;
      that.tableDataRep = [];
      // that.totalRep = 0;
      var data = {
        pageSize: that.pageSizeRep,
        currentPage: that.pageCurrentRep,
        searchKeyword: that.searchKeywordRep,
        sortName: that.sortNameGetRepList,
        sortType: that.sortTypeGetRepList,
        sync: sync,
        page: page,
        sync_size: sync_size,
        sync_rev: sync_rev,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=GetRepList&t=web", data)
        .then(function (response) {
          that.loadingRep = false;
          var result = response.data;
          if (result.status == 1) {
            // that.$Message.success(result.message);
            that.tableDataRep = result.data.data;
            that.totalRep = result.data.total;
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingRep = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    SyncRepSize(rep_name, index) {
      var that = this;
      if (that.tableDataRep[index]) {
        that.tableDataRep[index].loading_rep_size = true;
      }
      var data = {
        rep_name: rep_name,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=SyncRepSize&t=web", data)
        .then(function (response) {
          that.loadingRep = false;
          if (that.tableDataRep[index]) {
            that.tableDataRep[index].loading_rep_size = false;
          }
          var result = response.data;
          if (result.status == 1) {
            // that.$Message.success(result.message);
            that.GetRepList();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          if (that.tableDataRep[index]) {
            that.tableDataRep[index].loading_rep_size = false;
          }
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    SyncRepRev(rep_name, index) {
      var that = this;
      if (that.tableDataRep[index]) {
        that.tableDataRep[index].loading_rep_rev = true;
      }
      var data = {
        rep_name: rep_name,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=SyncRepRev&t=web", data)
        .then(function (response) {
          that.loadingRep = false;
          if (that.tableDataRep[index]) {
            that.tableDataRep[index].loading_rep_rev = false;
          }
          var result = response.data;
          if (result.status == 1) {
            // that.$Message.success(result.message);
            that.GetRepList();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          if (that.tableDataRep[index]) {
            that.tableDataRep[index].loading_rep_rev = false;
          }
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    /**
     * 所有仓库列表页码改变
     */
    PageChangeRep(value) {
      //设置当前页数
      this.pageCurrentRep = value;
      this.GetRepList();
    },
    /**
     * 所有仓库列表每页数量改变
     */
    PageSizeChangeRep(value) {
      //设置每页条数
      this.pageSizeRep = value;
      this.GetRepList();
    },
    /**
     * 所有仓库排序
     */
    SortChangeRep(value) {
      var sortNameGetRepList;
      try {
        sortNameGetRepList = value.key;
      } catch (error) {
        if (error instanceof TypeError) {
          sortNameGetRepList = value.slot;
        } else {
          throw error;
        }
      }
      // this.sortNameGetRepList =
      //   value.key !== undefined ? value.key : value.slot;
      if (value.order == "desc" || value.order == "asc") {
        this.sortTypeGetRepList = value.order;
      }
      this.GetRepList();
    },

    /**
     * 获取用户仓库列表
     */
    GetSvnUserRepList(sync = false, page = true) {
      var that = this;
      that.loadingUserRep = true;
      that.tableDataUserRep = [];
      that.totalUserRep = 0;
      var data = {
        pageSize: that.pageSizeUserRep,
        currentPage: that.pageCurrentUserRep,
        searchKeyword: that.searchKeywordRep,
        sortType: that.sortTypeGetSvnUserRepList,
        sync: sync,
        page: page,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=GetSvnUserRepList&t=web", data)
        .then(function (response) {
          that.loadingUserRep = false;
          var result = response.data;
          if (result.status == 1) {
            // that.$Message.success(result.message);
            that.tableDataUserRep = result.data.data;
            that.totalUserRep = result.data.total;
            that.enableCheckout = result.data.enableCheckout;
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingUserRep = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    /**
     * 用户仓库列表页码改变
     */
    PageChangeUserRep(value) {
      //设置当前页数
      this.pageCurrentUserRep = value;
      this.GetSvnUserRepList();
    },
    /**
     * 用户仓库每页数量改变
     */
    PageSizeChangeUserRep(value) {
      //设置每页条数
      this.pageSizeUserRep = value;
      this.GetSvnUserRepList();
    },
    /**
     * 用户仓库排序
     */
    SortChangeUserRep(value) {
      if (value.order == "desc" || value.order == "asc") {
        this.sortTypeGetSvnUserRepList = value.order;
      }
      this.GetSvnUserRepList();
    },

    /**
     * 编辑仓库备注信息
     */
    UpdRepNote(index, rep_name) {
      var that = this;
      if (that.noteSavingMap[rep_name] || !that.tableDataRep[index]) {
        return;
      }
      var data = {
        rep_name: rep_name,
        rep_note: that.tableDataRep[index].rep_note,
      };
      that.$set(that.noteSavingMap, rep_name, true);
      that.$axios
        .post("api.php?c=Svnrep&a=UpdRepNote&t=web", data)
        .then(function (response) {
          var result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.$set(that.noteSavedMap, rep_name, true);
            setTimeout(() => {
              that.$set(that.noteSavedMap, rep_name, false);
            }, 1600);
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
          that.$set(that.noteSavingMap, rep_name, false);
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
          that.$set(that.noteSavingMap, rep_name, false);
        });
    },
    isRepNoteSaving(rep_name) {
      return !!this.noteSavingMap[rep_name];
    },
    isRepNoteSaved(rep_name) {
      return !!this.noteSavedMap[rep_name];
    },

    /**
     * 仓库权限
     */
    ModalRepPri(rep_name) {
      //通过按钮点击浏览 初始化路径和仓库名称
      this.currentRepPath = "/";
      this.currentRepName = rep_name;
      //显示对话框
      this.modalRepPri = true;
    },

    /**
     * SVN用户配置仓库权限
     */
    ModalRepPriUser(rep_name, svnn_user_pri_path_id, pri_path) {
      //通过按钮点击浏览 初始化路径和仓库名称
      this.currentRepPath = pri_path;
      this.currentRepName = rep_name;
      //SVN用户权限路径id
      this.svnn_user_pri_path_id = svnn_user_pri_path_id;
      //显示对话框
      this.modalRepPri = true;
    },

    /**
     * 高级
     */
    ModalRepAdvance(rep_name) {
      if (this.$refs.repoAdvancedSettings) {
        this.$refs.repoAdvancedSettings.open(rep_name);
      }
    },

    /**
     * 编辑仓库名称
     */
    ModalEditRepName(rep_name) {
      //备份旧名称
      this.formRepEdit.old_rep_name = JSON.parse(JSON.stringify(rep_name));
      //设置新名称
      this.formRepEdit.new_rep_name = JSON.parse(JSON.stringify(rep_name));
      //配置标题
      this.titleModalEditRepName = "修改仓库名称 - " + rep_name;
      //显示对话框
      this.modalEditRepName = true;
    },
    UpdRepName() {
      var that = this;
      that.loadingEditRepName = true;
      var data = {
        old_rep_name: that.formRepEdit.old_rep_name,
        new_rep_name: that.formRepEdit.new_rep_name,
      };
      that.$axios
        .post("api.php?c=Svnrep&a=UpdRepName&t=web", data)
        .then(function (response) {
          that.loadingEditRepName = false;
          var result = response.data;
          if (result.status == 1) {
            that.$Message.success(result.message);
            that.modalEditRepName = false;
            that.GetRepList();
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingEditRepName = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },

    /**
     * 删除仓库
     */
    DelRep(rep_name) {
      this.deleteConfirm.visible = true;
      this.deleteConfirm.repName = rep_name;
      this.deleteConfirm.input = "";
      this.deleteConfirm.loading = false;
    },
    handleDeleteConfirmVisible(visible) {
      if (!visible) {
        this.deleteConfirm.repName = "";
        this.deleteConfirm.input = "";
        this.deleteConfirm.loading = false;
      }
    },
    ConfirmDeleteRep() {
      if (!this.deleteConfirm.repName || this.deleteConfirm.input !== this.deleteConfirm.repName) {
        return;
      }
      this.deleteConfirm.loading = true;
      this.$axios
        .post("api.php?c=Svnrep&a=DelRep&t=web", { rep_name: this.deleteConfirm.repName })
        .then((response) => {
          this.deleteConfirm.loading = false;
          const result = response.data;
          if (result.status == 1) {
            this.$Message.success(result.message);
            this.deleteConfirm.visible = false;
            this.GetRepList();
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.deleteConfirm.loading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    
    /**
     * 处理重置列宽
     */
    handleResetColumnWidth() {
      if (this.$refs.repTable) {
        this.$refs.repTable.resetColumnWidths();
      }
    },
    
    /**
     * 处理列可见性变化
     */
    handleColumnVisibilityChange(visibleColumnKeys) {
      this.visibleTableColumns = this.tableColumnRep.filter(column => {
        const key = column.key || column.slot;
        return visibleColumnKeys.includes(key);
      });
    },
  },
};
</script>

<style lang="less" scoped>
.repository-page {
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
}

.repo-toolbar {
  margin-bottom: var(--space-lg);
}

.action-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.mr-10 {
  margin-right: 0;
}

.search-input {
  /deep/ .ivu-input {
    border-radius: 6px 0 0 6px;
  }
}

.repo-search-box {
  position: relative;
  width: 100%;
}

.repo-suggest-panel {
  position: absolute;
  top: 38px;
  left: 0;
  right: 0;
  z-index: 50;
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.repo-suggest-item {
  padding: 10px var(--space-md);
  cursor: pointer;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.2s;
}

.repo-suggest-item:last-child {
  border-bottom: none;
}

.repo-suggest-item:hover,
.repo-suggest-item.active {
  background: var(--primary-light);
}

.suggest-main {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  color: var(--text-main);
}

.suggest-main /deep/ .suggest-highlight,
.suggest-meta /deep/ .suggest-highlight {
  color: var(--primary-color);
  background: rgba(45, 140, 240, 0.12);
  border-radius: 3px;
  padding: 0 2px;
  font-weight: 700;
}

.suggest-meta {
  display: flex;
  gap: 10px;
  margin-top: 4px;
  color: var(--text-light);
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.repo-suggest-state {
  padding: var(--space-md);
  color: var(--text-light);
  text-align: center;
}

.repo-empty-state {
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: var(--space-xs);
  padding: var(--space-lg) var(--space-md);
  color: var(--text-light);
  text-align: center;
}

.repo-empty-state strong {
  color: var(--text-main);
}

.repo-empty-illustration {
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #f0f7ff;
  color: var(--primary-color);
  font-size: 28px;
}

.table-container {
  position: relative;
  margin-top: var(--space-sm);
}

.repo-table-skeleton {
  position: absolute;
  left: 0;
  right: 0;
  top: 41px;
  bottom: 0;
  z-index: 2;
  padding: var(--space-md);
  background: rgba(255, 255, 255, 0.72);
  backdrop-filter: blur(2px);
  pointer-events: none;
}

.skeleton-row {
  display: grid;
  grid-template-columns: 12% 24% 18% 1fr;
  gap: var(--space-md);
  height: 34px;
  align-items: center;
}

.skeleton-row span {
  height: 10px;
  border-radius: 10px;
  background: linear-gradient(90deg, #f2f4f7 25%, #e8ecf2 37%, #f2f4f7 63%);
  background-size: 400% 100%;
  animation: skeleton-shimmer 1.4s ease infinite;
}

@keyframes skeleton-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: 0 0; }
}

.table-soft-loading /deep/ .ivu-table-body {
  filter: blur(1px);
  opacity: 0.55;
}

.modern-table /deep/ .ivu-table-cell {
  padding-left: 10px;
  padding-right: 10px;
}

.modern-table /deep/ th {
  height: 40px;
}

.modern-table /deep/ td {
  height: 48px;
}

.modern-table /deep/ .ivu-table-wrapper,
.modern-table /deep/ .ivu-table,
.modern-table /deep/ .ivu-table th,
.modern-table /deep/ .ivu-table td,
/deep/ .ivu-table th,
/deep/ .ivu-table td {
  border-left: 0 !important;
  border-right: 0 !important;
}

.modern-table /deep/ .ivu-table:before,
.modern-table /deep/ .ivu-table:after,
/deep/ .ivu-table:before,
/deep/ .ivu-table:after {
  display: none;
}

/deep/ .repo-row-highlight td {
  background-color: #fff7e6 !important;
  transition: background-color 0.3s;
}

.repo-action-group {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: nowrap;
  white-space: nowrap;
  gap: 6px;
  min-width: 216px;
  width: 100%;
}

.repo-action-button {
  flex: 0 0 68px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 68px;
  height: 28px;
  line-height: 26px;
  padding: 0;
  white-space: nowrap;
  border-radius: 4px;
}

.repo-action-button /deep/ span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.repo-action-button /deep/ .ivu-icon {
  margin-right: 3px;
}

.repo-more-button {
  width: 30px;
  padding: 0;
}

.repo-more-menu /deep/ .ivu-dropdown-item {
  min-width: 112px;
}

/* 现代化表格内部单元格 */
.index-cell {
  color: var(--text-light);
  font-weight: 500;
}

.sync-cell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  .value-text {
    color: var(--text-main);
    font-family: monospace;
  }
  .sync-btn {
    font-size: 18px;
    color: var(--text-light);
    cursor: pointer;
    transition: all 0.3s;
    &:hover {
      color: var(--primary-color);
    }
  }
}

.ani-rotate {
  animation: ani-rotate 1.5s linear infinite;
  color: var(--primary-color) !important;
}

@keyframes ani-rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.note-input {
  width: 100%;
  /deep/ .ivu-input {
    background: transparent;
    border: 1px transparent dashed;
    transition: all 0.2s;
    &:hover, &:focus {
      background: #fff;
      border-color: var(--border-color);
      padding-right: 30px;
    }
  }
}

.note-input-wrap {
  position: relative;
}

.note-edit-icon {
  position: absolute;
  right: 8px;
  top: 50%;
  z-index: 1;
  margin-top: -7px;
  color: var(--text-light);
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.note-input-wrap:hover .note-edit-icon {
  opacity: 1;
}

.note-saving-icon {
  position: absolute;
  right: 8px;
  top: 50%;
  margin-top: -7px;
  pointer-events: none;
}

.note-saved-icon {
  position: absolute;
  right: 8px;
  top: 50%;
  margin-top: -7px;
  color: #19be6b;
  pointer-events: none;
  animation: note-success-pop 0.28s ease-out;
}

@keyframes note-success-pop {
  0% { transform: scale(0.65); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.delete-confirm-repo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: var(--space-md) 0 var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  border-radius: 6px;
  background: #f8f9fb;
}

.delete-confirm-repo span {
  color: var(--text-light);
}

.delete-confirm-repo strong {
  color: #ed4014;
  font-family: monospace;
}

</style>

<style lang="less">
.my-modal {
  // 卡片
  .ivu-card-body {
    padding: 0px 16px 0px 16px;
  }

  // 分割线
  .ivu-divider-inner-text {
    // color: #2db7f5;
    color: #5cadff;
  }
  // 列表
  .ivu-list-split .ivu-list-item {
    border-bottom: 0px;
  }
  .ivu-list-item {
    padding: 2px 0px;
  }
  //列表选项颜色
  .ivu-list-item-meta-title {
    color: #515a6e;
  }
  .ivu-list-item-meta-description {
    // color: #2db7f5;
    color: #ff9900;
  }
  //编辑和移除按钮
  span {
    color: #515a6e;
  }
}
</style>

<style>
</style>
