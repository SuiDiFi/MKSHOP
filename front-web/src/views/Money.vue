<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 900px">
      <el-card shadow="never" style="margin-top: 14px">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <b>资金明细</b>
            <div style="color:#909399; font-size:13px">当前余额：<b style="color:#f56c6c">¥{{ balance }}</b></div>
          </div>
        </template>
        <div class="money-sum">
          <div class="sum-item">
            <div class="sum-label">累计收入</div>
            <div class="sum-val" style="color:#67c23a">+{{ totalIncome }}</div>
          </div>
          <div class="sum-item">
            <div class="sum-label">累计支出</div>
            <div class="sum-val" style="color:#f56c6c">-{{ totalExpense }}</div>
          </div>
        </div>
        <el-table :data="list" v-loading="loading">
          <el-table-column label="时间" width="170">
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
          <el-table-column prop="trade_type_text" label="类型" width="110" />
          <el-table-column label="变动金额" width="130">
            <template #default="{ row }">
              <span :style="{ color: Number(row.money) >= 0 ? '#67c23a' : '#f56c6c' }">
                {{ Number(row.money) >= 0 ? '+' : '' }}{{ row.money }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="变动后余额" width="120">
            <template #default="{ row }">¥{{ row.after }}</template>
          </el-table-column>
          <el-table-column prop="mark" label="备注" min-width="160" show-overflow-tooltip />
        </el-table>
        <el-empty v-if="!loading && !list.length" description="暂无资金流水" />
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
import { memberMoney } from '@/api';

const list = ref([]);
const total = ref(0);
const balance = ref('0.0000');
const totalIncome = ref('0.0000');
const totalExpense = ref('0.0000');
const loading = ref(false);
const query = ref({ page: 1, limit: 15 });

const load = async () => {
  loading.value = true;
  try {
    const res = await memberMoney(query.value);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
    balance.value = res.data?.balance ?? '0.0000';
    totalIncome.value = res.data?.total_income ?? '0.0000';
    totalExpense.value = res.data?.total_expense ?? '0.0000';
  } finally {
    loading.value = false;
  }
};
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');
onMounted(load);
</script>

<style scoped>
.money-sum { display: flex; gap: 12px; margin-bottom: 14px; }
.sum-item { flex: 1; background: #f8f9fb; border-radius: 8px; padding: 12px 16px; }
.sum-label { font-size: 13px; color: #909399; }
.sum-val { font-size: 20px; font-weight: 700; margin-top: 4px; }
</style>
