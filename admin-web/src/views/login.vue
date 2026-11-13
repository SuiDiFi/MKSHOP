<template>
  <div class="login-page">
    <div class="login-wrap">
      <div class="login">
        <!-- 左侧品牌区（自绘渐变，不使用对方素材） -->
        <div class="login-left">
          <div class="brand-logo">MK</div>
          <div class="brand-name">MKSHOP</div>
          <div class="brand-desc">虚拟商品电商系统 · 卡密 / 直充 / 上游供货 / 多端</div>
        </div>

        <!-- 右侧表单区（对方规范：表单宽 324px，标题 22px，按钮 48px） -->
        <div class="login-right">
          <div class="login-title">账号密码登录</div>
          <div class="login-desc">欢迎登录后台管理系统</div>
          <el-form :model="form" @keyup.enter="doLogin">
            <el-form-item>
              <el-input v-model="form.username" placeholder="请输入账号" clearable class="mks-input">
                <template #prefix><mks-icon name="user-line" :size="15" /></template>
              </el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="form.password" type="password" placeholder="请输入密码" show-password class="mks-input">
                <template #prefix><mks-icon name="lock-line" :size="15" /></template>
              </el-input>
            </el-form-item>
            <el-button type="primary" class="submit" :loading="loading" @click="doLogin">登录</el-button>
          </el-form>
          <div class="tips">MKSHOP v0.1.0 · ThinkPHP6 + Vue3 + Element Plus</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 登录页（对齐 KASUSHOU 实测：903×518 双栏卡片、radius 10、shadow 0 6px 40px rgba(0,0,0,.08)、
 * 左 415px / 右 488px、表单 324px、input 40px、按钮 48px）
 */
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/user';
import mksIcon from '@/components/mksIcon.vue';

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
.login-page {
  height: 100%;
  background: var(--mks-bg);
  font-family: var(--mks-font-family);
}
.login-wrap {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.login {
  display: flex;
  width: 903px;
  max-width: calc(100vw - 32px);
  height: 518px;
  background: var(--mks-bg-white);
  border-radius: var(--mks-radius-card);
  box-shadow: var(--mks-shadow-login);
  overflow: hidden;
}

/* 左栏 415px */
.login-left {
  flex: none;
  width: 415px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0 40px;
  color: #fff;
  background: linear-gradient(135deg, #006cff 0%, #399efd 100%);
}
.brand-logo {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  font-weight: 700;
  color: var(--mks-primary);
  background: #fff;
  border-radius: var(--mks-radius-panel);
  margin-bottom: 22px;
}
.brand-name {
  font-size: 30px;
  font-weight: 700;
  letter-spacing: 2px;
}
.brand-desc {
  margin-top: 14px;
  font-size: var(--mks-font-size);
  line-height: 22px;
  opacity: 0.9;
}

/* 右栏 488px：表单 324px，顶部留白 120px */
.login-right {
  flex: 1;
  min-width: 0;
  padding: 120px 40px 0;
}
.login-title {
  font-size: var(--mks-font-size-title);
  color: var(--mks-text-title);
}
.login-desc {
  margin: 8px 0 34px;
  font-size: var(--mks-font-size);
  color: var(--mks-text-placeholder);
}
.login-right :deep(.el-form) {
  width: 324px;
  max-width: 100%;
}
.mks-input :deep(.el-input__wrapper) {
  height: 40px;
  box-shadow: 0 0 0 1px var(--mks-border) inset;
}
.mks-input :deep(.el-input__inner) {
  font-size: var(--mks-font-size);
}
.submit {
  width: 324px;
  max-width: 100%;
  height: 48px;
  margin-top: 8px;
  font-size: 16px;
  border-radius: 2px;
}
.tips {
  margin-top: 22px;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-placeholder);
}

@media (max-width: 960px) {
  .login-left {
    display: none;
  }
  .login {
    height: auto;
    min-height: 460px;
  }
  .login-right {
    padding: 60px 28px;
  }
}
</style>
