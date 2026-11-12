import { mksRequest } from '@/utils/request';

// 首页/分类
export const homeIndex = () => mksRequest('/front/index/index', 'GET');
export const categoryIndex = () => mksRequest('/front/category/index', 'GET');
export const goodsIndex = (params) => mksRequest('/front/goods/index', 'GET', params);
export const goodsRead = (id) => mksRequest(`/front/goods/read/${id}`, 'GET');
export const diyPcHome = (params) => mksRequest('/front/diy/pc_home', 'GET', params);

// 会员
export const memberLogin = (data) => mksRequest('/front/member/login', 'POST', {}, data);
export const memberRegister = (data) => mksRequest('/front/member/register', 'POST', {}, data);
export const memberInfo = () => mksRequest('/front/member/info', 'GET');
export const memberLogout = () => mksRequest('/front/member/logout', 'POST');
export const memberMoney = (params) => mksRequest('/front/member/money', 'GET', params);

// 订单
export const orderBuy = (data) => mksRequest('/front/order/buy', 'POST', {}, data);
export const orderPay = (data) => mksRequest('/front/order/pay', 'POST', {}, data);
export const orderIndex = (params) => mksRequest('/front/order/index', 'GET', params);
export const orderQuery = (ordersn) => mksRequest('/front/order/query', 'GET', { ordersn });

// 内容
export const noticeIndex = (params) => mksRequest('/front/notice/index', 'GET', params);
export const noticeInfo = (id) => mksRequest(`/front/notice/info/${id}`, 'GET');
export const helpList = (params) => mksRequest('/front/support/list', 'GET', params);
export const helpInfo = (id) => mksRequest(`/front/support/detail/${id}`, 'GET');

// 在线客服
export const kefuStart = (source = 'h5') => mksRequest('/front/kefu/start', 'POST', {}, { source });
export const kefuMessages = (params) => mksRequest('/front/kefu/messages', 'GET', params);
export const kefuSend = (data) => mksRequest('/front/kefu/send', 'POST', {}, data);
