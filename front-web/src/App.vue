<template>
  <router-view />
  <div v-if="closed" class="site-closed">
    <el-icon :size="46"><Lock /></el-icon>
    <div class="closed-title">{{ site.name || '站点维护中' }}</div>
    <div class="closed-tip">{{ site.close_tip || '站点维护中，请稍后再试' }}</div>
  </div>
  <div v-if="showKefu && !closed" class="kefu-fab" title="在线客服" :style="{ background: themeColor }" @click="openKefu">
    <el-icon :size="22"><ChatDotRound /></el-icon>
    <span>客服</span>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChatDotRound, Lock } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { siteInfo } from '@/api';

const route = useRoute();
const router = useRouter();
const site = ref({});
const themeColor = computed(() => site.value.theme_color || '#409eff');
const closed = computed(() => Number(site.value.close_site) === 1);
const showKefu = computed(() => route.name !== 'Kefu');

onMounted(async () => {
  try {
    const res = await siteInfo();
    site.value = res.data || {};
    const title = site.value.seo_title || site.value.name;
    if (title) document.title = title;
    const color = site.value.theme_color;
    if (color) {
      const root = document.documentElement;
      root.style.setProperty('--el-color-primary', color);
      root.style.setProperty('--site-theme', color);
    }
  } catch (e) { /* 主站默认主题 */ }
});

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
.kefu-fab:hover { transform: translateY(-2px); }
.site-closed {
  position: fixed; inset: 0; z-index: 9999; background: #f5f7fa; color: #303133;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
}
.closed-title { font-size: 22px; font-weight: 700; }
.closed-tip { color: #909399; }
</style>
