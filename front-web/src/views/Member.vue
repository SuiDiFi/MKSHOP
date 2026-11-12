<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 960px">
      <div class="member-head" v-loading="loading">
        <div class="avatar">{{ initial }}</div>
        <div class="member-main">
          <div class="nickname">{{ user.nickname || user.username || '会员' }}</div>
          <div class="member-meta">
            <span>账号：{{ user.username }}</span>
            <span>等级：Lv.{{ user.level_id || 1 }}</span>
            <span>积分：{{ user.score || 0 }}</span>
          </div>
        </div>
        <div class="balance-box">
          <div class="balance-label">账户余额</div>
          <div class="balance-value">¥{{ user.balance || '0.0000' }}</div>
        </div>
      </div>

      <div class="quick-grid">
        <div v-for="q in quicks" :key="q.path" class="quick-item" @click="go(q.path)">
          <el-icon :size="26" :color="q.color"><component :is="q.icon" /></el-icon>
          <div class="quick-name">{{ q.name }}</div>
        </div>
      </div>

      <el-card shadow="never" style="margin-top: 14px">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <b>最近订单</b>
            <el-button link type="primary" @click="$router.push('/orders')">全部订单</el-button>
          </div>
        </template>
        <el-table :data="orders" v-loading="orderLoading" @row-click="(r) => $router.push(`/order/${r.ordersn}`)" style="cursor:pointer">
          <el-table-column prop="ordersn" label="订单号" min-width="180" show-overflow-tooltip />
          <el-table-column prop="goods_name" label="商品" min-width="150" show-overflow-tooltip />
          <el-table-column label="金额" width="100">
            <template #default="{ row }"><b style="color:#f56c6c">¥{{ row.total_price }}</b></template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="时间" width="160">
            <template #default="{ row }">{{ fmt(row.create_time) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!orderLoading && !orders.length" description="暂无订单" />
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  Wallet, List, Coin, Share, Tickets, Bell, QuestionFilled, Service, Shop,
} from '@element-plus/icons-vue';
import { memberInfo, orderIndex } from '@/api';
import { useUserStore } from '@/store/user';

const router = useRouter();
const store = useUserStore();
const user = ref({});
const orders = ref([]);
const loading = ref(true);
const orderLoading = ref(false);

const initial = computed(() => (user.value.nickname || user.value.username || 'M').slice(0, 1).toUpperCase());
const quicks = computed(() => {
  const base = [
    { name: '我的订单', path: '/orders', icon: List, color: '#409eff' },
    { name: '资金明细', path: '/money', icon: Wallet, color: '#67c23a' },
    { name: '分销中心', path: '/commission', icon: Share, color: '#e6a23c' },
    { name: '提现记录', path: '/commission?tab=withdraw', icon: Tickets, color: '#f56c6c' },
    { name: '商城公告', path: '/notices', icon: Bell, color: '#909399' },
    { name: '帮助中心', path: '/help', icon: QuestionFilled, color: '#409eff' },
    { name: '在线客服', path: '/kefu', icon: Service, color: '#67c23a' },
    { name: '账户余额', path: '/money', icon: Coin, color: '#e6a23c' },
  ];
  if (Number(user.value.has_vip) === 1) {
    base.unshift({ name: '分站管理', path: '/vip', icon: Shop, color: '#f56c6c' });
  }
  return base;
});

const go = (path) => router.push(path);
const statusText = (s) => ({ '-1': '已取消', 0: '待支付', 1: '处理中', 2: '正在处理', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知');
const statusType = (s) => ({ 0: 'warning', 1: 'primary', 2: 'primary', 3: 'success', 5: 'info' }[s] || 'info');
const fmt = (t) => (t > 0 ? new Date(t * 1000).toLocaleString('zh-CN', { hour12: false }) : '—');

onMounted(async () => {
  try {
    const res = await memberInfo();
    user.value = res.data?.userinfo || {};
    store.userinfo = user.value;
  } catch (e) { /* ignore */ } finally {
    loading.value = false;
  }
  orderLoading.value = true;
  try {
    const res = await orderIndex({ page: 1, limit: 5 });
    orders.value = res.data?.list || [];
  } catch (e) { /* ignore */ } finally {
    orderLoading.value = false;
  }
});
</script>

<style scoped>
.member-head { display: flex; align-items: center; gap: 16px; margin-top: 14px; padding: 22px; border-radius: 10px;
  background: linear-gradient(120deg, #409eff, #79bbff); color: #fff; }
.avatar { width: 58px; height: 58px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex;
  align-items: center; justify-content: center; font-size: 24px; font-weight: 700; }
.member-main { flex: 1; }
.nickname { font-size: 20px; font-weight: 700; }
.member-meta { font-size: 13px; opacity: .9; margin-top: 6px; display: flex; gap: 14px; flex-wrap: wrap; }
.balance-box { text-align: right; }
.balance-label { font-size: 13px; opacity: .85; }
.balance-value { font-size: 24px; font-weight: 700; margin-top: 2px; }
.quick-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 14px; }
.quick-item { background: #fff; border-radius: 8px; padding: 18px 10px; text-align: center; cursor: pointer; transition: box-shadow .2s; }
.quick-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.quick-name { font-size: 13px; margin-top: 8px; color: #606266; }
@media (max-width: 768px) {
  .quick-grid { grid-template-columns: repeat(4, 1fr); }
  .member-head { flex-wrap: wrap; }
  .balance-box { width: 100%; text-align: left; }
}
</style>
