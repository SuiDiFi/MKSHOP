<template>
  <el-sub-menu v-if="node.children && node.children.length > 0" :index="`M${node.id}`">
    <template #title>
      <el-icon v-if="iconOf(node)"><component :is="iconOf(node)" /></el-icon>
      <span>{{ node.title || node.name }}</span>
    </template>
    <menu-item v-for="child in node.children.filter(c => !c.hidden && c.path)" :key="child.id" :node="child" />
  </el-sub-menu>
  <el-menu-item v-else :index="routeIndexOf(node)">
    <el-icon v-if="iconOf(node)"><component :is="iconOf(node)" /></el-icon>
    <template #title>{{ node.title || node.name }}</template>
  </el-menu-item>
</template>

<script setup>
import { useUserStore } from '@/store/user';

const props = defineProps({ node: { type: Object, required: true } });
const user = useUserStore();

function iconOf(node) {
  const name = (node.icon || '').replace(/-line$|-fill$|-bold$/, '');
  return name || null;
}

// 菜单路由 index：注册动态路由时记录的完整路径
function routeIndexOf(node) {
  return user.menuPathMap[node.id] || node.path || null;
}
</script>
