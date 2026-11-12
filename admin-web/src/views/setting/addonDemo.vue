<template>
  <div>
    <el-card shadow="never">
      <template #header>
        <b>演示插件</b>
      </template>
      <el-alert type="info" :closable="false" show-icon
        title="该页面由插件安装时注入后台菜单（plugin.json → menus）；停用插件不影响菜单，卸载后菜单消失。" />
      <div class="row">
        <span class="label">插件自检接口</span>
        <code>/addons/demo/index/index</code>
        <el-button size="small" @click="load">请求</el-button>
      </div>
      <el-input v-model="text" type="textarea" :rows="12" readonly />
      <div class="tip">
        钩子验证：启用插件后完成一笔订单，可在服务器 runtime 日志中看到
        <code>[addon:demo] order.paid</code> / <code>[addon:demo] order.success</code>。
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const text = ref('');
const load = async () => {
  const base = import.meta.env.VITE_API_BASE || '';
  try {
    const res = await fetch(`${base}/index.php?s=/addons/demo/index/index`);
    text.value = JSON.stringify(await res.json(), null, 2);
  } catch (e) {
    text.value = '请求失败：' + e.message;
  }
};

onMounted(load);
</script>

<style scoped>
.row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 14px 0;
}
.label {
  color: #606266;
}
.tip {
  margin-top: 12px;
  color: #909399;
  font-size: 13px;
}
</style>
