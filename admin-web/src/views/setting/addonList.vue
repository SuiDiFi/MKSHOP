<template>
  <mks-page>
    <template #alert>
      插件只扫描服务器本地 addons/ 目录，安装后才注入后台菜单与钩子，配置项来自插件自带 config.php；快捷入口：
      <a class="quick-link" @click="goCfg('/application/market')">应用市场</a>
    </template>

    <template #toolbar>
      <el-tag type="info" effect="plain" size="small">MVP：仅支持服务器本地 addons/ 目录安装</el-tag>
      <el-button size="small" plain @click="load">刷新</el-button>
    </template>

      <el-table :data="list" v-loading="loading" border size="small">
        <template #empty><mks-empty text="还没有可用插件" hint="把插件包放到服务器 addons/ 目录后点「刷新」重新扫描" /></template>
        <el-table-column prop="title" label="插件" min-width="160" show-overflow-tooltip />
        <el-table-column prop="name" label="标识" width="120" />
        <el-table-column prop="version" label="版本" width="90" />
        <el-table-column prop="author" label="作者" width="120" show-overflow-tooltip />
        <el-table-column label="监听钩子" min-width="200">
          <template #default="{ row }">
            <el-tag v-for="h in row.hooks || []" :key="h" size="small" effect="plain" style="margin-right: 4px">{{ h }}</el-tag>
            <span v-if="!(row.hooks || []).length">—</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="!row.installed" type="info" size="small">未安装</el-tag>
            <el-tag v-else-if="row.status === 1" type="success" size="small">已启用</el-tag>
            <el-tag v-else type="warning" size="small">已停用</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button v-if="!row.installed" link type="primary" @click="install(row)">安装</el-button>
            <template v-else>
              <el-button v-if="row.status === 1" link type="warning" @click="toggle(row, false)">停用</el-button>
              <el-button v-else link type="success" @click="toggle(row, true)">启用</el-button>
              <el-button link type="primary" @click="openConfig(row)">配置</el-button>
              <el-button link type="info" @click="openDetail(row)">详情</el-button>
              <el-button link type="danger" @click="uninstall(row)">卸载</el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>
    </mks-page>

    <!-- 配置 -->
    <el-dialog v-model="configVisible" :title="`插件配置 · ${configTitle}`" width="560px">
      <el-form v-if="schema.length" label-width="120px">
        <el-form-item v-for="f in schema" :key="f.name" :label="f.title || f.name">
          <el-input v-if="(f.type || 'input') === 'input'" v-model="form[f.name]" :placeholder="f.tip || ''" />
          <el-input v-else-if="f.type === 'textarea'" v-model="form[f.name]" type="textarea" :rows="3" :placeholder="f.tip || ''" />
          <el-input v-else-if="f.type === 'number'" v-model="form[f.name]" type="number" />
          <el-switch v-else-if="f.type === 'switch'" v-model="form[f.name]" active-value="1" inactive-value="0" />
          <el-select v-else-if="f.type === 'select'" v-model="form[f.name]" style="width: 100%">
            <el-option v-for="o in f.options || []" :key="o.value" :label="o.label" :value="String(o.value)" />
          </el-select>
          <el-input v-else v-model="form[f.name]" />
          <div v-if="f.tip" class="tip">{{ f.tip }}</div>
        </el-form-item>
      </el-form>
      <el-empty v-else description="该插件未提供 config.php 配置表单" :image-size="60" />
      <template #footer>
        <el-button @click="configVisible = false">取消</el-button>
        <el-button type="primary" :disabled="!schema.length" @click="saveConfig">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情 -->
    <el-dialog v-model="detailVisible" :title="`插件详情 · ${detail?.addon?.title || ''}`" width="620px">
      <el-descriptions v-if="detail" :column="2" border size="small">
        <el-descriptions-item label="标识">{{ detail.addon.name }}</el-descriptions-item>
        <el-descriptions-item label="版本">{{ detail.addon.version }}</el-descriptions-item>
        <el-descriptions-item label="作者">{{ detail.addon.author || '—' }}</el-descriptions-item>
        <el-descriptions-item label="状态">{{ detail.addon.status === 1 ? '已启用' : '已停用' }}</el-descriptions-item>
        <el-descriptions-item label="简介" :span="2">{{ detail.addon.description || '—' }}</el-descriptions-item>
        <el-descriptions-item label="钩子" :span="2">
          <el-tag v-for="h in detail.hooks" :key="h" size="small" effect="plain" style="margin-right: 4px">{{ h }}</el-tag>
          <span v-if="!detail.hooks.length">—</span>
        </el-descriptions-item>
        <el-descriptions-item label="注入菜单" :span="2">
          <div v-for="m in detail.menus" :key="m.id">#{{ m.id }} {{ m.name }}</div>
          <span v-if="!detail.menus.length">—</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  addonIndex, addonRead, addonInstall, addonEnable, addonDisable, addonUninstall, addonConfigSave,
} from '@/api';
import mksPage from '@/components/mksPage.vue';
import mksEmpty from '@/components/mksEmpty.vue';

const router = useRouter();
const goCfg = (p) => router.push(p);

const list = ref([]);
const loading = ref(false);
const configVisible = ref(false);
const detailVisible = ref(false);
const configTitle = ref('');
const configId = ref(0);
const schema = ref([]);
const form = ref({});
const detail = ref(null);

const load = async () => {
  loading.value = true;
  try {
    const res = await addonIndex();
    list.value = res.data.list || [];
  } finally {
    loading.value = false;
  }
};

const install = async (row) => {
  await ElMessageBox.confirm(`确认安装插件「${row.title}」？安装后将立即启用。`, '安装插件');
  await addonInstall(row.name);
  ElMessage.success('安装成功');
  load();
};

const toggle = async (row, enable) => {
  if (enable) {
    await addonEnable(row.db_id);
    ElMessage.success('已启用');
  } else {
    await addonDisable(row.db_id);
    ElMessage.success('已停用');
  }
  load();
};

const uninstall = async (row) => {
  await ElMessageBox.confirm(
    `确认卸载插件「${row.title}」？将移除其注入的后台菜单；插件数据表默认保留。`,
    '卸载插件',
    { type: 'warning' }
  );
  await addonUninstall(row.db_id, 0);
  ElMessage.success('已卸载');
  load();
};

const openConfig = async (row) => {
  const res = await addonRead(row.db_id);
  configId.value = row.db_id;
  configTitle.value = res.data.addon.title;
  schema.value = res.data.schema || [];
  form.value = { ...(res.data.config || {}) };
  configVisible.value = true;
};

const saveConfig = async () => {
  await addonConfigSave(configId.value, form.value);
  ElMessage.success('保存成功');
  configVisible.value = false;
};

const openDetail = async (row) => {
  const res = await addonRead(row.db_id);
  detail.value = res.data;
  detailVisible.value = true;
};

onMounted(load);
</script>

<style scoped>
.quick-link { color: var(--mks-primary); cursor: pointer; }
.tip {
  color: #909399;
  font-size: 12px;
  line-height: 18px;
}
</style>
