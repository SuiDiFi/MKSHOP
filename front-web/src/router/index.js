import { createRouter, createWebHashHistory } from 'vue-router';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue'),
    meta: { title: '首页' },
  },
  { path: '/goods/:id', name: 'Goods', component: () => import('@/views/Goods.vue'), meta: { title: '商品详情' } },
  { path: '/login', name: 'Login', component: () => import('@/views/Login.vue'), meta: { title: '登录' } },
  { path: '/register', name: 'Register', component: () => import('@/views/Register.vue'), meta: { title: '注册' } },
  { path: '/orders', name: 'Orders', component: () => import('@/views/Orders.vue'), meta: { title: '我的订单', auth: true } },
  { path: '/order/:sn', name: 'OrderDetail', component: () => import('@/views/OrderDetail.vue'), meta: { title: '订单详情', auth: true } },
  { path: '/commission', name: 'Commission', component: () => import('@/views/Commission.vue'), meta: { title: '分销中心', auth: true } },
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
