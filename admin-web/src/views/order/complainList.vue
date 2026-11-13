<template>
  <div class="page-card">
    <el-card shadow="never">
      <!-- 状态页签（对齐 KASUSHOU：所有/自营/商家/渠道售后 → 按 status+来源双维） -->
      <el-tabs v-model="query.statusTab" @tab-change="doSearch">
        <el-tab-pane label="所有售后" name="" />
        <el-tab-pane label="待处理" name="1" />
        <el-tab-pane label="已处理" name="2" />
        <el-tab-pane label="已撤销" name="3" />
        <el-tab-pane label="自营售后" name="s1" />
        <el-tab-pane label="商家售后" name="s2" />
        <el-tab-pane label="渠道售后" name="s3" />
      </el-tabs>

      <el-form inline size="default" class="filter-form" @submit.prevent>
        <el-form-item label="搜索"><el-input v-model="query.keyword" placeholder="订单号/主题/用户" clearable style="width: 180px" @keyup.enter="doSearch" /></el-form-item>
        <el-form-item label="售后类型">
          <el-select v-model="query.type_id" clearable style="width: 140px" @change="doSearch">
            <el-option v-for="t in typeOptions" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker v-model="query.create_range" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss"
            start-placeholder="开始" end-placeholder="结束" style="width: 330px" @change="doSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
          <el-button plain @click="doExport">售后记录导出</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border v-loading="loading">
        <el-table-column label="售后状态" width="100">
          <template #default="{ row }">
            <el-tag :type="{ 1: 'warning', 2: 'success', 3: 'info' }[row.status]" size="small">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单状态" width="100">
          <template #default="{ row }">{{ orderStatusText(row.order_status) }}</template>
        </el-table-column>
        <el-table-column prop="subject" label="售后主题" min-width="150" show-overflow-tooltip />
        <el-table-column prop="ordersn" label="订单号" width="180" show-overflow-tooltip />
        <el-table-column label="商品信息" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.goods_name }}（{{ row.order_total_price }}）</template>
        </el-table-column>
        <el-table-column prop="nickname" label="用户信息" width="120" show-overflow-tooltip />
        <el-table-column label="来源" width="90">
          <template #default="{ row }">{{ row.supplier_type_text }}</template>
        </el-table-column>
        <el-table-column label="售后时间" width="160">
          <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
            <el-button v-if="Number(row.status) === 1" link type="success" @click="openHandle(row)">处理售后</el-button>
            <el-popconfirm v-if="Number(row.status) === 1" title="撤销售后并退款？" width="200" @confirm="doCancel(row)">
              <template #reference><el-button link type="warning">撤销订单</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
      </div>
    </el-card>

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="售后详情" size="520px">
      <el-descriptions v-if="detail.complain" :column="1" border size="small">
        <el-descriptions-item label="售后主题">{{ detail.complain.subject }}</el-descriptions-item>
        <el-descriptions-item label="订单号">{{ detail.complain.ordersn }}</el-descriptions-item>
        <el-descriptions-item label="商品">{{ detail.complain.goods_name }}</el-descriptions-item>
        <el-descriptions-item label="金额">{{ detail.complain.total_price }}</el-descriptions-item>
        <el-descriptions-item label="用户">{{ detail.complain.nickname }}（#{{ detail.complain.uid }}）</el-descriptions-item>
        <el-descriptions-item label="来源">{{ detail.complain.supplier_type_text }}</el-descriptions-item>
        <el-descriptions-item label="投诉内容">{{ detail.complain.content || '—' }}</el-descriptions-item>
        <el-descriptions-item label="售后时间">{{ formatTime(detail.complain.create_time) }}</el-descriptions-item>
        <el-descriptions-item label="订单状态">{{ orderStatusText(detail.order ? detail.order.status : -9) }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>

    <!-- 处理售后弹窗 -->
    <el-dialog v-model="handleVisible" title="处理售后" width="520px">
      <el-form label-width="90px">
        <el-form-item label="处理结果">
          <el-radio-group v-model="handleForm.result">
            <el-radio value="agree">同意退款</el-radio>
            <el-radio value="refuse">拒绝（仅关闭）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="handleForm.result === 'agree'" label="退款金额">
          <el-input-number v-model="handleForm.money" :min="0" :precision="4" style="width: 200px" />
          <span class="muted" style="margin-left: 8px">实付 {{ handleForm.total }}</span>
        </el-form-item>
        <el-form-item label="处理备注"><el-input v-model="handleForm.mark" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="handleVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doHandle">确认处理</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { complainIndex, complainRead, complainDual, complainTypeList, exportCsv } from '@/api';

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const typeOptions = ref([]);
const detailVisible = ref(false);
const detail = ref({ complain: null, order: null });
const handleVisible = ref(false);
const saving = ref(false);
const handleForm = reactive({ id: 0, result: 'agree', money: 0, total: '0', mark: '' });

const query = reactive({ page: 1, limit: 15, statusTab: '', keyword: '', type_id: null, create_range: null });

const orderStatusText = (s) => ({
  [-1]: '取消支付', 0: '等待支付', 1: '等待处理', 2: '正在处理', 3: '交易成功', 4: '取消交易', 5: '已经退款', [-9]: '—',
}[s] || '—');
const formatTime = (ts) => (ts > 0 ? new Date(ts * 1000).toLocaleString('zh-CN') : '—');

function buildWhere() {
  const where = [];
  const t = query.statusTab;
  if (['1', '2', '3'].includes(t)) {
    where.push({ field: 'status', op: 'eq', value: Number(t) });
  } else if (['s1', 's2', 's3'].includes(t)) {
    where.push({ field: 'supplier_type', op: 'eq', value: Number(t.slice(1)) });
  }
  if (query.keyword) {
    const f = /^\d+$/.test(query.keyword) ? 'ordersn' : 'subject';
    where.push({ field: f, op: 'like', value: query.keyword });
  }
  if (query.type_id !== null && query.type_id !== '') where.push({ field: 'type_id', op: 'eq', value: Number(query.type_id) });
  if (Array.isArray(query.create_range) && query.create_range.length === 2) {
    const ts = (v) => Math.floor(new Date(v).getTime() / 1000);
    where.push({ field: 'create_time', op: 'between', value: [ts(query.create_range[0]), ts(query.create_range[1])] });
  }
  return JSON.stringify(where);
}

async function load() {
  loading.value = true;
  try {
    const res = await complainIndex({ page: query.page, limit: query.limit, where: buildWhere() });
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
  Object.assign(query, { page: 1, statusTab: '', keyword: '', type_id: null, create_range: null });
  load();
}

async function doExport() {
  await exportCsv('/admin/complain/export', { where: buildWhere() }, `complain_${Date.now()}.csv`);
  ElMessage.success('导出完成');
}

async function openDetail(row) {
  const res = await complainRead(row.id);
  detail.value = { complain: res.data.complain || row, order: res.data.order || null };
  detailVisible.value = true;
}

function openHandle(row) {
  Object.assign(handleForm, { id: row.id, result: 'agree', money: Number(row.total_price) || 0, total: row.order_total_price, mark: '' });
  handleVisible.value = true;
}

async function doHandle() {
  saving.value = true;
  try {
    const res = await complainDual(handleForm.id, {
      action: 'handle',
      result: handleForm.result,
      money: String(handleForm.money),
      mark: handleForm.mark,
    });
    ElMessage.success(res.msg || '处理完成');
    handleVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doCancel(row) {
  const res = await complainDual(row.id, { action: 'cancel', mark: '后台撤销' });
  ElMessage.success(res.msg || '已撤销');
  load();
}

onMounted(async () => {
  try {
    const t = await complainTypeList();
    typeOptions.value = t.data || [];
  } catch (e) { /* 类型下拉可选 */ }
  load();
});
</script>

<style scoped>
.filter-form :deep(.el-form-item) { margin-bottom: 8px; }
.muted { color: #909399; font-size: 12px; }
</style>
