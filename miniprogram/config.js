// 小程序全局配置
// 规则：AppID 与服务器域名由 scripts/inject-config.js 在构建前注入 config.build.js；
//       页面代码不得硬编码 AppID / 域名 / 商户号等环境值。
// 调试兜底：开发者可临时 wx.setStorageSync('mks_mp_base_url', 'https://xxx') 覆盖请求域名。
const build = require('./config.build');

function runtimeBaseUrl() {
  try {
    return String(wx.getStorageSync('mks_mp_base_url') || '');
  } catch (e) {
    return '';
  }
}

module.exports = {
  appid: build.appid || '',
  baseUrl: (runtimeBaseUrl() || build.baseUrl || '').replace(/\/+$/, ''),
};
