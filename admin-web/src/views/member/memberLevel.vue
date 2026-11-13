<template>
  <mks-page>
    <template #alert>
      折扣率语义：0 = 不打折；95 = 95 折（按商品售价百分比）；一级/二级佣金比例按订单实付计算
    </template>

    <template #toolbar>
      <el-button size="small" type="success" @click="openCreate">新增等级</el-button>
    </template>

      <el-table :data="list" border v-loading="loading">
        <template #empty><mks-empty text="还没有会员等级" hint="先新增一个等级，用户列表的等级折扣才能生效" /></template>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="等级名称" min-width="120" />
        <el-table-column label="升级门槛(累计消费)" width="150">
          <template #default="{ row }">{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="折扣率%" width="90">
          <template #default="{ row }">{{ row.pay_rate }}</template>
        </el-table-column>
        <el-table-column label="一级佣金%" width="95">
          <template #default="{ row }">{{ row.one_rate }}</template>
        </el-table-column>
        <el-table-column label="二级佣金%" width="95">
          <template #default="{ row }">{{ row.two_rate }}</template>
        </el-table-column>
        <el-table-column label="邀请人数" width="90">
          <template #default="{ row }">{{ row.invite_num }}</template>
        </el-table-column>
        <el-table-column label="默认等级" width="90">
          <template #default="{ row }">
            <el-tag v-if="Number(row.is_default) === 1" type="success" size="small">默认</el-tag>
            <span v-else style="color: #bbb">—</span>
          </template>
        </el-table-column>
        <el-table-column label="自动升级" width="90">
          <template #default="{ row }">
            <el-tag :type="Number(row.is_up) === 1 ? 'warning' : 'info'" size="small">
              {{ Number(row.is_up) === 1 ? '开启' : '关闭' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="说明" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-popconfirm title="确认删除该等级？" width="200" @confirm="doDelete(row)">
              <template #reference><el-button link type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </mks-page>

    <el-dialog v-model="formVisible" :title="form.id ? '编辑等级' : '新增等级'" width="560px">
      <el-form label-width="120px">
        <el-form-item label="等级名称" required><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="升级门槛金额">
          <el-input-number v-model="form.amount" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="折扣率%(pay_rate)">
          <el-input-number v-model="form.pay_rate" :min="0" :max="100" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="一级佣金%">
          <el-input-number v-model="form.one_rate" :min="0" :max="100" style="width: 100%" />
        </el-form-item>
        <el-form-item label="二级佣金%">
          <el-input-number v-model="form.two_rate" :min="0" :max="100" style="width: 100%" />
        </el-form-item>
        <el-form-item label="升级所需邀请人数">
          <el-input-number v-model="form.invite_num" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="默认等级">
          <el-switch v-model="form.is_default" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="自动升级">
          <el-switch v-model="form.is_up" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="允许升级">
          <el-switch v-model="form.can_up" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="等级说明"><el-input v-model="form.description" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { memberLevelList, memberLevelCreate, memberLevelSave, memberLevelDelete } from '../../api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const loading = ref(false);
const list = ref([]);
const formVisible = ref(false);
const saving = ref(false);
const form = ref({});

const money = (v) => `¥${Number(v || 0).toFixed(2)}`;

const load = async () => {
  loading.value = true;
  try {
    const res = await memberLevelList();
    list.value = res.data || [];
  } finally {
    loading.value = false;
  }
};
const openCreate = () => {
  form.value = {
    name: '', amount: 0, pay_rate: 0, one_rate: 0, two_rate: 0, invite_num: 0,
    is_default: 0, is_up: 0, can_up: 1, description: '',
  };
  formVisible.value = true;
};
const openEdit = (row) => {
  form.value = { ...row };
  formVisible.value = true;
};
const doSave = async () => {
  if (!form.value.name) {
    ElMessage.warning('请填写等级名称');
    return;
  }
  saving.value = true;
  try {
    if (form.value.id) {
      await memberLevelSave(form.value.id, form.value);
    } else {
      await memberLevelCreate(form.value);
    }
    ElMessage.success('保存成功');
    formVisible.value = false;
    load();
  } finally {
    saving.value = false;
  }
};
const doDelete = async (row) => {
  await memberLevelDelete(row.id);
  ElMessage.success('已删除');
  load();
};

onMounted(load);
</script>

