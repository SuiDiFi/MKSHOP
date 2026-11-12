<template>
  <router-view />
  <div v-if="showKefu" class="kefu-fab" title="在线客服" @click="openKefu">
    <el-icon :size="22"><ChatDotRound /></el-icon>
    <span>客服</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChatDotRound } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const route = useRoute();
const router = useRouter();

const showKefu = computed(() => route.name !== 'Kefu');

const openKefu = () => {
  if (!localStorage.getItem('mks_member_token')) {
    ElMessage.warning('请先登录后再联系客服');
    router.push({ name: 'Login', query: { redirect: '/kefu' } });
    return;
  }
  router.push('/kefu');
};
</script>

<style scoped>
.kefu-fab {
  position: fixed; right: 26px; bottom: 90px; z-index: 2000;
  width: 56px; height: 56px; border-radius: 50%;
  background: #409eff; color: #fff; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  box-shadow: 0 6px 18px rgba(64, 158, 255, .35);
  transition: transform .2s, background .2s;
  font-size: 11px; gap: 1px;
}
.kefu-fab:hover { transform: translateY(-2px); background: #337ecc; }
</style>
