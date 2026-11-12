<template>
  <div class="page">
    <div class="member-head">
      <div class="avatar">{{ initial }}</div>
      <div class="who">
        <div class="nick">{{ user.nickname || user.username || '会员' }}</div>
        <div class="acct">账号：{{ user.username }} · Lv.{{ user.level_id || 1 }}</div>
      </div>
      <div class="setting" @click="logout">退出</div>
    </div>

    <div class="wrap">
      <div class="wallet">
        <div>
          <div class="muted" style="color:rgba(255,255,255,.8)">账户余额</div>
          <div class="money">¥{{ user.balance || '0.0000' }}</div>
        </div>
        <div>
          <div class="muted" style="color:rgba(255,255,255,.8)">可提现佣金</div>
          <div class="money">¥{{ user.commission_money || '0.0000' }}</div>
        </div>
      </div>

      <div class="menu">
        <div class="menu-item" @click="$router.push('/orders')">
          <span>📋 我的订单</span><span class="muted">›</span>
        </div>
        <div class="menu-item" @click="$router.push('/money')">
          <span>💰 资金明细</span><span class="muted">›</span>
        </div>
        <div class="menu-item" @click="$router.push('/notices')">
          <span>📢 商城公告</span><span class="muted">›</span>
        </div>
        <div class="menu-item" @click="$router.push('/help')">
          <span>❓ 帮助中心</span><span class="muted">›</span>
        </div>
      </div>

      <div class="menu">
        <div class="menu-item" @click="$router.push('/')">
          <span>🛍️ 继续购物</span><span class="muted">›</span>
        </div>
      </div>
      <div class="muted" style="text-align:center; margin-top:18px">MKSHOP · 自动发卡商城</div>
    </div>
    <Tabbar />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Tabbar from '@/components/Tabbar.vue';
import { memberInfo, memberLogout } from '@/api';

const router = useRouter();
const user = ref({});
const initial = computed(() => (user.value.nickname || user.value.username || 'M').slice(0, 1).toUpperCase());

const logout = async () => {
  try { await memberLogout(); } catch (e) { /* ignore */ }
  localStorage.removeItem('mks_member_token');
  localStorage.removeItem('mks_member_info');
  router.replace('/login');
};

onMounted(async () => {
  try {
    const res = await memberInfo();
    user.value = res.data?.userinfo || {};
  } catch (e) { /* ignore */ }
});
</script>

<style scoped>
.member-head { background: linear-gradient(135deg, #1d6fe8, #57a3ff); color: #fff; padding: 26px 16px 34px; display: flex; align-items: center; gap: 12px; }
.avatar { width: 54px; height: 54px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; }
.who { flex: 1; }
.nick { font-size: 18px; font-weight: 700; }
.acct { font-size: 12px; opacity: .85; margin-top: 4px; }
.setting { font-size: 13px; opacity: .9; border: 1px solid rgba(255,255,255,.5); border-radius: 14px; padding: 4px 12px; }
.wallet { display: flex; justify-content: space-between; background: linear-gradient(135deg, #2b7bf0, #4f9bff); color: #fff; border-radius: 12px; padding: 16px; margin-top: -18px; position: relative; }
.money { font-size: 20px; font-weight: 800; margin-top: 4px; }
.menu { background: #fff; border-radius: 10px; margin-top: 10px; overflow: hidden; }
.menu-item { display: flex; justify-content: space-between; padding: 15px 14px; border-bottom: 1px solid #f6f7f9; font-size: 14px; }
.menu-item:last-child { border-bottom: 0; }
</style>
