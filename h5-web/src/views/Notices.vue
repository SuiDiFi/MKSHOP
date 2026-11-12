<template>
  <div class="page plain">
    <NavBar title="商城公告" />
    <div class="wrap">
      <div class="card" v-if="list.length" style="padding: 0 12px">
        <div v-for="n in list" :key="n.id" class="row" @click="$router.push(`/notice/${n.id}`)">
          <div class="title">
            <span v-if="n.is_top" class="tag red" style="margin-right:6px">置顶</span>{{ n.title }}
          </div>
          <div class="muted" style="margin-top:4px">{{ n.summary || '—' }}</div>
          <div class="muted" style="margin-top:4px">{{ fmt(n.create_time) }}</div>
        </div>
      </div>
      <div v-else-if="!loading" class="empty"><div class="big">📢</div>暂无公告</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import NavBar from '@/components/NavBar.vue';
import { noticeIndex } from '@/api';

const list = ref([]);
const loading = ref(false);
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(async () => {
  loading.value = true;
  try {
    const res = await noticeIndex({ page: 1, limit: 30 });
    list.value = res.data?.list || [];
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.row { padding: 14px 0; border-bottom: 1px solid #f6f7f9; }
.row:last-child { border-bottom: 0; }
.title { font-size: 15px; font-weight: 600; }
</style>
