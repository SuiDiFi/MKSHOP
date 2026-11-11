<template>
  <div>
    <Topbar />
    <div class="wrap" style="max-width: 420px; margin-top: 60px;">
      <el-card shadow="never">
        <h3 style="text-align:center; margin-top: 0">账号登录</h3>
        <el-form @submit.prevent="doLogin">
          <el-form-item>
            <el-input v-model="form.username" placeholder="账号" size="large" @keyup.enter="doLogin">
              <template #prefix><el-icon><User /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="form.password" type="password" placeholder="密码" size="large" show-password @keyup.enter="doLogin">
              <template #prefix><el-icon><Lock /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-button type="primary" size="large" style="width: 100%" :loading="loading" @click="doLogin">登 录</el-button>
          <div style="text-align:center; margin-top: 12px; font-size: 14px">
            还没有账号？<router-link :to="{ name: 'Register', query: $route.query }" style="color:#409eff">立即注册</router-link>
          </div>
        </el-form>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { memberLogin } from '@/api';
import { useUserStore } from '@/store/user';

const form = ref({ username: '', password: '' });
const loading = ref(false);
const store = useUserStore();
const router = useRouter();
const route = useRoute();

const doLogin = async () => {
  if (!form.value.username || !form.value.password) {
    ElMessage.warning('请输入账号和密码');
    return;
  }
  loading.value = true;
  try {
    const res = await memberLogin(form.value);
    store.setToken(res.data?.token);
    store.userinfo = res.data?.userinfo || null;
    ElMessage.success('登录成功');
    router.push(route.query.redirect || '/');
  } catch (e) { /* ignore */ } finally {
    loading.value = false;
  }
};
</script>
