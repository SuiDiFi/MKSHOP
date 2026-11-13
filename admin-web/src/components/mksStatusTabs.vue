<template>
  <div class="mks-status-tabs">
    <!-- 上层：带数量角标的页签 -->
    <el-tabs v-if="tabs && tabs.length" :model-value="modelValue" class="mks-tabs" @update:model-value="(v) => emit('update:modelValue', v)">
      <el-tab-pane v-for="t in tabs" :key="t.value" :name="t.value">
        <template #label>
          <span class="tab-lab">
            {{ t.label }}
            <span v-if="t.count !== undefined && t.count !== null" class="tab-count">({{ t.count }})</span>
          </span>
        </template>
      </el-tab-pane>
    </el-tabs>

    <!-- 下层：胶囊状态按钮排（对方 .button-box.order-status-bar，radius 16px） -->
    <div v-if="pills && pills.length" class="mks-status-bar">
      <button
        v-for="p in pills"
        :key="p.value"
        type="button"
        class="mks-pill"
        :class="{ 'is-active': p.value === pillValue }"
        @click="emit('update:pillValue', p.value)"
      >
        {{ p.label }}<span v-if="p.count" class="pill-count">{{ p.count }}</span>
      </button>
      <slot name="extra" />
    </div>
  </div>
</template>

<script setup>
/**
 * 列表页状态筛选（对齐 KASUSHOU 订单页「双轨筛选」：上 el-tabs 带数量 + 下胶囊按钮排）
 * 只用其中一种时传对应 prop 即可
 */
defineProps({
  modelValue: { type: [String, Number], default: '' },
  pillValue: { type: [String, Number], default: '' },
  tabs: { type: Array, default: () => [] },   // [{label, value, count?}]
  pills: { type: Array, default: () => [] },  // [{label, value, count?}]
});
const emit = defineEmits(['update:modelValue', 'update:pillValue']);
</script>

<style scoped>
.mks-status-tabs {
  margin-bottom: 4px;
}
.mks-tabs {
  margin-bottom: 4px;
}
.mks-tabs :deep(.el-tabs__header) {
  margin-bottom: 0;
}
.mks-tabs :deep(.el-tabs__item) {
  height: 40px;
  line-height: 40px;
  font-size: var(--mks-font-size);
}
.tab-lab {
  /* 标签与数量必须同排：页签是 flex 项，被挤压时数量会掉到第二行 */
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}
.tab-count {
  margin-left: 4px;
  color: var(--mks-text-placeholder);
  font-size: var(--mks-font-size-table);
}
/* 胶囊里的数量要拉开间距（对方为“交易成功 3”，不是粘连的“交易成功3”） */
.pill-count {
  margin-left: 6px;
  opacity: 0.85;
}
.mks-status-bar {
  margin-bottom: 0;
}
</style>
