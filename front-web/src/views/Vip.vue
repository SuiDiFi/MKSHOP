<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 1100px">
      <div class="vip-head" v-loading="loading">
        <div>
          <div class="vip-title">{{ site.name || '我的分站' }}</div>
          <div class="vip-meta">
            <span>分站ID：{{ site.id }}</span>
            <span>加价率：{{ site.rate }}%</span>
            <span>平台抽成：{{ site.share_rate }}%</span>
            <span v-if="site.path_prefix">目录：/{{ site.path_prefix }}</span>
          </div>
        </div>
        <div class="wallet">
          <div><div class="label">可提佣金</div><div class="num">¥{{ wallet.commission_money || '0.0000' }}</div></div>
          <div><div class="label">冻结中</div><div class="num warn">¥{{ wallet.freez_commission_money || '0.0000' }}</div></div>
        </div>
      </div>

      <el-tabs v-model="tab" style="margin-top: 8px">
        <el-tab-pane label="概览" name="overview">
          <div class="stat-grid">
            <div class="stat"><div class="k">今日订单</div><div class="v">{{ orders.today }}</div></div>
            <div class="stat"><div class="k">今日销售额</div><div class="v">¥{{ orders.today_sales || '0.00' }}</div></div>
            <div class="stat"><div class="k">累计订单</div><div class="v">{{ orders.total }}</div></div>
            <div class="stat"><div class="k">累计销售额</div><div class="v">¥{{ orders.sales || '0.00' }}</div></div>
            <div class="stat"><div class="k">待结算抽成</div><div class="v warn">¥{{ share.pending || '0.0000' }}</div></div>
            <div class="stat"><div class="k">累计抽成</div><div class="v">¥{{ share.total || '0.0000' }}</div></div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="商品定价" name="goods">
          <div class="bar">
            <el-input v-model="goodsQuery.keyword" placeholder="商品名称" clearable style="width: 200px" @keyup.enter="loadGoods" />
            <el-button @click="loadGoods">查询</el-button>
            <span class="tip">默认价 = 基准价 × (1+分站加价率)，商品级定价优先</span>
          </div>
          <el-table :data="goods" v-loading="loadingGoods" border size="small" max-height="460">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="goods_name" label="商品" min-width="220" show-overflow-tooltip />
            <el-table-column prop="base_price" label="基准价" width="100" />
            <el-table-column prop="vip_price" label="本站售价" width="100" />
            <el-table-column label="定价方式" width="110">
              <template #default="{ row }">{{ row.has_override ? modeText(row.vip_mode) : '分站比例' }}</template>
            </el-table-column>
            <el-table-column label="加价" width="90"><template #default="{ row }">{{ row.add_price }}</template></el-table-column>
            <el-table-column label="操作" width="130" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openPrice(row)">定价</el-button>
                <el-button v-if="row.has_override" link type="danger" @click="clearPrice(row)">恢复</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="goodsTotal"
              :page-size="goodsQuery.limit" v-model:current-page="goodsQuery.page" @current-change="loadGoods" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="订单" name="order">
          <div class="bar">
            <el-input v-model="orderQuery.keyword" placeholder="订单号/商品名" clearable style="width: 200px" @keyup.enter="loadOrders" />
            <el-button @click="loadOrders">查询</el-button>
          </div>
          <el-table :data="orders" v-loading="loadingOrders" border size="small" max-height="460">
            <el-table-column prop="ordersn" label="订单号" min-width="180" />
            <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
            <el-table-column prop="quantity" label="数量" width="70" />
            <el-table-column prop="vip_goods_price" label="本站价" width="100" />
            <el-table-column prop="total_price" label="总额" width="100" />
            <el-table-column label="状态" width="100"><template #default="{ row }">{{ statusText(row.status) }}</template></el-table-column>
            <el-table-column label="下单时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="orderTotal"
              :page-size="orderQuery.limit" v-model:current-page="orderQuery.page" @current-change="loadOrders" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="结算" name="settle">
          <div class="bar">
            <el-select v-model="settleQuery.sure_status" placeholder="结算状态" clearable style="width: 140px" @change="loadSettles">
              <el-option label="待结算" :value="1" />
              <el-option label="已结算" :value="2" />
              <el-option label="已退款" :value="3" />
              <el-option label="部分退款" :value="4" />
            </el-select>
            <el-button @click="loadSettles">查询</el-button>
          </div>
          <el-table :data="settles" v-loading="loadingSettles" border size="small" max-height="460">
            <el-table-column prop="ordersn" label="订单号" min-width="180" />
            <el-table-column prop="goods_name" label="商品" min-width="150" show-overflow-tooltip />
            <el-table-column prop="commision_price" label="抽成金额" width="110" />
            <el-table-column prop="back_commision_price" label="已回冲" width="100" />
            <el-table-column label="状态" width="100"><template #default="{ row }">{{ sureText(row.sure_status) }}</template></el-table-column>
            <el-table-column label="冻结到期" width="160"><template #default="{ row }">{{ Number(row.frozen_time) > 0 ? fmt(row.frozen_time) : '立即' }}</template></el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="settleTotal"
              :page-size="settleQuery.limit" v-model:current-page="settleQuery.page" @current-change="loadSettles" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="抽成流水" name="logs">
          <el-table :data="logs" v-loading="loadingLogs" border size="small" max-height="460">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="money" label="金额" width="110" />
            <el-table-column label="类型" width="120"><template #default="{ row }">{{ tradeText(row.trade_type) }}</template></el-table-column>
            <el-table-column prop="before" label="变更前" width="110" />
            <el-table-column prop="after" label="变更后" width="110" />
            <el-table-column prop="mark" label="备注" min-width="220" show-overflow-tooltip />
            <el-table-column label="时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="logTotal"
              :page-size="logQuery.limit" v-model:current-page="logQuery.page" @current-change="loadLogs" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="站点配置" name="config">
          <el-form label-width="110px" style="max-width: 680px">
            <el-form-item label="站点名称"><el-input v-model="config.site_name" :placeholder="site.name" /></el-form-item>
            <el-form-item label="Logo地址"><el-input v-model="config.site_logo" /></el-form-item>
            <el-form-item label="主题色"><el-input v-model="config.theme_color" /></el-form-item>
            <el-form-item label="SEO标题"><el-input v-model="config.seo_title" /></el-form-item>
            <el-form-item label="SEO关键词"><el-input v-model="config.seo_keywords" /></el-form-item>
            <el-form-item label="SEO描述"><el-input v-model="config.seo_description" type="textarea" :rows="2" /></el-form-item>
            <el-form-item label="客服QQ"><el-input v-model="config.kefu_qq" /></el-form-item>
            <el-form-item label="客服微信"><el-input v-model="config.kefu_wx" /></el-form-item>
            <el-form-item label="关闭站点">
              <el-switch v-model="config.close_site" active-value="1" inactive-value="0" />
            </el-form-item>
            <el-form-item label="关闭提示"><el-input v-model="config.close_tip" type="textarea" :rows="2" /></el-form-item>
            <el-form-item><el-button type="primary" :loading="saving" @click="saveConfig">保存配置</el-button></el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="绑定域名" name="domain">
          <el-table :data="domains" border size="small">
            <el-table-column prop="url" label="域名" min-width="220" />
            <el-table-column label="类型" width="100"><template #default="{ row }">{{ Number(row.is_main) === 1 ? '主站' : '分站' }}</template></el-table-column>
            <el-table-column prop="template_pc" label="PC模板" width="140" />
            <el-table-column prop="template" label="H5模板" width="140" />
          </el-table>
          <el-alert type="info" :closable="false" style="margin-top: 10px"
            title="域名由平台管理员绑定；如需新增域名请联系平台客服。" />
        </el-tab-pane>
      </el-tabs>
    </div>

    <el-dialog v-model="priceVisible" title="商品级定价" width="420px">
      <el-form label-width="90px">
        <el-form-item label="商品"><span>{{ priceGoods.goods_name }}</span></el-form-item>
        <el-form-item label="基准价"><span>{{ priceGoods.base_price }}</span></el-form-item>
        <el-form-item label="定价方式">
          <el-select v-model="priceForm.mode" style="width: 100%">
            <el-option label="比例加价（%）" :value="1" />
            <el-option label="固定加价（元）" :value="2" />
            <el-option label="固定售价（元）" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="数值">
          <el-input-number v-model="priceForm.value" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="priceVisible = false">取消</el-button>
        <el-button type="primary" @click="savePrice">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import Topbar from '@/components/Topbar.vue';
