<template>
  <div class="header-bar">
    <div class="logo-container">
      <div class="logo">MK</div>
      <div class="title">MKSHOP</div>
    </div>
    <el-scrollbar class="menu-scroll">
      <el-menu :default-active="activePath" mode="horizontal" :ellipsis="false" router class="top-menu">
        <menu-item v-for="node in menus" :key="node.id" :node="node" />
      </el-menu>
    </el-scrollbar>
  </div>
</template>

<script setup>
/**
 * 横向布局顶部菜单（对方 .vab-layout-horizontal：logo 蓝底 + el-menu--horizontal + popup 子菜单）
 */
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useUserStore } from '@/store/user';
import { navMenus, activePathOf } from '@/utils/menu';
import MenuItem from './menu-item.vue';

const route = useRoute();
const user = useUserStore();

// 保留顶级「首页」渠道（之前按 path!=='/' 过滤，导致横向布局少一个入口）
const menus = computed(() => navMenus(user.menus || []));
// 隐藏页（商品新增/编辑）用菜单 activeMenu 归属高亮，否则回落到 menuId 映射
const activePath = computed(() => activePathOf(route, user.menuPathMap));
</script>

<style scoped>
.header-bar {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  height: var(--mks-nav-h);
  background: var(--mks-primary);
}
.logo-container {
  flex: none;
  display: flex;
  align-items: center;
  height: var(--mks-nav-h);
  padding-right: 16px;
  /* 蓝底通栏下不留白块：背景跟随 header-bar，仅 logo 方块用白色（与 vab logo-container-horizontal 一致） */
  background: transparent;
}
.logo-container .logo {
  width: var(--mks-column-icon-w);
  height: var(--mks-nav-h);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: var(--mks-primary);
  background: #fff;
}
.logo-container .title {
  padding-left: 10px;
  font-size: 20px;
  color: #fff;
  white-space: nowrap;
}
.menu-scroll {
  flex: 1;
  min-width: 0;
}
.top-menu {
  border-bottom: none;
  background: transparent;
}
.top-menu :deep(.el-menu-item),
.top-menu :deep(.el-sub-menu__title) {
  height: var(--mks-nav-h);
  color: rgba(255, 255, 255, 0.85);
  background: transparent;
  border-bottom: none;
}
.top-menu :deep(.el-menu-item:hover),
.top-menu :deep(.el-sub-menu__title:hover) {
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
}
.top-menu :deep(.el-menu-item.is-active) {
  color: #fff;
  background: rgba(0, 0, 0, 0.12);
  border-bottom: 2px solid #fff !important;
}
</style>
