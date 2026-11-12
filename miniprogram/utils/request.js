const { baseUrl } = require('../config');

function goLogin() {
  wx.removeStorageSync('mks_member_token');
  wx.removeStorageSync('mks_member_info');
  wx.navigateTo({ url: '/pages/login/login' });
}

/**
 * 统一请求：s 参数放 query，GET 参数走 data，POST 走 JSON body
 */
function request(path, method = 'GET', params = {}, data = undefined) {
  return new Promise((resolve, reject) => {
    const token = wx.getStorageSync('mks_member_token');
    wx.request({
      url: `${baseUrl}/index.php?s=${encodeURIComponent(path)}`,
      method,
      data: method === 'GET' ? params : (data || {}),
      header: Object.assign(
        { 'Content-Type': 'application/json' },
        token ? { Authorization: `Bearer ${token}` } : {}
      ),
      success(res) {
        const body = res.data || {};
        if (body.code === 0) return resolve(body);
        if (res.statusCode === 401 || body.code === 401) {
          goLogin();
          return reject(new Error(body.msg || '请先登录'));
        }
        wx.showToast({ title: body.msg || '请求失败', icon: 'none' });
        const err = new Error(body.msg || 'Error');
        err.body = body;
        reject(err);
      },
      fail(err) {
        wx.showToast({ title: '网络错误', icon: 'none' });
        reject(err);
      },
    });
  });
}

function isLogin() {
  return !!wx.getStorageSync('mks_member_token');
}

function requireLogin() {
  if (isLogin()) return true;
  goLogin();
  return false;
}

module.exports = { request, isLogin, requireLogin, goLogin, baseUrl };
