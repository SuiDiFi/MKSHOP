<template>
  <div class="d-ad" :style="{ height: (Number(v.height) || 120) + 'px' }" @click="go">
    <img v-if="v.img" :src="v.img" alt="ad" />
    <div v-else class="ad-fallback">广告位（请在后台配置图片）</div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const router = useRouter();
const go = () => {
  if (!v.value.url) return;
  if (v.value.url.startsWith('#')) router.push(v.value.url.slice(1));
  else window.open(v.value.url, '_blank');
};
</script>

<style scoped>
.d-ad { border-radius: 10px; overflow: hidden; cursor: pointer; }
.d-ad img { width: 100%; height: 100%; object-fit: cover; }
.ad-fallback { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(120deg, #e8f3ff, #f0f9eb); color: #909399; font-size: 13px; }
</style>
