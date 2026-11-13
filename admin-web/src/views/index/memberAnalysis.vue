<template>
  <div class="page-card" v-loading="loading">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-date-picker v-model="range" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 240px" @change="load" />
          <el-button type="primary" @click="load">查询</el-button>
          <el-button plain @click="setDays(7)">近 7 天</el-button>
          <el-button plain @click="setDays(30)">近 30 天</el-button>
          <el-button plain @click="setDays(90)">近 90 天</el-button>
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
      <template #header>会员趋势（按日）</template>
      <div class="chart-sub">新增会员 / 成交人数（人）</div>
      <mini-chart :labels="trendChart.labels" :series="trendChart.people" :fmt="fmtShort" />
      <el-divider style="margin: 10px 0" />
      <div class="chart-sub">成交金额（元）</div>
      <mini-chart type="bar" :labels="trendChart.labels" :series="trendChart.money" :fmt="fmtShort" />
    </el-card>

    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>等级分布</template>
          <el-table :data="s.level_dist || []" border size="small" empty-text="暂无会员">
            <el-table-column prop="level_name" label="等级" min-width="140" />
            <el-table-column prop="cnt" label="人数" width="100" sortable />
            <el-table-column label="余额合计" width="150" sortable prop="balance"><template #default="{ row }">{{ row.balance }}</template></el-table-column>
            <el-table-column label="占比" min-width="160">
              <template #default="{ row }">
                <div class="ratio"><i :style="{ width: pct(row.cnt) }"></i></div>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>资金与充值</template>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="会员余额合计">{{ money(s.balance_total) }} 元</el-descriptions-item>
            <el-descriptions-item label="待结佣金合计">{{ money(s.commission_total) }} 元</el-descriptions-item>
            <el-descriptions-item label="区间充值笔数">{{ s.recharge_count ?? 0 }} 笔</el-descriptions-item>
            <el-descriptions-item label="区间充值金额">{{ money(s.recharge_amount) }} 元</el-descriptions-item>
            <el-descriptions-item label="其中赠送金额">{{ money(s.recharge_give) }} 元</el-descriptions-item>
            <el-descriptions-item label="区间统计天数">{{ s.range?.days ?? 0 }} 天</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { userStatics, userStaticsTrend } from '@/api';
import miniChart from '@/components/miniChart.vue';

const loading = ref(false);
const s = ref({});
const trend = ref({});

function iso(d) { return d.toISOString().slice(0, 10); }
const range = ref([iso(new Date(Date.now() - 29 * 86400000)), iso(new Date())]);

function setDays(n) {
  range.value = [iso(new Date(Date.now() - (n - 1) * 86400000)), iso(new Date())];
  load();
}

const money = (v) => Number(v || 0).toFixed(2);
const fmtShort = (v) => (v >= 10000 ? (v / 10000).toFixed(1) + 'w' : String(Math.round(v)));
const pct = (n) => ((Number(n || 0) / Math.max(1, Number(s.value.member_total || 0))) * 100).toFixed(1) + '%';

const kpis = computed(() => {
  const x = s.value;
  return [
    { title: '会员总数', value: (x.member_total ?? 0).toLocaleString(), color: '#409eff', foot: `今日新增 ${x.today_new ?? 0} 人` },
    { title: '区间新增会员', value: (x.new_member ?? 0).toLocaleString(), color: '#67c23a', foot: `区间 ${x.range?.days ?? 0} 天` },
    { title: '区间成交人数', value: (x.buyers ?? 0).toLocaleString(), color: '#e6a23c', foot: `活跃成交率 ${x.active_rate ?? 0}%` },
    { title: '区间充值', value: `${x.recharge_count ?? 0} 笔`, color: '#9254de', foot: `${money(x.recharge_amount)} 元（含赠送 ${money(x.recharge_give)}）` },
  ];
});

const trendChart = computed(() => {
  const list = trend.value.list || [];
  return {
    labels: list.map((r) => r.date.slice(5)),
    // 人数与金额分图，避免混轴失真
    people: [
      { name: '新增会员', data: list.map((r) => Number(r.new_member || 0)) },
      { name: '成交人数', color: '#e6a23c', data: list.map((r) => Number(r.buyers || 0)) },
    ],
    money: [
      { name: '成交金额', color: '#67c23a', data: list.map((r) => Number(r.user_amount || 0)) },
    ],
  };
});

async function load() {
  loading.value = true;
  try {
    const [a, b] = await Promise.all([
      userStatics({ start: range.value[0], end: range.value[1] }),
      userStaticsTrend({ start: range.value[0], end: range.value[1] }),
    ]);
    s.value = a.data || {};
    trend.value = b.data || {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.kpi { border-left: 3px solid #409eff; background: #fafafa; border-radius: 6px; padding: 12px 14px; }
.kpi-title { font-size: 12px; color: #909399; }
.kpi-num { font-size: 22px; font-weight: 700; margin: 6px 0 4px; }
.kpi-foot { font-size: 12px; color: #909399; min-height: 18px; }
.ratio { height: 8px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.ratio i { display: block; height: 100%; background: #409eff; }
.chart-sub { font-size: 12px; color: #909399; margin-bottom: 4px; }
</style>
