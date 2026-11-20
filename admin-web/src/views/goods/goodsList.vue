<template>
  <mks-page>
    <!-- 引导条（对齐对方商品列表） -->
    <template #alert>
      商品上架前请先配好分类与卡库；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/category')">商品分类</a>·
      <a class="quick-link" @click="goCfg('/goods/cardstock')">卡密库存</a>·
      <a class="quick-link" @click="goCfg('/goods/goodstpl')">商品模板</a>
    </template>

    <!-- 筛选区：默认 5 项，其余折叠 -->
    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="关键字"><el-input v-model="query.keyword" placeholder="商品名称/编号" clearable style="width: 190px" @keyup.enter="doSearch" /></el-form-item>
          <el-form-item label="状态">
            <el-select v-model="query.status" placeholder="全部状态" clearable style="width: 120px" @change="doSearch">
              <el-option v-for="s in statusOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
            </el-select>
          </el-form-item>
          <el-form-item label="类型">
            <el-select v-model="query.goods_type" placeholder="全部类型" clearable style="width: 110px" @change="doSearch">
              <el-option v-for="s in typeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
            </el-select>
          </el-form-item>
          <el-form-item label="分类">
            <el-tree-select v-model="query.category_id" :data="categoryTree" check-strictly clearable
              node-key="id" :props="{ label: 'name', value: 'id', children: 'children' }"
              placeholder="商品分类" style="width: 150px" @change="doSearch" />
          </el-form-item>
          <el-form-item label="供货商ID"><el-input v-model="query.uid" placeholder="供货商ID" clearable style="width: 90px" @keyup.enter="doSearch" /></el-form-item>
        </template>
        <template #more>
          <el-form-item label="供货方式">
            <el-select v-model="query.provide_type" placeholder="供货方式" clearable style="width: 110px" @change="doSearch">
              <el-option v-for="s in provideOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
            </el-select>
          </el-form-item>
          <el-form-item label="成本价">
            <el-input v-model="query.cost_value_min" placeholder="≥" style="width: 80px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.cost_value_max" placeholder="≤" style="width: 80px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="销售价">
            <el-input v-model="query.real_price_min" placeholder="≥" style="width: 80px" @keyup.enter="doSearch" />
            <span style="margin: 0 4px">~</span>
            <el-input v-model="query.real_price_max" placeholder="≤" style="width: 80px" @keyup.enter="doSearch" />
          </el-form-item>
          <el-form-item label="余额限购">
            <el-select v-model="query.only_balance" placeholder="余额限购" clearable style="width: 120px" @change="doSearch">
              <el-option label="仅余额支付" :value="1" />
              <el-option label="不限" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="销售限价">
            <el-select v-model="query.need_balance" placeholder="销售限价" clearable style="width: 120px" @change="doSearch">
              <el-option label="需要限价" :value="1" />
              <el-option label="不限" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="创建时间">
            <el-date-picker v-model="query.create_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="创建开始" end-placeholder="创建结束" style="width: 330px" @change="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <!-- 状态页签（对齐对方「全部商品(9107) 销售中(8048) …」，带真实数量角标） -->
    <template #status>
      <mks-status-tabs v-model="query.status" :tabs="statusTabDefs" @update:model-value="doSearch" />
    </template>

    <!-- 批量操作（统一左上） -->
    <template #toolbar>
      <el-button size="small" type="primary" @click="goAdd">添加商品</el-button>
      <el-button size="small" :disabled="!selection.length" type="danger" plain @click="batchRecycle">批量移至回收站({{ selection.length }})</el-button>
      <el-button size="small" plain @click="filterToday">今日创建</el-button>
      <el-button size="small" plain @click="doExport">导出</el-button>
      <el-button size="small" :disabled="!selection.length" plain @click="copyGoods">复制参数</el-button>
      <span class="muted" style="margin-left: 8px">已选 {{ selection.length }} 个商品</span>
    </template>

      <!-- 表格（对方商品列表：无边框无斑马、字号 12px） -->
      <el-table :data="list" size="small" v-loading="loading" @selection-change="(s) => (selection = s)">
        <template #empty><mks-empty text="没有符合条件的商品" hint="试试放宽筛选条件，或先添加商品" /></template>
        <el-table-column type="selection" width="42" fixed="left" />
        <el-table-column label="排序" width="72">
          <template #default="{ row }">
            <!-- 行内快捷改值（参考侧 row.xxxEdit 机制：非编辑态不插输入框） -->
            <el-input v-if="row.sortEdit" v-model="row.sortDraft" v-focus size="small" style="width: 58px"
              @keyup.enter="commitInline(row, 'sort')" @blur="commitInline(row, 'sort')" />
            <span v-else class="inline-value" @click="startInline(row, 'sort')">{{ row.sort }}</span>
          </template>
        </el-table-column>
        <el-table-column label="ID" width="92">
          <template #default="{ row }">
            <span>{{ row.id }}</span>
            <el-link underline="never" class="cell-copy" title="复制ID" @click="copyText(row.id)">
              <mks-icon name="file-copy-line" :size="14" />
            </el-link>
          </template>
        </el-table-column>
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
        <el-table-column label="售价 / 库存" width="168">
          <template #default="{ row }">
            <!-- 双段胶囊（对齐对方：dark+light 各 20px 高 12px padding0 5px，库存按 warn_num 预警切色） -->
            <div class="cap-line">
              <template v-if="row.real_priceEdit">
                <el-input v-model="row.real_priceDraft" v-focus size="small" style="width: 110px"
                  @keyup.enter="commitInline(row, 'real_price')" @blur="commitInline(row, 'real_price')" />
              </template>
              <template v-else>
                <span class="cap-group">
                  <span class="cap cap-dark" @click="startInline(row, 'real_price')">{{ row.real_price }}</span>
                  <span class="cap cap-light" @click="startInline(row, 'real_price')">{{ row.face_value }}</span>
                </span>
                <el-link underline="never" class="cell-edit" title="修改售价" @click="startInline(row, 'real_price')">
                  <mks-icon name="edit-line" :size="14" />
                </el-link>
              </template>
            </div>
            <div class="cap-line">
              <template v-if="row.stock_numEdit">
                <el-input v-model="row.stock_numDraft" v-focus size="small" style="width: 110px"
                  @keyup.enter="commitInline(row, 'stock_num')" @blur="commitInline(row, 'stock_num')" />
              </template>
              <template v-else>
                <span class="cap-group" :class="{ 'is-warn': Number(row.stock_num) <= Number(row.warn_num) }">
                  <span class="cap cap-dark" @click="startInline(row, 'stock_num')">{{ row.stock_num }}</span>
                  <span class="cap cap-light">预警 {{ row.warn_num || 0 }}</span>
                </span>
                <el-link underline="never" class="cell-edit" title="修改库存" @click="startInline(row, 'stock_num')">
                  <mks-icon name="edit-line" :size="14" />
                </el-link>
              </template>
            </div>
          </template>
        </el-table-column>
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
              <el-button link type="primary" @click="goEdit(row)">编辑</el-button>
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
      <template #pager>
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="total"
          v-model:page-size="query.limit" v-model:current-page="query.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="doSearch" @current-change="load" />
      </template>
    </mks-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
  goodsIndex, goodsDelete, goodsDestroy, goodsRestore,
  goodsModify, goodsStatusType, goodsStatusCounts, goodsGoodsType, goodsProvideType, categoryList,
  exportCsv,
} from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksEmpty from '@/components/mksEmpty.vue';
import mksIcon from '@/components/mksIcon.vue';
import { useInlineEdit } from '@/utils/inlineEdit';

