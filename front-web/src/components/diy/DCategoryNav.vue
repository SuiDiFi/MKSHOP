<template>
  <div v-if="list.length" class="d-catenav">
    <div class="cn-head">{{ v.title || '商品分类' }}</div>
    <div class="cn-list">
      <div v-for="c in list" :key="c.id" class="cn-item" @click="$router.push(`/category?cate=${c.id}`)">
        <span class="cn-icon">📦</span>
        <span class="cn-name">{{ c.name }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { categoryIndex } from '@/api';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const list = ref([]);

onMounted(async () => {
  try {
    const res = await categoryIndex();
    list.value = (res.data?.list || []).slice(0, Math.max(1, Number(v.value.limit) || 8));
  } catch (e) { /* ignore */ }
});
</script>

<style scoped>
.d-catenav { background: #fff; border-radius: 10px; padding: 14px; }
.cn-head { font-size: 15px; font-weight: 700; margin-bottom: 12px; }
.cn-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 10px; }
.cn-item { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 6px; border-radius: 8px; background: #f7f9fc; cursor: pointer; }
.cn-item:hover { background: #ecf5ff; }
.cn-icon { font-size: 20px; }
.cn-name { font-size: 12px; color: #606266; text-align: center; }
</style>
