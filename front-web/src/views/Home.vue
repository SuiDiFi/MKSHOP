<template>
  <div>
    <Topbar />
    <div class="wrap page-body">
      <!-- DIY 模式：按装修配置渲染 -->
      <template v-if="diyMode">
        <el-input v-model="keyword" placeholder="搜索商品" clearable style="max-width: 320px; margin: 14px auto 0; display: flex"
          @keyup.enter="searchGo">
          <template #append><el-button :icon="Search" @click="searchGo" /></template>
        </el-input>
        <div v-for="(c, i) in diyComps" :key="i" style="margin-top: 14px">
          <component :is="renderer(c.name)" :value="c.value" />
        </div>
      </template>

      <!-- 默认布局：分类 + 搜索 + 商品网格 -->
      <template v-else>
        <div style="display:flex; gap:12px; margin-top:14px; flex-wrap:wrap; align-items:center;">
          <el-radio-group v-model="activeCate" @change="doSearch">
            <el-radio-button :value="0">全部</el-radio-button>
            <el-radio-button v-for="c in cates" :key="c.id" :value="c.id">{{ c.name }}</el-radio-button>
          </el-radio-group>
          <el-input v-model="keyword" placeholder="搜索商品" clearable style="width: 220px" @keyup.enter="doSearch">
            <template #append><el-button :icon="Search" @click="doSearch" /></template>
          </el-input>
        </div>

        <div v-loading="loading">
          <div v-if="list.length" class="goods-grid">
            <div v-for="g in list" :key="g.id" class="goods-card" @click="$router.push(`/goods/${g.id}`)">
              <div class="img">
                <img v-if="g.goods_img" :src="g.goods_img" :alt="g.goods_name" />
                <el-icon v-else :size="42" color="#c0c4cc"><GoodsFilled /></el-icon>
              </div>
              <div class="info">
                <div class="name">{{ g.goods_name }}</div>
                <div class="row">
                  <div class="price"><small>¥</small>{{ g.real_price }}</div>
                  <el-tag v-if="g.ishot" type="danger" size="small" effect="light">热销</el-tag>
                  <el-tag v-else-if="Number(g.stock_num) <= 0" type="info" size="small">缺货</el-tag>
                </div>
              </div>
            </div>
          </div>
          <el-empty v-else-if="!loading" description="暂无商品" style="margin-top: 60px" />
        </div>
        <div class="pager" v-if="total > query.limit">
          <el-pagination background layout="prev, pager, next" :total="total"
            :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue';
import { useRouter } from 'vue-router';
import { Search } from '@element-plus/icons-vue';
import { goodsCates, goodsIndex } from '@/api';
import { diyPcHome } from '@/api';

const router = useRouter();
const cates = ref([]);
const activeCate = ref(0);
const keyword = ref('');
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 12 });

// DIY
const diyMode = ref(false);
const diyComps = ref([]);
// 异步组件注册（必须 defineAsyncComponent 包装，裸 () => import() 在 :is 会被当函数组件执行）
const rendererMap = {
  Banner: defineAsyncComponent(() => import('@/components/diy/DBanner.vue')),
  Notice: defineAsyncComponent(() => import('@/components/diy/DNotice.vue')),
  Menus: defineAsyncComponent(() => import('@/components/diy/DMenus.vue')),
  GoodsList: defineAsyncComponent(() => import('@/components/diy/DGoodsList.vue')),
  RichText: defineAsyncComponent(() => import('@/components/diy/DRichText.vue')),
};
const renderer = (name) => rendererMap[name] || rendererMap.RichText;

const load = async () => {
  loading.value = true;
  try {
    const params = { ...query.value };
    if (activeCate.value) params.category_id = activeCate.value;
    if (keyword.value) params.keyword = keyword.value;
    const res = await goodsIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
const searchGo = () => {
  if (!keyword.value) return;
  // DIY 模式下搜索：切回默认列表布局并按关键词过滤
  diyMode.value = false;
  query.value.page = 1;
  load();
};

onMounted(async () => {
  // 优先加载 DIY 配置（已发布且有组件 → DIY 模式）
  try {
    const diy = await diyPcHome();
    const comps = diy.data?.components || [];
    if (comps.length > 0) {
      diyComps.value = comps;
      diyMode.value = true;
      return;
    }
  } catch (e) { /* 回退默认布局 */ }
  goodsCates().then((res) => { cates.value = res.data || []; }).catch(() => {});
  load();
});
</script>
