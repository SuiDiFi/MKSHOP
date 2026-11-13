<template>
  <header class="nav-bar" :class="{ 'is-compact': compact }">
    <div v-if="!compact" class="left-panel">
      <mks-icon
        class="fold-unfold"
        :name="settings.collapse ? 'menu-unfold-line' : 'menu-fold-line'"
        :size="20"
        @click="settings.toggleCollapse()"
      />
      <!-- 面包屑：每项带图标、分隔符 > ；容器节点（Layout）不给链接
           —— 继承菜单里「商品/用户/订单」渠道 path 与首页下统计页同名，
              若给链接点「订单」会落到「交易分析」，此处有意偏离 KASUSHOU 行为（已记录在方案文档） -->
      <el-breadcrumb v-if="crumbs.length" class="mks-breadcrumb" separator=">">
        <el-breadcrumb-item
          v-for="(c, i) in crumbs"
          :key="c.path"
          :to="i === crumbs.length - 1 || c.layout ? undefined : { path: c.path }"
        >
          <span class="crumb">
            <mks-icon v-if="c.icon" :name="c.icon" :size="14" /> {{ c.title }}
          </span>
        </el-breadcrumb-item>
      </el-breadcrumb>
    </div>

    <div class="right-panel">
      <el-tooltip v-if="settings.showFrontHomeUrl" content="前台首页" placement="bottom">
        <mks-icon name="home-2-line" :size="18" class="fn" @click="openFront" />
      </el-tooltip>

      <el-popover v-if="settings.showNotice" placement="bottom" :width="300" trigger="click" popper-class="mks-notice-pop">
        <template #reference>
          <span class="notice-ref">
            <el-badge :value="noticeTotal" :hidden="!noticeTotal" :max="999">
              <mks-icon name="notification-line" :size="18" class="fn" />
            </el-badge>
          </span>
        </template>
        <el-tabs v-model="noticeTab" class="mks-notice-tabs">
          <el-tab-pane :label="`待办(${notices.todo.length})`" name="todo">
            <div v-if="!notices.todo.length" class="notice-empty">暂无待办事项</div>
            <div v-for="t in notices.todo" :key="t.label" class="notice-row" @click="go(t.path)">
              <span>{{ t.label }}</span><b>{{ t.value }}</b>
            </div>
          </el-tab-pane>
          <el-tab-pane :label="`消息(${notices.msg.length})`" name="msg">
            <div class="notice-empty">{{ notices.msg.length ? '' : '消息模块未接入' }}</div>
            <div v-for="(m, i) in notices.msg" :key="i" class="notice-row"><span>{{ m }}</span></div>
          </el-tab-pane>
          <el-tab-pane :label="`系统公告(${notices.news.length})`" name="news">
            <div class="notice-empty">{{ notices.news.length ? '' : '公告模块未接入' }}</div>
            <div v-for="(n, i) in notices.news" :key="i" class="notice-row"><span>{{ n }}</span></div>
          </el-tab-pane>
        </el-tabs>
      </el-popover>

      <el-tooltip v-if="settings.showFullScreen" content="全屏" placement="bottom">
        <mks-icon :name="isFull ? 'fullscreen-exit-fill' : 'fullscreen-fill'" :size="18" class="fn" @click="toggleFull" />
      </el-tooltip>

      <el-tooltip v-if="settings.showThemeSetting" content="主题配置" placement="bottom">
        <mks-icon name="palette-line" :size="18" class="fn" @click="$emit('open-theme')" />
      </el-tooltip>

      <el-tooltip v-if="settings.showRefresh" content="刷新" placement="bottom">
        <mks-icon name="refresh-line" :size="18" class="fn" @click="reload" />
      </el-tooltip>

      <el-tooltip v-if="settings.showCleanCache" content="清除外观缓存（主题/标签，不清除登录态）" placement="bottom">
        <mks-icon name="brush-3-line" :size="18" class="fn" @click="cleanCache" />
      </el-tooltip>

      <el-dropdown class="avatar-dropdown" @command="onCommand">
        <span class="user">
          <el-avatar :size="28" class="avatar">{{ (user.userinfo.username || 'A').slice(0, 1).toUpperCase() }}</el-avatar>
          <span class="name">{{ user.userinfo.realname || user.userinfo.username || '管理员' }}</span>
          <el-icon><ArrowDown /></el-icon>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile"><mks-icon name="user-line" /> 个人中心</el-dropdown-item>
            <el-dropdown-item command="logout" divided><mks-icon name="logout-circle-r-line" /> 退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>

    <el-dialog v-model="profileVisible" title="个人中心" width="600px">
      <el-descriptions :column="1" border size="small">
        <el-descriptions-item v-for="(v, k) in profileRows" :key="k" :label="k">{{ v }}</el-descriptions-item>
      </el-descriptions>
      <template #footer><el-button type="primary" @click="profileVisible = false">关闭</el-button></template>
    </el-dialog>
  </header>
