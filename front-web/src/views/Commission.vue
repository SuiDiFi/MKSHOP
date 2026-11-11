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
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { commissionIndex, commissionTeams, commissionLogs } from '@/api';

const overview = ref(null);
const teams = ref([]);
const logs = ref([]);
const teamLoading = ref(false);
const logLoading = ref(false);

const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleDateString('zh-CN') : '—');

onMounted(async () => {
  try {
    const res = await commissionIndex();
    overview.value = res.data || null;
  } catch (e) { /* ignore */ }
  teamLoading.value = true;
  commissionTeams({ page: 1, limit: 10 }).then((r) => { teams.value = r.data?.list || []; }).finally(() => { teamLoading.value = false; });
  logLoading.value = true;
  commissionLogs({ page: 1, limit: 10 }).then((r) => { logs.value = r.data?.list || []; }).finally(() => { logLoading.value = false; });
});
</script>
