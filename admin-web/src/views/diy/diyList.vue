<template>
  <div class="page-card">
    <el-card shadow="never">
      <template #header><b>DIY 页面装修</b></template>
      <el-table :data="pages" v-loading="loading" border>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="title" label="页面" min-width="140" />
        <el-table-column prop="desc" label="描述" min-width="180" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="small">
              {{ Number(row.status) === 1 ? '已发布' : '草稿' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="组件数" width="90">
          <template #default="{ row }">{{ componentCount(row.value) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="170">
          <template #default="{ row }">
            <el-button link type="primary" @click="$router.push(`/diy/editor/${row.id}`)">装修</el-button>
            <el-button link @click="preview">预览</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { diyIndex } from '../../api';

const pages = ref([]);
const loading = ref(false);

const componentCount = (valueJson) => {
  try {
    const arr = JSON.parse(valueJson || '[]');
    return Array.isArray(arr) ? arr.length : 0;
  } catch (e) { return 0; }
};
const preview = () => {
  window.open(`/front-web/#/?diy_preview=1&cb=${Date.now()}`, '_blank');
};

onMounted(async () => {
  loading.value = true;
  try {
    const res = await diyIndex();
    pages.value = res.data?.list || [];
  } finally {
    loading.value = false;
  }
});
</script>
