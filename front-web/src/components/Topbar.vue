<template>
  <div class="topbar">
    <div class="wrap topbar-inner">
      <div class="logo" @click="$router.push('/')">
        <el-icon><ShoppingCart /></el-icon> MKSHOP
      </div>
      <div class="nav-links">
        <router-link to="/">首页</router-link>
        <router-link to="/category">分类</router-link>
        <router-link to="/notices">公告</router-link>
        <router-link to="/help">帮助中心</router-link>
        <router-link to="/orders">我的订单</router-link>
        <router-link to="/member">会员中心</router-link>
        <router-link to="/commission">分销中心</router-link>
      </div>
      <div class="user-area">
        <template v-if="store.isLogin">
          <span>{{ store.userinfo?.nickname || store.userinfo?.username || '会员' }}</span>
          <el-button link type="danger" size="small" @click="doLogout">退出</el-button>
        </template>
        <template v-else>
          <el-button link type="primary" @click="$router.push('/login')">登录</el-button>
          <el-button link @click="$router.push('/register')">注册</el-button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/user';
import { memberLogout } from '@/api';

const store = useUserStore();
const router = useRouter();

onMounted(() => {
  if (store.isLogin && !store.userinfo) {
    store.fetchInfo().catch(() => {});
  }
});

const doLogout = async () => {
  try {
    await memberLogout();
  } catch (e) { /* ignore */ }
  store.logout();
  ElMessage.success('已退出');
  router.push('/');
};
</script>