import {
  vipOverview, vipGoodsIndex, vipGoodsPriceSave, vipGoodsPriceClear,
  vipOrderIndex, vipSettlementIndex, vipCommissionLogs,
  vipConfigRead, vipConfigSave, vipDomainIndex,
} from '@/api';

const tab = ref('overview');
const loading = ref(true);
const site = ref({});
const wallet = ref({});
const orders = ref({});
const share = ref({});
const goods = ref([]);
const goodsTotal = ref(0);
const loadingGoods = ref(false);
const goodsQuery = ref({ page: 1, limit: 15, keyword: '' });
const orderList = ref([]);
const orderTotal = ref(0);
const loadingOrders = ref(false);
const orderQuery = ref({ page: 1, limit: 15, keyword: '' });
const settles = ref([]);
const settleTotal = ref(0);
const loadingSettles = ref(false);
const settleQuery = ref({ page: 1, limit: 15, sure_status: '' });
const logs = ref([]);
const logTotal = ref(0);
const loadingLogs = ref(false);
const logQuery = ref({ page: 1, limit: 15 });
const config = ref({});
const saving = ref(false);
const domains = ref([]);
const priceVisible = ref(false);
const priceGoods = ref({});
const priceForm = ref({ mode: 1, value: 0 });

const modeText = (m) => ({ 1: '比例加价', 2: '固定加价', 3: '固定售价' }[Number(m)] || '—');
const sureText = (s) => ({ 1: '待结算', 2: '已结算', 3: '已退款', 4: '部分退款' }[Number(s)] || '—');
const tradeText = (t) => ({ 3: '抽成入账', 4: '抽成回冲', 7: '抽成冻结', 8: '抽成解冻' }[Number(t)] || '—');
const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '等待处理', 2: '正在处理', 3: '已完成', 4: '已取消', 5: '已退款' }[Number(s)] || '—');
const fmt = (t) => (Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

const loadOverview = async () => {
  const res = await vipOverview();
  const d = res.data || {};
  site.value = d.site || {};
  wallet.value = d.wallet || {};
  orders.value = d.orders || {};
  share.value = d.share || {};
};
const loadGoods = async () => {
  loadingGoods.value = true;
  try {
    const res = await vipGoodsIndex(goodsQuery.value);
    goods.value = res.data?.list || [];
    goodsTotal.value = res.data?.count || 0;
  } finally { loadingGoods.value = false; }
};
const openPrice = (row) => {
  priceGoods.value = row;
  priceForm.value = { mode: Number(row.vip_mode) || 1, value: Number(row.vip_value) || 0 };
  priceVisible.value = true;
};
const savePrice = async () => {
  await vipGoodsPriceSave({ goods_id: priceGoods.value.id, ...priceForm.value });
  ElMessage.success('已保存');
  priceVisible.value = false;
  loadGoods();
};
const clearPrice = async (row) => {
  await vipGoodsPriceClear({ goods_id: row.id });
  ElMessage.success('已恢复默认');
  loadGoods();
};
const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await vipOrderIndex(orderQuery.value);
    orderList.value = res.data?.list || [];
    orderTotal.value = res.data?.count || 0;
  } finally { loadingOrders.value = false; }
};
const loadSettles = async () => {
  loadingSettles.value = true;
  try {
    const res = await vipSettlementIndex(settleQuery.value);
    settles.value = res.data?.list || [];
    settleTotal.value = res.data?.count || 0;
  } finally { loadingSettles.value = false; }
};
const loadLogs = async () => {
  loadingLogs.value = true;
  try {
    const res = await vipCommissionLogs(logQuery.value);
    logs.value = res.data?.list || [];
    logTotal.value = res.data?.count || 0;
  } finally { loadingLogs.value = false; }
};
const loadConfig = async () => {
  const res = await vipConfigRead();
  config.value = res.data?.config || {};
};
const saveConfig = async () => {
  saving.value = true;
  try {
    await vipConfigSave(config.value);
    ElMessage.success('已保存');
  } finally { saving.value = false; }
};
const loadDomains = async () => {
  const res = await vipDomainIndex();
  domains.value = res.data || [];
};

