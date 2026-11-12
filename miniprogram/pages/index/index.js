const api = require('../../utils/api');

Page({
  data: { site: {}, notices: [], hot: [], loading: true },

  onShow() {
    this.load();
  },

  async load() {
    this.setData({ loading: true });
    try {
      const res = await api.homeIndex();
      this.setData({
        site: res.data.site || {},
        notices: res.data.notices || [],
        hot: res.data.hot || [],
      });
    } catch (e) { /* request 已提示 */ } finally {
      this.setData({ loading: false });
    }
  },

  goCategory() { wx.switchTab({ url: '/pages/category/category' }); },
  goOrders() { wx.switchTab({ url: '/pages/orders/orders' }); },
  goMember() { wx.switchTab({ url: '/pages/member/member' }); },
  goKefu() { wx.navigateTo({ url: '/pages/kefu/kefu' }); },
  goGoods(e) { wx.navigateTo({ url: `/pages/goods/detail?id=${e.currentTarget.dataset.id}` }); },
});
