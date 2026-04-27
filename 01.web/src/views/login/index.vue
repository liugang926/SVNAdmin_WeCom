<template>
  <div class="login-wrapper">
    <div class="login-container">
      <!-- 左侧：品牌展示区 -->
      <div class="login-brand">
        <div class="brand-content">
          <h1 class="brand-title">SVNAdmin Professional</h1>
          <p class="brand-desc">企业级 Subversion 可视化管理平台</p>
          <div class="feature-list">
            <div class="feature-item">
              <Icon type="md-checkmark-circle-outline" />
              <span>深度集成企业微信通知</span>
            </div>
            <div class="feature-item">
              <Icon type="md-checkmark-circle-outline" />
              <span>精细化权限矩阵管理</span>
            </div>
            <div class="feature-item">
              <Icon type="md-checkmark-circle-outline" />
              <span>自动化钩子工作流</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧：登录表单区 -->
      <div class="login-form-area">
        <div class="login-card">
          <div class="login-header">
            <h2>欢迎回来</h2>
            <p>请登录您的账号以继续管理</p>
          </div>
          
          <Form
            ref="formUserLogin"
            :model="formUserLogin"
            :rules="ruleValidateLogin"
            @keydown.enter.native="Submit('formUserLogin')"
            label-position="top"
          >
            <FormItem prop="user_name" label="用户名">
              <Input
                v-model="formUserLogin.user_name"
                size="large"
                placeholder="请输入用户名"
                prefix="ios-person-outline"
              />
            </FormItem>
            
            <FormItem prop="user_pass" label="密码">
              <Input
                type="password"
                password
                size="large"
                v-model="formUserLogin.user_pass"
                placeholder="请输入密码"
                prefix="ios-lock-outline"
              />
            </FormItem>

            <FormItem label="身份角色">
              <Select
                v-model="formUserLogin.user_role"
                size="large"
                :transfer="true"
                @on-change="ChangeSelect"
              >
                <Option value="1">管理人员 (Administrator)</Option>
                <Option value="3">子管理员 (Sub-Admin)</Option>
                <Option value="2">SVN用户 (User)</Option>
              </Select>
            </FormItem>

            <FormItem prop="code" v-if="verifyOption" label="验证码">
              <div class="captcha-wrapper">
                <Input
                  v-model="formUserLogin.code"
                  size="large"
                  placeholder="验证码"
                  class="captcha-input"
                />
                <img
                  @click="GetVerifyCode"
                  :src="formUserLogin.base64"
                  class="captcha-img"
                />
              </div>
            </FormItem>

            <FormItem style="margin-top: 32px">
              <Button
                type="primary"
                size="large"
                long
                @click="Submit('formUserLogin')"
                :loading="loadingLogin"
                class="login-btn"
              >
                立即登录
              </Button>
            </FormItem>
          </Form>
          
          <div class="login-footer">
            <p>© 2026 SVNAdmin WeCom Edition. All rights reserved.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="less" scoped>
