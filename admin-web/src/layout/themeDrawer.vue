<template>
  <el-drawer v-model="visible" :size="280" direction="rtl" title="主题配置" class="mks-theme-drawer">
    <el-form label-position="left" label-width="72px" size="small">
      <el-form-item>
        <template #label>
          布局
          <el-tooltip content="导航布局形态；分栏为 KASUSHOU 线上默认" placement="top">
            <mks-icon name="question-line" :size="13" class="q" />
          </el-tooltip>
        </template>
        <el-select v-model="s.layout">
          <el-option v-for="o in LAYOUT_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </el-form-item>

      <el-form-item label="主题">
        <el-select v-model="s.theme">
          <el-option v-for="o in THEME_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </el-form-item>

      <el-form-item>
        <template #label>
          菜单背景
          <el-tooltip content="侧栏使用背景图（对方 vab-background）" placement="top">
            <mks-icon name="question-line" :size="13" class="q" />
          </el-tooltip>
        </template>
        <el-radio-group v-model="s.background">
          <el-radio-button value="none">无</el-radio-button>
          <el-radio-button value="mks-background">有</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="标签"><el-switch v-model="s.showTabs" /></el-form-item>
      <el-form-item label="标签图标"><el-switch v-model="s.showTabsIcon" /></el-form-item>

      <el-form-item label="标签风格">
        <el-select v-model="s.tabsBarStyle">
          <el-option v-for="o in TABS_STYLE_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </el-form-item>

      <el-form-item>
        <template #label>
          分栏风格
          <el-tooltip content="分栏布局下一级渠道列的排列方式" placement="top">
            <mks-icon name="question-line" :size="13" class="q" />
          </el-tooltip>
        </template>
        <el-select v-model="s.columnStyle" :disabled="s.layout !== 'column'">
          <el-option v-for="o in COLUMN_STYLE_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </el-form-item>

      <el-form-item label="头部固定"><el-switch v-model="s.fixedHeader" /></el-form-item>
      <el-form-item label="进度条"><el-switch v-model="s.showProgressBar" /></el-form-item>
      <el-form-item label="刷新"><el-switch v-model="s.showRefresh" /></el-form-item>
      <el-form-item label="通知"><el-switch v-model="s.showNotice" /></el-form-item>
      <el-form-item label="全屏"><el-switch v-model="s.showFullScreen" /></el-form-item>
      <el-form-item label="搜索"><el-switch v-model="s.showSearch" /></el-form-item>
      <el-form-item label="国际化"><el-switch v-model="s.showLanguage" /></el-form-item>
    </el-form>

    <template #footer>
      <div class="drawer-footer">
        <el-button type="primary" @click="save">保存</el-button>
        <el-button @click="reset">恢复默认</el-button>
      </div>
    </template>
  </el-drawer>
</template>

<script setup>
/**
 * 主题配置抽屉（对齐 KASUSHOU：右上调色板图标 → 280px 右抽屉，footer＝保存/恢复默认）
 * 选项清单与默认值来自浏览器实测取证（docs/UI-后台对齐KASUSHOU.md §1.3-3）
 */
import { computed } from 'vue';
import { ElMessage } from 'element-plus';
import { useSettingsStore, LAYOUT_OPTIONS, THEME_OPTIONS, TABS_STYLE_OPTIONS, COLUMN_STYLE_OPTIONS } from '@/store/settings';
import mksIcon from '@/components/mksIcon.vue';

const props = defineProps({ modelValue: { type: Boolean, default: false } });
const emit = defineEmits(['update:modelValue']);

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

// el-form 直接绑定 store 实例：抽屉内改动即时预览（与对方行为一致），点保存才落盘
const s = useSettingsStore();

function save() {
  s.save();
  ElMessage.success('主题已保存');
  visible.value = false;
}
function reset() {
  s.reset();
  ElMessage.success('已恢复默认设置');
}
</script>

<style scoped>
.q {
  color: var(--mks-text-placeholder);
  margin-left: 3px;
  cursor: help;
}
.drawer-footer {
  display: flex;
  gap: 10px;
}
.mks-theme-drawer :deep(.el-drawer__body) {
  padding: 16px;
}
</style>
