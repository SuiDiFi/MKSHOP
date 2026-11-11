<template>
  <el-alert v-if="v.text" :title="v.text" type="warning" show-icon :closable="false" style="border-radius: 8px" />
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
</script>
