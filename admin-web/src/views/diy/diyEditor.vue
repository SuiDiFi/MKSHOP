<template>
  <div class="diy-editor">
    <!-- 顶部栏 -->
    <div class="editor-top">
      <span class="page-title">{{ page.title || 'DIY 装修' }}</span>
      <el-select v-model="templateKey" placeholder="选择模板" size="small" style="width: 180px" @change="applyTemplate">
        <el-option v-for="t in templates" :key="t.key" :label="t.name" :value="t.key" />
      </el-select>
      <el-radio-group v-model="status" size="small">
        <el-radio-button :value="0">草稿</el-radio-button>
        <el-radio-button :value="1">发布</el-radio-button>
      </el-radio-group>
      <el-button type="primary" size="small" :loading="saving" @click="doSave">保存</el-button>
      <el-button size="small" @click="doPreview">预览</el-button>
      <el-button size="small" @click="$router.back()">返回</el-button>
    </div>

    <div class="editor-body">
      <!-- 左：组件库 -->
      <div class="panel">
        <div class="panel-title">组件库</div>
        <div v-for="c in library" :key="c.name" class="lib-item" @click="addComponent(c)">
          <div class="lib-name">{{ c.title }}</div>
          <div class="lib-desc">{{ c.desc }}</div>
        </div>
      </div>

      <!-- 中：画布（组件序列） -->
      <div class="canvas">
        <div v-for="(c, idx) in comps" :key="idx" class="comp-block"
          :class="{ active: activeIdx === idx }" @click="activeIdx = idx">
          <div class="comp-head">
            <span>{{ c.title }}（{{ c.name }}）</span>
            <span>
              <el-button link size="small" :disabled="idx === 0" @click.stop="move(idx, -1)">上移</el-button>
              <el-button link size="small" :disabled="idx === comps.length - 1" @click.stop="move(idx, 1)">下移</el-button>
              <el-button link type="danger" size="small" @click.stop="removeComp(idx)">删除</el-button>
            </span>
          </div>
          <!-- 组件预览摘要 -->
          <div class="comp-preview">
            <template v-if="c.name === 'Search'">
              <span class="muted">搜索框：{{ c.value.placeholder || '搜索商品' }}</span>
            </template>
            <template v-else-if="c.name === 'ImageAd'">
              <el-tag size="small">{{ c.value.img ? '已配置图片' : '未配置图片' }}</el-tag>
              <span class="muted" style="margin-left:6px">高度 {{ c.value.height || 120 }}px</span>
            </template>
            <template v-else-if="c.name === 'CategoryNav'">
              <span class="muted">{{ c.value.title || '商品分类' }} · 最多 {{ c.value.limit || 8 }} 个</span>
            </template>
            <template v-else-if="c.name === 'Banner'">
              <el-tag v-for="(b, i) in (c.value.list || [])" :key="i" size="small" style="margin-right:6px">{{ b.img ? '图' + (i + 1) : '空图' + (i + 1) }}</el-tag>
              <span v-if="!(c.value.list || []).length" class="muted">未配置</span>
            </template>
            <template v-else-if="c.name === 'Notice'">
              <span class="muted">{{ c.value.text || '未配置公告' }}</span>
            </template>
            <template v-else-if="c.name === 'Menus'">
              <el-tag v-for="(m, i) in (c.value.list || [])" :key="i" size="small" style="margin-right:6px">{{ m.title || '菜单' + (i + 1) }}</el-tag>
              <span v-if="!(c.value.list || []).length" class="muted">未配置</span>
            </template>
            <template v-else-if="c.name === 'GoodsList'">
              <span class="muted">楼层「{{ c.value.title || '未命名' }}」· {{ sourceText(c.value) }}</span>
            </template>
            <template v-else-if="c.name === 'RichText'">
              <span class="muted">{{ (c.value.html || '').replace(/<[^>]+>/g, '').slice(0, 40) || '未配置' }}</span>
            </template>
          </div>
        </div>
        <el-empty v-if="!comps.length" description="从左侧添加组件" :image-size="60" />
      </div>

      <!-- 右：配置表单 -->
      <div class="panel" v-if="active">
        <div class="panel-title">配置 · {{ active.title }}</div>

        <template v-if="active.name === 'Search'">
          <el-form label-width="70px" size="small">
            <el-form-item label="占位文案"><el-input v-model="active.value.placeholder" /></el-form-item>
          </el-form>
        </template>

        <template v-else-if="active.name === 'ImageAd'">
          <el-form label-width="70px" size="small">
            <el-form-item label="图片"><el-input v-model="active.value.img" placeholder="图片 URL" /></el-form-item>
            <el-form-item label="跳转"><el-input v-model="active.value.url" placeholder="#/goods/1 或外链" /></el-form-item>
            <el-form-item label="高度"><el-input-number v-model="active.value.height" :min="60" :max="400" /></el-form-item>
          </el-form>
        </template>

        <template v-else-if="active.name === 'CategoryNav'">
          <el-form label-width="70px" size="small">
            <el-form-item label="标题"><el-input v-model="active.value.title" /></el-form-item>
            <el-form-item label="数量"><el-input-number v-model="active.value.limit" :min="1" :max="12" /></el-form-item>
          </el-form>
        </template>

        <template v-else-if="active.name === 'Banner'">
          <div v-for="(b, i) in active.value.list" :key="i" class="cfg-item">
            <el-input v-model="b.img" placeholder="图片 URL" size="small" style="margin-bottom:6px" />
            <div style="display:flex; gap:6px">
              <el-input v-model="b.url" placeholder="跳转链接（选填）" size="small" />
              <el-button type="danger" size="small" @click="active.value.list.splice(i, 1)">删</el-button>
            </div>
          </div>
          <el-button size="small" @click="active.value.list.push({ img: '', url: '' })">添加轮播图</el-button>
        </template>

        <template v-else-if="active.name === 'Notice'">
          <el-input v-model="active.value.text" type="textarea" :rows="3" placeholder="公告文案" />
        </template>

        <template v-else-if="active.name === 'Menus'">
          <div v-for="(m, i) in active.value.list" :key="i" class="cfg-item">
            <el-input v-model="m.icon" placeholder="图标 emoji 或 URL" size="small" style="margin-bottom:6px" />
            <div style="display:flex; gap:6px">
              <el-input v-model="m.title" placeholder="名称" size="small" />
              <el-input v-model="m.url" placeholder="路由 (#/goods/1)" size="small" />
              <el-button type="danger" size="small" @click="active.value.list.splice(i, 1)">删</el-button>
            </div>
          </div>
          <el-button size="small" @click="active.value.list.push({ icon: '', title: '', url: '' })">添加菜单</el-button>
        </template>

        <template v-else-if="active.name === 'GoodsList'">
          <el-form label-width="70px" size="small">
            <el-form-item label="楼层名"><el-input v-model="active.value.title" /></el-form-item>
            <el-form-item label="商品来源">
              <el-radio-group v-model="active.value.source">
                <el-radio value="cate">按分类</el-radio>
                <el-radio value="ids">指定商品</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item v-if="active.value.source === 'cate'" label="分类">
              <el-select v-model="active.value.cate_id" clearable style="width: 100%">
                <el-option v-for="cat in cates" :key="cat.id" :label="cat.name" :value="cat.id" />
              </el-select>
            </el-form-item>
            <el-form-item v-else label="商品ID">
              <el-input v-model="active.value.goods_ids" placeholder="逗号分隔，如 1,2" />
            </el-form-item>
            <el-form-item label="数量"><el-input-number v-model="active.value.num" :min="1" :max="12" /></el-form-item>
          </el-form>
        </template>

        <template v-else-if="active.name === 'RichText'">
          <el-input v-model="active.value.html" type="textarea" :rows="8" placeholder="HTML 内容" />
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { diyRead, diySave, diyComponents, categoryList } from '../../api';

