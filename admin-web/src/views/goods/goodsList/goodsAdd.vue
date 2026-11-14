<template>
  <div class="goods-add" v-loading="loading">
    <div class="ga-head">
      <h3>
        {{ isEdit ? '编辑商品' : '新增商品' }}
        <span v-if="isEdit" class="ga-id">ID {{ form.id }}</span>
      </h3>
      <div class="ga-head-tip">新增与编辑同一页面；「上一步/下一步」只切页签不校验，「保存」才整表校验并一次提交。</div>
    </div>

    <el-card shadow="never" class="ga-card">
      <!-- 页签只当导航：内容用 v-show 常驻，保证一次 validate() 覆盖全部页签字段 -->
      <el-tabs v-model="activeTab" class="ga-tabs">
        <el-tab-pane v-for="t in tabs" :key="t.name" :label="t.label" :name="t.name" />
      </el-tabs>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" label-position="right">
        <!-- ── 基本信息 ── -->
        <div v-show="activeTab === 'base'">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="商品名称" prop="goods_name">
                <el-input v-model="form.goods_name" maxlength="1000" placeholder="前台标题，支持长标题" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="商品编号" prop="goods_no">
                <el-input v-model="form.goods_no" placeholder="留空自动生成" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="商品简称" prop="goods_short_name">
                <el-input v-model="form.goods_short_name" />
              </el-form-item>
            </el-col>

            <el-col :span="6">
              <el-form-item label="商品类型" prop="goods_type">
                <el-select v-model="form.goods_type" style="width: 100%">
                  <el-option v-for="s in typeOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="供货方式" prop="provide_type">
                <el-select v-model="form.provide_type" style="width: 100%">
                  <el-option v-for="s in provideOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="所属分类" prop="category_ids">
                <el-tree-select v-model="form.category_ids" :data="categoryTree" multiple check-strictly clearable
                  node-key="id" :props="{ label: 'name', value: 'id', children: 'children' }"
                  placeholder="可多选" style="width: 100%" />
              </el-form-item>
            </el-col>

            <el-col :span="6">
              <el-form-item label="品牌标签" prop="tags_id">
                <el-select v-model="form.tags_id" clearable style="width: 100%">
                  <el-option v-for="t in tagsOptions" :key="t.id" :label="t.name" :value="t.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="排序值" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="999999999" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="热销标记" prop="ishot">
                <el-switch v-model="form.ishot" :active-value="1" :inactive-value="0" />
                <span class="ga-tip">前台首页按热销优先排序</span>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="标题加粗" prop="goods_name_blod_flag">
                <el-switch v-model="form.goods_name_blod_flag" :active-value="1" :inactive-value="0" />
                <span class="ga-tip">当前只作用于后台列表显示</span>
              </el-form-item>
            </el-col>

            <el-col :span="6">
              <el-form-item label="面值/划线价" prop="face_value">
                <el-input-number v-model="form.face_value" :min="0" :precision="4" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="成本价" prop="cost_value">
                <el-input-number v-model="form.cost_value" :min="0" :precision="5" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="售价" prop="real_price">
                <el-input-number v-model="form.real_price" :min="0" :precision="4" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="标题颜色" prop="goods_name_color">
                <el-color-picker v-model="form.goods_name_color" />
                <span class="ga-tip">当前只作用于后台列表显示</span>
              </el-form-item>
            </el-col>

            <el-col :span="24">
              <el-form-item label="商品主图" prop="goods_img">
                <mks-pic-uploader v-model="mainImg" :max="1" tip="建议尺寸：800 * 800px" />
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="轮播图" prop="goods_imgs">
                <mks-pic-uploader v-model="carousel" :max="5" tip="建议尺寸：800 * 800px，最多上传 5 张；第一张作封面" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <!-- ── 库存管理 ── -->
        <div v-show="activeTab === 'stock'">
          <el-row :gutter="16">
            <el-col :span="24">
              <el-form-item label="卡密库绑定">
                <div class="ga-bind">
                  <el-select v-model="bindCateId" :disabled="!isEdit || form.goods_type !== 1" placeholder="选择卡密库"
                    style="width: 240px" clearable>
                    <el-option v-for="c in cardCates" :key="c.id" :label="`${c.name}（余 ${c.card_num ?? 0}）`" :value="c.id" />
                  </el-select>
                  <el-button type="primary" size="small" :disabled="!isEdit || form.goods_type !== 1"
                    :loading="binding" @click="doBindStock">绑定 / 换绑</el-button>
                  <span v-if="!isEdit" class="ga-tip">新增商品保存后才能绑定卡库</span>
                  <span v-else-if="form.goods_type !== 1" class="ga-tip">仅卡密商品需要绑定卡库</span>
                </div>
                <ul v-if="form.stock_bind" class="ga-bind-info">
                  <li>当前卡库<b>{{ form.stock_bind.name }}</b></li>
                  <li>卡库余量<b>{{ form.stock_bind.stock_num }}</b></li>
                  <li>卡库成本<b>{{ form.stock_bind.cost_value }}</b></li>
                </ul>
                <p v-else class="ga-note-inline">未绑定卡库：卡密商品下单会因取不到卡而失败。</p>
              </el-form-item>
            </el-col>

            <el-col :span="6">
              <el-form-item label="库存总数" prop="stock_num">
                <el-input-number v-model="form.stock_num" :min="0" :precision="0" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="库存预警值" prop="warn_num">
                <el-input-number v-model="form.warn_num" :min="0" :precision="0" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="虚拟销量" prop="virtual_num">
                <el-input-number v-model="form.virtual_num" :min="0" :precision="0" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="真实销量">
                <span class="ga-readonly">{{ form.sales_num ?? 0 }}</span>
                <span class="ga-tip">由订单累加，不可手改</span>
              </el-form-item>
            </el-col>
          </el-row>
          <p class="ga-note">
            卡密商品的「库存总数」在绑定或发货后按卡库余量重算；手工改它只对手工/自动发货类型有意义。
          </p>
        </div>

        <!-- ── 商品详情 ── -->
        <div v-show="activeTab === 'detail'">
          <el-row :gutter="16">
            <el-col :span="24">
              <el-form-item label="商品详情" prop="goods_info">
                <el-input v-model="form.goods_info" type="textarea" :rows="16"
                  placeholder="前台详情内容，按 HTML 源码保存（富文本编辑器尚未接入）" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <!-- ── 注意事项 ── -->
        <div v-show="activeTab === 'notice'">
          <el-row :gutter="16">
            <el-col :span="24">
              <el-form-item label="购买须知" prop="goods_notice">
                <el-input v-model="form.goods_notice" type="textarea" :rows="14" placeholder="下单前必须让买家看到的内容" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <!-- ── 使用说明 ── -->
        <div v-show="activeTab === 'explain'">
          <el-row :gutter="16">
            <el-col :span="24">
              <el-form-item label="下单提示" prop="order_notice">
                <el-input v-model="form.order_notice" type="textarea" :rows="10" placeholder="下单页/订单详情展示的操作步骤" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="充值/兑换地址" prop="recharge_url">
                <el-input v-model="form.recharge_url" placeholder="需要买家跳外部充填时填写" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="到账耗时" prop="recharge_time_value">
                <el-input-number v-model="form.recharge_time_value" :min="0" :precision="0" controls-position="right"
                  style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="耗时单位" prop="recharge_time_type">
                <el-select v-model="form.recharge_time_type" style="width: 100%">
                  <el-option label="不显示" :value="0" />
                  <el-option label="分钟" :value="1" />
                  <el-option label="小时" :value="2" />
                  <el-option label="天" :value="3" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <p class="ga-note">
            前台文案口径：`预计 {数值}{单位}内到账`，数值为 0 时不显示（front-web/src/views/Goods.vue:17）。
          </p>
        </div>

        <!-- ── 商品配置 ── -->
        <div v-show="activeTab === 'config'">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="最少购买" prop="start_count">
                <el-input-number v-model="form.start_count" :min="1" :precision="0" controls-position="right" style="width: 180px" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="最多购买" prop="end_count">
                <el-input-number v-model="form.end_count" :min="1" :precision="0" controls-position="right" style="width: 180px" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="数量档位" prop="num_count">
                <el-input v-model="form.num_count" placeholder="逗号分隔，如 1,2,5,10" style="max-width: 260px" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="倍数限购" prop="multiple">
                <el-input-number v-model="form.multiple" :min="0" :precision="0" controls-position="right" style="width: 180px" />
                <span class="ga-tip">0=不限；填 2 表示只能按 2 的倍数买</span>
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="下单输入项" prop="tpl_id">
                <el-select v-model="form.tpl_id" clearable placeholder="不使用模板" style="width: 100%">
                  <el-option v-for="t in tplOptions" :key="t.id" :label="t.name" :value="t.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="上架状态" prop="status">
                <el-select v-model="form.status" style="width: 100%">
                  <el-option v-for="s in statusOptions" :key="s.code" :label="s.label" :value="Number(s.code)" />
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="审核状态" prop="check_status">
                <el-select v-model="form.check_status" style="width: 100%">
                  <el-option label="待审核" :value="4" />
                  <el-option label="已审核" :value="9" />
                  <el-option label="已拒绝" :value="5" />
                </el-select>
                <span class="ga-tip">前台只展示已审核(9)与已下架(3)之外的可见集</span>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="参与返现" prop="is_rebate">
                <el-switch v-model="form.is_rebate" :active-value="1" :inactive-value="0" />
                <span class="ga-tip">关闭后该商品订单不产生分销佣金</span>
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="允许售后" prop="can_back">
                <el-switch v-model="form.can_back" :active-value="1" :inactive-value="0" />
                <span class="ga-tip">当前只写进订单快照，退款流程尚未按它拦截</span>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="渠道自动补单" prop="docking_order_bu">
                <el-switch v-model="form.docking_order_bu" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="补单次数上限" prop="order_bu_count">
                <el-input-number v-model="form.order_bu_count" :min="0" :precision="0" controls-position="right"
                  style="width: 180px" />
                <span class="ga-tip">0 = 后台「停止补单」</span>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="补单间隔(秒)" prop="auto_order_time">
                <el-input-number v-model="form.auto_order_time" :min="0" :precision="0" controls-position="right"
                  style="width: 180px" />
                <span class="ga-tip">0 = 不限间隔</span>
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-form>
    </el-card>

    <div class="ga-foot">
      <el-button type="primary" size="small" :style="{ visibility: tabIndex === 0 ? 'hidden' : 'visible' }"
        @click="prevStep">上一步</el-button>
      <el-button type="primary" size="small" @click="nextStep">下一步</el-button>
      <el-button type="primary" size="small" :loading="saving" @click="doSave">保存</el-button>
    </div>
  </div>
