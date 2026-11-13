<template>
  <div class="mks-query-form">
    <el-form :model="model" inline label-width="0" @submit.prevent>
      <!-- 常显筛选项（建议 ≤5 个，与 KASUSHOU 默认折叠态一致） -->
      <slot name="short" />

      <!-- 折叠区：仅在展开时渲染 -->
      <template v-if="expanded">
        <slot name="more" />
      </template>

      <el-form-item>
        <el-button type="primary" :loading="loading" @click="$emit('search')">
          <mks-icon name="search-line" class="btn-ico" /> 查询
        </el-button>
        <el-button @click="$emit('reset')">
          <mks-icon name="refresh-line" class="btn-ico" /> 重置
        </el-button>
        <!-- 对方文案是「展开 / 合并」（不是"收起"） -->
        <span v-if="hasMore" class="mks-fold-btn" :class="{ 'is-open': expanded }" @click="expanded = !expanded">
          {{ expanded ? '合并' : '展开' }}<mks-icon name="arrow-up-s-line" :size="14" />
        </span>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
/**
 * 列表页查询区（对齐 KASUSHOU：默认只显示 5 项，其余折叠；按钮文案「展开/合并」）
 * 用法：把高频筛选放 #short，其余放 #more
 */
import { ref, computed, useSlots } from 'vue';
import mksIcon from '@/components/mksIcon.vue';

defineProps({
  model: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});
defineEmits(['search', 'reset']);

const slots = useSlots();
const expanded = ref(false);
const hasMore = computed(() => !!slots.more);
</script>

<style scoped>
.mks-query-form {
  padding: 16px 16px 0;
  margin-bottom: 12px;
  background: var(--mks-bg-white);
  border: 1px solid var(--mks-border-light);
  border-radius: var(--mks-radius-panel);
}
.mks-query-form :deep(.el-form-item) {
  margin-right: 12px;
  margin-bottom: 16px;
}
.btn-ico {
  margin-right: 4px;
}
.mks-fold-btn {
  margin-left: 10px;
  vertical-align: middle;
}
</style>
