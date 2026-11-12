<template>
  <div class="page plain">
    <NavBar title="会员登录" />
    <div class="wrap">
      <div class="login-card">
        <div class="logo">🛒</div>
        <div class="welcome">欢迎回到 MKSHOP</div>
        <div class="field">
          <input v-model="form.username" class="input" placeholder="账号" autocomplete="username" />
        </div>
        <div class="field">
          <input v-model="form.password" type="password" class="input" placeholder="密码" autocomplete="current-password" @keyup.enter="submit" />
        </div>
        <button class="btn block" style="margin-top:20px" :disabled="loading" @click="submit">
          {{ loading ? '登录中…' : '登录' }}
        </button>
        <div class="links">
          <span class="muted">还没有账号？</span>
          <router-link to="/register" class="link">立即注册</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import NavBar from '@/components/NavBar.vue';
import { memberLogin } from '@/api';
import { toast } from '@/utils/request';

const route = useRoute();
const router = useRouter();
const form = ref({ username: '', password: '' });
const loading = ref(false);

const submit = async () => {
  if (!form.value.username || !form.value.password) {
    toast('请输入账号和密码');
    return;
  }
  loading.value = true;
  try {
    const res = await memberLogin(form.value);
    localStorage.setItem('mks_member_token', res.data?.token || '');
    localStorage.setItem('mks_member_info', JSON.stringify(res.data?.userinfo || {}));
    toast('登录成功');
    router.replace(route.query.redirect || '/');
  } catch (e) { /* toast 已提示 */ } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.login-card { background: #fff; border-radius: 14px; padding: 26px 20px; margin-top: 26px; text-align: center; }
.logo { font-size: 44px; }
.welcome { font-size: 18px; font-weight: 700; margin: 8px 0 18px; }
.field { margin-top: 12px; }
.links { margin-top: 18px; font-size: 13px; }
.link { color: #1d6fe8; margin-left: 4px; }
</style>
