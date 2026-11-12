const api = require('../../utils/api');

Page({
  data: { username: '', password: '', loading: false },

  onInput(e) {
    this.setData({ [e.currentTarget.dataset.field]: e.detail.value });
  },

  async submit() {
    const { username, password } = this.data;
    if (!username.trim() || !password) {
      wx.showToast({ title: '请输入账号和密码', icon: 'none' });
      return;
    }
    this.setData({ loading: true });
    try {
      const res = await api.memberLogin({ username: username.trim(), password });
      wx.setStorageSync('mks_member_token', res.data.token);
      wx.setStorageSync('mks_member_info', res.data.userinfo || {});
      wx.showToast({ title: '登录成功', icon: 'success' });
      setTimeout(() => {
        const pages = getCurrentPages();
        if (pages.length > 1) wx.navigateBack();
        else wx.switchTab({ url: '/pages/member/member' });
      }, 500);
    } catch (e) { /* request 已提示 */ } finally {
      this.setData({ loading: false });
    }
  },
});
