<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-input v-model="query.keyword" placeholder="订单号/充值账号" clearable style="width: 200px" @keyup.enter="doSearch" />
          <el-select v-model="query.status" placeholder="全部状态" clearable style="width: 140px" @change="doSearch">
            <el-option v-for="s in statusOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
          </el-select>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="ordersn" label="订单号" width="200" show-overflow-tooltip />
        <el-table-column prop="goods_name" label="商品" min-width="180" show-overflow-tooltip />
        <el-table-column prop="recharge_account" label="充值账号" width="130" show-overflow-tooltip />
        <el-table-column label="数量" width="60">
          <template #default="{ row }">{{ row.quantity }}</template>
        </el-table-column>
        <el-table-column label="金额" width="90">
          <template #default="{ row }"><b style="color: #f56c6c">{{ row.total_price }}</b></template>
        </el-table-column>
        <el-table-column prop="nickname" label="买家" width="110" show-overflow-tooltip />
        <el-table-column prop="pay_type" label="支付" width="80" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="下单时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="210" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
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
  orderIndex, orderInfo, orderGoodsCards, orderDual, orderDelete, orderStatusType,
} from '@/api';

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const statusOptions = ref([]);
const query = reactive({ page: 1, limit: 15, keyword: '', status: null });
const detailVisible = ref(false);
const detail = ref({ order: null, logs: [], cards: [] });

const statusTagType = (s) => ({ [-1]: 'info', 0: 'warning', 1: 'warning', 2: 'primary', 3: 'success', 4: 'info', 5: 'danger' }[s] || 'info');
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

function buildWhere() {
  const where = [];
  if (query.keyword) {
    where.push(/^\d/.test(query.keyword)
      ? { field: 'ordersn', op: 'like', value: query.keyword }
      : { field: 'recharge_account', op: 'like', value: query.keyword });
  }
  if (query.status !== null && query.status !== '') where.push({ field: 'status', op: 'eq', value: query.status });
  return JSON.stringify(where);
}

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
  query.keyword = '';
  query.status = null;
  doSearch();
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

async function doRefund(row) {
  const { value } = await ElMessageBox.prompt(
    `退款金额（实付 ${row.total_price}）`, '订单退款',
    { inputValue: String(row.total_price), inputPattern: /^\d+(\.\d{1,4})?$/, inputErrorMessage: '金额格式不正确', type: 'warning' }
  );
  const res = await orderDual(row.id, { status: 5, money: value, remark: '后台退款' });
  ElMessage.success(res.msg || '退款成功');
  load();
}

onMounted(async () => {
  const st = await orderStatusType();
  statusOptions.value = st.data || [];
  load();
});
</script>
