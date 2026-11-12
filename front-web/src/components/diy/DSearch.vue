<template>
  <div class="d-search">
    <input v-model="kw" :placeholder="v.placeholder || '搜索商品'" @keyup.enter="go" />
    <button @click="go">搜索</button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const kw = ref('');
const router = useRouter();
const go = () => {
  router.push({ path: '/category', query: kw.value ? { kw: kw.value } : {} });
};
</script>

<style scoped>
.d-search { display: flex; gap: 8px; background: #fff; border-radius: 10px; padding: 10px; }
.d-search input { flex: 1; height: 40px; border: 1px solid #e4e7ed; border-radius: 20px; padding: 0 16px; font-size: 14px; outline: none; }
.d-search input:focus { border-color: #409eff; }
.d-search button { width: 74px; border: 0; border-radius: 20px; background: #409eff; color: #fff; font-size: 14px; cursor: pointer; }
</style>
