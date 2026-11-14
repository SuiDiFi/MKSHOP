<template>
  <mks-page>
    <template #alert>
      会员的等级折扣与上下级关系直接决定取价与佣金，改完记得回订单验证；快捷入口：
      <a class="quick-link" @click="goCfg('/member/level')">会员等级</a>·
      <a class="quick-link" @click="goCfg('/finance/membercommission')">分销佣金</a>·
      <a class="quick-link" @click="goCfg('/member/userpricetpl')">密价模板</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="关键字">
            <el-input v-model="query.keyword" placeholder="账号/昵称/手机号" clearable style="width: 200px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="等级">
            <el-select v-model="query.level_id" placeholder="全部等级" clearable style="width: 140px" @change="doSearch">
              <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="上级ID">
            <el-input v-model="query.pid" placeholder="上级ID" clearable style="width: 110px" @keyup.enter="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #status>
      <mks-status-tabs v-model="query.status" :tabs="statusTabs" @update:model-value="doSearch" />
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openCreate">新增会员</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="没有符合条件的会员" hint="试试放宽关键字、等级或上级ID" /></template>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="username" label="账号" min-width="120" show-overflow-tooltip />
        <el-table-column prop="nickname" label="昵称" min-width="110" show-overflow-tooltip />
        <el-table-column prop="level_name" label="等级" width="100" />
        <el-table-column label="上级" width="140">
          <template #default="{ row }">
            <span v-if="row.pid > 0">{{ row.parent_name || '—' }}（{{ row.pid }}）</span>
            <span v-else style="color: #bbb">—</span>
          </template>
        </el-table-column>
        <el-table-column prop="child_num" label="下级" width="70" />
        <el-table-column label="余额" width="100">
          <template #default="{ row }">{{ money(row.balance) }}</template>
        </el-table-column>
        <el-table-column label="佣金" width="100">
          <template #default="{ row }">{{ money(row.commission_money) }}</template>
        </el-table-column>
        <el-table-column label="累计消费" width="100">
          <template #default="{ row }">{{ money(row.cost) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'danger'" size="small">
              {{ Number(row.status) === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="注册时间" width="160">
          <template #default="{ row }">{{ fmt(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="warning" @click="doResetPassword(row)">重置密码</el-button>
            <el-popconfirm v-if="row.pid > 0" title="确认解除该会员的上级绑定？" width="220" @confirm="doUnbind(row)">
              <template #reference><el-button link type="info">解绑</el-button></template>
            </el-popconfirm>
            <el-popconfirm title="确认删除该会员？（软删，可还原）" width="240" @confirm="doDelete(row)">
              <template #reference><el-button link type="danger">删除</el-button></template>
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

    <!-- 新增/编辑 -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑会员' : '新增会员'" width="520px">
      <el-form label-width="90px">
        <el-form-item v-if="!form.id" label="账号" required><el-input v-model="form.username" /></el-form-item>
        <el-form-item v-if="!form.id" label="密码" required>
          <el-input v-model="form.password" type="password" show-password placeholder="至少 6 位" />
        </el-form-item>
        <el-form-item label="昵称"><el-input v-model="form.nickname" /></el-form-item>
        <el-form-item label="手机号"><el-input v-model="form.mobile" /></el-form-item>
        <el-form-item label="邮箱"><el-input v-model="form.email" /></el-form-item>
        <el-form-item label="等级">
          <el-select v-model="form.level_id" style="width: 100%">
            <el-option v-for="l in levels" :key="l.id" :label="l.name" :value="l.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注"><el-input v-model="form.mark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情 -->
    <!-- 会员详情：1000px 抽屉 + 页签级懒加载（资金/佣金/订单各自一次列表请求） -->
    <mks-detail-drawer v-model="detailVisible" title="用户信息" :tabs="detailTabs" @tab-load="onDetailTabLoad">
      <template #top>
        <ul class="mks-dt-meta">
          <li>余额<b>{{ money(detail?.balance) }}</b></li>
          <li>佣金<b>{{ money(detail?.commission_money) }}</b></li>
          <li>累计消费<b>{{ money(detail?.cost) }}</b></li>
          <li>成交订单<b>{{ detail?.order_success ?? 0 }} / {{ detail?.order_count ?? 0 }} 笔</b></li>
        </ul>
      </template>

      <template #info>
        <el-descriptions v-if="detail" :column="2" border size="small">
          <el-descriptions-item label="会员ID">{{ detail.id }}</el-descriptions-item>
          <el-descriptions-item label="账号">{{ detail.username }}</el-descriptions-item>
          <el-descriptions-item label="昵称">{{ detail.nickname }}</el-descriptions-item>
          <el-descriptions-item label="等级">
            {{ detail.level_name }}（折扣率 {{ detail.pay_rate }}%）
          </el-descriptions-item>
          <el-descriptions-item label="上级">{{ detail.pid > 0 ? `${detail.parent_name || '—'}（${detail.pid}）` : '—' }}</el-descriptions-item>
          <el-descriptions-item label="下级数">{{ detail.child_num }}</el-descriptions-item>
          <el-descriptions-item label="余额">{{ money(detail.balance) }}</el-descriptions-item>
          <el-descriptions-item label="佣金余额">{{ money(detail.commission_money) }}</el-descriptions-item>
          <el-descriptions-item label="冻结佣金">{{ money(detail.freez_commission_money) }}</el-descriptions-item>
          <el-descriptions-item label="累计佣金">{{ money(detail.total_commission_money) }}</el-descriptions-item>
          <el-descriptions-item label="累计消费">{{ money(detail.cost) }}</el-descriptions-item>
          <el-descriptions-item label="累计充值">{{ money(detail.total_recharge_money) }}</el-descriptions-item>
          <el-descriptions-item label="订单数">{{ detail.order_count }}</el-descriptions-item>
          <el-descriptions-item label="成功订单">{{ detail.order_success }}</el-descriptions-item>
          <el-descriptions-item label="注册时间">{{ fmt(detail.create_time) }}</el-descriptions-item>
          <el-descriptions-item label="最后登录">{{ fmt(detail.last_time) }}</el-descriptions-item>
        </el-descriptions>
      </template>

      <template #money>
        <el-table :data="tabData.money.list" border size="small" v-loading="tabData.money.loading">
          <template #empty><mks-empty text="暂无资金流水" /></template>
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
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
          <el-table-column prop="mark" label="备注" min-width="120" show-overflow-tooltip />
        </el-table>
      </template>

      <template #commission>
        <el-table :data="tabData.commission.list" border size="small" v-loading="tabData.commission.loading">
          <template #empty><mks-empty text="暂无佣金记录" /></template>
          <el-table-column prop="order_id" label="订单ID" width="90" />
          <el-table-column prop="layer" label="层级" width="70" />
          <el-table-column prop="commision_price" label="佣金" width="100" />
          <el-table-column prop="sure_status_text" label="结算状态" width="100" />
          <el-table-column label="时间" width="170">
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
        </el-table>
      </template>

      <template #orders>
        <el-table :data="tabData.orders.list" border size="small" v-loading="tabData.orders.loading">
          <template #empty><mks-empty text="暂无订单" /></template>
          <el-table-column prop="ordersn" label="订单号" width="190" show-overflow-tooltip />
          <el-table-column prop="goods_name" label="商品" min-width="160" show-overflow-tooltip />
          <el-table-column prop="total_price" label="金额" width="100" />
          <el-table-column prop="status_text" label="状态" width="100" />
          <el-table-column label="下单时间" width="170">
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
        </el-table>
      </template>
    </mks-detail-drawer>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  memberIndex, memberRead, memberCreate, memberSave, memberDelete,
  memberUnbind, memberResetPassword, memberLevelList,
  orderIndex, commissionIndex, memberMoneyIndex,
} from '../../api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksEmpty from '@/components/mksEmpty.vue';
import mksDetailDrawer from '@/components/mksDetailDrawer.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const levels = ref([]);
const query = reactive({ page: 1, limit: 20, keyword: '', level_id: null, status: '', pid: '' });
const statusTabs = [{ label: '全部', value: '' }, { label: '正常', value: 1 }, { label: '禁用', value: 0 }];
const formVisible = ref(false);
const saving = ref(false);
const form = ref({});
const detailVisible = ref(false);
const detail = ref(null);

const money = (v) => `¥${Number(v || 0).toFixed(2)}`;
const fmt = (t) => {
  if (typeof t === 'string' && t.includes('-')) return t;
  return Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—';
};

const buildWhere = () => {
  const where = [];
  if (query.level_id !== null && query.level_id !== '') where.push({ field: 'level_id', op: 'eq', value: query.level_id });
  if (query.status !== null && query.status !== '') where.push({ field: 'status', op: 'eq', value: query.status });
  if (query.pid) where.push({ field: 'pid', op: 'eq', value: query.pid });
  return JSON.stringify(where);
};

const load = async () => {
  loading.value = true;
  try {
    const params = { page: query.page, limit: query.limit, where: buildWhere() };
    if (query.keyword) params.keyword = query.keyword;
    const res = await memberIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.page = 1; load(); };
const resetQuery = () => {
  Object.assign(query, { page: 1, keyword: '', level_id: null, status: '', pid: '' });
  load();
};
const openCreate = () => {
  form.value = { username: '', password: '', nickname: '', mobile: '', email: '', level_id: levels.value[0]?.id || 1, status: 1, mark: '' };
  formVisible.value = true;
};
const openEdit = (row) => {
  form.value = {
    id: row.id, username: row.username, nickname: row.nickname, mobile: row.mobile,
    email: row.email, level_id: row.level_id, status: Number(row.status), mark: row.mark || '',
  };
  formVisible.value = true;
};
const doSave = async () => {
  saving.value = true;
  try {
    if (form.value.id) {
      await memberSave(form.value.id, form.value);
    } else {
      await memberCreate(form.value);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
};
const detailTabs = [
  { label: '用户信息', name: 'info' },
  { label: '资金明细', name: 'money' },
  { label: '佣金明细', name: 'commission' },
  { label: '购买记录', name: 'orders' },
];
const tabData = reactive({
  money: { list: [], loading: false, loaded: false },
  commission: { list: [], loading: false, loaded: false },
  orders: { list: [], loading: false, loaded: false },
});

// 页签首次激活才发请求（对方抽屉同款懒加载节奏）
const onDetailTabLoad = async (name) => {
  const uid = detail.value?.id;
  if (!uid || !tabData[name] || tabData[name].loaded) return;
  tabData[name].loading = true;
  try {
    let res;
    if (name === 'money') res = await memberMoneyIndex({ uid, limit: 100 });
    else if (name === 'commission') res = await commissionIndex({ uid, limit: 100 });
    else res = await orderIndex({ page: 1, limit: 100, where: JSON.stringify([{ field: 'uid', op: 'eq', value: uid }]) });
    tabData[name].list = res.data.list || [];
    tabData[name].loaded = true;
  } finally {
    tabData[name].loading = false;
  }
};

const openDetail = async (row) => {
  const res = await memberRead(row.id);
  detail.value = res.data || null;
  Object.values(tabData).forEach((v) => { v.list = []; v.loaded = false; });
  detailVisible.value = true;
};
const doResetPassword = async (row) => {
  try {
    const { value } = await ElMessageBox.prompt('请输入新密码（至少 6 位）', `重置 ${row.username} 的密码`, {
      inputType: 'password',
      inputPattern: /^.{6,}$/,
      inputErrorMessage: '密码至少 6 位',
    });
    await memberResetPassword(row.id, value);
    ElMessage.success('密码已重置');
  } catch (e) { /* 取消 */ }
};
const doUnbind = async (row) => {
  await memberUnbind(row.id);
  ElMessage.success('已解绑');
  load();
};
const doDelete = async (row) => {
  await memberDelete(row.id);
  ElMessage.success('已删除');
  load();
};

onMounted(async () => {
  try {
    const res = await memberLevelList();
    levels.value = res.data || [];
  } catch (e) { /* ignore */ }
  load();
});
</script>

<style scoped>
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
</style>
