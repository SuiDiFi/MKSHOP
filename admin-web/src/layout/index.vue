<template>
  <div class="mks-layout">
    <!-- 分栏（默认）：266px = 64 渠道图标列 + 202 二级菜单 -->
    <column-bar v-if="settings.isColumn" />
    <!-- 纵向 / 综合：266px 侧栏（折叠 64px） -->
    <side-menu v-else-if="settings.isVertical" />

    <div class="wrapper">
      <!-- 横向：菜单与功能区同处 60px 一行 -->
      <div v-if="settings.isHorizontal" class="header-row">
        <horizontal-menu />
        <nav-bar compact @open-theme="themeVisible = true" />
      </div>
      <nav-bar v-else @open-theme="themeVisible = true" />

      <tabs-view v-if="settings.showTabs" />

      <main class="app-main">
        <div class="app-main-inner">
          <router-view v-slot="{ Component, route: r }">
            <transition name="fade-transform" mode="out-in">
              <component :is="Component" :key="r.fullPath" />
            </transition>
          </router-view>
        </div>

        <footer class="mks-footer">
          <mks-icon name="copyright-line" :size="14" />
          <span>Copyright {{ year }} MKSHOP</span>
        </footer>
      </main>
    </div>

    <theme-drawer v-model="themeVisible" />
  </div>
</template>

<script setup>
/**
 * 后台布局骨架（对齐 KASUSHOU vue-admin-beautiful 的三种导航形态）
 * 尺寸与结构取证见 docs/UI-后台对齐KASUSHOU.md §1.2 / §1.3
 */
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSettingsStore } from '@/store/settings';
import { useTabsStore } from '@/store/tabs';
import ColumnBar from './columnBar.vue';
import SideMenu from './sideMenu.vue';
import HorizontalMenu from './horizontalMenu.vue';
import NavBar from './navBar.vue';
import TabsView from './tabsView.vue';
import ThemeDrawer from './themeDrawer.vue';
import mksIcon from '@/components/mksIcon.vue';

const route = useRoute();
const settings = useSettingsStore();
const tabs = useTabsStore();
const themeVisible = ref(false);
const year = new Date().getFullYear();

settings.boot();

// 路由变化 → 登记标签（对方无 keep-alive，页面每次重建）
watch(
  () => route.fullPath,
  () => tabs.addView(route),
  { immediate: true }
);
</script>

<style scoped>
.mks-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--mks-bg);
}

.wrapper {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 横向布局的 60px 头行 */
.header-row {
  flex: none;
  display: flex;
  align-items: center;
  height: var(--mks-nav-h);
  background: var(--mks-primary);
}

.app-main {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  background: var(--mks-bg);
}
/* 内容区滚动，页脚始终贴底（对方 main 与 footer 分离的结构） */
.app-main-inner {
  flex: 1;
  min-height: 0;
  padding: var(--mks-main-pad);
  overflow: auto;
}

/* 页脚：55px 白底条（对方 footer bg=rgb(255,255,255)，之前透明压在灰底上） */
.mks-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  height: var(--mks-footer-h);
  margin: 0 calc(-1 * var(--mks-main-pad)) calc(-1 * var(--mks-main-pad));
  padding: 0 var(--mks-main-pad);
  font-size: var(--mks-font-size);
  color: var(--mks-text-footer);
  background: var(--mks-bg-white);
  border-top: 1px solid var(--mks-border-light);
}
</style>
