<template>
  <div class="page plain">
    <NavBar :title="article.title ? '详情' : '内容详情'" />
    <div class="wrap">
      <div class="card" v-if="article.title">
        <div style="font-size:18px; font-weight:700; line-height:1.5">{{ article.title }}</div>
        <div class="muted" style="margin-top:6px">{{ fmt(article.create_time) }}</div>
        <div class="rich" v-html="article.content" />
      </div>
      <div v-else-if="!loading" class="empty"><div class="big">📄</div>内容不存在</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import NavBar from '@/components/NavBar.vue';
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
.rich { margin-top: 14px; font-size: 14px; line-height: 1.9; color: #333; }
.rich :deep(img) { max-width: 100%; }
</style>
