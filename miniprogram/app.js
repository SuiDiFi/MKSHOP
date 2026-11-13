const api = require('./utils/api');
const { baseUrl } = require('./config');

App({
  globalData: {
    siteName: 'MKSHOP 商城',
    baseUrl,
    mpConfig: null,
    mpConfigReady: null,
  },
  onLaunch() {
    // 启动即拉取小程序配置（登录方式、微信能力开关、协议链接等）
    this.globalData.mpConfigReady = this.loadMpConfig();
  },
  async loadMpConfig() {
    try {
      const res = await api.mpConfig();
      const cfg = res.data || {};
      this.globalData.mpConfig = cfg;
      if (cfg.site_name) this.globalData.siteName = cfg.site_name;
      return cfg;
    } catch (e) {
      // 配置拉取失败不阻断启动，页面使用默认值
      return null;
    }
  },
});
