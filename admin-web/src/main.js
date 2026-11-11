import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';

import App from './App.vue';
import { router } from './router';
import './styles/main.css';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(ElementPlus, { locale: zhCn });

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component);
}

// ── 全局路由守卫：登录态 + 动态路由加载 ──
router.beforeEach(async (to) => {
  const { useUserStore } = await import('@/store/user');
  const store = useUserStore();
  if (to.name === 'Login') {
    if (store.token) return { path: '/' };
    return true;
  }
  if (!store.token) {
    return { name: 'Login' };
  }
  if (!store.routesLoaded) {
    try {
      await store.fetchInfo();
    } catch (e) {
      // token 失效/后端异常：清 token 再去登录，防止「有 token 访问登录页 → 跳 / → fetchInfo 401」重定向环
      store.logout();
      return { name: 'Login' };
    }
    // 动态路由已注册，重新解析当前地址（否则匹配到注册前的 NotFound 记录）
    return to.fullPath;
  }
  return true;
});

app.mount('#app');
