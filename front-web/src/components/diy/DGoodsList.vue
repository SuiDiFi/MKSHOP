<template>
  <div v-if="list.length" class="floor">
    <div class="floor-head">
      <span class="floor-title">{{ v.title || '商品推荐' }}</span>
      <a v-if="v.source === 'cate' && v.cate_id" :href="'#/goods/' + v.cate_id" class="floor-more">更多 ›</a>
    </div>
    <div class="floor-grid">
      <div v-for="g in list" :key="g.id" class="floor-card" @click="$router.push(`/goods/${g.id}`)">
        <div class="floor-img">
          <img v-if="g.goods_img" :src="g.goods_img" :alt="g.goods_name" />
          <el-icon v-else :size="30" color="#c0c4cc"><GoodsFilled /></el-icon>
        </div>
        <div class="floor-name">{{ g.goods_name }}</div>
        <div class="floor-price"><small>¥</small>{{ g.real_price }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { goodsIndex } from '@/api';

const props = defineProps({ value: { type: Object, default: () => ({}) } });
const v = computed(() => props.value || {});
const list = ref([]);

const load = async () => {
  const params = { page: 1, limit: Math.min(12, Math.max(1, Number(v.value.num) || 4)) };
  if (v.value.source === 'ids' && v.value.goods_ids) {
    params.ids = v.value.goods_ids;
  } else if (v.value.cate_id) {
    params.category_id = v.value.cate_id;
  }
  const res = await goodsIndex(params);
  list.value = res.data?.list || [];
};

onMounted(load);
watch(() => v.value, load, { deep: true });
</script>

<style scoped>
.floor { background: #fff; border-radius: 8px; padding: 14px; }
.floor-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.floor-title { font-size: 16px; font-weight: 700; }
.floor-more { font-size: 13px; color: #909399; }
.floor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.floor-card { border: 1px solid #f0f0f0; border-radius: 8px; overflow: hidden; cursor: pointer; transition: box-shadow .2s; }
.floor-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); }
.floor-img { height: 120px; background: linear-gradient(135deg, #e8f3ff, #f0f9eb); display: flex; align-items: center; justify-content: center; }
.floor-img img { max-width: 100%; max-height: 100%; object-fit: cover; }
.floor-name { font-size: 13px; padding: 8px 10px 4px; height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.floor-price { color: #f56c6c; font-weight: 700; padding: 0 10px 10px; }
.floor-price small { font-size: 12px; }
</style>
