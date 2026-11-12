<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 1100px">
      <template v-if="loading">
        <el-skeleton :rows="6" animated style="margin-top: 16px" />
      </template>

      <!-- 未开通商户：入驻申请 -->
      <template v-else-if="!hasMerchant">
        <div class="apply-head">
          <div class="apply-title">商家入驻</div>
          <div class="apply-tip">提交店铺资料后由平台审核，通过后即可在商户中心管理供货商品与货款。</div>
        </div>

        <el-alert v-if="!info.enabled" type="warning" :closable="false" style="margin-top: 12px"
          title="商户入驻暂未开放，请稍后再试。" />

        <template v-else>
          <el-alert v-if="apply && apply.status === 1" type="info" :closable="false" style="margin-top: 12px"
            title="入驻申请已提交，等待平台审核。" />
          <el-alert v-if="apply && apply.status === 3" type="error" :closable="false" style="margin-top: 12px"
            :title="`上次申请已被拒绝：${apply.audit_mark || '无备注'}，可修改后重新提交。`" />

          <el-card v-if="!apply || apply.status !== 1" shadow="never" style="margin-top: 12px">
            <el-form label-width="100px" style="max-width: 620px">
              <el-form-item label="店铺名" required>
                <el-input v-model="form.merchant_name" maxlength="100" placeholder="请输入店铺名称" />
              </el-form-item>
              <el-form-item label="联系人" required>
                <el-input v-model="form.contact" maxlength="50" placeholder="请输入联系人姓名" />
              </el-form-item>
              <el-form-item label="联系电话" required>
                <el-input v-model="form.mobile" maxlength="30" placeholder="请输入联系电话" />
              </el-form-item>
              <el-form-item label="店铺简介">
                <el-input v-model="form.intro" type="textarea" :rows="3" maxlength="1000" show-word-limit />
              </el-form-item>
              <el-form-item label="资质图片">
                <el-input v-model="form.license_img" placeholder="营业执照/资质图片地址（可选）" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="submitting" @click="submitApply">提交申请</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card v-if="apply" shadow="never" style="margin-top: 12px">
            <template #header><b>申请记录</b></template>
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item label="店铺名">{{ apply.merchant_name }}</el-descriptions-item>
              <el-descriptions-item label="联系人">{{ apply.contact }}</el-descriptions-item>
              <el-descriptions-item label="联系电话">{{ apply.mobile }}</el-descriptions-item>
              <el-descriptions-item label="状态">{{ apply_status_text }}</el-descriptions-item>
              <el-descriptions-item label="提交时间">{{ fmt(apply.create_time) }}</el-descriptions-item>
              <el-descriptions-item label="审核时间">{{ apply.audit_time > 0 ? fmt(apply.audit_time) : '—' }}</el-descriptions-item>
              <el-descriptions-item label="审核备注" :span="2">{{ apply.audit_mark || '—' }}</el-descriptions-item>
            </el-descriptions>
          </el-card>
        </template>
      </template>

      <!-- 已开通商户：商户中心 -->
      <template v-else>
        <div class="merchant-head">
          <div>
            <div class="merchant-title">{{ merchant.name || '我的店铺' }}</div>
            <div class="merchant-meta">
              <span>商户ID：{{ merchant.id }}</span>
              <span>联系人：{{ merchant.contact || '—' }}</span>
              <span>结算方式：成本结算</span>
            </div>
          </div>
          <div class="wallet">
            <div><div class="label">可提货款</div><div class="num">¥{{ wallet.supply_money || '0.0000' }}</div></div>
            <div><div class="label">冻结中</div><div class="num warn">¥{{ wallet.freez_supply_money || '0.0000' }}</div></div>
          </div>
        </div>

        <el-tabs v-model="tab" style="margin-top: 8px">
          <el-tab-pane label="概览" name="overview">
            <div class="stat-grid">
              <div class="stat"><div class="k">今日订单</div><div class="v">{{ orders.today || 0 }}</div></div>
              <div class="stat"><div class="k">今日销售额</div><div class="v">¥{{ orders.today_sales || '0.00' }}</div></div>
              <div class="stat"><div class="k">累计订单</div><div class="v">{{ orders.total || 0 }}</div></div>
              <div class="stat"><div class="k">累计销售额</div><div class="v">¥{{ orders.sales || '0.00' }}</div></div>
              <div class="stat"><div class="k">待结算货款</div><div class="v warn">¥{{ supply.pending || '0.0000' }}</div></div>
              <div class="stat"><div class="k">已结算货款</div><div class="v">¥{{ supply.settled || '0.0000' }}</div></div>
            </div>
            <el-card shadow="never" style="margin-top: 12px">
              <template #header><b>近 7 日销售</b></template>
              <el-table :data="daily" size="small" border>
                <el-table-column prop="day" label="日期" width="140" />
                <el-table-column prop="orders" label="订单数" width="120" />
                <el-table-column prop="sales" label="销售额" />
              </el-table>
              <el-empty v-if="!daily.length" description="暂无销售数据" :image-size="60" />
            </el-card>
          </el-tab-pane>

          <el-tab-pane label="订单" name="order">
            <div class="bar">
              <el-select v-model="orderQuery.status" placeholder="订单状态" clearable style="width: 140px" @change="loadOrders">
                <el-option label="待支付" :value="0" />
                <el-option label="等待处理" :value="1" />
                <el-option label="正在处理" :value="2" />
                <el-option label="已完成" :value="3" />
                <el-option label="已退款" :value="5" />
              </el-select>
              <el-input v-model="orderQuery.keyword" placeholder="订单号/商品名" clearable style="width: 200px" @keyup.enter="loadOrders" />
              <el-button @click="loadOrders">查询</el-button>
            </div>
            <el-table :data="orderList" v-loading="loadingOrders" border size="small" max-height="460">
              <el-table-column prop="ordersn" label="订单号" min-width="180" />
              <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
              <el-table-column prop="quantity" label="数量" width="70" />
              <el-table-column prop="total_price" label="金额" width="100" />
              <el-table-column label="状态" width="100"><template #default="{ row }">{{ statusText(row.status) }}</template></el-table-column>
              <el-table-column label="下单时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
              <el-table-column label="操作" width="90" fixed="right">
                <template #default="{ row }"><el-button link type="primary" @click="openOrder(row)">详情</el-button></template>
              </el-table-column>
            </el-table>
            <div class="pager">
              <el-pagination background layout="total, prev, pager, next" :total="orderTotal"
                :page-size="orderQuery.limit" v-model:current-page="orderQuery.page" @current-change="loadOrders" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="商品" name="goods">
            <div class="bar">
              <el-input v-model="goodsQuery.keyword" placeholder="商品名称" clearable style="width: 200px" @keyup.enter="loadGoods" />
              <el-button @click="loadGoods">查询</el-button>
              <span class="tip">商户商品维护请在平台后台提交，本页仅查看</span>
            </div>
            <el-table :data="goodsList" v-loading="loadingGoods" border size="small" max-height="460">
              <el-table-column prop="id" label="ID" width="70" />
              <el-table-column prop="goods_name" label="商品" min-width="220" show-overflow-tooltip />
              <el-table-column prop="real_price" label="售价" width="100" />
              <el-table-column prop="sales_num" label="销量" width="90" />
              <el-table-column label="状态" width="100"><template #default="{ row }">{{ goodsStatusText(row.status) }}</template></el-table-column>
              <el-table-column label="创建时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
            </el-table>
            <div class="pager">
              <el-pagination background layout="total, prev, pager, next" :total="goodsTotal"
                :page-size="goodsQuery.limit" v-model:current-page="goodsQuery.page" @current-change="loadGoods" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="货款流水" name="flow">
            <el-table :data="flows" v-loading="loadingFlow" border size="small" max-height="460">
              <el-table-column prop="id" label="ID" width="70" />
              <el-table-column prop="money" label="金额" width="110" />
              <el-table-column prop="trade_type_text" label="类型" width="140" />
              <el-table-column prop="before" label="变更前" width="110" />
              <el-table-column prop="after" label="变更后" width="110" />
              <el-table-column prop="mark" label="备注" min-width="220" show-overflow-tooltip />
              <el-table-column label="时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
            </el-table>
            <div class="pager">
              <el-pagination background layout="total, prev, pager, next" :total="flowTotal"
                :page-size="flowQuery.limit" v-model:current-page="flowQuery.page" @current-change="loadFlow" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="提现" name="withdraw">
            <el-card shadow="never">
              <el-form label-width="100px" style="max-width: 560px">
                <el-form-item label="可提货款">
                  <span class="money">¥{{ withdrawCfg.supply_money || '0.0000' }}</span>
                  <span class="tip" style="margin-left: 10px">冻结中 ¥{{ withdrawCfg.freez_supply_money || '0.0000' }}</span>
                </el-form-item>
                <el-form-item label="提现金额">
                  <el-input v-model="withdrawForm.money" placeholder="请输入提现金额" />
                </el-form-item>
                <el-form-item label="提现方式">
                  <el-select v-model="withdrawForm.way_id" placeholder="选择提现方式" clearable style="width: 100%">
                    <el-option v-for="w in withdrawCfg.ways || []" :key="w.id" :label="`${w.name} ${w.account}`" :value="w.id" />
                  </el-select>
                </el-form-item>
                <el-form-item v-if="!withdrawForm.way_id" label="收款账号">
                  <el-input v-model="withdrawForm.account" placeholder="收款账号" />
                </el-form-item>
                <el-form-item v-if="!withdrawForm.way_id" label="收款人">
                  <el-input v-model="withdrawForm.true_name" placeholder="收款人姓名" />
                </el-form-item>
                <el-form-item label="备注">
                  <el-input v-model="withdrawForm.mark" maxlength="200" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="withdrawing" @click="submitWithdraw">申请提现</el-button>
                  <span class="tip" style="margin-left: 10px">最低提现 {{ withdrawCfg.min_money || 0 }} 元</span>
                </el-form-item>
              </el-form>
            </el-card>

            <el-table :data="withdrawList" v-loading="loadingWithdraw" border size="small" style="margin-top: 12px" max-height="360">
              <el-table-column prop="id" label="ID" width="70" />
              <el-table-column prop="money" label="金额" width="100" />
              <el-table-column prop="fee" label="手续费" width="100" />
              <el-table-column prop="way_name" label="方式" width="120" />
              <el-table-column prop="account" label="收款账号" min-width="140" show-overflow-tooltip />
              <el-table-column prop="status_text" label="状态" width="100" />
              <el-table-column label="申请时间" width="160"><template #default="{ row }">{{ fmt(row.create_time) }}</template></el-table-column>
            </el-table>
            <div class="pager">
              <el-pagination background layout="total, prev, pager, next" :total="withdrawTotal"
                :page-size="withdrawQuery.limit" v-model:current-page="withdrawQuery.page" @current-change="loadWithdraw" />
            </div>
          </el-tab-pane>
        </el-tabs>
      </template>
    </div>

    <el-dialog v-model="orderVisible" title="订单详情" width="620px">
      <el-descriptions v-if="orderDetail.id" :column="2" border size="small">
        <el-descriptions-item label="订单号" :span="2">{{ orderDetail.ordersn }}</el-descriptions-item>
        <el-descriptions-item label="商品" :span="2">{{ orderDetail.goods_name }}</el-descriptions-item>
        <el-descriptions-item label="数量">{{ orderDetail.quantity }}</el-descriptions-item>
        <el-descriptions-item label="金额">{{ orderDetail.total_price }}</el-descriptions-item>
        <el-descriptions-item label="状态">{{ statusText(orderDetail.status) }}</el-descriptions-item>
        <el-descriptions-item label="支付时间">{{ orderDetail.pay_time > 0 ? fmt(orderDetail.pay_time) : '—' }}</el-descriptions-item>
        <el-descriptions-item label="下单时间" :span="2">{{ fmt(orderDetail.create_time) }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import Topbar from '@/components/Topbar.vue';
import {
  merchantApplyInfo, merchantApplySubmit, merchantOverview,
  merchantOrderIndex, merchantOrderRead, merchantGoodsIndex,
  merchantSupplyFlow, merchantWithdrawConfig, merchantWithdrawApply, merchantWithdrawIndex,
} from '@/api';

const loading = ref(true);
const hasMerchant = ref(false);
const info = ref({});
const apply = ref(null);
const apply_status_text = ref('');
const form = ref({ merchant_name: '', contact: '', mobile: '', intro: '', license_img: '' });
const submitting = ref(false);

const tab = ref('overview');
const merchant = ref({});
const wallet = ref({});
const orders = ref({});
const supply = ref({});
const daily = ref([]);

const orderList = ref([]);
const orderTotal = ref(0);
const loadingOrders = ref(false);
const orderQuery = ref({ page: 1, limit: 15, keyword: '', status: '' });
const orderVisible = ref(false);
const orderDetail = ref({});

const goodsList = ref([]);
const goodsTotal = ref(0);
const loadingGoods = ref(false);
const goodsQuery = ref({ page: 1, limit: 15, keyword: '' });

const flows = ref([]);
const flowTotal = ref(0);
const loadingFlow = ref(false);
const flowQuery = ref({ page: 1, limit: 15 });

const withdrawCfg = ref({});
const withdrawForm = ref({ money: '', way_id: '', account: '', true_name: '', mark: '' });
const withdrawing = ref(false);
const withdrawList = ref([]);
const withdrawTotal = ref(0);
const loadingWithdraw = ref(false);
const withdrawQuery = ref({ page: 1, limit: 10 });

const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '等待处理', 2: '正在处理', 3: '已完成', 4: '已取消', 5: '已退款' }[Number(s)] || '—');
const goodsStatusText = (s) => ({ 1: '销售中', 2: '暂停中', 3: '已下架', 6: '已售罄', 7: '已预警', 8: '回收站' }[Number(s)] || '—');
const fmt = (t) => (Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

const loadApplyInfo = async () => {
  const res = await merchantApplyInfo();
  info.value = res.data || {};
  hasMerchant.value = Number(info.value.has_merchant) === 1;
  apply.value = info.value.apply || null;
  apply_status_text.value = info.value.apply_status_text || '';
  if (hasMerchant.value) {
    merchant.value = info.value.merchant || {};
    await loadOverview();
  }
};

const submitApply = async () => {
  submitting.value = true;
  try {
    await merchantApplySubmit(form.value);
    ElMessage.success('提交成功，等待平台审核');
    await loadApplyInfo();
  } finally {
    submitting.value = false;
  }
};

const loadOverview = async () => {
  const res = await merchantOverview();
  const d = res.data || {};
  merchant.value = d.merchant || merchant.value;
  wallet.value = d.wallet || {};
  orders.value = d.orders || {};
  supply.value = d.supply || {};
  daily.value = d.daily || [];
};
const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await merchantOrderIndex(orderQuery.value);
    orderList.value = res.data?.list || [];
    orderTotal.value = res.data?.count || 0;
  } finally { loadingOrders.value = false; }
};
const openOrder = async (row) => {
  const res = await merchantOrderRead(row.id);
  orderDetail.value = res.data || {};
  orderVisible.value = true;
};
const loadGoods = async () => {
  loadingGoods.value = true;
  try {
    const res = await merchantGoodsIndex(goodsQuery.value);
    goodsList.value = res.data?.list || [];
    goodsTotal.value = res.data?.count || 0;
  } finally { loadingGoods.value = false; }
};
const loadFlow = async () => {
  loadingFlow.value = true;
  try {
    const res = await merchantSupplyFlow(flowQuery.value);
    flows.value = res.data?.list || [];
    flowTotal.value = res.data?.count || 0;
  } finally { loadingFlow.value = false; }
};
const loadWithdrawCfg = async () => {
  const res = await merchantWithdrawConfig();
  withdrawCfg.value = res.data || {};
};
const loadWithdraw = async () => {
  loadingWithdraw.value = true;
  try {
    const res = await merchantWithdrawIndex(withdrawQuery.value);
    withdrawList.value = res.data?.list || [];
    withdrawTotal.value = res.data?.count || 0;
  } finally { loadingWithdraw.value = false; }
};
const submitWithdraw = async () => {
  if (!withdrawForm.value.money) {
    ElMessage.error('请输入提现金额');
    return;
  }
  withdrawing.value = true;
  try {
    await merchantWithdrawApply(withdrawForm.value);
    ElMessage.success('提交成功，等待审核');
    withdrawForm.value.money = '';
    withdrawForm.value.mark = '';
    await Promise.all([loadWithdrawCfg(), loadWithdraw(), loadFlow()]);
  } finally { withdrawing.value = false; }
};

