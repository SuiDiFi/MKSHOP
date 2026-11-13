<template>
  <div class="page-card" v-loading="loading">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-date-picker v-model="range" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 240px" @change="load" />
          <el-button type="primary" @click="load">查询</el-button>
          <el-button plain @click="setDays(7)">近 7 天</el-button>
          <el-button plain @click="setDays(30)">近 30 天</el-button>
          <span class="tip">统计口径：成交（status=3）按 finish_time 归日，与财务对账一致</span>
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

    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="16">
        <el-card shadow="never">
          <template #header>商品出单趋势（按日）</template>
          <div class="chart-sub">销售额（元）</div>
          <mini-chart :labels="trendChart.labels" :series="trendChart.money" :fmt="fmtShort" />
          <el-divider style="margin: 10px 0" />
          <div class="chart-sub">销量 / 出单商品数（个）</div>
          <mini-chart type="bar" :labels="trendChart.labels" :series="trendChart.count" :fmt="fmtShort" />
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never">
          <template #header>商品状态分布</template>
          <div v-if="!s.status_dist?.length" class="empty">暂无商品</div>
          <div v-else class="dist">
            <div v-for="r in s.status_dist" :key="r.code" class="dist-row">
              <span class="dist-label">{{ r.label }}</span>
              <span class="dist-bar"><i :style="{ width: (r.count / statusMax * 100).toFixed(1) + '%' }"></i></span>
              <span class="dist-val">{{ r.count }} 个</span>
            </div>
          </div>
          <el-divider style="margin: 8px 0" />
          <div class="mini-title">供货方式分布</div>
          <div v-if="!s.provide_dist?.length" class="empty">暂无数据</div>
          <div v-else class="dist">
            <div v-for="r in s.provide_dist" :key="r.code" class="dist-row">
              <span class="dist-label">{{ r.label }}</span>
              <span class="dist-bar"><i :style="{ width: (r.count / provideMax * 100).toFixed(1) + '%' }"></i></span>
              <span class="dist-val">{{ r.count }} 个</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" style="margin-top: 12px">
      <template #header>
        <div class="toolbar">
          <b>商品排行</b>
          <el-radio-group v-model="sort" size="small" @change="loadRank">
            <el-radio-button value="sales">按销量</el-radio-button>
            <el-radio-button value="amount">按销售额</el-radio-button>
            <el-radio-button value="orders">按订单数</el-radio-button>
            <el-radio-button value="browse">按浏览量</el-radio-button>
          </el-radio-group>
          <el-select v-model="limit" size="small" style="width: 110px" @change="loadRank">
            <el-option v-for="n in [10, 20, 50, 100]" :key="n" :label="`Top ${n}`" :value="n" />
          </el-select>
        </div>
      </template>
      <el-table :data="rank.list || []" border size="small" empty-text="区间内无成交商品">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="goods_name" label="商品" min-width="200" show-overflow-tooltip />
        <el-table-column prop="goods_no" label="编号" width="130" />
        <el-table-column prop="provide_type_label" label="供货方式" width="100" />
        <el-table-column prop="sales_qty" label="销量" width="90" sortable />
        <el-table-column label="销售额" width="130" sortable prop="amount"><template #default="{ row }"><b style="color:#67c23a">{{ row.amount }}</b></template></el-table-column>
        <el-table-column label="利润" width="130" prop="profit"><template #default="{ row }">{{ row.profit }}</template></el-table-column>
        <el-table-column prop="orders" label="订单数" width="90" />
        <el-table-column prop="browse" label="浏览量" width="90" />
        <el-table-column prop="stock_num" label="库存" width="80" />
        <el-table-column label="售价" width="100"><template #default="{ row }">{{ Number(row.real_price).toFixed(2) }}</template></el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { goodsStatics, goodsStaticsTrend, goodsStaticsList } from '@/api';
import miniChart from '@/components/miniChart.vue';

const loading = ref(false);
const s = ref({});
const trend = ref({});
const rank = ref({});
const sort = ref('sales');
const limit = ref(20);

function iso(d) { return d.toISOString().slice(0, 10); }
const range = ref([iso(new Date(Date.now() - 29 * 86400000)), iso(new Date())]);

function setDays(n) {
  range.value = [iso(new Date(Date.now() - (n - 1) * 86400000)), iso(new Date())];
  load();
}

const fmtShort = (v) => (v >= 10000 ? (v / 10000).toFixed(1) + 'w' : String(Math.round(v)));
const money = (v) => Number(v || 0).toFixed(2);

const kpis = computed(() => {
  const x = s.value;
  return [
    { title: '在售商品', value: x.goods_online ?? '-', color: '#409eff', foot: `商品总数 ${x.goods_total ?? 0} 个` },
    { title: '区间销量', value: x.sales_qty ?? 0, color: '#67c23a', foot: `出单商品 ${x.active_goods ?? 0} 个 · 销售额 ${money(x.sales_amount)} 元` },
    { title: '可售卡密', value: (x.card_available ?? 0).toLocaleString(), color: '#e6a23c', foot: `库存预警商品 ${x.stock_warn ?? 0} 个` },
    { title: '累计浏览量', value: (x.browse_total ?? 0).toLocaleString(), color: '#9254de', foot: `累计销量 ${x.sales_num_all ?? 0}` },
  ];
});

const statusMax = computed(() => Math.max(1, ...(s.value.status_dist || []).map((r) => Number(r.count || 0))));
const provideMax = computed(() => Math.max(1, ...(s.value.provide_dist || []).map((r) => Number(r.count || 0))));

const trendChart = computed(() => {
  const list = trend.value.list || [];
  return {
    labels: list.map((r) => r.date.slice(5)),
    // 金额与件数/个数分图：混轴会让小量级序列看不出起伏
    money: [
      { name: '销售额', data: list.map((r) => Number(r.sales_amount || 0)) },
    ],
    count: [
      { name: '销量', color: '#e6a23c', data: list.map((r) => Number(r.sales_qty || 0)) },
      { name: '出单商品数', color: '#9254de', data: list.map((r) => Number(r.active_goods || 0)) },
    ],
  };
});

async function loadRank() {
  const res = await goodsStaticsList({ start: range.value[0], end: range.value[1], sort: sort.value, limit: limit.value });
  rank.value = res.data || {};
}

async function load() {
  loading.value = true;
  try {
    const [a, b] = await Promise.all([
      goodsStatics({ start: range.value[0], end: range.value[1] }),
      goodsStaticsTrend({ start: range.value[0], end: range.value[1] }),
    ]);
    s.value = a.data || {};
    trend.value = b.data || {};
    await loadRank();
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.tip { color: #909399; font-size: 12px; margin-left: auto; }
.kpi { border-left: 3px solid #409eff; background: #fafafa; border-radius: 6px; padding: 12px 14px; }
.kpi-title { font-size: 12px; color: #909399; }
.kpi-num { font-size: 22px; font-weight: 700; margin: 6px 0 4px; }
.kpi-foot { font-size: 12px; color: #909399; min-height: 18px; }
.mini-title { font-size: 12px; color: #606266; margin-bottom: 6px; }
.chart-sub { font-size: 12px; color: #909399; margin-bottom: 4px; }
.empty { color: #c0c4cc; font-size: 12px; text-align: center; padding: 14px 0; }
.dist-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px; }
.dist-label { width: 62px; color: #606266; flex: none; }
.dist-bar { flex: 1; height: 8px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.dist-bar i { display: block; height: 100%; background: #409eff; }
.dist-val { width: 72px; text-align: right; color: #909399; flex: none; }
</style>
