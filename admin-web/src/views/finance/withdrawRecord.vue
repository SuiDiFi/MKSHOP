<template>
  <mks-page>
    <template #alert>
      提现申请需人工核对线下打款后再通过，通过后冻结金额直接出账且不可撤销；快捷入口：
      <a class="quick-link" @click="goCfg('/finance/membercommission')">分销佣金</a>·
      <a class="quick-link" @click="goCfg('/member/list')">用户列表</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="来源">
            <el-select v-model="query.type" placeholder="全部来源" clearable style="width: 130px" @change="doSearch">
              <el-option label="余额" :value="1" />
              <el-option label="佣金" :value="2" />
              <el-option label="商户货款" :value="3" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键字">
            <el-input v-model="query.keyword" placeholder="会员/姓名/账号" clearable style="width: 200px" @keyup.enter="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #status>
      <mks-status-tabs v-model="query.status" :tabs="statusTabs" @update:model-value="doSearch" />
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="没有符合条件的提现申请" hint="试试放宽来源或关键字筛选" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="会员" min-width="130">
          <template #default="{ row }">
            {{ row.apply_uname }}<span style="color: #909399">（{{ row.apply_uid }}）</span>
          </template>
        </el-table-column>
        <el-table-column label="来源" width="80">
          <template #default="{ row }">
            <el-tag :type="Number(row.type) === 2 ? 'warning' : (Number(row.type) === 3 ? 'success' : 'info')" size="small">{{ row.type_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="提现金额" width="110">
          <template #default="{ row }">{{ money(row.money) }}</template>
        </el-table-column>
        <el-table-column label="手续费" width="100">
          <template #default="{ row }">{{ money(row.fee) }}</template>
        </el-table-column>
        <el-table-column label="实际到账" width="110">
          <template #default="{ row }">{{ money(row.real_money) }}</template>
        </el-table-column>
        <el-table-column label="收款方式" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">
            <div>{{ row.way_name || '—' }}</div>
            <div style="color: #909399; font-size: 12px">{{ row.account }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="true_name" label="收款人" width="100" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="申请时间" width="160">
          <template #default="{ row }">{{ fmt(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="审核信息" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            <div v-if="Number(row.status) !== 1">{{ row.admin_uname || '—' }} {{ fmt(row.check_time) }}</div>
            <div v-else style="color: #bbb">—</div>
            <div v-if="row.mark" style="color: #909399; font-size: 12px">{{ row.mark }}</div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <template v-if="Number(row.status) === 1">
              <el-button link type="success" @click="openReview(row, 2)">通过打款</el-button>
              <el-button link type="danger" @click="openReview(row, 3)">拒绝</el-button>
            </template>
            <span v-else style="color: #bbb">已处理</span>
          </template>
        </el-table-column>
      </el-table>
      <template #pager>
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="total"
          v-model:page-size="query.limit" v-model:current-page="query.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="doSearch" @current-change="load" />
      </template>
    </mks-page>

    <el-dialog v-model="reviewVisible" :title="review.status === 2 ? '确认通过并打款' : '拒绝提现申请'" width="480px">
      <el-alert v-if="review.status === 2" type="warning" :closable="false" show-icon
        title="请确认已通过线下渠道完成打款，通过后冻结金额将直接出账且不可撤销" style="margin-bottom: 12px" />
      <el-form label-width="90px">
        <el-form-item label="会员">{{ review.row?.apply_uname }}（{{ review.row?.apply_uid }}）</el-form-item>
        <el-form-item label="金额">{{ money(review.row?.money) }}（手续费 {{ money(review.row?.fee) }}）</el-form-item>
        <el-form-item label="收款账号">{{ review.row?.way_name }} / {{ review.row?.account }} / {{ review.row?.true_name }}</el-form-item>
        <el-form-item label="备注">
          <el-input v-model="review.mark" type="textarea" :rows="2" placeholder="审核备注/打款凭证号（拒绝时建议填写原因）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reviewVisible = false">取消</el-button>
        <el-button :type="review.status === 2 ? 'success' : 'danger'" :loading="submitting" @click="submitReview">
          {{ review.status === 2 ? '确认已打款' : '确认拒绝' }}
        </el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { memberCollectIndex, memberCollectDual } from '../../api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const query = reactive({ page: 1, limit: 20, status: '', type: null, keyword: '' });
const statusTabs = [
  { label: '全部', value: '' },
  { label: '待审核', value: 1 },
  { label: '已通过', value: 2 },
  { label: '已拒绝', value: 3 },
];
const reviewVisible = ref(false);
const submitting = ref(false);
const review = reactive({ row: null, status: 2, mark: '' });

const money = (v) => `¥${Number(v || 0).toFixed(2)}`;
const fmt = (t) => {
  if (typeof t === 'string' && t.includes('-')) return t;
  return Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—';
};
const statusTag = (s) => ({ 1: 'warning', 2: 'success', 3: 'danger' }[Number(s)] || 'info');

const load = async () => {
  loading.value = true;
  try {
    const params = { page: query.page, limit: query.limit };
    if (query.status !== null && query.status !== '') params.status = query.status;
    if (query.type !== null && query.type !== '') params.type = query.type;
    if (query.keyword) params.keyword = query.keyword;
    const res = await memberCollectIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.page = 1; load(); };
const resetQuery = () => {
  Object.assign(query, { page: 1, status: '', type: null, keyword: '' });
  load();
};
const openReview = (row, status) => {
  review.row = row;
  review.status = status;
  review.mark = '';
  reviewVisible.value = true;
};
const submitReview = async () => {
  submitting.value = true;
  try {
    await memberCollectDual(review.row.id, { status: review.status, mark: review.mark });
    ElMessage.success(review.status === 2 ? '已通过并确认打款' : '已拒绝，金额已退回');
    reviewVisible.value = false;
    load();
  } finally {
    submitting.value = false;
  }
};

onMounted(load);
</script>

<style scoped>
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
</style>
