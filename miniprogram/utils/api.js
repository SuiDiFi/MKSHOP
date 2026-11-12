// API 定义（与 PC/H5 共用同一套前台接口）
const { request } = require('./request');

// 首页/分类/商品
const homeIndex = () => request('/front/index/index');
const categoryIndex = () => request('/front/category/index');
const goodsIndex = (params) => request('/front/goods/index', 'GET', params);
const goodsRead = (id) => request(`/front/goods/read/${id}`);

// 会员
const memberLogin = (data) => request('/front/member/login', 'POST', {}, data);
const memberInfo = () => request('/front/member/info');
const memberLogout = () => request('/front/member/logout', 'POST');

// 订单
const orderBuy = (data) => request('/front/order/buy', 'POST', {}, data);
const orderPay = (data) => request('/front/order/pay', 'POST', {}, data);
const orderIndex = (params) => request('/front/order/index', 'GET', params);
const orderQuery = (ordersn) => request('/front/order/query', 'GET', { ordersn });

// 客服
const kefuStart = (source = 'mp') => request('/front/kefu/start', 'POST', {}, { source });
const kefuMessages = (params) => request('/front/kefu/messages', 'GET', params);
const kefuSend = (data) => request('/front/kefu/send', 'POST', {}, data);

module.exports = {
  homeIndex, categoryIndex, goodsIndex, goodsRead,
  memberLogin, memberInfo, memberLogout,
  orderBuy, orderPay, orderIndex, orderQuery,
  kefuStart, kefuMessages, kefuSend,
};
