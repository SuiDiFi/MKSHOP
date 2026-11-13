<template>
  <mks-page>
    <template #alert>
      角色勾选的菜单与接口权限点决定管理员登录后可见的菜单和可调用的接口，权限保存后需重新登录生效；快捷入口：
      <a class="quick-link" @click="goCfg('/setting/usermanagement/menulist')">菜单管理</a>·
      <a class="quick-link" @click="goCfg('/setting/usermanagement/userlist')">管理员列表</a>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openForm()">新增角色</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有角色" hint="新增角色并在「权限」中勾选菜单后，管理员才有对应入口" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="角色名称" min-width="140" />
        <el-table-column prop="value" label="标识" width="110" />
        <el-table-column label="超管" width="80">
          <template #default="{ row }">
            <el-tag v-if="row.role === '*' || row.value === 'super'" type="danger">是</el-tag>
            <el-tag v-else type="info">否</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="mark" label="备注" min-width="140" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openPerms(row)">权限</el-button>
            <el-button link type="primary" @click="openForm(row)" :disabled="row.id === 1">编辑</el-button>
            <el-button link type="danger" @click="doDelete(row.id)" :disabled="row.id === 1">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </mks-page>

    <!-- 角色表单 -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑角色' : '新增角色'" width="460px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="角色名称" required><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="角色标识"><el-input v-model="form.value" placeholder="如 Admin（super 为超管）" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.mark" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 权限配置 -->
    <el-dialog v-model="permVisible" title="配置权限（菜单 + 接口权限点）" width="560px">
      <el-tree
        ref="permTree"
        :data="tree"
        show-checkbox
        node-key="id"
        :props="{ label: 'title', children: 'children' }"
        default-expand-all
        :default-checked-keys="checkedKeys"
      />
      <template #footer>
        <el-button @click="permVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSavePerms">保存权限</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { roleIndex, roleCreate, roleSave, roleDelete, roleModifyPerms, routerIndex, getMenus } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const tree = ref([]);
const checkedKeys = ref([]);
const currentRole = ref(null);
const formVisible = ref(false);
const permVisible = ref(false);
const form = reactive({ id: 0, name: '', value: '', mark: '', status: 1 });

async function load() {
  loading.value = true;
  try {
    const res = await roleIndex({ limit: 100 });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

async function loadTree(role) {
  currentRole.value = role;
  // 权限树 = 全部菜单节点 + 接口权限点（router/index 拉平铺）
  const res = await routerIndex({ limit: 500 });
  const all = res.data.list || [];
  // 菜单树（后台 getMenus 已按当前管理员过滤，超管拿全量树）+ 接口点挂到所属菜单下
  const menuRes = await getMenus();
  const menus = menuRes.data || [];
  const menusById = {};
  const walk = (nodes) => {
    for (const n of nodes || []) {
      menusById[n.id] = { ...n, children: menusById[n.id]?.children || [] };
      walk(n.children);
    }
  };
  walk(menus);
  // 接口权限点按 api_url 归属不到菜单 parentId（种子中多数 parentId 指向菜单），按 parentId 挂
  for (const p of all) {
    if (p.auth_type !== 2) continue;
    const parent = menusById[p.parentId];
    if (parent) parent.children.push({ id: p.id, title: `接口:${p.title}`, children: [] });
  }
  tree.value = Object.values(menusById);

  // 已勾选 = role.role 串中的 id
  checkedKeys.value = String(role.role || '')
    .split(',')
    .filter((v) => /^\d+$/.test(v))
    .map(Number);
  permVisible.value = true;
}

async function doSavePerms() {
  saving.value = true;
  try {
    const checked = permTree.value.getCheckedKeys().concat(permTree.value.getHalfCheckedKeys());
    await roleModifyPerms(currentRole.value.id, checked.join(','));
    ElMessage.success('权限已更新（重新登录后生效）');
    permVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

function openForm(row) {
  Object.assign(form, { id: 0, name: '', value: '', mark: '', status: 1 });
  if (row) Object.assign(form, { id: row.id, name: row.name, value: row.value, mark: row.mark, status: row.status });
  formVisible.value = true;
}

async function doSave() {
  saving.value = true;
  try {
    if (form.id) await roleSave(form.id, { ...form });
    else await roleCreate({ ...form });
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doDelete(id) {
  await roleDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
