<template>
  <div>
    <Modal v-model="visible" :draggable="true" :title="title">
      <Tabs type="card" v-model="curTab" @on-click="clickTab">
        <TabPane label="仓库属性" name="attribute">
          <Table
            :show-header="false"
            :columns="detailColumns"
            :data="detailData"
            :loading="detailLoading"
            size="small"
            height="350"
          >
            <template slot-scope="{ index }" slot="copy">
              <Button icon="md-copy" type="text" @click="copyDetail(index)"></Button>
            </template>
            <template slot-scope="{ row }" slot="uuid" v-if="row.repKey == 'UUID' || row.repKey == 'uuid'">
              <Button type="primary" size="small" @click="openSetUUID">重设</Button>
            </template>
          </Table>
        </TabPane>
        <TabPane label="仓库备份" name="backup">
          <Alert type="error" show-icon v-if="!file.on">当前环境PHP未开启文件上传功能</Alert>
          <Row style="margin-bottom: 15px">
            <Col span="15">
              <Tooltip max-width="250" content="以svnadmin dump的方式加入后台任务进行备份" placement="bottom" :transfer="true">
                <Button type="primary" ghost icon="ios-cafe-outline" :loading="dumpLoading" @click="svnadminDump">
                  立即备份
                </Button>
              </Tooltip>
              <Button type="primary" ghost icon="ios-cloud-upload-outline" @click="openUploadBackup">上传备份</Button>
            </Col>
          </Row>
          <Table
            height="300"
            border
            :columns="backupColumns"
            :data="backupData"
            size="small"
            :loading="backupLoading"
          >
            <template slot-scope="{ index, row }" slot="action">
              <Button type="success" size="small" :loading="loadBackupLoading[index]" @click="svnadminLoad(row.fileName, index)">
                恢复
              </Button>
              <Button type="success" size="small" @click="downloadBackup(row.fileUrl)">下载</Button>
              <Button type="error" size="small" @click="deleteBackup(row.fileName)">删除</Button>
            </template>
          </Table>
        </TabPane>
      </Tabs>
      <div slot="footer">
        <Button type="primary" ghost @click="visible = false">取消</Button>
      </div>
    </Modal>

    <Modal v-model="uuidModalVisible" :draggable="true" title="重设仓库UUID">
      <Form :label-width="80" @submit.native.prevent>
        <FormItem label="UUID">
          <Input v-model="tempUUID" placeholder="SVN仓库唯一标识符" />
        </FormItem>
        <FormItem>
          <Button type="primary" :loading="uuidLoading" @click="setUUID">确定</Button>
        </FormItem>
      </Form>
      <div slot="footer">
        <Button type="primary" ghost @click="uuidModalVisible = false">取消</Button>
      </div>
    </Modal>

    <Modal v-model="loadErrorVisible" :draggable="true" title="仓库导入错误">
      <Input v-model="loadError" readonly :rows="15" show-word-limit type="textarea" />
      <div slot="footer">
        <Button type="primary" ghost @click="loadErrorVisible = false">取消</Button>
      </div>
    </Modal>

    <Modal
      v-model="uploadVisible"
      :draggable="true"
      title="仓库备份文件上传"
      @on-visible-change="changeUploadVisible"
    >
      <Form :label-width="80">
        <FormItem label="上传文件">
          <Button type="primary" icon="ios-cloud-upload-outline" ghost @click="clickUploadFile">选择文件</Button>
          <input ref="backupFile" type="file" name="backupFile" accept=".dump" style="display: none" @change="handleFileChange" />
        </FormItem>
        <FormItem label="上传进度">
          <Progress :percent="file.percent" :stroke-width="20" status="active" />
        </FormItem>
        <FormItem label="文件名称"><span style="color: #2d8cf0">{{ file.name }}</span></FormItem>
        <FormItem label="上传体积"><span style="color: #2d8cf0">{{ file.size }}</span></FormItem>
        <FormItem label="当前阶段"><span style="color: red">{{ file.desc }}</span></FormItem>
        <FormItem label="分片大小"><span style="color: #2d8cf0">{{ file.chunkSize }} MB</span></FormItem>
        <FormItem label="剩余时间"><span style="color: #2d8cf0">{{ file.left }}</span></FormItem>
        <FormItem label="分片清理">
          <span style="color: #2d8cf0">
            {{ file.deleteOnMerge == 1 ? "合并完成后服务器自动删除分片" : "合并完成后服务器不自动删除分片" }}
          </span>
        </FormItem>
        <FormItem label="上传控制">
          <Button type="primary" ghost v-if="!file.stop" @click="file.stop = true">暂停</Button>
          <span v-else style="color: red">暂停后需要重新选择文件-已上传分片依然有效</span>
        </FormItem>
      </Form>
      <div slot="footer">
        <Button type="primary" ghost @click="closeUpload">取消</Button>
      </div>
    </Modal>
  </div>
