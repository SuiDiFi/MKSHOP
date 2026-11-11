import { createRouter, createWebHashHistory } from 'vue-router';
import Layout from '@/layout/index.vue';

// 静态路由
export const staticRoutes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login.vue'),
    meta: { title: '登录' },
  },
  {
    // DIY 编辑器（全屏工作台，无菜单入口，由 diyList 跳转）
    path: '/diy/editor/:id',
    name: 'DiyEditor',
    component: () => import('@/views/diy/diyEditor.vue'),
    meta: { title: 'DIY 编辑器' },
  },
];

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    ...staticRoutes,
    {
      path: '/',
      name: 'Layout',
      component: Layout,
      redirect: '/index',
      children: [],
    },
    // 兜底：普通路由（不可 redirect 到 /index，登录前动态路由未注册会形成无限重定向递归）
    { path: '/:pathMatch(.*)*', name: 'NotFound', component: () => import('@/views/placeholder.vue') },
  ],
});

// 视图组件映射（菜单 component 字段 '@/views/xxx' → 实际组件；未实现页面落到占位符）
const viewModules = import.meta.glob('../views/**/*.vue');

export function resolveView(componentPath) {
  if (!componentPath) return null;
  let p = String(componentPath).replace(/^@\//, '').replace(/\/+$/, '');
  const direct = viewModules[`../${p}.vue`];
  if (direct) return direct;
  const idx = viewModules[`../${p}/index.vue`];
  if (idx) return idx;
  return () => import('@/views/placeholder.vue');
}

/**
 * 按后端菜单树注册动态路由（登录后调用）
 * 菜单节点：{ id, parentId, name, path, component, hidden, children }
 */
export function registerDynamicRoutes(menus) {
  const added = [];
  const pathMap = {}; // menuId → 完整路由路径（供侧边栏 el-menu router 定位）

  function build(nodes, parentPath) {
    for (const node of nodes) {
      const children = node.children || [];
      const selfPath = node.path?.startsWith('/')
        ? node.path
        : `${parentPath}/${node.path || ''}`.replace(/\/+$/, '') || parentPath;

      if (node.component && node.component !== 'Layout' && node.path) {
        const view = resolveView(node.component);
        pathMap[node.id] = selfPath;
        const route = {
          path: selfPath,
          name: `M${node.id}`,
          component: view,
          meta: { title: node.title || node.name, icon: node.icon, menuId: node.id },
        };
        router.addRoute('Layout', route);
        added.push(route);
      }
      if (children.length > 0) build(children, selfPath);
    }
  }

  build(menus || [], '');
  import('@/store/user').then(({ useUserStore }) => {
    useUserStore().menuPathMap = pathMap;
  });
  return added;
}
