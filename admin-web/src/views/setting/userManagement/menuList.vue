<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar" style="margin-bottom: 0">
          <el-button type="success" @click="openForm(0)">新增节点</el-button>
          <el-button @click="load">刷新</el-button>
          <el-tag type="warning" style="margin-left: auto">修改菜单后需重新登录刷新前端路由</el-tag>
        </div>
      </template>
      <el-table :data="tree" border row-key="id" default-expand-all v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="title" label="标题" min-width="160" />
        <el-table-column prop="name" label="路由名" min-width="120" />
        <el-table-column prop="path" label="路径" min-width="130" />
        <el-table-column prop="component" label="组件" min-width="180" show-overflow-tooltip />
        <el-table-column label="类型" width="90">
          <template #default="{ row }">
            <el-tag :type="row.auth_type === 1 ? 'primary' : 'warning'" size="small">
              {{ row.auth_type === 1 ? '菜单' : '接口点' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="api_url" label="接口地址" min-width="130" />
        <el-table-column prop="methods" label="方法" width="80" />
        <el-table-column prop="hidden" label="隐藏" width="70">
          <template #default="{ row }">{{ row.hidden === 1 ? '是' : '否' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row.id)">编辑</el-button>
            <el-popconfirm title="确定删除该节点（含子节点需先删子节点）？" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑节点' : '新增节点'" width="560px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="父节点ID">
          <el-input-number v-model="form.parentId" :min="0" />
        </el-form-item>
        <el-form-item label="标题" required><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="类型">
          <el-radio-group v-model="form.auth_type">
            <el-radio :value="1">菜单节点</el-radio>
            <el-radio :value="2">接口权限点</el-radio>
          </el-radio-group>
        </el-form-item>
        <template v-if="form.auth_type === 1">
          <el-form-item label="路由名"><el-input v-model="form.name" placeholder="唯一，如 UserList" /></el-form-item>
          <el-form-item label="路由路径"><el-input v-model="form.path" placeholder="顶级用 /xxx，子级用 xxx" /></el-form-item>
          <el-form-item label="组件"><el-input v-model="form.component" placeholder="@/views/setting/base" /></el-form-item>
          <el-form-item label="图标"><el-input v-model="form.icon" /></el-form-item>
          <el-form-item label="菜单隐藏"><el-switch v-model="form.hidden" :active-value="1" :inactive-value="0" /></el-form-item>
        </template>
        <template v-else>
          <el-form-item label="接口地址" required><el-input v-model="form.api_url" placeholder="如 admin/index" /></el-form-item>
          <el-form-item label="HTTP方法">
            <el-select v-model="form.methods" style="width: 140px">
              <el-option label="GET" value="GET" /><el-option label="POST" value="POST" />
            </el-select>
          </el-form-item>
        </template>
        <el-form-item label="排序"><el-input-number v-model="form.sort" :min="-999" :max="9999" /></el-form-item>
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
import { routerIndex, routerRead, routerCreate, routerSave, routerDelete } from '@/api';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const tree = ref([]);
const formVisible = ref(false);
const form = reactive({});

const EMPTY = {
  id: 0, parentId: 0, title: '', name: '', path: '', component: '', icon: '', hidden: 0,
  sort: 100, auth_type: 1, api_url: '', methods: 'GET',
};

function toTree(rows) {
  const map = {};
  for (const r of rows) map[r.id] = { ...r, children: map[r.id]?.children || [] };
  const tree = [];
  for (const id of Object.keys(map)) {
    const pid = map[id].parentId;
    if (pid > 0 && map[pid]) map[pid].children.push(map[id]);
    else tree.push(map[id]);
  }
  return tree;
}

async function load() {
  loading.value = true;
  try {
    const res = await routerIndex({ limit: 500 });
    list.value = res.data.list || [];
    tree.value = toTree(list.value);
  } finally {
    loading.value = false;
  }
}

async function openForm(id) {
  Object.assign(form, EMPTY);
  if (id) {
    const res = await routerRead(id);
    Object.assign(form, res.data);
  }
  formVisible.value = true;
}

async function doSave() {
  saving.value = true;
  try {
    if (form.id) await routerSave(form.id, { ...form });
    else await routerCreate({ ...form });
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doDelete(id) {
  await routerDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>
