<template>
  <div class="page">
    <NavBar title="我的订单" />
    <div class="tabs">
      <div v-for="t in tabs" :key="t.value" class="tab" :class="{ active: status === t.value }" @click="pick(t.value)">
        {{ t.label }}
      </div>
    </div>
    <div class="wrap">
      <div class="goods-list" v-if="list.length">
        <div v-for="o in list" :key="o.id" class="order-card" @click="$router.push(`/order/${o.ordersn}`)">
          <div class="order-top">
            <span class="muted">{{ o.ordersn }}</span>
            <span :class="['tag', statusClass(o.status)]">{{ statusText(o.status) }}</span>
          </div>
          <div class="order-body">
            <div class="thumb">
              <img v-if="o.goods_img" :src="o.goods_img" :alt="o.goods_name" />
              <span v-else>🛍️</span>
            </div>
            <div class="order-info">
              <div class="name">{{ o.goods_name }}</div>
              <div class="muted" style="margin-top:4px">数量 ×{{ o.quantity }}</div>
            </div>
            <div class="price"><small>¥</small>{{ o.total_price }}</div>
          </div>
          <div class="order-foot">
            <span class="muted">{{ fmt(o.create_time) }}</span>
            <span class="detail-link">查看详情 ›</span>
          </div>
        </div>
      </div>
      <div v-else-if="!loading" class="empty"><div class="big">📋</div>暂无订单</div>
      <div class="pager" v-if="total > query.limit">
        <button :disabled="query.page <= 1" @click="pageTo(query.page - 1)">上一页</button>
        <span class="muted" style="align-self:center">{{ query.page }}/{{ Math.ceil(total / query.limit) }}</span>
        <button :disabled="query.page >= Math.ceil(total / query.limit)" @click="pageTo(query.page + 1)">下一页</button>
      </div>
    </div>
    <Tabbar />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import NavBar from '@/components/NavBar.vue';
import Tabbar from '@/components/Tabbar.vue';
import { orderIndex } from '@/api';

const tabs = [
  { label: '全部', value: '' },
  { label: '待支付', value: '0' },
  { label: '处理中', value: '1' },
  { label: '已完成', value: '3' },
];
const status = ref('');
const list = ref([]);
const total = ref(0);
const loading = ref(false);
const query = ref({ page: 1, limit: 10 });

const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '处理中', 2: '处理中', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知');
const statusClass = (s) => ({ 0: 'red', 1: '', 2: '', 3: 'green', 5: 'gray' }[s] || 'gray');
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false, month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—');

const load = async () => {
  loading.value = true;
  try {
    const params = { ...query.value };
    if (status.value !== '') params.status = Number(status.value);
    const res = await orderIndex(params);
    list.value = res.data?.list || [];
    total.value = res.data?.count || 0;
  } finally {
    loading.value = false;
  }
};
const pick = (v) => { status.value = v; query.value.page = 1; load(); };
const pageTo = (p) => { query.value.page = p; load(); };
onMounted(load);
</script>

<style scoped>
.tabs { display: flex; background: #fff; padding: 0 8px; border-bottom: 1px solid #f0f1f3; position: sticky; top: 48px; z-index: 10; }
.tab { flex: 1; text-align: center; padding: 11px 0; font-size: 13px; color: #606266; }
.tab.active { color: #1d6fe8; font-weight: 700; position: relative; }
.tab.active::after { content: ''; position: absolute; left: 50%; bottom: 0; width: 22px; height: 2px; background: #1d6fe8; transform: translateX(-50%); }
.order-card { background: #fff; border-radius: 10px; padding: 12px; }
.order-top { display: flex; justify-content: space-between; align-items: center; }
.order-body { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
.thumb { width: 64px; height: 64px; border-radius: 8px; background: #f6f7f9; display: flex; align-items: center; justify-content: center; font-size: 22px; overflow: hidden; flex-shrink: 0; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.order-info { flex: 1; min-width: 0; }
.name { font-weight: 600; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.order-foot { display: flex; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #f0f1f3; }
.detail-link { color: #1d6fe8; font-size: 13px; }
</style>
