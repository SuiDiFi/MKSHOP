<template>
  <div class="mks-page">
    <!-- 引导条（对方每个业务列表都有：el-alert--success is-light + 快捷入口内链） -->
    <el-alert v-if="$slots.alert" type="success" :closable="true" show-icon class="mks-guide">
      <template #title><slot name="alert" /></template>
    </el-alert>

    <slot name="query" />

    <div class="mks-card">
      <slot name="status" />
      <div v-if="$slots.toolbar" class="toolbar">
        <slot name="toolbar" />
      </div>
      <slot />
      <div v-if="$slots.pager" class="pager">
        <slot name="pager" />
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 列表页统一容器（对齐 KASUSHOU 列表页结构：引导条 → 查询区 → 卡片{页签/工具栏/表格/分页}）
 * 工具栏统一左上，分页统一卡片底部左对齐
 */
defineProps({
  /** 预留：紧凑模式（财务类页面行高更小的场景） */
  compact: { type: Boolean, default: false },
});
</script>

<style scoped>
.mks-page {
  min-height: 100%;
}
.mks-guide {
  margin-bottom: 12px;
  border-radius: var(--mks-radius-panel);
}
.mks-card {
  padding: 12px 16px 16px;
  background: var(--mks-bg-white);
  border: 1px solid var(--mks-border-light);
  border-radius: var(--mks-radius-panel);
}
.mks-card > .toolbar {
  margin-bottom: 12px;
}
.mks-card > .pager {
  margin-top: 14px;
}
</style>