const router = useRouter();
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const selection = ref([]);
const statusOptions = ref([]);
const typeOptions = ref([]);
const provideOptions = ref([]);
const categoryTree = ref([]);
const statusCounts = ref({ total: 0, by_status: {}, recycle: 0, stock_warn: 0 });

/** 行内快捷改值 + 行内复制（共享工具：utils/inlineEdit.js，铺开范式） */
const { vFocus, startInline, commitInline, copyText } = useInlineEdit(goodsModify);

/** 状态页签 = 全部 + 字典各项（带真实数量）+ 回收站 */
const statusTabDefs = computed(() => {
  const c = statusCounts.value;
  const out = [{ label: '全部商品', value: '', count: c.total ?? 0 }];
  for (const s of statusOptions.value) {
    // 回收站(8)单独用 recycle 计数（已软删，不在 by_status 里）
    const n = Number(s.code) === 8 ? (c.recycle ?? 0) : (c.by_status?.[s.code] ?? 0);
    if (Number(s.code) === 7) {
      out.push({ label: s.label, value: Number(s.code), count: c.stock_warn ?? 0 });
      continue;
    }
    out.push({ label: s.label, value: Number(s.code), count: n });
  }
  return out;
});

async function loadStatusCounts() {
  try {
    const res = await goodsStatusCounts();
    statusCounts.value = res.data || statusCounts.value;
  } catch (e) {
    /* 角标失败不阻断列表 */
  }
}

/** 引导条快捷入口 */
function goCfg(path) {
  router.push(path);
}

const query = reactive({
  page: 1, limit: 20, keyword: '', status: null, goods_type: null, provide_type: null,
  category_id: null, uid: '', cost_value_min: '', cost_value_max: '',
  real_price_min: '', real_price_max: '', only_balance: null, need_balance: null,
  create_range: null, today: false,
});

/** 新增/编辑走独立页（/goods/add、/goods/edit/:id，同一组件承载） */
function goAdd() {
  router.push('/goods/add');
}
function goEdit(row) {
  router.push(`/goods/edit/${row.id}`);
}

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
  const [st, gt, pt, ct] = await Promise.all([
    goodsStatusType(), goodsGoodsType(), goodsProvideType(), categoryList(),
  ]);
  statusOptions.value = st.data || [];
  typeOptions.value = gt.data || [];
  provideOptions.value = pt.data || [];
  categoryTree.value = ct.data || [];
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
  loadStatusCounts();
});
</script>

<style scoped>
.muted { color: var(--mks-text-placeholder); font-size: var(--mks-font-size-table); }
.quick-link {
  color: var(--mks-primary);
  cursor: pointer;
}
.toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
/* 行内复制/编辑图标与行内值：全局公共样式（styles/main.css「列表页行内交互」段） */
/* 售价/库存双段胶囊（对方实测：20px 高、padding 0 5px、12px、dark+light 拼接圆角、cursor pointer） */
.cap-line {
  display: flex;
  align-items: center;
  line-height: 22px;
}
.cap-group {
  display: inline-flex;
  align-items: center;
}
.cap {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 5px;
  font-size: 12px;
  line-height: 20px;
  cursor: pointer;
}
.cap-dark {
  background: #1890ff;
  color: #fff;
  border-radius: 3px 0 0 3px;
}
.cap-light {
  background: #e8f4ff;
  color: #1890ff;
  border-radius: 0 3px 3px 0;
}
.cap-group.is-warn .cap-dark {
  background: #f56c6c;
}
.cap-group.is-warn .cap-light {
  background: #fef0f0;
  color: #f56c6c;
}
</style>
