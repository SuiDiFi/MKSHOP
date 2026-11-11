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

// ─── 订单 ───
export const orderBuy = (data) => mksRequest('/front/order/buy', 'POST', {}, data);
export const orderPay = (data) => mksRequest('/front/order/pay', 'POST', {}, data);
export const orderIndex = (params) => mksRequest('/front/order/index', 'GET', params);
export const orderQuery = (params) => mksRequest('/front/order/query', 'GET', params);

// ─── 分销中心（M4）───
export const commissionIndex = () => mksRequest('/front/commission/index', 'GET');
export const commissionTeams = (params) => mksRequest('/front/commission/teams', 'GET', params);
export const commissionLogs = (params) => mksRequest('/front/commission/logs', 'GET', params);
