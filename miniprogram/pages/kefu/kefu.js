const api = require('../../utils/api');
const { requireLogin } = require('../../utils/request');

Page({
  data: {
    messages: [],
    content: '',
    sessionId: 0,
    lastId: 0,
    closed: false,
    loading: true,
    intoView: '',
  },

  onLoad() {
    if (!requireLogin()) return;
    this.start();
  },

  onUnload() {
    this.stop();
  },

  onHide() {
    this.stop();
  },

  onShow() {
    if (this.data.sessionId) this.timer = setInterval(() => this.fetchMessages(), 3000);
  },

  start() {
    this.stop();
    this.setData({ loading: true });
    api.kefuStart('mp').then((res) => {
      this.setData({
        sessionId: res.data.session_id || 0,
        lastId: 0,
        messages: [],
        closed: (res.data.status || 1) !== 1,
      });
      return this.fetchMessages();
    }).finally(() => {
      this.setData({ loading: false });
      this.timer = setInterval(() => this.fetchMessages(), 3000);
    });
  },

  stop() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  },

  async fetchMessages() {
    if (!this.data.sessionId) return;
    try {
      const res = await api.kefuMessages({ session_id: this.data.sessionId, last_id: this.data.lastId });
      const rows = (res.data.list || []).map((m) => Object.assign({}, m, {
        cls: m.from_type === 3 ? 'sys-row' : (m.from_type === 1 ? 'mine' : 'other'),
        avatar: m.from_type === 2 ? '客' : '我',
      }));
      if (rows.length) {
        const messages = this.data.messages.concat(rows);
        this.setData({
          messages,
          lastId: rows[rows.length - 1].id,
          intoView: 'm' + rows[rows.length - 1].id,
        });
      }
      this.setData({ closed: (res.data.status || 1) !== 1 });
    } catch (e) { /* 轮询失败静默 */ }
  },

  onInput(e) {
    this.setData({ content: e.detail.value });
  },

  async send() {
    const text = this.data.content.trim();
    if (!text || this.data.closed) return;
    try {
      await api.kefuSend({ session_id: this.data.sessionId, content: text });
      this.setData({ content: '' });
      await this.fetchMessages();
    } catch (e) { /* request 已提示 */ }
  },

  restart() {
    this.start();
  },
});
