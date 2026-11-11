<template>
  <div v-if="(v.list || []).length" class="menus-box">
    <a v-for="(m, i) in v.list" :key="i" class="menu-item" :href="m.url || 'javascript:;'" @click.prevent="go(m.url)">
      <div class="menu-icon">
        <img v-if="m.icon && m.icon.startsWith('http')" :src="m.icon" :alt="m.title" />
        <span v-else-if="m.icon">{{ m.icon }}</span>
        <el-icon v-else :size="24" color="#409eff"><Grid /></el-icon>
      </div>
      <div class="menu-title">{{ m.title }}</div>
    </a>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const router = useRouter();
const go = (url) => {
  if (url && url.startsWith('#')) router.push(url.slice(1));
  else if (url) window.open(url, '_blank');
};
</script>

<style scoped>
.menus-box { background: #fff; border-radius: 8px; padding: 14px 8px; display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
.menu-item { display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer; }
.menu-icon { width: 44px; height: 44px; border-radius: 50%; background: #ecf5ff; display: flex; align-items: center; justify-content: center; font-size: 22px; overflow: hidden; }
.menu-icon img { width: 100%; height: 100%; object-fit: cover; }
.menu-title { font-size: 12px; color: #606266; }
@media (max-width: 768px) { .menus-box { grid-template-columns: repeat(4, 1fr); } }
</style>
