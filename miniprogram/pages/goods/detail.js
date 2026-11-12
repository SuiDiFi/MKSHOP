const api = require('../../utils/api');
const { requireLogin } = require('../../utils/request');

Page({
  data: {
    id: 0,
    goods: {},
    quantity: 1,
    rechargeAccount: '',
    buying: false,
    total: '0.00',
    needAccount: false,
    loading: true,
  },

  onLoad(options) {
    this.setData({ id: Number(options.id || 0) });
    this.load();
  },

  async load() {
    try {
      const res = await api.goodsRead(this.data.id);
      const g = res.data.goods || res.data || {};
      this.setData({
        goods: g,
        needAccount: Number(g.goods_type) === 2,
        quantity: Math.max(1, Number(g.start_count || 1)),
      });
      this.calcTotal();
    } catch (e) { /* ignore */ } finally {
      this.setData({ loading: false });
    }
  },

  calcTotal() {
    const price = Number(this.data.goods.real_price || 0);
    this.setData({ total: (price * this.data.quantity).toFixed(2) });
  },

  dec() {
    const min = Math.max(1, Number(this.data.goods.start_count || 1));
    if (this.data.quantity > min) {
      this.setData({ quantity: this.data.quantity - 1 });
      this.calcTotal();
    }
  },

  inc() {
    const max = Math.max(1, Number(this.data.goods.end_count || 999));
    if (this.data.quantity < max) {
      this.setData({ quantity: this.data.quantity + 1 });
      this.calcTotal();
    }
  },

  onQty(e) {
    let n = Number(e.detail.value || 1);
    const min = Math.max(1, Number(this.data.goods.start_count || 1));
    const max = Math.max(min, Number(this.data.goods.end_count || 999));
    if (isNaN(n) || n < min) n = min;
    if (n > max) n = max;
    this.setData({ quantity: n });
    this.calcTotal();
  },

  onAccount(e) {
    this.setData({ rechargeAccount: e.detail.value });
  },

  async doBuy() {
    if (this.data.buying || Number(this.data.goods.stock_num) <= 0) return;
    if (!requireLogin()) return;
    if (this.data.needAccount && !this.data.rechargeAccount.trim()) {
      wx.showToast({ title: '请输入充值账号', icon: 'none' });
      return;
    }
    this.setData({ buying: true });
    try {
      const res = await api.orderBuy({
        goods_id: this.data.id,
        quantity: this.data.quantity,
        recharge_account: this.data.rechargeAccount,
        pay_type: 'balance',
      });
      const sn = res.data.ordersn;
      wx.showToast({ title: '下单支付成功', icon: 'success' });
      setTimeout(() => {
        wx.navigateTo({ url: `/pages/order/detail?sn=${sn}` });
      }, 600);
    } catch (err) {
      // 余额不足等：仍跳转订单详情，可稍后继续支付
      const sn = err.body && err.body.data && err.body.data.ordersn;
      if (sn) {
        wx.navigateTo({ url: `/pages/order/detail?sn=${sn}` });
      }
    } finally {
      this.setData({ buying: false });
    }
  },
});
