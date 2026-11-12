const api = require('../../utils/api');
const { statusText, formatTime, copyText } = require('../../utils/util');

Page({
  data: { sn: '', order: {}, cards: [], paying: false, loading: true, status_text: '', create_text: '' },

  onLoad(options) {
    this.setData({ sn: options.sn || '' });
    this.load();
  },

  async load() {
    try {
      const res = await api.orderQuery(this.data.sn);
      const d = res.data || {};
      this.setData({
        order: d,
        cards: d.cards || [],
        status_text: statusText(d.status),
        create_text: formatTime(d.create_time),
      });
    } catch (e) { /* ignore */ } finally {
      this.setData({ loading: false });
    }
  },

  async doPay() {
    this.setData({ paying: true });
    try {
      await api.orderPay({ ordersn: this.data.sn });
      wx.showToast({ title: '支付成功', icon: 'success' });
      this.load();
    } catch (e) { /* ignore */ } finally {
      this.setData({ paying: false });
    }
  },

  copyCard(e) {
    const { no, pwd } = e.currentTarget.dataset;
    copyText(`卡号：${no}\n卡密：${pwd}`);
    wx.showToast({ title: '已复制', icon: 'success' });
  },

  goOrders() { wx.switchTab({ url: '/pages/orders/orders' }); },
});
