<template>
  <div class="mks-pic">
    <div v-for="(u, i) in list" :key="i + u" class="mks-pic-item">
      <el-image :src="u" fit="cover" class="mks-pic-img" :preview-src-list="previewList" :initial-index="i"
        preview-teleported />
      <div class="mks-pic-bar">
        <el-button link size="small" :disabled="i === 0" @click="move(i, -1)">左移</el-button>
        <el-button link type="danger" size="small" @click="remove(i)">删除</el-button>
      </div>
    </div>

    <el-upload v-if="list.length < max" :show-file-list="false" :http-request="doUpload" accept="image/*"
      :multiple="max > 1" class="mks-pic-up">
      <div class="mks-pic-add">
        <mks-icon name="add-line" :size="20" />
        <span>{{ list.length }}/{{ max }}</span>
      </div>
    </el-upload>

    <div class="mks-pic-side">
      <el-button link type="primary" size="small" :disabled="list.length >= max" @click="addByUrl">网络图片</el-button>
    </div>
    <div v-if="tip" class="mks-pic-tip">{{ tip }}</div>
  </div>
</template>

<script setup>
/**
 * 图片上传（对齐 KASUSHOU PicUploader：max 张数 + 「网络图片」入口 + 尺寸提示）
 * 后端 POST file/upload 已就绪，返回 {url:'/uploads/goods/…'}；这里只认数组模型。
 */
import { computed, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import service from '@/utils/request';
import mksIcon from '@/components/mksIcon.vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 1 },
  tip: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const list = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const previewList = computed(() => list.value);
const uploading = ref(0);

function commit(next) {
  emit('update:modelValue', next.slice(0, props.max));
}

function remove(i) {
  const next = [...list.value];
  next.splice(i, 1);
  commit(next);
}

/** 轮播图顺序有意义（前台第一张即封面），所以要能调序 */
function move(i, delta) {
  const next = [...list.value];
  const j = i + delta;
  if (j < 0 || j >= next.length) return;
  [next[i], next[j]] = [next[j], next[i]];
  commit(next);
}

async function doUpload(options) {
  if (list.value.length + uploading.value >= props.max) return;
  uploading.value += 1;
  const fd = new FormData();
  fd.append('file', options.file);
  fd.append('dir', 'goods');
  try {
    const res = await service.post('/index.php', fd, { params: { s: '/admin/file/upload' } });
    const url = res.data?.url || res.data?.path;
    if (!url) return ElMessage.error('上传结果缺少地址');
    commit([...list.value, url]);
    ElMessage.success(res.msg || '上传成功');
  } finally {
    uploading.value -= 1;
  }
}

async function addByUrl() {
  try {
    const { value } = await ElMessageBox.prompt('填写图片地址（http(s):// 或 /uploads/…）', '网络图片', {
      inputPattern: /^(https?:\/\/|\/)\S+$/i,
      inputErrorMessage: '地址需以 http(s):// 或 / 开头',
    });
    commit([...list.value, value.trim()]);
  } catch (e) {
    /* 取消 */
  }
}
</script>

<style scoped>
.mks-pic {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 8px;
  width: 100%;
}
.mks-pic-item {
  position: relative;
  width: 84px;
  height: 84px;
  border: 1px solid var(--mks-border);
  border-radius: var(--mks-radius);
  overflow: hidden;
}
.mks-pic-img {
  width: 100%;
  height: 100%;
}
.mks-pic-bar {
  position: absolute;
  inset: auto 0 0 0;
  display: flex;
  justify-content: space-between;
  padding: 0 4px;
  height: 20px;
  line-height: 20px;
  background: rgba(0, 0, 0, 0.45);
}
.mks-pic-bar :deep(.el-button) {
  color: #fff;
}
.mks-pic-up :deep(.el-upload) {
  border: 1px dashed var(--mks-border);
  border-radius: var(--mks-radius);
  cursor: pointer;
}
.mks-pic-add {
  width: 84px;
  height: 84px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--mks-text-placeholder);
  font-size: var(--mks-font-size-table);
}
.mks-pic-side {
  width: 100%;
}
.mks-pic-tip {
  width: 100%;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-placeholder);
}
</style>
