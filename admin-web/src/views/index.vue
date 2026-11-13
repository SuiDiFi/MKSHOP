<template>
  <div class="page-card" v-loading="loading">
    <!-- 顶部欢迎 + 今日摘要 -->
    <el-card shadow="never">
      <template #header>
        <div class="hd">
          <div>
            <b>MKSHOP 运营看板</b>
            <span class="sub">{{ user.userinfo.realname || user.userinfo.username }} · {{ today }}</span>
          </div>
          <div class="hd-right">
            <el-tag v-if="d.yesterday" type="info" effect="plain">
              昨日成交 {{ d.yesterday.success_amount }} 元 / {{ d.yesterday.success_count }} 单
            </el-tag>
            <el-button :icon="Refresh" circle title="刷新" @click="load" />
          </div>
        </div>
      </template>

      <el-row :gutter="12">
        <el-col :span="6" v-for="k in kpis" :key="k.title">
          <div class="kpi" :style="{ borderColor: k.color }">
            <div class="kpi-title">{{ k.title }}</div>
            <div class="kpi-num" :style="{ color: k.color }">{{ k.value }}</div>
            <div class="kpi-foot">
              <span v-if="k.delta !== null">
                较昨日
                <b :style="{ color: k.delta >= 0 ? '#67c23a' : '#f56c6c' }">
                  {{ k.delta >= 0 ? '+' : '' }}{{ k.delta }}%
                </b>
                · {{ k.foot }}
              </span>
              <span v-else>{{ k.foot }}</span>
            </div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 待办 -->
    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="16">
        <el-card shadow="never">
          <template #header>待处理事项</template>
          <div class="todo">
            <div v-for="t in todos" :key="t.label" class="todo-item" @click="go(t.path)">
              <div class="todo-num" :class="{ warn: t.value > 0 }">{{ t.value }}</div>
              <div class="todo-label">{{ t.label }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never">
          <template #header>平台总量</template>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="会员总数">{{ fmtNum(d.totals?.member_total) }}</el-descriptions-item>
            <el-descriptions-item label="在售商品">{{ fmtNum(d.totals?.goods_online) }} / {{ fmtNum(d.totals?.goods_total) }}</el-descriptions-item>
            <el-descriptions-item label="可售卡密">{{ fmtNum(d.totals?.card_available) }}</el-descriptions-item>
            <el-descriptions-item label="累计订单">{{ fmtNum(d.totals?.order_total) }}</el-descriptions-item>
            <el-descriptions-item label="会员余额合计">{{ fmtMoney(d.totals?.balance_total) }} 元</el-descriptions-item>
            <el-descriptions-item label="待结佣金合计">{{ fmtMoney(d.totals?.commission_total) }} 元</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- 图表（同一张图只放同量级序列，否则小量级序列会被压成贴地直线） -->
    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>近 7 日成交金额趋势（元）</template>
          <mini-chart :labels="trend7.labels" :series="trend7.money" :fmt="fmtShort" />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>近 7 日订单量（单）</template>
          <mini-chart type="bar" :labels="trend7.labels" :series="trend7.count" :fmt="fmtShort" />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>今日分时成交额（元）</template>
          <mini-chart type="bar" :labels="hourly.labels" :series="hourly.money" :fmt="fmtShort" />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>今日分时订单量（单）</template>
          <mini-chart type="bar" :labels="hourly.labels" :series="hourly.count" :fmt="fmtShort" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Refresh } from '@element-plus/icons-vue';
import { indexStatics } from '@/api';
import { useUserStore } from '@/store/user';
import miniChart from '@/components/miniChart.vue';

const router = useRouter();
const user = useUserStore();
const loading = ref(false);
const d = ref({});

const today = new Date().toISOString().slice(0, 10);

const fmtMoney = (v) => (v === undefined || v === null ? '-' : Number(v).toFixed(2));
const fmtNum = (v) => (v === undefined || v === null ? '-' : Number(v).toLocaleString());
const fmtShort = (v) => (v >= 10000 ? (v / 10000).toFixed(1) + 'w' : String(Math.round(v)));

