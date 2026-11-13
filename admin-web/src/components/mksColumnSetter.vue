<template>
  <button type="button" class="mks-pill" @click="open">
    <mks-icon name="list-settings-line" :size="14" /> 自定义表格列
  </button>

  <el-dialog v-model="visible" title="自定义列" width="500px" append-to-body>
    <el-checkbox v-model="checkAll" :indeterminate="indeterminate" class="mks-checkall" @change="toggleAll">
      全选
    </el-checkbox>
    <el-divider style="margin: 8px 0 14px" />
    <el-checkbox-group v-model="picked" class="mks-col-group" @change="syncState">
      <el-checkbox v-for="c in columns" :key="c.prop" :value="c.prop" :disabled="c.locked">{{ c.label }}</el-checkbox>
    </el-checkbox-group>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="save">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
/**
 * 自定义表格列（对齐 KASUSHOU 订单列表：500px dialog + 全选 + 列 checkbox + 取消/确定）
 * v-model 为"可见列 prop 数组"，由本组件按 storeKey 读写 localStorage，
 * 页面首屏即可拿到持久化结果（之前由组件内部持有状态会导致页面渲染与存储不一致）
 */
import { ref, computed, onMounted } from 'vue';
import mksIcon from '@/components/mksIcon.vue';

const props = defineProps({
  columns: { type: Array, required: true },              // [{prop, label, locked?, visible?}]
  modelValue: { type: Array, default: () => [] },
  storeKey: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'change']);

const KEY = (k) => `mks_cols_${k || 'default'}`;
const visible = ref(false);
const picked = ref([...props.modelValue]);

const allProps = computed(() => props.columns.map((c) => c.prop));
const lockedProps = computed(() => props.columns.filter((c) => c.locked).map((c) => c.prop));
const checkAll = ref(false);
const indeterminate = computed(() => picked.value.length > 0 && picked.value.length < allProps.value.length);

function defaults() {
  return props.columns.filter((c) => c.visible !== false).map((c) => c.prop);
}

function readStored() {
  try {
    const raw = props.storeKey ? localStorage.getItem(KEY(props.storeKey)) : null;
    const arr = raw ? JSON.parse(raw) : null;
    return Array.isArray(arr) ? arr.filter((p) => allProps.value.includes(p)) : null;
  } catch (e) {
    return null;
  }
}

onMounted(() => {
  const stored = readStored();
  const next = stored && stored.length ? stored : defaults();
  if (JSON.stringify(next) !== JSON.stringify(props.modelValue)) emit('update:modelValue', next);
});

function open() {
  picked.value = [...props.modelValue];
  syncState();
  visible.value = true;
}

function toggleAll(v) {
  picked.value = v ? [...allProps.value] : [...lockedProps.value];
  syncState();
}

function syncState() {
  checkAll.value = picked.value.length === allProps.value.length;
}

function save() {
  // 锁定列必须可见
  picked.value = Array.from(new Set([...lockedProps.value, ...picked.value]));
  if (props.storeKey) {
    try {
      localStorage.setItem(KEY(props.storeKey), JSON.stringify(picked.value));
    } catch (e) {
      /* 存储不可用时静默降级 */
    }
  }
  emit('update:modelValue', [...picked.value]);
  emit('change', [...picked.value]);
  visible.value = false;
}
</script>

<style scoped>
.mks-col-group {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px 0;
}
.mks-checkall {
  font-weight: 500;
}
</style>
