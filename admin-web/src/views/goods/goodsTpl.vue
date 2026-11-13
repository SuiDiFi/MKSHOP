<template>
  <mks-page>
    <template #alert>
      参数模板决定商品下单时要求买家额外填写的输入项；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/list')">商品列表</a>·
      <a class="quick-link" @click="goCfg('/goods/category')">商品分类</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="模板名称">
            <el-input v-model="query.keyword" placeholder="模板名称" clearable style="width: 200px" @keyup.enter="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openForm()">添加参数模板</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有参数模板" hint="添加模板后，到商品编辑页勾选即可启用" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="模板名称" min-width="180" />
        <el-table-column label="输入项" min-width="240" show-overflow-tooltip>
          <template #default="{ row }">{{ row.input_labels || '—' }}</template>
        </el-table-column>
        <el-table-column label="输入项数" width="90">
          <template #default="{ row }">{{ row.input_count }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="权重" width="80" />
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确认删除该模板？" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <template #pager>
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="total"
          v-model:page-size="query.limit" v-model:current-page="query.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="doSearch" @current-change="load" />
      </template>
    </mks-page>

    <!-- 模板编辑弹窗：动态输入项（对齐 KASUSHOU「添加输入项」） -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑参数模板' : '添加参数模板'" width="720px" top="6vh">
      <el-form label-width="90px">
        <el-form-item label="模板名称" required><el-input v-model="form.name" maxlength="100" style="width: 320px" /></el-form-item>
        <el-form-item label="权重"><el-input-number v-model="form.sort" :min="0" style="width: 140px" /></el-form-item>
        <el-form-item label="输入项">
          <div style="width: 100%">
            <div v-for="(item, i) in form.inputs" :key="i" class="tpl-row">
              <el-input v-model="item.label" placeholder="输入项名称（如 卡号）" style="width: 200px" />
              <el-input v-model="item.placeholder" placeholder="占位提示（可选）" style="width: 220px; margin-left: 6px" />
              <el-checkbox v-model="item.required" style="margin-left: 6px">必填</el-checkbox>
              <el-button link type="danger" @click="form.inputs.splice(i, 1)">删除</el-button>
            </div>
            <el-button size="small" type="primary" plain @click="addInput">添加输入项</el-button>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">确定</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { goodsTplIndex, goodsTplCreate, goodsTplSave, goodsTplDelete, goodsTplModify } from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const query = reactive({ page: 1, limit: 20, keyword: '' });
const formVisible = ref(false);
const form = reactive({ id: 0, name: '', sort: 100, status: 1, inputs: [] });

const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

function buildWhere() {
  const where = [];
  if (query.keyword) where.push({ field: 'name', op: 'like', value: query.keyword });
  return JSON.stringify(where);
}

async function load() {
  loading.value = true;
  try {
    const res = await goodsTplIndex({ page: query.page, limit: query.limit, where: buildWhere() });
    list.value = res.data.list || [];
    total.value = res.data.count || 0;
  } finally {
    loading.value = false;
  }
}

function doSearch() {
  query.page = 1;
  load();
}

function resetQuery() {
  query.keyword = '';
  doSearch();
}

function addInput() {
  form.inputs.push({ label: '', placeholder: '', required: false });
}

async function openForm(row) {
  form.id = row ? row.id : 0;
  form.name = row ? row.name : '';
  form.sort = row ? Number(row.sort) : 100;
  form.status = row ? Number(row.status) : 1;
  let inputs = row ? row.inputs : [];
  if (typeof inputs === 'string') {
    try { inputs = JSON.parse(inputs || '[]'); } catch (e) { inputs = []; }
  }
  form.inputs = (Array.isArray(inputs) ? inputs : []).map((x) => ({
    label: x.label || '', placeholder: x.placeholder || '', required: !!x.required,
  }));
  formVisible.value = true;
}

async function doSave() {
  if (!form.name) return ElMessage.warning('请填写模板名称');
  const inputs = form.inputs.filter((x) => x.label.trim());
  if (!inputs.length) return ElMessage.warning('至少添加一个输入项');
  saving.value = true;
  try {
    const payload = { name: form.name, sort: form.sort, status: form.status, inputs };
    if (form.id) await goodsTplSave(form.id, payload);
    else await goodsTplCreate(payload);
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await goodsTplModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await goodsTplDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>

<style scoped>
.tpl-row { display: flex; align-items: center; margin-bottom: 8px; }
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
