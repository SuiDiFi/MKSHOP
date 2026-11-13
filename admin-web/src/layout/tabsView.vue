<template>
  <div class="tabs-bar" :class="`mks-tabs--${settings.tabsBarStyle}`">
    <el-tabs
      :model-value="activePath"
      :closable="false"
      class="mks-tabs"
      @tab-change="onSwitch"
    >
      <el-tab-pane v-for="v in views" :key="v.path" :name="v.path">
        <template #label>
          <span class="tab-label" @mousedown.middle.prevent="closeOne(v)">
            <mks-icon v-if="settings.showTabsIcon && v.icon" :name="v.icon" :size="14" />
            <span class="tab-title">{{ v.title }}</span>
            <!-- 自己渲染关闭图标：EP 的 pane.closable=false 会被 tabs 级 closable 或运算覆盖（源码 tab-nav.mjs:234） -->
            <mks-icon v-if="!v.noClosable" name="close-line" :size="12" class="tab-close" @click.stop="closeOne(v)" />
          </span>
        </template>
      </el-tab-pane>
    </el-tabs>

    <el-dropdown class="tabs-more" trigger="click" @command="onMore">
      <mks-icon name="menu-line" :size="18" class="tabs-more-icon" />
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item command="others"><mks-icon name="close-line" /> 关闭其他</el-dropdown-item>
          <el-dropdown-item command="left"><mks-icon name="arrow-left-line" /> 关闭左侧</el-dropdown-item>
          <el-dropdown-item command="right"><mks-icon name="arrow-right-line" /> 关闭右侧</el-dropdown-item>
          <el-dropdown-item command="all" divided><mks-icon name="close-line" /> 关闭全部</el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
  </div>
</template>

<script setup>
/**
 * 多标签页（对齐 KASUSHOU tags-view 实测）
 *  · 高 50px，smooth 风格为叠压造型（.el-tabs__item margin-right:-18px）
 *  · 首页 noClosable；「更多」下拉＝关闭其他/左/右/全部；对方无右键菜单
 */
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTabsStore } from '@/store/tabs';
import { useSettingsStore } from '@/store/settings';
import mksIcon from '@/components/mksIcon.vue';

const route = useRoute();
const router = useRouter();
const tabs = useTabsStore();
const settings = useSettingsStore();

const views = computed(() => tabs.visitedViews);
const activePath = computed(() => route.path);

function onSwitch(path) {
  const v = tabs.visitedViews.find((x) => x.path === path);
  if (v && v.path !== route.path) router.push(v.fullPath || v.path);
}

async function closeOne(v) {
  if (v.noClosable) return;
  const next = await tabs.close(v.path);
  if (v.path === route.path) router.push(next || '/index');
}

async function onMore(cmd) {
  const cur = route.path;
  if (cmd === 'others') tabs.closeOthers(cur);
  else if (cmd === 'left') tabs.closeLeft(cur);
  else if (cmd === 'right') tabs.closeRight(cur);
  else if (cmd === 'all') {
    const next = tabs.closeAll();
    if (cur !== next) router.push(next);
  }
}
</script>

<style scoped>
.tabs-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--mks-tabs-h);
  padding: 0 var(--mks-main-pad);
  background: var(--mks-bg-white);
  border-bottom: 1px solid var(--mks-border-light);
}
.mks-tabs {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}
.mks-tabs :deep(.el-tabs__header) {
  margin: 0;
  border-bottom: none;
}
.mks-tabs :deep(.el-tabs__nav-scroll) {
  overflow-x: auto;
}
.mks-tabs :deep(.el-tabs__nav-next),
.mks-tabs :deep(.el-tabs__nav-prev) {
  line-height: var(--mks-tabs-h);
}
/* 对方 smooth 风格没有底部激活条，胶囊底已是强调，去掉多余蓝线 */
.mks-tabs :deep(.el-tabs__active-bar) {
  display: none;
}
.mks-tabs :deep(.el-tabs__nav-wrap::after) {
  display: none;
}
.mks-tabs :deep(.el-tabs__item) {
  display: inline-flex;
  align-items: center;
  height: 38px;
  line-height: 38px;
  padding: 0 12px !important;
  font-size: var(--mks-font-size-input);
  color: var(--mks-text-secondary);
  background: var(--mks-bg);
  border: 1px solid var(--mks-border-light) !important;
  border-radius: var(--mks-radius-panel);
}
.mks-tabs :deep(.el-tabs__item.is-active) {
  color: var(--mks-primary);
  background: var(--el-color-primary-light-9);
  border-color: var(--el-color-primary-light-7) !important;
}
.tab-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.tab-close {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--mks-text-placeholder);
  transition: 0.2s;
}
.tab-close:hover {
  color: #fff;
  background: var(--mks-danger);
}

/* smooth：叠压造型（对方 .el-tabs__item{margin-right:-18px}） */
.mks-tabs--smooth :deep(.el-tabs__item) {
  margin-right: -18px;
  border-radius: 19px;
  padding-left: 20px !important;
}
/* card：方角卡片 */
.mks-tabs--card :deep(.el-tabs__item) {
  border-radius: var(--mks-radius) var(--mks-radius) 0 0;
}
/* smart：无边框 */
.mks-tabs--smart :deep(.el-tabs__item) {
  border: none !important;
  background: transparent;
}

.tabs-more {
  flex: none;
  margin-left: 10px;
  outline: none;
}
.tabs-more-icon {
  cursor: pointer;
  color: var(--mks-text-secondary);
}
.tabs-more-icon:hover {
  color: var(--mks-primary);
}
</style>
