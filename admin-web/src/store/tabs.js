import { defineStore } from 'pinia';

/**
 * 多标签页（对齐 KASUSHOU 后台 tags-view）
 * 取证要点：标签持久化（对方存 _kss_data_.tabs.visitedRoutes）、首页 noClosable、
 *          「更多」下拉提供 关闭其他/左侧/右侧/全部（对方无右键菜单、无右键刷新）
 */
const KEY = 'mks_admin_tabs';

/** 固定首页标签：不可关闭（对方 meta.noClosable） */
const HOME = { path: '/index', fullPath: '/index', name: 'Index', title: '首页', icon: 'home-2-line', noClosable: true };

function load() {
  try {
    const raw = localStorage.getItem(KEY);
    const list = raw ? JSON.parse(raw) : [];
    if (!Array.isArray(list)) return [HOME];
    const merged = list.filter((v) => v && v.path);
    if (!merged.some((v) => v.path === HOME.path)) merged.unshift(HOME);
    return merged;
  } catch (e) {
    return [HOME];
  }
}

function persist(list) {
  try {
    localStorage.setItem(KEY, JSON.stringify(list.map((v) => ({ ...v }))));
  } catch (e) {
    /* 隐私模式写失败不影响功能 */
  }
}

export const useTabsStore = defineStore('tabs', {
  state: () => ({ visitedViews: load() }),
  getters: {
    /** 当前激活标签 */
    activePath: (s) => (s.visitedViews.find((v) => v.path === s.current) ? s.current : s.visitedViews[0]?.path || ''),
  },
  actions: {
    /** 路由变化时登记标签（meta.tabHidden 的页面不入列） */
    addView(route) {
      this.current = route.path;
      if (route.meta?.tabHidden) return;
      if (this.visitedViews.some((v) => v.path === route.path)) return;
      this.visitedViews.push({
        path: route.path,
        fullPath: route.fullPath,
        name: route.name,
        title: route.meta?.title || '未命名',
        icon: route.meta?.icon || '',
        noClosable: route.meta?.noClosable === true,
      });
      persist(this.visitedViews);
    },
    async close(path) {
      const i = this.visitedViews.findIndex((v) => v.path === path);
      if (i < 0 || this.visitedViews[i].noClosable) return null;
      const [removed] = this.visitedViews.splice(i, 1);
      persist(this.visitedViews);
      // 返回下一个应跳转的标签（对方行为：关当前后跳相邻）
      const next = this.visitedViews[i] || this.visitedViews[i - 1];
      return next ? next.fullPath || next.path : null;
    },
    closeOthers(path) {
      this.visitedViews = this.visitedViews.filter((v) => v.noClosable || v.path === path);
      persist(this.visitedViews);
    },
    closeLeft(path) {
      const i = this.visitedViews.findIndex((v) => v.path === path);
      if (i < 0) return;
      this.visitedViews = this.visitedViews.filter((v, idx) => idx >= i || v.noClosable);
      persist(this.visitedViews);
    },
    closeRight(path) {
      const i = this.visitedViews.findIndex((v) => v.path === path);
      if (i < 0) return;
      this.visitedViews = this.visitedViews.filter((v, idx) => idx <= i || v.noClosable);
      persist(this.visitedViews);
    },
    closeAll() {
      this.visitedViews = this.visitedViews.filter((v) => v.noClosable);
      persist(this.visitedViews);
      return HOME.fullPath;
    },
    /** 退出登录时清空，避免下个账号看到上个账号的标签 */
    clear() {
      this.visitedViews = [HOME];
      persist(this.visitedViews);
    },
  },
});
