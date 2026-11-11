<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar" style="margin-bottom: 0">
          <el-button type="success" @click="openForm()">新增分类</el-button>
          <el-button @click="load">刷新</el-button>
        </div>
      </template>

      <el-table :data="tree" border row-key="id" v-loading="loading"
        :tree-props="{ children: 'children' }" default-expand-all>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="分类名称" min-width="220">
          <template #default="{ row }">
            <span :style="{ color: row.color || '' }">{{ row.name }}</span>
            <el-tag v-if="row.badge" size="small" style="margin-left: 4px">{{ row.badge }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0"
              @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column prop="goods_num" label="商品数" width="90" />
        <el-table-column prop="sort" label="排序" width="80">
          <template #default="{ row }">
            <el-input-number v-model="row.sort" size="small" :controls="false" style="width: 60px"
              @change="(v) => doModify(row, 'sort', v)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-button link type="success" @click="openForm(null, row.id)">加子级</el-button>
            <el-popconfirm title="确定删除？（有子级/挂有商品时不可删）" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑分类' : '新增分类'" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="上级分类">
          <el-tree-select v-model="form.pid" :data="tree" check-strictly clearable
            node-key="id" :props="{ label: 'name', value: 'id', children: 'children' }"
            placeholder="不选=顶级分类" style="width: 100%" />
        </el-form-item>
        <el-form-item label="名称" required><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item label="简称"><el-input v-model="form.short_name" /></el-form-item>
        <el-form-item label="颜色"><el-color-picker v-model="form.color" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
        <el-form-item label="图片"><el-input v-model="form.img" placeholder="图片 URL" /></el-form-item>
        <el-form-item label="角标文本"><el-input v-model="form.badge" /></el-form-item>
        <el-form-item label="跳转链接">
          <el-switch v-model="form.is_jump_url" :active-value="1" :inactive-value="0" />
          <el-input v-if="form.is_jump_url" v-model="form.jump_url" placeholder="外部链接" style="margin-top: 6px" />
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
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { categoryList, categoryCreate, categorySave, categoryDelete, categoryModify } from '@/api';

const loading = ref(false);
const saving = ref(false);
const tree = ref([]);
const formVisible = ref(false);
const emptyForm = () => ({
  id: 0, pid: 0, name: '', short_name: '', color: '', sort: 100, status: 1,
  img: '', badge: '', is_jump_url: 0, jump_url: '',
});
const form = reactive(emptyForm());

async function load() {
  loading.value = true;
  try {
    const res = await categoryList();
    tree.value = res.data || [];
  } finally {
    loading.value = false;
  }
}

function openForm(row, pid = 0) {
  Object.assign(form, emptyForm());
  form.pid = pid || 0;
  if (row) {
    Object.assign(form, row);
    form.is_jump_url = row.is_jump_url || 0;
    form.jump_url = row.jump_url || '';
  }
  formVisible.value = true;
}

async function doSave() {
  if (!form.name) return ElMessage.warning('请填写分类名称');
  saving.value = true;
  try {
    if (form.id) {
      await categorySave(form.id, { ...form });
    } else {
      const { id, ...payload } = form;
      await categoryCreate(payload);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await categoryModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await categoryDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>
