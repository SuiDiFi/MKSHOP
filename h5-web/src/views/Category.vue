<template>
  <div class="page">
    <NavBar title="商品分类" />
    <div class="cate-wrap">
      <aside class="side">
        <div class="side-item" :class="{ active: activeCate === 0 }" @click="pick(0)">全部</div>
        <div v-for="c in cates" :key="c.id" class="side-item" :class="{ active: activeCate === c.id }" @click="pick(c.id)">
          {{ c.name }}
        </div>
      </aside>
      <section class="main">
        <div class="search">
          <input v-model="keyword" class="input" placeholder="搜索商品" style="height:38px" @keyup.enter="doSearch" />
        </div>
        <div class="goods-list">
          <div v-for="g in list" :key="g.id" class="goods-row" @click="$router.push(`/goods/${g.id}`)">
            <div class="thumb">
              <img v-if="g.goods_img" :src="g.goods_img" :alt="g.goods_name" />
              <span v-else>🛍️</span>
            </div>
            <div class="info">
              <div class="name">{{ g.goods_name }}</div>
              <div class="meta">
                <div class="price"><small>¥</small>{{ g.real_price }}</div>
                <span v-if="Number(g.stock_num) <= 0" class="tag gray">缺货</span>
                <span v-else class="tag green">在售</span>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!loading && !list.length" class="empty"><div class="big">📦</div>该分类暂无商品</div>
        <div class="pager" v-if="total > query.limit">
          <button :disabled="query.page <= 1" @click="pageTo(query.page - 1)">上一页</button>
          <span class="muted" style="align-self:center">{{ query.page }}/{{ Math.ceil(total / query.limit) }}</span>
          <button :disabled="query.page >= Math.ceil(total / query.limit)" @click="pageTo(query.page + 1)">下一页</button>
        </div>
      </section>
    </div>
    <Tabbar />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import NavBar from '@/components/NavBar.vue';
import Tabbar from '@/components/Tabbar.vue';
import { categoryIndex, goodsIndex } from '@/api';

const cates = ref([]);
const activeCate = ref(0);
const keyword = ref('');
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 20 });

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
const pick = (id) => { activeCate.value = id; query.value.page = 1; load(); };
const doSearch = () => { query.value.page = 1; load(); };
const pageTo = (p) => { query.value.page = p; load(); };

onMounted(async () => {
  try {
    const res = await categoryIndex();
    cates.value = res.data?.list || [];
  } catch (e) { /* ignore */ }
  load();
});
</script>

<style scoped>
.cate-wrap { display: flex; min-height: calc(100vh - 104px); }
.side { width: 92px; background: #fff; border-right: 1px solid #f0f1f3; padding-bottom: 20px; }
.side-item { padding: 13px 6px; text-align: center; font-size: 13px; color: #606266; }
.side-item.active { background: #f4f5f7; color: #1d6fe8; font-weight: 700; border-left: 3px solid #1d6fe8; }
.main { flex: 1; min-width: 0; padding: 10px; }
</style>
