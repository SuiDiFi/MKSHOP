<template>
  <el-container class="layout">
    <el-aside :width="collapse ? '64px' : '220px'" class="aside">
      <div class="logo-bar">
        <span class="logo">MK</span>
        <span v-show="!collapse" class="title">MKSHOP</span>
      </div>
      <el-scrollbar>
        <el-menu
          :default-active="activeMenu"
          :collapse="collapse"
          :collapse-transition="false"
          background-color="#1f2d3d"
          text-color="#bfcbd9"
          active-text-color="#409eff"
          router
        >
          <menu-item v-for="node in visibleMenus" :key="node.id" :node="node" />
        </el-menu>
      </el-scrollbar>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="left">
          <el-icon class="collapse-btn" @click="collapse = !collapse">
            <Expand v-if="collapse" /><Fold v-else />
          </el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item v-if="route.meta?.title">{{ route.meta.title }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="right">
          <el-dropdown @command="onCommand">
            <span class="user">
              <el-avatar :size="30" class="avatar">{{ (user.userinfo.username || 'A').slice(0, 1).toUpperCase() }}</el-avatar>
              <span class="name">{{ user.userinfo.realname || user.userinfo.username || '管理员' }}</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="logout">
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>
      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import { useUserStore } from '@/store/user';
import MenuItem from './menu-item.vue';

const route = useRoute();
const router = useRouter();
const user = useUserStore();
const collapse = ref(false);

const visibleMenus = computed(() =>
  (user.menus || []).filter((m) => !m.hidden && m.path && m.path !== '/')
);
const activeMenu = computed(() => route.meta?.menuId ? `M${route.meta.menuId}` : route.path);

async function onCommand(cmd) {
  if (cmd === 'logout') {
    await ElMessageBox.confirm('确定退出登录？', '提示', { type: 'warning' });
    await user.logout();
    router.push('/login');
  }
}
</script>

<style scoped>
.layout { height: 100%; }
.aside {
  background: #1f2d3d;
  transition: width 0.2s;
  overflow: hidden;
}
.logo-bar {
  height: 56px; display: flex; align-items: center; justify-content: center; gap: 8px;
  color: #fff; background: #17222e;
}
.logo { width: 30px; height: 30px; border-radius: 8px; background: #409eff; color: #fff; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.title { font-size: 16px; font-weight: 600; letter-spacing: 1px; }
.aside :deep(.el-menu) { border-right: none; }
.header {
  height: 56px; display: flex; align-items: center; justify-content: space-between;
  background: #fff; border-bottom: 1px solid #e4e7ed;
}
.left { display: flex; align-items: center; gap: 12px; }
.collapse-btn { font-size: 20px; cursor: pointer; }
.right { display: flex; align-items: center; }
.user { display: flex; align-items: center; gap: 8px; cursor: pointer; outline: none; }
.name { font-size: 14px; color: #303133; }
.avatar { background: #409eff; color: #fff; font-weight: 600; }
.main { padding: 0; background: #f5f7fa; overflow: auto; }
</style>
