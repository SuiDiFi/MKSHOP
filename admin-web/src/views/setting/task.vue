<template>
  <mks-page>
    <template #alert>
      定时任务由 cron 每分钟执行 `php think mks:timer` 触发，只有已注册的 mark 才会跑（渠道补单、处理中订单同步兜底、统计刷新）；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/docking')">对接渠道</a>·
      <a class="quick-link" @click="goCfg('/order/list')">订单列表</a>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openForm()">新增任务</el-button>
      <el-button size="small" plain @click="load">刷新</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有定时任务" hint="新增任务并启用后，队列进程会按周期自动执行" /></template>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="title" label="任务标题" min-width="150" />
        <el-table-column prop="name" label="任务标识" min-width="150" />
        <el-table-column prop="cycle" label="执行周期" min-width="120" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch :model-value="row.status === 1" @change="toggle(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="last_execution_time" label="上次执行" width="160">
          <template #default="{ row }">{{ row.last_execution_time ? formatTime(row.last_execution_time) : '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确定删除该任务？" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </mks-page>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑任务' : '新增任务'" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="任务标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="任务标识" required>
          <el-select v-model="form.name" filterable allow-create placeholder="选择或输入标识">
            <el-option v-for="t in taskNames" :key="t.name" :label="t.title" :value="t.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="执行周期"><el-input v-model="form.cycle" placeholder="如 30/0（每30分钟）" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { timerIndex, timerTaskName, timerCreate, timerSave, timerDelete, timerModify } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const taskNames = ref([]);
const formVisible = ref(false);
const form = reactive({ id: 0, title: '', name: '', cycle: '', status: 0 });

function formatTime(ts) {
  return ts ? new Date(ts * 1000).toLocaleString('zh-CN', { hour12: false }) : '-';
}

async function load() {
  loading.value = true;
  try {
    const res = await timerIndex({ limit: 100 });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

function openForm(row) {
  Object.assign(form, { id: 0, title: '', name: '', cycle: '', status: 0 });
  if (row) Object.assign(form, { id: row.id, title: row.title, name: row.name, cycle: row.cycle || '', status: row.status });
  formVisible.value = true;
}

async function doSave() {
  saving.value = true;
  try {
    if (form.id) await timerSave(form.id, { ...form });
    else await timerCreate({ ...form });
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function toggle(row) {
  await timerModify(row.id, 'status', row.status === 1 ? 0 : 1);
  ElMessage.success('已更新');
  load();
}

async function doDelete(id) {
  await timerDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(async () => {
  load();
  try {
    const res = await timerTaskName();
    taskNames.value = res.data || [];
  } catch (e) { /* 任务注册表可选 */ }
});
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
