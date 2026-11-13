<template>
  <mks-page>
    <template #alert>
      客户日结按用户汇总期初期末、加扣款与订单利润；快捷入口：
      <a class="quick-link" @click="goCfg('/finance/dockingday')">渠道日结</a>·
      <a class="quick-link" @click="goCfg('/finance/withdraw')">提现记录</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="对账日期">
            <el-date-picker v-model="query.date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 160px" @change="doSearch" />
          </el-form-item>
          <el-form-item label="用户ID">
            <el-input v-model="query.uid" placeholder="用户ID" clearable style="width: 120px" @keyup.enter="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #toolbar>
      <el-button size="small" type="warning" plain :loading="updating" @click="doUpdate">更新对账单</el-button>
      <el-button size="small" plain @click="doExport">导出</el-button>
    </template>

    <el-table :data="list" border v-loading="loading" size="small">
      <template #empty><mks-empty text="当天没有日结记录" hint="换个日期，或先点「更新对账单」生成" /></template>
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
  </mks-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { moneyDayIndex, moneyDayUpdate, exportCsv } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const updating = ref(false);
const list = ref([]);
const query = reactive({
  date: new Date(Date.now() - 86400000).toISOString().slice(0, 10),
  uid: '',
});

async function load() {
  loading.value = true;
  try {
    const res = await moneyDayIndex({ date: query.date || '', uid: query.uid || 0 });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

const doSearch = () => load();
const resetQuery = () => {
  query.date = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
  query.uid = '';
  load();
};

async function doUpdate() {
  updating.value = true;
  try {
    const res = await moneyDayUpdate({ date: query.date });
    ElMessage.success(res.msg || '已更新');
    load();
  } finally {
    updating.value = false;
  }
}

async function doExport() {
  await exportCsv('/admin/money_day/export', { date: query.date || '', uid: query.uid || 0 }, `moneyday_${query.date}.csv`);
  ElMessage.success('导出完成');
}

onMounted(load);
</script>

<style scoped>
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
</style>
