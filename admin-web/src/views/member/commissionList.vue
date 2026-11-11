<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-input v-model="query.order_id" placeholder="订单ID" clearable style="width: 140px" @keyup.enter="doSearch" />
          <el-input v-model="query.uid" placeholder="会员ID" clearable style="width: 140px" @keyup.enter="doSearch" />
          <el-select v-model="query.sure_status" placeholder="结算状态" clearable style="width: 140px" @change="doSearch">
            <el-option label="待结算" :value="1" />
            <el-option label="已结算" :value="2" />
            <el-option label="已退款" :value="3" />
            <el-option label="部分退款" :value="4" />
          </el-select>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="order_id" label="订单ID" width="90" />
        <el-table-column label="层级" width="70">
          <template #default="{ row }">{{ row.layer === 2 ? '两级' : '一级' }}</template>
        </el-table-column>
        <el-table-column label="一级佣金" min-width="170">
          <template #default="{ row }">{{ row.uid1_name || row.uid1 }}（ID {{ row.uid1 }}）</template>
        </el-table-column>
        <el-table-column label="金额" width="100">
          <template #default="{ row }"><b style="color: #67c23a">{{ row.price1 }}</b></template>
        </el-table-column>
        <el-table-column label="二级佣金" min-width="170">
          <template #default="{ row }">{{ row.uid2 ? `${row.uid2_name || row.uid2}（ID ${row.uid2}）` : '—' }}</template>
        </el-table-column>
        <el-table-column label="金额" width="100">
          <template #default="{ row }">{{ row.uid2 ? row.price2 : '—' }}</template>
        </el-table-column>
        <el-table-column prop="commision_price" label="合计" width="90" />
        <el-table-column label="已回冲" width="90">
          <template #default="{ row }">{{ row.back_commision_price }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="sureTagType(row.sure_status)">{{ row.sure_status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="冻结至" width="160">
          <template #default="{ row }">{{ row.frozen_time > 0 ? formatTime(row.frozen_time) : '—' }}</template>
        </el-table-column>
        <el-table-column label="登记时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="110" fixed="right">
          <template #default="{ row }">
            <el-popconfirm v-if="row.sure_status === 1" title="确认强制结算入账？" width="200" @confirm="doSettle(row)">
              <template #reference><el-button link type="primary">结算</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { commissionIndex, commissionSettle } from '../../api';

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const query = ref({ page: 1, limit: 15, order_id: '', uid: '', sure_status: '' });

const load = async () => {
  loading.value = true;
  try {
    const params = { page: query.value.page, limit: query.value.limit };
    if (query.value.order_id) params.order_id = query.value.order_id;
    if (query.value.uid) params.uid = query.value.uid;
    if (query.value.sure_status !== '' && query.value.sure_status !== null) params.sure_status = query.value.sure_status;
    const res = await commissionIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
const resetQuery = () => {
  query.value = { page: 1, limit: 15, order_id: '', uid: '', sure_status: '' };
  load();
};
const doSettle = async (row) => {
  await commissionSettle(row.id);
  ElMessage.success('结算成功');
  load();
};
const sureTagType = (s) => ({ 1: 'warning', 2: 'success', 3: 'danger', 4: 'warning' }[s] || 'info');
const formatTime = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.pager { display: flex; justify-content: flex-end; margin-top: 12px; }
</style>
