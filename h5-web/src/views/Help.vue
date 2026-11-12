<template>
  <div class="page plain">
    <NavBar title="帮助中心" />
    <div class="wrap">
      <div class="search card">
        <input v-model="keyword" class="input" placeholder="搜索问题" style="height:38px" @keyup.enter="doSearch" />
      </div>
      <div class="card" v-if="list.length" style="padding: 0 12px">
        <div v-for="n in list" :key="n.id" class="row" @click="$router.push(`/help/${n.id}`)">
          <div class="title">❓ {{ n.title }}</div>
          <div class="muted" style="margin-top:4px">{{ n.summary || '—' }}</div>
        </div>
      </div>
      <div v-else-if="!loading" class="empty"><div class="big">❓</div>暂无帮助内容</div>

      <div class="card" style="display:flex; justify-content:space-between; align-items:center">
        <div>
          <div style="font-weight:600">没有找到答案？</div>
          <div class="muted" style="margin-top:4px">联系在线客服处理</div>
        </div>
        <button class="btn ghost" style="height:36px" @click="$router.push('/member')">联系客服</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import NavBar from '@/components/NavBar.vue';
import { helpList } from '@/api';

const list = ref([]);
const keyword = ref('');
const loading = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const params = { page: 1, limit: 30 };
    if (keyword.value) params.keyword = keyword.value;
    const res = await helpList(params);
    list.value = res.data?.list || [];
  } finally {
    loading.value = false;
  }
};
const doSearch = () => load();
onMounted(load);
</script>

<style scoped>
.search { padding: 8px; }
.row { padding: 14px 0; border-bottom: 1px solid #f6f7f9; }
.row:last-child { border-bottom: 0; }
.title { font-size: 15px; font-weight: 600; }
</style>
