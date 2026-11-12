<template>
  <div class="page plain">
    <NavBar title="资金明细" />
    <div class="wrap">
      <div class="summary">
        <div class="sum">
          <div class="muted">当前余额</div>
          <div class="v price">¥{{ balance }}</div>
        </div>
        <div class="sum">
          <div class="muted">累计收入</div>
          <div class="v" style="color:#16a34a">+{{ totalIncome }}</div>
        </div>
        <div class="sum">
          <div class="muted">累计支出</div>
          <div class="v" style="color:#f0483e">-{{ totalExpense }}</div>
        </div>
      </div>

      <div class="card" v-if="list.length" style="padding:0 12px">
        <div v-for="row in list" :key="row.id" class="row">
          <div>
            <div style="font-size:14px">{{ row.trade_type_text || '余额变动' }}</div>
            <div class="muted" style="margin-top:3px">{{ fmt(row.create_time) }} · {{ row.mark || '—' }}</div>
          </div>
          <div style="text-align:right">
            <div :style="{ color: Number(row.money) >= 0 ? '#16a34a' : '#f0483e', fontWeight: 700 }">
              {{ Number(row.money) >= 0 ? '+' : '' }}{{ row.money }}
            </div>
            <div class="muted">余额 {{ row.after }}</div>
          </div>
        </div>
      </div>
      <div v-else-if="!loading" class="empty"><div class="big">💰</div>暂无资金流水</div>
      <div class="pager" v-if="total > query.limit">
        <button :disabled="query.page <= 1" @click="pageTo(query.page - 1)">上一页</button>
        <span class="muted" style="align-self:center">{{ query.page }}/{{ Math.ceil(total / query.limit) }}</span>
        <button :disabled="query.page >= Math.ceil(total / query.limit)" @click="pageTo(query.page + 1)">下一页</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import NavBar from '@/components/NavBar.vue';
import { memberMoney } from '@/api';

const list = ref([]);
const total = ref(0);
const balance = ref('0.0000');
const totalIncome = ref('0.0000');
const totalExpense = ref('0.0000');
const loading = ref(false);
const query = ref({ page: 1, limit: 15 });

const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');
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
const pageTo = (p) => { query.value.page = p; load(); };
onMounted(load);
</script>

<style scoped>
.summary { display: flex; background: #fff; border-radius: 10px; padding: 14px; margin-top: 10px; }
.sum { flex: 1; text-align: center; }
.v { font-size: 18px; font-weight: 700; margin-top: 4px; }
.row { display: flex; justify-content: space-between; gap: 10px; padding: 12px 0; border-bottom: 1px solid #f6f7f9; }
.row:last-child { border-bottom: 0; }
</style>
