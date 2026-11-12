const api = require('../../utils/api');

Page({
  data: {
    cates: [],
    activeCate: 0,
    keyword: '',
    list: [],
    total: 0,
    totalPages: 1,
    page: 1,
    limit: 20,
    loading: false,
  },

  onLoad() {
    this.loadCates();
    this.loadList();
  },

  async loadCates() {
    try {
      const res = await api.categoryIndex();
      this.setData({ cates: res.data.list || res.data || [] });
    } catch (e) { /* ignore */ }
  },

  async loadList() {
    this.setData({ loading: true });
    try {
      const params = { page: this.data.page, limit: this.data.limit };
      if (this.data.activeCate) params.cate_id = this.data.activeCate;
      if (this.data.keyword) params.keyword = this.data.keyword;
      const res = await api.goodsIndex(params);
      const total = res.data.count || 0;
      this.setData({
        list: res.data.list || [],
        total,
        totalPages: Math.max(1, Math.ceil(total / this.data.limit)),
      });
    } catch (e) { /* ignore */ } finally {
      this.setData({ loading: false });
    }
  },

  pick(e) {
    this.setData({ activeCate: Number(e.currentTarget.dataset.id), page: 1 });
    this.loadList();
  },

  onSearch(e) {
    this.setData({ keyword: (e.detail.value || '').trim(), page: 1 });
    this.loadList();
  },

  prev() {
    if (this.data.page <= 1) return;
    this.setData({ page: this.data.page - 1 });
    this.loadList();
  },

  next() {
    if (this.data.page >= this.data.totalPages) return;
    this.setData({ page: this.data.page + 1 });
    this.loadList();
  },

  goGoods(e) { wx.navigateTo({ url: `/pages/goods/detail?id=${e.currentTarget.dataset.id}` }); },
});
