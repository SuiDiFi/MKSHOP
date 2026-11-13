<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-select v-model="query.type" placeholder="全部类型" clearable style="width: 130px" @change="doSearch">
            <el-option label="会员密价" :value="1" />
            <el-option label="销售限价" :value="2" />
            <el-option label="商品权限" :value="3" />
          </el-select>
          <el-input v-model="query.keyword" placeholder="模板名称" clearable style="width: 180px" @keyup.enter="doSearch" />
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
          <el-button type="success" @click="openForm()">添加模板</el-button>
        </div>
      </template>

      <el-table :data="list" border v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="模板名称" min-width="160" />
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="幅度/规则" min-width="140">
          <template #default="{ row }">{{ valueText(row) }}</template>
        </el-table-column>
        <el-table-column label="已设商品" width="90">
          <template #default="{ row }">{{ row.goods_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="已设用户" width="90">
          <template #default="{ row }">{{ row.user_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确认删除该模板？绑定关系将一并移除" @confirm="doDelete(row.id)">
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

    <!-- 编辑弹窗：基础信息 + 商品多选 + 用户ID -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑模板' : '添加模板'" width="680px" top="6vh">
      <el-form label-width="90px">
        <el-form-item label="模板名称" required><el-input v-model="form.name" maxlength="100" style="width: 320px" /></el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" style="width: 200px">
            <el-option label="会员密价" :value="1" />
            <el-option label="销售限价" :value="2" />
            <el-option label="商品权限" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.type === 1" label="幅度类型">
          <el-select v-model="form.mode" style="width: 200px">
            <el-option label="按比例加价（%）" :value="1" />
            <el-option label="固定加价（元）" :value="2" />
            <el-option label="固定售价（元）" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.type === 1" label="幅度值">
          <el-input-number v-model="form.value" :min="0" :precision="2" :step="form.mode === 1 ? 1 : 0.5" style="width: 200px" />
          <span class="hint">{{ form.mode === 1 ? '会员价 = 成本/售价 ×（1 + 幅度%）' : '元' }}</span>
        </el-form-item>
        <el-form-item v-if="form.type === 2" label="最低售价">
          <el-input-number v-model="form.value" :min="0" :precision="2" :step="0.5" style="width: 200px" />
          <span class="hint">所选用户下单售价不得低于该值</span>
        </el-form-item>
        <el-form-item v-if="form.type === 3" label="规则说明"><span class="hint">所选用户仅可看到并购买所选商品（白名单）</span></el-form-item>
        <el-form-item label="绑定商品">
          <el-select v-model="form.goods_ids" multiple filterable remote clearable
            :remote-method="searchGoods" :loading="goodsLoading" placeholder="输入商品名搜索（可多选）"
            style="width: 100%" class="goods-select">
            <el-option v-for="g in goodsOptions" :key="g.id" :label="`${g.id}·${g.name}`" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="绑定用户">
          <el-input v-model="userIdsInput" type="textarea" :rows="2" placeholder="用户ID，逗号分隔（如 3,7,12）" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { userPriceTplIndex, userPriceTplRead, userPriceTplCreate, userPriceTplSave, userPriceTplDelete, userPriceTplModify, goodsIndex } from '@/api';

const TYPE_LABELS = { 1: '会员密价', 2: '销售限价', 3: '商品权限' };
const MODE_LABELS = { 1: '按比例加价', 2: '固定加价', 3: '固定售价' };

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const query = reactive({ page: 1, limit: 15, type: null, keyword: '' });

const formVisible = ref(false);
const form = reactive({ id: 0, name: '', type: 1, mode: 1, value: 0, status: 1, goods_ids: [], user_ids: [] });
const userIdsInput = ref('');
const goodsOptions = ref([]);
const goodsLoading = ref(false);

const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');
const typeLabel = (t) => TYPE_LABELS[t] || '未知';
const typeTag = (t) => ({ 1: 'warning', 2: 'danger', 3: 'success' }[t] || 'info');

function valueText(row) {
  const v = Number(row.value) || 0;
  if (Number(row.type) === 1) {
    if (Number(row.mode) === 1) return `${v}% 加价`;
    if (Number(row.mode) === 2) return `+${v} 元`;
    if (Number(row.mode) === 3) return `= ${v} 元`;
  }
  if (Number(row.type) === 2) return `最低 ${v} 元`;
  return '按绑定生效';
}

function buildWhere() {
  const where = [];
  if (query.keyword) where.push({ field: 'name', op: 'like', value: query.keyword });
  if (query.type) where.push({ field: 'type', op: 'eq', value: query.type });
  return JSON.stringify(where);
}

async function load() {
  loading.value = true;
  try {
    const res = await userPriceTplIndex({ page: query.page, limit: query.limit, where: buildWhere() });
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
  query.type = null;
  query.keyword = '';
  doSearch();
}

async function searchGoods(kw) {
  goodsLoading.value = true;
  try {
    const where = kw ? JSON.stringify([{ field: 'goods_name', op: 'like', value: kw }]) : '';
    const res = await goodsIndex({ page: 1, limit: 50, where });
    goodsOptions.value = res.data.list || [];
  } finally {
    goodsLoading.value = false;
  }
}

async function openForm(row) {
  form.id = row ? row.id : 0;
  form.name = row ? row.name : '';
  form.type = row ? Number(row.type) : 1;
  form.mode = row ? Number(row.mode) : 1;
  form.value = row ? Number(row.value) : 0;
  form.status = row ? Number(row.status) : 1;
  form.goods_ids = [];
  form.user_ids = [];
  userIdsInput.value = '';
  formVisible.value = true;
  searchGoods('');
  if (row) {
    const res = await userPriceTplRead(row.id);
    const detail = res.data || {};
    form.goods_ids = (detail.goods_ids || []).map(Number);
    form.user_ids = (detail.user_ids || []).map(Number);
    userIdsInput.value = form.user_ids.join(',');
    if (form.goods_ids.length) {
      const known = new Set(goodsOptions.value.map((g) => g.id));
      const missing = form.goods_ids.filter((id) => !known.has(id));
      if (missing.length) {
        const res2 = await goodsIndex({ page: 1, limit: 100, where: JSON.stringify([{ field: 'id', op: 'in', value: missing }]) });
        goodsOptions.value = [...(res2.data.list || []), ...goodsOptions.value];
      }
    }
  }
}

async function doSave() {
  if (!form.name) return ElMessage.warning('请填写模板名称');
  const userIds = userIdsInput.value.split(/[,，\s]+/).map((x) => parseInt(x, 10)).filter((x) => x > 0);
  saving.value = true;
  try {
    const payload = { name: form.name, type: form.type, mode: form.mode, value: form.value, status: form.status, goods_ids: form.goods_ids, user_ids: userIds };
    if (form.id) await userPriceTplSave(form.id, payload);
    else await userPriceTplCreate(payload);
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await userPriceTplModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await userPriceTplDelete(id);
  ElMessage.success('已删除');
  load();
}

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.hint { color: #999; font-size: 12px; margin-left: 8px; }
.goods-select { max-width: 480px; }
</style>
