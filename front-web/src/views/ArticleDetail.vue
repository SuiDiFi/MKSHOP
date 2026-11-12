<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 860px">
      <el-card shadow="never" style="margin-top: 14px" v-loading="loading">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <b>{{ article.title || '内容详情' }}</b>
            <el-button link @click="$router.back()">返回</el-button>
          </div>
        </template>
        <div v-if="article.title">
          <div class="meta">{{ fmt(article.create_time) }}</div>
          <div class="article-body" v-html="article.content" />
        </div>
        <el-empty v-else-if="!loading" description="内容不存在" />
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { noticeInfo, helpInfo } from '@/api';

const route = useRoute();
const article = ref({});
const loading = ref(true);

const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(async () => {
  try {
    const api = route.name === 'HelpDetail' ? helpInfo : noticeInfo;
    const res = await api(route.params.id);
    article.value = res.data || {};
  } catch (e) { /* ignore */ } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.meta { color: #c0c4cc; font-size: 12px; margin-bottom: 14px; }
.article-body { line-height: 1.9; font-size: 14px; color: #303133; }
.article-body :deep(img) { max-width: 100%; }
</style>
