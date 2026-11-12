const api = require('../../utils/api');
const { requireLogin } = require('../../utils/request');
const { statusText } = require('../../utils/util');

Page({
  data: {
    tabs: [
      { label: '全部', value: '' },
      { label: '待支付', value: 0 },
      { label: '处理中', value: 1 },
      { label: '已完成', value: 3 },
    ],
    active: '',
    list: [],
    page: 1,
    limit: 10,
    total: 0,
    loading: false,
  },

  onShow() {
    if (!requireLogin()) return;
    this.setData({ page: 1 });
    this.load();
  },

  async load() {
    this.setData({ loading: true });
    try {
      const params = { page: this.data.page, limit: this.data.limit };
      if (this.data.active !== '') params.status = this.data.active;
      const res = await api.orderIndex(params);
      const rows = (res.data.list || []).map((o) => Object.assign({}, o, { status_text: statusText(o.status) }));
      this.setData({ list: rows, total: res.data.count || 0 });
    } catch (e) { /* ignore */ } finally {
      this.setData({ loading: false });
    }
  },

  pick(e) {
    const raw = e.currentTarget.dataset.value;
    this.setData({ active: raw === '' || raw === undefined ? '' : Number(raw), page: 1 });
    this.load();
  },

  goDetail(e) {
    wx.navigateTo({ url: `/pages/order/detail?sn=${e.currentTarget.dataset.sn}` });
  },

  more() {
    if (this.data.list.length >= this.data.total) return;
    this.setData({ page: this.data.page + 1 }, () => this.loadMore());
  },

  async loadMore() {
    try {
      const params = { page: this.data.page, limit: this.data.limit };
      if (this.data.active !== '') params.status = this.data.active;
      const res = await api.orderIndex(params);
      const rows = (res.data.list || []).map((o) => Object.assign({}, o, { status_text: statusText(o.status) }));
      this.setData({ list: this.data.list.concat(rows), total: res.data.count || 0 });
    } catch (e) { /* ignore */ }
  },

  goLogin() { wx.navigateTo({ url: '/pages/login/login' }); },
});
