<template>
  <mks-page>
    <template #alert>
      商户须绑定会员账号才能开店，入驻审核通过后才生成店铺，货款按结算状态解冻后才可用；快捷入口：
      <a class="quick-link" @click="goCfg('/member/list')">会员列表</a>·
      <a class="quick-link" @click="goCfg('/finance/membermoney')">客户资金明细</a>
    </template>

    <template #query>
      <!-- 三个页签是三个独立列表，筛选区随当前页签切换 -->
      <mks-query-form v-if="tab === 'merchant'" :model="mQuery" :loading="loadingMerchant" @search="doMerchantSearch" @reset="resetMerchantQuery">
        <template #short>
          <el-form-item label="关键字">
            <el-input v-model="mQuery.keyword" placeholder="店铺名/联系人/电话" clearable style="width: 220px" @keyup.enter="doMerchantSearch" />
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="mQuery.status" placeholder="状态" clearable style="width: 130px" @change="doMerchantSearch">
              <el-option label="正常" :value="1" />
              <el-option label="停用" :value="0" />
            </el-select>
          </el-form-item>
        </template>
      </mks-query-form>
      <mks-query-form v-else-if="tab === 'apply'" :model="aQuery" :loading="loadingApply" @search="doApplySearch" @reset="resetApplyQuery">
        <template #short>
          <el-form-item label="审核状态">
            <el-select v-model="aQuery.status" placeholder="审核状态" clearable style="width: 140px" @change="doApplySearch">
              <el-option label="待审核" :value="1" />
              <el-option label="已通过" :value="2" />
              <el-option label="已拒绝" :value="3" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键字">
            <el-input v-model="aQuery.keyword" placeholder="店铺名/联系人/电话" clearable style="width: 220px" @keyup.enter="doApplySearch" />
          </el-form-item>
        </template>
      </mks-query-form>
      <mks-query-form v-else :model="sQuery" :loading="loadingSettle" @search="doSettleSearch" @reset="resetSettleQuery">
        <template #short>
          <el-form-item label="订单号">
            <el-input v-model="sQuery.keyword" placeholder="订单号" clearable style="width: 200px" @keyup.enter="doSettleSearch" />
          </el-form-item>
          <el-form-item label="结算状态">
            <el-select v-model="sQuery.sure_status" placeholder="结算状态" clearable style="width: 140px" @change="doSettleSearch">
              <el-option label="待结算" :value="1" />
              <el-option label="已结算" :value="2" />
              <el-option label="已退款" :value="3" />
              <el-option label="部分退款" :value="4" />
            </el-select>
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #status>
      <mks-status-tabs v-model="tab" :tabs="paneTabs" @update:model-value="onTabChange" />
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openCreate">新增商户</el-button>
    </template>

    <!-- ── 商户列表 ── -->
    <template v-if="tab === 'merchant'">
          <el-table :data="merchants" v-loading="loadingMerchant" border size="small">
            <template #empty><mks-empty text="还没有商户" hint="新增商户或审核通过入驻申请后，这里才会出现店铺" /></template>
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="name" label="店铺名" min-width="150" show-overflow-tooltip />
            <el-table-column prop="username" label="会员账号" width="130" />
            <el-table-column prop="contact" label="联系人" width="110" />
            <el-table-column prop="mobile" label="电话" width="130" />
            <el-table-column prop="goods_count" label="商品" width="80" />
            <el-table-column prop="order_count" label="订单" width="80" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-switch :model-value="row.status === 1" @change="(v) => toggleStatus(row, v)" />
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="165">
              <template #default="{ row }">{{ fmt(row.create_time) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openDetail(row)">详情</el-button>
                <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
                <el-button link type="primary" @click="openFlow(row)">货款流水</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="mTotal"
              v-model:page-size="mQuery.limit" v-model:current-page="mQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
              @size-change="doMerchantSearch" @current-change="loadMerchants" />
          </div>
    </template>

    <!-- ── 入驻审核 ── -->
    <template v-else-if="tab === 'apply'">
          <el-table :data="applies" v-loading="loadingApply" border size="small">
            <template #empty><mks-empty text="还没有入驻申请" hint="会员在前台发起商户入驻申请后进入这里审核" /></template>
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="merchant_name" label="店铺名" min-width="150" show-overflow-tooltip />
            <el-table-column prop="username" label="会员账号" width="130" />
            <el-table-column prop="contact" label="联系人" width="110" />
            <el-table-column prop="mobile" label="电话" width="130" />
            <el-table-column prop="status_text" label="状态" width="100" />
            <el-table-column label="提交时间" width="165">
              <template #default="{ row }">{{ fmt(row.create_time) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openApplyAudit(row)">查看/审核</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="aTotal"
              v-model:page-size="aQuery.limit" v-model:current-page="aQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
              @size-change="doApplySearch" @current-change="loadApplies" />
          </div>
    </template>

    <!-- ── 货款结算 ── -->
    <template v-else>
          <el-table :data="settlements" v-loading="loadingSettle" border size="small">
            <template #empty><mks-empty text="还没有货款结算单" hint="商户订单交易完成后才生成结算记录" /></template>
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column prop="ordersn" label="订单号" min-width="180" />
            <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
            <el-table-column prop="supply_name" label="供货商" width="120" />
            <el-table-column prop="supply_price" label="货款" width="110" />
            <el-table-column prop="back_supply_price" label="回冲" width="110" />
            <el-table-column prop="sure_status_text" label="结算状态" width="110" />
            <el-table-column prop="frozen_time_text" label="解冻时间" width="165" />
            <el-table-column label="创建时间" width="165">
              <template #default="{ row }">{{ fmt(row.create_time) }}</template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="sTotal"
              v-model:page-size="sQuery.limit" v-model:current-page="sQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
              @size-change="doSettleSearch" @current-change="loadSettlements" />
          </div>
    </template>
    </mks-page>

    <!-- 新增商户 -->
    <el-dialog v-model="createVisible" title="新增商户" width="560px">
      <el-form :model="createForm" label-width="90px">
        <el-form-item label="绑定会员" required>
          <el-select v-model="createForm.uid" filterable remote :remote-method="searchMembers"
            placeholder="输入账号/昵称/手机号搜索" style="width: 100%">
            <el-option v-for="m in memberOptions" :key="m.id" :label="`${m.username}（${m.nickname || '无昵称'}）`" :value="m.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="店铺名" required>
          <el-input v-model="createForm.name" maxlength="100" />
        </el-form-item>
        <el-form-item label="联系人"><el-input v-model="createForm.contact" /></el-form-item>
        <el-form-item label="电话"><el-input v-model="createForm.mobile" /></el-form-item>
        <el-form-item label="简介"><el-input v-model="createForm.intro" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="submitCreate">确定</el-button>
      </template>
    </el-dialog>

    <!-- 编辑商户 -->
    <el-dialog v-model="editVisible" title="编辑商户" width="560px">
      <el-form :model="editForm" label-width="90px">
        <el-form-item label="店铺名"><el-input v-model="editForm.name" maxlength="100" /></el-form-item>
        <el-form-item label="Logo"><el-input v-model="editForm.logo" /></el-form-item>
        <el-form-item label="联系人"><el-input v-model="editForm.contact" /></el-form-item>
        <el-form-item label="电话"><el-input v-model="editForm.mobile" /></el-form-item>
        <el-form-item label="简介"><el-input v-model="editForm.intro" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 商户详情 -->
    <el-drawer v-model="detailVisible" title="商户详情" size="520px">
      <template v-if="detail.merchant">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="商户ID">{{ detail.merchant.id }}</el-descriptions-item>
          <el-descriptions-item label="店铺名">{{ detail.merchant.name }}</el-descriptions-item>
          <el-descriptions-item label="会员">{{ detail.member?.username }} / {{ detail.member?.nickname }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ detail.merchant.contact || '—' }}</el-descriptions-item>
          <el-descriptions-item label="电话">{{ detail.merchant.mobile || '—' }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ detail.merchant.status === 1 ? '正常' : '停用' }}</el-descriptions-item>
        </el-descriptions>
        <div class="section">货款钱包</div>
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="可用货款">{{ detail.wallet?.supply_money }}</el-descriptions-item>
          <el-descriptions-item label="冻结货款">{{ detail.wallet?.freez_supply_money }}</el-descriptions-item>
        </el-descriptions>
        <div class="section">结算汇总</div>
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="待结算">{{ detail.settlement?.pending }}</el-descriptions-item>
          <el-descriptions-item label="已结算">{{ detail.settlement?.settled }}</el-descriptions-item>
          <el-descriptions-item label="累计回冲">{{ detail.settlement?.refunded }}</el-descriptions-item>
        </el-descriptions>
      </template>
    </el-drawer>

    <!-- 货款流水 -->
    <el-drawer v-model="flowVisible" :title="`货款流水 - ${flowMerchant.name || ''}`" size="640px">
      <el-table :data="flowList" v-loading="loadingFlow" border size="small" max-height="560">
        <el-table-column prop="money" label="金额" width="100" />
        <el-table-column prop="trade_type_text" label="类型" width="140" />
        <el-table-column prop="before" label="变更前" width="100" />
        <el-table-column prop="after" label="变更后" width="100" />
        <el-table-column prop="mark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="时间" width="160">
          <template #default="{ row }">{{ fmt(row.create_time) }}</template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="flowTotal"
          :page-size="flowQuery.limit" v-model:current-page="flowQuery.page" @current-change="loadFlow" />
      </div>
    </el-drawer>

    <!-- 入驻审核 -->
    <el-dialog v-model="applyVisible" title="入驻申请审核" width="620px">
      <template v-if="applyDetail.apply">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="店铺名">{{ applyDetail.apply.merchant_name }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ applyDetail.apply.status === 1 ? '待审核' : (applyDetail.apply.status === 2 ? '已通过' : '已拒绝') }}</el-descriptions-item>
          <el-descriptions-item label="会员">{{ applyDetail.member?.username }} / {{ applyDetail.member?.nickname }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ applyDetail.apply.mobile }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ applyDetail.apply.contact }}</el-descriptions-item>
          <el-descriptions-item label="提交时间">{{ fmt(applyDetail.apply.create_time) }}</el-descriptions-item>
          <el-descriptions-item label="简介" :span="2">{{ applyDetail.apply.intro || '—' }}</el-descriptions-item>
          <el-descriptions-item label="资质" :span="2">{{ applyDetail.apply.license_img || '—' }}</el-descriptions-item>
          <el-descriptions-item v-if="applyDetail.apply.status !== 1" label="审核备注" :span="2">{{ applyDetail.apply.audit_mark || '—' }}</el-descriptions-item>
        </el-descriptions>
        <el-form v-if="applyDetail.apply.status === 1" label-width="80px" style="margin-top: 12px">
          <el-form-item label="审核备注"><el-input v-model="auditMark" type="textarea" :rows="2" maxlength="500" /></el-form-item>
        </el-form>
      </template>
      <template #footer>
        <el-button @click="applyVisible = false">关闭</el-button>
        <template v-if="applyDetail.apply && applyDetail.apply.status === 1">
          <el-button type="danger" :loading="auditing" @click="audit(3)">拒绝</el-button>
          <el-button type="primary" :loading="auditing" @click="audit(2)">通过并开通</el-button>
        </template>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  merchantIndex, merchantRead, merchantCreate, merchantSave, merchantStatus,
  merchantMemberOptions, merchantSupplyFlow, merchantSettlementIndex,
  merchantApplyIndex, merchantApplyRead, merchantApplyAudit,
} from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const tab = ref('merchant');
// 页签切换的是三个独立数据列表，不是同一列表的状态筛选
const paneTabs = [
  { label: '商户列表', value: 'merchant' },
  { label: '入驻审核', value: 'apply' },
  { label: '货款结算', value: 'settlement' },
];
const fmt = (t) => (Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

// 商户列表
const merchants = ref([]);
const mTotal = ref(0);
const loadingMerchant = ref(false);
const mQuery = ref({ page: 1, limit: 20, keyword: '', status: '' });
const loadMerchants = async () => {
  loadingMerchant.value = true;
  try {
    const res = await merchantIndex(mQuery.value);
    merchants.value = res.data?.list || [];
    mTotal.value = res.data?.count || 0;
  } finally { loadingMerchant.value = false; }
};
const doMerchantSearch = () => {
  mQuery.value.page = 1;
  loadMerchants();
};
const resetMerchantQuery = () => {
  mQuery.value.keyword = '';
  mQuery.value.status = '';
  doMerchantSearch();
};
const toggleStatus = async (row, val) => {
  await ElMessageBox.confirm(`确定${val ? '启用' : '停用'}商户「${row.name}」？`, '提示', { type: 'warning' });
  await merchantStatus({ id: row.id, status: val ? 1 : 0 });
  ElMessage.success('操作成功');
  loadMerchants();
};

// 新增
const createVisible = ref(false);
const creating = ref(false);
const memberOptions = ref([]);
const createForm = ref({ uid: '', name: '', contact: '', mobile: '', intro: '' });
const openCreate = () => {
  createForm.value = { uid: '', name: '', contact: '', mobile: '', intro: '' };
  memberOptions.value = [];
  createVisible.value = true;
};
const searchMembers = async (kw) => {
  const res = await merchantMemberOptions({ keyword: kw || '' });
  memberOptions.value = res.data || [];
};
const submitCreate = async () => {
  if (!createForm.value.uid || !createForm.value.name) {
    ElMessage.error('请选择会员并填写店铺名');
    return;
  }
  creating.value = true;
  try {
    await merchantCreate(createForm.value);
    ElMessage.success('创建成功');
    createVisible.value = false;
    loadMerchants();
  } finally { creating.value = false; }
};

// 编辑
const editVisible = ref(false);
const saving = ref(false);
const editForm = ref({ id: 0, name: '', logo: '', contact: '', mobile: '', intro: '' });
const openEdit = (row) => {
  editForm.value = {
    id: row.id, name: row.name, logo: row.logo, contact: row.contact, mobile: row.mobile, intro: row.intro,
  };
  editVisible.value = true;
};
const submitEdit = async () => {
  saving.value = true;
  try {
    const { id, ...data } = editForm.value;
    await merchantSave(id, data);
    ElMessage.success('保存成功');
    editVisible.value = false;
    loadMerchants();
  } finally { saving.value = false; }
};

// 详情
const detailVisible = ref(false);
const detail = ref({});
const openDetail = async (row) => {
  const res = await merchantRead(row.id);
  detail.value = res.data || {};
  detailVisible.value = true;
};

// 流水
const flowVisible = ref(false);
const flowMerchant = ref({});
const flowList = ref([]);
const flowTotal = ref(0);
const loadingFlow = ref(false);
const flowQuery = ref({ page: 1, limit: 15, uid: 0 });
const openFlow = (row) => {
  flowMerchant.value = row;
  flowQuery.value = { page: 1, limit: 15, uid: row.uid };
  flowVisible.value = true;
  loadFlow();
};
const loadFlow = async () => {
  loadingFlow.value = true;
  try {
    const res = await merchantSupplyFlow(flowQuery.value);
    flowList.value = res.data?.list || [];
    flowTotal.value = res.data?.count || 0;
  } finally { loadingFlow.value = false; }
};

// 入驻审核
const applies = ref([]);
const aTotal = ref(0);
const loadingApply = ref(false);
const aQuery = ref({ page: 1, limit: 20, keyword: '', status: 1 });
const loadApplies = async () => {
  loadingApply.value = true;
  try {
    const res = await merchantApplyIndex(aQuery.value);
    applies.value = res.data?.list || [];
    aTotal.value = res.data?.count || 0;
  } finally { loadingApply.value = false; }
};
const doApplySearch = () => {
  aQuery.value.page = 1;
  loadApplies();
};
const resetApplyQuery = () => {
  // 默认只看待审核，重置回到该页签初始值
  aQuery.value.keyword = '';
  aQuery.value.status = 1;
  doApplySearch();
};
const applyVisible = ref(false);
const applyDetail = ref({});
const auditMark = ref('');
const auditing = ref(false);
const openApplyAudit = async (row) => {
  const res = await merchantApplyRead(row.id);
  applyDetail.value = res.data || {};
  auditMark.value = '';
  applyVisible.value = true;
};
const audit = async (status) => {
  auditing.value = true;
  try {
    await merchantApplyAudit(applyDetail.value.apply.id, { status, mark: auditMark.value });
    ElMessage.success(status === 2 ? '已通过并开通商户' : '已拒绝');
    applyVisible.value = false;
    loadApplies();
    loadMerchants();
  } finally { auditing.value = false; }
};

// 结算
const settlements = ref([]);
const sTotal = ref(0);
const loadingSettle = ref(false);
const sQuery = ref({ page: 1, limit: 20, keyword: '', sure_status: '' });
const loadSettlements = async () => {
  loadingSettle.value = true;
  try {
    const res = await merchantSettlementIndex(sQuery.value);
    settlements.value = res.data?.list || [];
    sTotal.value = res.data?.count || 0;
  } finally { loadingSettle.value = false; }
};
const doSettleSearch = () => {
  sQuery.value.page = 1;
  loadSettlements();
};
const resetSettleQuery = () => {
  sQuery.value.keyword = '';
  sQuery.value.sure_status = '';
  doSettleSearch();
};

const onTabChange = (name) => {
  if (name === 'apply') loadApplies();
  if (name === 'settlement') loadSettlements();
};

onMounted(loadMerchants);
</script>

<style scoped>
.section { font-weight: 600; margin: 16px 0 8px; color: #303133; }
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
