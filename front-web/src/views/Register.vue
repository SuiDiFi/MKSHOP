<template>
  <div>
    <Topbar />
    <div class="wrap" style="max-width: 420px; margin-top: 60px;">
      <el-card shadow="never">
        <h3 style="text-align:center; margin-top: 0">注册账号</h3>
        <el-form @submit.prevent="doRegister">
          <el-form-item>
            <el-input v-model="form.username" placeholder="账号（至少 3 位）" size="large">
              <template #prefix><el-icon><User /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="form.password" type="password" placeholder="密码（至少 6 位）" size="large" show-password>
              <template #prefix><el-icon><Lock /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="form.invite_code" placeholder="邀请码（选填）" size="large">
              <template #prefix><el-icon><Promotion /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-button type="primary" size="large" style="width: 100%" :loading="loading" @click="doRegister">注 册</el-button>
          <div style="text-align:center; margin-top: 12px; font-size: 14px">
            已有账号？<router-link :to="{ name: 'Login' }" style="color:#409eff">直接登录</router-link>
          </div>
        </el-form>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { memberRegister } from '@/api';
import { useUserStore } from '@/store/user';

const form = ref({ username: '', password: '', invite_code: '' });
const loading = ref(false);
const store = useUserStore();
const router = useRouter();

const doRegister = async () => {
  if (!form.value.username || !form.value.password) {
    ElMessage.warning('请输入账号和密码');
    return;
  }
  loading.value = true;
  try {
    const res = await memberRegister(form.value);
    store.setToken(res.data?.token);
    store.userinfo = res.data?.userinfo || null;
    ElMessage.success('注册成功');
    router.push('/');
  } catch (e) { /* ignore */ } finally {
    loading.value = false;
  }
};
</script>