const loaded = {};
watch(tab, (t) => {
  if (loaded[t]) return;
  loaded[t] = true;
  if (t === 'goods') loadGoods();
  if (t === 'order') loadOrders();
  if (t === 'settle') loadSettles();
  if (t === 'logs') loadLogs();
  if (t === 'config') loadConfig();
  if (t === 'domain') loadDomains();
});

onMounted(async () => {
  try {
    await loadOverview();
  } finally {
    loading.value = false;
  }
  loaded.overview = true;
});
</script>

<style scoped>
.vip-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 14px;
  padding: 20px 22px; border-radius: 10px; color: #fff; background: linear-gradient(120deg, #409eff, #79bbff); flex-wrap: wrap; }
.vip-title { font-size: 22px; font-weight: 700; }
.vip-meta { font-size: 13px; opacity: .9; margin-top: 8px; display: flex; gap: 14px; flex-wrap: wrap; }
.wallet { display: flex; gap: 26px; text-align: right; }
.wallet .label { font-size: 12px; opacity: .85; }
.wallet .num { font-size: 20px; font-weight: 700; margin-top: 2px; }
.wallet .num.warn { color: #ffe58f; }
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 6px; }
.stat { background: #fff; border-radius: 8px; padding: 16px; }
.stat .k { color: #909399; font-size: 13px; }
.stat .v { font-size: 22px; font-weight: 700; margin-top: 6px; color: #303133; }
.stat .v.warn { color: #e6a23c; }
.bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
.tip { color: #909399; font-size: 12px; }
.pager { display: flex; justify-content: flex-end; margin-top: 10px; }
@media (max-width: 768px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
