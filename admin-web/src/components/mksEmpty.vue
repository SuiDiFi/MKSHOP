<template>
  <div class="mks-data-empty">
    <mks-icon :name="icon" class="mks-empty-icon" />
    <div class="txt">{{ text }}</div>
    <div v-if="hint" class="hint">{{ hint }}</div>
  </div>
</template>

<script setup>
/**
 * 空状态（对方用插画占位而非 Element 默认"暂无数据"文字）
 */
import mksIcon from '@/components/mksIcon.vue';

defineProps({
  text: { type: String, default: '暂无数据' },
  hint: { type: String, default: '' },
  icon: { type: String, default: 'inbox-line' },
});
</script>

<style scoped>
.mks-data-empty .txt {
  font-size: var(--mks-font-size);
  color: var(--mks-text-secondary);
}
.mks-data-empty .hint {
  margin-top: 6px;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-placeholder);
}
</style>
