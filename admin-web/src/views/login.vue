<template>
  <div class="login-wrap">
    <div class="login-card">
      <div class="brand">
        <div class="logo">MK</div>
        <h1>MKSHOP 管理后台</h1>
        <p>虚拟商品电商系统 · 自研重构版</p>
      </div>
      <el-form :model="form" @keyup.enter="doLogin" size="large">
        <el-form-item>
          <el-input v-model="form.username" placeholder="管理员账号" clearable>
            <template #prefix><el-icon><User /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-input v-model="form.password" type="password" placeholder="密码" show-password>
            <template #prefix><el-icon><Lock /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-button type="primary" class="submit" size="large" :loading="loading" @click="doLogin">
          登 录
        </el-button>
      </el-form>
      <div class="tips">MKSHOP v0.1.0 · ThinkPHP6 + Vue3</div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/user';

const router = useRouter();
const store = useUserStore();
const form = reactive({ username: '', password: '' });
const loading = ref(false);

async function doLogin() {
  if (!form.username || !form.password) {
    ElMessage.warning('请输入账号和密码');
    return;
  }
  loading.value = true;
  try {
    await store.login(form.username, form.password);
    ElMessage.success('登录成功');
    router.push('/');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-wrap {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1f2d3d 0%, #2b4a6f 100%);
}
.login-card {
  width: 380px;
  padding: 40px 36px 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
}
.brand { text-align: center; margin-bottom: 28px; }
.logo {
  width: 56px; height: 56px; margin: 0 auto 12px;
  border-radius: 14px; background: #2b4a6f; color: #fff;
  font-size: 24px; font-weight: 700; line-height: 56px;
}
.brand h1 { font-size: 20px; margin: 0 0 6px; color: #1f2d3d; }
.brand p { font-size: 12px; color: #909399; margin: 0; }
.submit { width: 100%; margin-top: 6px; }
.tips { text-align: center; font-size: 12px; color: #c0c4cc; margin-top: 18px; }
</style>
