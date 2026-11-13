<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-date-picker v-model="date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 160px" @change="load" />
          <el-input v-model="uid" placeholder="用户ID" clearable style="width: 110px" @keyup.enter="load" />
          <el-button type="primary" @click="load">查询</el-button>
          <el-button type="warning" plain :loading="updating" @click="doUpdate">更新对账单</el-button>
          <el-button plain @click="doExport">导出</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading" size="small">
        <el-table-column prop="total_create_time" label="日期" width="100" />
        <el-table-column prop="uid" label="UID" width="80" />
        <el-table-column label="期初余额" width="110"><template #default="{ row }">{{ row.money_before_balance }}</template></el-table-column>
        <el-table-column label="期末余额" width="110"><template #default="{ row }">{{ row.money_after_balance }}</template></el-table-column>
        <el-table-column label="加款" width="100"><template #default="{ row }">{{ row.money_add_money }}</template></el-table-column>
        <el-table-column label="扣款" width="100"><template #default="{ row }">{{ row.money_sub_money }}</template></el-table-column>
        <el-table-column label="充值" width="100"><template #default="{ row }">{{ row.recharge_money }}</template></el-table-column>
        <el-table-column label="订单利润" width="110"><template #default="{ row }"><b style="color:#67c23a">{{ row.order_order_price }}</b></template></el-table-column>
        <el-table-column label="订单金额" width="110"><template #default="{ row }">{{ row.order_total_price }}</template></el-table-column>
        <el-table-column label="订单成本" width="110"><template #default="{ row }">{{ row.order_cost_value }}</template></el-table-column>
        <el-table-column label="退款金额" width="110"><template #default="{ row }">{{ row.order_back_price }}</template></el-table-column>
        <el-table-column label="订单佣金" width="110"><template #default="{ row }">{{ row.order_commission_money }}</template></el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { moneyDayIndex, moneyDayUpdate, exportCsv } from '@/api';

const loading = ref(false);
const updating = ref(false);
const list = ref([]);
const date = ref(new Date(Date.now() - 86400000).toISOString().slice(0, 10));
const uid = ref('');

async function load() {
  loading.value = true;
  try {
    const res = await moneyDayIndex({ date: date.value || '', uid: uid.value || 0 });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

async function doUpdate() {
  updating.value = true;
  try {
    const res = await moneyDayUpdate({ date: date.value });
    ElMessage.success(res.msg || '已更新');
    load();
  } finally {
    updating.value = false;
  }
}

async function doExport() {
  await exportCsv('/admin/money_day/export', { date: date.value || '', uid: uid.value || 0 }, `moneyday_${date.value}.csv`);
  ElMessage.success('导出完成');
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
</style>
