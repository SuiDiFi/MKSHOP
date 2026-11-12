const api = require('../../utils/api');
const { requireLogin } = require('../../utils/request');

Page({
  data: { user: {}, isLogin: false, initial: 'M' },

  onShow() {
    const isLogin = requireLogin();
    this.setData({ isLogin });
    if (isLogin) this.load();
  },

  async load() {
    try {
      const res = await api.memberInfo();
      const user = res.data.userinfo || {};
      this.setData({
        user,
        initial: String(user.nickname || user.username || 'M').slice(0, 1).toUpperCase(),
      });
    } catch (e) { /* ignore */ }
  },

  goOrders() { wx.switchTab({ url: '/pages/orders/orders' }); },
  goKefu() { wx.navigateTo({ url: '/pages/kefu/kefu' }); },
  goHome() { wx.switchTab({ url: '/pages/index/index' }); },

  logout() {
    wx.showModal({
      title: '提示',
      content: '确定退出登录？',
      success: async (r) => {
        if (!r.confirm) return;
        try { await api.memberLogout(); } catch (e) { /* ignore */ }
        wx.removeStorageSync('mks_member_token');
        wx.removeStorageSync('mks_member_info');
        this.setData({ user: {}, isLogin: false, initial: 'M' });
        wx.navigateTo({ url: '/pages/login/login' });
      },
    });
  },
});
