<template>
  <mks-page>
    <template #alert>
      对接渠道用于自动发货与库存同步，「同步库存」会按渠道商品覆盖本地卡库；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/cardstock')">卡密库存</a>·
      <a class="quick-link" @click="goCfg('/finance/dockingday')">渠道日结</a>
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openForm()">新增渠道</el-button>
      <el-button size="small" type="warning" plain @click="doSyncStock(0)">全部渠道同步库存</el-button>
      <el-button size="small" plain @click="load">刷新</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有对接渠道" hint="新增渠道并启用后，才能自动发货与同步库存" /></template>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="渠道名称" min-width="160" />
        <el-table-column prop="type" label="协议" width="110" />
        <el-table-column prop="url" label="接口地址" min-width="200" show-overflow-tooltip />
        <el-table-column label="余额" width="100">
          <template #default="{ row }">{{ row.balance }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0"
              @change="(v) => doModify(row, 'status', v)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button link type="success" @click="doMoney(row)">查余额</el-button>
            <el-button link type="warning" @click="doSyncStock(row.id)">同步库存</el-button>
            <el-button link type="primary" @click="openGoods(row)">渠道商品</el-button>
            <el-button link type="primary" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确定删除该渠道？" @confirm="doDelete(row.id)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </mks-page>

    <!-- 渠道表单 -->
    <el-dialog v-model="formVisible" :title="form.id ? '编辑渠道' : '新增渠道'" width="560px">
      <el-form :model="form" label-width="110px">
        <el-form-item label="渠道名称" required><el-input v-model="form.name" maxlength="200" /></el-form-item>
        <el-form-item label="对接协议">
          <el-select v-model="form.type" style="width: 100%">
            <el-option label="KASUSHOU v2api" value="kasushou_v2" />
          </el-select>
        </el-form-item>
        <el-form-item label="接口地址" required><el-input v-model="form.url" placeholder="https://上游域名（不带 /api/v1）" /></el-form-item>
        <el-form-item label="APPID(UserId)"><el-input v-model="form.username" placeholder="用户接口 appid" /></el-form-item>
        <el-form-item label="APIKEY"><el-input v-model="form.password" show-password placeholder="用于签名的 apikey" /></el-form-item>
        <el-form-item label="余额预警">
          <el-switch v-model="form.warn_open" :active-value="1" :inactive-value="0" />
          <el-input-number v-if="form.warn_open" v-model="form.warn" :min="0" style="margin-left: 8px" />
        </el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 渠道商品对接 -->
    <el-dialog v-model="goodsVisible" :title="`渠道商品对接 — ${current?.name || ''}`" width="760px">
      <el-alert type="info" :closable="false" title="选择渠道商品 → 绑定本地商品（本地商品按 渠道成本×加价 定价，发货自动转渠道）"
        style="margin-bottom: 10px" />
      <div class="toolbar" style="margin-bottom: 10px">
        <el-input v-model="goodsKeyword" placeholder="商品名搜索" clearable style="width: 200px" @keyup.enter="loadGoods" />
        <el-button type="primary" @click="loadGoods">查询</el-button>
      </div>
      <el-table :data="goodsList" border v-loading="goodsLoading" max-height="360">
        <el-table-column prop="id" label="渠道商品ID" width="110" />
        <el-table-column prop="goods_name" label="名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="面值/进价" width="140">
          <template #default="{ row }">{{ row.face_value }} / <b style="color: #f56c6c">{{ row.goods_price }}</b></template>
        </el-table-column>
        <el-table-column prop="stock_num" label="库存" width="90" />
        <el-table-column label="绑定本地商品" min-width="200">
          <template #default="{ row }">
            <el-select v-model="row._localGoodsId" filterable clearable placeholder="选择本地商品" style="width: 100%" size="small">
              <el-option v-for="g in localGoods" :key="g.id" :label="`${g.goods_name} (#${g.id})`" :value="g.id" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button link type="success" :disabled="!row._localGoodsId" @click="doDual(row)">对接</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
  dockingIndex, dockingCreate, dockingSave, dockingDelete,
  dockingUserMoney, dockingGoodsList, dockingGoodsDual, goodsIndex,
} from '@/api';
import { dockingSyncStock } from '@/api/m3';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const formVisible = ref(false);
const emptyForm = () => ({
  id: 0, name: '', type: 'kasushou_v2', url: '', username: '', password: '',
  warn: 0, warn_open: 1, sort: 100, status: 1,
});
const form = reactive(emptyForm());
const current = ref(null);

// 渠道商品
const goodsVisible = ref(false);
const goodsLoading = ref(false);
const goodsList = ref([]);
const goodsKeyword = ref('');
const localGoods = ref([]);

async function load() {
  loading.value = true;
  try {
    const res = await dockingIndex({ page: 1, limit: 50 });
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
}

function openForm(row) {
  Object.assign(form, emptyForm());
  if (row) Object.assign(form, { ...row, username: '', password: '' });
  formVisible.value = true;
}

async function doSave() {
  if (!form.name || !form.url) return ElMessage.warning('请填写名称与接口地址');
  saving.value = true;
  try {
    if (form.id) {
      await dockingSave(form.id, { ...form });
    } else {
      const { id, ...payload } = form;
      await dockingCreate(payload);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
}

async function doModify(row, field, value) {
  await dockingSave(row.id, { [field]: value });
  ElMessage.success('已更新');
}

async function doDelete(id) {
  await dockingDelete(id);
  ElMessage.success('已删除');
  load();
}

async function doMoney(row) {
  const res = await dockingUserMoney(row.id);
  row.balance = res.data.money;
  ElMessage.success(`渠道余额：${res.data.money}`);
}

async function doSyncStock(dockingId) {
  const res = await dockingSyncStock(dockingId);
  ElMessage.success(res.msg || '同步完成');
}

async function openGoods(row) {
  current.value = row;
  goodsVisible.value = true;
  goodsKeyword.value = '';
  const local = await goodsIndex({ page: 1, limit: 200 });
  localGoods.value = (local.data.list || []).filter((g) => Number(g.goods_type) !== 3);
  loadGoods();
}

async function loadGoods() {
  goodsLoading.value = true;
  try {
    const res = await dockingGoodsList(current.value.id, 1, goodsKeyword.value);
    goodsList.value = (res.data.list || []).map((g) => ({ ...g, _localGoodsId: null }));
  } finally {
    goodsLoading.value = false;
  }
}

async function doDual(row) {
  await dockingGoodsDual({
    docking_id: current.value.id,
    goods_id: row._localGoodsId,
    docking_goods_id: row.id,
    docking_goods_name: row.goods_name,
    cost_value: row.goods_price,
  });
  ElMessage.success('已对接，该商品发货将走渠道');
  loadGoods();
}

onMounted(load);
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
