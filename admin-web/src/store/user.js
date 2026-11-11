import { defineStore } from 'pinia';
import { getInfo, login as loginApi, logout as logoutApi, getMenus } from '@/api';
import { registerDynamicRoutes } from '@/router';

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('mks_admin_token') || '',
    userinfo: JSON.parse(localStorage.getItem('mks_admin_user') || 'null') || {},
    menus: JSON.parse(localStorage.getItem('mks_admin_menus') || '[]') || [],
    isSuper: localStorage.getItem('mks_admin_super') === '1',
    routesLoaded: false,
    menuPathMap: {}, // menuId → 路由全路径
  }),
  actions: {
    async login(username, password) {
      const res = await loginApi(username, password);
      this.token = res.data.token;
      this.userinfo = res.data.userinfo || {};
      this.isSuper = !!res.data.is_super;
      localStorage.setItem('mks_admin_token', this.token);
      localStorage.setItem('mks_admin_user', JSON.stringify(this.userinfo));
      localStorage.setItem('mks_admin_super', this.isSuper ? '1' : '0');
      await this.loadMenus();
    },
    async loadMenus() {
      const res = await getMenus();
      this.menus = res.data || [];
      registerDynamicRoutes(this.menus);
      this.routesLoaded = true;
      localStorage.setItem('mks_admin_menus', JSON.stringify(this.menus));
    },
    async fetchInfo() {
      const res = await getInfo();
      this.userinfo = res.data.userinfo || {};
      this.isSuper = !!res.data.is_super;
      localStorage.setItem('mks_admin_user', JSON.stringify(this.userinfo));
      if (!this.routesLoaded) {
        await this.loadMenus();
      }
    },
    async logout() {
      try {
        await logoutApi();
      } catch (e) {
        /* 忽略 */
      }
      this.token = '';
      this.userinfo = {};
      this.menus = [];
      this.routesLoaded = false;
      localStorage.removeItem('mks_admin_token');
      localStorage.removeItem('mks_admin_user');
      localStorage.removeItem('mks_admin_menus');
      localStorage.removeItem('mks_admin_super');
    },
  },
});
