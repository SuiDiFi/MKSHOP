<template>
  <div>
    <Topbar />
    <div class="wrap page-body">
      <!-- 概览 -->
      <el-card shadow="never" style="margin-top: 14px" v-if="overview">
        <template #header><b>分销中心</b></template>
        <el-row :gutter="16">
          <el-col :span="6">
            <el-statistic title="可提现佣金" :value="Number(overview.commission_money)" :precision="2" prefix="¥" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="累计佣金" :value="Number(overview.total_commission_money)" :precision="2" prefix="¥" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="我的团队" :value="Number(overview.child_num)" suffix="人" />
          </el-col>
          <el-col :span="6">
            <div style="font-size: 13px; color: #909399">我的邀请码</div>
            <div style="font-size: 22px; font-weight: 700; color: #409eff">{{ overview.invite_code }}</div>
            <el-tag size="small" effect="plain" style="margin-top: 4px">
              一级 {{ overview.one_rate }}% / 二级 {{ overview.two_rate }}%
            </el-tag>
          </el-col>
        </el-row>
      </el-card>

      <el-row :gutter="14" style="margin-top: 14px">
        <!-- 我的团队 -->
        <el-col :span="12">
          <el-card shadow="never">
            <template #header><b>我的团队</b></template>
            <el-table :data="teams" v-loading="teamLoading" size="small">
              <el-table-column prop="id" label="ID" width="70" />
              <el-table-column prop="nickname" label="昵称" min-width="90" show-overflow-tooltip />
              <el-table-column prop="username" label="账号" min-width="90" show-overflow-tooltip />
              <el-table-column label="下级" width="60" prop="child_num" />
              <el-table-column label="注册时间" width="100">
                <template #default="{ row }">{{ fmt(row.create_time) }}</template>
              </el-table-column>
            </el-table>
          </el-card>
        </el-col>
        <!-- 佣金明细 -->
        <el-col :span="12">
          <el-card shadow="never">
            <template #header><b>佣金明细</b></template>
            <el-table :data="logs" v-loading="logLoading" size="small">
              <el-table-column label="变动" width="90">
                <template #default="{ row }">
                  <b :style="{ color: Number(row.change_type) === 1 ? '#67c23a' : '#f56c6c' }">
                    {{ Number(row.change_type) === 1 ? '+' : '-' }}{{ row.money }}
                  </b>
                </template>
              </el-table-column>
              <el-table-column prop="mark" label="说明" min-width="150" show-overflow-tooltip />
              <el-table-column label="时间" width="100">
                <template #default="{ row }">{{ fmt(row.create_time) }}</template>
              </el-table-column>
            </el-table>
          </el-card>
        </el-col>
      </el-row>

      <!-- 佣金提现 -->
      <el-card shadow="never" style="margin-top: 14px" v-if="cfg">
        <template #header>
          <b>佣金提现</b>
          <el-button link type="primary" style="float: right" @click="wayVisible = true">+ 新增收款方式</el-button>
        </template>
        <el-row :gutter="16" style="margin-bottom: 12px">
          <el-col :span="6">
            <el-statistic title="可提现佣金" :value="Number(cfg.commission_money)" :precision="2" prefix="¥" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="提现冻结中" :value="Number(cfg.freez_commission_money)" :precision="2" prefix="¥" />
          </el-col>
          <el-col :span="12">
            <div style="font-size: 13px; color: #909399">
              最低提现 {{ cfg.min_money }} 元；手续费按金额档位收取：
              <span v-for="t in cfg.fee_tiers" :key="t.min">
                {{ t.min }}~{{ Number(t.max) > 0 ? t.max : '不限' }} 元 {{ t.rate }}%；
              </span>
            </div>
          </el-col>
        </el-row>
        <el-form inline>
          <el-form-item label="提现金额">
            <el-input-number v-model="apply.money" :min="Number(cfg.min_money) || 0.01" :precision="2" style="width: 160px" />
          </el-form-item>
          <el-form-item label="收款方式">
            <el-select v-model="apply.way_id" placeholder="请选择" style="width: 200px">
              <el-option v-for="w in cfg.ways" :key="w.id" :label="`${w.name} ${w.account}`" :value="w.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="apply.mark" placeholder="选填" style="width: 160px" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :loading="applying" @click="doApply">申请提现</el-button>
          </el-form-item>
        </el-form>

        <el-table :data="collects" v-loading="collectLoading" size="small">
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column label="金额" width="100">
            <template #default="{ row }">¥{{ row.money }}</template>
          </el-table-column>
          <el-table-column label="手续费" width="90">
            <template #default="{ row }">¥{{ row.fee }}</template>
          </el-table-column>
          <el-table-column prop="way_name" label="收款方式" min-width="120" show-overflow-tooltip />
          <el-table-column prop="account" label="收款账号" min-width="130" show-overflow-tooltip />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag size="small" :type="{ 1: 'warning', 2: 'success', 3: 'danger' }[Number(row.status)] || 'info'">
                {{ row.status_text }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="申请时间" width="170">
            <template #default="{ row }">{{ fmtFull(row.create_time) }}</template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>

    <el-dialog v-model="wayVisible" title="新增收款方式" width="460px">
      <el-form label-width="90px">
        <el-form-item label="方式名称" required><el-input v-model="wayForm.name" placeholder="如：支付宝 / 微信 / 银行卡" /></el-form-item>
        <el-form-item label="收款账号" required><el-input v-model="wayForm.account" /></el-form-item>
        <el-form-item label="收款人"><el-input v-model="wayForm.true_name" /></el-form-item>
        <el-form-item label="开户行"><el-input v-model="wayForm.company" placeholder="银行卡需填写" /></el-form-item>
        <el-form-item label="设为默认">
          <el-switch v-model="wayForm.is_default" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="wayVisible = false">取消</el-button>
        <el-button type="primary" :loading="waySaving" @click="doSaveWay">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
  commissionIndex, commissionTeams, commissionLogs,
  collectConfig, collectIndex, collectSave, collectWaySave,
} from '@/api';