/** 环比（昨日为 0 时不显示百分比，避免除零假象） */
function delta(todayVal, yestVal) {
  const t = Number(todayVal || 0);
  const y = Number(yestVal || 0);
  if (y === 0) return null;
  return Math.round(((t - y) / y) * 1000) / 10;
}

const kpis = computed(() => {
  const t = d.value.today || {};
  const y = d.value.yesterday || {};
  return [
    { title: '今日成交额（元）', value: fmtMoney(t.success_amount), color: '#67c23a', delta: delta(t.success_amount, y.success_amount), foot: `昨日 ${fmtMoney(y.success_amount)} 元` },
    { title: '今日成交订单', value: fmtNum(t.success_count), color: '#409eff', delta: delta(t.success_count, y.success_count), foot: `昨日 ${fmtNum(y.success_count)} 单` },
    { title: '今日订单量（含未成交）', value: fmtNum(t.order_count), color: '#e6a23c', delta: delta(t.order_count, y.order_count), foot: `下单额 ${fmtMoney(t.order_amount)} 元` },
    { title: '今日利润（元）', value: fmtMoney(t.profit), color: '#9254de', delta: delta(t.profit, y.profit), foot: `成交人数 ${fmtNum(t.buyers)} 人 · 客单价 ${fmtMoney(t.avg_price)} 元` },
  ];
});

const todos = computed(() => {
  const p = d.value.pending || {};
  return [
    { label: '等待处理订单', value: Number(p.wait_deal || 0), path: '/order/list' },
    { label: '正在处理订单', value: Number(p.dealing || 0), path: '/order/list' },
    { label: '未支付订单', value: Number(p.wait_pay || 0), path: '/order/list' },
    { label: '待处理售后', value: Number(p.complain_wait || 0), path: '/order/complain' },
    { label: '待审核商品', value: Number(p.goods_wait || 0), path: '/goods/list' },
    { label: '已售罄商品', value: Number(p.goods_sold_out || 0), path: '/goods/cardstock' },
    { label: '待审核商户', value: Number(p.apply_wait || 0), path: '/member/merchant' },
  ];
});

const trend7 = computed(() => {
  const list = d.value.trend7?.list || [];
  return {
    labels: list.map((r) => r.date.slice(5)),
    // 金额组（元）与数组组（单）分开绘制，避免混轴失真
    money: [
      { name: '成交额', data: list.map((r) => Number(r.success_amount || 0)) },
      { name: '利润', color: '#9254de', data: list.map((r) => Number(r.profit || 0)) },
    ],
    count: [
      { name: '订单量', color: '#e6a23c', data: list.map((r) => Number(r.order_count || 0)) },
      { name: '成交单', color: '#409eff', data: list.map((r) => Number(r.success_count || 0)) },
    ],
  };
});

const hourly = computed(() => {
  const list = d.value.today_hour?.list || [];
  return {
    labels: list.map((r) => r.hour),
    money: [
      { name: '成交额', color: '#67c23a', data: list.map((r) => Number(r.order_amount || 0)) },
    ],
    count: [
      { name: '订单量', color: '#409eff', data: list.map((r) => Number(r.order_count || 0)) },
    ],
  };
});

function go(path) {
  if (path) router.push(path);
}

async function load() {
  loading.value = true;
  try {
    const res = await indexStatics();
    d.value = res.data || {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.hd { display: flex; align-items: center; justify-content: space-between; }
.hd .sub { margin-left: 12px; color: #909399; font-size: 12px; }
.hd-right { display: flex; align-items: center; gap: 10px; }
.kpi { border-left: 3px solid #409eff; background: #fafafa; border-radius: 6px; padding: 12px 14px; }
.kpi-title { font-size: 12px; color: #909399; }
.kpi-num { font-size: 24px; font-weight: 700; margin: 6px 0 4px; }
.kpi-foot { font-size: 12px; color: #909399; min-height: 18px; }
.todo { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.todo-item { text-align: center; padding: 10px 4px; border-radius: 6px; cursor: pointer; background: #fafafa; transition: 0.15s; }
.todo-item:hover { background: #ecf5ff; }
.todo-num { font-size: 20px; font-weight: 700; color: #303133; }
.todo-num.warn { color: #e6a23c; }
.todo-label { font-size: 12px; color: #909399; margin-top: 2px; }
</style>
