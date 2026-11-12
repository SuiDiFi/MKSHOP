<template>
  <div>
    <Topbar />
    <div class="wrap page-body">
      <div class="cate-layout">
        <aside class="cate-side">
          <div class="cate-title">全部分类</div>
          <div v-for="c in cates" :key="c.id" class="cate-item"
            :class="{ active: activeCate === c.id }" @click="pickCate(c.id)">
            {{ c.name }}
            <span class="cate-num">{{ c.goods_num || 0 }}</span>
          </div>
          <el-empty v-if="!cates.length" description="暂无分类" :image-size="50" />
        </aside>
        <section class="cate-main">
          <div class="cate-toolbar">
            <el-input v-model="keyword" placeholder="搜索商品" clearable style="max-width: 300px"
              @keyup.enter="doSearch">
              <template #append><el-button :icon="Search" @click="doSearch" /></template>
            </el-input>
            <el-radio-group v-model="sort" size="small" @change="doSearch">
              <el-radio-button value="default">综合</el-radio-button>
              <el-radio-button value="hot">热销</el-radio-button>
            </el-radio-group>
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
                    <el-tag v-if="Number(g.stock_num) <= 0" type="info" size="small">缺货</el-tag>
                    <el-tag v-else-if="g.ishot" type="danger" size="small" effect="light">热销</el-tag>
                  </div>
                </div>
              </div>
            </div>
            <el-empty v-else-if="!loading" description="该分类下暂无商品" style="margin-top: 60px" />
          </div>
          <div class="pager" v-if="total > query.limit">
            <el-pagination background layout="prev, pager, next" :total="total"
              :page-size="query.limit" v-model:current-page="query.page" @current-change="load" />
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Search } from '@element-plus/icons-vue';
import { categoryIndex, goodsIndex } from '@/api';

const route = useRoute();
const cates = ref([]);
const activeCate = ref(0);
const keyword = ref('');
const sort = ref('default');
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 12 });

const load = async () => {
  loading.value = true;
  try {
    const params = { ...query.value };
    if (activeCate.value) params.category_id = activeCate.value;
    if (keyword.value) params.keyword = keyword.value;
    const res = await goodsIndex(params);
    let rows = res.data?.list || [];
    if (sort.value === 'hot') {
      rows = [...rows].sort((a, b) => Number(b.sales_num || 0) - Number(a.sales_num || 0));
    }
    list.value = rows;
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const doSearch = () => { query.value.page = 1; load(); };
const pickCate = (id) => {
  activeCate.value = id;
  query.value.page = 1;
  load();
};

onMounted(async () => {
  const cateId = Number(route.query.cate || 0);
  if (cateId) activeCate.value = cateId;
  try {
    const res = await categoryIndex();
    cates.value = res.data?.list || [];
  } catch (e) { /* ignore */ }
  load();
});
</script>

<style scoped>
.cate-layout { display: flex; gap: 14px; margin-top: 14px; align-items: flex-start; }
.cate-side { width: 190px; background: #fff; border-radius: 8px; padding: 10px; flex-shrink: 0; }
.cate-title { font-weight: 600; padding: 6px 10px 10px; }
.cate-item { display: flex; justify-content: space-between; padding: 9px 10px; border-radius: 6px; cursor: pointer; font-size: 14px; color: #606266; }
.cate-item:hover { background: #f5f7fa; }
.cate-item.active { background: #ecf5ff; color: #409eff; font-weight: 600; }
.cate-num { color: #c0c4cc; font-size: 12px; }
.cate-main { flex: 1; min-width: 0; }
.cate-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 6px; }
@media (max-width: 768px) {
  .cate-layout { flex-direction: column; }
  .cate-side { width: 100%; display: flex; overflow-x: auto; gap: 6px; }
  .cate-title { display: none; }
  .cate-item { white-space: nowrap; }
}
</style>
