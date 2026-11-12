import { mksRequest } from '@/utils/request';

// ─── 会员（M4）───
export const memberLogin = (data) => mksRequest('/front/member/login', 'POST', {}, data);
export const memberRegister = (data) => mksRequest('/front/member/register', 'POST', {}, data);
export const memberInfo = () => mksRequest('/front/member/info', 'GET');
export const memberLogout = () => mksRequest('/front/member/logout', 'POST');
export const memberBindParent = (data) => mksRequest('/front/member/bind_parent', 'POST', {}, data);

// ─── 商品 ───
export const goodsCates = () => mksRequest('/front/goods/cates', 'GET');
export const goodsIndex = (params) => mksRequest('/front/goods/index', 'GET', params);
export const goodsRead = (id) => mksRequest(`/front/goods/read/${id}`, 'GET');
export const diyPcHome = (params) => mksRequest('/front/diy/pc_home', 'GET', params);

// ─── M5 首页/分类/内容 ───
export const homeIndex = () => mksRequest('/front/index/index', 'GET');
export const categoryIndex = () => mksRequest('/front/category/index', 'GET');
export const noticeIndex = (params) => mksRequest('/front/notice/index', 'GET', params);
export const noticeInfo = (id) => mksRequest(`/front/notice/info/${id}`, 'GET');
export const helpList = (params) => mksRequest('/front/support/list', 'GET', params);
export const helpInfo = (id) => mksRequest(`/front/support/detail/${id}`, 'GET');
export const memberMoney = (params) => mksRequest('/front/member/money', 'GET', params);

// ─── M5 在线客服 ───
export const kefuStart = (source = 'pc') => mksRequest('/front/kefu/start', 'POST', {}, { source });
export const kefuMessages = (params) => mksRequest('/front/kefu/messages', 'GET', params);
export const kefuSend = (data) => mksRequest('/front/kefu/send', 'POST', {}, data);

// ─── 订单 ───
export const orderBuy = (data) => mksRequest('/front/order/buy', 'POST', {}, data);
export const orderPay = (data) => mksRequest('/front/order/pay', 'POST', {}, data);
export const orderIndex = (params) => mksRequest('/front/order/index', 'GET', params);
export const orderQuery = (params) => mksRequest('/front/order/query', 'GET', params);

// ─── 分销中心（M4）───
export const commissionIndex = () => mksRequest('/front/commission/index', 'GET');
export const commissionTeams = (params) => mksRequest('/front/commission/teams', 'GET', params);
export const commissionLogs = (params) => mksRequest('/front/commission/logs', 'GET', params);

// ─── 提现（M4）───
export const collectConfig = () => mksRequest('/front/member_collect/config', 'POST');
export const collectIndex = (params) => mksRequest('/front/member_collect/index', 'GET', params);
export const collectSave = (data) => mksRequest('/front/member_collect/save', 'POST', {}, data);
export const collectWays = () => mksRequest('/front/member_collect_way/index', 'GET');
export const collectWaySave = (data) => mksRequest('/front/member_collect_way/save', 'POST', {}, data);
export const collectWayDelete = (id) => mksRequest('/front/member_collect_way/delete', 'POST', {}, { id });