</template>

<script setup>
/**
 * 顶部导航（对齐 KASUSHOU nav-bar 实测：60px 高、左折叠+带图标面包屑、右 6 个功能位 + 头像下拉）
 * 通知数据只取已有真实接口 index_statics 的 pending；消息/公告模块未接入时明确显示空态，不编造数据
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useUserStore } from '@/store/user';
import { useSettingsStore } from '@/store/settings';
import { breadcrumbOf } from '@/utils/menu';
import { indexStatics } from '@/api';
import mksIcon from '@/components/mksIcon.vue';

defineProps({
  /** 紧凑模式：只保留右侧功能区，背在横向菜单同一行蓝底上（横向布局用） */
  compact: { type: Boolean, default: false },
});
defineEmits(['open-theme']);

const route = useRoute();
const router = useRouter();
const user = useUserStore();
const settings = useSettingsStore();

const crumbs = computed(() => breadcrumbOf(route.path, user.menus || [], user.menuPathMap));

const noticeTab = ref('todo');
const notices = ref({ todo: [], msg: [], news: [] });
const noticeTotal = computed(() => notices.value.todo.reduce((a, b) => a + Number(b.value || 0), 0));

const isFull = ref(false);
const profileVisible = ref(false);
const profileRows = computed(() => {
  const u = user.userinfo || {};
  return {
    登录账号: u.username || '-',
    姓名: u.realname || '-',
    管理员ID: u.id ?? '-',
    角色: u.roles || '-',
    状态: Number(u.status) === 1 ? '正常' : '禁用',
  };
});

function go(path) {
  if (path) router.push(path);
}

function openFront() {
  window.open(`${location.origin}/`, '_blank');
}

function reload() {
  window.location.reload();
}

async function loadNotices() {
  try {
    const res = await indexStatics();
    const d = res.data || {};
    const p = d.pending || {};
    notices.value.todo = [
      { label: '等待处理订单', value: Number(p.wait_deal || 0), path: '/order/list' },
      { label: '正在处理订单', value: Number(p.dealing || 0), path: '/order/list' },
      { label: '未支付订单', value: Number(p.wait_pay || 0), path: '/order/list' },
      { label: '待处理售后', value: Number(p.complain_wait || 0), path: '/order/complain' },
      { label: '待审核商品', value: Number(p.goods_wait || 0), path: '/goods/list' },
      { label: '待审核商户', value: Number(p.apply_wait || 0), path: '/member/merchant' },
    ].filter((t) => t.value > 0);
  } catch (e) {
    /* 通知失败不打断导航 */
  }
}

function toggleFull() {
  const el = document.documentElement;
  if (!document.fullscreenElement) el.requestFullscreen?.();
  else document.exitFullscreen?.();
}

function onFullChange() {
  isFull.value = !!document.fullscreenElement;
}

