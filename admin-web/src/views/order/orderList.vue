<template>
  <div class="page-card">
    <el-card shadow="never">
      <!-- 状态页签（对齐 KASUSHOU：全部/等待支付/等待处理/正在处理/交易成功/取消交易/已经退款/取消支付/部分退款/异常订单） -->
      <el-tabs v-model="query.statusTab" @tab-change="doSearch">
        <el-tab-pane v-for="t in statusTabs" :key="String(t.value)" :label="t.label" :name="String(t.value)" />
      </el-tabs>

      <!-- 筛选区 -->
      <el-form inline size="default" class="filter-form" @submit.prevent>
        <el-form-item label="订单号"><el-input v-model="query.ordersn" clearable style="width: 170px" @keyup.enter="doSearch" /></el-form-item>
        <el-form-item label="下单参数"><el-input v-model="query.recharge_account" clearable style="width: 150px" @keyup.enter="doSearch" /></el-form-item>
        <el-form-item label="用户ID"><el-input v-model="query.uid" clearable style="width: 90px" @keyup.enter="doSearch" /></el-form-item>
        <el-form-item label="商品名称"><el-input v-model="query.goods_name" clearable style="width: 150px" @keyup.enter="doSearch" /></el-form-item>
        <template v-if="filterExpanded">
          <el-form-item label="支付方式">
            <el-select v-model="query.pay_type" clearable style="width: 110px" @change="doSearch">
              <el-option label="余额支付" value="balance" />
              <el-option label="在线支付" value="online" />
            </el-select>
          </el-form-item>
          <el-form-item label="下单来源">
            <el-select v-model="query.user_where" clearable style="width: 110px" @change="doSearch">
              <el-option label="PC" value="pc" />
              <el-option label="H5" value="h5" />
              <el-option label="小程序" value="mp" />
              <el-option label="API" value="api" />
            </el-select>
          </el-form-item>
          <el-form-item label="商品类型">
            <el-select v-model="query.goods_type" clearable style="width: 110px" @change="doSearch">
              <el-option v-for="s in goodsTypeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
            </el-select>
          </el-form-item>
          <el-form-item label="供货商ID"><el-input v-model="query.supplier_id" clearable style="width: 90px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="销售价">
            <el-input v-model="query.total_price_min" placeholder="≥" style="width: 90px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.total_price_max" placeholder="≤" style="width: 90px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="成本价">
            <el-input v-model="query.cost_value_min" placeholder="≥" style="width: 90px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.cost_value_max" placeholder="≤" style="width: 90px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="创建时间">
            <el-date-picker v-model="query.create_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="开始" end-placeholder="结束" style="width: 330px" @change="doSearch" />
          </el-form-item>
          <el-form-item label="完成时间">
            <el-date-picker v-model="query.finish_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="开始" end-placeholder="结束" style="width: 330px" @change="doSearch" />
          </el-form-item>
        </template>
        <el-form-item>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
          <el-button link type="primary" @click="filterExpanded = !filterExpanded">{{ filterExpanded ? '收起' : '展开' }}</el-button>
        </el-form-item>
      </el-form>

      <!-- 批量操作（对齐 KASUSHOU：批量删除/处理订单/批量补单/批量同步回调/导出订单/复制参数/自定义表格列） -->
      <div class="toolbar" style="margin-bottom: 10px">
        <el-button :disabled="!selection.length" type="danger" plain @click="batchDelete">批量删除({{ selection.length }})</el-button>
        <el-button :disabled="!selection.length" type="warning" plain @click="batchAction('bu')">批量补单</el-button>
        <el-button :disabled="!selection.length" type="warning" plain @click="batchAction('sync')">批量同步回调</el-button>
        <el-button type="primary" plain @click="doExport">导出订单</el-button>
        <el-button :disabled="!selection.length" plain @click="copyParams">复制参数</el-button>
        <el-dropdown style="margin-left: 8px" @command="(c) => toggleCol(c)">
          <el-button plain>自定义表格列<el-icon style="margin-left: 4px"><i-ep-arrow-down /></el-icon></el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item v-for="c in allColumns" :key="c.key" :command="c.key">
                <el-checkbox :model-value="c.visible" @click.prevent>{{ c.label }}</el-checkbox>
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
        <span class="muted" style="margin-left: 12px">已选 {{ selection.length }} 条</span>
      </div>

      <!-- 表格 -->
      <el-table :data="list" border v-loading="loading" @selection-change="(s) => (selection = s)">
        <el-table-column type="selection" width="42" fixed="left" />
        <el-table-column label="订单状态" width="110" v-if="colVisible('status')">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ row.status_text }}</el-tag>
            <el-tag v-if="Number(row.is_fail) === 1" type="danger" size="small" style="margin-left: 4px">异常</el-tag>
            <el-tag v-if="row.is_part_back" type="warning" size="small" style="margin-left: 4px">部分退款</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ordersn" label="订单号" width="190" show-overflow-tooltip v-if="colVisible('ordersn')" />
        <el-table-column prop="goods_name" label="商品名称" min-width="170" show-overflow-tooltip v-if="colVisible('goods_name')" />
        <el-table-column label="下单参数" width="130" show-overflow-tooltip v-if="colVisible('recharge_account')">
          <template #default="{ row }">{{ row.recharge_account || '—' }}</template>
        </el-table-column>
        <el-table-column label="用户/发货渠道" width="150" show-overflow-tooltip v-if="colVisible('user')">
          <template #default="{ row }">
            <div>{{ row.nickname }}（#{{ row.uid }}）</div>
            <div class="muted" v-if="row.supplier_name">渠道：{{ row.supplier_name }}</div>
            <div class="muted" v-else-if="Number(row.vip_id) > 0">分站：#{{ row.vip_id }}</div>
          </template>
        </el-table-column>
        <el-table-column label="销售价" width="95" v-if="colVisible('total_price')">
          <template #default="{ row }"><b style="color: #f56c6c">{{ row.total_price }}</b></template>
        </el-table-column>
        <el-table-column label="成本价" width="90" v-if="colVisible('cost_value')">
          <template #default="{ row }">{{ row.cost_value }}</template>
        </el-table-column>
        <el-table-column label="利润" width="90" v-if="colVisible('order_price')">
          <template #default="{ row }"><b style="color: #67c23a">{{ row.order_price }}</b></template>
        </el-table-column>
        <el-table-column label="下单时间" width="160" v-if="colVisible('create_time')">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="处理时间" width="160" v-if="colVisible('finish_time')">
          <template #default="{ row }">{{ row.finish_time > 0 ? formatTime(row.finish_time) : '—' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
            <el-button v-if="row.docking_info" link type="warning" @click="doSync(row)">同步</el-button>
            <el-button v-if="Number(row.provide_type) === 6 && [1, 2].includes(row.status)" link type="warning"
              @click="doBu(row)">补单</el-button>
            <el-button v-if="Number(row.provide_type) === 6 && row.status === 1" link type="info"
              @click="doNoBu(row)">停补</el-button>
            <el-button v-if="row.status === 1" link type="success" @click="doDual(row, 3)">处理成功</el-button>
            <el-button v-if="row.status === 0" link type="warning" @click="doDual(row, -1)">取消</el-button>
            <el-popconfirm v-if="row.status === 3" title="确认退款？余额原路退回，已发卡密将冻结" width="240"
              @confirm="doRefund(row)">
              <template #reference><el-button link type="danger">退款</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
      </div>
    </el-card>

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="订单详情" size="560px">
      <template v-if="detail.order">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="订单号">{{ detail.order.ordersn }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(detail.order.status)">{{ detail.order.status_text }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="商品">{{ detail.order.goods_name }} × {{ detail.order.quantity }}</el-descriptions-item>
          <el-descriptions-item label="充值账号">{{ detail.order.recharge_account || '—' }}</el-descriptions-item>
          <el-descriptions-item label="金额">{{ detail.order.total_price }}（单价 {{ detail.order.price }}）</el-descriptions-item>
          <el-descriptions-item label="成本/利润">{{ detail.order.cost_value }} / {{ detail.order.order_price }}</el-descriptions-item>
          <el-descriptions-item label="支付">{{ detail.order.pay_type || '—' }} @ {{ formatTime(detail.order.pay_time) }}</el-descriptions-item>
          <el-descriptions-item label="下单 IP">{{ detail.order.ip || '—' }}（{{ detail.order.user_where }}）</el-descriptions-item>
          <el-descriptions-item label="用户备注">{{ detail.order.mark || '—' }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin: 14px 0 8px">卡密明细</h4>
        <el-table :data="detail.cards" border size="small" empty-text="无卡密（非卡密商品或未发货）">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="card_no" label="卡号" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.card_no || '—' }}</template>
          </el-table-column>
          <el-table-column prop="card_password" label="卡密" min-width="140" show-overflow-tooltip />
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="['', 'success', 'info', 'danger'][row.status]" size="small">
                {{ ['', '待售', '已售', '冻结'][row.status] || row.status }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <h4 style="margin: 14px 0 8px">操作日志</h4>
        <el-timeline style="padding-left: 4px">
          <el-timeline-item v-for="lg in detail.logs" :key="lg.id" :timestamp="formatTime(lg.create_time)">
            {{ lg.mark }}
          </el-timeline-item>
        </el-timeline>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  orderIndex, orderInfo, orderGoodsCards, orderDual, orderDelete, orderStatusType, orderSync, exportCsv,
} from '@/api';
import { orderBu, orderNoBu } from '@/api/m3';
import { goodsGoodsType } from '@/api';

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const selection = ref([]);
const goodsTypeOptions = ref([]);
const filterExpanded = ref(false);
const detailVisible = ref(false);
const detail = ref({ order: null, logs: [], cards: [] });

