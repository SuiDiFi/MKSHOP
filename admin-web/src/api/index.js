import { mksRequest } from '@/utils/request';
import service from '@/utils/request';

/**
 * CSV 导出（blob 下载）
 * @param {string} path 后台接口路径（如 /admin/order/export）
 * @param {object} params 查询参数（与列表一致）
 * @param {string} filename 下载文件名
 */
export async function exportCsv(path, params = {}, filename = 'export.csv') {
  const res = await service.request({
    url: '/index.php',
    method: 'GET',
    params: { ...params, s: path },
    responseType: 'blob',
  });
  const url = URL.createObjectURL(res.data || res);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

/** 后台登录 */
export const login = (username, password) =>
  mksRequest('/admin/login/login', 'POST', {}, { username, password });

/** 我的信息 */
export const getInfo = () => mksRequest('/admin/login/getInfo');

/** 后台菜单树 */
export const getMenus = () => mksRequest('/admin/login/getMenus');

/** 登出 */
export const logout = () => mksRequest('/admin/login/logout', 'POST');

// ─── 管理员管理（注意：admin 模块 api_url 本身为 admin/*，故 URL 双层前缀 /admin/admin/*）───
export const adminIndex = (params) => mksRequest('/admin/admin/index', 'GET', params);
export const adminRead = (id) => mksRequest(`/admin/admin/read/${id}`);
export const adminCreate = (form) => mksRequest('/admin/admin/create', 'POST', {}, { form });
export const adminSave = (id, form) => mksRequest(`/admin/admin/save/${id}`, 'POST', {}, { form });
export const adminDelete = (id) => mksRequest(`/admin/admin/delete/${id}`, 'POST');
export const adminModify = (id, field, value) =>
  mksRequest(`/admin/admin/modify/${id}`, 'POST', {}, { field, value });
export const adminDoLog = (params) => mksRequest('/admin/admin/do_log', 'GET', params);
export const adminLoginLog = (params) => mksRequest('/admin/admin/login_log', 'GET', params);
export const adminLoginStatus = (params) => mksRequest('/admin/admin/login_status', 'GET', params);
export const adminDelLoginStatus = (id) => mksRequest('/admin/admin/del_login_status', 'POST', {}, { id });

// ─── 角色管理 ───
export const roleIndex = (params) => mksRequest('/admin/role/index', 'GET', params);
export const roleList = (params) => mksRequest('/admin/role/list', 'POST', {}, params);
export const roleRead = (id) => mksRequest(`/admin/role/read/${id}`);
export const roleCreate = (form) => mksRequest('/admin/role/create', 'POST', {}, { form });
export const roleSave = (id, form) => mksRequest(`/admin/role/save/${id}`, 'POST', {}, { form });
export const roleDelete = (id) => mksRequest(`/admin/role/delete/${id}`, 'POST');
export const roleModifyPerms = (id, role) =>
  mksRequest(`/admin/role/modify_role/${id}`, 'POST', {}, { role });

// ─── 菜单/权限管理 ───
export const routerIndex = (params) => mksRequest('/admin/router/index', 'GET', params);
export const routerRead = (id) => mksRequest(`/admin/router/read/${id}`);
export const routerCreate = (form) => mksRequest('/admin/router/create', 'POST', {}, { form });
export const routerSave = (id, form) => mksRequest(`/admin/router/save/${id}`, 'POST', {}, { form });
export const routerDelete = (id) => mksRequest(`/admin/router/delete/${id}`, 'POST');

// ─── 系统配置中心 + 字典 ───
export const configGroupList = () => mksRequest('/admin/sys/config_group_list');
export const configGroupForm = (group) => mksRequest('/admin/sys/config_group_form', 'GET', { group });
export const saveConfigGroup = (group, form) =>
  mksRequest('/admin/sys/save_config_group', 'POST', {}, { group, form });
export const dictOptions = (type) => mksRequest('/admin/sys/dict_options', 'GET', { type });
export const dictIndex = (params) => mksRequest('/admin/sys/dict_index', 'GET', params);
export const dictSave = (form, id = 0) => mksRequest('/admin/sys/dict_save', 'POST', {}, { form, id });
export const dictDelete = (id) => mksRequest('/admin/sys/dict_delete', 'POST', {}, { id });

// ─── 定时任务 ───
export const timerIndex = (params) => mksRequest('/admin/sys_timer/index', 'GET', params);
export const timerRead = (id) => mksRequest(`/admin/sys_timer/read/${id}`);
export const timerTaskName = () => mksRequest('/admin/sys_timer/task_name', 'POST');
export const timerCreate = (form) => mksRequest('/admin/sys_timer/create', 'POST', {}, { form });
export const timerSave = (id, form) => mksRequest(`/admin/sys_timer/save/${id}`, 'POST', {}, { form });
export const timerDelete = (id) => mksRequest(`/admin/sys_timer/delete/${id}`, 'POST');
export const timerModify = (id, field, value) =>
  mksRequest(`/admin/sys_timer/modify/${id}`, 'POST', {}, { field, value });

// ─── 商品管理（M1）───
export const goodsIndex = (params) => mksRequest('/admin/goods/index', 'GET', params);
export const goodsRead = (id) => mksRequest(`/admin/goods/read/${id}`);
export const goodsCreate = (form) => mksRequest('/admin/goods/create', 'POST', {}, { form });
export const goodsSave = (id, form) => mksRequest(`/admin/goods/save/${id}`, 'POST', {}, { form });
export const goodsDelete = (id) => mksRequest(`/admin/goods/delete/${id}`, 'POST');
export const goodsDestroy = (id) => mksRequest(`/admin/goods/destroy/${id}`, 'POST');
export const goodsRestore = (id) => mksRequest(`/admin/goods/restore/${id}`, 'POST');
export const goodsModify = (id, field, value) =>
  mksRequest(`/admin/goods/modify/${id}`, 'POST', {}, { field, value });
export const goodsStatusType = () => mksRequest('/admin/goods/status_type', 'POST');
export const goodsGoodsType = () => mksRequest('/admin/goods/goods_type', 'POST');
export const goodsProvideType = () => mksRequest('/admin/goods/provide_type', 'POST');
export const goodsDefaultTags = () => mksRequest('/admin/goods/default_tags_list', 'POST');

// ─── 商品分类（M1）───
export const categoryIndex = (params) => mksRequest('/admin/category/index', 'GET', params);
export const categoryList = () => mksRequest('/admin/category/list', 'POST');
export const categoryCreate = (form) => mksRequest('/admin/category/create', 'POST', {}, { form });
export const categorySave = (id, form) => mksRequest(`/admin/category/save/${id}`, 'POST', {}, { form });
export const categoryDelete = (id) => mksRequest(`/admin/category/delete/${id}`, 'POST');
export const categoryModify = (id, field, value) =>
  mksRequest(`/admin/category/modify/${id}`, 'POST', {}, { field, value });

// ─── 卡密库存（M1）───
export const cardIndex = (params) => mksRequest('/admin/card/index', 'GET', params);
export const cardCreate = (form) => mksRequest('/admin/card/create', 'POST', {}, { form });
export const cardImport = (form) => mksRequest('/admin/card/save', 'POST', {}, { form });
export const cardDelete = (id) => mksRequest(`/admin/card/delete/${id}`, 'POST');
export const cardDeleteAll = (form) => mksRequest('/admin/card/delete_all', 'POST', {}, form);
export const cardCateIndex = (params) => mksRequest('/admin/card_cate/index', 'GET', params);
export const cardCateList = () => mksRequest('/admin/card_cate/list', 'POST');
export const cardCateCreate = (form) => mksRequest('/admin/card_cate/create', 'POST', {}, { form });
export const cardCateSave = (id, form) => mksRequest(`/admin/card_cate/save/${id}`, 'POST', {}, { form });
export const cardCateDelete = (id) => mksRequest(`/admin/card_cate/delete/${id}`, 'POST');
export const cardHistoryIndex = (params) => mksRequest('/admin/card_history/index', 'GET', params);

// ─── 品牌标签（M1）───
export const tagsIndex = (params) => mksRequest('/admin/goods_tags/index', 'GET', params);
export const tagsList = () => mksRequest('/admin/goods_tags/list', 'POST');
export const tagsCreate = (form) => mksRequest('/admin/goods_tags/create', 'POST', {}, { form });
export const tagsSave = (id, form) => mksRequest(`/admin/goods_tags/save/${id}`, 'POST', {}, { form });
export const tagsDelete = (id) => mksRequest(`/admin/goods_tags/delete/${id}`, 'POST');
export const tagsModify = (id, field, value) =>
  mksRequest(`/admin/goods_tags/modify/${id}`, 'POST', {}, { field, value });

// ─── 商品下单输入项模板（M7 对齐 KASUSHOU goods_tpl）───
export const goodsTplIndex = (params) => mksRequest('/admin/goods_tpl/index', 'GET', params);
export const goodsTplList = () => mksRequest('/admin/goods_tpl/list', 'POST');
export const goodsTplCreate = (form) => mksRequest('/admin/goods_tpl/create', 'POST', {}, { form });
export const goodsTplSave = (id, form) => mksRequest(`/admin/goods_tpl/save/${id}`, 'POST', {}, { form });
export const goodsTplDelete = (id) => mksRequest(`/admin/goods_tpl/delete/${id}`, 'POST');
export const goodsTplModify = (id, field, value) =>
  mksRequest(`/admin/goods_tpl/modify/${id}`, 'POST', {}, { field, value });

// ─── 定价模板（M7 对齐 KASUSHOU pricereadydata）───
export const priceReadyIndex = (params) => mksRequest('/admin/price_ready/index', 'GET', params);
export const priceReadyCreate = (form) => mksRequest('/admin/price_ready/create', 'POST', {}, { form });
export const priceReadySave = (id, form) => mksRequest(`/admin/price_ready/save/${id}`, 'POST', {}, { form });
export const priceReadyDelete = (id) => mksRequest(`/admin/price_ready/delete/${id}`, 'POST');
export const priceReadyTypeList = () => mksRequest('/admin/price_ready/type_list', 'GET');
export const priceReadyModify = (id, field, value) =>
  mksRequest(`/admin/price_ready/modify/${id}`, 'POST', {}, { field, value });

// ─── 售后/投诉管理（M7 对齐 KASUSHOU 投诉管理）───
export const complainIndex = (params) => mksRequest('/admin/complain/index', 'GET', params);
export const complainRead = (id) => mksRequest(`/admin/complain/read/${id}`);
export const complainDual = (id, data) => mksRequest(`/admin/complain/dual/${id}`, 'POST', {}, data);
export const complainTypeList = () => mksRequest('/admin/complain/type_list', 'GET');
export const complainExport = exportCsv;

// ─── 财务对账（M7 对齐 KASUSHOU moneyday / 渠道对账）───
export const moneyDayIndex = (params) => mksRequest('/admin/money_day/index', 'GET', params);
export const moneyDayUpdate = (data) => mksRequest('/admin/money_day/update', 'POST', {}, data);
export const moneyDayExport = exportCsv;
export const dockingDayIndex = (params) => mksRequest('/admin/money_day/channel_index', 'GET', params);
export const dockingDayUpdate = (data) => mksRequest('/admin/money_day/channel_update', 'POST', {}, data);

// ─── 用户权限模板（M7 对齐 KASUSHOU 用户密价/限价/商品权限）───
export const userPriceTplIndex = (params) => mksRequest('/admin/user_price_tpl/index', 'GET', params);
export const userPriceTplRead = (id) => mksRequest(`/admin/user_price_tpl/read/${id}`);
export const userPriceTplCreate = (form) => mksRequest('/admin/user_price_tpl/create', 'POST', {}, { form });
export const userPriceTplSave = (id, form) => mksRequest(`/admin/user_price_tpl/save/${id}`, 'POST', {}, { form });
export const userPriceTplDelete = (id) => mksRequest(`/admin/user_price_tpl/delete/${id}`, 'POST');
export const userPriceTplModify = (id, field, value) =>
  mksRequest(`/admin/user_price_tpl/modify/${id}`, 'POST', {}, { field, value });

// ─── 订单管理（M2）───
export const orderIndex = (params) => mksRequest('/admin/order/index', 'GET', params);
export const orderInfo = (id) => mksRequest(`/admin/order/info/${id}`);
export const orderGoodsCards = (id) => mksRequest(`/admin/order/goods/${id}`);
export const orderDual = (id, data) => mksRequest(`/admin/order/dual/${id}`, 'POST', {}, data);
export const orderDelete = (id) => mksRequest(`/admin/order/delete/${id}`, 'POST');
export const orderModify = (id, field, value) =>
  mksRequest(`/admin/order/modify/${id}`, 'POST', {}, { field, value });
export const orderStatusType = () => mksRequest('/admin/order/status_type', 'POST');
export const orderPayType = () => mksRequest('/admin/order/pay_type', 'POST');
export const orderSync = (id) => mksRequest(`/admin/order/sync_order/${id}`, 'POST');

// ─── 对接渠道（M3）───
export const dockingIndex = (params) => mksRequest('/admin/docking/index', 'GET', params);
export const dockingList = () => mksRequest('/admin/docking/list', 'POST');
export const dockingCreate = (form) => mksRequest('/admin/docking/create', 'POST', {}, { form });
export const dockingSave = (id, form) => mksRequest(`/admin/docking/save/${id}`, 'POST', {}, { form });
export const dockingDelete = (id) => mksRequest(`/admin/docking/delete/${id}`, 'POST');
export const dockingUserMoney = (dockingId) => mksRequest('/admin/docking/user_money', 'POST', {}, { docking_id: dockingId });
export const dockingGoodsList = (dockingId, page = 1, keyword = '') =>
  mksRequest('/admin/docking/goods_list', 'POST', {}, { docking_id: dockingId, page, keyword });
export const dockingGoodsDual = (form) => mksRequest('/admin/docking/goods_dual', 'POST', {}, form);
export const dockingLogIndex = (params) => mksRequest('/admin/docking/log_index', 'GET', params);

// ─── 会员管理（M4）───
export const memberIndex = (params) => mksRequest('/admin/member/index', 'GET', params);
export const memberRead = (id) => mksRequest(`/admin/member/read/${id}`);
export const memberCreate = (form) => mksRequest('/admin/member/create', 'POST', {}, { form });
export const memberSave = (id, form) => mksRequest(`/admin/member/save/${id}`, 'POST', {}, { form });
export const memberDelete = (id) => mksRequest(`/admin/member/delete/${id}`, 'POST');
export const memberRestore = (id) => mksRequest(`/admin/member/restore/${id}`, 'POST');
export const memberUnbind = (id) => mksRequest(`/admin/member/unbind/${id}`, 'POST');
export const memberResetPassword = (id, password) =>
  mksRequest(`/admin/member/reset_password/${id}`, 'POST', {}, { password });
export const memberStatusType = () => mksRequest('/admin/member/status_type', 'POST');

// ─── 会员等级（M4）───
export const memberLevelIndex = (params) => mksRequest('/admin/member_level/index', 'GET', params);
export const memberLevelList = () => mksRequest('/admin/member_level/list', 'POST');
export const memberLevelCreate = (form) => mksRequest('/admin/member_level/create', 'POST', {}, { form });
export const memberLevelSave = (id, form) => mksRequest(`/admin/member_level/save/${id}`, 'POST', {}, { form });
export const memberLevelDelete = (id) => mksRequest(`/admin/member_level/delete/${id}`, 'POST');

// ─── 提现审核（M4）───
export const memberCollectIndex = (params) => mksRequest('/admin/member_collect/index', 'GET', params);
export const memberCollectDual = (id, data) => mksRequest(`/admin/member_collect/dual/${id}`, 'POST', {}, data);

// ─── 分销佣金（M4）───
export const commissionIndex = (params) => mksRequest('/admin/commission/index', 'GET', params);
export const commissionSettle = (id) => mksRequest(`/admin/commission/settle/${id}`, 'POST');

// ─── DIY 装修（M5）───
export const diyIndex = () => mksRequest('/admin/diy/index', 'GET');
export const diyRead = (id) => mksRequest(`/admin/diy/read/${id}`, 'GET');
export const diySave = (id, data) => mksRequest(`/admin/diy/save/${id}`, 'POST', {}, data);
export const diyComponents = () => mksRequest('/admin/diy/components', 'GET');

// ─── 客服工作台（M5）───
export const kefuIndex = (params) => mksRequest('/admin/kefu/index', 'GET', params);
export const kefuMessages = (params) => mksRequest('/admin/kefu/messages', 'GET', params);
export const kefuReply = (data) => mksRequest('/admin/kefu/reply', 'POST', {}, data);
export const kefuClose = (data) => mksRequest('/admin/kefu/close', 'POST', {}, data);

// ─── 分站管理（M6）───
export const vipIndex = (params) => mksRequest('/admin/vip/index', 'GET', params);
export const vipRead = (id) => mksRequest(`/admin/vip/read/${id}`, 'GET');
export const vipCreate = (form) => mksRequest('/admin/vip/create', 'POST', {}, { form });
export const vipSave = (id, form) => mksRequest(`/admin/vip/save/${id}`, 'POST', {}, { form });
export const vipDelete = (id) => mksRequest(`/admin/vip/delete/${id}`, 'POST');
export const vipGoodsPriceIndex = (params) => mksRequest('/admin/vip/goods_price_index', 'GET', params);
export const vipGoodsPriceSave = (data) => mksRequest('/admin/vip/goods_price_save', 'POST', {}, data);
export const vipGoodsPriceClear = (data) => mksRequest('/admin/vip/goods_price_clear', 'POST', {}, data);
export const vipOrderIndex = (params) => mksRequest('/admin/vip/order_index', 'GET', params);
export const vipSettlementIndex = (params) => mksRequest('/admin/vip/settlement_index', 'GET', params);
export const vipSettlementSettle = (data) => mksRequest('/admin/vip/settlement_settle', 'POST', {}, data);
export const vipConfigRead = (params) => mksRequest('/admin/vip/config_read', 'GET', params);
export const vipConfigSave = (data) => mksRequest('/admin/vip/config_save', 'POST', {}, data);
export const vipDomainIndex = (params) => mksRequest('/admin/vip/domain_index', 'GET', params);
export const vipDomainSave = (data) => mksRequest('/admin/vip/domain_save', 'POST', {}, data);
export const vipDomainDelete = (data) => mksRequest('/admin/vip/domain_delete', 'POST', {}, data);

// ─── 商户（M6-B）───
export const merchantIndex = (params) => mksRequest('/admin/merchant/index', 'GET', params);
export const merchantRead = (id) => mksRequest(`/admin/merchant/read/${id}`, 'GET');
export const merchantCreate = (data) => mksRequest('/admin/merchant/create', 'POST', {}, data);
export const merchantSave = (id, data) => mksRequest(`/admin/merchant/save/${id}`, 'POST', {}, data);
export const merchantStatus = (data) => mksRequest('/admin/merchant/status', 'POST', {}, data);
export const merchantMemberOptions = (params) => mksRequest('/admin/merchant/member_options', 'GET', params);
export const merchantSupplyFlow = (params) => mksRequest('/admin/merchant/supply_flow', 'GET', params);
export const merchantSettlementIndex = (params) => mksRequest('/admin/merchant/settlement_index', 'GET', params);
export const merchantApplyIndex = (params) => mksRequest('/admin/merchant_apply/index', 'GET', params);
export const merchantApplyRead = (id) => mksRequest(`/admin/merchant_apply/read/${id}`, 'GET');
export const merchantApplyAudit = (id, data) => mksRequest(`/admin/merchant_apply/audit/${id}`, 'POST', {}, data);

// ─── 插件（M6-C）───
export const addonIndex = (params) => mksRequest('/admin/addon/index', 'GET', params);
export const addonScan = () => mksRequest('/admin/addon/scan', 'GET');
export const addonRead = (id) => mksRequest(`/admin/addon/read/${id}`, 'GET');
export const addonHooks = () => mksRequest('/admin/addon/hooks', 'GET');
export const addonInstall = (name) => mksRequest('/admin/addon/install', 'POST', {}, { name });
export const addonEnable = (id) => mksRequest('/admin/addon/enable', 'POST', {}, { id });
export const addonDisable = (id) => mksRequest('/admin/addon/disable', 'POST', {}, { id });
export const addonUninstall = (id, dropData = 0) => mksRequest('/admin/addon/uninstall', 'POST', {}, { id, drop_data: dropData });
export const addonConfigSave = (id, form) => mksRequest('/admin/addon/config_save', 'POST', {}, { id, form });