const loaded = {};
watch(tab, (t) => {
  if (loaded[t] || !hasMerchant.value) return;
  loaded[t] = true;
  if (t === 'order') loadOrders();
  if (t === 'goods') loadGoods();
  if (t === 'flow') loadFlow();
  if (t === 'withdraw') { loadWithdrawCfg(); loadWithdraw(); }
});

onMounted(async () => {
  try {
    await loadApplyInfo();
  } finally {
    loading.value = false;
  }
  loaded.overview = true;
});
</script>

<style scoped>
.apply-head { margin-top: 14px; padding: 22px; border-radius: 10px; color: #fff;
  background: linear-gradient(120deg, #e6a23c, #f3d19e); }
.apply-title { font-size: 22px; font-weight: 700; }
.apply-tip { font-size: 13px; opacity: .92; margin-top: 8px; }
.merchant-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 14px;
  padding: 20px 22px; border-radius: 10px; color: #fff; background: linear-gradient(120deg, #e6a23c, #f3d19e); flex-wrap: wrap; }
.merchant-title { font-size: 22px; font-weight: 700; }
.merchant-meta { font-size: 13px; opacity: .92; margin-top: 8px; display: flex; gap: 14px; flex-wrap: wrap; }
.wallet { display: flex; gap: 26px; text-align: right; }
.wallet .label { font-size: 12px; opacity: .85; }
.wallet .num { font-size: 20px; font-weight: 700; margin-top: 2px; }
.wallet .num.warn { color: #fff3c4; }
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 6px; }
.stat { background: #fff; border-radius: 8px; padding: 16px; }
.stat .k { color: #909399; font-size: 13px; }
.stat .v { font-size: 22px; font-weight: 700; margin-top: 6px; color: #303133; }
.stat .v.warn { color: #e6a23c; }
.bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
.tip { color: #909399; font-size: 12px; }
.money { font-size: 18px; font-weight: 700; color: #e6a23c; }
.pager { display: flex; justify-content: flex-end; margin-top: 10px; }
@media (max-width: 768px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
