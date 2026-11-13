<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-input v-model="query.keyword" placeholder="模板名称" clearable style="width: 200px" @keyup.enter="doSearch" />
          <el-select v-model="query.type" placeholder="全部类型" clearable style="width: 150px" @change="doSearch">
            <el-option v-for="t in typeOptions" :key="t.code" :label="t.label" :value="t.code" />
          </el-select>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
          <el-button type="success" @click="openForm()">添加定价模板</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="名称" min-width="180" />
        <el-table-column label="类型" width="150">
          <template #default="{ row }">{{ typeLabel(row.type) }}</template>
        </el-table-column>
        <el-table-column label="加价规则" min-width="160">
          <template #default="{ row }">
            <span v-if="Number(row.type) === 3">售价 {{ row.cha_price }}</span>
            <span v-else>+{{ row.cha_price }}{{ Number(row.type) === 1 ? '%' : ' 元' }}</span>
            <span v-if="Number(row.start_price) > 0" class="muted">（面值 {{ row.start_price }}~{{ row.end_price }}）</span>
          </template>
        </el-table-column>
        <el-table-column label="等级价格档" min-width="140">
          <template #default="{ row }">{{ priceArrText(row.price_arr) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="权重" width="80" />
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确认删除该模板？" @confirm="doDelete(row.id)">
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

    <el-dialog v-model="formVisible" :title="form.id ? '编辑定价模板' : '添加定价模板'" width="640px" top="6vh">
      <el-form label-width="110px">
        <el-form-item label="名称" required><el-input v-model="form.name" maxlength="100" style="width: 320px" /></el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type" style="width: 220px" @change="form.cha_price = 0">
            <el-option v-for="t in typeOptions" :key="t.code" :label="t.label" :value="t.code" />
          </el-select>
        </el-form-item>
        <el-form-item :label="ruleLabel">
          <el-input-number v-model="form.cha_price" :min="0" :precision="Number(form.type) === 3 ? 2 : 2" style="width: 180px" />
        </el-form-item>
        <el-form-item label="适用面值区间">
          <el-input-number v-model="form.start_price" :min="0" :precision="2" style="width: 140px" />
          <span style="margin: 0 6px">~</span>
          <el-input-number v-model="form.end_price" :min="0" :precision="2" style="width: 140px" />
        </el-form-item>
        <el-form-item label="等级价格档">
          <div style="width: 100%">
            <div v-for="(item, i) in form.priceRows" :key="i" class="tpl-row">
              <span class="muted">等级</span>
              <el-input-number v-model="item.level" :min="1" style="width: 100px" />
              <span class="muted">售价</span>
              <el-input-number v-model="item.price" :min="0" :precision="2" style="width: 140px" />
              <el-button link type="danger" @click="form.priceRows.splice(i, 1)">删除</el-button>
            </div>
            <el-button size="small" plain @click="form.priceRows.push({ level: 1, price: 0 })">添加等级价格</el-button>
          </div>
        </el-form-item>
        <el-form-item label="权重"><el-input-number v-model="form.sort" :min="0" style="width: 160px" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { priceReadyIndex, priceReadyCreate, priceReadySave, priceReadyDelete, priceReadyTypeList, priceReadyModify } from '@/api';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const typeOptions = ref([]);
const query = reactive({ page: 1, limit: 15, keyword: '', type: null });
const formVisible = ref(false);
const form = reactive({ id: 0, name: '', type: 1, cha_price: 0, start_price: 0, end_price: 0, sort: 100, status: 1, priceRows: [] });

const ruleLabel = computed(() => ({ 1: '加价比例（%）', 2: '固定加价（元）', 3: '固定售价（元）' }[Number(form.type)] || '加价规则'));
const typeLabel = (t) => typeOptions.value.find((x) => Number(x.code) === Number(t))?.label || t;
const priceArrText = (arr) => {
  let o = arr;
  if (typeof arr === 'string') { try { o = JSON.parse(arr || '{}'); } catch (e) { o = {}; } }
  if (!o || typeof o !== 'object') return '—';
  const keys = Object.keys(o);
  return keys.length ? keys.map((k) => `L${k}=${o[k]}`).join(' ') : '—';
};

function buildWhere() {
  const where = [];
  if (query.keyword) where.push({ field: 'name', op: 'like', value: query.keyword });
  if (query.type !== null && query.type !== '') where.push({ field: 'type', op: 'eq', value: Number(query.type) });
  return JSON.stringify(where);
}

async function load() {
  loading.value = true;
  try {
    const res = await priceReadyIndex({ page: query.page, limit: query.limit, where: buildWhere() });
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
  query.type = null;
  doSearch();
}

async function openForm(row) {
  form.id = row ? row.id : 0;
  form.name = row ? row.name : '';
  form.type = row ? Number(row.type) : 1;
  form.cha_price = row ? Number(row.cha_price) : 0;
  form.start_price = row ? Number(row.start_price) : 0;
  form.end_price = row ? Number(row.end_price) : 0;
  form.sort = row ? Number(row.sort) : 100;
  form.status = row ? Number(row.status) : 1;
  let arr = row ? row.price_arr : {};
  if (typeof arr === 'string') { try { arr = JSON.parse(arr || '{}'); } catch (e) { arr = {}; } }
  form.priceRows = Object.keys(arr || {}).map((k) => ({ level: Number(k), price: Number(arr[k]) }));
  formVisible.value = true;
}

async function doSave() {
  if (!form.name) return ElMessage.warning('请填写模板名称');
  saving.value = true;
  try {
    const priceArr = {};
    for (const r of form.priceRows) {
      if (Number(r.level) > 0) priceArr[String(r.level)] = String(r.price);
    }
    const payload = {
      name: form.name, type: form.type, cha_price: String(form.cha_price),
      start_price: String(form.start_price), end_price: String(form.end_price),
      price_arr: JSON.stringify(priceArr), sort: form.sort, status: form.status,
    };
    if (form.id) await priceReadySave(form.id, payload);
    else await priceReadyCreate(payload);
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await priceReadyModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await priceReadyDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(async () => {
  const t = await priceReadyTypeList();
  typeOptions.value = t.data || [];
  load();
});
</script>

<style scoped>
.tpl-row { display: flex; align-items: center; margin-bottom: 8px; gap: 4px; }
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.muted { color: #909399; font-size: 12px; }
</style>
