<template>
  <mks-page>
    <template #alert>
      品牌用于商品打标与前台筛选，排序值决定品牌列表展示顺序；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/list')">商品列表</a>·
      <a class="quick-link" @click="goCfg('/goods/category')">商品分类</a>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openForm()">新增品牌</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有品牌" hint="新增品牌后即可在商品编辑页勾选" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="品牌名称" min-width="180">
          <template #default="{ row }">
            <span :style="{ color: row.color || '' }">{{ row.name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="short_name" label="简称" width="120" />
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0"
              @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
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
      <template #pager>
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="total"
          v-model:page-size="query.limit" v-model:current-page="query.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="load" @current-change="load" />
      </template>
    </mks-page>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑品牌' : '新增品牌'" width="460px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="名称" required><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item label="简称"><el-input v-model="form.short_name" maxlength="100" /></el-form-item>
        <el-form-item label="颜色"><el-color-picker v-model="form.color" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
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
import { tagsIndex, tagsCreate, tagsSave, tagsDelete, tagsModify } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const query = reactive({ page: 1, limit: 20 });
const formVisible = ref(false);
const emptyForm = () => ({ id: 0, name: '', short_name: '', color: '', sort: 100, status: 1 });
const form = reactive(emptyForm());
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

async function load() {
  loading.value = true;
  try {
    const res = await tagsIndex({ page: query.page, limit: query.limit });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
  } finally {
    loading.value = false;
  }
}

function openForm(row) {
  Object.assign(form, emptyForm());
  if (row) Object.assign(form, row);
  formVisible.value = true;
}

async function doSave() {
  if (!form.name) return ElMessage.warning('请填写品牌名称');
  saving.value = true;
  try {
    if (form.id) {
      await tagsSave(form.id, { ...form });
    } else {
      const { id, ...payload } = form;
      await tagsCreate(payload);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await tagsModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await tagsDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