const route = useRoute();
const router = useRouter();
const page = ref({});
const status = ref(0);
const comps = ref([]);
const activeIdx = ref(-1);
const saving = ref(false);
const cates = ref([]);
const templates = ref([]);
const templateKey = ref('');

// 组件默认配置
const defaultValues = {
  Search: { placeholder: '搜索商品' },
  Banner: { height: 280, list: [{ img: '', url: '' }] },
  Notice: { text: '' },
  Menus: { list: [{ icon: '', title: '', url: '' }] },
  CategoryNav: { title: '商品分类', limit: 8 },
  ImageAd: { img: '', url: '', height: 120 },
  GoodsList: { title: '热卖推荐', source: 'cate', cate_id: 0, goods_ids: '', num: 4 },
  RichText: { html: '' },
};
const library = ref([
  { name: 'Search', title: '搜索框', desc: '商品搜索入口' },
  { name: 'Banner', title: '轮播图', desc: '图片轮播，支持跳转链接' },
  { name: 'Notice', title: '公告条', desc: '公告文案' },
  { name: 'Menus', title: '金刚区', desc: '图标快捷导航' },
  { name: 'CategoryNav', title: '分类导航', desc: '按分类快捷进入' },
  { name: 'ImageAd', title: '单图广告', desc: '整行广告图' },
  { name: 'GoodsList', title: '商品楼层', desc: '按分类或指定商品展示' },
  { name: 'RichText', title: '富文本', desc: '自定义图文内容' },
]);

