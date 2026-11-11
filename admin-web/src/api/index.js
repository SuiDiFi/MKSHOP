import { mksRequest } from '@/utils/request';

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
