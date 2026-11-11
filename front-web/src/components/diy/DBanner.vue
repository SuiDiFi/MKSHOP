<template>
  <el-carousel v-if="(v.list || []).length" :height="height" :interval="4000" arrow="hover" style="border-radius: 8px; overflow: hidden">
    <el-carousel-item v-for="(b, i) in v.list" :key="i">
      <a :href="b.url || 'javascript:;'" @click.prevent="go(b.url)">
        <img :src="b.img" style="width: 100%; height: 100%; object-fit: cover" :alt="'banner' + (i + 1)" />
        <div v-if="!b.img" class="banner-fallback">Banner {{ i + 1 }}</div>
      </a>
    </el-carousel-item>
  </el-carousel>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const height = computed(() => (v.value.height ? v.value.height + 'px' : '280px'));
const router = useRouter();
const go = (url) => {
  if (url && url.startsWith('#')) router.push(url.slice(1));
  else if (url) window.open(url, '_blank');
};
</script>

<style scoped>
.banner-fallback {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #409eff, #67c23a); color: #fff; font-size: 22px; font-weight: 700;
}
</style>
