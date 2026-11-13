<template>
  <div class="page-card" v-loading="loading">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-date-picker v-model="range" type="daterange" value-format="YYYY-MM-DD" :shortcuts="shortcuts" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 240px" @change="load" />
          <el-button type="primary" @click="load">查询</el-button>
          <el-button plain @click="setDays(7)">近 7 天</el-button>
          <el-button plain @click="setDays(30)">近 30 天</el-button>
          <el-button plain @click="setDays(90)">近 90 天</el-button>
          <el-divider direction="vertical" />
          <el-tooltip content="把区间内每日成交汇总写入 mks_statics（长区间图表走物化表，速度更快）">
            <el-button type="warning" plain :loading="aggLoading" @click="doAggregate">回算汇总</el-button>
          </el-tooltip>
          <el-tag v-if="materialized" type="success" effect="plain">长区间 · 读物化表</el-tag>
        </div>
      </template>

      <el-row :gutter="12">
        <el-col :span="6" v-for="k in kpis" :key="k.title">
          <div class="kpi" :style="{ borderColor: k.color }">
            <div class="kpi-title">{{ k.title }}</div>
            <div class="kpi-num" :style="{ color: k.color }">{{ k.value }}</div>
            <div class="kpi-foot">{{ k.foot }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never" style="margin-top: 12px">
      <template #header>成交趋势（按日）</template>
      <mini-chart :labels="dayChart.labels" :series="dayChart.series" :fmt="fmtShort" />
    </el-card>

    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>今日分时金额（成交额 / 利润，元）</template>
          <mini-chart type="bar" :labels="hourChart.labels" :series="hourChart.money" :fmt="fmtShort" />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>今日分时订单量（单）</template>
          <mini-chart type="bar" :labels="hourChart.labels" :series="hourChart.count" :fmt="fmtShort" />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="8" v-for="g in distGroups" :key="g.title">
        <el-card shadow="never">
          <template #header>{{ g.title }}</template>
          <div v-if="!g.rows.length" class="empty">区间内无成交</div>
          <div v-else class="dist">
            <div v-for="r in g.rows" :key="r.code" class="dist-row">
              <span class="dist-label">{{ r.label }}</span>
              <span class="dist-bar"><i :style="{ width: (r.count / g.max * 100).toFixed(1) + '%' }"></i></span>
              <span class="dist-val">{{ r.count }} 单 / {{ Number(r.amount || 0).toFixed(2) }} 元</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" style="margin-top: 12px">
      <template #header>明细（按日）</template>
      <el-table :data="trend.list || []" border size="small" max-height="420">
        <el-table-column prop="date" label="日期" width="110" />
        <el-table-column prop="order_count" label="订单量" width="90" />
        <el-table-column label="下单额" width="120"><template #default="{ row }">{{ row.order_amount }}</template></el-table-column>
        <el-table-column prop="success_count" label="成交单" width="90" />
        <el-table-column label="成交额" width="120"><template #default="{ row }"><b style="color:#67c23a">{{ row.success_amount }}</b></template></el-table-column>
        <el-table-column label="利润" width="120"><template #default="{ row }">{{ row.profit }}</template></el-table-column>
        <el-table-column label="成本" width="120"><template #default="{ row }">{{ row.cost }}</template></el-table-column>
        <el-table-column prop="buyers" label="成交人数" width="100" />
        <el-table-column label="退款额" width="120"><template #default="{ row }">{{ row.refund_amount }}</template></el-table-column>
        <el-table-column v-if="materialized" label="来源" width="90">
          <template #default="{ row }">{{ row.source === 'statics' ? '物化表' : '实时' }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { buyStatics, buyStaticsTrend, buyStaticsToday, staticsAggregate } from '@/api';
import miniChart from '@/components/miniChart.vue';

const loading = ref(false);
const aggLoading = ref(false);
const s = ref({});
const trend = ref({});
const today = ref({});

function iso(d) { return d.toISOString().slice(0, 10); }
const range = ref([iso(new Date(Date.now() - 6 * 86400000)), iso(new Date())]);

const shortcuts = [
  { text: '今天', value: () => [new Date(), new Date()] },
  { text: '近 7 天', value: () => [new Date(Date.now() - 6 * 86400000), new Date()] },
  { text: '近 30 天', value: () => [new Date(Date.now() - 29 * 86400000), new Date()] },
];

function setDays(n) {
  range.value = [iso(new Date(Date.now() - (n - 1) * 86400000)), iso(new Date())];
  load();
}

const money = (v) => (Number(v || 0)).toFixed(2);
const fmtShort = (v) => (v >= 10000 ? (v / 10000).toFixed(1) + 'w' : String(Math.round(v)));
const materialized = computed(() => trend.value.range?.materialized === true);

const kpis = computed(() => {
  const x = s.value;
  return [
    { title: '成交金额（元）', value: money(x.total_amount), color: '#67c23a', foot: `成交 ${x.success_count ?? 0} 单 / 下单 ${x.order_count ?? 0} 单` },
    { title: '利润（元）', value: money(x.profit), color: '#9254de', foot: `成本 ${money(x.cost)} 元 · 利润率 ${x.profit_rate ?? 0}%` },
    { title: '成交率', value: (x.success_rate ?? 0) + '%', color: '#409eff', foot: `客单价 ${money(x.avg_price)} 元 · 成交人数 ${x.buyers ?? 0}` },
    { title: '退款 / 取消', value: `${x.refund_count ?? 0} / ${x.cancel_count ?? 0}`, color: '#f56c6c', foot: `区间 ${x.range?.start} ~ ${x.range?.end}` },
  ];
});

const dayChart = computed(() => {
  const list = trend.value.list || [];
  return {
    labels: list.map((r) => r.date.slice(5)),
    series: [
      { name: '成交额', data: list.map((r) => Number(r.success_amount || 0)) },
      { name: '利润', color: '#9254de', data: list.map((r) => Number(r.profit || 0)) },
      { name: '退款额', color: '#f56c6c', data: list.map((r) => Number(r.refund_amount || 0)) },
    ],
  };
});

const hourChart = computed(() => {
  const list = today.value.list || [];
  // 金额与单量分图：同一坐标轴下 6 单会被 330 元压到看不出来
  return {
    labels: list.map((r) => r.hour),
    money: [
      { name: '成交额', color: '#67c23a', data: list.map((r) => Number(r.order_amount || 0)) },
      { name: '利润', color: '#9254de', data: list.map((r) => Number(r.profit || 0)) },
    ],
    count: [
      { name: '订单量', color: '#409eff', data: list.map((r) => Number(r.order_count || 0)) },
    ],
  };
});

/** 构成分组（统一算好宽度基准，避开在模板里写 Math.max） */
const distGroups = computed(() => {
  const defs = [
    { title: '供货方式构成', rows: s.value.provide_type || [] },
    { title: '支付网关构成', rows: s.value.pay_gateway || [] },
    { title: '主站 / 分站构成', rows: s.value.vip_site || [] },
  ];
  return defs.map((g) => ({ ...g, max: Math.max(1, ...g.rows.map((r) => Number(r.count || 0))) }));
});

async function load() {
  loading.value = true;
  try {
    const [a, b, c] = await Promise.all([
      buyStatics({ start: range.value[0], end: range.value[1] }),
      buyStaticsTrend({ start: range.value[0], end: range.value[1] }),
      buyStaticsToday(),
    ]);
    s.value = a.data || {};
    trend.value = b.data || {};
    today.value = c.data || {};
  } finally {
    loading.value = false;
  }
}

async function doAggregate() {
  aggLoading.value = true;
  try {
    const res = await staticsAggregate({ start: range.value[0], end: range.value[1] });
    ElMessage.success(res.msg || '已回算');
    load();
  } finally {
    aggLoading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.kpi { border-left: 3px solid #409eff; background: #fafafa; border-radius: 6px; padding: 12px 14px; }
.kpi-title { font-size: 12px; color: #909399; }
.kpi-num { font-size: 22px; font-weight: 700; margin: 6px 0 4px; }
.kpi-foot { font-size: 12px; color: #909399; min-height: 18px; }
.empty { color: #c0c4cc; font-size: 12px; text-align: center; padding: 16px 0; }
.dist-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px; }
.dist-label { width: 62px; color: #606266; flex: none; }
.dist-bar { flex: 1; height: 8px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.dist-bar i { display: block; height: 100%; background: #409eff; }
.dist-val { width: 132px; text-align: right; color: #909399; flex: none; }
</style>
