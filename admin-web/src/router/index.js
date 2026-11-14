import { createRouter, createWebHashHistory } from 'vue-router';
import Layout from '@/layout/index.vue';
import progress from '@/utils/progress';
import { useSettingsStore } from '@/store/settings';

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

// 菜单 component 字段 '@/views/xxx' → 视图模块键；null 表示该页尚未实现
function viewKey(componentPath) {
  if (!componentPath) return null;
  const p = String(componentPath).replace(/^@\//, '').replace(/\/+$/, '');
  if (viewModules[`../${p}.vue`]) return `../${p}.vue`;
  if (viewModules[`../${p}/index.vue`]) return `../${p}/index.vue`;
  return null;
}

export function resolveView(componentPath) {
  const key = viewKey(componentPath);
  return key ? viewModules[key] : () => import('@/views/placeholder.vue');
}

// 同 path 竞争时的优先级：组件已实现 > 菜单可见
function rank(node) {
  return (viewKey(node.component) ? 2 : 0) + (Number(node.hidden) === 1 ? 0 : 1);
}

/**
 * 按后端菜单树注册动态路由（登录后调用）
 * 菜单节点：{ id, parentId, name, path, component, hidden, children }
 */
export function registerDynamicRoutes(menus) {
  const added = [];
  const pathMap = {}; // menuId → 完整路由路径（供侧边栏 el-menu router 定位）
  const pending = new Map(); // selfPath → 择优后占用该 path 的菜单节点

  function collect(nodes, parentPath) {
    for (const node of nodes) {
      const children = node.children || [];
      // 父级可能是首页容器（path='/'），拼接后必须折叠重复斜杠，否则注册成 //index
      const selfPath = node.path?.startsWith('/')
        ? node.path
        : `${parentPath}/${node.path || ''}`.replace(/\/{2,}/g, '/').replace(/\/+$/, '') || parentPath;

      if (node.component && node.component !== 'Layout' && node.path) {
        pathMap[node.id] = selfPath;
        const prev = pending.get(selfPath);
        // 菜单表存在同 path 的遗留行（component 指向未实现视图、或本身是 hidden 副本）；
        // 若不择优，遗留行会先注册并把真实页面遮成占位符、或让页签标题与侧栏不一致。
        if (!prev || rank(node) > rank(prev)) pending.set(selfPath, node);
      }
      if (children.length > 0) collect(children, selfPath);
    }
  }

  collect(menus || [], '');

  for (const [selfPath, node] of pending) {
    const route = {
      path: selfPath,
      name: `M${node.id}`,
      component: resolveView(node.component),
      meta: {
        title: node.title || node.name,
        icon: node.icon,
        menuId: node.id,
        // 标签页行为对齐 KASUSHOU：首页不可关闭；隐藏页（编辑页等）不入标签
        noClosable: Number(node.noClosable) === 1 || node.name === 'Index',
        tabHidden: Number(node.hidden) === 1 || Number(node.tabHidden) === 1,
        // 隐藏页高亮归属菜单（商品新增/编辑 → /goods/list）
        activeMenu: node.activeMenu || '',
      },
    };
    router.addRoute('Layout', route);
    added.push(route);
  }

  import('@/store/user').then(({ useUserStore }) => {
    useUserStore().menuPathMap = pathMap;
  });
  return added;
}

// ── 顶部进度条（主题配置可关）──
// 守卫执行时 pinia 已安装（导航发生在 app.mount 之后），可直接取 store
router.beforeEach(() => {
  let on = true;
  try {
    on = useSettingsStore().showProgressBar;
  } catch (e) {
    on = true;
  }
  if (on) progress.start();
  return true;
});
router.afterEach(() => progress.done());
