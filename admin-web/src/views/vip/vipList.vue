<template>
  <mks-page>
    <template #alert>
      分站按「绑定域名 / 二级目录」独立进站，状态停用后前台不可访问，「主站商品」开关决定分站能否展示主站商品，结算冻结填 0 时取全局 order_vip_sure_time；快捷入口：
      <a class="quick-link" @click="goCfg('/member/list')">会员列表</a>·
      <a class="quick-link" @click="goCfg('/member/level')">会员等级</a>
    </template>

    <template #query>
      <mks-query-form :model="query" :loading="loading" @search="doSearch" @reset="resetQuery">
        <template #short>
          <el-form-item label="分站名称">
            <el-input v-model="query.name" placeholder="分站名称" clearable style="width: 180px" @keyup.enter="doSearch" />
          </el-form-item>
        </template>
      </mks-query-form>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openCreate">新建分站</el-button>
    </template>

      <el-table :data="list" v-loading="loading" border>
        <template #empty><mks-empty text="还没有分站" hint="新建分站并绑定域名后，可用独立域名进入商城" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="分站名称" min-width="130" />
        <el-table-column label="分站主" min-width="140">
          <template #default="{ row }">{{ row.owner ? `${row.owner_nickname}（${row.owner}）` : `UID ${row.uid}` }}</template>
        </el-table-column>
        <el-table-column prop="domain" label="绑定域名" min-width="160" show-overflow-tooltip />
        <el-table-column prop="path_prefix" label="二级目录" width="100">
          <template #default="{ row }">{{ row.path_prefix ? '/' + row.path_prefix : '—' }}</template>
        </el-table-column>
        <el-table-column label="主站商品" width="90">
          <template #default="{ row }">{{ Number(row.allow_main_goods) === 1 ? '展示' : '隐藏' }}</template>
        </el-table-column>
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
        <el-table-column label="操作" width="210" fixed="right">
          <template #default="{ row }">
            <el-button link type="success" @click="openDetail(row)">运营</el-button>
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-popconfirm title="确认删除该分站？" width="200" @confirm="doDelete(row)">
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
        <el-form-item label="二级目录">
          <el-input v-model="form.path_prefix" placeholder="如 shop（/shop/ 访问）" />
        </el-form-item>
        <el-form-item label="主站商品">
          <el-switch v-model="form.allow_main_goods" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="结算冻结">
          <el-input-number v-model="form.settle_minutes" :min="0" :max="100000" style="width: 100%" />
          <span class="tip">分钟；0=用全局 order_vip_sure_time</span>
        </el-form-item>
        <el-form-item label="加价率%">
          <el-input-number v-model="form.rate" :min="0" :max="100" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="平台抽成%">
          <el-input-number v-model="form.share_rate" :min="0" :max="100" :precision="2" style="width: 100%" />
          <span class="tip">按分站毛利计算；0=毛利全归分站</span>
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

    <!-- 运营详情 -->
    <vip-detail v-model="detailVisible" :site-id="detailSiteId" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { vipIndex, vipCreate, vipSave, vipDelete } from '../../api';
import VipDetail from './vipDetail.vue';
import mksPage from '@/components/mksPage.vue';
import mksQueryForm from '@/components/mksQueryForm.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 20, name: '' });
const dialogVisible = ref(false);
const saving = ref(false);
const form = ref({});
const detailVisible = ref(false);
const detailSiteId = ref(0);

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
const resetQuery = () => { query.value.name = ''; doSearch(); };
const openCreate = () => {
  form.value = { uid: 10000, name: '', domain: '', path_prefix: '', allow_main_goods: 1, settle_minutes: 0, rate: 0, share_rate: 0, status: 1, mark: '' };
  dialogVisible.value = true;
};
const openDetail = (row) => {
  detailSiteId.value = row.id;
  detailVisible.value = true;
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
.quick-link { color: var(--mks-primary); cursor: pointer; }
.tip { color: #909399; font-size: 12px; margin-left: 6px; }
</style>
