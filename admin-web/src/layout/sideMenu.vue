<template>
  <aside class="side-bar" :class="{ 'is-collapse': collapse, 'is-deep': isDeep }">
    <div class="logo-container">
      <div class="logo">MK</div>
      <div v-show="!collapse" class="title">MKSHOP</div>
    </div>
    <el-scrollbar class="menu-scroll">
      <el-menu
        :default-active="activePath"
        :collapse="collapse"
        :collapse-transition="false"
        :unique-opened="true"
        router
        class="side-menu"
      >
        <menu-item v-for="node in menus" :key="node.id" :node="node" />
      </el-menu>
    </el-scrollbar>
  </aside>
</template>

<script setup>
/**
 * 纵向布局侧栏（266px / 折叠 64px，菜单项 50px；配色由主题变量驱动）
 */
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useUserStore } from '@/store/user';
import { useSettingsStore } from '@/store/settings';
import { navMenus, activePathOf } from '@/utils/menu';
import MenuItem from './menu-item.vue';

const route = useRoute();
const user = useUserStore();
const settings = useSettingsStore();

const collapse = computed(() => settings.collapse);
const isDeep = computed(() => settings.theme.endsWith('-black'));
// 用 navMenus 保留顶级「首页」渠道（之前按 path!=='/' 过滤会丢掉首页入口）
const menus = computed(() => navMenus(user.menus || []));
// 隐藏页（商品新增/编辑）用菜单 activeMenu 归属高亮，否则回落到 menuId 映射
const activePath = computed(() => activePathOf(route, user.menuPathMap));
</script>

<style scoped>
.side-bar {
  flex: none;
  width: var(--mks-side-w);
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--mks-bg-side);
  box-shadow: var(--mks-shadow-side);
  z-index: var(--mks-z-side);
  transition: width 0.3s cubic-bezier(0.645, 0.045, 0.355, 1);
}
.side-bar.is-collapse {
  width: var(--mks-side-collapse-w);
}

.logo-container {
  flex: none;
  display: flex;
  align-items: center;
  height: var(--mks-nav-h);
  overflow: hidden;
}
.logo-container .logo {
  flex: none;
  width: var(--mks-column-icon-w);
  height: var(--mks-nav-h);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  background: var(--mks-primary-grad);
}
.logo-container .title {
  padding-left: 12px;
  font-size: 20px;
  white-space: nowrap;
  color: var(--mks-text);
}
.side-bar.is-deep .logo-container .title {
  color: #fff;
}

.menu-scroll {
  flex: 1;
  overflow: hidden;
}

/* el-menu 配色全部走变量，主题切换即时生效 */
.side-menu {
  border-right: none;
  background: transparent;
}
.side-menu :deep(.el-menu-item),
.side-menu :deep(.el-sub-menu__title) {
  height: var(--mks-menu-item-h);
  line-height: var(--mks-menu-item-h);
  font-size: var(--mks-font-size);
  color: var(--mks-side-text);
  background: transparent;
}
.side-menu :deep(.el-menu--inline .el-menu-item) {
  height: 44px;
  line-height: 44px;
  font-size: var(--mks-font-size-input);
}
.side-menu :deep(.el-menu-item:hover),
.side-menu :deep(.el-sub-menu__title:hover) {
  color: var(--mks-side-text-active);
  background: var(--mks-side-hover-bg);
}
.side-menu :deep(.el-menu-item.is-active) {
  color: var(--mks-side-text-active);
  background: var(--mks-side-bg-active);
  font-weight: 500;
}
.side-bar.is-deep .side-menu :deep(.el-menu-item.is-active),
.side-bar.is-deep .side-menu :deep(.el-menu-item:hover) {
  color: #fff;
  background: var(--mks-primary);
}
.side-bar.is-collapse :deep(.el-menu--collapse > .el-menu-item),
.side-bar.is-collapse :deep(.el-menu--collapse > .el-sub-menu > .el-sub-menu__title) {
  text-align: center;
}
</style>
