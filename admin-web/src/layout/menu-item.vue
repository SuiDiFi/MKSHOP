<template>
  <!-- 有子节点：子菜单（图标用 remixicon 名，DB icon 字段原样存 ri-* 名称） -->
  <el-sub-menu v-if="children.length" :index="`M${node.id}`">
    <template #title>
      <mks-icon v-if="node.icon" :name="node.icon" :size="16" class="mi" />
      <span>{{ node.title || node.name }}</span>
    </template>
    <menu-item v-for="child in children" :key="child.id" :node="child" />
  </el-sub-menu>

  <!-- 叶子：菜单项，index 用动态路由登记的绝对路径，配合 el-menu router 直接跳转 -->
  <el-menu-item v-else :index="path">
    <mks-icon v-if="node.icon" :name="node.icon" :size="16" class="mi" />
    <template #title>{{ node.title || node.name }}</template>
  </el-menu-item>
</template>

<script setup>
import { computed } from 'vue';
import { useUserStore } from '@/store/user';
import mksIcon from '@/components/mksIcon.vue';

const props = defineProps({ node: { type: Object, required: true } });
const user = useUserStore();

const children = computed(() => (props.node.children || []).filter((c) => !Number(c.hidden) && c.path));
const path = computed(() => user.menuPathMap[props.node.id] || props.node.path);
</script>

<style scoped>
.mi {
  margin-right: 6px;
  vertical-align: middle;
}
</style>
