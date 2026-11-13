const api = require('../../utils/api');

Page({
  data: {
    username: '',
    password: '',
    loading: false,
    siteName: 'MKSHOP 商城',
    wechatEnabled: false,
    releaseEnable: true,
    agreementUrl: '',
    privacyUrl: '',
  },

  onLoad() {
    const app = getApp();
    const ready = app && app.globalData && app.globalData.mpConfigReady;
    if (ready && typeof ready.then === 'function') ready.then(() => this.applyConfig());
    else this.applyConfig();
  },

  // 从 /mp/config 读取登录方式与协议链接（不硬编码）
  applyConfig() {
    const app = getApp();
    const cfg = (app && app.globalData && app.globalData.mpConfig) || {};
    const mode = cfg.login_mode || 'account';
    this.setData({
      siteName: cfg.site_name || (app && app.globalData && app.globalData.siteName) || 'MKSHOP 商城',
      wechatEnabled: !!cfg.wechat_login_enable && (mode === 'wechat' || mode === 'both'),
      releaseEnable: cfg.release_enable !== false,
      agreementUrl: cfg.agreement_url || '',
      privacyUrl: cfg.privacy_url || '',
    });
  },

  onInput(e) {
    this.setData({ [e.currentTarget.dataset.field]: e.detail.value });
  },

  // 微信授权登录（预留位：开关默认关闭；接入后改为 wx.login 取 code 再调用接口）
  onWechatLogin() {
    if (!this.data.wechatEnabled) {
      wx.showToast({ title: '微信授权登录未启用', icon: 'none' });
      return;
    }
    wx.showToast({ title: '微信授权登录待接入', icon: 'none' });
  },

  // 协议链接：小程序内打开外链需配置业务域名，暂以复制链接兜底
  copyLink(e) {
    const url = e.currentTarget.dataset.url;
    if (!url) return;
    wx.setClipboardData({
      data: url,
      success: () => wx.showToast({ title: '链接已复制', icon: 'none' }),
    });
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
