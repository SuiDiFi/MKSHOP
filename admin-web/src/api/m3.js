// ─── M3 渠道补单 / 补货（独立文件，避免与其它模块改动交叉）───
import { mksRequest } from '@/utils/request';

/** 后台补单（已提渠道单→同步；未提交→重新渠道下单） */
export const orderBu = (id) => mksRequest('/admin/order/do_get_order', 'POST', {}, { ids: String(id) });

/** 停止补单 */
export const orderNoBu = (id) => mksRequest('/admin/order/no_bu_order', 'POST', {}, { ids: String(id) });

/** 渠道库存/成本同步（docking_id 留空=全部启用渠道） */
export const dockingSyncStock = (dockingId = 0) => mksRequest('/admin/docking/sync_stock', 'POST', {}, { docking_id: dockingId });
