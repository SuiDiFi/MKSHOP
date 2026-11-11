<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar" style="margin-bottom: 0">
          <el-select v-model="query.type" placeholder="全部字典类型" clearable style="width: 200px" @change="load">
            <el-option v-for="t in types" :key="t" :label="t" :value="t" />
          </el-select>
          <el-button type="success" @click="openForm()">新增字典项</el-button>
        </div>
      </template>
      <el-table :data="list" border v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="dict_type" label="类型" min-width="130" />
        <el-table-column prop="code" label="码值" min-width="110" />
        <el-table-column prop="label" label="名称" min-width="140" />
        <el-table-column prop="color" label="标签色" width="100" />
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确定删除？" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
      </div>
    </el-card>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑字典项' : '新增字典项'" width="460px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="类型" required><el-input v-model="form.dict_type" placeholder="如 order_status" /></el-form-item>
        <el-form-item label="码值" required><el-input v-model="form.code" /></el-form-item>
        <el-form-item label="名称" required><el-input v-model="form.label" /></el-form-item>
        <el-form-item label="标签色"><el-input v-model="form.color" placeholder="如 danger/success" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { dictIndex, dictSave, dictDelete } from '@/api';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const types = ref([]);
const query = reactive({ page: 1, limit: 20, type: '' });
const formVisible = ref(false);
const form = reactive({ id: 0, dict_type: '', code: '', label: '', color: '', sort: 0, status: 1 });

async function load() {
  loading.value = true;
  try {
    const res = await dictIndex({ page: query.page, limit: query.limit, type: query.type || '' });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
    types.value = [...new Set(list.value.map((r) => r.dict_type))];
  } finally {
    loading.value = false;
  }
}

function openForm(row) {
  Object.assign(form, { id: 0, dict_type: query.type || '', code: '', label: '', color: '', sort: 0, status: 1 });
  if (row) Object.assign(form, row);
  formVisible.value = true;
}

async function doSave() {
  saving.value = true;
  try {
    await dictSave({ ...form }, form.id);
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doDelete(id) {
  await dictDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>
