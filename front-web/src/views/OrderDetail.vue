<template>
  <div>
    <Topbar />
    <div class="wrap page-body" v-loading="loading" style="max-width: 760px">
      <el-card shadow="never" style="margin-top: 14px" v-if="order.ordersn">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <b>订单详情</b>
            <el-tag :type="statusType(order.status)">{{ statusText(order.status) }}</el-tag>
          </div>
        </template>
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="订单号" :span="2">{{ order.ordersn }}</el-descriptions-item>
          <el-descriptions-item label="商品">{{ order.goods_name }}</el-descriptions-item>
          <el-descriptions-item label="数量">{{ order.quantity }}</el-descriptions-item>
          <el-descriptions-item label="金额"><b style="color:#f56c6c">¥{{ order.total_price }}</b></el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ fmt(order.create_time) }}</el-descriptions-item>
          <el-descriptions-item v-if="order.recharge_account" label="充值账号">{{ order.recharge_account }}</el-descriptions-item>
          <el-descriptions-item v-if="Number(order.status) === 0" label="操作">
            <el-button type="primary" size="small" :loading="paying" @click="doPay">继续支付</el-button>
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="order.recharge_hints" style="margin-top: 12px; color: #e6a23c; font-size: 13px">
          {{ order.recharge_hints }}
        </div>

        <!-- 卡密提货 -->
        <template v-if="Number(order.status) === 3">
          <div class="section-title">卡密信息</div>
          <div v-for="(c, i) in cards" :key="i" class="card-item">
            <div>卡号：{{ c.card_no || '—' }}</div>
            <div>卡密：{{ c.card_password || '—' }}</div>
          </div>
          <div style="margin-top: 10px; text-align: right">
            <el-button size="small" @click="copyAll">一键复制</el-button>
          </div>
        </template>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { orderQuery, orderPay } from '@/api';

const route = useRoute();
const order = ref({});
const cards = ref([]);
const loading = ref(true);
const paying = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const res = await orderQuery({ ordersn: route.params.sn });
    order.value = res.data || {};
    cards.value = res.data?.cards || [];
  } finally {
    loading.value = false;
  }
};
const doPay = async () => {
  paying.value = true;
  try {
    await orderPay({ ordersn: order.value.ordersn });
    ElMessage.success('支付成功');
    load();
  } catch (e) { /* ignore */ } finally {
    paying.value = false;
  }
};
const copyAll = () => {
  const text = cards.value.map((c) => `${c.card_no || ''}----${c.card_password || ''}`).join('\n');
  navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制'));
};
const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '处理中', 2: '正在处理', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知');
const statusType = (s) => ({ 0: 'warning', 1: 'primary', 2: 'primary', 3: 'success', 5: 'info' }[s] || 'info');
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(load);
</script>