// 状态页签（对齐 KASUSHOU 订单列表）
const statusTabs = [
  { label: '全部订单', value: '' },
  { label: '等待支付', value: 0 },
  { label: '等待处理', value: 1 },
  { label: '正在处理', value: 2 },
  { label: '交易成功', value: 3 },
  { label: '取消交易', value: 4 },
  { label: '已经退款', value: 5 },
  { label: '取消支付', value: -1 },
  { label: '部分退款', value: 'part' },
  { label: '异常订单', value: 'fail' },
];

// 列显隐（自定义表格列，localStorage 持久化）
const COLS_KEY = 'mks_order_cols';
const allColumns = ref([
  { key: 'status', label: '订单状态', visible: true },
  { key: 'ordersn', label: '订单号', visible: true },
  { key: 'goods_name', label: '商品名称', visible: true },
  { key: 'recharge_account', label: '下单参数', visible: true },
  { key: 'user', label: '用户/发货渠道', visible: true },
  { key: 'total_price', label: '销售价', visible: true },
  { key: 'cost_value', label: '成本价', visible: true },
  { key: 'order_price', label: '利润', visible: true },
  { key: 'create_time', label: '下单时间', visible: true },
  { key: 'finish_time', label: '处理时间', visible: true },
]);
try {
  const saved = JSON.parse(localStorage.getItem(COLS_KEY) || 'null');
  if (Array.isArray(saved)) {
    for (const c of allColumns.value) {
      c.visible = saved[c.key] !== false;
    }
  }
} catch (e) { /* 忽略本地存储异常 */ }
const colVisible = (key) => allColumns.value.find((c) => c.key === key)?.visible !== false;
function toggleCol(key) {
  const c = allColumns.value.find((x) => x.key === key);
  if (!c) return;
  c.visible = !c.visible;
  const map = {};
  for (const x of allColumns.value) map[x.key] = x.visible;
  localStorage.setItem(COLS_KEY, JSON.stringify(map));
}

