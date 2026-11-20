import { ElMessage } from 'element-plus';

/**
 * 行内快捷改值（对齐 KASUSHOU 列表页 row.xxxEdit 机制：非编辑态不插输入框，
 * 点击值/edit 图标进入编辑态，回车/失焦提交，值不变不发请求，成功后不整表刷新）。
 *
 * 用法（列表页 script setup 内）：
 *   const { vFocus, startInline, commitInline, copyText } = useInlineEdit(goodsModify);
 * 模板：
 *   <el-input v-if="row.sortEdit" v-model="row.sortDraft" v-focus @keyup.enter="commitInline(row,'sort')" @blur="commitInline(row,'sort')" />
 *   <span v-else class="inline-value" @click="startInline(row,'sort')">{{ row.sort }}</span>
 * 注意：row 必须是响应式对象（表格行直接来自接口数组时 reactive 代理已覆盖）。
 */
export function useInlineEdit(modifyFn) {
  /** 行内编辑输入框自动聚焦（局部指令：v-focus） */
  const vFocus = {
    mounted: (el) => el.querySelector('input')?.focus(),
  };

  function startInline(row, field) {
    row[`${field}Draft`] = row[field];
    row[`${field}Edit`] = true;
  }

  async function commitInline(row, field) {
    if (!row[`${field}Edit`]) return;
    row[`${field}Edit`] = false;
    const draft = String(row[`${field}Draft`] ?? '').trim();
    if (draft === String(row[field] ?? '') || draft === '') return; // 未变化不请求
    await modifyFn(row.id, field, draft);
    row[field] = draft;
    ElMessage.success('已更新');
  }

  async function copyText(text) {
    if (!text) return;
    await navigator.clipboard.writeText(String(text));
    ElMessage.success('复制成功');
  }

  return { vFocus, startInline, commitInline, copyText };
}
