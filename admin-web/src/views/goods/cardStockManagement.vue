<template>
  <mks-page>
    <template #alert>
      卡库是本地库存来源：商品绑定卡库后按「提取间隔」自动取卡发货，「导入卡密」按行去重入库；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/list')">商品列表</a>·
      <a class="quick-link" @click="goCfg('/goods/docking')">对接渠道</a>
    </template>

    <template #status>
      <mks-status-tabs v-model="activeTab" :tabs="paneTabs" @update:model-value="onTabChange" />
    </template>

    <!-- ── 卡密库 ── -->
    <template v-if="activeTab === 'cate'">
      <div class="toolbar">
        <el-button size="small" type="success" @click="openCateForm()">新增卡库</el-button>
      </div>
      <el-table :data="cateList" border v-loading="cateLoading">
        <template #empty><mks-empty text="还没有卡库" hint="新增卡库并导入卡密后，商品才能自动发货" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="卡库名称" min-width="180" />
        <el-table-column label="卡密类型" width="110">
          <template #default="{ row }">{{ cardTypeLabel(row.card_type) }}</template>
        </el-table-column>
        <el-table-column label="卡号/卡密别名" width="140">
          <template #default="{ row }">{{ row.card_no_alias }} / {{ row.card_password_alias }}</template>
        </el-table-column>
        <el-table-column prop="card_num" label="库存" width="80">
          <template #default="{ row }">
            <span :style="{ color: row.warn_open && row.card_num <= row.warn ? '#f56c6c' : '' }">{{ row.card_num }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="cost" label="成本" width="90" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0"
              @change="(v) => doCateModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="success" @click="openImport(row)">导入卡密</el-button>
            <el-button link type="primary" @click="openCateForm(row)">编辑</el-button>
            <el-button link type="warning" @click="doClearCate(row)">清空</el-button>
            <el-popconfirm title="确定删除该卡库？" @confirm="doCateDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="cateTotal"
          v-model:page-size="cateQuery.limit" v-model:current-page="cateQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="(v) => { cateQuery.page = 1; loadCate(); }" @current-change="loadCate" />
      </div>
    </template>

    <!-- ── 卡密列表 ── -->
    <template v-else-if="activeTab === 'card'">
      <div class="toolbar">
        <el-select v-model="cardQuery.cateId" placeholder="全部卡库" clearable style="width: 200px" @change="doCardSearch">
          <el-option v-for="c in cateOptions" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
        <el-select v-model="cardQuery.status" placeholder="全部状态" clearable style="width: 120px" @change="doCardSearch">
          <el-option label="待售" :value="1" />
          <el-option label="已售" :value="2" />
          <el-option label="冻结" :value="3" />
        </el-select>
        <el-switch v-model="showFull" active-text="显示明文" @change="loadCards" />
      </div>
      <el-table :data="cardList" border v-loading="cardLoading">
        <template #empty><mks-empty text="还没有卡密" hint="到「卡密库」页签为对应卡库导入卡密" /></template>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="卡号" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.card_no || '—' }}</template>
        </el-table-column>
        <el-table-column label="卡密" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ row.card_password || '—' }}</template>
        </el-table-column>
        <el-table-column label="卡库" width="150">
          <template #default="{ row }">{{ cateName(row.card_cate_id) }}</template>
        </el-table-column>
        <el-table-column prop="cost" label="成本" width="90" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : row.status === 2 ? 'info' : 'warning'">
              {{ ['', '待售', '已售', '冻结'][row.status] || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="入库时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-popconfirm title="确定删除该卡密？" @confirm="doCardDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="cardTotal"
          v-model:page-size="cardQuery.limit" v-model:current-page="cardQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="doCardSearch" @current-change="loadCards" />
      </div>
    </template>

    <!-- ── 导卡历史 ── -->
    <template v-else>
      <el-table :data="historyList" border v-loading="historyLoading">
        <template #empty><mks-empty text="还没有导卡记录" hint="每次导入卡密都会生成一条批次记录" /></template>
        <el-table-column prop="id" label="批次" width="80" />
        <el-table-column prop="card_cate_name" label="卡库" min-width="160" />
        <el-table-column prop="count" label="数量" width="80" />
        <el-table-column prop="cost" label="成本合计" width="110" />
        <el-table-column prop="mark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, sizes, prev, pager, next, jumper" :total="historyTotal"
          v-model:page-size="historyQuery.limit" v-model:current-page="historyQuery.page" :page-sizes="[20, 50, 100, 200, 500, 1000]"
          @size-change="(v) => { historyQuery.page = 1; loadHistory(); }" @current-change="loadHistory" />
      </div>
    </template>
  </mks-page>

    <!-- 卡库表单 -->
    <el-dialog v-model="cateFormVisible" :title="cateForm.id ? '编辑卡库' : '新增卡库'" width="540px">
      <el-form :model="cateForm" label-width="110px">
        <el-form-item label="卡库名称" required><el-input v-model="cateForm.name" maxlength="100" /></el-form-item>
        <el-form-item label="卡密类型" required>
          <el-select v-model="cateForm.card_type" style="width: 100%">
            <el-option label="卡密" :value="1" />
            <el-option label="卡号+卡密" :value="2" />
            <el-option label="固定卡密" :value="3" />
            <el-option label="固定卡号卡密" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="卡号别名"><el-input v-model="cateForm.card_no_alias" placeholder="默认：卡号" /></el-form-item>
        <el-form-item label="卡密别名"><el-input v-model="cateForm.card_password_alias" placeholder="默认：卡密" /></el-form-item>
        <el-form-item label="成本"><el-input-number v-model="cateForm.cost" :min="0" :precision="2" style="width: 100%" /></el-form-item>
        <el-form-item label="库存预警"><el-switch v-model="cateForm.warn_open" :active-value="1" :inactive-value="0" />
          <el-input-number v-if="cateForm.warn_open" v-model="cateForm.warn" :min="0" style="margin-left: 8px" />
        </el-form-item>
        <el-form-item label="提取间隔/次"><el-input-number v-model="cateForm.interval" :min="0" style="width: 120px" />
          <el-input-number v-model="cateForm.frequency" :min="1" style="margin-left: 8px" />
        </el-form-item>
        <el-form-item label="状态"><el-switch v-model="cateForm.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="cateFormVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doCateSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 导入卡密 -->
    <el-dialog v-model="importVisible" :title="`导入卡密 — ${importForm.cateName}`" width="560px">
      <el-alert v-if="importForm.cardType === 1" type="info" :closable="false" title="每行一条卡密" style="margin-bottom: 10px" />
      <el-alert v-else type="info" :closable="false"
        :title="`每行一条：卡号${importForm.split}卡密（分隔符可自定义）`" style="margin-bottom: 10px" />
      <el-form :model="importForm" label-width="90px">
        <el-form-item label="卡密内容" required>
          <el-input v-model="importForm.content" type="textarea" :rows="10" placeholder="每行一条，自动去重" />
        </el-form-item>
        <el-form-item v-if="importForm.cardType !== 1" label="分隔符"><el-input v-model="importForm.split" style="width: 120px" /></el-form-item>
        <el-form-item label="单条成本"><el-input-number v-model="importForm.cost" :min="0" :precision="2" style="width: 100%" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="importForm.mark" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doImport">导入</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  cardIndex, cardCreate, cardImport, cardDelete, cardDeleteAll,
  cardCateIndex, cardCateList, cardCateCreate, cardCateSave, cardCateDelete, cardHistoryIndex,
} from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksStatusTabs from '@/components/mksStatusTabs.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const activeTab = ref('cate');
const paneTabs = [
  { label: '卡密库', value: 'cate' },
  { label: '卡密列表', value: 'card' },
  { label: '导卡历史', value: 'history' },
];
const saving = ref(false);

// ── 卡库 ──
const cateLoading = ref(false);
const cateList = ref([]);
const cateTotal = ref(0);
const cateQuery = reactive({ page: 1, limit: 20 });
const cateOptions = ref([]);
const cateFormVisible = ref(false);
const emptyCate = () => ({
  id: 0, name: '', card_type: 2, card_no_alias: '卡号', card_password_alias: '卡密',
  cost: 0, warn: 0, warn_open: 0, interval: 10, frequency: 3, status: 1,
});
const cateForm = reactive(emptyCate());

// ── 卡密 ──
const cardLoading = ref(false);
const cardList = ref([]);
const cardTotal = ref(0);
const cardQuery = reactive({ page: 1, limit: 20, cateId: '', status: '' });
const showFull = ref(false);

// ── 导入 ──
const importVisible = ref(false);
const importForm = reactive({ cateId: 0, cateName: '', cardType: 1, content: '', split: '----', cost: 0, mark: '' });

// ── 历史 ──
const historyLoading = ref(false);
const historyList = ref([]);
const historyTotal = ref(0);
const historyQuery = reactive({ page: 1, limit: 20 });

const cardTypeLabel = (t) => ({ 1: '卡密', 2: '卡号+卡密', 3: '固定卡密', 4: '固定卡号卡密' }[t] || t);
const cateName = (id) => cateOptions.value.find((c) => c.id === id)?.name || `#${id}`;
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

async function loadCate() {
  cateLoading.value = true;
  try {
    const res = await cardCateIndex({ page: cateQuery.page, limit: cateQuery.limit });
    cateList.value = res.data.list || [];
    cateTotal.value = res.data.count || 0;
  } finally {
    cateLoading.value = false;
  }
}

async function loadCateOptions() {
  const res = await cardCateList();
  cateOptions.value = (res.data || []).filter((c) => c.status === 1);
}

async function loadCards() {
  cardLoading.value = true;
  try {
    const params = { page: cardQuery.page, limit: cardQuery.limit, show_full: showFull.value ? 1 : 0 };
    const where = [];
    if (cardQuery.cateId) where.push({ field: 'card_cate_id', op: 'eq', value: cardQuery.cateId });
    if (cardQuery.status) where.push({ field: 'status', op: 'eq', value: cardQuery.status });
    if (where.length) params.where = JSON.stringify(where);
    const res = await cardIndex(params);
    cardList.value = res.data.list || [];
    cardTotal.value = res.data.count || 0;
  } finally {
    cardLoading.value = false;
  }
}

function doCardSearch() {
  cardQuery.page = 1;
  loadCards();
}

async function loadHistory() {
  historyLoading.value = true;
  try {
    const res = await cardHistoryIndex({ page: historyQuery.page, limit: historyQuery.limit });
    historyList.value = res.data.list || [];
    historyTotal.value = res.data.count || 0;
  } finally {
    historyLoading.value = false;
  }
}

function onTabChange(tab) {
  if (tab === 'cate') loadCate();
  if (tab === 'card') { loadCateOptions(); loadCards(); }
  if (tab === 'history') loadHistory();
}

function openCateForm(row) {
  Object.assign(cateForm, emptyCate());
  if (row) Object.assign(cateForm, row);
  cateFormVisible.value = true;
}

async function doCateSave() {
  if (!cateForm.name) return ElMessage.warning('请填写卡库名称');
  saving.value = true;
  try {
    if (cateForm.id) {
      await cardCateSave(cateForm.id, { ...cateForm });
    } else {
      const { id, ...payload } = cateForm;
      await cardCateCreate(payload);
    }
    ElMessage.success('保存成功');
    cateFormVisible.value = false;
    loadCate();
    loadCateOptions();
  } finally {
    saving.value = false;
  }
}

async function doCateModify(row, field, value) {
  await cardCateSave(row.id, { [field]: value });
  ElMessage.success('已更新');
}

async function doCateDelete(id) {
  await cardCateDelete(id);
  ElMessage.success('已删除');
  loadCate();
  loadCateOptions();
}

async function doClearCate(row) {
  await ElMessageBox.confirm(`将清空卡库「${row.name}」内全部待售卡密（软删），确认？`, '清空卡库', { type: 'warning' });
  await cardDeleteAll({ card_cate_id: row.id });
  ElMessage.success('已清空待售卡密');
  loadCate();
  loadCards();
}

function openImport(row) {
  Object.assign(importForm, {
    cateId: row.id, cateName: row.name, cardType: row.card_type,
    content: '', split: '----', cost: Number(row.cost) || 0, mark: '',
  });
  importVisible.value = true;
}

async function doImport() {
  if (!importForm.content.trim()) return ElMessage.warning('请粘贴卡密内容');
  saving.value = true;
  try {
    const res = await cardImport({
      card_cate_id: importForm.cateId,
      content: importForm.content,
      split: importForm.split,
      cost: importForm.cost,
      mark: importForm.mark,
    });
    ElMessage.success(res.msg || '导入成功');
    importVisible.value = false;
    loadCate();
    loadCateOptions();
  } finally {
    saving.value = false;
  }
}

async function doCardDelete(id) {
  await cardDelete(id);
  ElMessage.success('已删除');
  loadCards();
  loadCate();
}

onMounted(() => {
  loadCate();
  loadCateOptions();
});
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
