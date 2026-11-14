<template>
  <aside class="column-bar" :class="[`cs-${settings.columnStyle}`]">
    <!-- logo 区：266×60（对方 .logo-container-column，logo 64×60 渐变 + 标题） -->
    <div class="logo-container">
      <div class="logo">MK</div>
      <div class="title">MKSHOP</div>
    </div>

    <!-- 分栏风格=横向：一级渠道移到顶部横条，二级菜单占满宽度 -->
    <div v-if="settings.columnStyle === 'horizontal'" class="top-groups">
      <el-scrollbar>
        <div class="top-groups-inner">
          <div
            v-for="g in groups"
            :key="g.menuId"
            class="group-item is-top"
            :class="{ 'is-active': active && active.menuId === g.menuId }"
            @click="onGroup(g)"
          >
            <mks-icon :name="g.icon" :size="16" />
            <span class="group-title">{{ g.title }}</span>
          </div>
        </div>
      </el-scrollbar>
    </div>

    <div class="column-grid">
      <!-- 一级渠道：64px 图标列（横向风格时已移到顶部） -->
      <div v-if="settings.columnStyle !== 'horizontal'" class="column-groups">
        <el-scrollbar>
          <div
            v-for="g in groups"
            :key="g.menuId"
            class="group-item"
            :class="{ 'is-active': active && active.menuId === g.menuId }"
            :title="g.title"
            @click="onGroup(g)"
          >
            <mks-icon :name="g.icon" :size="20" />
            <span v-if="settings.columnStyle !== 'arrow'" class="group-title">{{ g.title }}</span>
            <i v-else class="arrow"></i>
          </div>
        </el-scrollbar>
      </div>

      <!-- 二级菜单：202px（横向风格时 266px）；分组用带文字分隔线（对方 el-divider 规范） -->
      <div class="column-menu">
        <el-scrollbar>
          <div v-if="!items.length" class="mks-data-empty">
            <mks-icon name="inbox-line" :size="56" class="mks-empty-icon" />
            <div>该渠道下无可见菜单</div>
          </div>
          <template v-else>
            <!-- 渠道标题分隔线：对方二级列顶部有 el-divider 带文字 -->
            <el-divider v-if="needHeadDivider" content-position="left" class="mks-divider">
              {{ active?.title || '' }}
            </el-divider>
            <div v-for="(it, i) in items" :key="it.path + i">
              <el-divider v-if="it.type === 'divider'" content-position="left" class="mks-divider">
                {{ it.title }}
              </el-divider>
              <div v-else class="menu-item" :class="{ 'is-active': isActive(it) }" @click="go(it.path)">
                <mks-icon v-if="it.icon" :name="it.icon" :size="16" class="menu-icon" />
                <span class="menu-title">{{ it.title }}</span>
              </div>
            </div>
          </template>
        </el-scrollbar>
      </div>
    </div>
  </aside>
</template>

<script setup>
/**
 * 分栏布局左栏（对齐 KASUSHOU column 布局实测：266px = 64 图标列 + 202 菜单列，logo 60px）
 * 主题面板的「分栏风格」4 个取值均有真实视觉差异：
 *   vertical 纵向＝图标+文字（默认） | card 卡片＝再加卡片底 | arrow 箭头＝仅图标+激活指示条
 *   horizontal 横向＝一级渠道移到顶部横条、二级菜单占满 266px
 */
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/store/user';
import { useSettingsStore } from '@/store/settings';
import { columnOf, groupItems, activePathOf } from '@/utils/menu';
import mksIcon from '@/components/mksIcon.vue';

const route = useRoute();
const router = useRouter();
const user = useUserStore();
const settings = useSettingsStore();

const column = computed(() => columnOf(user.menus || [], route.path));
const groups = computed(() => column.value.groups);
const items = computed(() => column.value.items);
const active = computed(() => column.value.active);

