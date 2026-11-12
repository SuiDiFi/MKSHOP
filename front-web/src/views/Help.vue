<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 860px">
      <el-card shadow="never" style="margin-top: 14px">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <b>帮助中心</b>
            <el-input v-model="keyword" placeholder="搜索问题" clearable style="width: 220px" @keyup.enter="doSearch">
              <template #append><el-button :icon="Search" @click="doSearch" /></template>
            </el-input>
          </div>
        </template>
        <div v-loading="loading">
          <div v-for="n in list" :key="n.id" class="help-row" @click="$router.push(`/help/${n.id}`)">
            <el-icon color="#409eff"><QuestionFilled /></el-icon>
            <div class="help-main">
              <div class="help-title">{{ n.title }}</div>
              <div class="help-sub">{{ n.summary || '—' }}</div>
            </div>
            <el-icon color="#c0c4cc"><ArrowRight /></el-icon>
          </div>
          <el-empty v-if="!loading && !list.length" description="暂无帮助内容" />
        </div>
        <div class="pager" v-if="total > query.limit">
          <el-pagination background layout="prev, pager, next" :total="total"
            :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
        </div>
      </el-card>

      <el-card shadow="never" style="margin-top: 14px">
        <div style="display:flex; align-items:center; gap:12px; justify-content:space-between">
          <div>
            <div style="font-weight:600">没有找到答案？</div>
            <div style="color:#909399; font-size:13px; margin-top:4px">联系在线客服，我们会尽快为您处理。</div>
          </div>
          <el-button type="primary" @click="$router.push('/kefu')">联系客服</el-button>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Search } from '@element-plus/icons-vue';
import { helpList } from '@/api';

const list = ref([]);
const total = ref(0);
const loading = ref(false);
const keyword = ref('');
const query = ref({ page: 1, limit: 10 });

const load = async () => {
  loading.value = true;
  try {
    const params = { ...query.value };
    if (keyword.value) params.keyword = keyword.value;
    const res = await helpList(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
onMounted(load);
</script>

<style scoped>
.help-row { display: flex; align-items: center; gap: 10px; padding: 14px 4px; border-bottom: 1px solid #f2f3f5; cursor: pointer; }
.help-row:hover .help-title { color: #409eff; }
.help-main { flex: 1; }
.help-title { font-size: 15px; font-weight: 600; }
.help-sub { color: #909399; font-size: 13px; margin-top: 4px; }
</style>