const active = computed(() => (activeIdx.value >= 0 ? comps.value[activeIdx.value] : null));
const sourceText = (v) => (v.source === 'ids' ? `指定ID ${v.goods_ids || '未填'}` : `分类 ${v.cate_id || '全部'} × ${v.num || 4}`);

const addComponent = (c) => {
  comps.value.push({ name: c.name, title: c.title, value: JSON.parse(JSON.stringify(defaultValues[c.name])) });
  activeIdx.value = comps.value.length - 1;
};
const move = (idx, dir) => {
  const target = idx + dir;
  [comps.value[idx], comps.value[target]] = [comps.value[target], comps.value[idx]];
  activeIdx.value = target;
};
const removeComp = (idx) => {
  comps.value.splice(idx, 1);
  activeIdx.value = -1;
};
const doSave = async () => {
  saving.value = true;
  try {
    await diySave(page.value.id, { title: page.value.title, status: status.value, components: comps.value });
    ElMessage.success('保存成功');
  } finally {
    saving.value = false;
  }
};
const applyTemplate = async (key) => {
  const t = templates.value.find((x) => x.key === key);
  if (!t) return;
  try {
    await ElMessageBox.confirm(`应用模板「${t.name}」会覆盖当前组件序列，确定继续？`, '提示', { type: 'warning' });
  } catch (e) {
    templateKey.value = '';
    return;
  }
  comps.value = (t.components || []).map((c) => ({
    name: c.name,
    title: c.title || c.name,
    value: c.value || {},
  }));
  activeIdx.value = -1;
  ElMessage.success('已应用模板，保存后生效');
};
const doPreview = () => {
  window.open(`/front-web/#/?diy_preview=1&cb=${Date.now()}`, '_blank');
};

onMounted(async () => {
  categoryList().then((r) => { cates.value = r.data || []; }).catch(() => {});
  diyComponents().then((r) => {
    const d = r.data || {};
    if (Array.isArray(d.components)) library.value = d.components;
    templates.value = d.templates || [];
  }).catch(() => {});
  const res = await diyRead(route.params.id);
  const d = res.data || {};
  page.value = d;
  status.value = Number(d.status) || 0;
  comps.value = (d.components || []).map((c) => ({
    name: c.name, title: c.title || c.name,
    value: Object.assign(JSON.parse(JSON.stringify(defaultValues[c.name] || {})), c.value || {}),
  }));
});
</script>

<style scoped>
.diy-editor { display: flex; flex-direction: column; height: calc(100vh - 90px); }
.editor-top { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fff; border-radius: 8px; }
.page-title { font-weight: 700; flex: 1; }
.editor-body { display: flex; gap: 12px; flex: 1; margin-top: 12px; min-height: 0; }
.panel { width: 300px; background: #fff; border-radius: 8px; padding: 12px; overflow-y: auto; flex-shrink: 0; }
.panel-title { font-weight: 600; margin-bottom: 10px; }
.lib-item { border: 1px solid #ebeef5; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; cursor: pointer; }
.lib-item:hover { border-color: #409eff; background: #ecf5ff; }
.lib-name { font-weight: 600; font-size: 13px; }
.lib-desc { font-size: 12px; color: #909399; margin-top: 2px; }
.canvas { flex: 1; background: #f0f2f5; border-radius: 8px; padding: 12px; overflow-y: auto; }
.comp-block { background: #fff; border: 1px solid #ebeef5; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; cursor: pointer; }
.comp-block.active { border-color: #409eff; box-shadow: 0 0 0 2px rgba(64,158,255,.15); }
.comp-head { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; }
.comp-preview { margin-top: 8px; font-size: 12px; }
.muted { color: #c0c4cc; }
.cfg-item { border-bottom: 1px dashed #ebeef5; padding-bottom: 8px; margin-bottom: 8px; }
</style>
