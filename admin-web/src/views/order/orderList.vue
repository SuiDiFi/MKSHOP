<template>
  <mks-page>
    <!-- 引导条（对方每个业务列表都有） -->
    <template #alert>
      默认展示近 30 天创建的订单；金额单位元。快捷入口：
      <a class="quick-link" @click="goCfg">订单配置</a>
    </template>

    <!-- 筛选区：默认 5 项，其余折叠（对方文案「展开/合并」） -->
    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="订单号"><el-input v-model="query.ordersn" clearable placeholder="订单号" style="width: 170px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="下单参数"><el-input v-model="query.recharge_account" clearable placeholder="下单参数" style="width: 150px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="用户ID"><el-input v-model="query.uid" clearable placeholder="用户ID" style="width: 90px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="商品名称"><el-input v-model="query.goods_name" clearable placeholder="商品名称" style="width: 150px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="创建时间">
            <el-date-picker v-model="query.create_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="开始" end-placeholder="结束" style="width: 330px" @change="doSearch" />
          </el-form-item>
        </template>
        <template #more>
          <el-form-item label="支付方式">
            <el-select v-model="query.pay_type" clearable placeholder="支付方式" style="width: 110px" @change="doSearch">
              <el-option label="余额支付" value="balance" />
              <el-option label="在线支付" value="online" />
            </el-select>
          </el-form-item>
          <el-form-item label="下单来源">
            <el-select v-model="query.user_where" clearable placeholder="下单来源" style="width: 110px" @change="doSearch">
              <el-option label="PC" value="pc" />
              <el-option label="H5" value="h5" />
              <el-option label="小程序" value="mp" />
              <el-option label="API" value="api" />
            </el-select>
          </el-form-item>
          <el-form-item label="商品类型">
            <el-select v-model="query.goods_type" clearable placeholder="商品类型" style="width: 110px" @change="doSearch">
              <el-option v-for="s in goodsTypeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
            </el-select>
          </el-form-item>
          <el-form-item label="供货商ID"><el-input v-model="query.supplier_id" clearable placeholder="供货商ID" style="width: 90px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="销售价">
            <el-input v-model="query.total_price_min" placeholder="≥" style="width: 90px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.total_price_max" placeholder="≤" style="width: 90px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="成本价">
            <el-input v-model="query.cost_value_min" placeholder="≥" style="width: 90px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.cost_value_max" placeholder="≤" style="width: 90px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="完成时间">
            <el-date-picker v-model="query.finish_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="开始" end-placeholder="结束" style="width: 330px" @change="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <!-- 双轨筛选（对齐对方：上归属页签带数量 + 下状态胶囊排，自定义列直接放进胶囊排） -->
    <template #status>
      <mks-status-tabs
        v-model="query.provideTab"
        v-model:pill-value="query.statusTab"
        :tabs="tabDefs"
        :pills="pillDefs"
        @update:model-value="doSearch"
        @update:pill-value="doSearch"
      >
        <template #extra>
          <mks-column-setter v-model="visibleCols" :columns="allColumns" store-key="order" />
        </template>
      </mks-status-tabs>
    </template>

    <!-- 批量操作（统一左上） -->
    <template #toolbar>
      <el-button size="small" :disabled="!selection.length" type="danger" plain @click="batchDelete">批量删除({{ selection.length }})</el-button>
      <el-button size="small" :disabled="!selection.length" type="warning" plain @click="batchAction('bu')">批量补单</el-button>
      <el-button size="small" :disabled="!selection.length" type="warning" plain @click="batchAction('sync')">批量同步回调</el-button>
      <el-button size="small" type="primary" plain @click="doExport">导出订单</el-button>
      <el-button size="small" :disabled="!selection.length" plain @click="copyParams">复制参数</el-button>
      <span class="muted" style="margin-left: 12px">已选 {{ selection.length }} 条</span>
    </template>

      <!-- 表格（对方订单表有边框、字号 12px、操作列文字按钮 + 冻结右列） -->
      <el-table :data="list" border size="small" v-loading="loading" @selection-change="(s) => (selection = s)">
        <template #empty><mks-empty text="没有符合条件的订单" hint="试试放宽筛选条件或切换状态页签" /></template>
        <el-table-column type="selection" width="42" fixed="left" />
        <el-table-column label="订单状态" width="110" v-if="colVisible('status')">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ row.status_text }}</el-tag>
            <el-tag v-if="Number(row.is_fail) === 1" type="danger" size="small" style="margin-left: 4px">异常</el-tag>
            <el-tag v-if="row.is_part_back" type="warning" size="small" style="margin-left: 4px">部分退款</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ordersn" label="订单号" width="190" show-overflow-tooltip v-if="colVisible('ordersn')" />
        <el-table-column prop="goods_name" label="商品名称" min-width="170" show-overflow-tooltip v-if="colVisible('goods_name')" />
        <el-table-column label="下单参数" width="130" show-overflow-tooltip v-if="colVisible('recharge_account')">
          <template #default="{ row }">{{ row.recharge_account || '—' }}</template>
        </el-table-column>
        <el-table-column label="用户/发货渠道" width="150" show-overflow-tooltip v-if="colVisible('user')">
          <template #default="{ row }">
            <div>{{ row.nickname }}（#{{ row.uid }}）</div>
            <div class="muted" v-if="row.supplier_name">渠道：{{ row.supplier_name }}</div>
            <div class="muted" v-else-if="Number(row.vip_id) > 0">分站：#{{ row.vip_id }}</div>
          </template>
        </el-table-column>
        <el-table-column label="销售价" width="95" v-if="colVisible('total_price')">
          <template #default="{ row }"><b class="money-out">{{ row.total_price }}</b></template>
        </el-table-column>
        <el-table-column label="成本价" width="90" v-if="colVisible('cost_value')">
          <template #default="{ row }">{{ row.cost_value }}</template>
        </el-table-column>
        <el-table-column label="利润" width="90" v-if="colVisible('order_price')">
          <template #default="{ row }"><b class="money-in">{{ row.order_price }}</b></template>
        </el-table-column>
        <el-table-column label="下单时间" width="160" v-if="colVisible('create_time')">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="处理时间" width="160" v-if="colVisible('finish_time')">
          <template #default="{ row }">{{ row.finish_time > 0 ? formatTime(row.finish_time) : '—' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
            <el-button v-if="row.docking_info" link type="warning" @click="doSync(row)">同步</el-button>
            <el-button v-if="Number(row.provide_type) === 6 && [1, 2].includes(row.status)" link type="warning"
              @click="doBu(row)">补单</el-button>
            <el-button v-if="Number(row.provide_type) === 6 && row.status === 1" link type="info"
              @click="doNoBu(row)">停补</el-button>
            <el-button v-if="row.status === 1" link type="success" @click="doDual(row, 3)">处理成功</el-button>
            <el-button v-if="row.status === 0" link type="warning" @click="doDual(row, -1)">取消</el-button>
            <el-popconfirm v-if="row.status === 3" title="确认退款？余额原路退回，已发卡密将冻结" width="240"
              @confirm="doRefund(row)">
              <template #reference><el-button link type="danger">退款</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <template #pager>
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="total"
          v-model:page-size="query.limit" v-model:current-page="query.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="doSearch" @current-change="load" />
      </template>
    </mks-page>

    <!-- 详情抽屉：1000px + 页签级懒加载（页签按数据动态显隐，无底部按钮） -->
    <mks-detail-drawer v-model="detailVisible" title="订单信息" :tabs="detailTabs" @tab-load="onDetailTabLoad">
      <template #actions>
        <el-button v-if="detailRow?.docking_info" size="small" type="primary" plain @click="doSync(detailRow)">同步订单</el-button>
        <el-button v-if="Number(detailRow?.provide_type) === 6 && Number(detailRow?.status) === 1" size="small" type="info"
          plain @click="doNoBu(detailRow)">停止补单</el-button>
        <el-button v-if="Number(detailRow?.provide_type) === 6 && [1, 2].includes(Number(detailRow?.status))" size="small"
          type="warning" plain @click="doBu(detailRow)">补单</el-button>
        <el-button v-if="Number(detailRow?.status) === 1" size="small" type="success" plain @click="doDual(detailRow, 3)">处理成功</el-button>
        <el-popconfirm v-if="Number(detailRow?.status) === 3" title="确认退款？余额原路退回，已发卡密将冻结" width="240"
          @confirm="doRefund(detailRow)">
          <template #reference><el-button size="small" type="danger" plain>退款</el-button></template>
        </el-popconfirm>
        <el-button v-if="detailCards.length" size="small" plain @click="copyCards">复制卡密</el-button>
      </template>

      <template #top>
        <div class="mks-dt-head">
          <div class="mks-dt-main">
            <el-image v-if="detailRow?.goods_img" :src="detailRow.goods_img" fit="cover" class="mks-dt-img" />
            <div>
              <div class="mks-dt-goods">{{ detailRow?.goods_name }} × {{ detailRow?.quantity }}</div>
              <el-link underline="never" type="primary">{{ detailRow?.ordersn }}</el-link>
              <el-link underline="never" class="mks-dt-copy" @click="copyText(detailRow?.ordersn)">
                <mks-icon name="file-copy-line" :size="14" />
              </el-link>
            </div>
          </div>
          <ul class="mks-dt-meta">
            <li>订单状态<b :class="'st-' + detailRow?.status">{{ detailRow?.status_text }}</b></li>
            <li>订单总价<b>{{ detailRow?.total_price }}</b></li>
            <li>成本/利润<b>{{ detailRow?.cost_value }} / {{ detailRow?.order_price }}</b></li>
            <li>下单来源<b>{{ detailRow?.user_where || '—' }}</b></li>
          </ul>
        </div>
      </template>

      <template #info>
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="订单号">{{ detailOrder.ordersn }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(detailOrder.status)">{{ detailOrder.status_text }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="商品">{{ detailOrder.goods_name }} × {{ detailOrder.quantity }}</el-descriptions-item>
          <el-descriptions-item label="充值账号">{{ detailOrder.recharge_account || '—' }}</el-descriptions-item>
          <el-descriptions-item label="金额">{{ detailOrder.total_price }}（单价 {{ detailOrder.price }}）</el-descriptions-item>
          <el-descriptions-item label="成本/利润">{{ detailOrder.cost_value }} / {{ detailOrder.order_price }}</el-descriptions-item>
          <el-descriptions-item label="支付">{{ detailOrder.pay_type || '—' }} @ {{ formatTime(detailOrder.pay_time) }}</el-descriptions-item>
          <el-descriptions-item label="下单 IP">{{ detailOrder.ip || '—' }}（{{ detailOrder.user_where }}）</el-descriptions-item>
          <el-descriptions-item label="用户">{{ detailOrder.nickname }}（#{{ detailOrder.uid }}）</el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ formatTime(detailOrder.create_time) }}</el-descriptions-item>
          <el-descriptions-item label="用户备注" :span="2">{{ detailOrder.mark || '—' }}</el-descriptions-item>
        </el-descriptions>
      </template>

      <template #goods>
        <el-table :data="detailCards" border size="small">
          <template #empty><mks-empty text="无卡密" hint="非卡密商品或未发货" /></template>
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="card_no" label="卡号" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">{{ row.card_no || '—' }}</template>
          </el-table-column>
          <el-table-column prop="card_password" label="卡密" min-width="160" show-overflow-tooltip />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="['', 'success', 'info', 'danger'][row.status]" size="small">
                {{ ['', '待售', '已售', '冻结'][row.status] || row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" width="170">
            <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
          </el-table-column>
        </el-table>
      </template>

      <template #log>
        <el-timeline style="padding-left: 4px">
          <el-timeline-item v-for="lg in detailLogs" :key="lg.id" :timestamp="formatTime(lg.create_time)">
            {{ lg.mark }}
          </el-timeline-item>
        </el-timeline>
        <mks-empty v-if="!detailLogs.length" text="暂无操作日志" />
      </template>

      <template #commission>
        <el-table :data="tabData.commission.list" border size="small" v-loading="tabData.commission.loading">
          <template #empty><mks-empty text="暂无佣金记录" /></template>
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="layer" label="层级" width="70" />
          <el-table-column label="受益人" min-width="140">
            <template #default="{ row }">{{ row.uid1_name }}（#{{ row.uid1 }}）</template>
          </el-table-column>
          <el-table-column prop="price1" label="计算基数" width="110" />
          <el-table-column prop="commision_price" label="佣金" width="100" />
          <el-table-column prop="sure_status_text" label="结算状态" width="100" />
          <el-table-column label="时间" width="170">
            <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
          </el-table-column>
        </el-table>
      </template>

      <template #money>
        <el-table :data="tabData.money.list" border size="small" v-loading="tabData.money.loading">
          <template #empty><mks-empty text="暂无资金流水" /></template>
          <el-table-column prop="uid" label="用户ID" width="90" />
          <el-table-column prop="money" label="操作金额" width="110" />
          <el-table-column prop="trade_type_text" label="交易类型" min-width="120" />
          <el-table-column prop="before" label="变化前金额" width="120" />
          <el-table-column prop="after" label="变化后金额" width="120" />
          <el-table-column label="收支类型" width="90">
            <template #default="{ row }">
              <el-tag :type="row.change_type_text === '收入' ? 'success' : 'danger'" size="small">{{ row.change_type_text }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" width="170">
            <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
          </el-table-column>
          <el-table-column prop="mark" label="备注" min-width="120" show-overflow-tooltip />
        </el-table>
      </template>
    </mks-detail-drawer>
  </template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  orderIndex, orderInfo, orderGoodsCards, orderDual, orderDelete, orderStatusCounts, orderSync, exportCsv,
  commissionIndex, memberMoneyIndex,
} from '@/api';
import { orderBu, orderNoBu } from '@/api/m3';
import { goodsGoodsType } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksColumnSetter from '@/components/mksColumnSetter.vue';
import mksEmpty from '@/components/mksEmpty.vue';
import mksIcon from '@/components/mksIcon.vue';
import mksDetailDrawer from '@/components/mksDetailDrawer.vue';

const router = useRouter();
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const selection = ref([]);
const goodsTypeOptions = ref([]);
const detailVisible = ref(false);
const detail = ref({ order: null, logs: [], cards: [] });
const detailOrder = computed(() => detail.value.order || {});
const detailLogs = computed(() => detail.value.logs || []);
const detailCards = computed(() => detail.value.cards || []);
/** 页签数量角标（order/status_counts，默认近 30 天口径） */
const counts = ref({ by_status: {}, by_provide: {}, total: 0, part_refund: 0, fail: 0 });

/** 上层页签：订单归属维度（取我方 provide_type 字典：1 自营 / 5 供货 / 6 渠道） */
const tabDefs = computed(() => [
  { label: '所有订单', value: '', count: counts.value.total },
  { label: '平台订单', value: 1, count: counts.value.by_provide?.['1'] ?? 0 },
  { label: '供货订单', value: 5, count: counts.value.by_provide?.['5'] ?? 0 },
  { label: '渠道订单', value: 6, count: counts.value.by_provide?.['6'] ?? 0 },
]);

/** 下层胶囊：状态维度（对齐 KASUSHOU 订单状态页签） */
const pillDefs = computed(() => {
  const s = counts.value.by_status || {};
  return [
    { label: '全部订单', value: '' },
    { label: '等待支付', value: 0, count: s['0'] ?? 0 },
    { label: '等待处理', value: 1, count: s['1'] ?? 0 },
    { label: '正在处理', value: 2, count: s['2'] ?? 0 },
    { label: '交易成功', value: 3, count: s['3'] ?? 0 },
    { label: '取消交易', value: 4, count: s['4'] ?? 0 },
    { label: '已经退款', value: 5, count: s['5'] ?? 0 },
    { label: '取消支付', value: -1, count: s['-1'] ?? 0 },
    { label: '部分退款', value: 'part', count: counts.value.part_refund ?? 0 },
    { label: '异常订单', value: 'fail', count: counts.value.fail ?? 0 },
  ];
});

/** 列定义（mksColumnSetter 用 prop 作键，可见列存于 visibleCols） */
const allColumns = [
  { prop: 'status', label: '订单状态' },
  { prop: 'ordersn', label: '订单号' },
  { prop: 'goods_name', label: '商品名称' },
  { prop: 'recharge_account', label: '下单参数' },
  { prop: 'user', label: '用户/发货渠道' },
  { prop: 'total_price', label: '销售价' },
  { prop: 'cost_value', label: '成本价' },
  { prop: 'order_price', label: '利润' },
  { prop: 'create_time', label: '下单时间' },
  { prop: 'finish_time', label: '处理时间' },
];
const visibleCols = ref(allColumns.map((c) => c.prop));
const colVisible = (prop) => visibleCols.value.includes(prop);

function goCfg() {
  // 必须指向真实页面：/setting/base 是无组件的容器节点，直接跳会白屏
  router.push('/setting/base/general');
}

const query = reactive({
  page: 1, limit: 20, statusTab: '', provideTab: '', ordersn: '', recharge_account: '', uid: '', goods_name: '',
  pay_type: null, user_where: null, goods_type: null, supplier_id: '', total_price_min: '', total_price_max: '',
  cost_value_min: '', cost_value_max: '', create_range: null, finish_range: null,
});

const statusTagType = (s) => ({ [-1]: 'info', 0: 'warning', 1: 'warning', 2: 'primary', 3: 'success', 4: 'info', 5: 'danger' }[s] || 'info');
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

function buildWhere() {
  const where = [];
  const t = query.statusTab;
  if (t === 'part') {
    where.push({ field: 'has_back_money', op: 'gt', value: 0 }, { field: 'status', op: 'eq', value: 3 });
  } else if (t === 'fail') {
    where.push({ field: 'is_fail', op: 'eq', value: 1 });
  } else if (t !== '' && t !== null) {
    where.push({ field: 'status', op: 'eq', value: Number(t) });
  }
  // 归属页签（上层）与状态（下层）可叠加
  if (query.provideTab !== '' && query.provideTab !== null) {
    where.push({ field: 'provide_type', op: 'eq', value: Number(query.provideTab) });
  }
  if (query.ordersn) where.push({ field: 'ordersn', op: 'like', value: query.ordersn });
  if (query.recharge_account) where.push({ field: 'recharge_account', op: 'like', value: query.recharge_account });
  if (query.uid) where.push({ field: 'uid', op: 'eq', value: Number(query.uid) });
  if (query.goods_name) where.push({ field: 'goods_name', op: 'like', value: query.goods_name });
  if (query.pay_type) where.push({ field: 'pay_type', op: 'eq', value: query.pay_type });
  if (query.user_where) where.push({ field: 'user_where', op: 'eq', value: query.user_where });
  if (query.goods_type !== null && query.goods_type !== '') where.push({ field: 'goods_type', op: 'eq', value: Number(query.goods_type) });
  if (query.supplier_id) where.push({ field: 'supplier_id', op: 'eq', value: Number(query.supplier_id) });
  if (query.total_price_min !== '' || query.total_price_max !== '') {
    where.push({ field: 'total_price', op: 'between', value: [query.total_price_min || '0', query.total_price_max || '99999999'] });
  }
  if (query.cost_value_min !== '' || query.cost_value_max !== '') {
    where.push({ field: 'cost_value', op: 'between', value: [query.cost_value_min || '0', query.cost_value_max || '99999999'] });
  }
  if (Array.isArray(query.create_range) && query.create_range.length === 2) {
    where.push({ field: 'create_time', op: 'between', value: [ts(query.create_range[0]), ts(query.create_range[1])] });
  }
  if (Array.isArray(query.finish_range) && query.finish_range.length === 2) {
    where.push({ field: 'finish_time', op: 'between', value: [ts(query.finish_range[0]), ts(query.finish_range[1])] });
  }
  return JSON.stringify(where);
}

const ts = (v) => Math.floor(new Date(v).getTime() / 1000);

async function load() {
  loading.value = true;
  try {
    const res = await orderIndex({ page: query.page, limit: query.limit, where: buildWhere() });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
  } finally {
    loading.value = false;
  }
}

function doSearch() {
  query.page = 1;
  load();
}

function resetQuery() {
  Object.assign(query, {
    page: 1, statusTab: '', provideTab: '', ordersn: '', recharge_account: '', uid: '', goods_name: '',
    pay_type: null, user_where: null, goods_type: null, supplier_id: '', total_price_min: '', total_price_max: '',
    cost_value_min: '', cost_value_max: '', create_range: null, finish_range: null,
  });
  load();
}

/** 刷新页签角标（与列表并行，不阻塞渲染） */
async function loadCounts() {
  try {
    const res = await orderStatusCounts({ days: 30 });
    counts.value = res.data || counts.value;
  } catch (e) {
    /* 角标失败不影响列表 */
  }
}

async function doExport() {
  await exportCsv('/admin/order/export', { where: buildWhere() }, `orders_${Date.now()}.csv`);
  ElMessage.success('导出完成（最多 5000 条）');
}

async function openDetail(row) {
  const [info, cards] = await Promise.all([orderInfo(row.id), orderGoodsCards(row.id)]);
  const order = { ...row, ...(info.data.order || {}) };
  detailRow.value = row;
  tabData.commission.list = [];
  tabData.money.list = [];
  detail.value = { order, logs: info.data.logs || [], cards: cards.data || [] };
  detailVisible.value = true;
}

/** 详情抽屉：当前行（页头操作按钮要用列表行的原始字段） */
const detailRow = ref(null);

/** 页签按数据显隐（对方 tabList 同思路：无卡密不给「商品信息」，非余额支付不给「资金明细」） */
const detailTabs = computed(() => {
  const tabs = [
    { label: '订单详情', name: 'info' },
    { label: '订单日志', name: 'log' },
  ];
  if (detail.value.cards.length > 0) tabs.splice(1, 0, { label: '商品信息', name: 'goods' });
  tabs.push({ label: '订单佣金', name: 'commission' });
  if (detail.value.order?.uid > 0) tabs.push({ label: '资金明细', name: 'money' });
  return tabs;
});

/** 页签级懒加载：首次激活才发请求 */
const tabData = reactive({
  commission: { list: [], loading: false, loaded: false },
  money: { list: [], loading: false, loaded: false },
});

async function onDetailTabLoad(name) {
  const order = detail.value.order || {};
  if (name === 'commission' && !tabData.commission.loaded) {
    tabData.commission.loading = true;
    try {
      const res = await commissionIndex({ order_id: order.id, limit: 100 });
      tabData.commission.list = res.data.list || [];
      tabData.commission.loaded = true;
    } finally {
      tabData.commission.loading = false;
    }
  }
  if (name === 'money' && !tabData.money.loaded) {
    tabData.money.loading = true;
    try {
      const res = await memberMoneyIndex({ order_id: order.id, limit: 100 });
      tabData.money.list = res.data.list || [];
      tabData.money.loaded = true;
    } finally {
      tabData.money.loading = false;
    }
  }
}

async function copyText(text) {
  if (!text) return;
  await navigator.clipboard.writeText(String(text));
  ElMessage.success('复制成功');
}

function copyCards() {
  copyText(detail.value.cards.map((c) => c.card_password).filter(Boolean).join('\n'));
}

async function doDual(row, status) {
  const res = await orderDual(row.id, { status });
  ElMessage.success(res.msg || '处理成功');
  load();
}

async function doSync(row) {
  const res = await orderSync(row.id);
  ElMessage.success(res.msg || '同步完成');
  load();
}

async function doBu(row) {
  const res = await orderBu(row.id);
  ElMessage.success(res.msg || '补单完成');
  load();
}

async function doNoBu(row) {
  const res = await orderNoBu(row.id);
  ElMessage.success(res.msg || '已停止补单');
  load();
}

async function doRefund(row) {
  const { value } = await ElMessageBox.prompt(
    `退款金额（实付 ${row.total_price}）`, '订单退款',
    { inputValue: String(row.total_price), inputPattern: /^\d+(\.\d{1,4})?$/, inputErrorMessage: '金额格式不正确', type: 'warning' }
  );
  const res = await orderDual(row.id, { status: 5, money: value, remark: '后台退款' });
  ElMessage.success(res.msg || '退款成功');
  load();
}

async function batchDelete() {
  await ElMessageBox.confirm(`确认删除选中的 ${selection.value.length} 条订单？`, '批量删除', { type: 'warning' });
  await runBatch((row) => orderDelete(row.id));
}

async function batchAction(kind) {
  const label = kind === 'bu' ? '批量补单' : '批量同步回调';
  await ElMessageBox.confirm(`确认对选中的 ${selection.value.length} 条订单执行${label}？`, label, { type: 'warning' });
  await runBatch((row) => (kind === 'bu' ? orderBu(row.id) : orderSync(row.id)));
}

// 批量执行：逐条调用现有单条接口，汇总成功/失败
async function runBatch(fn) {
  const results = await Promise.allSettled(selection.value.map((row) => fn(row)));
  const ok = results.filter((r) => r.status === 'fulfilled').length;
  const fail = results.length - ok;
  ElMessage[fail > 0 ? 'warning' : 'success'](`完成：成功 ${ok} 条${fail ? `，失败 ${fail} 条` : ''}`);
  load();
}

async function copyParams() {
  const text = selection.value.map((r) => `${r.ordersn}\t${r.recharge_account || ''}`).join('\n');
  await navigator.clipboard.writeText(text);
  ElMessage.success(`已复制 ${selection.value.length} 条（订单号+下单参数）`);
}

onMounted(async () => {
  const gt = await goodsGoodsType();
  goodsTypeOptions.value = gt.data || [];
  load();
  loadCounts();
});
</script>

<style scoped>
.muted { color: var(--mks-text-placeholder); font-size: var(--mks-font-size-table); }
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
/* 表格金额列用变量，不再硬编码色值 */
.money-out { color: var(--mks-danger); }
.money-in { color: var(--mks-success); }
</style>
