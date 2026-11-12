<template>
  <div class="page plain">
    <NavBar title="订单详情" />
    <div class="wrap" v-if="order.ordersn">
      <div class="status-card">
        <div class="status">{{ statusText(order.status) }}</div>
        <div class="muted" style="color:rgba(255,255,255,.85); margin-top:6px">{{ statusHint }}</div>
      </div>

      <div class="card">
        <div class="line"><span class="muted">订单号</span><span>{{ order.ordersn }}</span></div>
        <div class="line"><span class="muted">商品</span><span>{{ order.goods_name }}</span></div>
        <div class="line"><span class="muted">数量</span><span>×{{ order.quantity }}</span></div>
        <div class="line"><span class="muted">金额</span><span class="price">¥{{ order.total_price }}</span></div>
        <div class="line"><span class="muted">支付方式</span><span>{{ order.pay_type === 'balance' ? '余额支付' : (order.pay_type || '—') }}</span></div>
        <div class="line" v-if="order.recharge_account"><span class="muted">充值账号</span><span>{{ order.recharge_account }}</span></div>
        <div class="line"><span class="muted">下单时间</span><span>{{ fmt(order.create_time) }}</span></div>
      </div>

      <div class="card" v-if="order.status === 0">
        <button class="btn block" :disabled="paying" @click="doPay">{{ paying ? '支付中…' : '余额支付' }}</button>
      </div>

      <div class="card" v-if="order.cards && order.cards.length">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
          <b>卡密信息（{{ order.cards.length }}）</b>
          <span class="muted">点击可复制</span>
        </div>
        <div v-for="(c, i) in order.cards" :key="c.id" class="cardkey" @click="copy(c)">
          <div class="muted">#{{ i + 1 }}</div>
          <div class="ck-line">卡号：{{ c.card_no }}</div>
          <div class="ck-line">卡密：{{ c.card_password }}</div>
        </div>
      </div>

      <div class="card" v-if="order.status === 3 && (!order.cards || !order.cards.length)">
        <div class="muted">该商品为虚拟直充，已完成充值，无需卡密。</div>
        <div v-if="order.recharge_url" style="margin-top:8px">
          <a :href="order.recharge_url" target="_blank" style="color:#1d6fe8">前往充值链接 ›</a>
        </div>
      </div>
    </div>
    <div v-else class="empty"><div class="big">🔍</div>订单不存在</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import NavBar from '@/components/NavBar.vue';
import { orderQuery, orderPay } from '@/api';
import { toast } from '@/utils/request';

const route = useRoute();
const order = ref({});
const paying = ref(false);

const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '处理中', 2: '处理中', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知');
const statusHint = computed(() => ({
  0: '请尽快完成支付',
  1: '系统正在发货，请稍候',
  2: '系统正在发货，请稍候',
  3: '订单已完成，请查收卡密',
  5: '订单已退款',
}[order.value.status] || '订单已关闭'));
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

const load = async () => {
  try {
    const res = await orderQuery(route.params.sn);
    order.value = res.data || {};
  } catch (e) { /* ignore */ }
};
const doPay = async () => {
  paying.value = true;
  try {
    await orderPay({ ordersn: order.value.ordersn });
    toast('支付成功');
    await load();
  } catch (e) { /* toast 已提示 */ } finally {
    paying.value = false;
  }
};
const copy = async (c) => {
  const text = `卡号：${c.card_no}\n卡密：${c.card_password}`;
  try {
    await navigator.clipboard.writeText(text);
    toast('已复制卡密');
  } catch (e) {
    toast(text);
  }
};

onMounted(load);
</script>

<style scoped>
.status-card { background: linear-gradient(135deg, #1d6fe8, #57a3ff); color: #fff; border-radius: 12px; padding: 20px 16px; margin-top: 12px; }
.status { font-size: 20px; font-weight: 800; }
.line { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; font-size: 13px; border-bottom: 1px solid #f6f7f9; }
.line:last-child { border-bottom: 0; }
.cardkey { background: #f7f9fc; border: 1px dashed #d6e2f5; border-radius: 8px; padding: 10px; margin-top: 8px; font-size: 13px; }
.ck-line { margin-top: 4px; word-break: break-all; }
</style>
