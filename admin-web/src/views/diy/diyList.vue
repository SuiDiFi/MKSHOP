<template>
  <mks-page>
    <template #alert>
      前台首页按「已发布」DIY 页面的组件树渲染，草稿改动需发布后才生效；分类导航、商品列表、图片广告等组件的数据来自对应模块；快捷入口：
      <a class="quick-link" @click="goCfg('/goods/category')">商品分类</a>·
      <a class="quick-link" @click="goCfg('/manage/advertisement')">图片广告</a>
    </template>

      <el-table :data="pages" v-loading="loading" border>
        <template #empty><mks-empty text="还没有 DIY 页面" hint="进入装修编辑器搭建组件树并发布后，前台首页才有内容" /></template>
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
  </mks-page>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { diyIndex } from '../../api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

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

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
</style>