const query = reactive({
  page: 1, limit: 15, statusTab: '', ordersn: '', recharge_account: '', uid: '', goods_name: '',
  pay_type: null, user_where: null, goods_type: null, supplier_id: '', total_price_min: '', total_price_max: '',
  cost_value_min: '', cost_value_max: '', create_range: null, finish_range: null,
});

const statusTagType = (s) => ({ [-1]: 'info', 0: 'warning', 1: 'warning', 2: 'primary', 3: 'success', 4: 'info', 5: 'danger' }[s] || 'info');
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

function buildWhere() {
  const where = [];
  const t = query.statusTab;
  if (t === 'part') {
    where.push({ field: 'has_back_money', op: 'gt', value: 0 }, { field: 'status', op: 'eq', value: 3 });
  } else if (t === 'fail') {
    where.push({ field: 'is_fail', op: 'eq', value: 1 });
  } else if (t !== '' && t !== null) {
    where.push({ field: 'status', op: 'eq', value: Number(t) });
  }
  if (query.ordersn) where.push({ field: 'ordersn', op: 'like', value: query.ordersn });
  if (query.recharge_account) where.push({ field: 'recharge_account', op: 'like', value: query.recharge_account });
  if (query.uid) where.push({ field: 'uid', op: 'eq', value: Number(query.uid) });
  if (query.goods_name) where.push({ field: 'goods_name', op: 'like', value: query.goods_name });
  if (query.pay_type) where.push({ field: 'pay_type', op: 'eq', value: query.pay_type });
  if (query.user_where) where.push({ field: 'user_where', op: 'eq', value: query.user_where });
  if (query.goods_type !== null && query.goods_type !== '') where.push({ field: 'goods_type', op: 'eq', value: Number(query.goods_type) });
  if (query.supplier_id) where.push({ field: 'supplier_id', op: 'eq', value: Number(query.supplier_id) });
  if (query.total_price_min !== '' || query.total_price_max !== '') {
    where.push({ field: 'total_price', op: 'between', value: [query.total_price_min || '0', query.total_price_max || '99999999'] });
  }
  if (query.cost_value_min !== '' || query.cost_value_max !== '') {
    where.push({ field: 'cost_value', op: 'between', value: [query.cost_value_min || '0', query.cost_value_max || '99999999'] });
  }
  if (Array.isArray(query.create_range) && query.create_range.length === 2) {
    where.push({ field: 'create_time', op: 'between', value: [ts(query.create_range[0]), ts(query.create_range[1])] });
  }
  if (Array.isArray(query.finish_range) && query.finish_range.length === 2) {
    where.push({ field: 'finish_time', op: 'between', value: [ts(query.finish_range[0]), ts(query.finish_range[1])] });
  }
  return JSON.stringify(where);
}

