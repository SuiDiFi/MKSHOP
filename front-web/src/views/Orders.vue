<template>
  <div>
    <Topbar />
    <div class="wrap page-body">
      <el-card shadow="never" style="margin-top: 14px">
        <template #header>
          <div style="display:flex; justify-content: space-between; align-items:center">
            <b>我的订单</b>
            <el-select v-model="query.status" placeholder="全部状态" clearable style="width: 140px" @change="doSearch">
              <el-option label="待支付" :value="0" />
              <el-option label="处理中" :value="1" />
              <el-option label="正在处理" :value="2" />
              <el-option label="已完成" :value="3" />
              <el-option label="已退款" :value="5" />
            </el-select>
          </div>
        </template>
        <el-table :data="list" v-loading="loading" @row-click="(r) => $router.push(`/order/${r.ordersn}`)" style="cursor:pointer">
          <el-table-column prop="ordersn" label="订单号" min-width="190" show-overflow-tooltip />
          <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
          <el-table-column label="数量" width="60" prop="quantity" />
          <el-table-column label="金额" width="90">
            <template #default="{ row }"><b style="color:#f56c6c">¥{{ row.total_price }}</b></template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="时间" width="160">
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
        </el-table>
        <div class="pager" v-if="total > query.limit">
          <el-pagination background layout="prev, pager, next" :total="total"
            :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { orderIndex } from '@/api';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 10, status: '' });

const load = async () => {
  loading.value = true;
  try {
    const params = { ...query.value };
    if (params.status === '' || params.status === null) delete params.status;
    const res = await orderIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '处理中', 2: '正在处理', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知');
const statusType = (s) => ({ 0: 'warning', 1: 'primary', 2: 'primary', 3: 'success', 5: 'info' }[s] || 'info');
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(load);
</script>
