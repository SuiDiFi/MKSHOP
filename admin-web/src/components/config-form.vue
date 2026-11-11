<template>
  <div class="page-card">
    <el-card shadow="never" v-loading="loading">
      <template #header>
        <span>{{ title }}（分组：{{ group }}）</span>
      </template>
      <el-form label-width="180px" style="max-width: 760px">
        <template v-for="item in list" :key="item.name">
          <!-- 开关 -->
          <el-form-item v-if="item.type === 'switch'" :label="item.info">
            <el-switch v-model="form[item.name]" />
          </el-form-item>
          <!-- 数字 -->
          <el-form-item v-else-if="item.type === 'number'" :label="item.info">
            <el-input-number v-model="form[item.name]" :step="1" />
          </el-form-item>
          <!-- 单选 -->
          <el-form-item v-else-if="item.type === 'radio' || item.type === 'select'" :label="item.info">
            <el-radio-group v-model="form[item.name]">
              <el-radio v-for="opt in optionsOf(item)" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </el-radio>
            </el-radio-group>
          </el-form-item>
          <!-- 多选 -->
          <el-form-item v-else-if="item.type === 'checkbox'" :label="item.info">
            <el-select v-model="form[item.name]" multiple clearable style="width: 320px">
              <el-option v-for="opt in optionsOf(item)" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </el-form-item>
          <!-- 文本域 -->
          <el-form-item v-else-if="item.type === 'textarea'" :label="item.info">
            <el-input v-model="form[item.name]" type="textarea" :rows="3" />
          </el-form-item>
          <!-- 富文本/JSON/其他 → 文本域兜底 -->
          <el-form-item v-else-if="item.type === 'editor' || item.type === 'diyTable'" :label="item.info">
            <el-input v-model="form[item.name]" type="textarea" :rows="4" placeholder="富文本/JSON 内容" />
          </el-form-item>
          <!-- 默认文本 -->
          <el-form-item v-else :label="item.info">
            <el-input v-model="form[item.name]" style="max-width: 380px" />
          </el-form-item>
          <div v-if="item.desc" class="field-desc">{{ item.desc }}</div>
        </template>
        <el-form-item v-if="list.length">
          <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
        </el-form-item>
        <el-empty v-if="!loading && !list.length" description="该分组暂无配置项" />
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { configGroupForm, saveConfigGroup } from '@/api';

const props = defineProps({
  group: { type: String, required: true },
  title: { type: String, default: '系统配置' },
});

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const form = reactive({});

function optionsOf(item) {
  try {
    const arr = typeof item.parameter === 'string' && item.parameter
      ? JSON.parse(item.parameter)
      : item.parameter || [];
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

async function load() {
  loading.value = true;
  try {
    const res = await configGroupForm(props.group);
    list.value = res.data.list || [];
    for (const item of list.value) {
      form[item.name] = item.value;
    }
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const data = {};
    for (const item of list.value) {
      let v = form[item.name];
      if (item.type === 'checkbox' && Array.isArray(v)) v = v.join(',');
      if (item.type === 'diyTable' || item.type === 'editor') v = typeof v === 'string' ? v : JSON.stringify(v);
      data[item.name] = v;
    }
    await saveConfigGroup(props.group, data);
    ElMessage.success('配置已保存');
  } finally {
    saving.value = false;
  }
}

watch(() => props.group, load);
onMounted(load);
</script>

<style scoped>
.field-desc { margin: -14px 0 8px 180px; font-size: 12px; color: #909399; }
</style>
