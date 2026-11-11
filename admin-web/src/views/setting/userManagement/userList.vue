<template>
  <div class="page-card">
    <el-card shadow="never">
      <el-tabs v-model="tab">
        <el-tab-pane label="管理员列表" name="list">
          <div class="toolbar">
            <el-input v-model="query.username" placeholder="账号" clearable style="width: 160px" @keyup.enter="load" />
            <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px">
              <el-option label="正常" value="1" /><el-option label="禁用" value="0" />
            </el-select>
            <el-button type="primary" @click="load">查询</el-button>
            <el-button type="success" @click="openForm()">新增管理员</el-button>
          </div>
          <el-table :data="list" border v-loading="loading">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="username" label="账号" min-width="110" />
            <el-table-column prop="realname" label="姓名" min-width="90" />
            <el-table-column prop="mobile" label="手机号" width="120" />
            <el-table-column prop="roles" label="角色" width="80" />
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.status === 1" @change="toggleStatus(row)" />
              </template>
            </el-table-column>
            <el-table-column prop="last_time" label="最后登录" width="160">
              <template #default="{ row }">{{ row.last_time ? formatTime(row.last_time) : '-' }}</template>
            </el-table-column>
            <el-table-column prop="login_count" label="登录次数" width="90" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openForm(row)">编辑</el-button>
                <el-popconfirm title="确定删除该管理员？" @confirm="doDelete(row.id)">
                  <template #reference><el-button link type="danger">删除</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="total"
              :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="操作日志" name="do_log">
          <el-table :data="logs" border v-loading="logLoading" height="520">
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="uid" label="管理员ID" width="90" />
            <el-table-column prop="title" label="操作" min-width="160" />
            <el-table-column prop="ip" label="IP" width="130" />
            <el-table-column prop="mark" label="备注" min-width="240" show-overflow-tooltip />
            <el-table-column prop="create_time" label="时间" width="160">
              <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
            </el-table-column>
          </el-table>
          <div class="pager">
            <el-pagination background layout="total, prev, pager, next" :total="logTotal"
              :page-size="logQuery.limit" v-model:current-page="logQuery.page" @current-change="loadLogs" />
          </div>
        </el-tab-pane>

        <el-tab-pane label="登录日志" name="login_log">
          <el-table :data="logs" border v-loading="logLoading" height="520">
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="uid" label="管理员ID" width="90" />
            <el-table-column prop="title" label="事件" min-width="120" />
            <el-table-column prop="ip" label="IP" width="130" />
            <el-table-column prop="create_time" label="时间" width="160">
              <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新增/编辑 -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑管理员' : '新增管理员'" width="480px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="账号" required>
          <el-input v-model="form.username" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="密码" :required="!form.id">
          <el-input v-model="form.password" type="password" show-password
            :placeholder="form.id ? '留空则不修改密码' : '至少 8 位'" />
        </el-form-item>
        <el-form-item label="姓名"><el-input v-model="form.realname" /></el-form-item>
        <el-form-item label="手机号"><el-input v-model="form.mobile" /></el-form-item>
        <el-form-item label="邮箱"><el-input v-model="form.email" /></el-form-item>
        <el-form-item label="角色ID">
          <el-select v-model="form.roles" placeholder="选择角色">
            <el-option v-for="r in roles" :key="r.id" :label="r.name" :value="String(r.id)" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import {
  adminIndex, adminCreate, adminSave, adminDelete, adminModify,
  adminDoLog, adminLoginLog, roleIndex,
} from '@/api';

const tab = ref('list');
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const query = reactive({ page: 1, limit: 15, username: '', status: '' });

const roles = ref([]);
const formVisible = ref(false);
const saving = ref(false);
const form = reactive({ id: 0, username: '', password: '', realname: '', mobile: '', email: '', roles: '1', status: 1 });

const logs = ref([]);
const logTotal = ref(0);
const logLoading = ref(false);
const logQuery = reactive({ page: 1, limit: 20 });

function formatTime(ts) {
  return ts ? new Date(ts * 1000).toLocaleString('zh-CN', { hour12: false }) : '-';
}

async function load() {
  loading.value = true;
  try {
    const where = [];
    if (query.username) where.push({ field: 'username', op: 'like', value: query.username });
    if (query.status !== '' && query.status !== null) where.push({ field: 'status', op: 'eq', value: query.status });
    const res = await adminIndex({ page: query.page, limit: query.limit, where: JSON.stringify(where) });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
  } finally {
    loading.value = false;
  }
}

async function loadLogs() {
  logLoading.value = true;
  try {
    const api = tab.value === 'login_log' ? adminLoginLog : adminDoLog;
    const res = await api({ page: logQuery.page, limit: logQuery.limit });
    logs.value = res.data.list || [];
    logTotal.value = res.data.count || 0;
  } finally {
    logLoading.value = false;
  }
}

function openForm(row) {
  Object.assign(form, { id: 0, username: '', password: '', realname: '', mobile: '', email: '', roles: '1', status: 1 });
  if (row) {
    Object.assign(form, {
      id: row.id, username: row.username, password: '', realname: row.realname,
      mobile: row.mobile, email: row.email, roles: String(row.roles || '1'), status: row.status,
    });
  }
  formVisible.value = true;
}

async function doSave() {
  saving.value = true;
  try {
    if (form.id) {
      await adminSave(form.id, { ...form });
    } else {
      await adminCreate({ ...form });
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doDelete(id) {
  await adminDelete(id);
  ElMessage.success('已删除');
  load();
}

async function toggleStatus(row) {
  await adminModify(row.id, 'status', row.status === 1 ? 0 : 1);
  ElMessage.success('已更新');
  load();
}

watch(tab, (t) => {
  if (t === 'do_log' || t === 'login_log') loadLogs();
});

onMounted(async () => {
  load();
  const res = await roleIndex({ limit: 100 });
  roles.value = res.data.list || [];
});
</script>