const ts = (v) => Math.floor(new Date(v).getTime() / 1000);

async function load() {
  loading.value = true;
  try {
    const res = await orderIndex({ page: query.page, limit: query.limit, where: buildWhere() });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
  } finally {
    loading.value = false;
  }
}

function doSearch() {
  query.page = 1;
  load();
}

function resetQuery() {
  Object.assign(query, {
    page: 1, statusTab: '', ordersn: '', recharge_account: '', uid: '', goods_name: '',
    pay_type: null, user_where: null, goods_type: null, supplier_id: '', total_price_min: '', total_price_max: '',
    cost_value_min: '', cost_value_max: '', create_range: null, finish_range: null,
  });
  load();
}

async function doExport() {
  await exportCsv('/admin/order/export', { where: buildWhere() }, `orders_${Date.now()}.csv`);
  ElMessage.success('导出完成（最多 5000 条）');
}

async function openDetail(row) {
  const [info, cards] = await Promise.all([orderInfo(row.id), orderGoodsCards(row.id)]);
  const order = { ...row, ...(info.data.order || {}) };
  detail.value = { order, logs: info.data.logs || [], cards: cards.data || [] };
  detailVisible.value = true;
}

async function doDual(row, status) {
  const res = await orderDual(row.id, { status });
  ElMessage.success(res.msg || '处理成功');
  load();
}

async function doSync(row) {
  const res = await orderSync(row.id);
  ElMessage.success(res.msg || '同步完成');
  load();
}

async function doBu(row) {
  const res = await orderBu(row.id);
  ElMessage.success(res.msg || '补单完成');
  load();
}

async function doNoBu(row) {
  const res = await orderNoBu(row.id);
  ElMessage.success(res.msg || '已停止补单');
  load();
}

async function doRefund(row) {
  const { value } = await ElMessageBox.prompt(
    `退款金额（实付 ${row.total_price}）`, '订单退款',
    { inputValue: String(row.total_price), inputPattern: /^\d+(\.\d{1,4})?$/, inputErrorMessage: '金额格式不正确', type: 'warning' }
  );
  const res = await orderDual(row.id, { status: 5, money: value, remark: '后台退款' });
  ElMessage.success(res.msg || '退款成功');
  load();
}

async function batchDelete() {
  await ElMessageBox.confirm(`确认删除选中的 ${selection.value.length} 条订单？`, '批量删除', { type: 'warning' });
  await runBatch((row) => orderDelete(row.id));
}

async function batchAction(kind) {
  const label = kind === 'bu' ? '批量补单' : '批量同步回调';
  await ElMessageBox.confirm(`确认对选中的 ${selection.value.length} 条订单执行${label}？`, label, { type: 'warning' });
  await runBatch((row) => (kind === 'bu' ? orderBu(row.id) : orderSync(row.id)));
}

// 批量执行：逐条调用现有单条接口，汇总成功/失败
async function runBatch(fn) {
  const results = await Promise.allSettled(selection.value.map((row) => fn(row)));
  const ok = results.filter((r) => r.status === 'fulfilled').length;
  const fail = results.length - ok;
  ElMessage[fail > 0 ? 'warning' : 'success'](`完成：成功 ${ok} 条${fail ? `，失败 ${fail} 条` : ''}`);
  load();
}

async function copyParams() {
  const text = selection.value.map((r) => `${r.ordersn}\t${r.recharge_account || ''}`).join('\n');
  await navigator.clipboard.writeText(text);
  ElMessage.success(`已复制 ${selection.value.length} 条（订单号+下单参数）`);
}

onMounted(async () => {
  const gt = await goodsGoodsType();
  goodsTypeOptions.value = gt.data || [];
  load();
});
</script>

<style scoped>
.filter-form :deep(.el-form-item) { margin-bottom: 8px; }
.muted { color: #909399; font-size: 12px; }
</style>
