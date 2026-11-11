<template>
  <div class="page-card">
    <el-card shadow="never">
      <el-empty :description="`「${route.meta?.title || route.path}」页面排期中（M1+ 里程碑实现）`">
        <template #image><el-icon :size="80" color="#c0c4cc"><Box /></el-icon></template>
        <el-button type="primary" plain @click="$router.push('/')">返回首页</el-button>
      </el-empty>
    </el-card>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
