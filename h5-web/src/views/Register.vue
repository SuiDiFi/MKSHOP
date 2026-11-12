<template>
  <div class="page plain">
    <NavBar title="注册会员" />
    <div class="wrap">
      <div class="login-card">
        <div class="welcome">创建 MKSHOP 账号</div>
        <div class="field">
          <input v-model="form.username" class="input" placeholder="账号（至少 3 位）" />
        </div>
        <div class="field">
          <input v-model="form.password" type="password" class="input" placeholder="密码（至少 6 位）" />
        </div>
        <div class="field">
          <input v-model="form.invite_code" class="input" placeholder="邀请码（选填）" />
        </div>
        <button class="btn block" style="margin-top:20px" :disabled="loading" @click="submit">
          {{ loading ? '注册中…' : '注册并登录' }}
        </button>
        <div class="links">
          <span class="muted">已有账号？</span>
          <router-link to="/login" class="link">去登录</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import NavBar from '@/components/NavBar.vue';
import { memberRegister } from '@/api';
import { toast } from '@/utils/request';

const router = useRouter();
const form = ref({ username: '', password: '', invite_code: '' });
const loading = ref(false);

const submit = async () => {
  if (!form.value.username || !form.value.password) {
    toast('请输入账号和密码');
    return;
  }
  loading.value = true;
  try {
    const res = await memberRegister(form.value);
    localStorage.setItem('mks_member_token', res.data?.token || '');
    localStorage.setItem('mks_member_info', JSON.stringify(res.data?.userinfo || {}));
    toast('注册成功');
    router.replace('/');
  } catch (e) { /* toast 已提示 */ } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.login-card { background: #fff; border-radius: 14px; padding: 26px 20px; margin-top: 26px; text-align: center; }
.welcome { font-size: 18px; font-weight: 700; margin-bottom: 18px; }
.field { margin-top: 12px; }
.links { margin-top: 18px; font-size: 13px; }
.link { color: #1d6fe8; margin-left: 4px; }
</style>
