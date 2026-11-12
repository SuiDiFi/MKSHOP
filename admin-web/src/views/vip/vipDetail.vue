<template>
  <el-dialog v-model="visible" :title="`分站运营 · ${detail.site?.name || ''}`" width="1000px" top="6vh" destroy-on-close>
    <el-tabs v-model="tab">
      <!-- 概览 -->
      <el-tab-pane label="概览" name="overview">
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="分站ID">{{ detail.site?.id }}</el-descriptions-item>
          <el-descriptions-item label="分站主">{{ detail.owner ? `${detail.owner.nickname || detail.owner.username}（UID ${detail.owner.id}）` : '—' }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ Number(detail.site?.status) === 1 ? '启用' : '停用' }}</el-descriptions-item>
          <el-descriptions-item label="加价率">{{ detail.site?.rate }}%</el-descriptions-item>
          <el-descriptions-item label="平台抽成">{{ detail.site?.share_rate }}%</el-descriptions-item>
          <el-descriptions-item label="主站商品">{{ Number(detail.site?.allow_main_goods) === 1 ? '展示' : '不展示' }}</el-descriptions-item>
          <el-descriptions-item label="订单数">{{ detail.stats?.order_count }}</el-descriptions-item>
          <el-descriptions-item label="订单总额">{{ detail.stats?.order_sales }}</el-descriptions-item>
          <el-descriptions-item label="平台利润">{{ detail.stats?.platform_profit }}</el-descriptions-item>
          <el-descriptions-item label="站长得成">{{ detail.stats?.share_total }}</el-descriptions-item>
          <el-descriptions-item label="待结算">{{ detail.stats?.share_pending }}</el-descriptions-item>
          <el-descriptions-item label="已回冲">{{ detail.stats?.share_refunded }}</el-descriptions-item>
        </el-descriptions>
      </el-tab-pane>

      <!-- 商品定价 -->
      <el-tab-pane label="商品定价" name="goods">
        <div class="bar">
          <el-input v-model="goodsQuery.keyword" placeholder="商品名称" clearable style="width: 200px" @keyup.enter="loadGoods" />
          <el-button @click="loadGoods">查询</el-button>
          <span class="tip">默认价 = 基准价 × (1+分站加价率)；商品级覆盖优先</span>
        </div>
        <el-table :data="goods" v-loading="loadingGoods" border size="small" max-height="430">
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="goods_name" label="商品" min-width="200" show-overflow-tooltip />
          <el-table-column prop="base_price" label="基准价" width="100" />
          <el-table-column prop="vip_price" label="分站售价" width="100" />
          <el-table-column label="定价方式" width="110">
            <template #default="{ row }">
              {{ row.has_override ? modeText(row.vip_mode) : '分站比例' }}
            </template>
          </el-table-column>
          <el-table-column label="加价" width="90">
            <template #default="{ row }">{{ row.add_price }}</template>
          </el-table-column>
          <el-table-column label="可见" width="70">
            <template #default="{ row }"><el-tag size="small" :type="row.visible ? 'success' : 'info'">{{ row.visible ? '是' : '否' }}</el-tag></template>
          </el-table-column>
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

      <!-- 订单 -->
      <el-tab-pane label="订单" name="order">
        <div class="bar">
          <el-input v-model="orderQuery.keyword" placeholder="订单号/商品名" clearable style="width: 200px" @keyup.enter="loadOrders" />
          <el-select v-model="orderQuery.status" placeholder="状态" clearable style="width: 120px" @change="loadOrders">
            <el-option label="等待支付" :value="0" />
            <el-option label="等待处理" :value="1" />
            <el-option label="正在处理" :value="2" />
            <el-option label="交易成功" :value="3" />
            <el-option label="已退款" :value="5" />
          </el-select>
          <el-button @click="loadOrders">查询</el-button>
        </div>
        <el-table :data="orders" v-loading="loadingOrders" border size="small" max-height="430">
          <el-table-column prop="ordersn" label="订单号" min-width="180" />
          <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
          <el-table-column prop="quantity" label="数量" width="70" />
          <el-table-column prop="vip_goods_price" label="分站价" width="100" />
          <el-table-column prop="total_price" label="总额" width="100" />
          <el-table-column prop="vip_add_price" label="加价" width="90" />
          <el-table-column prop="vip_profit_price" label="平台利润" width="100" />
          <el-table-column label="状态" width="100"><template #default="{ row }">{{ statusText(row.status) }}</template></el-table-column>
          <el-table-column label="下单时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
        </el-table>
        <div class="pager">
          <el-pagination background layout="total, prev, pager, next" :total="orderTotal"
            :page-size="orderQuery.limit" v-model:current-page="orderQuery.page" @current-change="loadOrders" />
        </div>
      </el-tab-pane>

      <!-- 结算 -->
      <el-tab-pane label="结算" name="settle">
        <div class="bar">
          <el-select v-model="settleQuery.sure_status" placeholder="结算状态" clearable style="width: 140px" @change="loadSettles">
            <el-option label="待结算" :value="1" />
            <el-option label="已结算" :value="2" />
            <el-option label="已退款" :value="3" />
            <el-option label="部分退款" :value="4" />
          </el-select>
          <el-button @click="loadSettles">查询</el-button>
          <el-button type="warning" @click="settleAll">解冻本站待结算</el-button>
        </div>
        <el-table :data="settles" v-loading="loadingSettles" border size="small" max-height="430">
          <el-table-column prop="ordersn" label="订单号" min-width="180" />
          <el-table-column prop="goods_name" label="商品" min-width="150" show-overflow-tooltip />
          <el-table-column prop="commision_price" label="站长得成" width="110" />
          <el-table-column prop="back_commision_price" label="已回冲" width="100" />
          <el-table-column label="状态" width="100"><template #default="{ row }">{{ sureText(row.sure_status) }}</template></el-table-column>
          <el-table-column label="冻结到期" width="160"><template #default="{ row }">{{ Number(row.frozen_time) > 0 ? fmt(row.frozen_time) : '立即' }}</template></el-table-column>
          <el-table-column label="操作" width="90" fixed="right">
            <template #default="{ row }">
              <el-button v-if="Number(row.sure_status) === 1" link type="primary" @click="settleOne(row)">解冻</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="pager">
          <el-pagination background layout="total, prev, pager, next" :total="settleTotal"
            :page-size="settleQuery.limit" v-model:current-page="settleQuery.page" @current-change="loadSettles" />
        </div>
      </el-tab-pane>

      <!-- 配置 -->
      <el-tab-pane label="配置" name="config">
        <el-form label-width="110px" style="max-width: 720px">
          <el-form-item label="站点名称"><el-input v-model="config.site_name" /></el-form-item>
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
          <el-form-item>
            <el-button type="primary" :loading="savingConfig" @click="saveConfig">保存配置</el-button>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 域名 -->
      <el-tab-pane label="域名" name="domain">
        <div class="bar">
          <el-button type="success" @click="openDomain()">新增域名</el-button>
          <span class="tip">域名唯一；分站域名解析以 sys_url 为唯一权威</span>
        </div>
        <el-table :data="domains" border size="small">
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="url" label="域名" min-width="200" />
          <el-table-column label="类型" width="100"><template #default="{ row }">{{ Number(row.is_main) === 1 ? '主站' : '分站' }}</template></el-table-column>
          <el-table-column prop="template_pc" label="PC模板" width="120" />
          <el-table-column prop="template" label="H5模板" width="120" />
          <el-table-column label="操作" width="130" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" @click="openDomain(row)">编辑</el-button>
              <el-button v-if="Number(row.is_main) !== 1" link type="danger" @click="removeDomain(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 商品定价弹窗 -->
    <el-dialog v-model="priceVisible" title="商品级定价" width="420px" append-to-body>
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

    <!-- 域名弹窗 -->
    <el-dialog v-model="domainVisible" title="域名绑定" width="460px" append-to-body>
      <el-form label-width="90px">
        <el-form-item label="域名"><el-input v-model="domainForm.url" placeholder="如 shop.example.com" /></el-form-item>
        <el-form-item label="PC模板"><el-input v-model="domainForm.template_pc" /></el-form-item>
        <el-form-item label="H5模板"><el-input v-model="domainForm.template" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="domainVisible = false">取消</el-button>
        <el-button type="primary" @click="saveDomain">保存</el-button>
      </template>
    </el-dialog>
  </el-dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  vipRead, vipGoodsPriceIndex, vipGoodsPriceSave, vipGoodsPriceClear,
  vipOrderIndex, vipSettlementIndex, vipSettlementSettle,
  vipConfigRead, vipConfigSave, vipDomainIndex, vipDomainSave, vipDomainDelete,
} from '../../api';