async function cleanCache() {
  await ElMessageBox.confirm('将清除主题设置与标签页缓存并刷新页面（不影响登录态），确定？', '清除缓存', { type: 'warning' });
  localStorage.removeItem('mks_admin_settings');
  localStorage.removeItem('mks_admin_tabs');
  ElMessage.success('已清除，正在刷新');
  setTimeout(reload, 400);
}

async function onCommand(cmd) {
  if (cmd === 'logout') {
    await ElMessageBox.confirm('确定退出登录？', '提示', { type: 'warning' });
    await user.logout();
    router.push('/login');
  } else if (cmd === 'profile') {
    profileVisible.value = true;
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullChange);
  loadNotices();
});
onBeforeUnmount(() => document.removeEventListener('fullscreenchange', onFullChange));
</script>

<style scoped>
.nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--mks-nav-h);
  padding: 0 var(--mks-main-pad);
  background: var(--mks-bg-white);
  border-bottom: 1px solid var(--mks-border-light);
}
/* 紧凑模式（横向布局）：透底、靠右、不占整行 */
.nav-bar.is-compact {
  flex: none;
  padding: 0 var(--mks-main-pad) 0 0;
  background: transparent;
  border-bottom: none;
}
.nav-bar.is-compact .fn,
.nav-bar.is-compact .user .name,
.nav-bar.is-compact .fold-unfold {
  color: rgba(255, 255, 255, 0.9);
}
.nav-bar.is-compact .fn:hover {
  color: #fff;
}
.nav-bar.is-compact .avatar {
  background: rgba(255, 255, 255, 0.25);
}
.left-panel,
.right-panel {
  display: flex;
  align-items: center;
  gap: 12px;
}
.fold-unfold {
  cursor: pointer;
  color: var(--mks-text-secondary);
}
.fold-unfold:hover {
  color: var(--mks-primary);
}
.mks-breadcrumb {
  font-size: var(--mks-font-size);
  line-height: var(--mks-nav-h);
}
.mks-breadcrumb :deep(.el-breadcrumb__separator) {
  color: var(--mks-text-placeholder);
  margin: 0 6px;
}
/* 末项不显示分隔符（对方末级不带 >） */
.mks-breadcrumb :deep(.el-breadcrumb__item:last-child .el-breadcrumb__separator) {
  display: none;
}
.mks-breadcrumb :deep(.el-breadcrumb__inner.is-link) {
  font-weight: 400;
  color: var(--mks-text);
  cursor: pointer;
}
.mks-breadcrumb :deep(.el-breadcrumb__inner.is-link:hover) {
  color: var(--mks-primary);
}
.mks-breadcrumb :deep(.el-breadcrumb__item:last-child .el-breadcrumb__inner) {
  font-weight: 400;
  color: var(--mks-text-secondary);
}
.crumb {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.fn {
  cursor: pointer;
  color: var(--mks-text-secondary);
}
.fn:hover {
  color: var(--mks-primary);
}
.notice-ref {
  display: inline-flex;
  align-items: center;
}
.notice-ref :deep(.el-badge__content) {
  border: none;
}
.avatar-dropdown {
  outline: none;
}
.user {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  outline: none;
}
.user .name {
  font-size: var(--mks-font-size);
  color: var(--mks-text);
}
.avatar {
  background: var(--mks-primary);
  color: #fff;
  font-weight: 600;
}
.notice-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 4px;
  font-size: var(--mks-font-size-input);
  color: var(--mks-text-regular);
  cursor: pointer;
  border-bottom: 1px dashed var(--mks-border-light);
}
.notice-row:hover {
  color: var(--mks-primary);
}
.notice-empty {
  padding: 16px 0;
  text-align: center;
  font-size: var(--mks-font-size-input);
  color: var(--mks-text-placeholder);
}
</style>

<style>
/* popover 内层需要非 scoped 样式（teleport 到 body） */
.mks-notice-pop {
  padding: 12px !important;
}
.mks-notice-tabs .el-tabs__header {
  margin-bottom: 6px;
}
</style>