</template>

<script>
import SparkMD5 from "spark-md5";

export default {
  name: "RepoAdvancedSettings",
  data() {
    return {
      visible: false,
      title: "",
      currentRepName: "",
      curTab: "attribute",
      uuidModalVisible: false,
      loadErrorVisible: false,
      uploadVisible: false,
      detailLoading: true,
      backupLoading: true,
      dumpLoading: false,
      uuidLoading: false,
      loadBackupLoading: [],
      tempUUID: "",
      loadError: "",
      file: {
        on: true,
        chunkSize: 1,
        deleteOnMerge: 1,
        percent: 0,
        current: 0,
        stop: true,
        total: 0,
        chunkCount: 0,
        name: "",
        size: "",
        desc: "",
        left: "",
      },
      detailColumns: [
        {
          title: "属性",
          key: "repKey",
        },
        {
          title: "内容",
          key: "repValue",
          tooltip: true,
        },
        {
          title: "复制",
          slot: "copy",
          width: 70,
        },
        {
          title: "操作",
          slot: "uuid",
          width: 70,
        },
      ],
      detailData: [],
      backupColumns: [
        {
          title: "文件名",
          key: "fileName",
          tooltip: true,
        },
        {
          title: "文件大小",
          key: "fileSize",
          width: 100,
        },
        {
          title: "操作",
          slot: "action",
          width: 180,
        },
      ],
      backupData: [],
    };
  },
  methods: {
    open(repName) {
      this.currentRepName = repName;
      this.title = "高级 - " + repName;
      this.visible = true;
      this.curTab = sessionStorage.curTabRepAdvance || "attribute";
      this.clickTab(this.curTab);
    },
    clickTab(name) {
      this.curTab = name || "attribute";
      sessionStorage.setItem("curTabRepAdvance", this.curTab);
      if (this.curTab === "attribute") {
        this.getDetail();
      } else if (this.curTab === "backup") {
        this.getUploadInfo();
        this.getBackupList();
      }
    },
    getDetail() {
      this.detailLoading = true;
      this.$axios
        .post("api.php?c=Svnrep&a=GetRepDetail&t=web", { rep_name: this.currentRepName })
        .then((response) => {
          this.detailLoading = false;
          const result = response.data;
          if (result.status == 1) {
            this.detailData = result.data;
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.detailLoading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    copyDetail(index) {
      const item = this.detailData[index];
      const copyContent = item.repKey + ":" + item.repValue;
      this.$copyText(copyContent).then(
        () => this.$Message.success("复制成功"),
        () => this.$Message.error("复制失败，请手动复制")
      );
    },
    openSetUUID() {
      this.tempUUID = "";
      this.uuidModalVisible = true;
    },
    setUUID() {
      this.uuidLoading = true;
      const data = {
        rep_name: this.currentRepName,
        uuid: this.tempUUID,
      };
      this.$axios
        .post("api.php?c=Svnrep&a=SetUUID&t=web", data)
        .then((response) => {
          this.uuidLoading = false;
          const result = response.data;
          if (result.status == 1) {
            this.$Message.success(result.message);
            this.getDetail();
            this.uuidModalVisible = false;
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.uuidLoading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    getUploadInfo() {
      this.$axios
        .post("api.php?c=Svnrep&a=GetUploadInfo&t=web", {})
        .then((response) => {
          const result = response.data;
          if (result.status == 1) {
            this.file.on = result.data.upload;
            this.file.chunkSize = result.data.chunkSize;
            this.file.deleteOnMerge = result.data.deleteOnMerge;
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    getBackupList() {
      this.backupLoading = true;
      this.backupData = [];
      this.$axios
        .post("api.php?c=Svnrep&a=GetBackupList&t=web", {})
        .then((response) => {
          this.backupLoading = false;
          const result = response.data;
          if (result.status == 1) {
            this.backupData = result.data;
            this.loadBackupLoading = result.data.map(() => false);
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.backupLoading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    svnadminDump() {
      this.dumpLoading = true;
      this.$axios
        .post("api.php?c=Svnrep&a=SvnadminDump&t=web", { rep_name: this.currentRepName })
        .then((response) => {
          this.dumpLoading = false;
          const result = response.data;
          if (result.status == 1) {
            this.$Message.success(result.message);
            this.getBackupList();
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch((error) => {
          this.dumpLoading = false;
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    openUploadBackup() {
      this.resetUploadProgress();
      this.file.stop = false;
      this.uploadVisible = true;
      this.$nextTick(() => {
        if (this.$refs.backupFile) {
          this.$refs.backupFile.value = "";
        }
      });
    },
    clickUploadFile() {
      if (this.$refs.backupFile) {
        this.$refs.backupFile.click();
      }
    },
    handleFileChange(event) {
      const file = event.target.files[0];
      if (!file) {
        return;
      }
      this.resetUploadProgress();
      this.file.stop = false;
      this.changeUpload(file);
    },
    resetUploadProgress() {
      this.file.percent = 0;
      this.file.current = 0;
      this.file.desc = "";
      this.file.left = "";
    },
    async changeUpload(file) {
      const fileSize = file.size;
      const chunkSize = 1024 * 1024 * 1;
      const chunkCount = Math.ceil(fileSize / chunkSize);
      this.file.total = chunkCount * 2;
      this.file.chunkCount = chunkCount;
      this.file.size = this.formatFileSize(fileSize);
      this.file.name = file.name;
      const md5 = await this.getFileMd5(file, chunkSize);

      for (let i = 0; i < chunkCount; i++) {
        const start = i * chunkSize;
        const end = Math.min(fileSize, start + chunkSize);
        const chunkFile = file.slice(start, end);
        const formdata = new FormData();
        formdata.append("file", chunkFile);
        formdata.append("md5", md5);
        formdata.append("filename", file.name);
        formdata.append("numBlobTotal", chunkCount);
        formdata.append("numBlobCurrent", i + 1);
        formdata.append("deleteOnMerge", this.file.deleteOnMerge);

        if (!this.file.stop) {
          await this.uploadBackup(formdata)
            .then((response) => {
              const result = response.data;
              if (result.status == 1) {
                if (result.data.completeCount == this.file.total / 2 - 1) {
                  this.file.desc = "分片合并中";
                } else if (result.data.completeCount == this.file.total / 2) {
                  this.file.desc = "分片合并完成（上传成功）";
                } else {
                  this.file.desc = `${this.file.chunkCount} 个分片上传中`;
                }
                if (result.data.complete) {
                  this.file.percent = 100;
                  const formateTime = this.formatTime(0);
                  this.file.left = `${formateTime[0]}时${formateTime[1]}分${formateTime[2]}秒`;
                  this.$Message.success(result.message);
                  this.getBackupList();
                  this.file.stop = true;
                } else {
                  this.file.current++;
                  this.file.percent = Math.trunc((this.file.current / this.file.total) * 100);
                  const formateTime = this.formatTime(this.file.total - this.file.current);
                  this.file.left = `${formateTime[0]}时${formateTime[1]}分${formateTime[2]}秒`;
                }
              } else {
                this.file.stop = true;
                this.$Message.error({ content: result.message, duration: 2 });
              }
            })
            .catch((error) => {
              this.file.stop = true;
              console.log(error);
              this.$Message.error("出错了 请联系管理员！");
            });
        }

        if (this.file.stop) {
          break;
        }
      }
    },
    uploadBackup(data) {
      return this.$axios.post("api.php?c=Svnrep&a=UploadBackup&t=web", data, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    },
    getFileMd5(file, chunkSize) {
      return new Promise((resolve, reject) => {
        const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice;
        const chunks = Math.ceil(file.size / chunkSize);
        let currentChunk = 0;
        const spark = new SparkMD5.ArrayBuffer();
        const fileReader = new FileReader();

        fileReader.onload = (e) => {
          spark.append(e.target.result);
          currentChunk++;
          if (this.file.stop) {
            return;
          }
          if (currentChunk < chunks) {
            loadNext();
          } else {
            resolve(spark.end());
          }
        };

        fileReader.onerror = (e) => reject(e);

        const loadNext = () => {
          let start = currentChunk * chunkSize;
          let end = start + chunkSize;
          if (end > file.size) {
            end = file.size;
          }
          fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
          this.file.current++;
          this.file.percent = Math.trunc((this.file.current / this.file.total) * 100);
          const formateTime = this.formatTime(this.file.total - this.file.current);
          this.file.left = `${formateTime[0]}时${formateTime[1]}分${formateTime[2]}秒`;
          this.file.desc = `${this.file.chunkCount} 个分片md5计算中`;
        };

        loadNext();
      });
    },
    svnadminLoad(fileName, index) {
      this.$set(this.loadBackupLoading, index, true);
      const data = {
        rep_name: this.currentRepName,
        fileName: fileName,
      };
      this.$axios
        .post("api.php?c=Svnrep&a=SvnadminLoad&t=web", data)
        .then((response) => {
          this.$set(this.loadBackupLoading, index, false);
          const result = response.data;
          if (result.status == 1) {
            this.$Message.success(result.message);
          } else {
            this.$Message.error({ content: result.message, duration: 2 });
            this.loadErrorVisible = true;
            this.loadError = result.data;
          }
        })
        .catch((error) => {
          this.$set(this.loadBackupLoading, index, false);
          console.log(error);
          this.$Message.error("出错了 请联系管理员！");
        });
    },
    downloadBackup(fileUrl) {
      window.open(fileUrl, "_blank");
    },
    deleteBackup(fileName) {
      this.$Modal.confirm({
        title: "删除文件",
        content: "确定要删除该文件吗？<br/>该操作不可逆！",
        onOk: () => {
          this.$axios
            .post("api.php?c=Svnrep&a=DelRepBackup&t=web", { fileName: fileName })
            .then((response) => {
              const result = response.data;
              if (result.status == 1) {
                this.$Message.success(result.message);
                this.getBackupList();
              } else {
                this.$Message.error({ content: result.message, duration: 2 });
              }
            })
            .catch((error) => {
              console.log(error);
              this.$Message.error("出错了 请联系管理员！");
            });
        },
      });
    },
    changeUploadVisible(value) {
      if (!value) {
        this.uploadVisible = false;
        this.file.stop = true;
      }
    },
    closeUpload() {
      this.uploadVisible = false;
      this.file.stop = true;
    },
    formatTime(seconds) {
      let h = parseInt((seconds / 60 / 60) % 24);
      h = h < 10 ? "0" + h : h;
      let m = parseInt((seconds / 60) % 60);
      m = m < 10 ? "0" + m : m;
      let s = parseInt(seconds % 60);
      s = s < 10 ? "0" + s : s;
      return [h, m, s];
    },
    formatFileSize(fileSize) {
      if (fileSize < 1024) {
        return fileSize + "B";
      }
      if (fileSize < 1024 * 1024) {
        return (fileSize / 1024).toFixed(2) + "KB";
      }
      if (fileSize < 1024 * 1024 * 1024) {
        return (fileSize / (1024 * 1024)).toFixed(2) + "MB";
      }
      return (fileSize / (1024 * 1024 * 1024)).toFixed(2) + "GB";
    },
  },
};
</script>
