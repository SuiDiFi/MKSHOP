<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-date-picker v-model="date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 160px" @change="load" />
          <el-button type="primary" @click="load">查询</el-button>
          <el-button type="warning" plain :loading="updating" @click="doUpdate">更新对账单</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading" size="small">
        <el-table-column prop="day" label="日期" width="110" />
        <el-table-column prop="supplier_id" label="渠道ID" width="80" />
        <el-table-column prop="supplier_name" label="渠道名称" min-width="140" />
        <el-table-column label="订单利润" width="120"><template #default="{ row }"><b style="color:#67c23a">{{ row.order_profit }}</b></template></el-table-column>
        <el-table-column label="订单金额" width="120"><template #default="{ row }">{{ row.order_total }}</template></el-table-column>
        <el-table-column label="订单成本" width="120"><template #default="{ row }">{{ row.order_cost }}</template></el-table-column>
        <el-table-column label="订单佣金" width="120"><template #default="{ row }">{{ row.order_commission }}</template></el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { dockingDayIndex, dockingDayUpdate } from '@/api';

const loading = ref(false);
const updating = ref(false);
const list = ref([]);
const date = ref(new Date(Date.now() - 86400000).toISOString().slice(0, 10));

async function load() {
  loading.value = true;
  try {
    const res = await dockingDayIndex({ date: date.value || '' });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

async function doUpdate() {
  updating.value = true;
  try {
    const res = await dockingDayUpdate({ date: date.value });
    ElMessage.success(res.msg || '已更新');
    load();
  } finally {
    updating.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
</style>