const props = defineProps({ modelValue: Boolean, siteId: [Number, String] });
const emit = defineEmits(['update:modelValue']);
const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

const tab = ref('overview');
const detail = ref({});
const goods = ref([]);
const goodsTotal = ref(0);
const loadingGoods = ref(false);
const goodsQuery = ref({ vip_id: 0, page: 1, limit: 15, keyword: '' });
const orders = ref([]);
const orderTotal = ref(0);
const loadingOrders = ref(false);
const orderQuery = ref({ vip_id: 0, page: 1, limit: 15, keyword: '', status: '' });
const settles = ref([]);
const settleTotal = ref(0);
const loadingSettles = ref(false);
const settleQuery = ref({ vip_id: 0, page: 1, limit: 15, sure_status: '' });
const config = ref({});
const savingConfig = ref(false);
const domains = ref([]);
const priceVisible = ref(false);
const priceGoods = ref({});
const priceForm = ref({ mode: 1, value: 0 });
const domainVisible = ref(false);
const domainForm = ref({});

const modeText = (m) => ({ 1: '比例加价', 2: '固定加价', 3: '固定售价' }[Number(m)] || '—');
const sureText = (s) => ({ 1: '待结算', 2: '已结算', 3: '已退款', 4: '部分退款' }[Number(s)] || '—');
const statusText = (s) => ({ '-1': '取消支付', 0: '等待支付', 1: '等待处理', 2: '正在处理', 3: '交易成功', 4: '取消交易', 5: '已退款' }[Number(s)] ?? '—');
const fmt = (t) => (Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN') : '—');

const loadAll = async () => {
  if (!props.siteId) return;
  const res = await vipRead(props.siteId);
  detail.value = res.data || {};
  goodsQuery.value.vip_id = props.siteId;
  orderQuery.value.vip_id = props.siteId;
  settleQuery.value.vip_id = props.siteId;
  loadGoods();
  loadOrders();
  loadSettles();
  loadConfig();
  loadDomains();
};

const loadGoods = async () => {
  loadingGoods.value = true;
  try {
    const res = await vipGoodsPriceIndex(goodsQuery.value);
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
  await vipGoodsPriceSave({ vip_id: props.siteId, goods_id: priceGoods.value.id, ...priceForm.value });
  ElMessage.success('已保存');
  priceVisible.value = false;
  loadGoods();
};
const clearPrice = async (row) => {
  await vipGoodsPriceClear({ vip_id: props.siteId, goods_id: row.id });
  ElMessage.success('已恢复默认');
  loadGoods();
};

const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await vipOrderIndex(orderQuery.value);
    orders.value = res.data?.list || [];
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
const settleOne = async (row) => {
  await vipSettlementSettle({ id: row.id });
  ElMessage.success('已解冻');
  loadSettles();
  loadAll();
};
const settleAll = async () => {
  await ElMessageBox.confirm('确认解冻本站全部待结算记录？', '提示', { type: 'warning' });
  await vipSettlementSettle({ vip_id: props.siteId });
  ElMessage.success('已处理');
  loadSettles();
  loadAll();
};

const loadConfig = async () => {
  const res = await vipConfigRead({ vip_id: props.siteId });
  config.value = res.data?.config || {};
};
const saveConfig = async () => {
  savingConfig.value = true;
  try {
    await vipConfigSave({ vip_id: props.siteId, ...config.value });
    ElMessage.success('已保存');
  } finally { savingConfig.value = false; }
};

const loadDomains = async () => {
  const res = await vipDomainIndex({ vip_id: props.siteId });
  domains.value = res.data || [];
};
const openDomain = (row) => {
  domainForm.value = row ? { ...row } : { vip_id: props.siteId, url: '', template: 'default', template_pc: 'default' };
  domainVisible.value = true;
};
const saveDomain = async () => {
  await vipDomainSave({ vip_id: props.siteId, ...domainForm.value });
  ElMessage.success('已保存');
  domainVisible.value = false;
  loadDomains();
};
const removeDomain = async (row) => {
  await vipDomainDelete({ id: row.id });
  ElMessage.success('已删除');
  loadDomains();
};

watch(() => props.modelValue, (v) => { if (v) { tab.value = 'overview'; loadAll(); } });
</script>

<style scoped>
.bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
.tip { color: #909399; font-size: 12px; }
.pager { display: flex; justify-content: flex-end; margin-top: 10px; }
</style>
