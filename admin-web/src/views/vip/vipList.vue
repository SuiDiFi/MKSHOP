<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header>
        <div class="toolbar">
          <el-input v-model="query.name" placeholder="分站名称" clearable style="width: 180px" @keyup.enter="doSearch" />
          <el-button type="primary" @click="doSearch">查询</el-button>
          <el-button type="success" @click="openCreate">新建分站</el-button>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" border>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="分站名称" min-width="130" />
        <el-table-column label="分站主" min-width="140">
          <template #default="{ row }">{{ row.owner ? `${row.owner_nickname}（${row.owner}）` : `UID ${row.uid}` }}</template>
        </el-table-column>
        <el-table-column prop="domain" label="绑定域名" min-width="160" show-overflow-tooltip />
        <el-table-column label="加价率" width="90">
          <template #default="{ row }">{{ row.rate }}%</template>
        </el-table-column>
        <el-table-column label="抽成率" width="90">
          <template #default="{ row }">{{ row.share_rate }}%</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="small">
              {{ Number(row.status) === 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="到期" width="110">
          <template #default="{ row }">{{ Number(row.expire_time) > 0 ? fmt(row.expire_time) : '永久' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-popconfirm title="确认删除该分站？" width="200" @confirm="doDelete(row)">
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

    <!-- 新建/编辑 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑分站' : '新建分站'" width="480px">
      <el-form label-width="90px">
        <el-form-item v-if="!form.id" label="分站主UID">
          <el-input-number v-model="form.uid" :min="10000" style="width: 100%" />
        </el-form-item>
        <el-form-item label="分站名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="绑定域名">
          <el-input v-model="form.domain" placeholder="如 shop.example.com（留空=未绑定）" />
        </el-form-item>
        <el-form-item label="加价率%">
          <el-input-number v-model="form.rate" :min="0" :max="100" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="抽成率%">
          <el-input-number v-model="form.share_rate" :min="0" :max="100" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">停用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注"><el-input v-model="form.mark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { vipIndex, vipCreate, vipSave, vipDelete } from '../../api';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 15, name: '' });
const dialogVisible = ref(false);
const saving = ref(false);
const form = ref({});

const load = async () => {
  loading.value = true;
  try {
    const res = await vipIndex(query.value);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
const openCreate = () => {
  form.value = { uid: 10000, name: '', domain: '', rate: 0, share_rate: 0, status: 1, mark: '' };
  dialogVisible.value = true;
};
const openEdit = (row) => {
  form.value = { ...row };
  dialogVisible.value = true;
};
const doSave = async () => {
  saving.value = true;
  try {
    if (form.value.id) {
      await vipSave(form.value.id, form.value);
    } else {
      await vipCreate(form.value);
    }
    ElMessage.success('保存成功');
    dialogVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
};
const doDelete = async (row) => {
  await vipDelete(row.id);
  ElMessage.success('删除成功');
  load();
};
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleDateString('zh-CN') : '—');

onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; gap: 8px; align-items: center; }
.pager { display: flex; justify-content: flex-end; margin-top: 12px; }
</style>
