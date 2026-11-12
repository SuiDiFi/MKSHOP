import { createRouter, createWebHashHistory } from 'vue-router';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue'),
    meta: { title: '首页' },
  },
  { path: '/goods/:id', name: 'Goods', component: () => import('@/views/Goods.vue'), meta: { title: '商品详情' } },
  { path: '/category', name: 'Category', component: () => import('@/views/Category.vue'), meta: { title: '分类' } },
  { path: '/notices', name: 'NoticeList', component: () => import('@/views/NoticeList.vue'), meta: { title: '商城公告' } },
  { path: '/notice/:id', name: 'NoticeDetail', component: () => import('@/views/ArticleDetail.vue'), meta: { title: '公告详情' } },
  { path: '/help', name: 'Help', component: () => import('@/views/Help.vue'), meta: { title: '帮助中心' } },
  { path: '/help/:id', name: 'HelpDetail', component: () => import('@/views/ArticleDetail.vue'), meta: { title: '帮助详情' } },
  { path: '/member', name: 'Member', component: () => import('@/views/Member.vue'), meta: { title: '会员中心', auth: true } },
  { path: '/money', name: 'Money', component: () => import('@/views/Money.vue'), meta: { title: '资金明细', auth: true } },
  { path: '/kefu', name: 'Kefu', component: () => import('@/views/Kefu.vue'), meta: { title: '在线客服', auth: true } },
  { path: '/login', name: 'Login', component: () => import('@/views/Login.vue'), meta: { title: '登录' } },
  { path: '/register', name: 'Register', component: () => import('@/views/Register.vue'), meta: { title: '注册' } },
  { path: '/orders', name: 'Orders', component: () => import('@/views/Orders.vue'), meta: { title: '我的订单', auth: true } },
  { path: '/order/:sn', name: 'OrderDetail', component: () => import('@/views/OrderDetail.vue'), meta: { title: '订单详情', auth: true } },
  { path: '/commission', name: 'Commission', component: () => import('@/views/Commission.vue'), meta: { title: '分销中心', auth: true } },
  { path: '/vip', name: 'Vip', component: () => import('@/views/Vip.vue'), meta: { title: '分站管理', auth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to) => {
  if (to.meta.auth && !localStorage.getItem('mks_member_token')) {
    return { name: 'Login', query: { redirect: to.fullPath } };
  }
  return true;
});