const overview = ref(null);
const teams = ref([]);
const logs = ref([]);
const teamLoading = ref(false);
const logLoading = ref(false);
const cfg = ref(null);
const collects = ref([]);
const collectLoading = ref(false);
const applying = ref(false);
const apply = reactive({ money: 0, way_id: null, mark: '' });
const wayVisible = ref(false);
const waySaving = ref(false);
const wayForm = reactive({ name: '', account: '', true_name: '', company: '', is_default: 0 });

const fmt = (t) => {
  if (typeof t === 'string' && t.includes('-')) return t.slice(0, 10);
  return Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleDateString('zh-CN') : '—';
};
const fmtFull = (t) => {
  if (typeof t === 'string' && t.includes('-')) return t;
  return Number(t) > 0 ? new Date(Number(t) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—';
};

const loadCfg = async () => {
  const res = await collectConfig();
  cfg.value = res.data || null;
  if (cfg.value?.ways?.length && !apply.way_id) {
    apply.way_id = cfg.value.ways[0].id;
  }
};
const loadCollects = async () => {
  collectLoading.value = true;
  try {
    const res = await collectIndex({ page: 1, limit: 10 });
    collects.value = res.data?.list || [];
  } finally {
    collectLoading.value = false;
  }
};
const doApply = async () => {
  if (!apply.way_id) {
    ElMessage.warning('请先添加并选择收款方式');
    return;
  }
  if (!apply.money || apply.money <= 0) {
    ElMessage.warning('请输入提现金额');
    return;
  }
  applying.value = true;
  try {
    await collectSave({ type: 2, money: apply.money, way_id: apply.way_id, mark: apply.mark });
    ElMessage.success('提现申请已提交，等待审核');
    apply.money = 0;
    apply.mark = '';
    await Promise.all([loadCfg(), loadCollects(), refreshOverview()]);
  } finally {
    applying.value = false;
  }
};
const doSaveWay = async () => {
  if (!wayForm.name || !wayForm.account) {
    ElMessage.warning('请填写方式名称与收款账号');
    return;
  }
  waySaving.value = true;
  try {
    await collectWaySave({ ...wayForm });
    ElMessage.success('收款方式已保存');
    wayVisible.value = false;
    Object.assign(wayForm, { name: '', account: '', true_name: '', company: '', is_default: 0 });
    await loadCfg();
  } finally {
    waySaving.value = false;
  }
};
const refreshOverview = async () => {
  try {
    const res = await commissionIndex();
    overview.value = res.data || null;
  } catch (e) { /* ignore */ }
};

onMounted(async () => {
  await refreshOverview();
  loadCfg().catch(() => {});
  loadCollects().catch(() => {});
  teamLoading.value = true;
  commissionTeams({ page: 1, limit: 10 }).then((r) => { teams.value = r.data?.list || []; }).finally(() => { teamLoading.value = false; });
  logLoading.value = true;
  commissionLogs({ page: 1, limit: 10 }).then((r) => { logs.value = r.data?.list || []; }).finally(() => { logLoading.value = false; });
});
</script>

<style scoped>
.page-body { padding-bottom: 30px; }
</style>
