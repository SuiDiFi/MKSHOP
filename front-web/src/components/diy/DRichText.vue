<template>
  <div v-if="v.html" class="rich-box" v-html="v.html" />
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
</script>

<style scoped>
.rich-box { background: #fff; border-radius: 8px; padding: 16px; line-height: 1.7; overflow: hidden; }
.rich-box :deep(img) { max-width: 100%; }
</style>
