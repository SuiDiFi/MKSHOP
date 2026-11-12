<template>
  <header class="navbar">
    <div class="back" @click="goBack">‹</div>
    <div class="title">{{ title }}</div>
    <div class="right"><slot name="right" /></div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router';

defineProps({ title: { type: String, default: '' } });
const router = useRouter();
const goBack = () => {
  if (window.history.length > 1) router.back();
  else router.push('/');
};
</script>