/** 首项不是分隔线时，补一条渠道标题分隔线（避免"一坨"无分段） */
const needHeadDivider = computed(() => items.value.length > 0 && items.value[0].type !== 'divider');

function isActive(it) {
  // 隐藏页（商品新增/编辑）按 activeMenu 归属高亮：/goods/add 要亮起「商品列表」
  const cur = activePathOf(route, user.menuPathMap);
  return cur === it.path || cur.startsWith(it.path + '/');
}

function onGroup(g) {
  // 渠道本身不是页面时，跳到该渠道第一个可点菜单
  const first = groupItems(g.raw, g.path).find((it) => it.type === 'item');
  const target = first ? first.path : g.path;
  if (target) router.push(target);
}

function go(path) {
  router.push(path);
}
</script>

<style scoped>
.column-bar {
  flex: none;
  width: var(--mks-column-w);
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--mks-bg-side);
  box-shadow: var(--mks-shadow-side);
  z-index: var(--mks-z-side);
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
  letter-spacing: 1px;
  color: #fff;
  background: var(--mks-primary-grad);
}
.logo-container .title {
  flex: 1;
  padding-left: 12px;
  font-size: 20px;
  font-weight: 400;
  color: var(--mks-text);
  white-space: nowrap;
}

.column-grid {
  flex: 1;
  min-height: 0;
  display: flex;
}

/* 一级渠道图标列 */
.column-groups {
  flex: none;
  width: var(--mks-column-icon-w);
  border-right: 1px solid var(--mks-border-light);
}
.column-groups :deep(.el-scrollbar__view) {
  padding: 4px 0;
}
.group-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  width: var(--mks-column-icon-w);
  height: 64px;
  color: var(--mks-text-secondary);
  cursor: pointer;
  transition: 0.2s;
}
.group-item:hover {
  color: var(--mks-primary);
}
.group-item.is-active {
  color: #fff;
  background: var(--mks-primary);
}
.group-title {
  font-size: 12px;
  line-height: 14px;
  max-width: 58px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* 卡片风格：每项独立卡片 */
.cs-card .group-item {
  width: 56px;
  margin: 4px auto;
  border-radius: var(--mks-radius-panel);
  background: var(--mks-bg-header);
}
.cs-card .group-item.is-active {
  background: var(--mks-primary);
}

/* 箭头风格：仅图标 + 激活指示条 */
.cs-arrow .group-item.is-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 18px;
  bottom: 18px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: #fff;
}
.cs-arrow .arrow {
  width: 18px;
  height: 2px;
  background: currentColor;
  opacity: 0.6;
}

/* 横向风格：一级渠道移到顶部横条 */
.top-groups {
  flex: none;
  border-bottom: 1px solid var(--mks-border-light);
}
.top-groups :deep(.el-scrollbar__wrap) {
  overflow-x: auto;
}
.top-groups-inner {
  display: flex;
  white-space: nowrap;
}
.group-item.is-top {
  flex: none;
  flex-direction: row;
  gap: 5px;
  width: auto;
  height: 44px;
  padding: 0 12px;
}

/* 二级菜单列 */
.column-menu {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}
.cs-horizontal .column-menu {
  width: var(--mks-column-w);
}
.column-menu :deep(.el-scrollbar__view) {
  padding: 6px 0;
}
.mks-divider {
  margin: 10px 0 6px;
}
.mks-divider :deep(.el-divider__text) {
  font-size: 12px;
  color: var(--mks-text-placeholder);
  background: var(--mks-bg-side);
}
.menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  height: var(--mks-menu-item-h);
  padding: 0 16px;
  font-size: var(--mks-font-size);
  color: var(--mks-side-text);
  cursor: pointer;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  transition: 0.2s;
}
.menu-item:hover {
  color: var(--mks-primary);
  background: var(--mks-side-hover-bg);
}
.menu-item.is-active {
  color: var(--mks-side-text-active);
  background: var(--mks-side-bg-active);
  font-weight: 500;
}
.menu-icon {
  flex: none;
}
</style>
