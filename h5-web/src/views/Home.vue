<template>
  <div class="page">
    <div class="home-head">
      <div class="brand">{{ site.name || 'MKSHOP 商城' }}</div>
      <div class="slogan">{{ site.desc || '自动发卡 · 虚拟充值 · 极速到账' }}</div>
      <div class="search" @click="$router.push('/category')">
        <span>🔍</span><span class="ph">搜索商品</span>
      </div>
    </div>

    <div class="wrap">
      <div v-if="notices.length" class="notice" @click="$router.push(`/notice/${notices[0].id}`)">
        <span class="notice-tag">公告</span>
        <span class="notice-text">{{ notices[0].title }}</span>
        <span class="muted">›</span>
      </div>

      <div class="quick">
        <div class="quick-item" @click="$router.push('/category')">
          <span class="qi">🗂️</span><span>全部分类</span>
        </div>
        <div class="quick-item" @click="$router.push('/orders')">
          <span class="qi">📋</span><span>我的订单</span>
        </div>
        <div class="quick-item" @click="$router.push('/member')">
          <span class="qi">💰</span><span>会员中心</span>
        </div>
        <div class="quick-item" @click="$router.push('/help')">
          <span class="qi">❓</span><span>帮助中心</span>
        </div>
      </div>

      <div class="section-head">
        <b>热门推荐</b>
        <span class="muted" @click="$router.push('/category')">查看全部 ›</span>
      </div>

      <div class="goods-list" v-if="hot.length">
        <div v-for="g in hot" :key="g.id" class="goods-row" @click="$router.push(`/goods/${g.id}`)">
          <div class="thumb">
            <img v-if="g.goods_img" :src="g.goods_img" :alt="g.goods_name" />
            <span v-else>🛍️</span>
          </div>
          <div class="info">
            <div class="name">{{ g.goods_name }}</div>
            <div class="muted" style="margin-top:4px">已售 {{ (Number(g.sales_num||0)+Number(g.virtual_num||0)) }}</div>
            <div class="meta">
              <div class="price"><small>¥</small>{{ g.real_price }}</div>
              <span v-if="Number(g.stock_num) <= 0" class="tag gray">缺货</span>
              <span v-else-if="g.ishot" class="tag red">热销</span>
              <span v-else class="tag">在售</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="empty"><div class="big">🛒</div>暂无商品</div>
    </div>
    <Tabbar />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Tabbar from '@/components/Tabbar.vue';
import { homeIndex } from '@/api';

const site = ref({});
const notices = ref([]);
const hot = ref([]);

onMounted(async () => {
  try {
    const res = await homeIndex();
    site.value = res.data?.site || {};
    notices.value = res.data?.notices || [];
    hot.value = res.data?.hot || [];
  } catch (e) { /* toast 已提示 */ }
});
</script>

<style scoped>
.home-head { background: linear-gradient(135deg, #1d6fe8, #57a3ff); color: #fff; padding: 20px 16px 26px; border-radius: 0 0 18px 18px; }
.brand { font-size: 22px; font-weight: 800; }
.slogan { font-size: 12px; opacity: .9; margin-top: 4px; }
.search { margin-top: 14px; height: 40px; background: #fff; border-radius: 20px; display: flex; align-items: center; gap: 8px; padding: 0 14px; color: #909399; }
.ph { font-size: 13px; }
.notice { display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 10px; padding: 10px 12px; margin-top: 10px; }
.notice-tag { background: #fff3e0; color: #e6a23c; border-radius: 4px; font-size: 11px; padding: 1px 5px; }
.notice-text { flex: 1; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.quick { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; background: #fff; border-radius: 10px; padding: 14px 6px; margin-top: 10px; }
.quick-item { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 12px; color: #606266; }
.qi { font-size: 22px; }
.section-head { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; font-size: 15px; }
</style>
