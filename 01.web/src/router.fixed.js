const routers = [
    {
        name: 'login',
        path: '/login',
        meta: {
            title: '',
            requireAuth: false
        },
        component: (resolve) => require(['@/views/login/index.vue'], resolve)
    },
    {
        name: 'index',
        path: '/',
        meta: {
            title: '首页',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/index/index.vue'], resolve)
    },
    {
        name: 'repositoryInfo',
        path: '/repositoryInfo',
        meta: {
            title: 'SVN仓库',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/repositoryInfo/index.vue'], resolve)
    },
    {
        name: 'repositoryUser',
        path: '/repositoryUser',
        meta: {
            title: 'SVN用户',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/repositoryUser/index.vue'], resolve)
    },
    {
        name: 'repositoryGroup',
        path: '/repositoryGroup',
        meta: {
            title: 'SVN分组',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/repositoryGroup/index.vue'], resolve)
    },
    {
        name: 'subadmin',
        path: '/subadmin',
        meta: {
            title: '子管理员',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/subadmin/index.vue'], resolve)
    },
    {
        name: 'wecom',
        path: '/wecom',
        meta: {
            title: '企业微信',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/wecom/index.vue'], resolve)
    },
    {
        name: 'crond',
        path: '/crond',
        meta: {
            title: '任务计划',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/crond/index.vue'], resolve)
    },
    {
        name: 'logs',
        path: '/logs',
        meta: {
            title: '系统日志',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/logs/index.vue'], resolve)
    },
    {
        name: 'distribute',
        path: '/distribute',
        meta: {
            title: '运维',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/distribute/index.vue'], resolve)
    },
    {
        name: 'setting',
        path: '/setting',
        meta: {
            title: '系统配置',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/setting/index.vue'], resolve)
    },
    {
        name: 'personal',
        path: '/personal',
        meta: {
            title: '个人中心',
            requireAuth: true
        },
        component: (resolve) => require(['@/views/personal/index.vue'], resolve)
    }
];

export default routers;
