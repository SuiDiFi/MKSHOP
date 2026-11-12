<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 860px">
      <el-card shadow="never" style="margin-top: 14px">
        <template #header><b>商城公告</b></template>
        <div v-loading="loading">
          <div v-for="n in list" :key="n.id" class="notice-row" @click="$router.push(`/notice/${n.id}`)">
            <div class="notice-title">
              <el-tag v-if="n.is_top" type="danger" size="small" effect="plain" style="margin-right:6px">置顶</el-tag>
              {{ n.title }}
            </div>
            <div class="notice-sub">{{ n.summary || '—' }}</div>
            <div class="notice-time">{{ fmt(n.create_time) }}</div>
          </div>
          <el-empty v-if="!loading && !list.length" description="暂无公告" />
        </div>
        <div class="pager" v-if="total > query.limit">
          <el-pagination background layout="prev, pager, next" :total="total"
            :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { noticeIndex } from '@/api';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 10 });

const load = async () => {
  loading.value = true;
  try {
    const res = await noticeIndex(query.value);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');
onMounted(load);
</script>

<style scoped>
.notice-row { padding: 14px 4px; border-bottom: 1px solid #f2f3f5; cursor: pointer; }
.notice-row:hover .notice-title { color: #409eff; }
.notice-title { font-size: 15px; font-weight: 600; }
.notice-sub { color: #909399; font-size: 13px; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.notice-time { color: #c0c4cc; font-size: 12px; margin-top: 4px; }
</style>
