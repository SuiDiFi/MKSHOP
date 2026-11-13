<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-input v-model="query.keyword" placeholder="商品名称/编号" clearable style="width: 190px" @keyup.enter="doSearch" />
          <el-select v-model="query.status" placeholder="全部状态" clearable style="width: 120px" @change="doSearch">
            <el-option v-for="s in statusOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
          </el-select>
          <el-select v-model="query.goods_type" placeholder="全部类型" clearable style="width: 110px" @change="doSearch">
            <el-option v-for="s in typeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
          </el-select>
          <el-select v-model="query.provide_type" placeholder="供货方式" clearable style="width: 110px" @change="doSearch">
            <el-option v-for="s in provideOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
          </el-select>
          <el-tree-select v-model="query.category_id" :data="categoryTree" check-strictly clearable
            node-key="id" :props="{ label: 'name', value: 'id', children: 'children' }"
            placeholder="商品分类" style="width: 150px" @change="doSearch" />
          <el-input v-model="query.uid" placeholder="供货商ID" clearable style="width: 90px" @keyup.enter="doSearch" />
          <el-button link type="primary" @click="filterExpanded = !filterExpanded">{{ filterExpanded ? '收起' : '展开' }}</el-button>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
          <el-button type="success" @click="openForm()">添加商品</el-button>
        </div>
        <!-- 展开区：更多筛选项（对齐 KASUSHOU） -->
        <div v-if="filterExpanded" class="toolbar" style="margin-top: 8px">
          <span class="muted">成本价</span>
          <el-input v-model="query.cost_value_min" placeholder="≥" style="width: 80px" @keyup.enter="doSearch" />
          <span class="muted">~</span>
          <el-input v-model="query.cost_value_max" placeholder="≤" style="width: 80px" @keyup.enter="doSearch" />
          <span class="muted">销售价</span>
          <el-input v-model="query.real_price_min" placeholder="≥" style="width: 80px" @keyup.enter="doSearch" />
          <span class="muted">~</span>
          <el-input v-model="query.real_price_max" placeholder="≤" style="width: 80px" @keyup.enter="doSearch" />
          <el-select v-model="query.only_balance" placeholder="余额限购" clearable style="width: 120px" @change="doSearch">
            <el-option label="仅余额支付" :value="1" />
            <el-option label="不限" :value="0" />
          </el-select>
          <el-select v-model="query.need_balance" placeholder="销售限价" clearable style="width: 120px" @change="doSearch">
            <el-option label="需要限价" :value="1" />
            <el-option label="不限" :value="0" />
          </el-select>
          <el-date-picker v-model="query.create_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
            start-placeholder="创建开始" end-placeholder="创建结束" style="width: 330px" @change="doSearch" />
        </div>
      </template>

      <!-- 批量操作（对齐 KASUSHOU：批量回收站/导出/复制参数/今日创建） -->
      <div class="toolbar" style="margin-bottom: 10px">
        <el-button :disabled="!selection.length" type="danger" plain @click="batchRecycle">批量移至回收站({{ selection.length }})</el-button>
        <el-button plain @click="filterToday">今日创建</el-button>
        <el-button plain @click="doExport">导出</el-button>
        <el-button :disabled="!selection.length" plain @click="copyGoods">复制参数</el-button>
        <span class="muted" style="margin-left: 8px">已选 {{ selection.length }} 个商品</span>
      </div>

      <el-table :data="list" border v-loading="loading" @selection-change="(s) => (selection = s)">
        <el-table-column type="selection" width="42" fixed="left" />
        <el-table-column prop="sort" label="排序" width="62" />
        <el-table-column prop="id" label="ID" width="62" />
        <el-table-column label="商品图" width="64">
          <template #default="{ row }">
            <el-image v-if="row.goods_img" :src="row.goods_img" style="width: 36px; height: 36px" fit="cover"
              :preview-src-list="[row.goods_img]" preview-teleported />
            <span v-else class="muted">—</span>
          </template>
        </el-table-column>
        <el-table-column prop="goods_no" label="编号" width="130" show-overflow-tooltip />
        <el-table-column label="商品名称" min-width="210">
          <template #default="{ row }">
            <span :style="{ color: row.goods_name_color || '', fontWeight: row.goods_name_blod_flag ? 700 : 400 }">
              {{ row.goods_name }}
            </span>
            <el-tag v-if="row.ishot" type="danger" size="small" style="margin-left: 4px">热</el-tag>
            <el-tag v-if="row.tags_name" type="info" size="small" style="margin-left: 4px">{{ row.tags_name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="category_names" label="分类" min-width="120" show-overflow-tooltip />
        <el-table-column label="类型/供货" width="120">
          <template #default="{ row }">
            <div>{{ typeLabel(row.goods_type) }}</div>
            <div class="muted">{{ provideLabel(row.provide_type) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="定价成本" width="90">
          <template #default="{ row }">{{ row.cost_value }}</template>
        </el-table-column>
        <el-table-column label="面值/售价" width="130">
          <template #default="{ row }">
            <span style="text-decoration: line-through; color: #999">{{ row.face_value }}</span>
            <b style="margin-left: 4px; color: #f56c6c">{{ row.real_price }}</b>
          </template>
        </el-table-column>
        <el-table-column prop="stock_num" label="库存" width="70" />
        <el-table-column prop="sales_num" label="销量" width="70" />
        <el-table-column label="所属供货商" width="100">
          <template #default="{ row }">{{ Number(row.uid) > 0 ? '#' + row.uid : '平台' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-switch v-if="row.status !== 8" v-model="row.status" :active-value="1" :inactive-value="3"
              @change="(v) => doModify(row, 'status', v)" />
            <el-tag v-else type="info">回收站</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status !== 8">
              <el-button link type="primary" @click="openForm(row)">编辑</el-button>
              <el-popconfirm title="移入回收站？" @confirm="doDelete(row.id)">
                <template #reference><el-button link type="danger">删除</el-button></template>
              </el-popconfirm>
            </template>
            <template v-else>
              <el-button link type="success" @click="doRestore(row.id)">还原</el-button>
              <el-popconfirm title="彻底删除不可恢复！" @confirm="doDestroy(row.id)">
                <template #reference><el-button link type="danger">彻底删除</el-button></template>
              </el-popconfirm>
            </template>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
      </div>
    </el-card>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑商品' : '新增商品'" width="680px" top="5vh">
      <el-form :model="form" label-width="100px">
        <el-row :gutter="12">
          <el-col :span="16"><el-form-item label="商品名称" required><el-input v-model="form.goods_name" maxlength="500" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="商品编号"><el-input v-model="form.goods_no" placeholder="留空自动生成" /></el-form-item></el-col>
          <el-col :span="8">
            <el-form-item label="商品类型" required>
              <el-select v-model="form.goods_type" style="width: 100%">
                <el-option v-for="s in typeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="供货方式">
              <el-select v-model="form.provide_type" style="width: 100%">
                <el-option v-for="s in provideOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="所属分类">
              <el-tree-select v-model="form.category_ids" :data="categoryTree" multiple check-strictly
                node-key="id" :props="{ label: 'name', value: 'id', children: 'children' }"
                placeholder="可多选" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8"><el-form-item label="面值/划线价"><el-input-number v-model="form.face_value" :min="0" :precision="2" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="销售价" required><el-input-number v-model="form.real_price" :min="0" :precision="2" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="成本价"><el-input-number v-model="form.cost_value" :min="0" :precision="2" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="最少购买"><el-input-number v-model="form.start_count" :min="1" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="最多购买"><el-input-number v-model="form.end_count" :min="1" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="数量档位"><el-input v-model="form.num_count" placeholder="逗号分隔，如 1,2,5,10" /></el-form-item></el-col>
          <el-col :span="8">
            <el-form-item label="品牌标签">
              <el-select v-model="form.tags_id" clearable style="width: 100%">
                <el-option v-for="t in tagsOptions" :key="t.id" :label="t.name" :value="t.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="下单模板">
              <el-select v-model="form.tpl_id" clearable style="width: 100%" placeholder="不使用模板">
                <el-option v-for="t in tplOptions" :key="t.id" :label="t.name" :value="t.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8"><el-form-item label="库存预警值"><el-input-number v-model="form.warn_num" :min="0" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="仅余额支付"><el-switch v-model="form.only_balance" :active-value="1" :inactive-value="0" /></el-form-item></el-col>
          <el-col :span="24"><el-form-item label="商品主图"><el-input v-model="form.goods_img" placeholder="图片 URL（M5 接入上传组件）" /></el-form-item></el-col>
          <el-col :span="24"><el-form-item label="商品详情"><el-input v-model="form.goods_info" type="textarea" :rows="4" /></el-form-item></el-col>
          <el-col :span="24"><el-form-item label="购买须知"><el-input v-model="form.goods_notice" type="textarea" :rows="2" /></el-form-item></el-col>
        </el-row>
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
import {
  goodsIndex, goodsRead, goodsCreate, goodsSave, goodsDelete, goodsDestroy, goodsRestore,
  goodsModify, goodsStatusType, goodsGoodsType, goodsProvideType, goodsDefaultTags, categoryList, tagsList,
  goodsTplList, exportCsv,
} from '@/api';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const selection = ref([]);
const statusOptions = ref([]);
const typeOptions = ref([]);
const provideOptions = ref([]);
const tagsOptions = ref([]);
const tplOptions = ref([]);
const categoryTree = ref([]);
const filterExpanded = ref(false);
const query = reactive({
  page: 1, limit: 15, keyword: '', status: null, goods_type: null, provide_type: null,
  category_id: null, uid: '', cost_value_min: '', cost_value_max: '',
  real_price_min: '', real_price_max: '', only_balance: null, need_balance: null,
  create_range: null, today: false,
});
const formVisible = ref(false);
const emptyForm = () => ({
  id: 0, goods_name: '', goods_no: '', goods_type: 1, provide_type: 1, category_ids: [],
  tags_id: 0, tpl_id: 0, face_value: 0, real_price: 0, cost_value: 0, start_count: 1, end_count: 1,
  num_count: '1', warn_num: 0, only_balance: 0, goods_img: '', goods_info: '', goods_notice: '', status: 1,
});
const form = reactive(emptyForm());

function typeLabel(code) {
  const hit = typeOptions.value.find((s) => Number(s.code) === Number(code));
  return hit ? hit.label : code;
}

function provideLabel(code) {
  const hit = provideOptions.value.find((s) => Number(s.code) === Number(code));
  return hit ? hit.label : '';
}

function buildWhere() {
  const where = [];
  if (query.keyword) {
    where.push({ field: /^\d+$/.test(query.keyword) ? 'goods_no' : 'goods_name', op: 'like', value: query.keyword });
  }
  if (query.status !== null && query.status !== '') where.push({ field: 'status', op: 'eq', value: query.status });
  if (query.goods_type !== null && query.goods_type !== '') where.push({ field: 'goods_type', op: 'eq', value: query.goods_type });
  if (query.provide_type !== null && query.provide_type !== '') where.push({ field: 'provide_type', op: 'eq', value: query.provide_type });
  if (query.uid) where.push({ field: 'uid', op: 'eq', value: Number(query.uid) });
  if (query.only_balance !== null && query.only_balance !== '') where.push({ field: 'only_balance', op: 'eq', value: Number(query.only_balance) });
  if (query.need_balance !== null && query.need_balance !== '') where.push({ field: 'need_balance', op: 'eq', value: Number(query.need_balance) });
  if (query.cost_value_min !== '' || query.cost_value_max !== '') {
    where.push({ field: 'cost_value', op: 'between', value: [query.cost_value_min || '0', query.cost_value_max || '99999999'] });
  }
  if (query.real_price_min !== '' || query.real_price_max !== '') {
    where.push({ field: 'real_price', op: 'between', value: [query.real_price_min || '0', query.real_price_max || '99999999'] });
  }
  if (query.today) {
    const dayStart = new Date(); dayStart.setHours(0, 0, 0, 0);
    where.push({ field: 'create_time', op: 'between', value: [Math.floor(dayStart.getTime() / 1000), Math.floor(Date.now() / 1000)] });
  } else if (Array.isArray(query.create_range) && query.create_range.length === 2) {
    const ts = (v) => Math.floor(new Date(v).getTime() / 1000);
    where.push({ field: 'create_time', op: 'between', value: [ts(query.create_range[0]), ts(query.create_range[1])] });
  }
  return JSON.stringify(where);
}

async function load() {
  loading.value = true;
  try {
    const res = await goodsIndex({
      page: query.page, limit: query.limit, where: buildWhere(),
      category_id: query.category_id || 0,
    });
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
  Object.assign(query, {
    page: 1, keyword: '', status: null, goods_type: null, provide_type: null, category_id: null,
    uid: '', cost_value_min: '', cost_value_max: '', real_price_min: '', real_price_max: '',
    only_balance: null, need_balance: null, create_range: null, today: false,
  });
  load();
}

function filterToday() {
  query.today = !query.today;
  doSearch();
}

async function doExport() {
  await exportCsv(
    '/admin/goods/export',
    { where: buildWhere(), category_id: query.category_id || 0 },
    `goods_${Date.now()}.csv`
  );
  ElMessage.success('导出完成（最多 5000 条）');
}

// 复制参数：选中商品的关键参数 JSON
async function copyGoods() {
  const text = JSON.stringify(selection.value.map((r) => ({
    goods_name: r.goods_name, goods_type: r.goods_type, provide_type: r.provide_type,
    category_ids: r.category_ids, face_value: r.face_value, real_price: r.real_price,
    cost_value: r.cost_value, start_count: r.start_count, end_count: r.end_count,
    num_count: r.num_count, warn_num: r.warn_num, only_balance: r.only_balance,
  })), null, 2);
  await navigator.clipboard.writeText(text);
  ElMessage.success(`已复制 ${selection.value.length} 个商品参数`);
}

async function batchRecycle() {
  const ids = selection.value.filter((r) => r.status !== 8).map((r) => r.id);
  if (!ids.length) return ElMessage.warning('选中商品均已在回收站');
  for (const id of ids) {
    try { await goodsDelete(id); } catch (e) { /* 逐条容错 */ }
  }
  ElMessage.success(`已移入回收站 ${ids.length} 个商品`);
  load();
}

async function loadEnums() {
  const [st, gt, pt, tg, ct, tpl] = await Promise.all([
    goodsStatusType(), goodsGoodsType(), goodsProvideType(), goodsDefaultTags(), categoryList(), goodsTplList(),
  ]);
  statusOptions.value = st.data || [];
  typeOptions.value = gt.data || [];
  provideOptions.value = pt.data || [];
  tagsOptions.value = tg.data || [];
  categoryTree.value = ct.data || [];
  tplOptions.value = tpl.data || [];
}

async function openForm(row) {
  Object.assign(form, emptyForm());
  if (row) {
    const res = await goodsRead(row.id);
    const detail = res.data || {};
    Object.assign(form, detail);
    form.category_ids = String(detail.category_ids || '').split(',').filter(Boolean).map(Number);
  }
  formVisible.value = true;
}

async function doSave() {
  if (!form.goods_name) return ElMessage.warning('请填写商品名称');
  saving.value = true;
  try {
    const payload = { ...form };
    payload.category_ids = (form.category_ids || []).join(',');
    if (form.id) {
      await goodsSave(form.id, payload);
    } else {
      delete payload.id;
      await goodsCreate(payload);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await goodsModify(row.id, field, value);
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await goodsDelete(id);
  ElMessage.success('已移入回收站');
  load();
}

async function doRestore(id) {
  await goodsRestore(id);
  ElMessage.success('已还原');
  load();
}

async function doDestroy(id) {
  await goodsDestroy(id);
  ElMessage.success('已彻底删除');
  load();
}

onMounted(async () => {
  await loadEnums();
  load();
});
</script>

<style scoped>
.muted { color: #909399; font-size: 12px; }
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
</style>
