<template>
  <mks-page>
    <template #alert>
      渠道日结按供货渠道汇总当日订单利润、成本与佣金；快捷入口：
      <a class="quick-link" @click="goCfg('/finance/moneyday')">客户日结</a>·
      <a class="quick-link" @click="goCfg('/goods/docking')">对接渠道</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="对账日期">
            <el-date-picker v-model="query.date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 160px" @change="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #toolbar>
      <el-button size="small" type="warning" plain :loading="updating" @click="doUpdate">更新对账单</el-button>
    </template>

    <el-table :data="list" border v-loading="loading" size="small">
      <template #empty><mks-empty text="当天没有渠道对账数据" hint="换个日期，或先点「更新对账单」生成" /></template>
      <el-table-column prop="day" label="日期" width="110" />
      <el-table-column prop="supplier_id" label="渠道ID" width="80" />
      <el-table-column prop="supplier_name" label="渠道名称" min-width="140" />
      <el-table-column label="订单利润" width="120"><template #default="{ row }"><b style="color:#67c23a">{{ row.order_profit }}</b></template></el-table-column>
      <el-table-column label="订单金额" width="120"><template #default="{ row }">{{ row.order_total }}</template></el-table-column>
      <el-table-column label="订单成本" width="120"><template #default="{ row }">{{ row.order_cost }}</template></el-table-column>
      <el-table-column label="订单佣金" width="120"><template #default="{ row }">{{ row.order_commission }}</template></el-table-column>
    </el-table>
  </mks-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { dockingDayIndex, dockingDayUpdate } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const updating = ref(false);
const list = ref([]);
const query = reactive({ date: new Date(Date.now() - 86400000).toISOString().slice(0, 10) });

async function load() {
  loading.value = true;
  try {
    const res = await dockingDayIndex({ date: query.date || '' });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

const doSearch = () => load();
const resetQuery = () => {
  query.date = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
  load();
};

async function doUpdate() {
  updating.value = true;
  try {
    const res = await dockingDayUpdate({ date: query.date });
    ElMessage.success(res.msg || '已更新');
    load();
  } finally {
    updating.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
</style>
