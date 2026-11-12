import { createRouter, createWebHashHistory } from 'vue-router';

const routes = [
  { path: '/', name: 'Home', component: () => import('@/views/Home.vue'), meta: { title: '首页', tab: true } },
  { path: '/category', name: 'Category', component: () => import('@/views/Category.vue'), meta: { title: '分类', tab: true } },
  { path: '/goods/:id', name: 'Goods', component: () => import('@/views/Goods.vue'), meta: { title: '商品详情' } },
  { path: '/login', name: 'Login', component: () => import('@/views/Login.vue'), meta: { title: '登录' } },
  { path: '/register', name: 'Register', component: () => import('@/views/Register.vue'), meta: { title: '注册' } },
  { path: '/orders', name: 'Orders', component: () => import('@/views/Orders.vue'), meta: { title: '我的订单', tab: true, auth: true } },
  { path: '/order/:sn', name: 'OrderDetail', component: () => import('@/views/OrderDetail.vue'), meta: { title: '订单详情', auth: true } },
  { path: '/member', name: 'Member', component: () => import('@/views/Member.vue'), meta: { title: '我的', tab: true, auth: true } },
  { path: '/money', name: 'Money', component: () => import('@/views/Money.vue'), meta: { title: '资金明细', auth: true } },
  { path: '/notices', name: 'Notices', component: () => import('@/views/Notices.vue'), meta: { title: '商城公告' } },
  { path: '/notice/:id', name: 'NoticeDetail', component: () => import('@/views/ArticleDetail.vue'), meta: { title: '公告详情' } },
  { path: '/help', name: 'Help', component: () => import('@/views/Help.vue'), meta: { title: '帮助中心' } },
  { path: '/help/:id', name: 'HelpDetail', component: () => import('@/views/ArticleDetail.vue'), meta: { title: '帮助详情' } },
  { path: '/kefu', name: 'Kefu', component: () => import('@/views/Kefu.vue'), meta: { title: '在线客服', auth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} - MKSHOP` : 'MKSHOP 商城';
  if (to.meta.auth && !localStorage.getItem('mks_member_token')) {
    return { name: 'Login', query: { redirect: to.fullPath } };
  }
  return true;
});
