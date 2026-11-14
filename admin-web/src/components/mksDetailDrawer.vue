<template>
  <el-drawer v-model="show" :size="size" append-to-body class="mks-detail-drawer">
    <template #header>
      <div class="mks-drawer-head">
        <div class="mks-drawer-title">
          <h3>{{ title }}</h3>
          <div class="mks-drawer-actions">
            <slot name="actions" />
          </div>
        </div>
        <slot name="top" />
      </div>
    </template>

    <el-tabs v-model="active" class="mks-drawer-tabs">
      <el-tab-pane v-for="t in tabs" :key="t.name" :label="t.label" :name="t.name">
        <!-- 首次激活才渲染，对齐对方"页签级懒加载" -->
        <template v-if="visited.has(t.name)">
          <slot :name="t.name" :tab="t" />
        </template>
      </el-tab-pane>
    </el-tabs>
  </el-drawer>
</template>

<script setup>
/**
 * 详情抽屉（KASUSHOU 1000px + 动态页签 + 页签级懒加载，无底部按钮）
 * tabs 由使用方按数据算出（对方 tabList 的显隐规则），name 同时作为具名插槽名。
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  active: { type: String, default: '' },
  title: { type: String, default: '详情' },
  size: { type: String, default: '1000px' },
  tabs: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'update:active', 'tab-load']);

const show = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});
const active = ref(props.active || props.tabs[0]?.name || '');
const visited = ref(new Set());

function touch(name) {
  if (!name || visited.value.has(name)) return;
  visited.value.add(name);
  emit('tab-load', name);
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return;
    if (!props.tabs.some((t) => t.name === active.value)) active.value = props.tabs[0]?.name || '';
    touch(active.value);
  }
);
watch(active, (name) => {
  emit('update:active', name);
  touch(name);
});
// 抽屉开着时切换页签数据源（如换单查看）
watch(() => props.active, (name) => {
  if (name && name !== active.value) active.value = name;
});
</script>

<style>
/* 抽屉外壳样式需要作用到使用方通过插槽传入的内容，故不 scoped */
.mks-detail-drawer .mks-drawer-head h3 {
  margin: 0;
  padding-left: 8px;
  font-size: 14px;
  font-weight: 600;
  line-height: 18px;
  border-left: 3px solid var(--mks-primary);
}
.mks-detail-drawer .mks-drawer-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.mks-detail-drawer .mks-drawer-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.mks-detail-drawer .el-drawer__body {
  padding-top: 6px;
}
.mks-detail-drawer .el-tabs__header {
  margin-bottom: 12px;
}
/* 页头主信息区（图 + 单号 + 复制） */
.mks-dt-head {
  margin-top: 4px;
}
.mks-dt-main {
  display: flex;
  gap: 10px;
  align-items: center;
}
.mks-dt-img {
  width: 60px;
  height: 60px;
  flex: none;
  border-radius: var(--mks-radius-pill);
}
.mks-dt-goods {
  margin-bottom: 4px;
  font-size: var(--mks-font-size);
}
.mks-dt-copy {
  margin-left: 6px;
  color: var(--mks-text-placeholder);
}
/* 页头关键指标行（对方 Top 下方 4 项 li） */
.mks-dt-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 24px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-secondary);
}
.mks-dt-meta b {
  margin-left: 6px;
  font-weight: 600;
  color: var(--mks-text-regular);
}
</style>