</template>

<script setup>
/**
 * 商品新增/编辑独立页（复杂实体编辑载体，对齐 KASUSHOU goodsAdd）
 * /goods/add 与 /goods/edit/:id 共用本组件（菜单 id 10/11 都指向这里）。
 * 字段只收后端真正消费掉的列：写进表单但没人读的属性等于骗人，宁可不放。
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
  goodsRead, goodsCreate, goodsSave, goodsSaveStock, goodsGoodsType, goodsProvideType, goodsStatusType,
  goodsDefaultTags, categoryList, goodsTplList, cardCateList,
} from '@/api';
import mksPicUploader from '@/components/mksPicUploader.vue';

const route = useRoute();
const router = useRouter();

const tabs = [
  { label: '基本信息', name: 'base' },
  { label: '库存管理', name: 'stock' },
  { label: '商品详情', name: 'detail' },
  { label: '注意事项', name: 'notice' },
  { label: '使用说明', name: 'explain' },
  { label: '商品配置', name: 'config' },
];
// 校验失败时按出错字段跳回所属页签
const propsOfTab = {
  base: ['goods_name', 'goods_no', 'goods_short_name', 'goods_type', 'provide_type', 'category_ids', 'tags_id',
    'sort', 'ishot', 'goods_name_blod_flag', 'face_value', 'cost_value', 'real_price', 'goods_name_color',
    'goods_img', 'goods_imgs'],
  stock: ['stock_num', 'warn_num', 'virtual_num'],
  detail: ['goods_info'],
  notice: ['goods_notice'],
  explain: ['order_notice', 'recharge_url', 'recharge_time_value', 'recharge_time_type'],
  config: ['start_count', 'end_count', 'num_count', 'multiple', 'tpl_id', 'status', 'check_status', 'is_rebate',
    'can_back', 'docking_order_bu', 'order_bu_count', 'auto_order_time'],
};
const tabOfProp = {};
for (const [tab, list] of Object.entries(propsOfTab)) list.forEach((p) => (tabOfProp[p] = tab));

const isEdit = computed(() => Number(route.params.id || 0) > 0);
const loading = ref(false);
const saving = ref(false);
const binding = ref(false);
const formRef = ref();
const activeTab = ref('base');
const tabIndex = computed(() => Math.max(0, tabs.findIndex((t) => t.name === activeTab.value)));

const form = reactive({
  id: 0,
  goods_name: '', goods_no: '', goods_short_name: '', goods_type: 1, provide_type: 1, category_ids: [],
  tags_id: null, sort: 0, ishot: 0, goods_name_blod_flag: 0, goods_name_color: '',
  face_value: 0, cost_value: 0, real_price: 0, goods_img: '', goods_imgs: '',
  stock_num: 0, warn_num: 0, virtual_num: 0, sales_num: 0, stock_bind: null,
  goods_info: '', goods_notice: '', order_notice: '', recharge_url: '',
  recharge_time_value: 0, recharge_time_type: 0,
  start_count: 1, end_count: 1, num_count: '1', multiple: 0, tpl_id: null, status: 1, check_status: 9,
  is_rebate: 1, can_back: 0, docking_order_bu: 0, order_bu_count: 1, auto_order_time: 0,
});

const rules = {
  goods_name: [{ required: true, message: '请填写商品名称', trigger: 'blur' }],
  goods_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  real_price: [{ required: true, message: '请填写售价', trigger: 'blur' }],
};

const typeOptions = ref([]);
const provideOptions = ref([]);
const statusOptions = ref([]);
const tagsOptions = ref([]);
const tplOptions = ref([]);
const categoryTree = ref([]);
const cardCates = ref([]);
const bindCateId = ref(null);

// 上传组件统一走数组模型，提交前再拼回逗号串
const carousel = ref([]);
const mainImg = computed({
  get: () => (form.goods_img ? [form.goods_img] : []),
  set: (v) => (form.goods_img = v[0] || ''),
});

function csvToList(v) {
  return String(v ?? '').split(',').map((s) => s.trim()).filter(Boolean);
}

function nextStep() {
  if (tabIndex.value < tabs.length - 1) activeTab.value = tabs[tabIndex.value + 1].name;
}
function prevStep() {
  if (tabIndex.value > 0) activeTab.value = tabs[tabIndex.value - 1].name;
}

async function loadEnums() {
  const [gt, pt, st, tg, tpl, ct, cc] = await Promise.all([
    goodsGoodsType(), goodsProvideType(), goodsStatusType(), goodsDefaultTags(),
    goodsTplList(), categoryList(), cardCateList(),
  ]);
  typeOptions.value = gt.data || [];
  provideOptions.value = pt.data || [];
  statusOptions.value = st.data || [];
  tagsOptions.value = tg.data || [];
  tplOptions.value = tpl.data || [];
  categoryTree.value = ct.data || [];
  cardCates.value = cc.data || [];
}

async function load() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const res = await goodsRead(route.params.id);
    const d = res.data || {};
    Object.keys(form).forEach((k) => {
      if (d[k] !== undefined && d[k] !== null) form[k] = d[k];
    });
    form.id = Number(d.id || route.params.id);
    form.category_ids = csvToList(d.category_ids).map(Number).filter((n) => n > 0);
    carousel.value = csvToList(d.goods_imgs);
    bindCateId.value = d.stock_bind?.card_cate_id || null;
    ['face_value', 'cost_value', 'real_price', 'stock_num', 'warn_num', 'virtual_num', 'sort', 'sales_num',
      'start_count', 'end_count', 'multiple', 'status', 'check_status',
      'recharge_time_value', 'recharge_time_type', 'order_bu_count', 'auto_order_time'].forEach((k) => {
      form[k] = Number(form[k] || 0);
    });
    // 0 在这两个下拉里等于「没选」，转成 null 才会显示占位符而不是裸值 0
    form.tags_id = Number(d.tags_id) > 0 ? Number(d.tags_id) : null;
    form.tpl_id = Number(d.tpl_id) > 0 ? Number(d.tpl_id) : null;
  } finally {
    loading.value = false;
  }
}

function payload() {
  const out = { ...form };
  delete out.stock_bind;
  delete out.sales_num; // 销量由订单累加，不接受回写
  out.category_ids = (form.category_ids || []).join(',');
  out.goods_imgs = carousel.value.filter(Boolean).join(',');
  out.tags_id = Number(form.tags_id || 0);
  out.tpl_id = Number(form.tpl_id || 0);
  if (!out.id) delete out.id;
  return out;
}

async function doSave() {
  try {
    await formRef.value.validate();
  } catch (e) {
    const first = Object.keys(e?.fields || e || {})[0];
    if (first && tabOfProp[first]) activeTab.value = tabOfProp[first];
    ElMessage.warning('请完善数据');
    return;
  }
  saving.value = true;
  try {
    const data = payload();
    const res = form.id ? await goodsSave(form.id, data) : await goodsCreate(data);
    ElMessage.success(res.msg || '保存成功');
    router.push('/goods/list');
  } finally {
    saving.value = false;
  }
}

async function doBindStock() {
  if (!bindCateId.value) return ElMessage.warning('请选择卡密库');
  binding.value = true;
  try {
    const res = await goodsSaveStock(form.id, bindCateId.value);
    ElMessage.success(res.msg || '库存已绑定卡密库');
    const r2 = await goodsRead(form.id);
    form.stock_bind = r2.data?.stock_bind || null;
    form.stock_num = Number(r2.data?.stock_num || 0);
  } finally {
    binding.value = false;
  }
}

onMounted(async () => {
  await loadEnums();
  await load();
});
</script>

<style scoped>
.goods-add {
  padding: var(--mks-main-pad);
}
.ga-head {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 10px;
}
.ga-head h3 {
  margin: 0;
  padding-left: 8px;
  font-size: var(--mks-font-size);
  font-weight: 600;
  border-left: 3px solid var(--mks-primary);
}
.ga-id {
  margin-left: 8px;
  color: var(--mks-text-secondary);
  font-weight: 400;
}
.ga-head-tip {
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-secondary);
}
.ga-card {
  border: 1px solid var(--mks-border-light);
}
.ga-tabs {
  margin-bottom: 6px;
}
.ga-foot {
  display: flex;
  justify-content: center;
  gap: 10px;
  padding: 14px 0 6px;
}
.ga-tip {
  margin-left: 8px;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-placeholder);
}
.ga-readonly {
  color: var(--mks-text-regular);
}
.ga-note {
  margin: 6px 0 0;
  padding-left: 110px;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-secondary);
}
.ga-note-inline {
  margin: 8px 0 0;
  font-size: var(--mks-font-size-table);
  color: var(--mks-warning);
}
.ga-bind {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  width: 100%;
}
.ga-bind-info {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 24px;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: var(--mks-font-size-table);
  color: var(--mks-text-secondary);
}
.ga-bind-info b {
  margin-left: 6px;
  color: var(--mks-text-regular);
}
</style>