.login-wrapper {
  height: 100vh;
  width: 100vw;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.login-container {
  width: 1000px;
  height: 640px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
  display: flex;
  overflow: hidden;
}

.login-brand {
  flex: 1.2;
  background-color: #2d8cf0;
  background-image: linear-gradient(150deg, #2d8cf0 0%, #17233d 100%);
  padding: 60px;
  color: #fff;
  display: flex;
  align-items: center;
}

.brand-title {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 8px;
}

.brand-desc {
  font-size: 16px;
  opacity: 0.8;
  margin-bottom: 48px;
}

.feature-list {
  .feature-item {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    font-size: 14px;
    opacity: 0.9;
    i {
      margin-right: 12px;
      font-size: 18px;
    }
  }
}

.login-form-area {
  flex: 1;
  padding: 40px 60px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-header {
  margin-bottom: 32px;
  h2 {
    font-size: 24px;
    color: #17233d;
    margin-bottom: 8px;
  }
  p {
    color: #808695;
    font-size: 14px;
  }
}

.captcha-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.captcha-img {
  height: 36px;
  width: 120px;
  cursor: pointer;
  border-radius: 4px;
  border: 1px solid #dcdee2;
}

.login-btn {
  height: 44px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(45, 140, 240, 0.3);
}

.login-footer {
  margin-top: 32px;
  text-align: center;
  color: #c5c8ce;
  font-size: 12px;
}

/deep/ .ivu-form-item-label {
  font-weight: 600;
  color: #515a6e;
  padding-bottom: 8px;
}

/deep/ .ivu-input-large {
  border-radius: 8px;
}
</style>

<script>
export default {
  data() {
    return {
      /**
       * 加载
       */
      loadingGetVerifyCode: "loading......",
      loadingLogin: false,

      /**
       * 组件状态
       */
      verifyOption: false,

      /**
       * 表单
       */
      // 登录表单
      formUserLogin: {
        user_name: "",
        user_pass: "",
        user_role: "",
        code: "",
        uuid: "",
        base64: "",
      },

      /**
       * 校验规则
       */
      // 登录校验规则
      ruleValidateLogin: {
        user_name: [
          { required: true, message: "用户名不能为空", trigger: "blur" },
        ],
        user_pass: [
          { required: true, message: "密码不能为空", trigger: "blur" },
        ],
        code: [{ required: true, message: "验证码不能为空", trigger: "blur" }],
      },
    };
  },
  computed: {},
  created() {},
  mounted() {
    var that = this;
    //还原下拉
    that.formUserLogin.user_role = localStorage.user_role
      ? localStorage.user_role
      : "2";
    if (sessionStorage.token) {
      that.$Message.success("已有登录信息 自动跳转中...");
      setTimeout(function () {
        that.$router.push({ name: sessionStorage.firstRoute });
      }, 2000);
    } else {
      that.GetVerifyOption();
    }
  },
  methods: {
    //记录下拉
    ChangeSelect(value) {
      localStorage.setItem("user_role", value);
    },
    //表单提交
    Submit(formName) {
      this.$refs[formName].validate((valid) => {
        if (valid) {
          this.Login();
        } else {
          return false;
        }
      });
    },
    /**
     * 获取验证码选项
     */
    GetVerifyOption() {
      var that = this;
      var data = {};
      that.$axios
        .post("api.php?c=Setting&a=GetVerifyOption&t=web", data)
        .then(function (response) {
          var result = response.data;
          if (result.status == 1) {
            if (result.data.enable == true) {
              that.verifyOption = true;
              that.GetVerifyCode();
            } else {
              that.verifyOption = false;
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
     * 请求验证码
     */
    GetVerifyCode() {
      var that = this;
      that.formUserLogin.base64 = "";
      that.loadingGetVerifyCode = "loading......";
      var data = {};
      that.$axios
        .post("api.php?c=Common&a=GetVerifyCode&t=web", data)
        .then(function (response) {
          var result = response.data;
          if (result.status == 1) {
            that.formUserLogin.uuid = result.data.uuid;
            that.formUserLogin.base64 = result.data.base64;
          } else {
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
    //登录
    Login() {
      var that = this;
      that.loadingLogin = true;
      var data = {
        user_name: that.formUserLogin.user_name,
        user_pass: that.formUserLogin.user_pass,
        user_role: that.formUserLogin.user_role,
        uuid: that.formUserLogin.uuid,
        code: that.formUserLogin.code,
      };
      that.$axios
        .post("api.php?c=Common&a=Login&t=web", data)
        .then(function (response) {
          that.loadingLogin = false;
          var result = response.data;
          if (result.status == 1) {
            //存储
            sessionStorage.setItem("token", result.data.token);
            sessionStorage.setItem("user_name", result.data.user_name);
            sessionStorage.setItem("user_role_id", result.data.user_role_id);
            sessionStorage.setItem(
              "user_role_name",
              result.data.user_role_name
            );
            sessionStorage.setItem("route", JSON.stringify(result.data.route));
            sessionStorage.setItem(
              "functions",
              JSON.stringify(result.data.functions)
            );

            that.$Message.success(result.message);

            if (result.data.user_role_id == 1) {
              //管理员跳转到首页
              sessionStorage.setItem("firstRoute", "index");
            } else if (result.data.user_role_id == 2) {
              //用户跳转到仓库页
              sessionStorage.setItem("firstRoute", "repositoryInfo");
            } else if (result.data.user_role_id == 3) {
              //子管理员跳转到有权限的首个页面
              sessionStorage.setItem(
                "firstRoute",
                result.data.route.children[0].name
              );
            }
            that.$router.push({ name: sessionStorage.firstRoute });
          } else {
            that.GetVerifyOption();
            that.$Message.error({ content: result.message, duration: 2 });
          }
        })
        .catch(function (error) {
          that.loadingLogin = false;
          console.log(error);
          that.$Message.error("出错了 请联系管理员！");
        });
    },
  },
};
</script>
